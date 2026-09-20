<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Rasio porsi bengkel dari jasa servis (sisanya milik mekanik per rasio masing-masing).
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, '20');

        $categories = [
            'Oli & Pelumas' => [
                ['OLI-1L-10W40', 'Oli Mesin 1L 10W-40', 28000, 45000, 24, 6],
                ['OLI-1L-MPX2', 'Oli Mesin Yamaha Mio Mpx2 1L', 32000, 52000, 18, 6],
                ['OLI-GARDU-08', 'Oli Gardan 0.8L', 18000, 30000, 15, 5],
                ['OLI-FORK-01', 'Oli Shock Fork 1L', 35000, 55000, 8, 3],
            ],
            'Filter' => [
                ['FLT-OLI-STD', 'Filter Oli Standar', 12000, 22000, 30, 8],
                ['FLT-UDARA-STD', 'Filter Udara Standar', 15000, 28000, 22, 6],
                ['FLT-BENSIN-STD', 'Filter Bensin', 8000, 15000, 40, 10],
            ],
            'Busi & Kelistrikan' => [
                ['BUSI-STD', 'Busi Standar', 12000, 25000, 50, 12],
                ['BUSI-IRIDIUM', 'Busi Iridium', 55000, 95000, 12, 4],
                ['AKI-5AH', 'Aki Kering 5Ah', 150000, 235000, 6, 2],
                ['LAMPU-LED-H4', 'Lampu LED H4', 55000, 95000, 10, 3],
            ],
            'Ban & Kaki-kaki' => [
                ['BAN-LUAR-80', 'Ban Luar 80/90-14', 95000, 145000, 8, 3],
                ['BAN-DALAM-80', 'Ban Dalam 80/90-14', 30000, 50000, 12, 4],
                ['KAMPAS-REM-DPN', 'Kampas Rem Depan', 35000, 65000, 14, 5],
                ['KAMPAS-REM-BLK', 'Kampas Rem Belakang', 32000, 60000, 14, 5],
            ],
            'Rantai & Transmisi' => [
                ['RANTAI-428H', 'Rantai 428H + Gir Set', 120000, 185000, 7, 2],
                ['GEAR-SET-STD', 'Gir Set Standar', 95000, 150000, 9, 3],
                ['VBELT-MIO', 'V-Belt Mio', 65000, 110000, 11, 4],
                ['ROLLER-MIO', 'Roller Mio', 45000, 80000, 13, 4],
            ],
            'Aksesoris & Cairan' => [
                ['CAIRAN-REM', 'Minyak Rem 300ml', 25000, 42000, 20, 6],
                ['COOLANT-1L', 'Coolant 1L', 28000, 48000, 16, 5],
                ['CHAINLUBE', 'Chain Lube 200ml', 22000, 40000, 18, 6],
                ['LAP-KANBO', 'Lap Kanebo', 10000, 18000, 25, 8],
            ],
        ];

        foreach ($categories as $categoryName => $products) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($products as [$sku, $name, $purchase, $selling, $stock, $minStock]) {
                Product::updateOrCreate(
                    ['code_sku' => $sku],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                        'purchase_price' => $purchase,
                        'selling_price' => $selling,
                        'stock' => $stock,
                        'min_stock' => $minStock,
                    ]
                );
            }
        }

        $services = [
            ['Servis Ringan / Tune Up', 50000],
            ['Servis Besar (Turun Mesin)', 350000],
            ['Ganti Oli Mesin', 20000],
            ['Ganti Oli Gardan', 20000],
            ['Ganti Busi', 15000],
            ['Ganti Filter Udara', 20000],
            ['Ganti Kampas Rem Depan', 35000],
            ['Ganti Kampas Rem Belakang', 35000],
            ['Servis CVT / Ganti V-Belt', 75000],
            ['Servis Rantai & Gir', 40000],
            ['Setel Rantai', 15000],
            ['Tambal Ban', 25000],
            ['Ganti Ban Luar', 30000],
            ['Ganti Ban Dalam', 20000],
            ['Ganti Aki', 25000],
            ['Servis Kelistrikan', 60000],
            ['Bongkar Pasang Body', 50000],
            ['Cuci Motor', 18000],
        ];

        foreach ($services as [$name, $price]) {
            Service::updateOrCreate(
                ['name' => $name],
                ['price' => $price, 'is_active' => true]
            );
        }

        $mechanics = [
            ['Andi', 85.00],
            ['Budi', 80.00],
            ['Citra', 80.00],
            ['Dedi', 70.00],
        ];

        foreach ($mechanics as [$name, $percentage]) {
            Mechanic::updateOrCreate(
                ['name' => $name],
                ['mechanic_percentage' => $percentage, 'is_active' => true]
            );
        }
    }
}