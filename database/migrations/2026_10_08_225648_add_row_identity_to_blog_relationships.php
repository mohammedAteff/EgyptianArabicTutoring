<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array RELATIONSHIPS = [
        'blog_related_articles' => 'related_blog_id',
        'blog_related_resources' => 'resource_id',
        'blog_related_games' => 'game_id',
    ];

    public function up(): void
    {
        foreach (self::RELATIONSHIPS as $tableName => $relatedColumn) {
            Schema::table($tableName, function (Blueprint $table) use ($relatedColumn): void {
                $table->unique(['blog_id', $relatedColumn]);
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropPrimary();
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->id();
            });
        }
    }

    public function down(): void
    {
        foreach (self::RELATIONSHIPS as $tableName => $relatedColumn) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('id');
            });
            Schema::table($tableName, function (Blueprint $table) use ($relatedColumn): void {
                $table->primary(['blog_id', $relatedColumn]);
            });
            Schema::table($tableName, function (Blueprint $table) use ($relatedColumn): void {
                $table->dropUnique(['blog_id', $relatedColumn]);
            });
        }
    }
};
