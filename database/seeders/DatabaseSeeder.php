<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => null,
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'owner'],
            [
                'name' => 'Pemilik Bengkel',
                'email' => null,
                'password' => Hash::make('password'),
                'role' => User::ROLE_OWNER,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'kasir'],
            [
                'name' => 'Kasir Bengkel',
                'email' => null,
                'password' => Hash::make('password'),
                'role' => User::ROLE_KASIR,
                'is_active' => true,
            ]
        );

        $this->call(MasterDataSeeder::class);
    }
}
