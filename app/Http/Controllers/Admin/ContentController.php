<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\CMS\Services\TranslationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        $faqs = Faq::query()->with(['revisions' => function ($q) {
            $q->where('status', 'draft')->latest('id');
        }])->orderBy('sort_order')->get();
        $pages = Page::query()->orderBy('title')->get();
        $socials = SocialLink::query()->orderBy('sort_order')->get();

        return view('admin.content.index', [
            'title' => 'Content, FAQs & Social Channels',
            'faqs' => $faqs,
            'pages' => $pages,
            'socials' => $socials,
        ]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $faq = Faq::create([
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'active' => $request->boolean('active', true),
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'faq_created',
            'entity_type' => Faq::class,
            'entity_id' => $faq->id,
            'new_data' => $faq->toArray(),
            'created_at' => now(),
        ]);

        app(TranslationService::class)->updateEnglishSource($faq, [
            'question' => $faq->question,
            'answer' => $faq->answer,
        ], Auth::id());

        return back()->with('success', 'FAQ added successfully.');
    }

    public function updateFaq(Request $request, Faq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'action' => ['nullable', 'string', 'in:draft,publish'],
        ]);

        $action = $request->input('action', 'publish');

        if ($action === 'draft') {
            $nextRevision = ($faq->revisions()->max('revision_number') ?? 0) + 1;
            ContentRevision::create([
                'revisable_type' => Faq::class,
                'revisable_id' => $faq->id,
                'revision_number' => $nextRevision,
                'title' => $validated['question'],
                'content' => [
                    'question' => $validated['question'],
                    'answer' => $validated['answer'],
                    'sort_order' => $validated['sort_order'] ?? $faq->sort_order,
                    'active' => $request->boolean('active', true),
                ],
                'created_by_id' => Auth::id(),
                'status' => 'draft',
            ]);

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'faq_draft_saved',
                'entity_type' => Faq::class,
                'entity_id' => $faq->id,
                'new_data' => [
                    'revision_number' => $nextRevision,
                    'question' => $validated['question'],
                ],
                'created_at' => now(),
            ]);

            return back()->with('success', "FAQ draft revision #{$nextRevision} saved. The live FAQ remains untouched.");
        }

        $prev = $faq->toArray();

        DB::transaction(function () use ($faq, $validated, $request, $prev) {
            $lockedFaq = Faq::where('id', $faq->id)->lockForUpdate()->firstOrFail();

            $lockedFaq->update([
                'question' => $validated['question'],
                'answer' => $validated['answer'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'active' => $request->boolean('active'),
            ]);

            app(TranslationService::class)->updateEnglishSource($lockedFaq, [
                'question' => $lockedFaq->question,
                'answer' => $lockedFaq->answer,
            ], Auth::id());

            $lockedFaq->revisions()->where('status', 'draft')->update(['status' => 'archived']);

            $nextRevision = ($lockedFaq->revisions()->max('revision_number') ?? 0) + 1;
            ContentRevision::create([
                'revisable_type' => Faq::class,
                'revisable_id' => $lockedFaq->id,
                'revision_number' => $nextRevision,
                'title' => $lockedFaq->question,
                'content' => [
                    'question' => $lockedFaq->question,
                    'answer' => $lockedFaq->answer,
                    'sort_order' => $lockedFaq->sort_order,
                    'active' => $lockedFaq->active,
                ],
                'created_by_id' => Auth::id(),
                'status' => 'published',
            ]);

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'faq_updated',
                'entity_type' => Faq::class,
                'entity_id' => $lockedFaq->id,
                'previous_data' => $prev,
                'new_data' => $lockedFaq->toArray(),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'FAQ published.');
    }

    public function discardFaqDraft(Faq $faq): RedirectResponse
    {
        $faq->revisions()->where('status', 'draft')->delete();

        return back()->with('success', 'FAQ draft discarded. Reverted to live published values.');
    }

    public function destroyFaq(Faq $faq): RedirectResponse
    {
        $prev = $faq->toArray();
        $faq->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'faq_deleted',
            'entity_type' => Faq::class,
            'entity_id' => $faq->id,
            'previous_data' => $prev,
            'created_at' => now(),
        ]);

        return back()->with('success', 'FAQ removed.');
    }

    public function updateSocial(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'socials' => ['required', 'array', 'max:50'],
            'socials.*' => ['required', 'array'],
            'socials.*.url_or_phone' => ['required', 'string', 'max:2048'],
            'socials.*.label' => ['nullable', 'string', 'max:100'],
            'socials.*.default_message' => ['nullable', 'string', 'max:1000'],
            'socials.*.enabled' => ['nullable', 'boolean'],
            'socials.*.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        DB::transaction(function () use ($validated): void {
            $ids = array_map('intval', array_keys($validated['socials']));
            sort($ids);
            $links = SocialLink::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($validated['socials'] as $id => $data) {
                $link = $links->get((int) $id);
                abort_if(! $link, 404);
                $this->validateSocialTarget($link, $data['url_or_phone']);
                $link->update([
                    'url_or_phone' => trim($data['url_or_phone']),
                    'label' => trim((string) ($data['label'] ?? $link->label)),
                    'default_message' => $link->platform === 'whatsapp' ? ($data['default_message'] ?? null) : null,
                    'enabled' => (bool) ($data['enabled'] ?? false),
                    'sort_order' => (int) $data['sort_order'],
                ]);
            }

            AuditLog::create([
                'administrator_id' => Auth::guard('web')->id(),
                'action' => 'social_links_updated',
                'entity_type' => SocialLink::class,
                'entity_id' => 0,
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Social links updated.');
    }

    public function toggleSocial(Request $request, SocialLink $socialLink): JsonResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $socialLink->update(['enabled' => (bool) $validated['enabled']]);

        return response()->json(['enabled' => $socialLink->enabled]);
    }

    public function storeSocial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'string', Rule::in(['youtube', 'instagram', 'tiktok', 'facebook', 'linkedin', 'x', 'custom', 'whatsapp', 'telegram'])],
            'label' => ['required', 'string', 'max:100'],
            'url_or_phone' => ['required', 'string', 'max:2048'],
            'default_message' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $candidate = new SocialLink(['platform' => $data['platform']]);
        $this->validateSocialTarget($candidate, $data['url_or_phone']);

        DB::transaction(function () use ($data): void {
            SocialLink::create([
                'platform' => $data['platform'],
                'label' => trim($data['label']),
                'url_or_phone' => trim($data['url_or_phone']),
                'default_message' => $data['platform'] === 'whatsapp' ? ($data['default_message'] ?? null) : null,
                'sort_order' => $data['sort_order'] ?? ((int) SocialLink::query()->max('sort_order') + 1),
                'enabled' => false,
            ]);
            AuditLog::create([
                'administrator_id' => Auth::guard('web')->id(),
                'action' => 'social_link_created',
                'entity_type' => SocialLink::class,
                'entity_id' => 0,
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Social channel added.');
    }

    private function validateSocialTarget(SocialLink $link, string $target): void
    {
        $target = trim($target);
        if ($link->platform === 'whatsapp') {
            $phone = preg_replace('/[^0-9]/', '', $target);
            if (! preg_match('/^\+?[1-9][0-9\s().-]{6,22}$/', $target) || strlen((string) $phone) < 8 || strlen((string) $phone) > 15) {
                throw ValidationException::withMessages(['socials' => 'Enter a valid international WhatsApp phone number.']);
            }

            return;
        }
        if ($link->platform === 'telegram' && preg_match('/^@?[A-Za-z0-9_]{5,32}$/', $target)) {
            return;
        }

        $parts = parse_url($target);
        if (! filter_var($target, FILTER_VALIDATE_URL)
            || ! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw ValidationException::withMessages(['socials' => 'Social URLs must be complete http or https URLs without embedded credentials.']);
        }
    }
}
