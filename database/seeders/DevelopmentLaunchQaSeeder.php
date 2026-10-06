<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DevelopmentLaunchQaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bolt_landing_stage5_qa'
            || basename(base_path()) !== 'stage5-launch-qa' || DB::table('administrators')->exists()) {
            throw new \RuntimeException('This fixture is restricted to the empty, isolated Stage 5 browser QA application.');
        }
        Setting::set('business_timezone', 'Africa/Cairo');
        $actor = AdministratorFactory::new()->create(['name' => 'Stage 5 QA Super Admin', 'email' => 'stage5-super@example.test', 'role' => 'super_admin', 'password' => 'Stage5BrowserPass!']);
        foreach (['admin', 'assistant'] as $role) {
            AdministratorFactory::new()->create(['name' => 'Stage 5 QA '.ucfirst($role), 'email' => 'stage5-'.$role.'@example.test', 'role' => $role, 'password' => 'Stage5BrowserPass!']);
        }
        $mfa = AdministratorFactory::new()->create(['name' => 'Stage 5 QA MFA', 'email' => 'stage5-mfa@example.test', 'role' => 'super_admin', 'password' => 'Stage5BrowserPass!']);
        $mfa->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now('UTC'), 'two_factor_version' => (string) Str::uuid(),
            'two_factor_recovery_codes' => [Hash::make('stage5-login-recovery'), Hash::make('stage5-operation-recovery')]])->save();
        $student = Student::factory()->verified()->create(['first_name' => 'Synthetic', 'last_name' => 'Stage Five', 'email' => 'stage5-student@example.test', 'email_normalized' => 'stage5-student@example.test']);
        Contact::query()->create(['name' => 'Independent QA lead', 'email' => 'stage5-lead@example.test']);
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'QA purchase', 2, '80.00', '0.00', 'USD', null, 'stage5-qa-purchase', $actor->id, null, null, 'one_hour');
        $type = SessionType::factory()->create(['title' => 'QA package lesson', 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id, 'funding_mode' => 'legacy']);
        DB::transaction(fn () => $ledger->consumeForBooking($booking, 'stage5-qa-debit'));
        $ledger->recordPayment($package, '40.00', 'stage5-qa-payment', $actor->id);
        Storage::disk('local')->put('lesson-materials/stage5-qa.pdf', "%PDF-1.4\nSynthetic lesson document\n");
        LessonMaterial::factory()->create(['booking_id' => $booking->id, 'kind' => 'private_file', 'disk' => 'local', 'path' => 'lesson-materials/stage5-qa.pdf', 'url' => null]);
        $resource = ResourceFactory::new()->create(['title' => 'Synthetic Resource', 'file_path' => 'resources/stage5-qa.pdf', 'slug' => 'stage5-qa-resource']);
        Storage::disk('local')->put($resource->file_path, "%PDF-1.4\nSynthetic library document\n");
        Storage::disk('managed_backups')->put('manual-emergency-preserved.zip', 'Synthetic external backup marker');
    }
}
