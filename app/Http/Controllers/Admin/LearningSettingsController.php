<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\LmsSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LearningSettingsController extends Controller
{
    public function __construct(private LmsSettings $settings) {}

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);
        Gate::forUser($actor)->authorize('manageSettings', Course::class);

        return $actor;
    }

    public function index(Request $request): Response
    {
        $this->actor($request);

        return response()->view('admin.lms.operations.settings', ['title' => 'LMS settings', 'document' => $this->settings->document(),
            'profiles' => ProtectionProfile::query()->where('active', true)->where('secure_playback', true)->where('downloads_allowed', false)->where('watermark', true)->whereNotNull('device_limit')->whereNotNull('stream_limit')->get(),
            'provider' => VideoProviderConnection::query()->where('provider', 'bunny')->first()], 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function update(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $version = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        $this->settings->save($actor, $request->only(array_keys(LmsSettings::DEFAULTS)), (int) $version['version']);

        return to_route('admin.lms.settings')->with('success', 'LMS defaults and attention thresholds saved.');
    }
}
