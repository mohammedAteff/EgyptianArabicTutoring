<?php

namespace App\Http\Controllers;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest as ResourceRequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $categorySlug = $request->query('category');

        $categories = ResourceCategory::where('active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $query = Resource::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['category.translations', 'translations'])
            ->orderBy('sort_order');

        if ($categorySlug) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        $resources = $query->paginate(12)->withQueryString();

        return view('public.resources.index', [
            'resources' => $resources,
            'categories' => $categories,
            'selectedCategory' => $categorySlug,
            'title' => 'Free Egyptian Arabic Workbooks & Guides',
            'isFallback' => false,
            'entityLocales' => ['en', 'fr', 'de'],
        ]);
    }

    public function show(string $slug): View
    {
        $resource = Resource::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['category.translations', 'translations'])
            ->firstOrFail();

        $resolved = $resource->resolveTranslation();

        return view('public.resources.show', [
            'resource' => $resource,
            'translation' => $resolved['translation'],
            'isFallback' => $resolved['is_fallback'],
            'isStale' => $resolved['is_stale'],
            'entityLocales' => $resource->getAvailableLocales(),
            'title' => ($resolved['translation']?->title ?? $resource->title).' — Free Egyptian Arabic Resource',
        ]);
    }

    public function preview(Request $request, string $slug): View
    {
        if (! Auth::guard('web')->check()) {
            abort(403, 'Preview requires administrator authentication.');
        }

        $resource = Resource::query()
            ->where('slug', $slug)
            ->with('category')
            ->firstOrFail();

        $draftRevision = $resource->revisions()->where('status', 'draft')->latest('id')->first();
        if ($draftRevision) {
            $previewResource = clone $resource;
            $previewResource->title = $draftRevision->title ?? $resource->title;
            $previewResource->short_description = $draftRevision->content['short_description'] ?? $resource->short_description;
            $previewResource->cover_image_path = $draftRevision->content['cover_image_path'] ?? $resource->cover_image_path;
        } else {
            $previewResource = $resource;
        }

        return view('public.resources.show', [
            'resource' => $previewResource,
            'title' => '[PREVIEW] '.$previewResource->title,
            'isPreview' => true,
        ]);
    }

    public function requestAccess(Request $request, string $slug, ContactService $contactService)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:100',
        ]);

        $resource = Resource::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        $visitorToken = $request->attributes->get('analytics_visitor_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_visitor_token') : null)
            ?? $request->cookie('_va_visitor');

        $sessionToken = $request->attributes->get('analytics_session_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_session_token') : null)
            ?? $request->cookie('_va_session');

        $visSession = $sessionToken ? VisitorSession::where('session_token', $sessionToken)->first() : null;

        // 1. Resolve or create canonical contact
        $attribution = [
            'utm_source' => $request->query('utm_source') ?? ($request->hasSession() ? $request->session()->get('utm_source') : null) ?? $visSession?->utm_source,
            'utm_medium' => $request->query('utm_medium') ?? ($request->hasSession() ? $request->session()->get('utm_medium') : null) ?? $visSession?->utm_medium,
            'utm_campaign' => $request->query('utm_campaign') ?? ($request->hasSession() ? $request->session()->get('utm_campaign') : null) ?? $visSession?->utm_campaign,
            'utm_content' => $request->query('utm_content') ?? ($request->hasSession() ? $request->session()->get('utm_content') : null) ?? $visSession?->utm_content,
            'utm_term' => $request->query('utm_term') ?? ($request->hasSession() ? $request->session()->get('utm_term') : null) ?? $visSession?->utm_term,
        ];

        [$contact, $resourceRequest] = DB::transaction(function () use ($contactService, $request, $resource, $attribution): array {
            $contact = $contactService->resolveOrCreate(
                email: $request->input('email'),
                name: $request->input('name'),
                attribution: $attribution
            );

            $resourceRequest = ResourceRequestModel::create([
                'contact_id' => $contact->id,
                'resource_id' => $resource->id,
                'source' => $attribution['utm_source'] ?? null,
                'medium' => $attribution['utm_medium'] ?? null,
                'campaign' => $attribution['utm_campaign'] ?? null,
                'content' => $attribution['utm_content'] ?? null,
                'term' => $attribution['utm_term'] ?? null,
                'landing_page' => $request->header('referer'),
            ]);

            return [$contact, $resourceRequest];
        }, 3);

        // 3. Log analytics event
        app(AnalyticsService::class)->trackEvent(
            eventType: 'resource_requested',
            page: '/'.ltrim($request->path(), '/'),
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            metadata: [
                'resource_slug' => $resource->slug,
                'resource_title' => $resource->title,
                'request_id' => $resourceRequest->id,
            ]
        );

        // Internal admin notification for new lead
        try {
            app(AdminNotificationService::class)->notifyResourceRequested($resourceRequest);
        } catch (\Throwable) {
            // Failure to dispatch notification must not break visitor download flow
        }

        // 4. Generate expiring download token in cache and session
        $downloadToken = Str::random(64);
        Cache::put("resource_download_token_{$downloadToken}", [
            'resource_id' => $resource->id,
            'contact_id' => $contact->id,
            'request_id' => $resourceRequest->id,
            'session_id' => $request->session()->getId(),
            'visitor_token' => $visitorToken,
        ], now()->addMinutes(60));

        $request->session()->put("download_token_{$resource->id}", [
            'token' => $downloadToken,
            'contact_id' => $contact->id,
            'request_id' => $resourceRequest->id,
            'session_id' => $request->session()->getId(),
            'visitor_token' => $visitorToken,
        ]);

        $targetUrl = app(LocalizedUrlService::class)->getLocalizedUrl('resource.detail', app()->getLocale(), $resource->slug);

        return redirect()->to($targetUrl)
            ->with('access_granted', true)
            ->with('download_token', $downloadToken)
            ->with('success', 'Your download is ready! Click the button below to get your file.');
    }

    public function download(Request $request, string $slug, ContactService $contactService)
    {
        $resource = Resource::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        $tokenData = null;
        $sessionTokenData = $request->session()->get("download_token_{$resource->id}");
        $visitorToken = $request->attributes->get('analytics_visitor_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_visitor_token') : null)
            ?? $request->cookie('_va_visitor');
        $matchesGrant = function (?array $grant) use ($resource, $request, $visitorToken): bool {
            if (! $grant || ($grant['resource_id'] ?? null) !== $resource->id) {
                return false;
            }

            $sameSession = isset($grant['session_id'])
                && hash_equals((string) $grant['session_id'], (string) $request->session()->getId());
            $sameVisitor = ! isset($grant['visitor_token'])
                || ! $grant['visitor_token']
                || ! $visitorToken
                || hash_equals((string) $grant['visitor_token'], (string) $visitorToken);

            return $sameSession && $sameVisitor;
        };

        if ($resource->is_gated) {
            $token = $request->query('token');

            if ($token) {
                // Validate and consume bearer tokens while holding a per-token
                // lock. An unauthorized replay must not consume the legitimate
                // visitor's grant, while two authorized requests cannot both
                // download the same single-use token.
                $tokenData = Cache::lock("resource_download_consume_{$token}", 5)->block(2, function () use ($token, $matchesGrant): ?array {
                    $cacheKey = "resource_download_token_{$token}";
                    $data = Cache::get($cacheKey);

                    if (! is_array($data) || ! $matchesGrant($data)) {
                        return null;
                    }

                    Cache::forget($cacheKey);

                    return $data;
                });
            }

            if (! $tokenData && $sessionTokenData) {
                $tokenData = $sessionTokenData;
            }

            if (! $matchesGrant($tokenData)) {
                $targetUrl = app(LocalizedUrlService::class)->getLocalizedUrl('resource.detail', app()->getLocale(), $slug);

                return redirect()->to($targetUrl)
                    ->with('error', 'Please enter your email to get free access to this resource.');
            }

            // The session copy is also single-use. This prevents a second
            // request without the query token from replaying the grant.
            $request->session()->forget("download_token_{$resource->id}");
        }

        if (! empty($resource->external_url)) {
            // Record download
            DB::transaction(function () use ($resource, $tokenData, $contactService): void {
                $contact = ! empty($tokenData['contact_id'])
                    ? $contactService->resolveCanonicalContact((int) $tokenData['contact_id'], lock: true)
                    : null;

                ResourceDownload::create([
                    'resource_id' => $resource->id,
                    'contact_id' => $contact?->id,
                    'request_id' => $tokenData['request_id'] ?? null,
                    'created_at' => now(),
                ]);
            }, 3);

            // Log analytics event
            $visitorToken = $request->session()->get('analytics_visitor_token');
            $sessionToken = $request->session()->get('analytics_session_token');

            app(AnalyticsService::class)->trackEvent(
                eventType: 'resource_downloaded',
                page: '/'.ltrim($request->path(), '/'),
                visitorToken: $visitorToken,
                sessionToken: $sessionToken,
                metadata: [
                    'resource_slug' => $resource->slug,
                    'resource_title' => $resource->title,
                    'external_url' => $resource->external_url,
                ]
            );

            return redirect()->away($resource->external_url);
        }

        // Verify physical file exists on disk
        $disk = Storage::disk('local');
        $filePath = $resource->file_path;

        if (! $filePath || ! $disk->exists($filePath)) {
            abort(404, 'The requested resource file is currently unavailable.');
        }

        // Record download
        DB::transaction(function () use ($resource, $tokenData, $contactService): void {
            $contact = ! empty($tokenData['contact_id'])
                ? $contactService->resolveCanonicalContact((int) $tokenData['contact_id'], lock: true)
                : null;

            ResourceDownload::create([
                'resource_id' => $resource->id,
                'contact_id' => $contact?->id,
                'request_id' => $tokenData['request_id'] ?? null,
                'created_at' => now(),
            ]);
        }, 3);

        // Log analytics event
        $visitorToken = $request->session()->get('analytics_visitor_token');
        $sessionToken = $request->session()->get('analytics_session_token');

        app(AnalyticsService::class)->trackEvent(
            eventType: 'resource_downloaded',
            page: '/'.ltrim($request->path(), '/'),
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            metadata: [
                'resource_slug' => $resource->slug,
                'resource_title' => $resource->title,
            ]
        );

        $downloadName = $resource->slug.'.'.($resource->file_type ?? 'pdf');

        return $disk->download($filePath, $downloadName);
    }
}
