<?php

namespace Tests\Feature;

use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test aturan bisnis kritis (AGENTS.md). Fase 9 - Testing & Polish.
 */
class BusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::factory()->kasir()->create();
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
    }

    private function makeWo(): Transaction
    {
        $this->actingAs($this->kasir)->post('/work-orders', [
            'plate_number' => 'B 1 XY',
            'customer_name' => 'C',
        ]);

        return Transaction::latest('id')->first();
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'code_sku' => 'P'.uniqid(),
            'name' => 'Produk',
            'purchase_price' => 10000,
            'selling_price' => 20000,
            'stock' => 20,
            'min_stock' => 1,
        ]);
    }

    public function test_commission_service_memakai_rasio_per_mekanik_bukan_flat(): void
    {
        $service = app(CommissionService::class);

        // Mekanik A rasio 90%, Mekanik B rasio 70% - hasil harus berbeda.
        $a = Mechanic::create(['name' => 'A', 'mechanic_percentage' => 90]);
        $b = Mechanic::create(['name' => 'B', 'mechanic_percentage' => 70]);

        $splitA = $service->split(100000, $a);
        $splitB = $service->split(100000, $b);

        $this->assertSame(90000.0, $splitA['mechanic_fee']);
        $this->assertSame(10000.0, $splitA['bengkel_fee']);
        $this->assertSame(70000.0, $splitB['mechanic_fee']);
        $this->assertSame(30000.0, $splitB['bengkel_fee']);
    }

    public function test_commission_fallback_ke_rasio_bengkel_global(): void
    {
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 25);

        $split = app(CommissionService::class)->split(100000, null);

        // Tanpa mekanik: porsi mekanik = 100 - 25 = 75%.
        $this->assertSame(75000.0, $split['mechanic_fee']);
        $this->assertSame(25000.0, $split['bengkel_fee']);
        $this->assertSame(25.0, $split['bengkel_percentage']);
    }

    public function test_porsi_jasa_memakai_rasio_mekanik_pertama_yang_dipilih(): void
    {
        $b = Mechanic::create(['name' => 'B', 'mechanic_percentage' => 70]);
        $a = Mechanic::create(['name' => 'A', 'mechanic_percentage' => 90]);
        $wo = $this->makeWo();

        // Mekanik pertama = B (70%) -> porsi mekanik 70rb, bengkel 30rb.
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'services' => [[
                'service_name' => 'Servis',
                'price' => 100000,
                'shares' => [
                    ['mechanic_id' => $b->id, 'amount' => 40000],
                    ['mechanic_id' => $a->id, 'amount' => 20000],
                ],
            ]],
        ])->assertRedirect();

        $service = $wo->fresh()->services->first();
        $this->assertSame(70000.0, (float) $service->mechanic_fee);
        // Bengkel = 30rb + sisa 10rb (70 - 60) = 40rb.
        $this->assertSame(40000.0, (float) $service->bengkel_fee);
    }

    public function test_snapshot_rasio_mekanik_tersimpan_per_share(): void
    {
        $a = Mechanic::create(['name' => 'A', 'mechanic_percentage' => 85]);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'services' => [[
                'service_name' => 'Servis',
                'price' => 100000,
                'shares' => [['mechanic_id' => $a->id, 'amount' => 50000]],
            ]],
        ])->assertRedirect();

        $share = $wo->fresh()->mechanicShares->first();
        $this->assertSame(85.0, (float) $share->mechanic_ratio);

        // Ubah rasio master setelah transaksi; snapshot tidak berubah.
        $a->update(['mechanic_percentage' => 50]);
        $this->assertSame(85.0, (float) $share->fresh()->mechanic_ratio);
    }

    public function test_transaksi_final_tidak_bisa_diupdate_status(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $this->assertTrue($wo->fresh()->isFinal());

        // Coba ubah status setelah final -> ditolak.
        $this->actingAs($this->kasir)
            ->patch("/work-orders/{$wo->id}/status", ['work_status' => 'proses'])
            ->assertSessionHas('error');

        $this->assertSame('selesai', $wo->fresh()->work_status);
    }

    public function test_produk_luar_tidak_pernah_masuk_stock_histories(): void
    {
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'external_products' => [[
                'name' => 'Kampas Rem', 'qty' => 1, 'purchase_price' => 25000, 'selling_price' => 40000,
            ]],
        ])->assertRedirect();

        $this->assertSame(0, StockHistory::count());
    }

    public function test_stok_sale_terhubung_ke_transaction_id(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 2]],
        ])->assertRedirect();

        $history = StockHistory::where('type', 'sale')->first();
        $this->assertNotNull($history);
        $this->assertSame($wo->id, $history->transaction_id);
        $this->assertSame(-2, $history->qty_change);
    }

    public function test_work_status_dan_payment_status_dua_dimensi_terpisah(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        // Dikerjakan tapi belum bayar -> bukan final.
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'belum_bayar',
            'work_status' => 'proses',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $fresh = $wo->fresh();
        $this->assertSame('proses', $fresh->work_status);
        $this->assertSame('belum_bayar', $fresh->payment_status);
        $this->assertFalse($fresh->isFinal());
        $this->assertNull($fresh->finalized_at);
    }

    public function test_finalisasi_selesai_diblokir_bila_belum_lunas(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        // Selesai kerja tapi belum bayar -> ditolak, bukan final.
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'belum_bayar',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertSessionHas('error');

        $fresh = $wo->fresh();
        $this->assertFalse($fresh->isFinal());
        $this->assertNull($fresh->finalized_at);
        $this->assertSame(0, $fresh->details()->count());
    }

    public function test_finalisasi_selesai_diblokir_bila_dp_kurang_dari_total(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        // Jual 1 produk; DP hanya setengah -> sisa belum dibayar.
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'dp',
            'paid_amount' => (float) $product->selling_price / 2,
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertSessionHas('error');

        $fresh = $wo->fresh();
        $this->assertFalse($fresh->isFinal());
        $this->assertNull($fresh->finalized_at);
    }
}
