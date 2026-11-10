<?php

use App\Domains\Administration\Models\Administrator;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Reporting\Services\TutorWorkService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->instance('request', Request::create('http://localhost'));
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'bolt_landing_test' || $connection->getConfig('host') !== '127.0.0.1'
    || (string) $connection->getConfig('port') !== '3307' || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'bolt_landing_test') {
    throw new RuntimeException('Tutor work concurrency worker refused an unverified database.');
}
app(DatabaseCapability::class)->assertMatchesReconciledEnvironment('mariadb', '10.11.18');
Http::preventStrayRequests();
Mail::fake();
Notification::fake();
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$barrier = $argv[2];
$allowed = str_replace('\\', '/', storage_path('framework/testing/tutor-work-'));
if (! str_starts_with(str_replace('\\', '/', $barrier), $allowed)) {
    throw new RuntimeException('Invalid tutor work barrier.');
}
if (($job['kind'] ?? '') === 'stale_revoke') {
    DB::beginTransaction();
    DB::table('tutor_work_segments')->get();
}
file_put_contents($barrier.'.'.getmypid().'.ready', 'ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Tutor work barrier timed out.');
    }
    usleep(10000);
}
try {
    $actor = Administrator::findOrFail($job['owner']);
    if (($job['kind'] ?? '') === 'stale_revoke') {
        $deadline = microtime(true) + 15;
        while (! is_file($barrier.'.resumed')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Takeover did not reach the committed barrier.');
            }
            usleep(10000);
        }
        $actor->update(['password' => 'StaleSnapshotSyntheticPassword123!']);
        DB::commit();
        $result = ['revoked' => true];
    } elseif (($job['kind'] ?? '') === 'takeover') {
        $service = app(TutorWorkService::class);
        $service->mutate($actor->id, 'pause', $job['id'], array_replace($job['payload'], ['action' => 'pause', 'version' => 1, 'operation_id' => (string) Str::uuid()]));
        $result = $service->mutate($actor->id, 'resume', $job['id'], $job['payload']);
        file_put_contents($barrier.'.resumed', 'committed');
    } elseif (($job['kind'] ?? 'mutate') === 'revoke') {
        $actor->update(['password' => 'RevokedSyntheticPassword123!']);
        $result = ['revoked' => true];
    } else {
        $result = app(TutorWorkService::class)->mutate($actor->id, $job['action'], $job['id'] ?? null, $job['payload'], $job['authority'] ?? null);
    }
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (HttpResponseException $exception) {
    echo json_encode(['status' => $exception->getResponse()->getStatusCode()], JSON_THROW_ON_ERROR);
} catch (HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
}
