<?php

namespace Tests\Feature;

use App\Models\CashMutation;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCheckoutTest extends TestCase
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
            'plate_number' => 'B 1234 XY',
            'customer_name' => 'Test',
        ]);

        return Transaction::first();
    }

    private function makeProduct(int $stock = 10, float $hpp = 30000, float $sell = 45000): Product
    {
        return Product::create([
            'code_sku' => 'P'.uniqid(),
            'name' => 'Produk Uji',
            'purchase_price' => $hpp,
            'selling_price' => $sell,
            'stock' => $stock,
            'min_stock' => 1,
        ]);
    }

    public function test_checkout_produk_mengurangi_stok_dan_mencatat_sale(): void
    {
        $product = $this->makeProduct(stock: 10);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 3]],
        ])->assertRedirect();

        $this->assertEquals(7, $product->fresh()->stock);

        $this->assertDatabaseHas('stock_histories', [
            'product_id' => $product->id,
            'transaction_id' => $wo->id,
            'type' => 'sale',
            'qty_change' => -3,
            'reason' => 'Penjualan',
        ]);
    }

    public function test_snapshot_harga_tidak_berubah_walau_master_diedit(): void
    {
        $product = $this->makeProduct(sell: 45000, hpp: 30000);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ]);

        $product->update(['selling_price' => 99999, 'purchase_price' => 88888]);

        $detail = $wo->fresh()->details->first();
        $this->assertEquals(45000, (float) $detail->selling_price);
        $this->assertEquals(30000, (float) $detail->purchase_price);
    }

    public function test_komisi_mengikuti_rasio_mekanik_dan_pembagian_manual(): void
    {
        $andi = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80]);
        $budi = Mechanic::create(['name' => 'Budi', 'mechanic_percentage' => 80]);
        $cici = Mechanic::create(['name' => 'Cici', 'mechanic_percentage' => 80]);
        $wo = $this->makeWo();

        // Jasa 100rb, rasio 80% -> porsi mekanik 80rb, bengkel 20rb.
        // Kasir bagi manual: 40rb + 25rb + 10rb = 75rb; sisa 5rb jadi bengkel -> bengkel 25rb.
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'services' => [[
                'service_name' => 'Servis Besar',
                'price' => 100000,
                'shares' => [
                    ['mechanic_id' => $andi->id, 'amount' => 40000],
                    ['mechanic_id' => $budi->id, 'amount' => 25000],
                    ['mechanic_id' => $cici->id, 'amount' => 10000],
                ],
            ]],
        ])->assertRedirect();

        $service = $wo->fresh()->services->first();
        $this->assertEquals(80000, (float) $service->mechanic_fee);
        $this->assertEquals(25000, (float) $service->bengkel_fee, 'Sisa 5rb harus jadi milik bengkel');

        $this->assertEquals(40000, (float) $service->shares->firstWhere('mechanic_id', $andi->id)->share_amount);
        $this->assertEquals(25000, (float) $service->shares->firstWhere('mechanic_id', $budi->id)->share_amount);
        $this->assertEquals(10000, (float) $service->shares->firstWhere('mechanic_id', $cici->id)->share_amount);
    }

    public function test_pembagian_melebihi_porsi_mekanik_ditolak(): void
    {
        $andi = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80]);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'services' => [[
                'service_name' => 'Servis',
                'price' => 100000,
                'shares' => [['mechanic_id' => $andi->id, 'amount' => 90000]], // > 80rb
            ]],
        ])->assertSessionHas('error');

        $this->assertEquals(0, $wo->fresh()->services()->count());
    }

    public function test_produk_luar_tidak_masuk_stok_dan_membuat_kas_keluar(): void
    {
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'external_products' => [[
                'name' => 'Kampas Rem',
                'qty' => 2,
                'purchase_price' => 25000,
                'selling_price' => 40000,
            ]],
        ])->assertRedirect();

        // Kas keluar = HPP x qty = 50.000.
        $this->assertDatabaseHas('cash_mutations', [
            'type' => 'out',
            'amount' => 50000,
            'transaction_id' => $wo->id,
        ]);

        // Tidak ada stock_history untuk produk luar.
        $this->assertEquals(0, StockHistory::count());
    }

    public function test_transaksi_final_tidak_bisa_di_checkout_ulang(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        $payload = [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ];

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", $payload)->assertRedirect();
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", $payload)->assertSessionHas('error');

        // Stok hanya berkurang sekali.
        $this->assertEquals(9, $product->fresh()->stock);
    }

    public function test_stok_tidak_cukup_ditolak_dan_rollback(): void
    {
        $product = $this->makeProduct(stock: 2);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 5]],
        ])->assertSessionHas('error');

        // Rollback: stok tetap, tidak ada detail tersimpan.
        $this->assertEquals(2, $product->fresh()->stock);
        $this->assertEquals(0, $wo->fresh()->details()->count());
        $this->assertEquals(0, CashMutation::count());
    }
}
