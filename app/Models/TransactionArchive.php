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
}
