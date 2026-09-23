<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mechanic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'mechanic_percentage',
        'bengkel_percentage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mechanic_percentage' => 'decimal:2',
            'bengkel_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Bila rasio bengkel tidak diisi, default = komplemen rasio mekanik.
        static::saving(function (Mechanic $mechanic) {
            if ($mechanic->bengkel_percentage === null && $mechanic->mechanic_percentage !== null) {
                $mechanic->bengkel_percentage = round(100 - (float) $mechanic->mechanic_percentage, 2);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
