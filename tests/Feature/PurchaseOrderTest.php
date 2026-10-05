<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RetentionRunLog;
use App\Models\StockHistory;
use App\Models\Supplier;
use App\Models\TransactionArchive;
use App\Models\User;
use App\Services\CsvExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

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

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    private function product(string $sku, float $hpp, int $stock = 5): Product
    {
        return Product::create([
            'code_sku' => $sku,
            'name' => 'Produk '.$sku,
            'purchase_price' => $hpp,
            'selling_price' => $hpp * 2,
            'stock' => $stock,
        ]);
    }

    public function test_owner_bisa_membuat_po_banyak_item(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::create(['name' => 'Distributor A']);
        $p1 = $this->product('P1', 10000);
        $p2 = $this->product('P2', 20000);

        $this->actingAs($owner)->post('/purchase-orders', [
            'supplier_id' => $supplier->id,
            'notes' => 'kirim cepat',
            'items' => [
                ['product_id' => $p1->id, 'qty' => 3, 'purchase_price' => 10000],
                ['product_id' => $p2->id, 'qty' => 2, 'purchase_price' => 20000],
            ],
        ])->assertRedirect();

        $po = PurchaseOrder::first();
        $this->assertNotNull($po);
        $this->assertStringStartsWith('PO-', $po->po_number);
        $this->assertSame(70000.0, (float) $po->total);
        $this->assertSame(2, $po->items()->count());
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->status);
    }

    public function test_po_menghasilkan_pdf(): void
    {
        $owner = $this->owner();
        $product = $this->product('P1', 12000);

        $this->actingAs($owner)->post('/purchase-orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'purchase_price' => 12000]],
        ])->assertRedirect();

        $po = PurchaseOrder::first();

        $this->actingAs($owner)->get(route('purchase-orders.pdf', $po))
            ->assertOk()
            ->assertDownload($po->po_number.'.pdf');
    }

    public function test_po_punya_halaman_cetak(): void
    {
        $owner = $this->owner();
        $product = $this->product('P1', 12000);

        $this->actingAs($owner)->post('/purchase-orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'purchase_price' => 12000]],
        ])->assertRedirect();

        $po = PurchaseOrder::first();

        $this->actingAs($owner)->get(route('purchase-orders.print', $po))
            ->assertOk()
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee($po->po_number);
    }

    public function test_terima_barang_menambah_stok_dan_tidak_bisa_dobel(): void
    {
        $owner = $this->owner();
        $p1 = $this->product('P1', 10000, stock: 5);
        $p2 = $this->product('P2', 20000, stock: 5);

        $this->actingAs($owner)->post('/purchase-orders', [
            'items' => [
                ['product_id' => $p1->id, 'qty' => 3, 'purchase_price' => 10000],
                ['product_id' => $p2->id, 'qty' => 2, 'purchase_price' => 20000],
            ],
        ])->assertRedirect();

        $po = PurchaseOrder::first();

        $this->actingAs($owner)->post(route('purchase-orders.receive', $po))->assertRedirect();

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_DITERIMA, $po->status);
        $this->assertNotNull($po->received_at);
        $this->assertSame(8, $p1->fresh()->stock);
        $this->assertSame(7, $p2->fresh()->stock);
        $this->assertDatabaseHas('stock_histories', [
            'product_id' => $p1->id,
            'type' => StockHistory::TYPE_IN,
            'qty_change' => 3,
        ]);

        // Terima lagi -> ditolak, stok tidak berubah.
        $this->actingAs($owner)->post(route('purchase-orders.receive', $po))->assertSessionHas('error');
        $this->assertSame(8, $p1->fresh()->stock);
        $this->assertSame(7, $p2->fresh()->stock);
    }

    public function test_kasir_tidak_bisa_akses_po(): void
    {
        $kasir = User::factory()->kasir()->create();
        $product = $this->product('P1', 10000);

        $this->actingAs($kasir)->get('/purchase-orders')->assertForbidden();
        $this->actingAs($kasir)->get('/suppliers')->assertForbidden();
        $this->actingAs($kasir)->post('/purchase-orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertForbidden();
    }

    public function test_harga_beli_dengan_pemisah_ribuan_disimpan_utuh(): void
    {
        $owner = $this->owner();
        $product = $this->product('P1', 5000);

        $this->actingAs($owner)->post('/purchase-orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'purchase_price' => '18.000']],
        ])->assertRedirect();

        $po = PurchaseOrder::first();
        $this->assertSame(18000.0, (float) $po->items->first()->purchase_price);
        $this->assertSame(18000.0, (float) $po->total);
    }

    public function test_owner_bisa_membatalkan_po(): void
    {
        $owner = $this->owner();
        $product = $this->product('P1', 10000);

        $this->actingAs($owner)->post('/purchase-orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'purchase_price' => 10000]],
        ])->assertRedirect();

        $po = PurchaseOrder::first();

        $this->actingAs($owner)->post(route('purchase-orders.cancel', $po))->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_DIBATALKAN, $po->fresh()->status);
        // Stok tidak berubah setelah batal.
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_po_dibatalkan_tidak_bisa_diterima_atau_diubah_status(): void
    {
        $owner = $this->owner();
        $product = $this->product('P1', 10000);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-CANCEL-1',
            'user_id' => $owner->id,
            'status' => PurchaseOrder::STATUS_DIBATALKAN,
            'total' => 10000,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'qty' => 2,
            'purchase_price' => 10000,
            'line_total' => 20000,
        ]);

        $this->actingAs($owner)->post(route('purchase-orders.receive', $po))->assertSessionHas('error');
        $this->assertSame(5, $product->fresh()->stock);

        $this->actingAs($owner)->patch(route('purchase-orders.status', $po), ['status' => 'dikirim'])
            ->assertSessionHas('error');
        $this->assertSame(PurchaseOrder::STATUS_DIBATALKAN, $po->fresh()->status);
    }

    public function test_po_diterima_tidak_bisa_dibatalkan(): void
    {
        $owner = $this->owner();
        $product = $this->product('P1', 10000);

        $this->actingAs($owner)->post('/purchase-orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'purchase_price' => 10000]],
        ])->assertRedirect();

        $po = PurchaseOrder::first();
        $this->actingAs($owner)->post(route('purchase-orders.receive', $po));

        $this->actingAs($owner)->post(route('purchase-orders.cancel', $po))->assertSessionHas('error');
        $this->assertSame(PurchaseOrder::STATUS_DITERIMA, $po->fresh()->status);
    }

    public function test_retensi_po_membatasi_jumlah(): void
    {
        $owner = $this->owner();

        for ($i = 0; $i < 3; $i++) {
            $po = PurchaseOrder::create([
                'po_number' => 'PO-TEST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'user_id' => $owner->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'total' => 1000,
            ]);
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_name' => 'X',
                'qty' => 1,
                'purchase_price' => 1000,
                'line_total' => 1000,
            ]);
        }

        $this->artisan('purchase-orders:retain', ['--max' => 1])->assertSuccessful();

        $this->assertSame(1, PurchaseOrder::count());
        // Item PO lama ikut terhapus (cascade).
        $this->assertSame(1, PurchaseOrderItem::count());

        // Arsip PO tercatat & file valid (pola sama seperti retensi transaksi).
        $archive = TransactionArchive::where('type', TransactionArchive::TYPE_PURCHASE_ORDERS)->first();
        $this->assertNotNull($archive);
        $this->assertSame(2, $archive->transaction_count);
        $this->assertTrue($archive->exists(), 'File arsip PO harus tertulis.');

        $content = File::get(storage_path('app/'.$archive->archive_path));
        $this->assertStringContainsString('PO;Tanggal;Supplier;', $content);
        $this->assertStringContainsString("\xEF\xBB\xBF", $content);

        // Jejak retensi.
        $run = RetentionRunLog::where('run_type', RetentionRunLog::TYPE_PURCHASE_ORDERS)->first();
        $this->assertNotNull($run);
        $this->assertSame(RetentionRunLog::TRIGGER_CRON, $run->trigger);
        $this->assertSame(2, $run->archived_count);
        $this->assertSame(2, $run->deleted_count);
        $this->assertSame(2, $run->details['purchase_order_items']);
    }

    public function test_retensi_po_gagal_verifikasi_tidak_menghapus_dan_catat_failed(): void
    {
        $owner = $this->owner();

        for ($i = 0; $i < 3; $i++) {
            $po = PurchaseOrder::create([
                'po_number' => 'PO-VER-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'user_id' => $owner->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'total' => 1000,
            ]);
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_name' => 'X',
                'qty' => 1,
                'purchase_price' => 1000,
                'line_total' => 1000,
            ]);
        }

        $this->partialMock(CsvExportService::class, function ($mock) {
            $mock->shouldReceive('verifyArchiveFile')->once()->andReturn(false);
        });

        $this->artisan('purchase-orders:retain', ['--max' => 1])->assertFailed();

        $this->assertSame(3, PurchaseOrder::count());

        $run = RetentionRunLog::where('run_type', RetentionRunLog::TYPE_PURCHASE_ORDERS)->first();
        $this->assertNotNull($run);
        $this->assertSame(RetentionRunLog::STATUS_FAILED, $run->status);
        $this->assertSame(0, $run->deleted_count);
    }
}
