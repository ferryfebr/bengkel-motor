<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_summaries', function (Blueprint $table) {
            // Rincian biaya agar laporan omset bersih bisa menampilkan spend.
            $table->decimal('refund_total', 14, 2)->default(0)->after('net_revenue');
            $table->decimal('cogs', 14, 2)->default(0)->after('refund_total');
            $table->decimal('mechanic_fee', 14, 2)->default(0)->after('cogs');
            $table->decimal('bengkel_fee', 14, 2)->default(0)->after('mechanic_fee');
        });
    }

    public function down(): void
    {
        Schema::table('daily_summaries', function (Blueprint $table) {
            $table->dropColumn(['refund_total', 'cogs', 'mechanic_fee', 'bengkel_fee']);
        });
    }
};
