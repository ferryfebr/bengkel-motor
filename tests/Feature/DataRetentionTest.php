<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\TransactionArchive;
use App\Models\User;
use App\Services\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::factory()->kasir()->create();
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/archives'));
        parent::tearDown();
    }

    private function makeProduct(int $stock = 100): Product
    {
        return Product::create([
            'code_sku' => 'P'.uniqid(),
            'name' => 'Produk',
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'stock' => $stock,
            'min_stock' => 1,
        ]);
    }

    private function makeFinalTransaction(Product $product, ?Carbon $createdAt = null): Transaction
    {
        $this->actingAs($this->kasir)->post('/work-orders', [
            'plate_number' => 'B 1 XY',
            'customer_name' => 'C',
        ]);
        $wo = Transaction::latest('id')->first();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'products' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertRedirect();

        if ($createdAt) {
            Transaction::withoutTimestamps(fn () => $wo->forceFill(['created_at' => $createdAt])->save());
        }

        return $wo->fresh();
    }

    public function test_archive_excess_mengarsipkan_dan_menghapus_transaksi_tertua(): void
    {
        $product = $this->makeProduct();

        $first = $this->makeFinalTransaction($product, Carbon::now()->subDays(3));
        $this->makeFinalTransaction($product, Carbon::now()->subDays(2));
        $this->makeFinalTransaction($product, Carbon::now()->subDays(1));

        $stockBefore = StockHistory::count();

        $result = app(DataRetentionService::class)->archiveExcess(keep: 2);

        $this->assertSame(1, $result['archived']);
        $this->assertNotNull($result['archive_path']);
        $this->assertFileExists(storage_path('app/'.$result['archive_path']));

        // Transaksi tertua hilang, sisanya tetap.
        $this->assertNull(Transaction::withTrashed()->find($first->id));
        $this->assertSame(2, Transaction::count());

        // Catatan arsip dibuat.
        $archive = TransactionArchive::first();
        $this->assertNotNull($archive);
        $this->assertSame($first->invoice_number, $archive->oldest_invoice);

        // stock_histories tidak terhapus & transaction_id jadi NULL.
        $this->assertSame($stockBefore, StockHistory::count());
        $this->assertSame(0, StockHistory::where('transaction_id', $first->id)->count());
    }

    public function test_transaksi_belum_final_tidak_dihapus(): void
    {
        $product = $this->makeProduct();
        $this->makeFinalTransaction($product);

        // Work order baru yang masih antre (belum final).
        $this->actingAs($this->kasir)->post('/work-orders', [
            'plate_number' => 'B 2 XY',
            'customer_name' => 'D',
        ]);
        $draft = Transaction::latest('id')->first();

        app(DataRetentionService::class)->archiveExcess(keep: 0);

        $this->assertNotNull(Transaction::find($draft->id));
    }

    public function test_prune_activity_logs_menghapus_melebihi_batas_baris(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'create',
                'model_type' => 'products',
                'model_id' => $i,
            ]);
        }

        $result = app(DataRetentionService::class)->pruneActivityLogs(maxRows: 4);

        $this->assertSame(6, $result['archived']);
        $this->assertSame(4, ActivityLog::count());
    }

    public function test_prune_archive_files_menghapus_file_lama(): void
    {
        $dir = storage_path('app/archives');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/old.csv';
        File::put($path, 'x');
        // Set mtime ke 40 hari lalu.
        touch($path, Carbon::now()->subDays(40)->getTimestamp());

        $result = app(DataRetentionService::class)->pruneArchiveFiles(maxDays: 30);

        $this->assertGreaterThanOrEqual(1, $result['deleted_files']);
        $this->assertFileDoesNotExist($path);
    }

    public function test_command_retention_run_berjalan(): void
    {
        $product = $this->makeProduct();
        $this->makeFinalTransaction($product);

        $this->artisan('retention:run')->assertSuccessful();
    }

    public function test_super_admin_bisa_jalankan_retensi_manual(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post('/system/retention')->assertRedirect();

        $this->actingAs($superAdmin)->get('/system')->assertOk();
    }

    public function test_kasir_tidak_bisa_akses_panel_sistem(): void
    {
        $this->actingAs($this->kasir)->get('/system')->assertForbidden();
    }
}
