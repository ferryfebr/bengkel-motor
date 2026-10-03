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

    public function test_update_produk_tanpa_hpp_tidak_membocorkan_hpp_ke_activity_log(): void
    {
        $product = $this->makeProduct();
        $owner = User::factory()->owner()->create();

        // Owner ubah stok (tambah) & harga jual (tanpa menyentuh HPP).
        $this->actingAs($owner)->put("/manage/products/{$product->id}", [
            'code_sku' => $product->code_sku,
            'name' => $product->name,
            'selling_price' => 47000,
            'stock_add' => 10,
        ])->assertRedirect('/manage/products');

        // Tidak ada nilai HPP di manapun pada activity_logs.
        $this->assertStringNotContainsString(
            'purchase_price',
            json_encode(ActivityLog::all()->toArray()),
        );

        $this->assertDatabaseHas('activity_logs', ['action' => 'update stock']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'update product_price']);
    }

    public function test_kasir_tidak_bisa_update_produk(): void
    {
        $product = $this->makeProduct();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->put("/manage/products/{$product->id}", [
            'code_sku' => $product->code_sku,
            'name' => $product->name,
            'selling_price' => 47000,
            'stock' => 20,
        ])->assertForbidden();
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
        $mechanic = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80, 'bengkel_percentage' => 20]);

        $this->actingAs($owner)->put("/manage/mechanics/{$mechanic->id}", [
            'name' => 'Andi',
            'mechanic_percentage' => 85,
            'bengkel_percentage' => 15,
            'is_active' => 1,
        ])->assertRedirect('/manage/mechanics');

        $this->assertDatabaseHas('activity_logs', ['action' => 'update mechanic_ratio']);
    }

    public function test_login_dan_logout_tercatat_di_activity_log(): void
    {
        $user = User::factory()->kasir()->create([
            'username' => 'kasirlog',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', ['username' => 'kasirlog', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login',
            'model_id' => $user->id,
        ]);

        $this->post('/logout')->assertRedirect('/');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'logout',
            'model_id' => $user->id,
        ]);
    }

    public function test_login_muncul_di_kategori_akun(): void
    {
        $user = User::factory()->kasir()->create([
            'username' => 'kasirakun',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
        $owner = User::factory()->owner()->create();

        $this->post('/login', ['username' => 'kasirakun', 'password' => 'password']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'model_id' => $user->id]);

        // Muncul di kategori "Akun & Login" (milik owner, karena kasir tidak akses aktivitas).
        $this->actingAs($owner)->get('/activity?category=akun')
            ->assertOk()
            ->assertSee('Login');

        // Tidak nyasar ke "Lainnya".
        $this->actingAs($owner)->get('/activity?category=sistem')
            ->assertOk()
            ->assertDontSee('Masuk ke sistem');
    }
}
