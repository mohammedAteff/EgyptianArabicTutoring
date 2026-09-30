<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dbName = DB::getDatabaseName();

        $getFkDetails = function (string $table, string $column) use ($dbName) {
            return DB::table('information_schema.KEY_COLUMN_USAGE as kcu')
                ->join('information_schema.REFERENTIAL_CONSTRAINTS as rc', function ($join) {
                    $join->on('kcu.CONSTRAINT_NAME', '=', 'rc.CONSTRAINT_NAME')
                        ->on('kcu.CONSTRAINT_SCHEMA', '=', 'rc.CONSTRAINT_SCHEMA');
                })
                ->where('kcu.TABLE_SCHEMA', $dbName)
                ->where('kcu.TABLE_NAME', $table)
                ->where('kcu.COLUMN_NAME', $column)
                ->whereNotNull('kcu.REFERENCED_TABLE_NAME')
                ->select([
                    'kcu.CONSTRAINT_NAME as name',
                    'kcu.REFERENCED_TABLE_NAME as ref_table',
                    'kcu.REFERENCED_COLUMN_NAME as ref_column',
                    'rc.UPDATE_RULE as update_rule',
                    'rc.DELETE_RULE as delete_rule',
                ])
                ->get();
        };

        $getColMeta = function (string $table, string $column) use ($dbName) {
            return DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $dbName)
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->first();
        };

        // =========================================================================
        // PHASE A1: READ-ONLY PREFLIGHT (NO MUTATIONS BEFORE ALL CHECKS PASS)
        // =========================================================================

        // 1. Mandatory presence and strict topology check for forms.prompt_trigger
        if (! Schema::hasColumn('forms', 'prompt_trigger')) {
            throw new RuntimeException('PREFLIGHT ABORT: forms.prompt_trigger is absent; Migration A requires the legacy intermediate schema.');
        }

        $promptCol = $getColMeta('forms', 'prompt_trigger');
        if (
            ! $promptCol
            || strtolower((string) $promptCol->DATA_TYPE) !== 'enum'
            || (string) $promptCol->COLUMN_TYPE !== "enum('none','after_booking','after_reschedule','next_session_check')"
            || $promptCol->IS_NULLABLE !== 'NO'
            || $promptCol->COLUMN_DEFAULT !== "'none'"
        ) {
            throw new RuntimeException('PREFLIGHT ABORT: forms.prompt_trigger does not strictly match verified legacy enum topology.');
        }

        // 2. Comprehensive preflight on parent primary keys before foreign key assignment
        $formsIdCol = $getColMeta('forms', 'id');
        if (! $formsIdCol || strtolower($formsIdCol->DATA_TYPE) !== 'bigint' || ! str_contains(strtolower($formsIdCol->COLUMN_TYPE), 'unsigned')) {
            throw new RuntimeException('PREFLIGHT ABORT: forms.id parent primary key must be unsigned bigint.');
        }

        $contactsIdCol = $getColMeta('contacts', 'id');
        if (! $contactsIdCol || strtolower($contactsIdCol->DATA_TYPE) !== 'bigint' || ! str_contains(strtolower($contactsIdCol->COLUMN_TYPE), 'unsigned')) {
            throw new RuntimeException('PREFLIGHT ABORT: contacts.id parent primary key must be unsigned bigint.');
        }

        $bookingsIdCol = $getColMeta('bookings', 'id');
        if (! $bookingsIdCol || strtolower($bookingsIdCol->DATA_TYPE) !== 'bigint' || ! str_contains(strtolower($bookingsIdCol->COLUMN_TYPE), 'unsigned')) {
            throw new RuntimeException('PREFLIGHT ABORT: bookings.id parent primary key must be unsigned bigint.');
        }

        // 3. Preflight on existing form_triggers table if already present
        if (Schema::hasTable('form_triggers')) {
            $triggerCol = $getColMeta('form_triggers', 'trigger_name');
            $formIdCol = $getColMeta('form_triggers', 'form_id');
            $formFks = $getFkDetails('form_triggers', 'form_id');

            if ($formFks->count() !== 1) {
                throw new RuntimeException('PREFLIGHT ABORT: form_triggers.form_id must have exactly one foreign key.');
            }

            $formFk = $formFks->first();
            $uniqueIndexMatches = (bool) DB::selectOne("
                SELECT 1
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = 'form_triggers'
                  AND INDEX_NAME = 'unique_form_trigger'
                  AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING COUNT(*) = 2
                  AND SUM(CASE WHEN COLUMN_NAME = 'form_id' AND SEQ_IN_INDEX = 1 THEN 1 ELSE 0 END) = 1
                  AND SUM(CASE WHEN COLUMN_NAME = 'trigger_name' AND SEQ_IN_INDEX = 2 THEN 1 ELSE 0 END) = 1
            ", [$dbName]);

            if (! $triggerCol || strtolower($triggerCol->DATA_TYPE) !== 'varchar' || (int) $triggerCol->CHARACTER_MAXIMUM_LENGTH !== 40 || $triggerCol->IS_NULLABLE !== 'NO') {
                throw new RuntimeException('PREFLIGHT ABORT: form_triggers.trigger_name does not match NOT NULL varchar(40).');
            }

            if (! $formIdCol || strtolower($formIdCol->DATA_TYPE) !== 'bigint' || ! str_contains(strtolower($formIdCol->COLUMN_TYPE), 'unsigned') || $formIdCol->IS_NULLABLE !== 'NO') {
                throw new RuntimeException('PREFLIGHT ABORT: form_triggers.form_id does not match NOT NULL unsigned bigint.');
            }

            if ($formFk->ref_table !== 'forms' || $formFk->ref_column !== 'id' || strtoupper($formFk->delete_rule) !== 'CASCADE' || ! in_array(strtoupper($formFk->update_rule), ['RESTRICT', 'NO ACTION'], true)) {
                throw new RuntimeException('PREFLIGHT ABORT: form_triggers.form_id foreign key constraint mismatch.');
            }

            if (! $uniqueIndexMatches) {
                throw new RuntimeException('PREFLIGHT ABORT: form_triggers missing unique_form_trigger index.');
            }

            $unapprovedTriggers = DB::table('form_triggers')
                ->whereNotIn('trigger_name', ['pre_booking', 'after_booking', 'after_reschedule', 'next_session_check'])
                ->pluck('trigger_name')
                ->toArray();

            if (! empty($unapprovedTriggers)) {
                throw new RuntimeException('PREFLIGHT ABORT: form_triggers contains unapproved trigger values: '.implode(',', $unapprovedTriggers));
            }

            // Conflict Guard: Pre-existing multiple pre_booking rows
            $preBookingCount = DB::table('form_triggers')->where('trigger_name', 'pre_booking')->count();
            if ($preBookingCount > 1) {
                $conflictingIds = DB::table('form_triggers')->where('trigger_name', 'pre_booking')->pluck('form_id')->implode(', ');
                throw new RuntimeException("PREFLIGHT ABORT: Multiple form_triggers already assigned pre_booking trigger. Conflicting form IDs: [{$conflictingIds}].");
            }
        }

        // 4. Integrity: Verify form_submissions.student_id topology and absence of orphans
        $studentCol = $getColMeta('form_submissions', 'student_id');
        if (! $studentCol || strtolower($studentCol->DATA_TYPE) !== 'bigint' || ! str_contains(strtolower($studentCol->COLUMN_TYPE), 'unsigned') || $studentCol->IS_NULLABLE !== 'NO') {
            throw new RuntimeException('PREFLIGHT ABORT: form_submissions.student_id must be NOT NULL unsigned bigint.');
        }

        $orphanedStudents = DB::table('form_submissions as fs')
            ->leftJoin('students as s', 'fs.student_id', '=', 's.id')
            ->whereNotNull('fs.student_id')
            ->whereNull('s.id')
            ->count();

        if ($orphanedStudents > 0) {
            throw new RuntimeException("PREFLIGHT ABORT: Found {$orphanedStudents} orphaned student_id entries in form_submissions.");
        }

        // 5. Integrity: Verify existing form_submissions.contact_id topology and orphan data
        if (Schema::hasColumn('form_submissions', 'contact_id')) {
            $contactCol = $getColMeta('form_submissions', 'contact_id');
            if (strtolower($contactCol->DATA_TYPE) !== 'bigint' || ! str_contains(strtolower($contactCol->COLUMN_TYPE), 'unsigned') || $contactCol->IS_NULLABLE !== 'YES') {
                throw new RuntimeException('PREFLIGHT ABORT: Existing form_submissions.contact_id must be nullable unsigned bigint.');
            }

            $orphanedContacts = DB::table('form_submissions as fs')
                ->leftJoin('contacts as c', 'fs.contact_id', '=', 'c.id')
                ->whereNotNull('fs.contact_id')
                ->whereNull('c.id')
                ->count();

            if ($orphanedContacts > 0) {
                throw new RuntimeException("PREFLIGHT ABORT: Found {$orphanedContacts} orphaned contact_id entries in form_submissions.");
            }

            $contactFks = $getFkDetails('form_submissions', 'contact_id');
            if ($contactFks->count() > 1) {
                throw new RuntimeException('PREFLIGHT ABORT: Multiple foreign keys found on form_submissions.contact_id.');
            }

            if ($contactFks->count() === 1) {
                $contactFk = $contactFks->first();
                if ($contactFk->ref_table !== 'contacts' || $contactFk->ref_column !== 'id' || strtoupper($contactFk->delete_rule) !== 'SET NULL' || ! in_array(strtoupper($contactFk->update_rule), ['RESTRICT', 'NO ACTION'], true)) {
                    throw new RuntimeException('PREFLIGHT ABORT: form_submissions.contact_id foreign key constraint mismatch.');
                }
            }
        }

        // 6. Integrity: Verify existing form_submissions.booking_id topology and orphan data
        if (Schema::hasColumn('form_submissions', 'booking_id')) {
            $bookingCol = $getColMeta('form_submissions', 'booking_id');
            if (strtolower($bookingCol->DATA_TYPE) !== 'bigint' || ! str_contains(strtolower($bookingCol->COLUMN_TYPE), 'unsigned') || $bookingCol->IS_NULLABLE !== 'YES') {
                throw new RuntimeException('PREFLIGHT ABORT: Existing form_submissions.booking_id must be nullable unsigned bigint.');
            }

            $orphanedBookings = DB::table('form_submissions as fs')
                ->leftJoin('bookings as b', 'fs.booking_id', '=', 'b.id')
                ->whereNotNull('fs.booking_id')
                ->whereNull('b.id')
                ->count();

            if ($orphanedBookings > 0) {
                throw new RuntimeException("PREFLIGHT ABORT: Found {$orphanedBookings} orphaned booking_id entries in form_submissions.");
            }

            $bookingFks = $getFkDetails('form_submissions', 'booking_id');
            if ($bookingFks->count() > 1) {
                throw new RuntimeException('PREFLIGHT ABORT: Multiple foreign keys found on form_submissions.booking_id.');
            }

            if ($bookingFks->count() === 1) {
                $bookingFk = $bookingFks->first();
                if ($bookingFk->ref_table !== 'bookings' || $bookingFk->ref_column !== 'id' || strtoupper($bookingFk->delete_rule) !== 'SET NULL' || ! in_array(strtoupper($bookingFk->update_rule), ['RESTRICT', 'NO ACTION'], true)) {
                    throw new RuntimeException('PREFLIGHT ABORT: form_submissions.booking_id foreign key constraint mismatch.');
                }
            }
        }

        // =========================================================================
        // PHASE A2: DDL AND DATA MUTATION
        // =========================================================================

        if (! Schema::hasTable('form_triggers')) {
            Schema::create('form_triggers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
                $table->string('trigger_name', 40);
                $table->unique(['form_id', 'trigger_name'], 'unique_form_trigger');
            });
        }

        DB::statement("
            INSERT INTO form_triggers (form_id, trigger_name)
            SELECT id, prompt_trigger
            FROM forms
            WHERE prompt_trigger IS NOT NULL AND prompt_trigger != 'none'
            ON DUPLICATE KEY UPDATE trigger_name = VALUES(trigger_name)
        ");

        if (! Schema::hasColumn('form_submissions', 'contact_id')) {
            Schema::table('form_submissions', function (Blueprint $table) {
                $table->foreignId('contact_id')->nullable()->after('student_id')->constrained('contacts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('form_submissions', 'booking_id')) {
            Schema::table('form_submissions', function (Blueprint $table) {
                $table->foreignId('booking_id')->nullable()->after('contact_id')->constrained('bookings')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new LogicException('Migration A is a forward-only compatibility migration and must not be rolled back.');
    }
};
