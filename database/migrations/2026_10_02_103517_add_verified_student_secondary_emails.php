<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('email_normalized', 255)->unique();
            $table->timestamp('verified_at');
            $table->timestamps();
        });
        Schema::create('student_email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('email_normalized', 255);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('resource_requests', function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->text('submitted_email')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Verified identities and resource activity require a reviewed forward migration.');
    }
};
