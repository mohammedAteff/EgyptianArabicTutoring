<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entitlement_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 100);
            $table->unsignedSmallInteger('nominal_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        foreach (['one_hour' => ['1-hour sessions', 60], 'two_hour' => ['2-hour sessions', 120]] as $code => [$label, $minutes]) {
            DB::table('entitlement_types')->insert(['code' => $code, 'label' => $label, 'nominal_minutes' => $minutes, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('student_packages', function (Blueprint $table): void {
            $table->string('offering_key', 60)->nullable()->index();
            $table->string('identity_state', 30)->default('legacy_unclassified');
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->char('purchase_fingerprint', 64)->nullable();
            $table->unique(['id', 'student_id'], 'package_owner_unique');
        });
        Schema::create('student_package_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_package_id');
            $table->unsignedBigInteger('student_id');
            $table->foreignId('entitlement_type_id')->constrained('entitlement_types')->restrictOnDelete();
            $table->unsignedInteger('granted_quantity');
            $table->timestamps();
            $table->foreign(['student_package_id', 'student_id'], 'allocation_package_owner_fk')->references(['id', 'student_id'])->on('student_packages')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['student_package_id', 'entitlement_type_id'], 'allocation_package_type_unique');
            $table->unique(['id', 'student_package_id', 'entitlement_type_id'], 'allocation_provenance_unique');
            $table->unique(['id', 'student_id'], 'allocation_owner_unique');
        });
        Schema::table('session_ledger_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('student_package_entitlement_id')->nullable();
            $table->unsignedBigInteger('entitlement_type_id')->nullable();
            $table->foreign(['student_package_entitlement_id', 'student_package_id', 'entitlement_type_id'], 'ledger_allocation_provenance_fk')->references(['id', 'student_package_id', 'entitlement_type_id'])->on('student_package_entitlements')->restrictOnDelete();
            $table->foreign(['student_package_entitlement_id', 'student_id'], 'ledger_allocation_owner_fk')->references(['id', 'student_id'])->on('student_package_entitlements')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['student_package_entitlement_id', 'id'], 'ledger_allocation_balance_index');
            $table->unique(['id', 'booking_id'], 'ledger_booking_provenance_unique');
        });
        Schema::table('session_types', function (Blueprint $table): void {
            $table->string('funding_mode', 20)->default('legacy');
            $table->foreignId('required_entitlement_type_id')->nullable()->constrained('entitlement_types')->restrictOnDelete();
            $table->unsignedSmallInteger('required_entitlement_units')->nullable();
        });
        // The diagnostic action/slug is authoritative configuration; other history needs review.
        DB::table('session_types')->where('slug', 'diagnostic-session')->update([
            'funding_mode' => 'package',
            'required_entitlement_type_id' => DB::table('entitlement_types')->where('code', 'one_hour')->value('id'),
            'required_entitlement_units' => 1,
        ]);
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('funding_mode', 20)->default('legacy');
            $table->unsignedBigInteger('entitlement_type_id')->nullable();
            $table->string('entitlement_code', 40)->nullable();
            $table->unsignedSmallInteger('entitlement_units')->nullable();
            $table->unsignedBigInteger('student_package_id')->nullable();
            $table->unsignedBigInteger('student_package_entitlement_id')->nullable();
            $table->unsignedBigInteger('consumed_ledger_entry_id')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->foreign(['student_package_entitlement_id', 'student_package_id', 'entitlement_type_id'], 'booking_allocation_provenance_fk')->references(['id', 'student_package_id', 'entitlement_type_id'])->on('student_package_entitlements')->restrictOnDelete();
            $table->foreign(['student_package_entitlement_id', 'student_id'], 'booking_allocation_owner_fk')->references(['id', 'student_id'])->on('student_package_entitlements')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['consumed_ledger_entry_id', 'id'], 'booking_debit_owner_fk')->references(['id', 'booking_id'])->on('session_ledger_entries')->restrictOnDelete();
        });
        Schema::create('entitlement_mapping_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_package_id')->unique()->constrained()->restrictOnDelete();
            $table->char('manifest_hash', 64);
            $table->string('reviewer');
            $table->text('evidence');
            $table->json('snapshot');
            $table->timestamp('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('student_package_entitlements')->exists()) {
            throw new RuntimeException('Typed transactions require a reviewed forward recovery, not a destructive rollback.');
        }
        Schema::dropIfExists('entitlement_mapping_reviews');
        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['booking_allocation_provenance_fk', 'booking_allocation_owner_fk', 'booking_debit_owner_fk'] as $key) {
                $table->dropForeign($key);
            }
            $table->dropColumn(['funding_mode', 'entitlement_type_id', 'entitlement_code', 'entitlement_units', 'student_package_id', 'student_package_entitlement_id', 'consumed_ledger_entry_id', 'request_fingerprint']);
        });
        Schema::table('session_types', function (Blueprint $table): void {
            $table->dropForeign(['required_entitlement_type_id']);
            $table->dropColumn(['funding_mode', 'required_entitlement_type_id', 'required_entitlement_units']);
        });
        Schema::table('session_ledger_entries', function (Blueprint $table): void {
            $table->dropForeign('ledger_allocation_provenance_fk');
            $table->dropForeign('ledger_allocation_owner_fk');
            $table->dropUnique('ledger_booking_provenance_unique');
            $table->dropIndex('ledger_allocation_balance_index');
            $table->dropColumn(['student_package_entitlement_id', 'entitlement_type_id']);
        });
        Schema::dropIfExists('student_package_entitlements');
        Schema::table('student_packages', function (Blueprint $table): void {
            $table->dropUnique('package_owner_unique');
            $table->dropColumn(['offering_key', 'identity_state', 'validity_days', 'purchase_fingerprint']);
        });
        Schema::dropIfExists('entitlement_types');
    }
};
