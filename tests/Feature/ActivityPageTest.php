<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityPageTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::factory()->kasir()->create();
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
    }

    public function test_owner_bisa_melihat_page_aktivitas(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/activity')->assertOk();
    }

    public function test_kasir_tidak_bisa_melihat_page_aktivitas(): void
    {
        $this->actingAs($this->kasir)->get('/activity')->assertForbidden();
    }

    public function test_simpan_draft_mencatat_aktivitas_keranjang(): void
    {
        $product = Product::create([
            'code_sku' => 'P1',
            'name' => 'Oli',
            'purchase_price' => 10000,
            'selling_price' => 45000,
            'stock' => 10,
            'min_stock' => 1,
        ]);

        $this->actingAs($this->kasir)->post('/work-orders', [
            'plate_number' => 'B 9 ZZ',
            'customer_name' => 'Z',
        ]);
        $wo = Transaction::latest('id')->first();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/draft", [
            'products' => [['product_id' => $product->id, 'qty' => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'update draft',
            'model_type' => Transaction::class,
            'model_id' => $wo->id,
        ]);

        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->get("/activity/transaction/{$wo->id}")
            ->assertOk()
            ->assertSee('Simpan draft nota')
            ->assertSee('Oli');
    }
}
