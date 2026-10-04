<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->string('kind', 24);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('resource_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('disk', 24)->nullable();
            $table->string('path')->nullable();
            $table->text('url')->nullable();
            $table->boolean('student_visible')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'withdrawn_at', 'student_visible', 'sort_order'], 'lesson_materials_projection');
        });
        DB::statement("ALTER TABLE lesson_materials ADD CONSTRAINT lesson_materials_payload CHECK (
            kind IN ('private_file', 'resource', 'external_link', 'recording') AND (
                (withdrawn_at IS NOT NULL AND student_visible = 0 AND resource_id IS NULL AND url IS NULL
                    AND ((disk IS NULL AND path IS NULL) OR (kind = 'private_file' AND disk IS NOT NULL AND disk = 'local' AND path IS NOT NULL)))
                OR (withdrawn_at IS NULL AND (
                    (kind = 'private_file' AND disk IS NOT NULL AND disk = 'local' AND path IS NOT NULL AND resource_id IS NULL AND url IS NULL)
                    OR (kind = 'resource' AND resource_id IS NOT NULL AND disk IS NULL AND path IS NULL AND url IS NULL)
                    OR (kind IN ('external_link', 'recording') AND url IS NOT NULL AND disk IS NULL AND path IS NULL AND resource_id IS NULL)
                ))
            ))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_materials');
    }
};
