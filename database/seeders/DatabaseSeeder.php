<?php

namespace Database\Seeders;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Actions\SyncDiagnosticSessionType;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Initial Super Administrator (Non-destructive & secret-safe)
        // Never update or overwrite existing administrator credentials during seeding!
        $adminEmail = env('ADMIN_DEFAULT_EMAIL', 'admin@boltlanding.test');
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD');

        if (! Administrator::where('email', $adminEmail)->exists() && Administrator::count() === 0) {
            if (app()->isProduction()) {
                if ($adminPassword) {
                    Administrator::create([
                        'email' => $adminEmail,
                        'name' => 'Tutor Admin',
                        'password' => Hash::make($adminPassword),
                        'role' => 'super_admin',
                    ]);
                } else {
                    $this->command?->info("Production environment: No administrator provisioned. Run 'php artisan admin:create' to provision an initial administrator.");
                }
            } else {
                Administrator::create([
                    'email' => $adminEmail,
                    'name' => 'Tutor Admin',
                    'password' => Hash::make($adminPassword ?: 'Password123!'),
                    'role' => 'super_admin',
                ]);
            }
        }

        // 2. Session Types (Legacy deactivated, Canonical Diagnostic Active)
        SessionType::updateOrCreate(
            ['slug' => 'egyptian-arabic-session'],
            [
                'title' => '1-on-1 Egyptian Arabic Tutoring',
                'description' => 'A personalized, immersive conversational session tailored to your current Arabic level and personal goals.',
                'duration_minutes' => 60,
                'price' => 40.00,
                'currency' => 'USD',
                'active' => false,
            ]
        );

        (new SyncDiagnosticSessionType)->execute();

        // 3. Weekly Availability Rules (Sunday=0 to Thursday=4 in Cairo time)
        // Two daily intervals: 09:00 - 13:00 and 15:00 - 19:00
        $weekdays = [0, 1, 2, 3, 4];
        foreach ($weekdays as $day) {
            AvailabilityRule::updateOrCreate(
                ['weekday' => $day, 'start_time' => '09:00:00', 'end_time' => '13:00:00'],
                [
                    'session_duration_minutes' => 60,
                    'buffer_minutes' => 15,
                    'min_notice_hours' => 12,
                    'max_horizon_days' => 60,
                    'enabled' => true,
                ]
            );

            AvailabilityRule::updateOrCreate(
                ['weekday' => $day, 'start_time' => '15:00:00', 'end_time' => '19:00:00'],
                [
                    'session_duration_minutes' => 60,
                    'buffer_minutes' => 15,
                    'min_notice_hours' => 12,
                    'max_horizon_days' => 60,
                    'enabled' => true,
                ]
            );
        }

        // 4. Default Settings
        $settings = [
            ['key' => 'site_name', 'value' => 'Egyptian Arabic with Abdallah', 'group' => 'general', 'is_public' => true],
            ['key' => 'business_timezone', 'value' => 'Africa/Cairo', 'group' => 'booking', 'is_public' => true],
            ['key' => 'default_language', 'value' => 'en', 'group' => 'general', 'is_public' => true],
            ['key' => 'maintenance_mode', 'value' => '0', 'group' => 'general', 'is_public' => false],
            ['key' => 'active_visitor_window', 'value' => '5', 'group' => 'analytics', 'is_public' => false],
            ['key' => 'session_timeout_minutes', 'value' => '30', 'group' => 'analytics', 'is_public' => false],
            ['key' => 'analytics_retention_days', 'value' => '180', 'group' => 'analytics', 'is_public' => false],
            ['key' => 'analytics_authoritative_cutover_date', 'value' => now('Africa/Cairo')->toDateString(), 'group' => 'analytics', 'is_public' => false],
            ['key' => 'backup_retention_days', 'value' => '30', 'group' => 'system', 'is_public' => false],
            ['key' => 'cancellation_policy', 'value' => 'Cancellations with at least 4 hours notice do not forfeit the session credit. Rescheduling requests must be made directly to Abdallah at least 24 hours before class.', 'group' => 'booking', 'is_public' => true],
            ['key' => 'booking_cancellation_cutoff_hours', 'value' => '4', 'group' => 'booking', 'is_public' => true],
            ['key' => 'booking_reschedule_cutoff_hours', 'value' => '24', 'group' => 'booking', 'is_public' => true],
            ['key' => 'rescheduling_policy', 'value' => 'Rescheduling is free and subject to available tutor calendar slots.', 'group' => 'booking', 'is_public' => true],
            ['key' => 'booking_instructions', 'value' => 'Choose your timezone and select a convenient date and time. An instant confirmation and calendar file will be generated for you.', 'group' => 'booking', 'is_public' => true],
            ['key' => 'hero_title', 'value' => 'Speak Egyptian Arabic with Confidence', 'group' => 'homepage', 'is_public' => true],
            ['key' => 'hero_subtitle', 'value' => 'Master authentic Egyptian street and conversational Arabic through structured, 1-on-1 private lessons with an experienced native speaker.', 'group' => 'homepage', 'is_public' => true],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(
                ['key' => $s['key']],
                [
                    'value' => $s['value'],
                    'group' => $s['group'],
                    'is_public' => $s['is_public'],
                ]
            );
        }

        // 5. Initial Resource Categories
        ResourceCategory::updateOrCreate(
            ['slug' => 'workbooks'],
            ['name' => 'Workbooks & Guides', 'sort_order' => 1, 'active' => true]
        );
        ResourceCategory::updateOrCreate(
            ['slug' => 'cheat-sheets'],
            ['name' => 'Vocabulary & Cheat Sheets', 'sort_order' => 2, 'active' => true]
        );

        // 6. Initial Social Links (Placeholders)
        $socials = [
            ['platform' => 'youtube', 'url_or_phone' => 'https://youtube.com', 'label' => 'YouTube', 'sort_order' => 1],
            ['platform' => 'tiktok', 'url_or_phone' => 'https://tiktok.com', 'label' => 'TikTok', 'sort_order' => 2],
            ['platform' => 'instagram', 'url_or_phone' => 'https://instagram.com', 'label' => 'Instagram', 'sort_order' => 3],
            ['platform' => 'telegram', 'url_or_phone' => 'https://t.me/arabicwithtutor', 'label' => 'Telegram', 'sort_order' => 4],
            [
                'platform' => 'whatsapp',
                'url_or_phone' => '+201000000000',
                'label' => 'WhatsApp',
                'default_message' => 'Hi, I came from your Egyptian Arabic content and would like to ask about a session.',
                'sort_order' => 5,
            ],
        ];
        foreach ($socials as $soc) {
            SocialLink::updateOrCreate(
                ['platform' => $soc['platform']],
                [
                    'url_or_phone' => $soc['url_or_phone'],
                    'label' => $soc['label'],
                    'default_message' => $soc['default_message'] ?? null,
                    'enabled' => true,
                    'sort_order' => $soc['sort_order'],
                ]
            );
        }

        // 7. Initial FAQs
        $faqs = [
            [
                'question' => 'What is Egyptian Arabic and why should I learn it?',
                'answer' => 'Egyptian Arabic (Masri) is the most widely understood dialect across the entire Arab world due to Egypt’s prominent film, music, and media industry. It is the best dialect for daily communication, travel, and building relationships.',
                'sort_order' => 1,
            ],
            [
                'question' => 'Do I need to read Arabic script before taking lessons?',
                'answer' => 'No prior experience is necessary. We can start using phonetics (Franco-Arabic / Latin transliteration) and learn the Arabic alphabet gradually as you progress.',
                'sort_order' => 2,
            ],
            [
                'question' => 'How are the sessions conducted?',
                'answer' => 'Sessions are held online 1-on-1 via video conferencing. You receive customized notes, vocabulary flashcard sets, and speaking exercises after each lesson.',
                'sort_order' => 3,
            ],
            [
                'question' => 'What if I need to reschedule my session?',
                'answer' => 'You can request a reschedule with at least 24 hours notice by contacting Abdallah directly via WhatsApp, Telegram, or email. Changes are subject to available tutor calendar slots.',
                'sort_order' => 4,
            ],
        ];
        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => $faq['sort_order'],
                    'active' => true,
                ]
            );
        }

        // 8. Learning Games
        Game::updateOrCreate(
            ['slug' => '6-word-story'],
            [
                'title' => '6-Word Story',
                'description' => 'Think fast, speak continuously, and practice Egyptian Arabic through quick speaking challenges.',
                'badge' => 'Speaking Practice',
                'thumbnail_path' => 'images/games/6-word-story.webp',
                'target_url' => 'https://mohamedateff.com/6word',
                'status' => 'available',
                'featured' => true,
                'sort_order' => 1,
            ]
        );

        // 9. Policy Pages (Privacy & Terms)
        $this->call(PolicyPagesSeeder::class);
    }
}
