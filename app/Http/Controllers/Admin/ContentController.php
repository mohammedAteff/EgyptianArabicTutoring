<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\SocialLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        $faqs = Faq::query()->orderBy('sort_order')->get();
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

        return back()->with('success', 'FAQ added successfully.');
    }

    public function updateFaq(Request $request, Faq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $prev = $faq->toArray();
        $faq->update([
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'active' => $request->boolean('active'),
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'faq_updated',
            'entity_type' => Faq::class,
            'entity_id' => $faq->id,
            'previous_data' => $prev,
            'new_data' => $faq->toArray(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'FAQ updated.');
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
