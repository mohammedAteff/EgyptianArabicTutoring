<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $badPayments = DB::table('payment_records as m')->join('student_packages as p', 'p.id', '=', 'm.student_package_id')
            ->where(fn ($q) => $q->whereColumn('m.student_id', '!=', 'p.student_id')->orWhereColumn('m.currency', '!=', 'p.currency')->orWhere('m.amount_paid', '<', 0))->exists();
        $badRefunds = DB::table('payment_refunds as r')->join('payment_records as m', 'm.id', '=', 'r.payment_record_id')
            ->where(fn ($q) => $q->whereColumn('r.student_id', '!=', 'm.student_id')->orWhereColumn('r.student_package_id', '!=', 'm.student_package_id')->orWhereColumn('r.currency', '!=', 'm.currency')->orWhere('r.amount_refunded', '<', 0))->exists();
        if ($badPayments || $badRefunds) {
            throw new RuntimeException('Financial ownership/currency/amount preflight failed. No records were changed; reconcile the identified records before migration.');
        }
        DB::statement('ALTER TABLE student_packages ADD UNIQUE INDEX package_currency_unique (id, currency)');
        DB::statement('ALTER TABLE payment_records ADD UNIQUE INDEX payment_financial_owner_unique (id, student_package_id, student_id)');
        DB::statement('ALTER TABLE payment_records ADD UNIQUE INDEX payment_currency_unique (id, currency)');
        DB::statement('ALTER TABLE payment_records ADD CONSTRAINT payment_purchase_owner_fk FOREIGN KEY (student_package_id, student_id) REFERENCES student_packages (id, student_id) ON UPDATE CASCADE ON DELETE RESTRICT');
        DB::statement('ALTER TABLE payment_refunds ADD CONSTRAINT refund_payment_owner_fk FOREIGN KEY (payment_record_id, student_package_id, student_id) REFERENCES payment_records (id, student_package_id, student_id) ON UPDATE CASCADE ON DELETE RESTRICT');
        DB::statement('ALTER TABLE payment_records ADD CONSTRAINT payment_purchase_currency_fk FOREIGN KEY (student_package_id, currency) REFERENCES student_packages (id, currency) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE payment_refunds ADD CONSTRAINT refund_payment_currency_fk FOREIGN KEY (payment_record_id, currency) REFERENCES payment_records (id, currency) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE payment_records ADD CONSTRAINT payment_nonnegative CHECK (amount_paid >= 0)');
        DB::statement('ALTER TABLE payment_refunds ADD CONSTRAINT refund_nonnegative CHECK (amount_refunded >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_refunds DROP FOREIGN KEY refund_payment_owner_fk, DROP FOREIGN KEY refund_payment_currency_fk, DROP CONSTRAINT refund_nonnegative');
        DB::statement('ALTER TABLE payment_records DROP FOREIGN KEY payment_purchase_owner_fk, DROP FOREIGN KEY payment_purchase_currency_fk, DROP CONSTRAINT payment_nonnegative, DROP INDEX payment_financial_owner_unique, DROP INDEX payment_currency_unique');
        DB::statement('ALTER TABLE student_packages DROP INDEX package_currency_unique');
    }
};
