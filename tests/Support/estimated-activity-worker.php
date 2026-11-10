<?php

use App\Domains\Administration\Models\Administrator;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Reporting\Services\EstimatedActivityGate;
use App\Domains\Reporting\Services\EstimatedActivityLifecycle;
use App\Domains\Reporting\Services\EstimatedActivityService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->instance('request', Request::create('http://localhost'));
$app->make(Kernel::class)->bootstrap();
if (! app(EstimatedActivityGate::class)->isolated()) {
    throw new RuntimeException('Activity worker refused an unverified isolated database/runtime.');
}
app(DatabaseCapability::class)->assertMatchesReconciledEnvironment('mariadb', '10.11.18');
$jobPath = realpath($argv[1]);
$root = str_replace('\\', '/', storage_path()).'/';
if (! $jobPath || ! str_starts_with(str_replace('\\', '/', $jobPath), $root) || ! str_ends_with($jobPath, '.job.json')) {
    throw new RuntimeException('Activity worker requires a private job inside its isolated storage.');
}
$job = json_decode(file_get_contents($jobPath), true, flags: JSON_THROW_ON_ERROR);
$barrier = storage_path('concurrent-release');
Carbon::setTestNow($job['now']);
CarbonImmutable::setTestNow($job['now']);
config(['estimated_activity.mode' => 'synthetic', 'estimated_activity.run_id' => $job['run']]);
Http::preventStrayRequests();
Mail::fake();
Notification::fake();
$request = Request::create('http://localhost/'.$job['domain'].'/estimated-activity/checkpoint', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($job['body'] ?? [], JSON_THROW_ON_ERROR));
$session = app('session')->driver();
$session->setId($job['session_id']);
$session->start();
$session->replace($job['session']);
$request->setLaravelSession($session);
$app->instance('request', $request);
$principal = $job['domain'] === 'student' ? Student::findOrFail($job['principal']) : Administrator::findOrFail($job['principal']);
Auth::guard($job['domain'] === 'student' ? 'student' : 'web')->setUser($principal);
$request->setUserResolver(fn (?string $guard = null) => Auth::guard($guard)->user());
file_put_contents($barrier.'.'.getmypid().'.ready', 'ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Activity worker barrier timed out.');
    }
    usleep(10000);
}
try {
    if ($job['action'] === 'checkpoint') {
        $result = app(EstimatedActivityService::class)->checkpoint($request, $job['domain']);
    } elseif ($job['action'] === 'merge') {
        app(StudentMergeService::class)->merge($job['primary'], $principal->id);
        $result = ['merged' => true];
    } elseif ($job['action'] === 'erase') {
        app(StudentPrivacyService::class)->anonymize($principal->id);
        $result = ['erased' => true];
    } else {
        DB::transaction(function () use ($principal): void {
            $principal::query()->whereKey($principal->id)->lockForUpdate()->firstOrFail();
            app(EstimatedActivityLifecycle::class)->revoke($principal);
        }, 5);
        $result = ['revoked' => true];
    }
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
} catch (ModelNotFoundException) {
    echo json_encode(['status' => 404], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Private fixture secrets and SQL are deliberately absent from worker output.
    echo json_encode(['status' => 500, 'exception' => $exception::class], JSON_THROW_ON_ERROR);
}
