<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Support\ActivityPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
        $query = Transaction::query()
            ->where('work_status', Transaction::WORK_SELESAI)
            ->where('payment_status', Transaction::PAY_LUNAS)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()));

        $maxItems = (clone $query)->withCount(['details', 'services'])->get()
            ->map(fn ($t) => $t->details_count + $t->services_count)->max() ?? 0;

        $filename = 'transaksi-'.($from?->toDateString() ?? 'awal').'-'.($to?->toDateString() ?? 'kini').'.csv';

        return response()->streamDownload(function () use ($query, $maxItems) {
            $out = fopen('php://output', 'w');

            $header = ['Invoice', 'Tanggal', 'Customer', 'Plat', 'Jenis Motor'];
            for ($i = 1; $i <= $maxItems; $i++) {
                $header[] = 'Item '.$i;
            }
            $header[] = 'Total';
            fputcsv($out, $header);

            foreach ($query->with(['details.product', 'services'])->orderBy('id')->lazyById(200) as $t) {
                fputcsv($out, $this->summaryRow($t, $maxItems));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Baris ringkas transaksi: item beserta biaya + total di kolom terakhir.
     *
     * @return array<int, mixed>
     */
    private function summaryRow(Transaction $t, int $maxItems): array
    {
        $items = $t->details->map(function ($d) {
            $name = $d->is_external ? $d->external_name : optional($d->product)->name;

            return $name.' x'.$d->qty.' = Rp '.number_format((float) $d->line_total, 0, ',', '.');
        })->all();

        foreach ($t->services as $s) {
            $items[] = $s->service_name.' = Rp '.number_format((float) $s->service_price, 0, ',', '.');
        }

        $row = [
            $t->invoice_number,
            optional($t->created_at)->format('d/m/Y H:i'),
            $t->customer_name,
            $t->plate_number,
            $t->motor_type,
        ];

        for ($i = 0; $i < $maxItems; $i++) {
            $row[] = $items[$i] ?? '';
        }

        $row[] = 'Rp '.number_format((float) $t->grand_total, 0, ',', '.');

        return $row;
    }

    /**
     * Stream CSV transaksi yang sudah selesai: rincian tiap produk & jasa
     * beserta biayanya, dengan total dibayar di kolom paling kanan.
     */
    public function streamCompletedTransactions(?Carbon $from = null, ?Carbon $to = null, string $search = ''): StreamedResponse
    {
        $maxItems = $this->completedQuery($from, $to, $search)
            ->withCount(['details', 'services'])
            ->get()
            ->map(fn ($t) => $t->details_count + $t->services_count)
            ->max() ?? 0;

        $filename = 'transaksi-selesai-'.($from?->toDateString() ?? 'awal').'-'.($to?->toDateString() ?? 'kini').'.csv';

        return response()->streamDownload(function () use ($from, $to, $search, $maxItems) {
            $out = fopen('php://output', 'w');

            $header = ['Invoice', 'Tanggal', 'Customer', 'Plat', 'Jenis Motor'];
            for ($i = 1; $i <= $maxItems; $i++) {
                $header[] = 'Item '.$i;
            }
            $header[] = 'Total Dibayar';
            fputcsv($out, $header);

            foreach ($this->completedQuery($from, $to, $search)
                ->with(['details.product', 'services'])
                ->orderBy('id')
                ->lazyById(200) as $t) {
                fputcsv($out, $this->summaryRow($t, $maxItems));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
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
     * Stream CSV activity_logs (retensi J9).
     */
    public function streamActivityLogs(?Carbon $before = null): StreamedResponse
    {
        $filename = 'aktivitas-'.($before?->toDateString() ?? Carbon::now()->toDateString()).'.csv';

        return response()->streamDownload(function () use ($before) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Waktu', 'Aktor', 'Kategori', 'Aktivitas', 'Keterangan']);

            $query = ActivityLog::with('user')->orderBy('id');
            if ($before) {
                $query->where('created_at', '<', $before);
            }

            foreach ($query->lazyById(500) as $log) {
                fputcsv($out, [
                    optional($log->created_at)->format('d/m/Y H:i'),
                    $log->user?->name ?? '-',
                    ActivityPresenter::CATEGORIES[ActivityPresenter::category($log->action, $log->model_type)] ?? '-',
                    ActivityPresenter::label($log->action),
                    ActivityPresenter::describe($log),
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
     * @return array<int, mixed>
     */
    private function transactionToRow(Transaction $transaction): array
    {
        $details = $transaction->details->map(fn ($d) => [
            'name' => $d->is_external ? $d->external_name : optional($d->product)->name,
            'qty' => $d->qty,
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
