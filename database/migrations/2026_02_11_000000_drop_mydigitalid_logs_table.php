<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Audit trail MyDigitalID dipindah dari DB table ke log file khusus
        // (storage/logs/mydigitalid*.log) — elak data sensitif (token/NRIC)
        // duduk lama dalam DB yang boleh diakses ramai role/admin.
        Schema::dropIfExists('mydigitalid_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('mydigitalid_logs', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('action')->index();
            $table->string('status')->index();
            $table->text('url')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }
};
