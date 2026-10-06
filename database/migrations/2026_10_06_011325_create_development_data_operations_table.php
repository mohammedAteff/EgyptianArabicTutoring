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
        Schema::create('development_data_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administrator_id')->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('session_binding', 64);
            $table->char('security_fingerprint', 64);
            $table->string('type', 16);
            $table->string('domain', 32);
            $table->json('scope');
            $table->json('preview');
            $table->string('status', 16)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('archive_path', 255)->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['administrator_id', 'status', 'created_at'], 'development_operations_owner_status');
            $table->index(['status', 'expires_at'], 'development_operations_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_data_operations');
    }
};
