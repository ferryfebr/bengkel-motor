<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(float $hpp = 30000, float $sell = 45000): Product
    {
        return Product::create([
            'code_sku' => 'P'.uniqid(),
            'name' => 'Produk Uji',
            'purchase_price' => $hpp,
            'selling_price' => $sell,
            'stock' => 10,
            'min_stock' => 1,
        ]);
    }

    public function test_owner_membuat_produk_tercatat_di_activity_log(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/manage/products', [
            'code_sku' => 'OLI-100',
            'name' => 'Oli Baru',
            'selling_price' => 50000,
            'stock' => 5,
            'purchase_price' => 35000,
        ])->assertRedirect();

        $log = ActivityLog::where('action', 'create')
            ->where('model_type', Product::class)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame('OLI-100', $log->new_values['code_sku']);
        // Owner berhak melihat HPP, jadi HPP tercatat.
        $this->assertEquals(35000, $log->new_values['purchase_price']);
    }

    public function test_hpp_tidak_bocor_ke_activity_log_saat_kasir_update_produk(): void
    {
        $product = $this->makeProduct();
        $kasir = User::factory()->kasir()->create();

        // Kasir ubah stok & harga jual (tanpa HPP).
        $this->actingAs($kasir)->put("/manage/products/{$product->id}", [
            'code_sku' => $product->code_sku,
            'name' => $product->name,
            'selling_price' => 47000,
            'stock' => 20,
            'min_stock' => 1,
        ])->assertRedirect('/manage/products');

        // Tidak ada nilai HPP di manapun pada activity_logs.
        $this->assertStringNotContainsString(
            'purchase_price',
            json_encode(ActivityLog::all()->toArray()),
        );

        $this->assertDatabaseHas('activity_logs', ['action' => 'update stock']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'update product_price']);
    }

    public function test_owner_ubah_hpp_tercatat_sebagai_update_product_hpp(): void
    {
        $product = $this->makeProduct(hpp: 30000);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->put("/manage/products/{$product->id}", [
            'code_sku' => $product->code_sku,
            'name' => $product->name,
            'selling_price' => (float) $product->selling_price,
            'stock' => $product->stock,
            'min_stock' => 1,
            'purchase_price' => 28000,
        ])->assertRedirect('/manage/products');

        $log = ActivityLog::where('action', 'update product_hpp')->first();
        $this->assertNotNull($log);
        $this->assertEquals(30000, $log->old_values['purchase_price']);
        $this->assertEquals(28000, $log->new_values['purchase_price']);
    }

    public function test_work_order_dan_ubah_status_tercatat(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->post('/work-orders', [
            'plate_number' => 'B 1234 XY',
            'customer_name' => 'Test',
        ])->assertRedirect();

        $wo = Transaction::first();
        $this->assertDatabaseHas('activity_logs', ['action' => 'create wo', 'model_id' => $wo->id]);

        $this->actingAs($kasir)->patch("/work-orders/{$wo->id}/status", [
            'work_status' => 'proses',
        ])->assertRedirect();

        $log = ActivityLog::where('action', 'update work_status')->first();
        $this->assertNotNull($log);
        $this->assertSame('antre', $log->old_values['work_status']);
        $this->assertSame('proses', $log->new_values['work_status']);
    }

    public function test_produk_luar_checkout_tercatat(): void
    {
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->post('/work-orders', [
            'plate_number' => 'B 1 XY', 'customer_name' => 'A',
        ]);
        $wo = Transaction::first();

        $this->actingAs($kasir)->post("/pos/{$wo->id}/checkout", [
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

        $log = ActivityLog::where('action', 'create external_product')->first();
        $this->assertNotNull($log);
        $this->assertSame('Kampas Rem', $log->new_values['name']);
    }

    public function test_ubah_rasio_mekanik_dan_bengkel_tercatat(): void
    {
        $owner = User::factory()->owner()->create();
        $mechanic = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80]);

        $this->actingAs($owner)->put("/manage/mechanics/{$mechanic->id}", [
            'name' => 'Andi',
            'mechanic_percentage' => 90,
            'is_active' => 1,
        ])->assertRedirect('/manage/mechanics');

        $this->assertDatabaseHas('activity_logs', ['action' => 'update mechanic_ratio']);

        $this->actingAs($owner)->put('/manage/mechanics-bengkel-percentage', [
            'bengkel_percentage' => 15,
        ])->assertRedirect('/manage/mechanics');

        $this->assertDatabaseHas('activity_logs', ['action' => 'update bengkel_ratio']);
    }
}
