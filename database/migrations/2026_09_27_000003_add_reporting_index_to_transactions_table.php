<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Mempercepat agregat laporan (filter status + rentang tanggal).
            $table->index(['work_status', 'payment_status', 'created_at'], 'transactions_report_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_report_idx');
        });
    }
};
