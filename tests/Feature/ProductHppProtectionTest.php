<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductHppProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        return Product::create([
            'code_sku' => 'OLI-001',
            'name' => 'Oli Test',
            'purchase_price' => 30000,
            'selling_price' => 45000,
            'stock' => 10,
            'min_stock' => 3,
        ]);
    }

    public function test_owner_bisa_lihat_hpp_di_endpoint_lookup(): void
    {
        $this->makeProduct();
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->getJson('/manage/products/lookup?q=OLI-001');

        $response->assertOk();
        $this->assertStringContainsString('purchase_price', $response->getContent());
        $response->assertJsonPath('data.0.purchase_price', 30000);
    }

    public function test_kasir_tidak_bisa_lihat_hpp_di_endpoint_lookup(): void
    {
        $this->makeProduct();
        $kasir = User::factory()->kasir()->create();

        $response = $this->actingAs($kasir)->getJson('/manage/products/lookup?q=OLI-001');

        $response->assertOk();
        // Field HPP harus benar-benar absen dari response mentah.
        $this->assertStringNotContainsString('purchase_price', $response->getContent());
    }

    public function test_kasir_tidak_bisa_mengirim_hpp_saat_membuat_produk(): void
    {
        $kasir = User::factory()->kasir()->create();

        $response = $this->actingAs($kasir)->post('/manage/products', [
            'code_sku' => 'HACK-1',
            'name' => 'Produk Curang',
            'selling_price' => 10000,
            'stock' => 1,
            'purchase_price' => 5000,
        ]);

        $response->assertSessionHasErrors('purchase_price');
        $this->assertDatabaseMissing('products', ['code_sku' => 'HACK-1']);
    }

    public function test_kasir_bisa_update_stok_dan_harga_jual_tanpa_mengubah_hpp(): void
    {
        $product = $this->makeProduct();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->put("/manage/products/{$product->id}", [
            'code_sku' => 'OLI-001',
            'name' => 'Oli Test',
            'selling_price' => 47000,
            'stock' => 20,
            'min_stock' => 3,
        ])->assertRedirect('/manage/products');

        $product->refresh();
        $this->assertEquals(47000, (float) $product->selling_price);
        $this->assertEquals(20, $product->stock);
        // HPP tidak boleh berubah / terhapus.
        $this->assertEquals(30000, (float) $product->purchase_price);
    }

    public function test_owner_bisa_set_hpp(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/manage/products', [
            'code_sku' => 'OLI-002',
            'name' => 'Oli Owner',
            'selling_price' => 50000,
            'stock' => 5,
            'purchase_price' => 35000,
        ])->assertRedirect('/manage/products');

        $this->assertDatabaseHas('products', [
            'code_sku' => 'OLI-002',
            'purchase_price' => 35000,
        ]);
    }
}
