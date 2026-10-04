<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        if (! Schema::hasIndex('session_ledger_entries', 'ledger_full_provenance_unique')) {
            DB::statement('ALTER TABLE session_ledger_entries ADD UNIQUE INDEX ledger_full_provenance_unique (id, booking_id, student_package_id, student_package_entitlement_id, entitlement_type_id)');
        }
        if (! DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', 'bookings')->where('CONSTRAINT_NAME', 'booking_full_debit_provenance_fk')->exists()) {
            DB::statement('ALTER TABLE bookings ADD CONSTRAINT booking_full_debit_provenance_fk FOREIGN KEY (consumed_ledger_entry_id, id, student_package_id, student_package_entitlement_id, entitlement_type_id) REFERENCES session_ledger_entries (id, booking_id, student_package_id, student_package_entitlement_id, entitlement_type_id)');
        }
        // MariaDB forbids CHECK expressions on cascade-owned columns; guards preserve merge cascades.
        foreach (['INSERT', 'UPDATE'] as $event) {
            $suffix = strtolower($event);
            DB::unprepared("CREATE TRIGGER ledger_typed_{$suffix}_guard BEFORE {$event} ON session_ledger_entries FOR EACH ROW BEGIN
                IF NOT ((NEW.student_package_entitlement_id IS NULL AND NEW.entitlement_type_id IS NULL) OR (NEW.student_package_entitlement_id IS NOT NULL AND NEW.entitlement_type_id IS NOT NULL AND NEW.student_package_id IS NOT NULL)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Incomplete ledger allocation provenance';
                END IF;
                IF NEW.student_package_entitlement_id IS NOT NULL AND NOT ((NEW.entry_type IN ('package_grant','cancellation_restore') AND NEW.credit_change > 0) OR (NEW.entry_type IN ('session_consumed','expiration_forfeit') AND NEW.credit_change < 0) OR (NEW.entry_type = 'courtesy_adjustment' AND NEW.credit_change <> 0)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid typed ledger units';
                END IF;
            END");
            DB::unprepared("CREATE TRIGGER booking_typed_{$suffix}_guard BEFORE {$event} ON bookings FOR EACH ROW BEGIN
                IF NOT ((NEW.funding_mode = 'package' AND NEW.entitlement_type_id IS NOT NULL AND NEW.entitlement_code IS NOT NULL AND NEW.entitlement_units IS NOT NULL AND NEW.entitlement_units > 0 AND NEW.student_package_id IS NOT NULL AND NEW.student_package_entitlement_id IS NOT NULL AND NEW.consumed_ledger_entry_id IS NOT NULL) OR (NEW.funding_mode IN ('direct','free','legacy') AND NEW.entitlement_type_id IS NULL AND NEW.entitlement_code IS NULL AND NEW.entitlement_units IS NULL AND NEW.student_package_id IS NULL AND NEW.student_package_entitlement_id IS NULL AND NEW.consumed_ledger_entry_id IS NULL)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Incomplete booking entitlement snapshot';
                END IF;
            END");
        }
        DB::statement("ALTER TABLE session_types ADD CONSTRAINT session_required_entitlement_check CHECK ((funding_mode = 'package' AND required_entitlement_type_id IS NOT NULL AND required_entitlement_units IS NOT NULL AND required_entitlement_units > 0) OR (funding_mode IN ('direct','free','legacy') AND required_entitlement_type_id IS NULL AND required_entitlement_units IS NULL))");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        DB::statement('ALTER TABLE bookings DROP FOREIGN KEY booking_full_debit_provenance_fk');
        DB::statement('ALTER TABLE session_ledger_entries DROP INDEX ledger_full_provenance_unique');
        foreach (['ledger', 'booking'] as $table) {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$table.'_typed_'.$event.'_guard');
            }
        }
        DB::statement('ALTER TABLE session_types DROP CONSTRAINT session_required_entitlement_check');
    }
};
