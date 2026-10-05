<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak setiap proses retensi (transactions:retain / activity:prune).
        // TIDAK ikut kena retensi apa pun — konsisten dengan prinsip SECURITY.md.
        Schema::create('retention_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('run_type', 50);
            $table->string('trigger', 20)->default('cron');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('success');
            $table->unsignedInteger('archived_count')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->json('details')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('run_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_run_logs');
    }
};
