<?php

namespace App\Http\Controllers;

use App\Models\TransactionArchive;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Arsip & Backup — owner & super_admin melihat dan mengunduh file arsip
 * transaksi tanpa perlu mengakses cPanel.
 */
class ArchiveController extends Controller
{
    public function index(): View
    {
        $archives = TransactionArchive::orderByDesc('created_at')->paginate(20);

        $totalBytes = TransactionArchive::all()
            ->filter(fn ($a) => $a->exists())
            ->sum(fn ($a) => $a->sizeBytes());

        return view('archives.index', [
            'archives' => $archives,
            'totalFiles' => TransactionArchive::count(),
            'totalBytes' => $totalBytes,
        ]);
    }

    public function download(TransactionArchive $archive): BinaryFileResponse|RedirectResponse
    {
        if (! $archive->exists()) {
            return back()->with('error', 'File arsip tidak ditemukan di server.');
        }

        return response()->download($archive->fullPath(), basename($archive->archive_path));
    }

    public function downloadAll(): BinaryFileResponse|RedirectResponse
    {
        $archives = TransactionArchive::orderBy('created_at')->get()->filter(fn ($a) => $a->exists());

        if ($archives->isEmpty()) {
            return back()->with('error', 'Belum ada file arsip untuk diunduh.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'arsip').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Gagal membuat file ZIP di server.');
        }

        foreach ($archives as $archive) {
            $zip->addFile($archive->fullPath(), basename($archive->archive_path));
        }

        $zip->close();

        return response()->download($tmp, 'arsip-transaksi-'.now()->format('Ymd-His').'.zip')
            ->deleteFileAfterSend(true);
    }
}
