<?php

namespace Tests\Feature;

use App\Models\TransactionArchive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    private string $relative = 'archives/arsip-test.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $dir = storage_path('app/archives');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        File::put(storage_path('app/'.$this->relative), "Invoice;Tanggal\nINV-1;01/01/2026");
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/'.$this->relative));
        parent::tearDown();
    }

    private function makeArchive(): TransactionArchive
    {
        return TransactionArchive::create([
            'archive_path' => $this->relative,
            'transaction_count' => 1,
            'oldest_invoice' => 'INV-1',
            'newest_invoice' => 'INV-1',
        ]);
    }

    public function test_owner_dan_super_admin_bisa_lihat_halaman_arsip(): void
    {
        $this->makeArchive();

        $owner = User::factory()->owner()->create();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->get('/archives')->assertOk()->assertSee('arsip-test.csv');
        $this->actingAs($super)->get('/archives')->assertOk();
    }

    public function test_kasir_tidak_bisa_akses_arsip(): void
    {
        $this->makeArchive();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get('/archives')->assertForbidden();
    }

    public function test_owner_bisa_unduh_file_arsip(): void
    {
        $archive = $this->makeArchive();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('archives.download', $archive))
            ->assertOk()
            ->assertDownload('arsip-test.csv');
    }

    public function test_unduh_semua_menghasilkan_zip(): void
    {
        $this->makeArchive();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('archives.download-all'))
            ->assertOk()
            ->assertDownload();
    }
}
