<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\Games\Models\Game;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        $games = Game::query()->orderBy('sort_order')->get();

        return view('admin.games.index', [
            'title' => 'Learning Games Management',
            'games' => $games,
        ]);
    }

    public function create(): View
    {
        return view('admin.games.create', [
            'title' => 'New Educational Game',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:games,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'badge' => ['nullable', 'string', 'max:64'],
            'target_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_path' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:available,coming_soon,disabled,archived,draft'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['title']);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['featured'] = $request->boolean('featured', false);

        $game = Game::create($validated);

        // Create initial revision #1
        ContentRevision::create([
            'revisable_type' => Game::class,
            'revisable_id' => $game->id,
            'revision_number' => 1,
            'title' => $game->title,
            'content' => [
                'slug' => $game->slug,
                'description' => $game->description,
                'badge' => $game->badge,
                'target_url' => $game->target_url,
                'thumbnail_path' => $game->thumbnail_path,
                'status' => $game->status,
                'sort_order' => $game->sort_order,
                'featured' => $game->featured,
            ],
            'created_by_id' => Auth::id(),
            'status' => $game->status === 'available' ? 'published' : 'draft',
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'game_created',
            'entity_type' => Game::class,
            'entity_id' => $game->id,
            'new_data' => $game->toArray(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.games.index')->with('success', "Game '{$game->title}' created.");
    }

    public function edit(Game $game): View
    {
        $draftRevision = $game->revisions()->where('status', 'draft')->latest('id')->first();

        return view('admin.games.edit', [
            'title' => 'Edit Game — '.$game->title,
            'game' => $game,
            'draftRevision' => $draftRevision,
        ]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('games', 'slug')->ignore($game->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'badge' => ['nullable', 'string', 'max:64'],
            'target_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_path' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:available,coming_soon,disabled,archived,draft'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        if (! empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $validated['featured'] = $request->boolean('featured', false);

        $action = $request->input('action');
        $isDraftAction = $action === 'draft' || ($action !== 'publish' && in_array($game->status, ['available', 'coming_soon'], true) && $validated['status'] === 'draft');

        if ($action === 'publish') {
            $validated['status'] = 'available';
        }

        if ($isDraftAction && in_array($game->status, ['available', 'coming_soon'], true)) {
            $nextRevision = ($game->revisions()->max('revision_number') ?? 0) + 1;
            ContentRevision::create([
                'revisable_type' => Game::class,
                'revisable_id' => $game->id,
                'revision_number' => $nextRevision,
                'title' => $validated['title'],
                'content' => [
                    'slug' => $validated['slug'] ?? $game->slug,
                    'description' => $validated['description'] ?? null,
                    'badge' => $validated['badge'] ?? null,
                    'target_url' => $validated['target_url'] ?? null,
                    'thumbnail_path' => $validated['thumbnail_path'] ?? null,
                    'status' => 'draft',
                    'sort_order' => $validated['sort_order'] ?? 0,
                    'featured' => $validated['featured'] ?? false,
                ],
                'created_by_id' => Auth::id(),
                'status' => 'draft',
            ]);

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'game_draft_saved',
                'entity_type' => Game::class,
                'entity_id' => $game->id,
                'new_data' => [
                    'revision_number' => $nextRevision,
                    'title' => $validated['title'],
                ],
                'created_at' => now(),
            ]);

            return redirect()->route('admin.games.edit', $game)
                ->with('success', "Draft revision #{$nextRevision} saved. The live game remains untouched.");
        }

        if ($action === 'publish' && $validated['status'] === 'draft') {
            $validated['status'] = 'available';
        }

        $prev = $game->toArray();
        $game->update($validated);

        $game->revisions()->where('status', 'draft')->update(['status' => 'archived']);

        $nextRevision = ($game->revisions()->max('revision_number') ?? 0) + 1;
        ContentRevision::create([
            'revisable_type' => Game::class,
            'revisable_id' => $game->id,
            'revision_number' => $nextRevision,
            'title' => $game->title,
            'content' => [
                'slug' => $game->slug,
                'description' => $game->description,
                'badge' => $game->badge,
                'target_url' => $game->target_url,
                'thumbnail_path' => $game->thumbnail_path,
                'status' => $game->status,
                'sort_order' => $game->sort_order,
                'featured' => $game->featured,
            ],
            'created_by_id' => Auth::id(),
            'status' => $game->status === 'available' ? 'published' : 'draft',
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'game_updated',
            'entity_type' => Game::class,
            'entity_id' => $game->id,
            'previous_data' => $prev,
            'new_data' => $game->toArray(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.games.index')->with('success', 'Game settings updated.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        $prev = $game->toArray();
        $gameTitle = $game->title;
        $game->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'game_deleted',
            'entity_type' => Game::class,
            'entity_id' => $game->id,
            'previous_data' => $prev,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.games.index')->with('success', "Game '{$gameTitle}' removed.");
    }

    public function discardDraft(Game $game): RedirectResponse
    {
        $game->revisions()->where('status', 'draft')->delete();

        return redirect()->route('admin.games.edit', $game)
            ->with('success', 'Draft revision discarded. Reverted to live published values.');
    }
}
