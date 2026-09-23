<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nominal yang benar-benar dibayar customer (DP atau lunas).
        // Sisa yang belum dibayar = grand_total - paid_amount.
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
