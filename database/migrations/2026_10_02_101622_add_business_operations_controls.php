<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['students', 'administrators'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->timestamp('suspended_at')->nullable();
                $table->foreignId('suspended_by')->nullable()->constrained('administrators')->nullOnDelete();
                $table->string('suspension_reason', 1000)->nullable();
            });
        }
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        foreach (['PayPal - Manual', 'InstaPay (Egypt)', 'Bank Wire', 'Vodafone Cash', 'Wise', 'Cash'] as $order => $name) {
            DB::table('payment_methods')->insert(['name' => $name, 'sort_order' => $order, 'is_default' => $order === 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('payment_records', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Business account and payment history must be preserved; use a reviewed forward migration.');
    }
};
