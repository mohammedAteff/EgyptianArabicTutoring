<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `session_ledger_entries` MODIFY COLUMN `entry_type` ENUM('package_grant', 'session_consumed', 'courtesy_adjustment', 'cancellation_restore', 'expiration_forfeit', 'transfer_out', 'transfer_in') NOT NULL");

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS ledger_typed_{$event}_guard");
                $upper = strtoupper($event);
                DB::unprepared("CREATE TRIGGER ledger_typed_{$event}_guard BEFORE {$upper} ON session_ledger_entries FOR EACH ROW BEGIN
                    IF NOT ((NEW.student_package_entitlement_id IS NULL AND NEW.entitlement_type_id IS NULL) OR (NEW.student_package_entitlement_id IS NOT NULL AND NEW.entitlement_type_id IS NOT NULL AND NEW.student_package_id IS NOT NULL)) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Incomplete ledger allocation provenance';
                    END IF;
                    IF NEW.student_package_entitlement_id IS NOT NULL AND NOT (
                        (NEW.entry_type IN ('package_grant', 'cancellation_restore', 'transfer_in') AND NEW.credit_change > 0)
                        OR (NEW.entry_type IN ('session_consumed', 'expiration_forfeit', 'transfer_out') AND NEW.credit_change < 0)
                        OR (NEW.entry_type = 'courtesy_adjustment' AND NEW.credit_change <> 0)
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid typed ledger units';
                    END IF;
                END");
            }
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS ledger_typed_{$event}_guard");
                $upper = strtoupper($event);
                DB::unprepared("CREATE TRIGGER ledger_typed_{$event}_guard BEFORE {$upper} ON session_ledger_entries FOR EACH ROW BEGIN
                    IF NOT ((NEW.student_package_entitlement_id IS NULL AND NEW.entitlement_type_id IS NULL) OR (NEW.student_package_entitlement_id IS NOT NULL AND NEW.entitlement_type_id IS NOT NULL AND NEW.student_package_id IS NOT NULL)) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Incomplete ledger allocation provenance';
                    END IF;
                    IF NEW.student_package_entitlement_id IS NOT NULL AND NOT (
                        (NEW.entry_type IN ('package_grant', 'cancellation_restore') AND NEW.credit_change > 0)
                        OR (NEW.entry_type IN ('session_consumed', 'expiration_forfeit') AND NEW.credit_change < 0)
                        OR (NEW.entry_type = 'courtesy_adjustment' AND NEW.credit_change <> 0)
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid typed ledger units';
                    END IF;
                END");
            }
        }

        DB::statement("ALTER TABLE `session_ledger_entries` MODIFY COLUMN `entry_type` ENUM('package_grant', 'session_consumed', 'courtesy_adjustment', 'cancellation_restore', 'expiration_forfeit') NOT NULL");
    }
};
