<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV transaksi & activity_logs untuk backup & arsip reposisi
 * (RINGKASAN §H6/J9). Ditulis streaming agar hemat memori shared hosting.
 */
class CsvExportService
{
    /**
     * Path direktori arsip relatif terhadap storage/app.
     */
    public const ARCHIVE_DIR = 'archives';

    /**
     * Stream CSV transaksi final (beserta detail, jasa, komisi) ke browser.
     */
    public function streamTransactions(?Carbon $from = null, ?Carbon $to = null): StreamedResponse
    {
        $filename = 'transaksi-'.($from?->toDateString() ?? 'awal').'-'.($to?->toDateString() ?? 'kini').'.csv';

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'invoice_number', 'created_at', 'finalized_at', 'customer_name', 'plate_number',
                'cashier_id', 'subtotal_products', 'subtotal_services', 'grand_total',
                'payment_method', 'work_status', 'payment_status', 'details', 'services', 'mechanic_shares',
            ]);

            foreach ($this->transactionRows($from, $to) as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Stream CSV activity_logs (retensi J9).
     */
    public function streamActivityLogs(?Carbon $before = null): StreamedResponse
    {
        $filename = 'activity-logs-before-'.($before?->toDateString() ?? Carbon::now()->toDateString()).'.csv';

        return response()->streamDownload(function () use ($before) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'user_id', 'impersonated_by', 'action', 'model_type', 'model_id', 'old_values', 'new_values']);

            $query = ActivityLog::query()->orderBy('id');
            if ($before) {
                $query->where('created_at', '<', $before);
            }

            foreach ($query->lazyById(500) as $log) {
                fputcsv($out, [
                    $log->id,
                    optional($log->created_at)->toDateTimeString(),
                    $log->user_id,
                    $log->impersonated_by,
                    $log->action,
                    $log->model_type,
                    $log->model_id,
                    json_encode($log->old_values),
                    json_encode($log->new_values),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Tulis file CSV arsip transaksi ke storage/app/archives, kembalikan path relatif.
     *
     * @param  iterable<Transaction>  $transactions
     */
    public function writeTransactionsArchive(iterable $transactions, string $label): string
    {
        $dir = storage_path('app/'.self::ARCHIVE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relative = self::ARCHIVE_DIR.'/transaksi-'.$label.'.csv';
        $path = storage_path('app/'.$relative);

        $out = fopen($path, 'w');
        fputcsv($out, [
            'invoice_number', 'created_at', 'finalized_at', 'customer_name', 'plate_number',
            'cashier_id', 'subtotal_products', 'subtotal_services', 'grand_total',
            'payment_method', 'work_status', 'payment_status', 'details', 'services', 'mechanic_shares',
        ]);

        foreach ($transactions as $transaction) {
            fputcsv($out, $this->transactionToRow($transaction));
        }

        fclose($out);

        return $relative;
    }

    /**
     * Tulis file CSV activity_logs ke arsip, kembalikan path relatif.
     */
    public function writeActivityLogsArchive(string $label, ?Carbon $before = null): string
    {
        $dir = storage_path('app/'.self::ARCHIVE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relative = self::ARCHIVE_DIR.'/activity-logs-'.$label.'.csv';
        $path = storage_path('app/'.$relative);

        $out = fopen($path, 'w');
        fputcsv($out, ['id', 'created_at', 'user_id', 'impersonated_by', 'action', 'model_type', 'model_id', 'old_values', 'new_values']);

        $query = ActivityLog::query()->orderBy('id');
        if ($before) {
            $query->where('created_at', '<', $before);
        }

        foreach ($query->lazyById(500) as $log) {
            fputcsv($out, [
                $log->id,
                optional($log->created_at)->toDateTimeString(),
                $log->user_id,
                $log->impersonated_by,
                $log->action,
                $log->model_type,
                $log->model_id,
                json_encode($log->old_values),
                json_encode($log->new_values),
            ]);
        }

        fclose($out);

        return $relative;
    }

    /**
     * Baris CSV transaksi, memuat relasi dengan eager loading.
     *
     * @return LazyCollection<int, array<int, mixed>>
     */
    private function transactionRows(?Carbon $from, ?Carbon $to): LazyCollection
    {
        $query = Transaction::with(['details', 'services.shares', 'mechanicShares'])
            ->where('work_status', Transaction::WORK_SELESAI)
            ->where('payment_status', Transaction::PAY_LUNAS)
            ->orderBy('id');

        if ($from) {
            $query->where('created_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $query->where('created_at', '<=', $to->copy()->endOfDay());
        }

        return $query->lazyById(200)->map(fn (Transaction $t) => $this->transactionToRow($t));
    }

    /**
     * @return array<int, mixed>
     */
    private function transactionToRow(Transaction $transaction): array
    {
        $details = $transaction->details->map(fn ($d) => [
            'name' => $d->is_external ? $d->external_name : optional($d->product)->name,
            'qty' => $d->qty,
            'purchase_price' => (float) $d->purchase_price,
            'selling_price' => (float) $d->selling_price,
            'line_total' => (float) $d->line_total,
        ])->all();

        $services = $transaction->services->map(fn ($s) => [
            'service_name' => $s->service_name,
            'price' => (float) $s->service_price,
            'mechanic_fee' => (float) $s->mechanic_fee,
            'bengkel_fee' => (float) $s->bengkel_fee,
        ])->all();

        $shares = $transaction->mechanicShares->map(fn ($s) => [
            'mechanic_id' => $s->mechanic_id,
            'share_amount' => (float) $s->share_amount,
        ])->all();

        return [
            $transaction->invoice_number,
            optional($transaction->created_at)->toDateTimeString(),
            optional($transaction->finalized_at)->toDateTimeString(),
            $transaction->customer_name,
            $transaction->plate_number,
            $transaction->cashier_id,
            (float) $transaction->subtotal_products,
            (float) $transaction->subtotal_services,
            (float) $transaction->grand_total,
            $transaction->payment_method,
            $transaction->work_status,
            $transaction->payment_status,
            json_encode($details),
            json_encode($services),
            json_encode($shares),
        ];
    }
}
