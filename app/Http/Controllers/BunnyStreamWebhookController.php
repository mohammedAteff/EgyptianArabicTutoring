<?php

namespace App\Http\Controllers;

use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\LmsVideoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BunnyStreamWebhookController extends Controller
{
    public function __invoke(Request $request, LmsVideoService $videos): Response
    {
        abort_unless(strlen($request->getContent()) <= 4096, 413);
        $connection = VideoProviderConnection::query()->where('provider', 'bunny')->firstOrFail();
        $videos->webhook($connection, $request->getContent(), (string) $request->header('X-BunnyStream-Signature-Version'),
            (string) $request->header('X-BunnyStream-Signature-Algorithm'), (string) $request->header('X-BunnyStream-Signature'));

        return response('', 204, ['Cache-Control' => 'no-store']);
    }
}
