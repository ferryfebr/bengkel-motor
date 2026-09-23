<?php

namespace Tests\Feature;

use App\Models\CashMutation;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowTest extends TestCase
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

    private function makeProduct(float $sell = 20000): Product
    {
        return Product::create([
            'code_sku' => 'P'.uniqid(),
            'name' => 'Produk',
            'purchase_price' => 10000,
            'selling_price' => $sell,
            'stock' => 20,
            'min_stock' => 1,
        ]);
    }

    public function test_checkout_mencatat_kas_masuk_sebesar_pembayaran(): void
    {
        $product = $this->makeProduct(50000);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $income = CashMutation::where('type', CashMutation::TYPE_IN)
            ->where('transaction_id', $wo->id)
            ->sum('amount');

        $this->assertSame(50000.0, (float) $income);
        $this->assertSame(50000.0, CashMutation::balance());
    }

    public function test_dp_mencatat_kas_masuk_sebesar_dp(): void
    {
        $product = $this->makeProduct(50000);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'payment_method' => 'cash',
            'payment_status' => 'dp',
            'paid_amount' => 20000,
            'work_status' => 'proses',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $this->assertSame(20000.0, CashMutation::balance());
    }

    public function test_owner_dapat_menarik_kas_dan_saldo_berkurang(): void
    {
        $product = $this->makeProduct(50000);
        $wo = $this->makeWo();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/cash/withdraw', [
            'amount' => 30000,
            'description' => 'setoran pemilik',
        ])->assertRedirect();

        $this->assertSame(20000.0, CashMutation::balance());
        $this->assertDatabaseHas('cash_mutations', [
            'type' => CashMutation::TYPE_OUT,
            'amount' => 30000,
        ]);
    }

    public function test_penarikan_melebihi_saldo_ditolak(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/cash/withdraw', [
            'amount' => 100000,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0.0, CashMutation::balance());
    }

    public function test_kasir_tidak_bisa_menarik_kas(): void
    {
        $this->actingAs($this->kasir)->post('/cash/withdraw', [
            'amount' => 1000,
        ])->assertForbidden();
    }

    public function test_mutasi_keluar_melebihi_saldo_ditolak(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/cash', [
            'type' => 'out',
            'amount' => 100000,
            'description' => 'beli alat',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0.0, CashMutation::balance());
    }
}