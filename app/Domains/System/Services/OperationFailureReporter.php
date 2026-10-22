<?php

namespace App\Domains\System\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class OperationFailureReporter
{
    public function report(Throwable $exception, string $operation): void
    {
        Log::error('Backend operation failed.', ['operation' => $operation, 'exception_type' => $exception::class]);
    }
}
