<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jenis/tipe motor, diinput kasir saat motor masuk (bebas teks).
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('motor_type', 100)->nullable()->after('plate_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('motor_type');
        });
    }
};
