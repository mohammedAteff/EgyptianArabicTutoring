<?php

use App\Domains\Administration\Models\Administrator;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\FeedbackService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->instance('request', Request::create('http://localhost'));
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'bolt_landing_test' || $connection->getConfig('host') !== '127.0.0.1'
    || (string) $connection->getConfig('port') !== '3307' || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'bolt_landing_test') {
    throw new RuntimeException('Feedback concurrency worker refused an unverified database.');
}
app(DatabaseCapability::class)->assertMatchesReconciledEnvironment('mariadb', '10.11.18');
Http::preventStrayRequests();
Mail::fake();
Notification::fake();
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$barrier = $argv[2];
$allowed = str_replace('\\', '/', storage_path('framework/testing/feedback-'));
if (! str_starts_with(str_replace('\\', '/', $barrier), $allowed)) {
    throw new RuntimeException('Invalid feedback barrier.');
}
file_put_contents($barrier.'.'.getmypid().'.ready', 'ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Feedback barrier timed out.');
    }
    usleep(10000);
}
try {
    $actor = $job['actor_type'] === 'student' ? Student::withTrashed()->findOrFail($job['actor_id']) : Administrator::findOrFail($job['actor_id']);
    $result = match ($job['operation']) {
        'create' => app(FeedbackService::class)->create($actor, $job['payload'])->id,
        'mutate' => app(FeedbackService::class)->mutate($actor, $job['conversation_id'], $job['payload'])->id,
        'merge' => app(StudentMergeService::class)->merge($job['primary_id'], $job['secondary_id'], $actor->id)->id,
        'erase' => app(StudentPrivacyService::class)->anonymize($job['student_id'], $actor->id)->id,
    };
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
}
