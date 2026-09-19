<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\CMS\Services\TranslationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $socials = $request->input('socials', []);

        foreach ($socials as $id => $data) {
            $link = SocialLink::find($id);
            if ($link) {
                $link->update([
                    'url_or_phone' => $data['url_or_phone'] ?? $link->url_or_phone,
                    'label' => $data['label'] ?? $link->label,
                    'default_message' => $data['default_message'] ?? null,
                    'enabled' => isset($data['enabled']),
                ]);
            }
        }

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'social_links_updated',
            'entity_type' => SocialLink::class,
            'entity_id' => 0,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Social links updated.');
    }
}
