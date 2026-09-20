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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mechanic_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
