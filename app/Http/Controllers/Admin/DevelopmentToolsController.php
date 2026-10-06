<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\System\Models\DevelopmentDataOperation;
use App\Domains\System\Services\DevelopmentDataCatalog;
use App\Domains\System\Services\DevelopmentDataFiles;
use App\Domains\System\Services\DevelopmentToolsAccess;
use App\Domains\System\Services\DevelopmentToolsService;
use App\Domains\System\Services\ManagedBackupCatalog;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DevelopmentOperationRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DevelopmentToolsController extends Controller
{
    public function __construct(private DevelopmentToolsAccess $access) {}

    public function index(Request $request, ManagedBackupCatalog $backups): Response
    {
        $actor = $this->actor($request);

        return $this->page('admin.system.development-tools', ['modules' => DevelopmentDataCatalog::LABELS,
            'domains' => DevelopmentDataCatalog::PHRASES, 'snapshots' => $backups->inventory(),
            'history' => DevelopmentDataOperation::query()->where('administrator_id', $actor->id)->latest('id')->limit(20)->get()]);
    }

    public function preview(DevelopmentOperationRequest $request, DevelopmentToolsService $tools): Response
    {
        $values = $request->validated();
        $scope = $values['scope'] ?? [];
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        foreach (['date_from', 'date_to'] as $date) {
            if (! empty($scope['filters'][$date])) {
                $boundary = CarbonImmutable::parse($scope['filters'][$date], $timezone)->startOfDay();
                $scope['filters'][$date] = ($date === 'date_to' ? $boundary->addDay() : $boundary)->utc()->toDateTimeString();
            }
        }
        $result = $tools->preview($this->actor($request), $request->session()->getId(), $values['type'], $values['domain'], $scope);

        return $this->previewPage($result);
    }

    public function upload(Request $request, DevelopmentToolsService $tools): Response
    {
        $actor = $this->actor($request);
        $request->validate(['archive' => ['required', 'file', 'mimes:zip', 'max:'.intdiv((int) config('development_tools.max_archive_bytes'), 1024)]]);
        $result = $tools->upload($actor, $request->session()->getId(), $request->file('archive'));

        return $this->previewPage($result);
    }

    public function selectImport(Request $request, DevelopmentToolsService $tools): Response
    {
        $actor = $this->actor($request);
        $values = $request->validate(['operation_token' => ['required', 'string', 'size:64'], 'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in(array_keys(DevelopmentDataCatalog::MODULES))]]);

        return $this->previewPage($tools->selectImport($actor, $request->session()->getId(), $values['operation_token'], $values['modules']));
    }

    public function confirm(Request $request, DevelopmentToolsService $tools): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['operation_token' => ['required', 'string', 'size:64'], 'password' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:6'], 'recovery_code' => ['nullable', 'string', 'max:128'],
            'phrase' => ['required', 'string', 'max:100']]);
        $summary = $tools->confirm($actor, $request->session()->getId(), $values['operation_token'], $values['password'],
            $values['code'] ?? null, $values['recovery_code'] ?? null, $values['phrase']);
        if ($summary['type'] === 'reset' && $summary['domain'] === 'students') {
            Auth::guard('student')->logout();
            $request->session()->forget(['student_id', 'student_authenticated_at', 'student_auth_expires_at', 'student_display_timezone']);
        }

        return redirect()->route('admin.development-tools.show', $summary['operation_id']);
    }

    public function show(Request $request, DevelopmentDataOperation $operation): Response
    {
        $actor = $this->actor($request);
        abort_unless($operation->administrator_id === $actor->id, 404);

        return $this->page('admin.system.development-completion', ['operation' => $operation]);
    }

    public function download(Request $request, DevelopmentDataOperation $operation, DevelopmentDataFiles $files): Response
    {
        $actor = $this->actor($request);
        $exportPrefix = preg_quote((string) config('development_tools.directory').'/exports/', '~');
        abort_unless($operation->administrator_id === $actor->id && $operation->status === 'completed'
            && is_string($operation->archive_path) && preg_match('~^'.$exportPrefix.'[a-f0-9-]{36}\.zip$~D', $operation->archive_path), 404);
        abort_unless($files->disk()->exists($operation->archive_path)
            && hash_file('sha256', $files->disk()->path($operation->archive_path)) === ($operation->summary['archive_sha256'] ?? null), 404);

        return response()->download($files->disk()->path($operation->archive_path), 'development-export-'.$operation->id.'.zip',
            ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        if (! $actor instanceof Administrator) {
            abort(403);
        }

        return $this->access->authorize($actor);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    /** @param array<string, mixed> $result */
    private function previewPage(array $result): Response
    {
        $operation = $result['operation'];
        $phrase = $operation->type === 'reset' ? DevelopmentDataCatalog::PHRASES[$operation->domain]
            : ($operation->type === 'import' ? 'RESTORE MISSING DEVELOPMENT DATA' : 'EXPORT DEVELOPMENT DATA');

        return $this->page('admin.system.development-preview', $result + ['phrase' => $phrase, 'moduleLabels' => DevelopmentDataCatalog::LABELS]);
    }
}
