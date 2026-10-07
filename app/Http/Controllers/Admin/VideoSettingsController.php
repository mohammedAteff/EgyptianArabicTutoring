<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\LmsVideoSettings;
use App\Domains\Lms\Services\VideoDeliveryProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VideoSettingsController extends Controller
{
    public function __construct(private LmsVideoSettings $settings) {}

    public function index(Request $request): Response
    {
        $this->actor($request);

        return response()->view('admin.lms.video.settings', ['title' => 'Protected video settings', 'connection' => VideoProviderConnection::query()->where('provider', 'bunny')->first(),
            'profiles' => ProtectionProfile::query()->orderBy('id')->get()], 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:0'], 'domains' => ['required', 'string', 'max:2600']]);
        $this->settings->save($this->actor($request), $request->only(['library_id', 'cdn_hostname', 'enabled', 'api_key', 'read_only_key', 'signing_key', 'account_key'])
            + ['allowed_domains' => array_values(array_filter(array_map('trim', explode(',', strtolower($values['domains'])))))], (int) $values['version']);

        return redirect()->route('admin.lms.video.settings')->with('success', 'Settings saved. Verify provider protection before releasing media.');
    }

    public function verify(Request $request, VideoDeliveryProvider $provider): RedirectResponse
    {
        $this->actor($request);
        $connection = $this->settings->connection();
        $version = $connection->lock_version;
        $provider->verifyProtection($connection, strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)));
        VideoProviderConnection::query()->whereKey($connection->id)->where('lock_version', $version)->update(['last_verified_at' => now('UTC')]);

        return redirect()->route('admin.lms.video.settings')->with('success', 'Provider protection settings verified. Every playback authorization rechecks them.');
    }

    public function profile(Request $request, ProtectionProfile $profile): RedirectResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $this->settings->profile($this->actor($request), $profile, $request->only(['device_limit', 'stream_limit', 'token_seconds', 'lease_seconds', 'heartbeat_seconds', 'watermark', 'drm_mode', 'active']), (int) $values['version']);

        return redirect()->route('admin.lms.video.settings')->with('success', 'Protection profile updated. Existing players must restart after the policy changes.');
    }

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);
        $this->settings->authorize($actor);

        return $actor;
    }
}
