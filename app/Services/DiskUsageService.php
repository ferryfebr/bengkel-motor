<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Pemakaian disk aplikasi terhadap kuota hosting.
 *
 * Catatan: `disk_total_space()`/`disk_free_space()` di shared hosting mengembalikan
 * ukuran partisi server, BUKAN kuota akun. Karena itu kita hitung pemakaian
 * aplikasi sendiri (database + storage + kode) lalu bandingkan dengan kuota
 * yang dikonfigurasi (default 2GB). Hasil di-cache agar tidak membebani hosting.
 */
class DiskUsageService
{
    private const CACHE_KEY = 'disk.usage.report';

    private const CACHE_TTL_MINUTES = 60;

    private const CODE_DIRS = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'];

    /**
     * @return array{
     *   available: bool, quota: int, used: int, percent: float,
     *   database: int, storage: int, code: int, archives: int, logs: int,
     *   server_total: int|null, server_free: int|null
     * }
     */
    public function report(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), fn () => $this->compute());
    }

    /**
     * Bersihkan cache (dipakai setelah retensi agar angka langsung segar).
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *   available: bool, quota: int, used: int, percent: float,
     *   database: int, storage: int, code: int, archives: int, logs: int,
     *   server_total: int|null, server_free: int|null
     * }
     */
    public function compute(): array
    {
        $quotaMb = max(0, (int) config('retention.disk_quota_mb', 2048));
        $quota = $quotaMb * 1048576;

        $database = $this->databaseSize();
        $storage = $this->dirSize(storage_path());
        $code = $this->codeSize();
        $archives = $this->dirSize(storage_path('app/archives'));
        $logs = $this->dirSize(storage_path('logs'));

        $used = $database + $storage + $code;
        $percent = $quota > 0 ? round($used / $quota * 100, 1) : 0.0;

        return [
            'available' => $quota > 0,
            'quota' => $quota,
            'used' => $used,
            'percent' => $percent,
            'database' => $database,
            'storage' => $storage,
            'code' => $code,
            'archives' => $archives,
            'logs' => $logs,
            'server_total' => $this->safeDiskCall('disk_total_space'),
            'server_free' => $this->safeDiskCall('disk_free_space'),
        ];
    }

    private function databaseSize(): int
    {
        try {
            $connection = DB::connection();

            if ($connection->getDriverName() === 'mysql') {
                $row = DB::selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS total
                     FROM information_schema.tables WHERE table_schema = ?',
                    [$connection->getDatabaseName()]
                );

                return (int) ($row->total ?? 0);
            }

            if ($connection->getDriverName() === 'sqlite') {
                $path = $connection->getDatabaseName();

                return is_file($path) ? (int) filesize($path) : 0;
            }
        } catch (Throwable) {
            // Abaikan bila tak bisa diakses (mis. user DB tanpa akses information_schema).
        }

        return 0;
    }

    private function codeSize(): int
    {
        $total = 0;

        foreach (self::CODE_DIRS as $dir) {
            $path = base_path($dir);
            if (is_dir($path)) {
                $total += $this->dirSize($path);
            }
        }

        return $total;
    }

    private function dirSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += (int) $file->getSize();
                }
            }
        } catch (Throwable) {
            return 0;
        }

        return $size;
    }

    private function safeDiskCall(string $function): ?int
    {
        try {
            $value = @$function(base_path());

            return $value !== false ? (int) $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
