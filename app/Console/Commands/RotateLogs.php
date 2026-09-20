<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class RotateLogs extends Command
{
    protected $signature = 'logs:rotate {--days=14 : Hapus file log lebih tua dari N hari}';

    protected $description = 'Hapus file laravel.log lama (pagar inode & disk hosting).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = Carbon::now()->subDays($days)->getTimestamp();
        $logDir = storage_path('logs');

        if (! File::isDirectory($logDir)) {
            $this->info('Direktori log tidak ada.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach (File::files($logDir) as $file) {
            if ($file->getMTime() < $threshold) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        $this->info("Log lama dihapus: {$deleted} file.");

        return self::SUCCESS;
    }
}
