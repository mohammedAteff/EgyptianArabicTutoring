<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Reporting\Services\TutorWorkContext;
use App\Domains\Reporting\Services\TutorWorkExport;
use App\Domains\Reporting\Services\TutorWorkReport;
use App\Domains\Reporting\Services\TutorWorkService;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TutorWorkController extends Controller
{
    private function actor(Request $request): int
    {
        $actor = (int) $request->user('web')?->id;
        app(TutorWorkService::class)->authorize($actor);

        return $actor;
    }

    public function index(Request $request, TutorWorkReport $read): Response
    {
        $actor = $this->actor($request);
        $workspace = app(TutorWorkWorkspace::class)->find();
        if (! $workspace || $workspace->transition_state !== 'ready') {
            return response()->view('admin.tutor-insights.work-setup', ['migrationPending' => $workspace === null || $workspace->transition_state === 'schema_pending'])->header('Cache-Control', 'private, no-store');
        }
        $context = TutorWorkContext::fromRequest($request, $actor);
        $view = $request->validate(['view' => ['nullable', 'in:overview,history,controls']])['view'] ?? 'overview';
        $data = ['context' => $context, 'view' => $view, 'timer' => app(TutorWorkService::class)->state($actor)];
        if ($view === 'overview') {
            $data['summary'] = $read->summary($context);
            $data['allocations'] = LazyCollection::make(fn () => $read->allocations($context))->filter(fn (array $row): bool => $context->matches($row['category'], $row['source']))->take(20)->collect();
        }
        if ($view === 'history') {
            $data['entries'] = $read->history($context)->paginate(20)->withQueryString();
            $data['bookings'] = $context->matches('teaching', 'recorded_session') && in_array($context->state, ['all', 'accepted'], true) ? $read->placedSources($context)->where('source', 'recorded_session')->paginate(20, ['*'], 'bookings_page')->withQueryString() : null;
        }

        return response()->view('admin.tutor-insights.work', $data)->header('Cache-Control', 'private, no-store');
    }

    public function state(Request $request): Response
    {
        $actor = (int) $request->user('web')?->id;
        app(TutorWorkService::class)->authorize($actor);
        $workspace = app(TutorWorkWorkspace::class)->find();
        $state = $workspace && $workspace->transition_state === 'ready' ? app(TutorWorkService::class)->state($actor) : ['server_at' => now('UTC')->startOfSecond()->toIso8601String(), 'timer' => null, 'activation_pending' => $workspace === null, 'transition_pending' => $workspace !== null];

        return response()->json($state + ['actor_id' => $actor, 'workspace_id' => $workspace?->id, 'context_version' => TutorWorkWorkspace::PROTOCOL, 'csrf' => csrf_token()])->header('Cache-Control', 'private, no-store');
    }

    public function mutate(Request $request, ?int $entry = null): Response
    {
        $actor = $this->actor($request);
        $workspace = app(TutorWorkWorkspace::class)->ready();
        abort_unless($request->input('context_version') === TutorWorkWorkspace::PROTOCOL
            && (string) $request->input('actor_context') === (string) $actor
            && (string) $request->input('workspace_context') === (string) $workspace->id, 409, 'The work interface or signed-in account changed. Refresh the interface before submitting a new command.');
        $action = $request->validate(['action' => ['required', 'in:start,manual,pause,resume,stop,correct,confirm,exclude']])['action'];
        abort_if(($entry === null) !== in_array($action, ['start', 'manual'], true), 404);
        $authority = app(AdministratorTwoFactorService::class)->fingerprint($request->user('web'));
        $result = app(TutorWorkService::class)->mutate($actor, $action, $entry, $request->all(), $authority);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $entry): Response
    {
        $actor = $this->actor($request);
        $service = app(TutorWorkService::class);
        $record = $service->shared($actor, $entry);

        $raw = DB::table('tutor_work_segments')->where('entry_id', $entry)->orderBy('id')->get();
        $revisions = DB::table('tutor_work_revisions')->where('workspace_id', $record->workspace_id)->where('entry_id', $entry)->orderByDesc('id')->paginate(20);
        $actorIds = $raw->pluck('opened_by')->merge($raw->pluck('closed_by'))->merge($revisions->pluck('actor_id'))->push($record->created_by)->filter()->unique();
        $actors = Administrator::whereIn('id', $actorIds)->pluck('name', 'id');

        return response()->view('admin.tutor-insights.work-detail', ['entry' => $record, 'effective' => $service->snapshot($record), 'seconds' => $service->seconds($record), 'raw' => $raw, 'revisions' => $revisions, 'actors' => $actors])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, TutorWorkExport $exports): Response
    {
        $context = TutorWorkContext::fromRequest($request, $this->actor($request));
        try {
            return $exports->download($context, $request->input('dataset', 'summary') ?: 'summary', $request->input('format', 'csv') ?: 'csv');
        } catch (ValidationException $exception) {
            if ($request->expectsJson() || ! isset($exception->errors()['format'])) {
                throw $exception;
            }

            return redirect()->route('admin.tutor-work.index', $context->filters() + ['dataset' => $request->input('dataset', 'summary')])->withErrors($exception->errors());
        }
    }
}
