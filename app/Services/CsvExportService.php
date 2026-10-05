<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\Transaction;
use App\Support\ActivityPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export & arsip CSV (RESIKO_HOSTING.md, RINGKASAN §H6/J9).
 *
 * Format (semua file): 1 baris = 1 item, pemisah ";", BOM UTF-8,
 * tanggal dd/mm/yyyy hh:mm, nominal angka polos (desimal koma).
 * Ditulis streaming agar hemat memori shared hosting.
 */
class CsvExportService
{
    /**
     * Path direktori arsip relatif terhadap storage/app.
     */
    public const ARCHIVE_DIR = 'archives';

    private const DELIMITER = ';';

    /**
     * Stream CSV transaksi final (rincian per item).
     */
    public function streamTransactions(?Carbon $from = null, ?Carbon $to = null): StreamedResponse
    {
        $query = Transaction::query()
            ->where('work_status', Transaction::WORK_SELESAI)
            ->where('payment_status', Transaction::PAY_LUNAS)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()));

        $filename = 'transaksi-'.($from?->toDateString() ?? 'awal').'-'.($to?->toDateString() ?? 'kini').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            $this->writeBom($out);
            fputcsv($out, [
                'Invoice', 'Tanggal', 'Pelanggan', 'Plat', 'Jenis Motor',
                'Jenis Item', 'Nama Item', 'Qty', 'Harga Satuan (Rp)', 'Subtotal (Rp)',
                'Status Bayar', 'Metode', 'Total Transaksi (Rp)', 'Dibayar (Rp)', 'Sisa (Rp)',
            ], self::DELIMITER);

            foreach ($query->with(['details.product', 'services'])->orderBy('id')->lazyById(200) as $t) {
                $this->writeTransactionRows($out, $t, false);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stream CSV transaksi yang sudah selesai + informasi refund.
     */
    public function streamCompletedTransactions(?Carbon $from = null, ?Carbon $to = null, string $search = ''): StreamedResponse
    {
        $filename = 'transaksi-selesai-'.($from?->toDateString() ?? 'awal').'-'.($to?->toDateString() ?? 'kini').'.csv';

        return response()->streamDownload(function () use ($from, $to, $search) {
            $out = fopen('php://output', 'w');
            $this->writeBom($out);
            fputcsv($out, [
                'Invoice', 'Tanggal', 'Pelanggan', 'Plat', 'Jenis Motor',
                'Jenis Item', 'Nama Item', 'Qty', 'Harga Satuan (Rp)', 'Subtotal (Rp)',
                'Status Bayar', 'Metode', 'Total Transaksi (Rp)', 'Dibayar (Rp)', 'Sisa (Rp)',
                'Total Refund (Rp)', 'Sisa Setelah Refund (Rp)',
            ], self::DELIMITER);

            foreach ($this->completedQuery($from, $to, $search)
                ->with(['details.product', 'services', 'returns.items.product'])
                ->orderBy('id')
                ->lazyById(200) as $t) {
                $this->writeTransactionRows($out, $t, true);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Tulis header + baris satu transaksi (1 baris per item).
     * Mode $withRefund menambah kolom Total Refund & Sisa Setelah Refund + baris refund.
     */
    private function writeTransactionRows($out, Transaction $t, bool $withRefund): void
    {
        $t->loadMissing(['details.product', 'services', 'returns.items.product']);

        $refundTotal = (float) $t->returns->sum('total');
        $sisa = round((float) $t->grand_total - $refundTotal, 2);

        $base = [
            $t->invoice_number,
            $this->dt($t->created_at),
            $t->customer_name,
            $t->plate_number,
            $t->motor_type,
        ];
        $tail = [
            $this->statusLabel($t->payment_status),
            $t->payment_method,
            $this->num($t->grand_total),
            $this->num($t->paid_amount),
            $this->num($t->remainingAmount()),
        ];

        foreach ($t->details as $d) {
            $row = array_merge($base, [
                $d->is_external ? 'Produk Luar' : 'Produk',
                $d->displayName(),
                $d->qty,
                $this->num($d->selling_price),
                $this->num($d->line_total),
            ], $tail);

            if ($withRefund) {
                $row[] = $this->num($refundTotal);
                $row[] = $this->num($sisa);
            }

            fputcsv($out, $row, self::DELIMITER);
        }

        foreach ($t->services as $s) {
            $row = array_merge($base, [
                'Jasa',
                $s->service_name,
                1,
                $this->num($s->service_price),
                $this->num($s->service_price),
            ], $tail);

            if ($withRefund) {
                $row[] = $this->num($refundTotal);
                $row[] = $this->num($sisa);
            }

            fputcsv($out, $row, self::DELIMITER);
        }

        if ($withRefund) {
            foreach ($t->returns as $ret) {
                $ret->loadMissing('items.product');
                foreach ($ret->items as $it) {
                    fputcsv($out, array_merge($base, [
                        'Refund',
                        $it->product?->name ?? 'Produk',
                        $it->qty,
                        $this->num($it->unit_price),
                        $this->num($it->line_total),
                    ], $tail, [
                        $this->num($refundTotal),
                        $this->num($sisa),
                    ]), self::DELIMITER);
                }
            }
        }
    }

    private function completedQuery(?Carbon $from, ?Carbon $to, string $search): Builder
    {
        return Transaction::query()
            ->where('work_status', Transaction::WORK_SELESAI)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Stream CSV activity_logs.
     */
    public function streamActivityLogs(?Carbon $before = null): StreamedResponse
    {
        $filename = 'aktivitas-'.($before?->toDateString() ?? Carbon::now()->toDateString()).'.csv';

        return response()->streamDownload(function () use ($before) {
            $out = fopen('php://output', 'w');
            $this->writeBom($out);
            fputcsv($out, [
                'Waktu', 'Pelaku', 'Dipengaruhi Oleh', 'Kategori', 'Aktivitas', 'Keterangan',
            ], self::DELIMITER);

            $query = ActivityLog::with('user')->orderBy('id');
            if ($before) {
                $query->where('created_at', '<', $before);
            }

            foreach ($query->lazyById(500) as $log) {
                fputcsv($out, $this->activityRow($log), self::DELIMITER);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Baris CSV satu activity_log.
     *
     * @return array<int, mixed>
     */
    private function activityRow(ActivityLog $log): array
    {
        return [
            $this->dt($log->created_at),
            $log->user?->name ?? '-',
            $log->impersonated_by ? 'Admin #'.$log->impersonated_by.' (Login Sebagai)' : '-',
            ActivityPresenter::CATEGORIES[ActivityPresenter::category($log->action, $log->model_type)] ?? '-',
            ActivityPresenter::label($log->action),
            ActivityPresenter::describe($log),
        ];
    }

    /**
     * Tulis file arsip activity_logs (dipakai activity:prune sebelum hapus).
     * Kembalikan path relatif terhadap storage/app.
     *
     * @param  iterable<ActivityLog>  $logs
     */
    public function writeActivityLogsArchive(iterable $logs, string $label): string
    {
        $dir = storage_path('app/'.self::ARCHIVE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relative = self::ARCHIVE_DIR.'/arsip-aktivitas-'.$label.'.csv';
        $path = storage_path('app/'.$relative);

        $out = fopen($path, 'w');
        $this->writeBom($out);
        fputcsv($out, [
            'Waktu', 'Pelaku', 'Dipengaruhi Oleh', 'Kategori', 'Aktivitas', 'Keterangan',
        ], self::DELIMITER);

        foreach ($logs as $log) {
            fputcsv($out, $this->activityRow($log), self::DELIMITER);
        }

        fclose($out);

        return $relative;
    }

    /**
     * Tulis file arsip pesanan pembelian (dipakai purchase-orders:retain sebelum hapus).
     * Kembalikan path relatif terhadap storage/app.
     *
     * @param  iterable<PurchaseOrder>  $orders
     */
    public function writePurchaseOrdersArchive(iterable $orders, string $label): string
    {
        $dir = storage_path('app/'.self::ARCHIVE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relative = self::ARCHIVE_DIR.'/arsip-po-'.$label.'.csv';
        $path = storage_path('app/'.$relative);

        $out = fopen($path, 'w');
        $this->writeBom($out);
        fputcsv($out, [
            'PO', 'Tanggal', 'Supplier', 'Status', 'Dibuat Oleh', 'Diterima Oleh',
            'Tanggal Diterima', 'Catatan', 'Nama Produk', 'Qty', 'Harga Beli (Rp)',
            'Subtotal (Rp)', 'Total PO (Rp)',
        ], self::DELIMITER);

        foreach ($orders as $po) {
            $this->writePurchaseOrderRows($out, $po);
        }

        fclose($out);

        return $relative;
    }

    /**
     * Baris arsip satu PO (1 baris per item; PO tanpa item tetap 1 baris).
     */
    private function writePurchaseOrderRows($out, PurchaseOrder $po): void
    {
        $po->loadMissing(['supplier', 'user', 'receiver', 'items']);

        $base = [
            $po->po_number,
            $this->dt($po->created_at),
            $po->supplier?->name ?? '-',
            $po->statusLabel(),
            $po->user?->name ?? '-',
            $po->receiver?->name ?? '-',
            $po->received_at ? $this->dt($po->received_at) : '-',
            $po->notes ?? '-',
        ];

        if ($po->items->isEmpty()) {
            fputcsv($out, array_merge($base, ['-', '', $this->num(0), $this->num(0), $this->num($po->total)]), self::DELIMITER);

            return;
        }

        foreach ($po->items as $it) {
            fputcsv($out, array_merge($base, [
                $it->product_name,
                $it->qty,
                $this->num($it->purchase_price),
                $this->num($it->line_total),
                $this->num($po->total),
            ]), self::DELIMITER);
        }
    }

    /**
     * Verifikasi file arsip benar-benar tertulis & lengkap SEBELUM data dihapus.
     * Cek: file ada, ukuran > 0, berawalan BOM UTF-8, dan jumlah baris data >= $minRows.
     */
    public function verifyArchiveFile(string $relative, int $minRows): bool
    {
        $path = storage_path('app/'.$relative);

        if (! is_file($path) || filesize($path) <= 0) {
            return false;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }

        $rows = 0;
        $header = true;
        while (($line = fgets($handle)) !== false) {
            if ($header) {
                $header = false;
                if (! str_starts_with($line, "\xEF\xBB\xBF")) {
                    fclose($handle);

                    return false;
                }
                continue;
            }
            if (trim($line) !== '') {
                $rows++;
            }
        }

        fclose($handle);

        return $rows >= $minRows;
    }

    /**
     * Tulis 1 file CSV arsip lengkap (transaksi + item + jasa + komisi + refund + kas),
     * kembalikan path relatif terhadap storage/app.
     *
     * @param  iterable<Transaction>  $transactions
     */
    public function writeTransactionsArchive(iterable $transactions, string $label): string
    {
        $dir = storage_path('app/'.self::ARCHIVE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relative = self::ARCHIVE_DIR.'/arsip-'.$label.'.csv';
        $path = storage_path('app/'.$relative);

        $out = fopen($path, 'w');
        $this->writeBom($out);
        fputcsv($out, [
            'Invoice', 'Tanggal', 'Pelanggan', 'Plat', 'Jenis Motor', 'Kasir',
            'Jenis Baris', 'Nama', 'Qty', 'Harga Satuan (Rp)', 'Subtotal (Rp)',
            'Total Transaksi (Rp)', 'Total Refund (Rp)', 'Status Bayar', 'Metode',
        ], self::DELIMITER);

        foreach ($transactions as $transaction) {
            $this->writeArchiveRows($out, $transaction);
        }

        fclose($out);

        return $relative;
    }

    /**
     * Baris arsip lengkap satu transaksi.
     */
    private function writeArchiveRows($out, Transaction $t): void
    {
        $t->loadMissing([
            'cashier',
            'details.product',
            'services.shares.mechanic',
            'returns.items.product',
            'cashMutations',
        ]);

        $refundTotal = (float) $t->returns->sum('total');

        $base = [
            $t->invoice_number,
            $this->dt($t->created_at),
            $t->customer_name,
            $t->plate_number,
            $t->motor_type,
            $t->cashier?->name,
        ];
        $tail = [
            $this->num($t->grand_total),
            $this->num($refundTotal),
            $this->statusLabel($t->payment_status),
            $t->payment_method,
        ];

        $push = function (array $row) use ($out, $base, $tail) {
            fputcsv($out, array_merge($base, $row, $tail), self::DELIMITER);
        };

        foreach ($t->details as $d) {
            $push([
                $d->is_external ? 'Produk Luar' : 'Produk',
                $d->displayName(),
                $d->qty,
                $this->num($d->selling_price),
                $this->num($d->line_total),
            ]);
        }

        foreach ($t->services as $s) {
            $push(['Jasa', $s->service_name, 1, $this->num($s->service_price), $this->num($s->service_price)]);

            foreach ($s->shares as $share) {
                $push([
                    'Komisi Mekanik',
                    ($share->mechanic?->name ?? 'Mekanik').' ('.$this->num($share->mechanic_ratio).'%)',
                    1,
                    $this->num($share->share_amount),
                    $this->num($share->share_amount),
                ]);
            }
        }

        foreach ($t->returns as $ret) {
            $ret->loadMissing('items.product');
            foreach ($ret->items as $it) {
                $push([
                    'Refund',
                    $it->product?->name ?? 'Produk',
                    $it->qty,
                    $this->num($it->unit_price),
                    $this->num($it->line_total),
                ]);
            }
        }

        foreach ($t->cashMutations as $c) {
            $push([
                $c->type === 'in' ? 'Kas Masuk' : 'Kas Keluar',
                $c->description ?: '-',
                1,
                $this->num($c->amount),
                $this->num($c->amount),
            ]);
        }
    }

    private function writeBom($out): void
    {
        fwrite($out, "\xEF\xBB\xBF");
    }

    private function num($value): string
    {
        return number_format((float) $value, 2, ',', '');
    }

    private function dt(?Carbon $date): string
    {
        return $date ? $date->format('d/m/Y H:i') : '';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'lunas' => 'Lunas',
            'dp' => 'DP',
            'belum_bayar' => 'Belum Bayar',
            default => (string) $status,
        };
    }
}
