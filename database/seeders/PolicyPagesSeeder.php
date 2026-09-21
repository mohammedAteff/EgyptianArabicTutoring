<?php

namespace Database\Seeders;

use App\Services\ContentService;
use Illuminate\Database\Seeder;

class PolicyPagesSeeder extends Seeder
{
    public function run(): void
    {
        $contentService = app(ContentService::class);

        $privacyText = <<<'TEXT'
Privacy Policy
Last Updated: September 2026

This Privacy Policy explains what personal information is collected, how it is handled, and how your privacy is protected.

1. Information Collected
Personal & Contact Details: Your name, email address, and messaging handle or phone number (WhatsApp or Telegram).
Scheduling & Learning Data: Diagnostic evaluations, learning roadmaps, and calendar booking records.
Technical & Geolocation Data: To understand our audience, our first-party analytics system derives an approximate country from your network connection. The application does not retain raw IP addresses in its application database.
Communication Files: Audio voice notes, text queries, and study materials exchanged for instructional review.

2. Payments & Financial Data
Payments are billed manually via PayPal invoice. This website does not process, record, or store credit card numbers, debit card details, or banking credentials. All transactions are subject to PayPal's independent privacy and security policies.

3. Online Meetings & Audio Privacy
Video coaching sessions conducted via Zoom, Google Meet, or Microsoft Teams are private 1-on-1 classes. They are not recorded unless you explicitly request a recording for your personal study. Pronunciation voice notes, custom PDFs, and feedback files are never shared publicly or used for marketing without your prior written permission.

4. How Information Is Used
Your personal data is used to provide the Services, operate the website, manage bookings, maintain security, and produce aggregated first-party audience analytics:
Managing session bookings and schedule adjustments.
Generating aggregated, first-party audience analytics (e.g., total visitors by country) without cross-site tracking or profiling.
Issuing manual PayPal invoices and verifying payments.
Delivering personalized lesson materials and feedback via WhatsApp, Telegram, or email. Personal data is not sold to or shared with third-party advertisers or data brokers; limited data may be processed by the trusted third-party service providers listed in Section 5 strictly as necessary to provide the Services.

5. Third-Party Service Providers
Data is shared only with tools necessary to deliver your coaching:
PayPal: For manual invoicing and payment processing.
Video Platforms (Zoom / Google Meet / Microsoft Teams): For hosting live lessons.
Messaging Platforms (WhatsApp / Telegram): For asynchronous audio feedback and direct communication.

6. Your Rights & Data Retention
Intake notes and learning logs are retained during your studies and for up to 12 months after your last class to allow smooth resumption if you return. Aggregated, pseudonymous analytics records are retained separately for long-term reporting. You may request an export of your materials or request the deletion of your personal contact records at any time by contacting the tutor directly. (Note: This policy provides an operational disclosure of platform practices and should be reviewed by qualified legal counsel.)
TEXT;

        $termsText = <<<'TEXT'
Terms and Conditions
Last Updated: September 2026

These Terms and Conditions govern your booking, payment, and participation in 1-on-1 Egyptian Arabic coaching, diagnostic sessions, and asynchronous feedback ("Services"). By booking a session on this website or paying an invoice, you agree to these terms.

1. Delivery & Platform Logistics
Live Video Sessions: Coaching sessions take place remotely via agreed video conferencing tools (Zoom, Google Meet, or Microsoft Teams). Meeting links are provided prior to each scheduled lesson.
Direct Scheduling: Lesson times and initial bookings are arranged directly through this website's scheduling interface.
Asynchronous Communication: Between-class micro-lessons, pronunciation reviews, and study queries are delivered via WhatsApp or Telegram based on your preference.
Package Validity:
Foundation Coaching Track (8 Sessions / 16 Hours): Valid for 75 calendar days from the date of purchase.
Fluency Immersion Track (12 Sessions / 24 Hours): Valid for 100 calendar days from the date of purchase. Unused sessions expire automatically after the validity window closes.

2. Invoicing, Payments & Credits
Manual Invoicing via PayPal: Pricing published on the website is displayed for reference. All payments are billed manually via PayPal invoice. Lesson slots and package enrollments are confirmed once payment verification is received.
48-Hour Diagnostic Credit: The $25 diagnostic fee is 100% credited toward an 8- or 12-session package if you confirm enrollment and pay the package invoice within 48 hours of completing your diagnostic session. Standard package pricing applies after this 48-hour window.

3. Rescheduling, Cancellations & No-Show Policy
Rescheduling (24+ Hours Notice): You may request to reschedule a session by contacting the tutor directly via WhatsApp, Telegram, or email at least 24 hours prior to the scheduled start time. Where calendar availability permits, adjustments will be accommodated without penalty.
Cancellations (4+ Hours Notice): Cancellations submitted with at least 4 hours' advance notice do not forfeit the session credit. The credit remains redeemable within your package validity period. Cancellations submitted with less than 4 hours' notice are forfeited and deducted from your package total.
15-Minute No-Show Rule: The tutor waits in the meeting room for 15 minutes. If you do not join within the first 15 minutes, the session is forfeited. If you join within the 15-minute window, instruction begins immediately but concludes at the originally scheduled end time.

4. Refund Policy
Diagnostic Sessions ($25): Non-refundable once the session has taken place. Cancellations made with at least 4 hours' notice can be rebooked or refunded, minus any non-recoverable PayPal processing fees.
Package Purchases: Packages are non-refundable once the first instructional session has been delivered. If a package is purchased and cancelled prior to attending the first lesson, a refund will be issued minus applicable non-recoverable PayPal fees.
Unused & Expired Lessons: No partial, pro-rated, or retroactive refunds are provided for lessons left unscheduled after the package expiration date (75 or 100 days).

5. Materials & Usage
Custom study roadmaps, curated documents, and video/audio feedback created for you are for your personal educational use only. You may not distribute, sell, or publicly share these proprietary training materials without prior written consent.
TEXT;

        $contentService->createPage([
            'slug' => 'privacy',
            'en' => [
                'title' => 'Privacy Policy',
                'content' => $privacyText,
            ],
        ]);

        $contentService->createPage([
            'slug' => 'terms',
            'en' => [
                'title' => 'Terms and Conditions',
                'content' => $termsText,
            ],
        ]);
    }
}
