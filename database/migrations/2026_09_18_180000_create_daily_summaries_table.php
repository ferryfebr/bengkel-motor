<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ringkasan harian agar laporan berat tidak hitung ulang seluruh riwayat
        // (RESIKO_HOSTING.md #10, RINGKASAN_SISTEM_v3.md §K9).
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->unique();
            $table->decimal('gross_revenue', 14, 2)->default(0);
            $table->decimal('net_revenue', 14, 2)->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->decimal('total_cash_in', 14, 2)->default(0);
            $table->decimal('total_cash_out', 14, 2)->default(0);
            $table->timestamps();

            $table->index('summary_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
