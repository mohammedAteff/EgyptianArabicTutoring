<?php

namespace App\Domains\Audit\Services;

use Illuminate\Session\DatabaseSessionHandler;

class PrivacyDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function ipAddress(): ?string
    {
        return null;
    }
}
