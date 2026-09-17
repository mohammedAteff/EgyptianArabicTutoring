<?php

use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\AnalyticsDashboardController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResourceCategoryController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');

// Native Booking System
Route::get('/book', [BookingController::class, 'index'])->name('booking.index');
Route::get('/book/confirmation/{token}', [BookingController::class, 'confirmation'])->name('booking.confirmation');
Route::get('/book/confirmation/{token}/ics', [BookingController::class, 'ics'])->name('booking.ics');
Route::get('/book/{token}/reschedule', [BookingController::class, 'showReschedule'])->name('booking.reschedule');
Route::post('/book/{token}/reschedule', [BookingController::class, 'processReschedule'])->middleware('throttle:booking-reschedule')->name('booking.reschedule.submit');
Route::post('/book/{token}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:booking-cancel')->name('booking.cancel');

// Resource Library & Gated Access
Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
Route::get('/resources/{slug}', [ResourceController::class, 'show'])->name('resources.show');
Route::post('/resources/{slug}/request', [ResourceController::class, 'requestAccess'])->middleware('throttle:resource-request')->name('resources.request');
Route::get('/resources/{slug}/download', [ResourceController::class, 'download'])->name('resources.download');

// Games
Route::get('/games', [GameController::class, 'index'])->name('games.index');
Route::get('/games/{slug}', [GameController::class, 'show'])->name('games.show');
Route::post('/games/{slug}/track', [GameController::class, 'track'])->middleware('throttle:game-track')->name('games.track');

// Static & CMS Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/p/{slug}', [PageController::class, 'show'])->name('page.show');

// Authenticated / Signed Draft Preview Routes (Section 49)
Route::get('/preview/home', [HomeController::class, 'preview'])->name('home.preview');
Route::get('/about/preview', [PageController::class, 'previewAbout'])->name('about.preview');
Route::get('/p/{slug}/preview', [PageController::class, 'preview'])->name('pages.preview');
Route::get('/resources/{slug}/preview', [ResourceController::class, 'preview'])->name('resources.preview');
Route::get('/games/{slug}/preview', [GameController::class, 'preview'])->name('games.preview');

// First-Party Client Analytics Ingestion
Route::post('/analytics/event', [AnalyticsController::class, 'track'])->middleware('throttle:60,1')->name('analytics.track');

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

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
    Route::middleware('auth:web')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Bookings
        Route::get('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [App\Http\Controllers\Admin\BookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{booking}', [App\Http\Controllers\Admin\BookingController::class, 'show'])->name('bookings.show');
        Route::patch('/bookings/{booking}/notes', [App\Http\Controllers\Admin\BookingController::class, 'updateNotes'])->name('bookings.notes');
        Route::post('/bookings/{booking}/complete', [App\Http\Controllers\Admin\BookingController::class, 'complete'])->name('bookings.complete');
        Route::post('/bookings/{booking}/no-show', [App\Http\Controllers\Admin\BookingController::class, 'markNoShow'])->name('bookings.no-show');
        Route::post('/bookings/{booking}/reschedule', [App\Http\Controllers\Admin\BookingController::class, 'reschedule'])->name('bookings.reschedule');
        Route::post('/bookings/{booking}/cancel', [App\Http\Controllers\Admin\BookingController::class, 'cancel'])->name('bookings.cancel');

        // Availability
        Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
        Route::post('/availability/rules', [AvailabilityController::class, 'storeRule'])->name('availability.rules.store');
        Route::post('/availability/rules/{rule}/toggle', [AvailabilityController::class, 'toggleRule'])->name('availability.toggle');
        Route::delete('/availability/rules/{rule}', [AvailabilityController::class, 'destroyRule'])->name('availability.destroy');
        Route::post('/availability/exceptions', [AvailabilityController::class, 'storeException'])->name('availability.exceptions.store');
        Route::delete('/availability/exceptions/{exception}', [AvailabilityController::class, 'destroyException'])->name('availability.exception.destroy');

        // Contacts & Leads
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/leads', [ContactController::class, 'leads'])->name('leads');
        Route::get('/contacts/duplicates', [ContactController::class, 'duplicates'])->name('contacts.duplicates');
        Route::post('/contacts/merge', [ContactController::class, 'merge'])->name('contacts.merge');
        Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
        Route::patch('/contacts/{contact}/notes', [ContactController::class, 'updateNotes'])->name('contacts.notes');

        // Resources & Categories
        Route::resource('resource-categories', ResourceCategoryController::class)->except(['show']);
        Route::get('/resources', [App\Http\Controllers\Admin\ResourceController::class, 'index'])->name('resources.index');
        Route::get('/resources/create', [App\Http\Controllers\Admin\ResourceController::class, 'create'])->name('resources.create');
        Route::post('/resources', [App\Http\Controllers\Admin\ResourceController::class, 'store'])->name('resources.store');
        Route::get('/resources/{resource}/edit', [App\Http\Controllers\Admin\ResourceController::class, 'edit'])->name('resources.edit');
        Route::put('/resources/{resource}', [App\Http\Controllers\Admin\ResourceController::class, 'update'])->name('resources.update');
        Route::delete('/resources/{resource}', [App\Http\Controllers\Admin\ResourceController::class, 'destroy'])->name('resources.destroy');

        // Games Lifecycle Management
        Route::get('/games', [App\Http\Controllers\Admin\GameController::class, 'index'])->name('games.index');
        Route::get('/games/create', [App\Http\Controllers\Admin\GameController::class, 'create'])->name('games.create');
        Route::post('/games', [App\Http\Controllers\Admin\GameController::class, 'store'])->name('games.store');
        Route::get('/games/{game}/edit', [App\Http\Controllers\Admin\GameController::class, 'edit'])->name('games.edit');
        Route::put('/games/{game}', [App\Http\Controllers\Admin\GameController::class, 'update'])->name('games.update');
        Route::delete('/games/{game}', [App\Http\Controllers\Admin\GameController::class, 'destroy'])->name('games.destroy');

        // Internal Notifications Center
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        // Content & FAQs
        Route::get('/content', [ContentController::class, 'index'])->name('content.index');
        Route::post('/content/faqs', [ContentController::class, 'storeFaq'])->name('content.faq.store');
        Route::put('/content/faqs/{faq}', [ContentController::class, 'updateFaq'])->name('content.faq.update');
        Route::delete('/content/faqs/{faq}', [ContentController::class, 'destroyFaq'])->name('content.faq.destroy');
        Route::post('/content/social', [ContentController::class, 'updateSocial'])->name('content.social.update');

        // CMS Custom Pages & Revisions
        Route::resource('pages', App\Http\Controllers\Admin\PageController::class)->except(['show']);
        Route::post('/pages/{page}/revisions/{revision}/restore', [App\Http\Controllers\Admin\PageController::class, 'restoreRevision'])->name('pages.revisions.restore');

        // Media Library
        Route::get('/media', [MediaController::class, 'index'])->name('media.index');
        Route::get('/media/picker', [MediaController::class, 'picker'])->name('media.picker');
        Route::post('/media', [MediaController::class, 'store'])->name('media.store');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        // Global Search
        Route::get('/search', [SearchController::class, 'search'])->name('search');

        // Analytics & Business Funnels
        Route::get('/analytics', [AnalyticsDashboardController::class, 'index'])->name('analytics');

        // Reports & Data Exports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

        // Super Admin Only Privileges
        Route::middleware('role:super_admin')->group(function () {
            // Settings
            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
            Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

            // System, Backups & Audit
            Route::get('/health', [SystemHealthController::class, 'index'])->name('health');
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
