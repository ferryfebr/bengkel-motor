<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class RunRetention extends Command
{
    protected $signature = 'retention:run';

    protected $description = 'Arsipkan/reposisi transaksi melewati kuota & jalankan retensi log (pagar hosting 2GB).';

    public function handle(DataRetentionService $retention): int
    {
        $result = $retention->runAll();

        $this->info('Retensi selesai.');
        $this->line('Transaksi diarsipkan: '.$result['transactions']['archived']);
        $this->line('File arsip dihapus: '.$result['archive_files']['deleted_files']);
        $this->line('Activity log diarsipkan: '.$result['activity_logs']['archived']);

        if ($retention->diskUsageWarning()) {
            $this->warn('PERINGATAN: pemakaian disk '.$retention->diskUsagePercent().'% (>=70%).');
        }

        return self::SUCCESS;
    }
}
