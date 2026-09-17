@extends('layouts.public')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="mb-12">
        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full">
            Privacy First Architecture
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-900 mt-3 tracking-tight">
            Privacy Policy & Data Ethics
        </h1>
        <p class="text-stone-500 text-sm mt-1">Last updated: September 2026</p>
    </div>

    <div class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 shadow-sm space-y-8 text-stone-700 leading-relaxed text-sm sm:text-base">
        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">1. No Third-Party Tracking Scripts</h2>
            <p>
                We believe deeply in visitor privacy. This website contains <strong>zero third-party tracking scripts</strong>. We do not use Google Analytics, Meta (Facebook) Pixels, TikTok pixels, or advertising data brokers. Your browsing activity on this site is never sold, shared, or transmitted to third-party ad networks.
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">2. Self-Hosted First-Party Analytics</h2>
            <p>
                To understand which lessons, workbooks, and games are helpful, our server records minimal first-party usage events directly in our own local database. These records are stored on our own server and retained for a maximum of 180 days before being automatically purged by our daily maintenance tasks.
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">3. Information We Collect</h2>
            <ul class="list-disc list-inside space-y-1 mt-2 text-stone-600">
                <li><strong>Booking details:</strong> Name, email address, optional phone/WhatsApp number, and learning notes provided during booking.</li>
                <li><strong>Resource requests:</strong> Email address provided to access free PDF workbooks and study guides.</li>
                <li><strong>Session technical cookies:</strong> Essential session cookies used strictly to maintain your booking cart and remember your timezone preference.</li>
            </ul>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">4. How Your Information Is Used</h2>
            <p>
                Your contact details are used exclusively to:
            </p>
            <ul class="list-disc list-inside space-y-1 mt-2 text-stone-600">
                <li>Send calendar invitations and direct video lesson links.</li>
                <li>Communicate directly regarding lesson rescheduling or preparation.</li>
                <li>Grant instant access to requested study guides and workbooks.</li>
            </ul>
            <p class="mt-2">
                We will never send you unsolicited marketing blasts or sell your contact information.
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">5. Data Access & Deletion Requests</h2>
            <p>
                You have the right at any time to request a copy of the contact data we hold for you or to request complete deletion of your records from our system. Simply reach out directly to Ahmad or email us.
            </p>
        </div>
    </div>
</div>
@endsection
