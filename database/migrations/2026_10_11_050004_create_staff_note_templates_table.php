<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_note_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('category', 60)->default('general');
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_note_templates');
    }
};
