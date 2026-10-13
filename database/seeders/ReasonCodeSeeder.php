<?php

namespace Database\Seeders;

use App\Domains\Financial\Models\ReasonCode;
use Illuminate\Database\Seeder;

class ReasonCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            // Refunds
            ['category' => 'refund', 'code' => 'student_request', 'label' => 'Student Request', 'sort_order' => 1],
            ['category' => 'refund', 'code' => 'tutor_cancellation', 'label' => 'Tutor Cancellation', 'sort_order' => 2],
            ['category' => 'refund', 'code' => 'duplicate_payment', 'label' => 'Duplicate Payment', 'sort_order' => 3],
            ['category' => 'refund', 'code' => 'pricing_correction', 'label' => 'Pricing Correction', 'sort_order' => 4],
            ['category' => 'refund', 'code' => 'other', 'label' => 'Other / Special Exception', 'sort_order' => 99],

            // Courtesy Credits
            ['category' => 'courtesy_credit', 'code' => 'service_recovery', 'label' => 'Service Recovery', 'sort_order' => 1],
            ['category' => 'courtesy_credit', 'code' => 'promotion', 'label' => 'Promotional Offer', 'sort_order' => 2],
            ['category' => 'courtesy_credit', 'code' => 'tutor_cancellation_compensation', 'label' => 'Tutor Cancellation Compensation', 'sort_order' => 3],
            ['category' => 'courtesy_credit', 'code' => 'administrative_correction', 'label' => 'Administrative Correction', 'sort_order' => 4],
            ['category' => 'courtesy_credit', 'code' => 'other', 'label' => 'Other', 'sort_order' => 99],

            // Validity Extensions
            ['category' => 'validity_extension', 'code' => 'travel', 'label' => 'Student Travel / Vacation', 'sort_order' => 1],
            ['category' => 'validity_extension', 'code' => 'medical_personal_break', 'label' => 'Medical or Personal Break', 'sort_order' => 2],
            ['category' => 'validity_extension', 'code' => 'tutor_availability', 'label' => 'Tutor Availability / Scheduling', 'sort_order' => 3],
            ['category' => 'validity_extension', 'code' => 'service_recovery', 'label' => 'Service Recovery', 'sort_order' => 4],
            ['category' => 'validity_extension', 'code' => 'administrative', 'label' => 'Administrative Extension', 'sort_order' => 5],
            ['category' => 'validity_extension', 'code' => 'other', 'label' => 'Other', 'sort_order' => 99],

            // Account Suspensions
            ['category' => 'suspension', 'code' => 'payment_overdue', 'label' => 'Payment Overdue', 'sort_order' => 1],
            ['category' => 'suspension', 'code' => 'conduct_issue', 'label' => 'Conduct Issue', 'sort_order' => 2],
            ['category' => 'suspension', 'code' => 'temporary_administrative_hold', 'label' => 'Temporary Administrative Hold', 'sort_order' => 3],
            ['category' => 'suspension', 'code' => 'requested_by_student', 'label' => 'Requested by Student', 'sort_order' => 4],
            ['category' => 'suspension', 'code' => 'other', 'label' => 'Other', 'sort_order' => 99],

            // Operational Status
            ['category' => 'operational_status', 'code' => 'completed_track', 'label' => 'Completed Study Track', 'sort_order' => 1],
            ['category' => 'operational_status', 'code' => 'travel_break', 'label' => 'Extended Travel Break', 'sort_order' => 2],
            ['category' => 'operational_status', 'code' => 'inactive_period', 'label' => 'Prolonged Inactivity', 'sort_order' => 3],
            ['category' => 'operational_status', 'code' => 'financial_hold', 'label' => 'Financial Hold', 'sort_order' => 4],
            ['category' => 'operational_status', 'code' => 'other', 'label' => 'Other', 'sort_order' => 99],
        ];

        foreach ($reasons as $data) {
            ReasonCode::updateOrCreate(
                ['code' => $data['code'], 'category' => $data['category']],
                [
                    'label' => $data['label'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
