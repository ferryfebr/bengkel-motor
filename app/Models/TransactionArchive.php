<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionArchive extends Model
{
    // Append-only: hanya created_at.
    public const UPDATED_AT = null;

    protected $fillable = [
        'archive_path',
        'transaction_count',
        'oldest_invoice',
        'newest_invoice',
    ];

    protected function casts(): array
    {
        return [
            'transaction_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function exists(): bool
    {
        return file_exists(storage_path('app/'.$this->archive_path));
    }

    public function fullPath(): string
    {
        return storage_path('app/'.$this->archive_path);
    }

    public function sizeBytes(): int
    {
        return $this->exists() ? (int) filesize($this->fullPath()) : 0;
    }

    public function humanSize(): string
    {
        $bytes = $this->sizeBytes();

        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}
