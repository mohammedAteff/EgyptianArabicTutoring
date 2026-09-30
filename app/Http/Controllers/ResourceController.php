<?php

namespace App\Http\Controllers;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest as ResourceRequestModel;
use App\Mail\ResourceVerificationPinMail;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public function issueDownloadGrant(Resource $resource, Contact $contact, string $visitorToken, string $sessionToken, ?int $requestId = null): string
    {
        $downloadToken = Str::random(64);
        Cache::put("resource_download_token_{$downloadToken}", [
            'resource_id' => $resource->id,
            'contact_id' => $contact->id,
            'request_id' => $requestId,
            'session_id' => request()->hasSession() ? request()->session()->getId() : null,
            'visitor_token' => $visitorToken,
        ], now()->addMinutes(60));

        if (request()->hasSession()) {
            request()->session()->put("download_token_{$resource->id}", [
                'token' => $downloadToken,
                'contact_id' => $contact->id,
                'request_id' => $requestId,
                'session_id' => request()->session()->getId(),
                'visitor_token' => $visitorToken,
            ]);
        }

        return $downloadToken;
    }

    public function requestAccess(Request $request, string $slug, ContactService $contactService)
    {
        $resource = Resource::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        $request->validate([
            'email' => [
                'bail',
                'required',
                'string',
                'email:rfc',
                function ($attribute, $value, $fail) {
                    if (app()->environment('testing')) {
                        return;
                    }
                    $domain = substr(strrchr((string) $value, '@'), 1);
                    if (! $domain || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                        $fail('The email domain format is invalid.');

                        return;
                    }
                    $cacheKey = 'dns_routability_'.md5(strtolower($domain));
                    $cached = Cache::get($cacheKey);
                    if ($cached === 'VALID') {
                        return;
                    }
                    $hasMx = @checkdnsrr($domain, 'MX');
                    $hasA = $hasMx ? false : @checkdnsrr($domain, 'A');
                    if ($hasMx || $hasA) {
                        Cache::put($cacheKey, 'VALID', 86400);

                        return;
                    }
                    Log::warning("DNS routability inconclusive for {$domain}; failing open per architectural contract.");
                },
                'max:255',
            ],
            'name' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'medium' => ['nullable', 'string', 'max:255'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:255'],
            'term' => ['nullable', 'string', 'max:255'],
            'landing_page' => ['nullable', 'string', 'max:255'],
        ]);

        $hasCookieVisitor = $request->hasCookie('_va_visitor') || $request->hasCookie('visitor_token');
        $hasCookieSession = $request->hasCookie('_va_session') || $request->hasCookie('session_token');

        if ($request->expectsJson() || $request->ajax()) {
            if (! $hasCookieVisitor || ! $hasCookieSession) {
                abort(403, 'Missing required visitor security context.');
            }
        }

        $visitorToken = (string) ($request->cookie('_va_visitor')
            ?? $request->cookie('visitor_token')
            ?? $request->attributes->get('visitor_token')
            ?? (session()->isStarted() ? session('analytics_visitor_token') : null));

        $sessionToken = (string) ($request->cookie('_va_session')
            ?? $request->cookie('session_token')
            ?? $request->attributes->get('session_token')
            ?? (session()->isStarted() ? session('analytics_session_token') : null));

        if (empty($visitorToken) || empty($sessionToken)) {
            abort(403, 'Missing required visitor security context.');
        }

        $visSession = VisitorSession::where('session_token', $sessionToken)->first();

        $attribution = [
            'utm_source' => $request->query('utm_source') ?? $request->input('source') ?? ($request->hasSession() ? $request->session()->get('utm_source') : null) ?? $visSession?->utm_source,
            'utm_medium' => $request->query('utm_medium') ?? $request->input('medium') ?? ($request->hasSession() ? $request->session()->get('utm_medium') : null) ?? $visSession?->utm_medium,
            'utm_campaign' => $request->query('utm_campaign') ?? $request->input('campaign') ?? ($request->hasSession() ? $request->session()->get('utm_campaign') : null) ?? $visSession?->utm_campaign,
            'utm_content' => $request->query('utm_content') ?? $request->input('content') ?? ($request->hasSession() ? $request->session()->get('utm_content') : null) ?? $visSession?->utm_content,
            'utm_term' => $request->query('utm_term') ?? $request->input('term') ?? ($request->hasSession() ? $request->session()->get('utm_term') : null) ?? $visSession?->utm_term,
            'source' => $request->query('utm_source') ?? $request->input('source') ?? ($request->hasSession() ? $request->session()->get('utm_source') : null) ?? $visSession?->utm_source,
            'medium' => $request->query('utm_medium') ?? $request->input('medium') ?? ($request->hasSession() ? $request->session()->get('utm_medium') : null) ?? $visSession?->utm_medium,
            'campaign' => $request->query('utm_campaign') ?? $request->input('campaign') ?? ($request->hasSession() ? $request->session()->get('utm_campaign') : null) ?? $visSession?->utm_campaign,
            'content' => $request->query('utm_content') ?? $request->input('content') ?? ($request->hasSession() ? $request->session()->get('utm_content') : null) ?? $visSession?->utm_content,
            'term' => $request->query('utm_term') ?? $request->input('term') ?? ($request->hasSession() ? $request->session()->get('utm_term') : null) ?? $visSession?->utm_term,
            'landing_page' => $request->input('landing_page') ?? $request->header('referer'),
        ];

        $pinEnabled = (bool) config('business.resources.require_pin_verification', false);

        if (! $pinEnabled) {
            $result = DB::transaction(function () use ($resource, $request, $visitorToken, $sessionToken, $attribution) {
                $contact = app(ContactService::class)->resolveOrCreate(
                    email: $request->email,
                    name: $request->name,
                    phone: null,
                    attribution: $attribution
                );

                $resourceRequest = ResourceRequestModel::create([
                    'resource_id' => $resource->id,
                    'contact_id' => $contact->id,
                    'visitor_token' => $visitorToken,
                    'session_token' => $sessionToken,
                    'source' => $attribution['source'] ?? null,
                    'medium' => $attribution['medium'] ?? null,
                    'campaign' => $attribution['campaign'] ?? null,
                    'content' => $attribution['content'] ?? null,
                    'term' => $attribution['term'] ?? null,
                    'landing_page' => $attribution['landing_page'] ?? null,
                ]);

                app(AnalyticsService::class)->trackEvent(
                    eventType: 'resource_requested',
                    page: '/'.ltrim($request->path(), '/'),
                    visitorToken: $visitorToken,
                    sessionToken: $sessionToken,
                    metadata: ['resource_id' => $resource->id, 'resource_title' => $resource->title]
                );

                try {
                    app(AdminNotificationService::class)->notifyResourceRequested($resourceRequest);
                } catch (\Throwable) {
                }

                $grantToken = $this->issueDownloadGrant($resource, $contact, $visitorToken, $sessionToken, $resourceRequest->id);

                $downloadUrl = route('resources.download', [
                    'slug' => $resource->slug,
                    'token' => $grantToken,
                ]);

                return ['download_url' => $downloadUrl, 'grant_token' => $grantToken];
            });

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['requires_pin' => false, 'download_url' => $result['download_url']]);
            }

            $targetUrl = app(LocalizedUrlService::class)->getLocalizedUrl('resource.detail', app()->getLocale(), $resource->slug);

            return redirect()->to($targetUrl)
                ->with('access_granted', true)
                ->with('download_token', $result['grant_token'])
                ->with('success', 'Your download is ready! Click the button below to get your file.');
        }

        $cacheStore = config('cache.default');
        if (! in_array($cacheStore, ['database', 'redis', 'memcached'], true)) {
            abort(500, 'PIN verification requires a shared cache store (database, redis, or memcached).');
        }

        $challenge = bin2hex(random_bytes(32));
        $pin = sprintf('%06d', random_int(0, 999999));

        Cache::put("resource_pin:{$challenge}", [
            'hash' => Hash::make($pin),
            'name' => $request->name,
            'email' => strtolower(trim((string) $request->email)),
            'resource_id' => $resource->id,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'attribution' => $attribution,
            'attempts' => 0,
        ], now()->addMinutes(10));

        Mail::to($request->email)->send(new ResourceVerificationPinMail($pin));

        return response()->json(['requires_pin' => true, 'challenge' => $challenge]);
    }

    public function verifyPin(Request $request, string $slug)
    {
        $resource = Resource::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $request->validate([
            'challenge' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'pin' => ['required', 'string', 'digits:6'],
        ]);

        $challenge = (string) $request->input('challenge');
        $pin = (string) $request->input('pin');
        $challengeHash = hash('sha256', $challenge);

        $cacheStore = config('cache.default');
        if (! in_array($cacheStore, ['database', 'redis', 'memcached'], true)) {
            abort(500, 'PIN verification requires a shared cache store (database, redis, or memcached).');
        }

        $hasCookieVisitor = $request->hasCookie('_va_visitor') || $request->hasCookie('visitor_token');
        $hasCookieSession = $request->hasCookie('_va_session') || $request->hasCookie('session_token');

        if (! $hasCookieVisitor || ! $hasCookieSession) {
            abort(403, 'Missing required visitor security context.');
        }

        $currentVisitorToken = (string) ($request->cookie('_va_visitor')
            ?? $request->cookie('visitor_token')
            ?? $request->attributes->get('visitor_token'));

        $currentSessionToken = (string) ($request->cookie('_va_session')
            ?? $request->cookie('session_token')
            ?? $request->attributes->get('session_token'));

        if (empty($currentVisitorToken) || empty($currentSessionToken)) {
            abort(403, 'Missing required visitor security context.');
        }

        $connection = DB::connection();
        $dbName = substr($connection->getDatabaseName(), 0, 20);
        $challengeDigest = substr($challengeHash, 0, 32);
        $lockKey = "{$dbName}:pin:{$challengeDigest}";
        if (strlen($lockKey) > 64) {
            throw new \LogicException("Lock key exceeds 64 characters: [{$lockKey}]");
        }

        $lock = Cache::lock("lock:{$lockKey}", 15);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            abort(429, 'Verification already in progress.');
        }

        try {
            if (ResourceRequestModel::where('consumed_challenge_hash', $challengeHash)->exists()) {
                abort(409, 'This verification code has already been consumed.');
            }

            $cached = Cache::get("resource_pin:{$challenge}");
            if (! is_array($cached) || (int) ($cached['resource_id'] ?? 0) !== (int) $resource->id) {
                abort(403, 'Invalid or expired challenge.');
            }

            if ($currentVisitorToken !== $cached['visitor_token'] || $currentSessionToken !== $cached['session_token']) {
                abort(403, 'Session authorization mismatch.');
            }

            if (! Hash::check($pin, $cached['hash'])) {
                $cached['attempts'] = ((int) ($cached['attempts'] ?? 0)) + 1;
                if ($cached['attempts'] >= 5) {
                    Cache::forget("resource_pin:{$challenge}");
                    abort(429, 'Too many failed verification attempts.');
                }
                Cache::put("resource_pin:{$challenge}", $cached, now()->addMinutes(10));
                abort(422, 'Invalid verification code.');
            }

            try {
                $downloadUrl = DB::transaction(function () use ($resource, $cached, $challengeHash, $currentVisitorToken, $currentSessionToken) {
                    $attribution = $cached['attribution'] ?? [];
                    $contact = app(ContactService::class)->resolveOrCreate(
                        email: $cached['email'],
                        name: $cached['name'],
                        phone: null,
                        attribution: $attribution
                    );

                    $resourceRequest = ResourceRequestModel::create([
                        'resource_id' => $resource->id,
                        'contact_id' => $contact->id,
                        'visitor_token' => $currentVisitorToken,
                        'session_token' => $currentSessionToken,
                        'consumed_challenge_hash' => $challengeHash,
                        'source' => $attribution['source'] ?? null,
                        'medium' => $attribution['medium'] ?? null,
                        'campaign' => $attribution['campaign'] ?? null,
                        'content' => $attribution['content'] ?? null,
                        'term' => $attribution['term'] ?? null,
                        'landing_page' => $attribution['landing_page'] ?? null,
                    ]);

                    app(AnalyticsService::class)->trackEvent(
                        eventType: 'resource_requested',
                        page: '/'.ltrim(request()->path(), '/'),
                        visitorToken: $currentVisitorToken,
                        sessionToken: $currentSessionToken,
                        metadata: ['resource_id' => $resource->id, 'resource_title' => $resource->title]
                    );

                    try {
                        app(AdminNotificationService::class)->notifyResourceRequested($resourceRequest);
                    } catch (\Throwable) {
                    }

                    $grantToken = $this->issueDownloadGrant($resource, $contact, $currentVisitorToken, $currentSessionToken, $resourceRequest->id);

                    return route('resources.download', [
                        'slug' => $resource->slug,
                        'token' => $grantToken,
                    ]);
                });
            } catch (QueryException $e) {
                $isDuplicate = $e->getCode() === '23000' && (($e->errorInfo[1] ?? null) === 1062);
                $isHashViolation = str_contains($e->getMessage(), 'resource_requests_consumed_challenge_hash_unique')
                    || str_contains($e->errorInfo[2] ?? '', 'resource_requests_consumed_challenge_hash_unique');
                if ($isDuplicate && $isHashViolation) {
                    abort(409, 'This verification code has already been consumed.');
                }
                throw $e;
            }

            Cache::forget("resource_pin:{$challenge}");

            return response()->json(['download_url' => $downloadUrl]);
        } finally {
            $lock->release();
        }
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
