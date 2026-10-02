<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_bots', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->text('token');
            $t->boolean('enabled')->default(true);
            $t->boolean('commands_enabled')->default(false);
            $t->json('commands')->nullable();
            $t->unsignedBigInteger('update_offset')->default(0);
            $t->timestamp('last_success_at')->nullable();
            $t->timestamp('last_failure_at')->nullable();
            $t->string('failure_code')->nullable();
            $t->timestamps();
        });
        Schema::create('telegram_destinations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('telegram_bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $t->string('name');
            $t->string('chat_id', 100);
            $t->string('type', 20)->default('private');
            $t->string('detail_level', 20)->default('summary');
            $t->boolean('enabled')->default(true);
            $t->json('allowed_user_ids')->nullable();
            $t->timestamps();
            $t->unique(['telegram_bot_id', 'chat_id']);
        });
        Schema::create('telegram_rules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('telegram_bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $t->string('name');
            $t->string('trigger', 60)->index();
            $t->boolean('legacy_reminder')->default(false);
            $t->boolean('enabled')->default(true);
            $t->string('mode', 20)->default('instant');
            $t->string('priority', 20)->default('normal');
            $t->unsignedInteger('minutes')->default(60);
            $t->unsignedInteger('threshold')->default(1);
            $t->unsignedInteger('window_minutes')->default(60);
            $t->unsignedInteger('cooldown_minutes')->default(1440);
            $t->string('send_time', 5)->default('08:00');
            $t->string('quiet_start', 5)->nullable();
            $t->string('quiet_end', 5)->nullable();
            $t->json('sections')->nullable();
            $t->json('conditions')->nullable();
            $t->text('template');
            $t->timestamps();
        });
        Schema::create('telegram_destination_rule', function (Blueprint $t): void {
            $t->foreignId('telegram_rule_id')->constrained('telegram_rules')->cascadeOnDelete();
            $t->foreignId('telegram_destination_id')->constrained('telegram_destinations')->cascadeOnDelete();
            $t->primary(['telegram_rule_id', 'telegram_destination_id']);
        });
        Schema::create('telegram_rule_states', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('telegram_rule_id')->constrained('telegram_rules')->cascadeOnDelete();
            $t->string('entity', 64);
            $t->timestamp('last_emitted_at')->nullable();
            $t->timestamps();
            $t->unique(['telegram_rule_id', 'entity']);
        });
        Schema::create('telegram_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('telegram_bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $t->foreignId('telegram_destination_id')->constrained('telegram_destinations')->cascadeOnDelete();
            $t->foreignId('telegram_rule_id')->nullable()->constrained('telegram_rules')->nullOnDelete();
            $t->string('dedupe_key', 64)->unique();
            $t->string('trigger', 60);
            $t->text('payload');
            $t->string('status', 20)->default('pending');
            $t->timestamp('due_at');
            $t->timestamp('attempted_at')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedInteger('next_part')->default(0);
            $t->json('message_ids')->nullable();
            $t->string('failure_code')->nullable();
            $t->timestamps();
            $t->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        foreach (['telegram_deliveries', 'telegram_rule_states', 'telegram_destination_rule', 'telegram_rules', 'telegram_destinations', 'telegram_bots'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
