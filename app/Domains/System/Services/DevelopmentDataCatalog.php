<?php

namespace App\Domains\System\Services;

use Illuminate\Validation\ValidationException;

class DevelopmentDataCatalog
{
    public const VERSION = 1;

    public const MODULES = [
        'students.profile' => ['students', 'student_emails'],
        'students.bookings' => ['bookings', 'booking_events', 'session_reschedules', 'booking_policy_decisions', 'migration_booking_exceptions', 'migration_booking_timezone_exceptions', 'recurring_lesson_plans', 'recurring_lesson_occurrences', 'booking_waitlists', 'student_unavailabilities'],
        'students.forms' => ['form_submissions', 'form_answers', 'form_submission_revisions'],
        'students.teaching' => ['student_bins', 'homeworks', 'learning_plans', 'learning_milestones', 'tutor_preparations', 'resource_assignments', 'student_error_logs', 'teaching_tags', 'lesson_feedback', 'staff_tasks', 'student_operational_alerts'],
        'students.materials' => ['lesson_materials'],
        'students.notifications' => ['student_notifications'],
        'financial.packages' => ['student_packages', 'student_package_entitlements', 'entitlement_mapping_reviews', 'package_installments', 'package_renewals'],
        'financial.payments' => ['payment_records', 'payment_refunds'],
        'financial.ledger' => ['session_ledger_entries'],
        'analytics.events' => ['analytics_events'],
        'analytics.visitors' => ['visitors'],
        'analytics.sessions' => ['visitor_sessions'],
        'analytics.acquisition' => ['marketing_touches', 'visitor_funnel_progressions'],
        'analytics.rollups' => ['daily_metrics', 'daily_country_metrics', 'daily_social_metrics', 'analytics_reconciliation_audits'],
        'analytics.maintenance' => ['maintenance_visits'],
        'resources.records' => ['resources', 'resource_translations'],
        'resources.categories' => ['resource_categories', 'category_translations'],
        'resources.history' => ['resource_requests', 'resource_downloads'],
        'resources.revisions' => ['content_revisions', 'entity_translation_revisions'],
        'backups.snapshots' => [],
    ];

    public const LABELS = [
        'students.profile' => 'Student identity / profiles', 'students.bookings' => 'Bookings and planning',
        'students.forms' => 'Form submissions / answers', 'students.teaching' => 'Teaching records / notes / tasks',
        'students.materials' => 'Lesson materials / private files', 'students.notifications' => 'Student notifications',
        'financial.packages' => 'Purchases / allocations / installments / renewals', 'financial.payments' => 'Actual payments / refunds',
        'financial.ledger' => 'Typed ledger / billing history', 'analytics.events' => 'Raw analytics events',
        'analytics.visitors' => 'Visitors', 'analytics.sessions' => 'Visitor sessions', 'analytics.acquisition' => 'Campaign / funnel facts',
        'analytics.rollups' => 'Country / social / daily rollups', 'analytics.maintenance' => 'Maintenance traffic',
        'resources.records' => 'Resources / translations / private files', 'resources.categories' => 'Resource categories',
        'resources.history' => 'Resource requests / downloads', 'backups.snapshots' => 'Selected managed snapshots (sanitized portable copies)',
        'resources.revisions' => 'Resource / category revision history',
    ];

    public const PHRASES = ['analytics' => 'RESET ALL ANALYTICS', 'students' => 'DELETE ALL STUDENTS',
        'financial' => 'RESET FINANCIAL TEST HISTORY', 'resources' => 'RESET RESOURCE TEST DATA', 'backups' => 'RESET MANAGED BACKUPS'];

    /** @return list<string> */
    public function tables(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::MODULES))));
    }

    /** @param list<string> $modules
     * @return list<string> */
    public function selectedTables(array $modules): array
    {
        if ($modules === [] || array_diff($modules, array_keys(self::MODULES)) !== []) {
            throw ValidationException::withMessages(['modules' => 'Select at least one supported module.']);
        }

        return array_values(array_unique(array_merge(...array_map(fn (string $module): array => self::MODULES[$module], $modules))));
    }

    /** @param list<string> $tables
     * @return list<string> */
    public function modulesFor(array $tables): array
    {
        return array_keys(array_filter(self::MODULES, fn (array $members): bool => array_intersect($tables, $members) !== []));
    }
}
