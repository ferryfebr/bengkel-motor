<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Rasio porsi bengkel dari jasa servis (sisanya milik mekanik per rasio masing-masing).
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, '20');

        $categories = [
            'Oli & Pelumas',
            'Filter',
            'Busi & Kelistrikan',
            'Ban & Kaki-kaki',
            'Rantai & Transmisi',
            'Aksesoris & Cairan',
        ];

        foreach ($categories as $categoryName) {
            Category::firstOrCreate(['name' => $categoryName]);
        }
    }
}
