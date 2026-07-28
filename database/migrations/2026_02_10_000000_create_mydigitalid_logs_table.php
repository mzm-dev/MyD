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
        Schema::create('mydigitalid_logs', function (Blueprint $table) {
            $table->id();

            // Transaction ID dari MyDigitalID flow
            $table->string('transaction_id')->nullable()->index();

            // User yang melakukan login (nullable, kerana callback belum login)
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            // Jenis action: AUTH_REDIRECT, STATE_VALIDATION_FAIL, SECURITY_FAIL, TOKEN_EXCHANGE_FAIL, etc
            $table->string('action')->index();

            // Status transaksi: PENDING, SUCCESS, FAILED
            $table->string('status')->index();

            // URL yang dilawati
            $table->text('url')->nullable();

            // Data tambahan dalam JSON (untuk error messages, user info, etc)
            $table->json('payload')->nullable();

            // IP Address untuk security tracking
            $table->string('ip_address', 45)->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes untuk query yang efficient
            $table->index(['transaction_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mydigitalid_logs');
    }
};
