<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('draft','dikirim','diterima','dibatalkan') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('draft','dikirim','diterima') NOT NULL DEFAULT 'draft'");
        }
    }
};
