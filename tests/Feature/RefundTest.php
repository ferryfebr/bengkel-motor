<?php

namespace Tests\Feature;

use App\Models\CashMutation;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::factory()->kasir()->create();
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
    }

    private function makeProduct(int $stock = 10, float $sell = 45000): Product
    {
        return Product::create([
            'code_sku' => 'P'.uniqid(),
            'name' => 'Produk Uji',
            'purchase_price' => 30000,
            'selling_price' => $sell,
            'stock' => $stock,
        ]);
    }

    private function makeWo(): Transaction
    {
        $this->actingAs($this->kasir)->post('/work-orders', [
            'plate_number' => 'B 1234 XY',
            'customer_name' => 'Test',
        ]);

        return Transaction::first();
    }

    private function finalTransaction(Product $product, int $qty = 4): Transaction
    {
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => $qty]],
        ])->assertRedirect();

        return $wo->fresh();
    }

    public function test_refund_sebagian_mengembalikan_stok_dan_mencatat_kas_keluar(): void
    {
        $product = $this->makeProduct(stock: 10, sell: 45000);
        $wo = $this->finalTransaction($product, qty: 4); // stok 6

        $detail = $wo->details->first();

        $this->actingAs($this->kasir)->post("/work-orders/{$wo->id}/refund", [
            'reason' => 'barang tidak cocok',
            'items' => [
                ['transaction_detail_id' => $detail->id, 'qty' => 2],
            ],
        ])->assertRedirect(route('work-orders.show', $wo));

        // Stok balik: 6 + 2 = 8.
        $this->assertSame(8, $product->fresh()->stock);

        $this->assertDatabaseHas('stock_histories', [
            'product_id' => $product->id,
            'type' => 'return',
            'qty_change' => 2,
        ]);

        // Kas keluar refund = 2 x 45.000 = 90.000.
        $this->assertDatabaseHas('cash_mutations', [
            'type' => CashMutation::TYPE_OUT,
            'category' => CashMutation::CATEGORY_REFUND,
            'amount' => 90000,
            'transaction_id' => $wo->id,
        ]);

        $return = TransactionReturn::first();
        $this->assertNotNull($return);
        $this->assertEquals(90000, (float) $return->total);
        $this->assertSame(1, $return->items()->count());
    }

    public function test_refund_melebihi_sisa_ditolak(): void
    {
        $product = $this->makeProduct(stock: 10, sell: 45000);
        $wo = $this->finalTransaction($product, qty: 4);
        $detail = $wo->details->first();

        $this->actingAs($this->kasir)->post("/work-orders/{$wo->id}/refund", [
            'items' => [['transaction_detail_id' => $detail->id, 'qty' => 5]],
        ])->assertSessionHas('error');

        $this->assertSame(0, TransactionReturn::count());
        $this->assertSame(6, $product->fresh()->stock);
    }

    public function test_produk_luar_tidak_bisa_direfund(): void
    {
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'external_products' => [[
                'name' => 'Kampas Rem',
                'qty' => 1,
                'purchase_price' => 25000,
                'selling_price' => 40000,
            ]],
        ])->assertRedirect();

        $detail = $wo->fresh()->details->first();

        $this->actingAs($this->kasir)->post("/work-orders/{$wo->id}/refund", [
            'items' => [['transaction_detail_id' => $detail->id, 'qty' => 1]],
        ])->assertSessionHas('error');

        $this->assertSame(0, TransactionReturn::count());
    }

    public function test_transaksi_belum_final_tidak_bisa_direfund(): void
    {
        $product = $this->makeProduct();
        $wo = $this->makeWo();

        // Draft saja (belum final).
        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'payment_method' => 'cash',
            'payment_status' => 'belum_bayar',
            'work_status' => 'proses',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $detail = $wo->fresh()->details->first();

        $this->actingAs($this->kasir)->post("/work-orders/{$wo->id}/refund", [
            'items' => [['transaction_detail_id' => $detail->id, 'qty' => 1]],
        ])->assertSessionHasErrors();

        $this->assertSame(0, TransactionReturn::count());
    }

    public function test_transaksi_selesai_menampilkan_tanda_refund(): void
    {
        $product = $this->makeProduct();
        $wo = $this->finalTransaction($product, qty: 1);
        $detail = $wo->details->first();

        $this->actingAs($this->kasir)->post("/work-orders/{$wo->id}/refund", [
            'items' => [['transaction_detail_id' => $detail->id, 'qty' => 1]],
        ])->assertRedirect();

        $this->actingAs($this->kasir)->get('/work-orders/completed')
            ->assertOk()
            ->assertSee('ADA REFUND');
    }
}
