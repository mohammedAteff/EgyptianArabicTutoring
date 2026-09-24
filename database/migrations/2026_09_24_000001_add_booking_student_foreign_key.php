<?php

use App\Domains\Database\Services\DatabaseCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'bookings_student_id_foreign';

    public function up(): void
    {
        if ($this->constraintExists()) {
            return;
        }

        $orphans = DB::table('bookings as bookings')
            ->whereNotNull('bookings.student_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('students as students')
                ->whereColumn('students.id', 'bookings.student_id'))
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("Cannot add bookings.student_id foreign key: {$orphans} orphaned booking rows require manual triage.");
        }

        if (app()->environment('testing', 'local')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreign('student_id', self::CONSTRAINT)
                    ->references('id')
                    ->on('students')
                    ->nullOnDelete();
            });

            return;
        }

        $capability = new DatabaseCapability(deferReconciledCheck: true);
        try {
            $capability->assertMatchesReconciledEnvironment(
                config('database.reconciled.vendor'),
                config('database.reconciled.version'),
            );
        } catch (Throwable $exception) {
            $this->logManualStrategy($capability, $exception->getMessage());
            throw $exception;
        }

        if (! in_array($capability->vendor(), ['mysql', 'mariadb'], true)) {
            $message = 'No lock-free foreign-key deployment strategy is verified for this database vendor.';
            $this->logManualStrategy($capability, $message);
            throw new RuntimeException($message);
        }

        $algorithm = DatabaseCapability::onlineForeignKeyAddAlgorithm($capability->vendor(), $capability->version());

        try {
            DB::statement(
                'ALTER TABLE bookings ADD CONSTRAINT '.self::CONSTRAINT.' '
                .'FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL, '
                .'ALGORITHM='.$algorithm.', LOCK=NONE'
            );
        } catch (Throwable $exception) {
            $this->logManualStrategy($capability, $exception->getMessage());
            throw new RuntimeException(
                "Migration 8B was stopped because the server refused the explicitly requested {$algorithm}/LOCK=NONE foreign-key change. "
                .'Use the logged maintenance-window or reviewed online-schema-change plan; foreign_key_checks was not disabled.',
                previous: $exception,
            );
        }
    }

    public function down(): void
    {
        if (! $this->constraintExists()) {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP FOREIGN KEY '.self::CONSTRAINT);
    }

    private function constraintExists(): bool
    {
        return DB::table('information_schema.key_column_usage')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'bookings')
            ->where('COLUMN_NAME', 'student_id')
            ->where('REFERENCED_TABLE_NAME', 'students')
            ->where('REFERENCED_COLUMN_NAME', 'id')
            ->exists();
    }

    private function logManualStrategy(DatabaseCapability $capability, string $reason): void
    {
        $message = 'Migration 8B requires a reviewed manual deployment: verify a restorable backup and zero orphan rows, then run '
            .'the exact ALTER TABLE statement only inside a maintenance window or via a reviewed online-schema-change plan. '
            .'Do not disable foreign_key_checks. '
            .'ALTER TABLE bookings ADD CONSTRAINT '.self::CONSTRAINT.' FOREIGN KEY (student_id) '
            .'REFERENCES students (id) ON DELETE SET NULL, ALGORITHM='
            .DatabaseCapability::onlineForeignKeyAddAlgorithm($capability->vendor(), $capability->version())
            .', LOCK=NONE.';

        Log::critical($message, [
            'vendor' => $capability->vendor(),
            'version' => $capability->version(),
            'reason' => $reason,
        ]);
    }
};
