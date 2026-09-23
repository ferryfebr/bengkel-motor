<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft transaksi sementara & alur pembayaran DP (fase revisi POS).
 */
class PosDraftTest extends TestCase
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

    public function test_simpan_draft_menyimpan_rincian_tanpa_memotong_stok(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        $response = $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'payment_method' => 'cash',
            'payment_status' => 'belum_bayar',
            'work_status' => 'proses',
            'products' => [['product_id' => $product->id, 'qty' => 3]],
        ])->assertRedirect();

        $fresh = $wo->fresh();
        $this->assertSame(1, $fresh->details()->count());
        $this->assertSame('belum_bayar', $fresh->payment_status);
        $this->assertSame('proses', $fresh->work_status);
        $this->assertFalse($fresh->isFinal());
        $this->assertNull($fresh->finalized_at);

        // Stok TIDAK berkurang, tidak ada stock_histories tipe sale.
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_histories', 0);
    }

    public function test_draft_tetap_tersimpan_walau_belum_dibayar_sama_sekali(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        // Draft murni: tidak ada pembayaran sama sekali, sisa = total.
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'payment_method' => 'cash',
            'payment_status' => 'belum_bayar',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect()->assertSessionHas('status');

        $fresh = $wo->fresh();
        $this->assertSame(1, $fresh->details()->count());
        $this->assertSame(0.0, (float) $fresh->paid_amount);
        $this->assertSame(20000.0, $fresh->remainingAmount());
        $this->assertFalse($fresh->isPaid());
        $this->assertFalse($fresh->isFinal());

        // Stok belum dipotong karena belum final.
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertNull($fresh->finalized_at);
    }

    public function test_draft_bisa_direvisi_dan_dilanjutkan(): void
    {
        $a = $this->makeProduct();
        $b = $this->makeProduct();
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'products' => [['product_id' => $a->id, 'qty' => 1]],
        ])->assertRedirect();

        // Revisi: ganti isi nota.
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'products' => [['product_id' => $b->id, 'qty' => 2]],
        ])->assertRedirect();

        $fresh = $wo->fresh();
        $this->assertSame(1, $fresh->details()->count());
        $this->assertSame($b->id, $fresh->details()->first()->product_id);
        $this->assertSame(2, $fresh->details()->first()->qty);
        $this->assertSame(40000.0, (float) $fresh->grand_total);
    }

    public function test_lunas_menyimpan_paid_amount_penuh_dan_final(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $fresh = $wo->fresh();
        $this->assertTrue($fresh->isFinal());
        $this->assertSame(20000.0, (float) $fresh->paid_amount);
        $this->assertSame(0.0, $fresh->remainingAmount());
        $this->assertSame(19, $product->fresh()->stock);
    }

    public function test_dp_tercatat_dan_sisa_dihitung(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'payment_method' => 'cash',
            'payment_status' => 'dp',
            'paid_amount' => 5000,
            'work_status' => 'proses',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $fresh = $wo->fresh();
        $this->assertSame(5000.0, (float) $fresh->paid_amount);
        $this->assertSame(15000.0, $fresh->remainingAmount());
        $this->assertFalse($fresh->isPaid());
    }
}
