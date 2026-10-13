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
        Schema::create('resource_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 190);
            $table->string('slug', 190)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('resource_collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained('resource_collections')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['collection_id', 'resource_id'], 'res_coll_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_collection_items');
        Schema::dropIfExists('resource_collections');
    }
};
