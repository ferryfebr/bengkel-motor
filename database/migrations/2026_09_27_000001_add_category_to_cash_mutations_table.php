<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_mutations', function (Blueprint $table) {
            $table->enum('category', [
                'manual',
                'external_product',
                'mechanic_payout',
                'refund',
                'transaction_income',
            ])->default('manual')->after('description');
        });

        // Backfill data lama:
        // Kas keluar otomatis dari HPP produk luar.
        DB::table('cash_mutations')
            ->whereNotNull('transaction_id')
            ->where('type', 'out')
            ->update(['category' => 'external_product']);

        // Kas masuk dari pembayaran transaksi.
        DB::table('cash_mutations')
            ->whereNotNull('transaction_id')
            ->where('type', 'in')
            ->update(['category' => 'transaction_income']);

        // Gaji mekanik (tidak terhubung transaksi, deskripsi diawali "Gaji mekanik -").
        DB::table('cash_mutations')
            ->whereNull('transaction_id')
            ->where('description', 'like', 'Gaji mekanik -%')
            ->update(['category' => 'mechanic_payout']);
    }

    public function down(): void
    {
        Schema::table('cash_mutations', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
