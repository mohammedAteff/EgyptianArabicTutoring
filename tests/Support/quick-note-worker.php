<?php

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Student\QuickNoteController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->instance('request', Request::create('http://localhost'));
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if (! $app->environment('testing') || $connection->getDatabaseName() !== 'bolt_landing_test' || $connection->getConfig('host') !== '127.0.0.1' || (string) $connection->getConfig('port') !== '3307' || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'bolt_landing_test') {
    throw new RuntimeException('Concurrency worker refused an unverified database.');
}
app(DatabaseCapability::class)->assertMatchesReconciledEnvironment('mariadb', '10.11.18');
$request = Request::create('/student/quick-notes', 'POST', ['creation_id' => $argv[2], 'body' => 'Concurrent draft']);
$request->attributes->set('student', Student::query()->findOrFail($argv[1]));
$response = app(QuickNoteController::class)->store($request, app(TeachingRecordService::class), app(AuditLogService::class));
echo $response->getContent();
