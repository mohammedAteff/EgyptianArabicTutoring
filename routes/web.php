<?php

use App\Http\Controllers\Admin\AccountSuspensionController;
use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\AnalyticsDashboardController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MeetingLinkController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResourceCategoryController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SessionTypeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffBinController;
use App\Http\Controllers\Admin\StudentBillingController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\TelegramController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use App\Http\Controllers\Admin\TwoFactorSecurityController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\Student\AuthController as StudentAuthController;
use App\Http\Controllers\Student\BookingController as StudentBookingController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\FormController as StudentFormController;
use App\Http\Controllers\Student\LessonWorkspaceController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\RescheduleController as StudentRescheduleController;
use App\Http\Controllers\StudentBinController;
use App\Http\Middleware\ApplyAdminNoindexHeaders;
use App\Http\Middleware\EnsureAdminPreviewAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/fr', [HomeController::class, 'index'])->name('home.fr');
Route::get('/de', [HomeController::class, 'index'])->name('home.de');

// LiteSpeed subdirectory landing target (used only by the Hostinger wrapper).
Route::get('/_arabictutor-landing', [HomeController::class, 'index'])->name('home.internal');

// Native Booking System
Route::prefix('student')->name('student.')->middleware(ApplyAdminNoindexHeaders::class)->group(function () {
    Route::get('/login', [StudentAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [StudentAuthController::class, 'login'])->middleware('throttle:student-verification')->name('login.submit');
    Route::middleware('student.auth')->group(function () {
        Route::get('/notes', [StudentBinController::class, 'index'])->name('bins.index');
        Route::post('/notes', [StudentBinController::class, 'store'])->name('bins.store');
        Route::get('/notes/{bin}/edit', [StudentBinController::class, 'edit'])->name('bins.edit');
        Route::put('/notes/{bin}', [StudentBinController::class, 'update'])->name('bins.update');

        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::post('/profile/emails', [ProfileController::class, 'requestVerification'])->middleware('throttle:6,1')->name('profile.email.request');
        Route::get('/profile/emails/verify/{token}', [ProfileController::class, 'verify'])->middleware('throttle:10,1')->name('profile.email.verify');
        Route::get('/', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/lessons/{booking}', [LessonWorkspaceController::class, 'show'])->whereNumber('booking')->name('lessons.show');
        Route::get('/lessons/{booking}/materials/{material}', [LessonWorkspaceController::class, 'open'])->whereNumber(['booking', 'material'])->name('lessons.materials.open');
        Route::get('/forms/{slug}', [StudentFormController::class, 'show'])->name('forms.show');
        Route::post('/forms/{slug}', [StudentFormController::class, 'save'])->middleware('throttle:student-form-save')->name('forms.save');
        Route::post('/forms/{slug}/autosave', [StudentFormController::class, 'autosave'])->middleware('throttle:student-form-save')->name('forms.autosave');
        Route::get('/bookings/create', [StudentBookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [StudentBookingController::class, 'store'])->middleware('throttle:student-booking-finalize')->name('bookings.store');
        Route::get('/bookings/{booking}/reschedule', [StudentRescheduleController::class, 'show'])->name('bookings.reschedule');
        Route::post('/bookings/{booking}/reschedule', [StudentRescheduleController::class, 'update'])->middleware('throttle:booking-reschedule')->name('bookings.reschedule.submit');
        Route::post('/logout', [StudentAuthController::class, 'logout'])->name('logout');
    });
});

Route::get('/booking', [BookingController::class, 'index'])->name('booking.index');
Route::get('/book', function (Request $request) {
    $queryString = $request->server->get('QUERY_STRING') ?: $request->getQueryString();

    return redirect($queryString ? '/booking?'.$queryString : '/booking', 301);
});
Route::get('/fr/reservation', [BookingController::class, 'index'])->name('booking.fr');
Route::get('/de/buchen', [BookingController::class, 'index'])->name('booking.de');

Route::get('/booking/confirmation/{token}', [BookingController::class, 'confirmation'])->name('booking.confirmation');
Route::get('/book/confirmation/{token}', function (string $token, Request $request) {
    $qs = $request->server->get('QUERY_STRING') ?: $request->getQueryString();

    return redirect('/booking/confirmation/'.$token.($qs ? '?'.$qs : ''), 301);
});
Route::get('/fr/reservation/confirmation/{token}', [BookingController::class, 'confirmation'])->name('booking.confirmation.fr');
Route::get('/de/buchen/bestaetigung/{token}', [BookingController::class, 'confirmation'])->name('booking.confirmation.de');

Route::get('/booking/confirmation/{token}/ics', [BookingController::class, 'ics'])->name('booking.ics');
Route::get('/book/confirmation/{token}/ics', function (string $token, Request $request) {
    $qs = $request->getQueryString();

    return redirect('/booking/confirmation/'.$token.'/ics'.($qs ? '?'.$qs : ''), 301);
});

Route::get('/booking/{token}/reschedule', [BookingController::class, 'showReschedule'])->name('booking.reschedule');
Route::get('/book/{token}/reschedule', function (string $token, Request $request) {
    $qs = $request->getQueryString();

    return redirect('/booking/'.$token.'/reschedule'.($qs ? '?'.$qs : ''), 301);
});
Route::post('/booking/{token}/reschedule', [BookingController::class, 'processReschedule'])->middleware('throttle:booking-reschedule')->name('booking.reschedule.submit');
Route::post('/book/{token}/reschedule', [BookingController::class, 'processReschedule'])->middleware('throttle:booking-reschedule');

Route::post('/booking/{token}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:booking-cancel')->name('booking.cancel');
Route::post('/book/{token}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:booking-cancel');

// Pricing & Coaching Tracks
Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
Route::get('/fr/tarifs', [PricingController::class, 'index'])->name('pricing.fr');
Route::get('/de/preise', [PricingController::class, 'index'])->name('pricing.de');

// Resource Library & Gated Access
Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
Route::get('/fr/ressources', [ResourceController::class, 'index'])->name('resources.fr');
Route::get('/de/ressourcen', [ResourceController::class, 'index'])->name('resources.de');

Route::get('/resources/{slug}', [ResourceController::class, 'show'])->name('resources.show');
Route::get('/fr/ressources/{slug}', [ResourceController::class, 'show'])->name('resources.show.fr');
Route::get('/de/ressourcen/{slug}', [ResourceController::class, 'show'])->name('resources.show.de');

Route::post('/resources/{slug}/request', [ResourceController::class, 'requestAccess'])->middleware('throttle:resource-request')->name('resources.request');
Route::post('/fr/ressources/{slug}/request', [ResourceController::class, 'requestAccess'])->middleware('throttle:resource-request')->name('resources.request.fr');
Route::post('/de/ressourcen/{slug}/request', [ResourceController::class, 'requestAccess'])->middleware('throttle:resource-request')->name('resources.request.de');

Route::post('/resources/{slug}/verify-pin', [ResourceController::class, 'verifyPin'])->middleware('throttle:resource-request')->name('resources.verify-pin');
Route::post('/fr/ressources/{slug}/verify-pin', [ResourceController::class, 'verifyPin'])->middleware('throttle:resource-request')->name('resources.verify-pin.fr');
Route::post('/de/ressourcen/{slug}/verify-pin', [ResourceController::class, 'verifyPin'])->middleware('throttle:resource-request')->name('resources.verify-pin.de');

Route::get('/resources/{slug}/download', [ResourceController::class, 'download'])->name('resources.download');
Route::get('/fr/ressources/{slug}/download', [ResourceController::class, 'download'])->name('resources.download.fr');
Route::get('/de/ressourcen/{slug}/download', [ResourceController::class, 'download'])->name('resources.download.de');

// Games
Route::get('/games', [GameController::class, 'index'])->name('games.index');
Route::get('/fr/jeux', [GameController::class, 'index'])->name('games.fr');
Route::get('/de/spiele', [GameController::class, 'index'])->name('games.de');

Route::get('/games/{slug}', [GameController::class, 'show'])->name('games.show');
Route::get('/fr/jeux/{slug}', [GameController::class, 'show'])->name('games.show.fr');
Route::get('/de/spiele/{slug}', [GameController::class, 'show'])->name('games.show.de');

Route::post('/games/{slug}/track', [GameController::class, 'track'])->middleware('throttle:game-track')->name('games.track');

// Static & CMS Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/fr/a-propos', [PageController::class, 'about'])->name('about.fr');
Route::get('/de/ueber-uns', [PageController::class, 'about'])->name('about.de');

Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/fr/faq', [PageController::class, 'faq'])->name('faq.fr');
Route::get('/de/faq', [PageController::class, 'faq'])->name('faq.de');

Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/fr/conditions', [PageController::class, 'terms'])->name('terms.fr');
Route::get('/de/agb', [PageController::class, 'terms'])->name('terms.de');

Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/fr/confidentialite', [PageController::class, 'privacy'])->name('privacy.fr');
Route::get('/de/datenschutz', [PageController::class, 'privacy'])->name('privacy.de');

Route::get('/p/{slug}', [PageController::class, 'show'])->name('page.show');
Route::get('/fr/p/{slug}', [PageController::class, 'show'])->name('page.show.fr');
Route::get('/de/p/{slug}', [PageController::class, 'show'])->name('page.show.de');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[A-Za-z0-9-]+')->name('blog.show');

// Authenticated admin draft previews (Section 49 & Section 33)
Route::middleware([ApplyAdminNoindexHeaders::class, EnsureAdminPreviewAccess::class])->group(function () {
    Route::get('/preview/home', [HomeController::class, 'preview'])->name('home.preview');
    Route::get('/about/preview', [PageController::class, 'previewAbout'])->name('about.preview');
    Route::get('/p/{slug}/preview', [PageController::class, 'preview'])->name('pages.preview');
    Route::get('/resources/{slug}/preview', [ResourceController::class, 'preview'])->name('resources.preview');
    Route::get('/games/{slug}/preview', [GameController::class, 'preview'])->name('games.preview');
    Route::get('/faq/preview', [PageController::class, 'previewFaq'])->name('faq.preview');
});

// First-Party Client Analytics Ingestion
Route::post('/analytics/event', [AnalyticsController::class, 'track'])->middleware('throttle:60,1')->name('analytics.track');
Route::post('/api/analytics/events', [AnalyticsController::class, 'track'])->middleware('throttle:60,1')->name('analytics.events');

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(ApplyAdminNoindexHeaders::class)->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:staff-two-factor-challenge')->name('two-factor.verify');

    // Password Recovery
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:password-reset-request')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:password-reset-attempt')->name('password.update');

    /*
    |--------------------------------------------------------------------------
    | Authenticated Admin Operations
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:web', 'account.active'])->group(function () {
        Route::prefix('security')->name('security.')->middleware('role:super_admin')->group(function (): void {
            Route::get('/', [TwoFactorSecurityController::class, 'show'])->name('show');
            Route::middleware('throttle:staff-two-factor-security')->group(function (): void {
                Route::post('/enrollment', [TwoFactorSecurityController::class, 'store'])->name('enroll');
                Route::post('/confirmation', [TwoFactorSecurityController::class, 'confirm'])->name('confirm');
                Route::post('/recovery-codes', [TwoFactorSecurityController::class, 'regenerate'])->name('regenerate');
                Route::post('/authenticator', [TwoFactorSecurityController::class, 'reset'])->name('reset');
                Route::delete('/two-factor', [TwoFactorSecurityController::class, 'destroy'])->name('disable');
            });
        });
        Route::post('/students/{student}/packages/{package}/validity', [StudentBillingController::class, 'extendValidity'])->middleware('role:super_admin,admin')->name('students.packages.validity');
        Route::post('/accounts/{type}/{account}/suspension', [AccountSuspensionController::class, 'update'])->middleware('role:super_admin')->name('accounts.suspension');
        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->middleware('role:super_admin,admin')->name('payment-methods.index');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->middleware('role:super_admin,admin')->name('payment-methods.store');
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->middleware('role:super_admin,admin')->name('payment-methods.update');
        Route::middleware('role:super_admin,admin')->group(function (): void {
            Route::post('/meeting-links/reconcile', [MeetingLinkController::class, 'reconcile'])->name('meeting-links.reconcile');
            Route::get('/meeting-links', [MeetingLinkController::class, 'index'])->name('meeting-links.index');
            Route::post('/meeting-links/providers', [MeetingLinkController::class, 'provider'])->name('meeting-links.providers');
            Route::post('/meeting-links/rooms', [MeetingLinkController::class, 'room'])->name('meeting-links.rooms');
            Route::post('/meeting-links/settings', [MeetingLinkController::class, 'settings'])->name('meeting-links.settings');
            Route::post('/bookings/{booking}/meeting-room', [MeetingLinkController::class, 'assign'])->name('meeting-links.assign');
            Route::post('/students/{student}/meeting-preference', [MeetingLinkController::class, 'preference'])->name('students.meeting-preference');
        });
        Route::post('/preferences/time', [SettingController::class, 'timePreference'])->middleware('role:super_admin,admin,assistant')->name('preferences.time');
        Route::post('/settings/operations', [SettingController::class, 'operational'])->middleware('role:super_admin,admin')->name('settings.operations');
        Route::get('/', [DashboardController::class, 'index'])->middleware('role:super_admin,admin')->name('dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:super_admin,admin');

        // Bookings
        Route::get('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [App\Http\Controllers\Admin\BookingController::class, 'create'])->middleware('role:super_admin,admin')->name('bookings.create');
        Route::post('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'store'])->middleware('role:super_admin,admin')->name('bookings.store');
        Route::get('/bookings/{booking}', [App\Http\Controllers\Admin\BookingController::class, 'show'])->name('bookings.show');
        Route::get('/bookings/{booking}/lesson', [App\Http\Controllers\Admin\LessonWorkspaceController::class, 'show'])->name('lessons.show');
        Route::post('/bookings/{booking}/lesson/materials', [App\Http\Controllers\Admin\LessonWorkspaceController::class, 'store'])->name('lessons.materials.store');
        Route::patch('/bookings/{booking}/lesson/materials/{material}', [App\Http\Controllers\Admin\LessonWorkspaceController::class, 'update'])->name('lessons.materials.update');
        Route::delete('/bookings/{booking}/lesson/materials/{material}', [App\Http\Controllers\Admin\LessonWorkspaceController::class, 'destroy'])->name('lessons.materials.destroy');
        Route::get('/bookings/{booking}/lesson/materials/{material}', [App\Http\Controllers\Admin\LessonWorkspaceController::class, 'open'])->name('lessons.materials.open');
        Route::patch('/bookings/{booking}/notes', [App\Http\Controllers\Admin\BookingController::class, 'updateNotes'])->middleware('role:super_admin,admin')->name('bookings.notes');
        Route::post('/bookings/{booking}/complete', [App\Http\Controllers\Admin\BookingController::class, 'complete'])->middleware('role:super_admin,admin')->name('bookings.complete');
        Route::post('/bookings/{booking}/no-show', [App\Http\Controllers\Admin\BookingController::class, 'markNoShow'])->middleware('role:super_admin,admin')->name('bookings.no-show');
        Route::post('/bookings/{booking}/reschedule', [App\Http\Controllers\Admin\BookingController::class, 'reschedule'])->middleware('role:super_admin,admin')->name('bookings.reschedule');
        Route::post('/bookings/{booking}/cancel', [App\Http\Controllers\Admin\BookingController::class, 'cancel'])->middleware('role:super_admin,admin')->name('bookings.cancel');

        // Availability
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
            Route::post('/availability/rules', [AvailabilityController::class, 'storeRule'])->name('availability.rules.store');
            Route::post('/availability/rules/{rule}/toggle', [AvailabilityController::class, 'toggleRule'])->name('availability.toggle');
            Route::delete('/availability/rules/{rule}', [AvailabilityController::class, 'destroyRule'])->name('availability.destroy');
            Route::post('/availability/exceptions', [AvailabilityController::class, 'storeException'])->name('availability.exceptions.store');
            Route::delete('/availability/exceptions/{exception}', [AvailabilityController::class, 'destroyException'])->name('availability.exception.destroy');
        });

        // Contacts & Leads
        Route::get('/contacts/export', [ContactController::class, 'export'])->middleware('role:super_admin,admin')->name('contacts.export');
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/leads', [ContactController::class, 'leads'])->name('leads');
        Route::get('/contacts/duplicates', [ContactController::class, 'duplicates'])->name('contacts.duplicates');
        Route::post('/contacts/merge', [ContactController::class, 'merge'])->middleware('role:super_admin,admin')->name('contacts.merge');
        Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
        Route::patch('/contacts/{contact}/notes', [ContactController::class, 'updateNotes'])->middleware('role:super_admin,admin')->name('contacts.notes');

        // Resources & Categories
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::resource('resource-categories', ResourceCategoryController::class)->except(['show']);
            Route::get('/resources', [App\Http\Controllers\Admin\ResourceController::class, 'index'])->name('resources.index');
            Route::get('/resources/create', [App\Http\Controllers\Admin\ResourceController::class, 'create'])->name('resources.create');
            Route::post('/resources', [App\Http\Controllers\Admin\ResourceController::class, 'store'])->name('resources.store');
            Route::get('/resources/{resource}/edit', [App\Http\Controllers\Admin\ResourceController::class, 'edit'])->name('resources.edit');
            Route::put('/resources/{resource}', [App\Http\Controllers\Admin\ResourceController::class, 'update'])->name('resources.update');
            Route::delete('/resources/{resource}', [App\Http\Controllers\Admin\ResourceController::class, 'destroy'])->name('resources.destroy');
            Route::delete('/resources/{resource}/draft', [App\Http\Controllers\Admin\ResourceController::class, 'discardDraft'])->name('resources.draft.destroy');
        });

        // Games Lifecycle Management
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/games', [App\Http\Controllers\Admin\GameController::class, 'index'])->name('games.index');
            Route::get('/games/create', [App\Http\Controllers\Admin\GameController::class, 'create'])->name('games.create');
            Route::post('/games', [App\Http\Controllers\Admin\GameController::class, 'store'])->name('games.store');
            Route::get('/games/{game}/edit', [App\Http\Controllers\Admin\GameController::class, 'edit'])->name('games.edit');
            Route::put('/games/{game}', [App\Http\Controllers\Admin\GameController::class, 'update'])->name('games.update');
            Route::delete('/games/{game}', [App\Http\Controllers\Admin\GameController::class, 'destroy'])->name('games.destroy');
            Route::delete('/games/{game}/draft', [App\Http\Controllers\Admin\GameController::class, 'discardDraft'])->name('games.draft.destroy');
        });

        // Internal Notifications Center
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
            Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        });

        // Content & FAQs
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/content', [ContentController::class, 'index'])->name('content.index');
            Route::post('/content/faqs', [ContentController::class, 'storeFaq'])->name('content.faq.store');
            Route::put('/content/faqs/{faq}', [ContentController::class, 'updateFaq'])->name('content.faq.update');
            Route::delete('/content/faqs/{faq}', [ContentController::class, 'destroyFaq'])->name('content.faq.destroy');
            Route::delete('/content/faqs/{faq}/draft', [ContentController::class, 'discardFaqDraft'])->name('content.faq.draft.destroy');
            Route::post('/content/social', [ContentController::class, 'updateSocial'])->name('content.social.update');
            Route::post('/content/social/add', [ContentController::class, 'storeSocial'])->name('content.social.store');
            Route::post('/content/social/{socialLink}/toggle', [ContentController::class, 'toggleSocial'])->name('content.social.toggle');
        });

        // CMS Custom Pages & Revisions
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::resource('pages', App\Http\Controllers\Admin\PageController::class)->except(['show']);
            Route::delete('/pages/{page}/draft', [App\Http\Controllers\Admin\PageController::class, 'discardDraft'])->name('pages.draft.destroy');
            Route::post('/pages/{page}/revisions/{revision}/restore', [App\Http\Controllers\Admin\PageController::class, 'restoreRevision'])->name('pages.revisions.restore');
        });

        // Translations & Multilingual CMS Revisions
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::post('/translations/{entityType}/{id}/{locale}/draft', [TranslationController::class, 'saveDraft'])->name('translations.save-draft');
            Route::post('/translations/{entityType}/{id}/{locale}/publish', [TranslationController::class, 'publish'])->name('translations.publish');
            Route::get('/translations/{entityType}/{id}/{locale}/reconcile', [TranslationController::class, 'reconcile'])->name('translations.reconcile');
        });

        Route::middleware('role:super_admin,admin')->group(function () {
            // Billing & Cashier Operations Hub (Phase 3)
            Route::get('/session-types', [SessionTypeController::class, 'index'])->name('session-types.index');
            Route::post('/session-types/{sessionType?}', [SessionTypeController::class, 'save'])->name('session-types.save');
            Route::get('/billing/cashier', [StudentBillingController::class, 'cashier'])->name('billing.cashier');
            Route::get('/billing/check-diagnostic-credit/{student}', [StudentBillingController::class, 'checkDiagnosticCredit'])->name('billing.check_diagnostic');
            Route::get('/billing/export', [StudentBillingController::class, 'exportFinancials'])->name('billing.export');
            Route::get('/billing/reconcile', [StudentBillingController::class, 'reconcile'])->name('billing.reconcile');
            Route::get('/billing/reconcile/export', [StudentBillingController::class, 'exportReconciliation'])->name('billing.reconcile.export');

            Route::patch('/students/{student}', [StudentController::class, 'update'])->name('students.update');
            Route::post('/students/{student}/packages', [StudentBillingController::class, 'storePackage'])->name('students.packages.store');
            Route::post('/students/{student}/packages/{package}/payments', [StudentBillingController::class, 'storePayment'])->name('students.payments.store');
            Route::post('/students/{student}/payments/{payment}/refunds', [StudentBillingController::class, 'storeRefund'])->name('students.refunds.store');
            Route::post('/students/{student}/packages/{package}/credits', [StudentBillingController::class, 'adjustCredits'])->name('students.credits.adjust');

            // Promotions Management Portal
            Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
            Route::get('/promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
            Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
            Route::get('/promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
            Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
            Route::post('/promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
            Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
            Route::get('/promotions/{promotion}/preview', [PromotionController::class, 'preview'])->middleware('admin.preview')->name('promotions.preview');

            Route::prefix('blog')->name('blog.')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\BlogController::class, 'index'])->name('index');
                Route::get('/create', [App\Http\Controllers\Admin\BlogController::class, 'create'])->name('create');
                Route::post('/', [App\Http\Controllers\Admin\BlogController::class, 'store'])->name('store');
                Route::get('/{blog}/edit', [App\Http\Controllers\Admin\BlogController::class, 'edit'])->name('edit');
                Route::put('/{blog}', [App\Http\Controllers\Admin\BlogController::class, 'update'])->name('update');
                Route::delete('/{blog}', [App\Http\Controllers\Admin\BlogController::class, 'destroy'])->name('destroy');
                Route::get('/{blog}/preview', [App\Http\Controllers\Admin\BlogController::class, 'preview'])->name('preview');
            });
            Route::get('/forms/create', [FormController::class, 'create'])->name('forms.create');
            Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
            Route::get('/forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
            Route::put('/forms/{form}', [FormController::class, 'update'])->name('forms.update');
            Route::put('/forms/{form}/publish', [FormController::class, 'publish'])->name('forms.publish');
            Route::post('/forms/{form}/archive', [FormController::class, 'archive'])->name('forms.archive');
        });

        Route::middleware('role:super_admin,admin,assistant')->group(function () {
            Route::get('/students/export', [StudentController::class, 'export'])->middleware('role:super_admin,admin')->name('students.export');
            Route::get('/students', [StudentController::class, 'index'])->name('students.index');
            Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
            Route::get('/staff-notes', [StaffBinController::class, 'index'])->name('staff-bins.index');
            Route::post('/staff-notes', [StaffBinController::class, 'store'])->name('staff-bins.store');
            Route::get('/staff-notes/{bin}/edit', [StaffBinController::class, 'edit'])->name('staff-bins.edit');
            Route::put('/staff-notes/{bin}', [StaffBinController::class, 'update'])->name('staff-bins.update');
            Route::delete('/staff-notes/{bin}', [StaffBinController::class, 'destroy'])->name('staff-bins.destroy');
            Route::get('/students/{student}/notes', [StudentBinController::class, 'index'])->name('student-bins.index');
            Route::post('/students/{student}/notes', [StudentBinController::class, 'store'])->name('student-bins.store');
            Route::get('/students/{student}/notes/{bin}/edit', [StudentBinController::class, 'edit'])->name('student-bins.edit');
            Route::put('/students/{student}/notes/{bin}', [StudentBinController::class, 'update'])->name('student-bins.update');
            Route::delete('/students/{student}/notes/{bin}', [StudentBinController::class, 'destroy'])->name('student-bins.destroy');

            Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
            Route::get('/forms/{form}/submissions', [FormController::class, 'submissions'])->name('forms.submissions');
            Route::get('/forms/{form}/export', [FormController::class, 'export'])->name('forms.export');
        });

        // Media Library
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/media', [MediaController::class, 'index'])->name('media.index');
            Route::get('/media/picker', [MediaController::class, 'picker'])->name('media.picker');
            Route::post('/media', [MediaController::class, 'store'])->name('media.store');
            Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        });

        // Global Search
        Route::get('/search', [SearchController::class, 'search'])->middleware('role:super_admin,admin')->name('search');

        // Analytics & Business Funnels
        Route::get('/analytics', [AnalyticsDashboardController::class, 'index'])->middleware('admin.role:super_admin,admin')->name('analytics');
        Route::get('/analytics/overview/export', [AnalyticsDashboardController::class, 'exportOverview'])->middleware('admin.role:super_admin,admin')->name('analytics.overview.export');
        Route::get('/analytics/countries', [AnalyticsDashboardController::class, 'countries'])->middleware('admin.role:super_admin,admin')->name('analytics.countries');
        Route::get('/analytics/countries/export', [AnalyticsDashboardController::class, 'exportCountries'])->middleware('admin.role:super_admin,admin')->name('analytics.countries.export');
        Route::get('/analytics/sections', [AnalyticsDashboardController::class, 'sections'])->middleware('admin.role:super_admin,admin')->name('analytics.sections');
        Route::get('/analytics/sections/export', [AnalyticsDashboardController::class, 'exportSections'])->middleware('admin.role:super_admin,admin')->name('analytics.sections.export');

        // Reports & Data Exports
        Route::get('/reports', [ReportController::class, 'index'])->middleware('role:super_admin,admin')->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->middleware('role:super_admin,admin')->name('reports.export');

        // Everyday operational settings are available to both administrator roles.
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::prefix('telegram')->name('telegram.')->group(function (): void {
                Route::get('/', [TelegramController::class, 'index'])->name('index');
                Route::post('/global', [TelegramController::class, 'global'])->name('global');
                Route::post('/bots/{bot?}', [TelegramController::class, 'bot'])->middleware('throttle:30,1')->name('bot');
                Route::post('/destinations/{destination?}', [TelegramController::class, 'destination'])->name('destination');
                Route::post('/rules/{rule?}', [TelegramController::class, 'rule'])->name('rule');
                Route::post('/run/{rule}', [TelegramController::class, 'run'])->middleware('throttle:5,1')->name('run');
                Route::post('/preview', [TelegramController::class, 'preview'])->middleware('throttle:30,1')->name('preview');
                Route::post('/test/{destination}', [TelegramController::class, 'test'])->middleware('throttle:5,1')->name('test');
            });
            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
            Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/telegram/test', [SettingController::class, 'testTelegram'])->name('settings.telegram.test');
        });

        // Super Admin Only Privileges
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/students/{student}/merge', [StudentController::class, 'merge'])->name('students.merge');
            Route::post('/students/{student}/anonymize', [StudentController::class, 'anonymize'])->name('students.anonymize');

            // System, Backups & Audit
            Route::get('/health', [SystemHealthController::class, 'index'])->name('health');
            Route::get('/health/maintenance-visitors/export', [SystemHealthController::class, 'exportMaintenanceTraffic'])->name('health.maintenance-visitors.export');
            Route::get('/audit-logs', [SystemHealthController::class, 'auditLogs'])->name('audit-logs');
            Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
            Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
            Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');

            // Staff & Administrator Management
            Route::resource('administrators', AdministratorController::class)->except(['show']);
        });
    });
});
