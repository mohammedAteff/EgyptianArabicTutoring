<?php

namespace App\Services;

use App\Domains\Booking\Actions\SyncDiagnosticSessionType;
use App\Domains\Booking\Models\SessionType;

class SessionTypeSyncService
{
    public function sync(): SessionType
    {
        return app(SyncDiagnosticSessionType::class)->execute();
    }
}
