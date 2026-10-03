<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ringkasan komisi harian per mekanik, agar laporan/gaji tetap utuh
        // setelah transaksi lama dihapus oleh retensi (RESIKO_HOSTING.md).
        Schema::create('mechanic_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mechanic_id')->constrained('mechanics')->cascadeOnDelete();
            $table->date('summary_date');
            $table->unsignedInteger('total_jobs')->default(0);
            $table->decimal('total_share', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['mechanic_id', 'summary_date']);
            $table->index('summary_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanic_daily_summaries');
    }
};
