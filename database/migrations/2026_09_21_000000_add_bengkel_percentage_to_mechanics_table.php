<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rasio porsi bengkel per mekanik (pendamping mechanic_percentage).
        Schema::table('mechanics', function (Blueprint $table) {
            $table->decimal('bengkel_percentage', 5, 2)->nullable()->after('mechanic_percentage');
        });

        // Backfill: rasio bengkel = komplemen rasio mekanik untuk data lama.
        DB::table('mechanics')->update([
            'bengkel_percentage' => DB::raw('100 - mechanic_percentage'),
        ]);
    }

    public function down(): void
    {
        Schema::table('mechanics', function (Blueprint $table) {
            $table->dropColumn('bengkel_percentage');
        });
    }
};