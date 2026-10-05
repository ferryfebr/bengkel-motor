<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\RetentionRunLog;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\TransactionArchive;
use App\Models\TransactionDetail;
use App\Models\TransactionMechanicShare;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Models\TransactionService as TransactionServiceModel;
use App\Models\User;
use App\Services\CsvExportService;
use App\Services\MechanicPayrollService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private array $createdFiles = [];

    private array $preExistingArchives = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dir = storage_path('app/archives');
        $this->preExistingArchives = is_dir($dir)
            ? array_map(fn ($f) => $f->getFilename(), File::files($dir))
            : [];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $relative) {
            File::delete(storage_path('app/'.$relative));
        }

        // Hapus file arsip yang dibuat selama test agar tidak menumpuk.
        $dir = storage_path('app/archives');
        if (is_dir($dir)) {
            foreach (File::files($dir) as $file) {
                if (! in_array($file->getFilename(), $this->preExistingArchives, true)) {
                    File::delete($file->getPathname());
                }
            }
        }

        parent::tearDown();
    }

    private function makeFinalTransaction(float $total = 100000): Transaction
    {
        $kasir = User::factory()->kasir()->create();

        $t = Transaction::create([
            'invoice_number' => 'INV-'.uniqid(),
            'cashier_id' => $kasir->id,
            'customer_name' => 'Test',
            'plate_number' => 'B 1 XY',
            'subtotal_products' => $total,
            'subtotal_services' => 0,
            'grand_total' => $total,
            'paid_amount' => $total,
            'payment_method' => 'cash',
            'work_status' => Transaction::WORK_SELESAI,
            'payment_status' => Transaction::PAY_LUNAS,
            'finalized_at' => now(),
        ]);

        TransactionDetail::create([
            'transaction_id' => $t->id,
            'is_external' => false,
            'qty' => 1,
            'selling_price' => $total,
            'line_total' => $total,
        ]);

        return $t;
    }

    public function test_retensi_transaksi_mengarsip_lalu_menghapus_yang_tertua(): void
    {
        $old = $this->makeFinalTransaction();
        $mid = $this->makeFinalTransaction();
        $new = $this->makeFinalTransaction();

        $this->artisan('transactions:retain', ['--limit' => 1])->assertSuccessful();

        // Tersisa 1 (paling baru).
        $this->assertSame(1, Transaction::final()->count());
        $this->assertTrue(Transaction::whereKey($new->id)->exists());
        $this->assertFalse(Transaction::whereKey($old->id)->exists());
        $this->assertFalse(Transaction::whereKey($mid->id)->exists());

        // Detail ikut terhapus (cascade).
        $this->assertSame(0, TransactionDetail::whereIn('transaction_id', [$old->id, $mid->id])->count());

        // Arsip dibuat + file ada.
        $archive = TransactionArchive::first();
        $this->assertNotNull($archive);
        $this->assertSame(2, $archive->transaction_count);
        $this->createdFiles[] = $archive->archive_path;
        $this->assertTrue($archive->exists(), 'File arsip harus tertulis.');

        $content = File::get(storage_path('app/'.$archive->archive_path));
        $this->assertStringContainsString('Invoice;Tanggal;Pelanggan', $content);
        $this->assertStringContainsString("\xEF\xBB\xBF", $content);
    }

    public function test_retensi_transaksi_menjaga_stock_histories(): void
    {
        $t = $this->makeFinalTransaction();
        $product = Product::create([
            'code_sku' => 'P'.uniqid(), 'name' => 'Oli', 'selling_price' => 100000, 'stock' => 5,
        ]);
        StockHistory::create([
            'product_id' => $product->id,
            'user_id' => $t->cashier_id,
            'transaction_id' => $t->id,
            'type' => StockHistory::TYPE_SALE,
            'qty_change' => -1,
            'reason' => 'Penjualan',
        ]);

        $this->artisan('transactions:retain', ['--limit' => 0])->assertSuccessful();

        // Log stok tetap, transaction_id jadi NULL (nullOnDelete).
        $history = StockHistory::first();
        $this->assertNotNull($history);
        $this->assertNull($history->transaction_id);
    }

    public function test_arsip_memuat_baris_refund(): void
    {
        $kasir = User::factory()->kasir()->create();
        $t = Transaction::create([
            'invoice_number' => 'INV-RF-1',
            'cashier_id' => $kasir->id,
            'customer_name' => 'Refund Test',
            'plate_number' => 'B 9 XY',
            'subtotal_products' => 50000,
            'subtotal_services' => 0,
            'grand_total' => 50000,
            'paid_amount' => 50000,
            'payment_method' => 'cash',
            'work_status' => Transaction::WORK_SELESAI,
            'payment_status' => Transaction::PAY_LUNAS,
            'finalized_at' => now(),
        ]);
        $detail = TransactionDetail::create([
            'transaction_id' => $t->id,
            'is_external' => false,
            'qty' => 1,
            'selling_price' => 50000,
            'line_total' => 50000,
        ]);
        $product = Product::create(['code_sku' => 'RF1', 'name' => 'Oli Refund', 'selling_price' => 50000, 'stock' => 5]);
        $return = TransactionReturn::create([
            'transaction_id' => $t->id, 'user_id' => $kasir->id, 'total' => 50000, 'reason' => 'rusak',
        ]);
        TransactionReturnItem::create([
            'transaction_return_id' => $return->id,
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);

        $path = app(CsvExportService::class)->writeTransactionsArchive(
            Transaction::whereKey($t->id)->get(), 'test-refund'
        );
        $this->createdFiles[] = $path;

        $content = File::get(storage_path('app/'.$path));
        $this->assertStringContainsString('Refund', $content);
        $this->assertStringContainsString('Oli Refund', $content);
    }

    public function test_activity_prune_membatasi_jumlah_baris(): void
    {
        $kasir = User::factory()->kasir()->create();

        for ($i = 0; $i < 5; $i++) {
            ActivityLog::create([
                'user_id' => $kasir->id,
                'action' => 'create',
                'model_type' => Product::class,
            ]);
        }

        $this->assertSame(5, ActivityLog::count());

        $this->artisan('activity:prune', ['--max' => 2, '--months' => 12])->assertSuccessful();

        $this->assertSame(2, ActivityLog::count());
    }

    public function test_activity_prune_mengarsip_csv_lalu_menghapus_dan_mencatat_run(): void
    {
        $kasir = User::factory()->kasir()->create();

        for ($i = 0; $i < 5; $i++) {
            ActivityLog::create([
                'user_id' => $kasir->id,
                'action' => 'create',
                'model_type' => Product::class,
            ]);
        }

        $this->artisan('activity:prune', ['--max' => 2, '--months' => 12])->assertSuccessful();

        // 2 baris terbaru tetap.
        $this->assertSame(2, ActivityLog::count());

        // Arsip aktivitas tercatat & file valid.
        $archive = TransactionArchive::where('type', TransactionArchive::TYPE_ACTIVITY)->first();
        $this->assertNotNull($archive);
        $this->assertSame(3, $archive->transaction_count);
        $this->assertTrue($archive->exists(), 'File arsip aktivitas harus tertulis.');
        $this->createdFiles[] = $archive->archive_path;

        $content = File::get(storage_path('app/'.$archive->archive_path));
        $this->assertStringContainsString('Waktu;Pelaku;', $content);
        $this->assertStringContainsString('Kategori;Aktivitas;Keterangan', $content);
        $this->assertStringContainsString("\xEF\xBB\xBF", $content);

        // Jejak retensi append-only.
        $run = RetentionRunLog::where('run_type', RetentionRunLog::TYPE_ACTIVITY)->first();
        $this->assertNotNull($run);
        $this->assertSame(RetentionRunLog::TRIGGER_CRON, $run->trigger);
        $this->assertNull($run->user_id);
        $this->assertSame(3, $run->archived_count);
        $this->assertSame(3, $run->deleted_count);
        $this->assertSame(3, $run->details['activity_logs']);
    }

    public function test_activity_prune_dry_run_tidak_mengubah_atau_mencatat(): void
    {
        $kasir = User::factory()->kasir()->create();

        for ($i = 0; $i < 5; $i++) {
            ActivityLog::create([
                'user_id' => $kasir->id,
                'action' => 'create',
                'model_type' => Product::class,
            ]);
        }

        $this->artisan('activity:prune', ['--max' => 2, '--months' => 12, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(5, ActivityLog::count());
        $this->assertSame(0, TransactionArchive::where('type', TransactionArchive::TYPE_ACTIVITY)->count());
        $this->assertSame(0, RetentionRunLog::count());
    }

    public function test_transactions_retain_mencatat_run_log_cron(): void
    {
        $this->makeFinalTransaction();
        $this->makeFinalTransaction();

        $this->artisan('transactions:retain', ['--limit' => 1])->assertSuccessful();

        $run = RetentionRunLog::where('run_type', RetentionRunLog::TYPE_TRANSACTIONS)->first();
        $this->assertNotNull($run);
        $this->assertSame(RetentionRunLog::TRIGGER_CRON, $run->trigger);
        $this->assertNull($run->user_id);
        $this->assertSame(1, $run->archived_count);
        $this->assertSame(1, $run->deleted_count);
        $this->assertSame(1, $run->details['transaction_details']);
        $this->assertArrayHasKey('archive_path', $run->details);
    }

    public function test_retain_gagal_verifikasi_tidak_menghapus_dan_catat_failed(): void
    {
        $this->makeFinalTransaction();
        $this->makeFinalTransaction();

        // Paksa verifikasi file arsip gagal -> tidak boleh ada penghapusan.
        $this->partialMock(CsvExportService::class, function ($mock) {
            $mock->shouldReceive('verifyArchiveFile')->once()->andReturn(false);
        });

        $this->artisan('transactions:retain', ['--limit' => 1])->assertFailed();

        $this->assertSame(2, Transaction::final()->count());

        $run = RetentionRunLog::where('run_type', RetentionRunLog::TYPE_TRANSACTIONS)->first();
        $this->assertNotNull($run);
        $this->assertSame(RetentionRunLog::STATUS_FAILED, $run->status);
        $this->assertSame(0, $run->deleted_count);
    }

    public function test_activity_prune_gagal_verifikasi_tidak_menghapus_dan_catat_failed(): void
    {
        $kasir = User::factory()->kasir()->create();

        for ($i = 0; $i < 5; $i++) {
            ActivityLog::create([
                'user_id' => $kasir->id,
                'action' => 'create',
                'model_type' => Product::class,
            ]);
        }

        $this->partialMock(CsvExportService::class, function ($mock) {
            $mock->shouldReceive('verifyArchiveFile')->once()->andReturn(false);
        });

        $this->artisan('activity:prune', ['--max' => 2, '--months' => 12])->assertFailed();

        $this->assertSame(5, ActivityLog::count());

        $run = RetentionRunLog::where('run_type', RetentionRunLog::TYPE_ACTIVITY)->first();
        $this->assertNotNull($run);
        $this->assertSame(RetentionRunLog::STATUS_FAILED, $run->status);
        $this->assertSame(0, $run->deleted_count);
    }

    public function test_retensi_manual_via_panel_mencatat_trigger_dan_user(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->post('/system/retention')->assertRedirect(route('system.index'));

        $run = RetentionRunLog::where('trigger', RetentionRunLog::TRIGGER_MANUAL)
            ->where('user_id', $super->id)
            ->where('run_type', RetentionRunLog::TYPE_TRANSACTIONS)
            ->first();

        $this->assertNotNull($run);
        $this->assertSame(RetentionRunLog::TRIGGER_MANUAL, $run->trigger);
        $this->assertSame($super->id, $run->user_id);

        // Proses aktivitas juga tercatat manual atas nama Super Admin.
        $this->assertDatabaseHas('retention_run_logs', [
            'run_type' => RetentionRunLog::TYPE_ACTIVITY,
            'trigger' => RetentionRunLog::TRIGGER_MANUAL,
            'user_id' => $super->id,
        ]);
    }

    public function test_dry_run_tidak_mengubah_data(): void
    {
        $this->makeFinalTransaction();
        $this->makeFinalTransaction();

        $this->artisan('transactions:retain', ['--limit' => 1, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(2, Transaction::final()->count());
        $this->assertSame(0, TransactionArchive::count());
    }

    public function test_super_admin_bisa_lihat_panel_sistem_dan_jalankan_retensi(): void
    {
        $this->makeFinalTransaction();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get('/system')->assertOk()->assertSee('Jalankan Retensi');

        $this->actingAs($super)->post('/system/retention')->assertRedirect(route('system.index'));
    }

    public function test_owner_tidak_bisa_jalankan_retensi(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/system/retention')->assertForbidden();
    }

    public function test_komisi_mekanik_tetap_utuh_setelah_transaksi_diarsip(): void
    {
        $kasir = User::factory()->kasir()->create();
        $mechanic = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80, 'bengkel_percentage' => 20]);

        $transaction = Transaction::create([
            'invoice_number' => 'INV-KOMISI-1',
            'cashier_id' => $kasir->id,
            'plate_number' => 'B 7 KO',
            'subtotal_services' => 100000,
            'grand_total' => 100000,
            'paid_amount' => 100000,
            'payment_method' => 'cash',
            'work_status' => Transaction::WORK_SELESAI,
            'payment_status' => Transaction::PAY_LUNAS,
            'finalized_at' => now(),
        ]);

        $service = TransactionServiceModel::create([
            'transaction_id' => $transaction->id,
            'service_name' => 'Servis',
            'service_price' => 100000,
            'mechanic_fee' => 80000,
            'bengkel_fee' => 20000,
        ]);

        TransactionMechanicShare::create([
            'transaction_id' => $transaction->id,
            'transaction_service_id' => $service->id,
            'mechanic_id' => $mechanic->id,
            'mechanic_ratio' => 80,
            'share_amount' => 80000,
        ]);

        // Retensi pakai limit 0 -> arsip & hapus semua transaksi final.
        $this->artisan('transactions:retain', ['--limit' => 0])->assertSuccessful();

        $archivePath = TransactionArchive::latest('id')->value('archive_path');
        if ($archivePath) {
            $this->createdFiles[] = $archivePath;
        }

        $this->assertSame(0, Transaction::final()->count());
        $this->assertSame(0, TransactionMechanicShare::count());

        // Ringkasan komisi tersimpan.
        $this->assertDatabaseHas('mechanic_daily_summaries', [
            'mechanic_id' => $mechanic->id,
            'total_share' => 80000,
        ]);

        // Laporan komisi tetap menampilkan.
        $commission = app(ReportService::class)->mechanicCommission(Carbon::today(), Carbon::today());
        $this->assertCount(1, $commission);
        $this->assertSame('Andi', $commission[0]['mechanic_name']);
        $this->assertSame(80000.0, $commission[0]['total_share']);

        // Gaji mekanik (earned) tetap utuh.
        $this->assertSame(80000.0, app(MechanicPayrollService::class)->earned($mechanic->id));
    }
}
