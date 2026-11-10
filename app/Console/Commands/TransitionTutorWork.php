<?php

namespace App\Console\Commands;

use App\Domains\Reporting\Services\TutorWorkTransition;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use RuntimeException;

class TransitionTutorWork extends Command
{
    protected $signature = 'tutor-work:transition {--adopt=* : Verified legacy entry IDs to adopt} {--preserve=* : Legacy entry IDs to retain outside shared scope} {--cutover-active : Explicitly finalize every legacy active timer for review} {--expect= : Exact preflight evidence digest} {--apply : Apply the reviewed decision on the explicitly approved target}';

    protected $description = 'Read-only Tutor Work upgrade preflight, or explicitly adopt/preserve a complete bounded legacy set';

    public function handle(TutorWorkTransition $transition): int
    {
        try {
            $result = $this->option('apply') ? $transition->convert($this->option('adopt'), $this->option('preserve'), (bool) $this->option('cutover-active'), (string) $this->option('expect')) : $transition->preflight();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (QueryException) {
            $this->error('The database operation failed. No data conversion committed; inspect schema/migration status and rerun read-only preflight. Private SQL bindings are not reported.');
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }
}
