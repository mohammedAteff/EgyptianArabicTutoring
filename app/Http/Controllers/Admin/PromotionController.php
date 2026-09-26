<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Media;
use App\Domains\Marketing\Models\Promotion;
use App\Domains\Marketing\Services\PromotionService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionService $promotionService
    ) {}

    public function index(): View
    {
        $promotions = Promotion::query()
            ->latest('id')
            ->paginate(15);

        return view('admin.promotions.index', [
            'promotions' => $promotions,
            'promotionService' => $this->promotionService,
        ]);
    }

    public function create(): View
    {
        return view('admin.promotions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'headline' => 'required|string|max:255',
            'subheadline' => 'nullable|string',
            'cta_text' => 'required|string|max:100',
            'cta_url' => ['required', 'string', 'max:255', $this->safeCtaUrlRule()],
            'display_type' => 'required|in:top_bar,floating_modal,inline_card',
            'banner_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'banner_image_path' => ['nullable', 'string', 'max:255', $this->safeBannerPathRule()],
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'has_countdown' => 'nullable|boolean',
        ]);

        $bannerPath = $validated['banner_image_path'] ?? null;
        if ($request->hasFile('banner_image')) {
            $bannerPath = $this->storeBannerImage($request->file('banner_image'));
        }

        // Store timestamps as UTC instants; form inputs are in Cairo local time
        $startsAtUtc = ! empty($validated['starts_at'])
            ? Carbon::parse($validated['starts_at'], 'Africa/Cairo')->setTimezone('UTC')
            : null;

        $endsAtUtc = ! empty($validated['ends_at'])
            ? Carbon::parse($validated['ends_at'], 'Africa/Cairo')->setTimezone('UTC')
            : null;

        $promotion = Promotion::create([
            'title' => $validated['title'],
            'headline' => $validated['headline'],
            'subheadline' => $validated['subheadline'] ?? null,
            'cta_text' => $validated['cta_text'],
            'cta_url' => $validated['cta_url'],
            'banner_image_path' => $bannerPath,
            'display_type' => $validated['display_type'],
            'is_active' => $request->boolean('is_active'),
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
            'has_countdown' => $request->boolean('has_countdown'),
        ]);

        $this->promotionService->clearCache();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'promotion_created',
            'entity_type' => Promotion::class,
            'entity_id' => $promotion->id,
            'new_data' => $promotion->toArray(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion '{$promotion->title}' created successfully.");
    }

    public function edit(Promotion $promotion): View
    {
        $startsAtCairo = $promotion->starts_at
            ? $promotion->starts_at->copy()->setTimezone('Africa/Cairo')->format('Y-m-d\TH:i')
            : null;

        $endsAtCairo = $promotion->ends_at
            ? $promotion->ends_at->copy()->setTimezone('Africa/Cairo')->format('Y-m-d\TH:i')
            : null;

        return view('admin.promotions.edit', [
            'promotion' => $promotion,
            'startsAtCairo' => $startsAtCairo,
            'endsAtCairo' => $endsAtCairo,
        ]);
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'headline' => 'required|string|max:255',
            'subheadline' => 'nullable|string',
            'cta_text' => 'required|string|max:100',
            'cta_url' => ['required', 'string', 'max:255', $this->safeCtaUrlRule()],
            'display_type' => 'required|in:top_bar,floating_modal,inline_card',
            'banner_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'banner_image_path' => ['nullable', 'string', 'max:255', $this->safeBannerPathRule()],
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'has_countdown' => 'nullable|boolean',
        ]);

        $bannerPath = $promotion->banner_image_path;
        if ($request->hasFile('banner_image')) {
            $bannerPath = $this->storeBannerImage($request->file('banner_image'));
        } elseif ($request->filled('banner_image_path')) {
            $bannerPath = $request->input('banner_image_path');
        }

        $startsAtUtc = ! empty($validated['starts_at'])
            ? Carbon::parse($validated['starts_at'], 'Africa/Cairo')->setTimezone('UTC')
            : null;

        $endsAtUtc = ! empty($validated['ends_at'])
            ? Carbon::parse($validated['ends_at'], 'Africa/Cairo')->setTimezone('UTC')
            : null;

        $oldData = $promotion->toArray();

        $promotion->update([
            'title' => $validated['title'],
            'headline' => $validated['headline'],
            'subheadline' => $validated['subheadline'] ?? null,
            'cta_text' => $validated['cta_text'],
            'cta_url' => $validated['cta_url'],
            'banner_image_path' => $bannerPath,
            'display_type' => $validated['display_type'],
            'is_active' => $request->boolean('is_active'),
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
            'has_countdown' => $request->boolean('has_countdown'),
        ]);

        $this->promotionService->clearCache();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'promotion_updated',
            'entity_type' => Promotion::class,
            'entity_id' => $promotion->id,
            'old_data' => $oldData,
            'new_data' => $promotion->fresh()->toArray(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion '{$promotion->title}' updated successfully.");
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $promotion->update([
            'is_active' => ! $promotion->is_active,
        ]);

        $this->promotionService->clearCache();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'promotion_toggled',
            'entity_type' => Promotion::class,
            'entity_id' => $promotion->id,
            'new_data' => ['is_active' => $promotion->is_active],
            'created_at' => now(),
        ]);

        $status = $promotion->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Promotion '{$promotion->title}' {$status}.");
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $title = $promotion->title;
        $id = $promotion->id;
        $oldData = $promotion->toArray();

        $promotion->delete();

        $this->promotionService->clearCache();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'promotion_deleted',
            'entity_type' => Promotion::class,
            'entity_id' => $id,
            'old_data' => $oldData,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion '{$title}' deleted successfully.");
    }

    public function preview(Promotion $promotion): View
    {
        return view('admin.promotions.preview', [
            'promotion' => $promotion,
        ]);
    }

    private function storeBannerImage(UploadedFile $image): string
    {
        $path = $image->store('promotions', 'public');
        $size = @getimagesize($image->getRealPath());

        Media::create([
            'filename' => $image->getClientOriginalName(),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $image->getMimeType(),
            'file_size' => $image->getSize(),
            'dimensions' => $size ? ['width' => $size[0], 'height' => $size[1]] : null,
            'alt_text' => null,
        ]);

        return $path;
    }

    private function safeCtaUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
                $fail('The promotion link must be a local path or an HTTPS URL.');

                return;
            }

            $isLocalPath = str_starts_with($value, '/') && ! str_starts_with($value, '//');
            $isHttpsUrl = filter_var($value, FILTER_VALIDATE_URL)
                && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https';

            if (! $isLocalPath && ! $isHttpsUrl) {
                $fail('The promotion link must be a local path or an HTTPS URL.');
            }
        };
    }

    private function safeBannerPathRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! preg_match('/^(?:media|promotions)\/[A-Za-z0-9_\/-]+\.(?:jpe?g|png|webp)$/i', $value)) {
                $fail('The banner must reference a public image in the media or promotions library.');
            }
        };
    }
}
