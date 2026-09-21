<?php

namespace App\Services;

use App\Domains\Analytics\Services\AnalyticsService as DomainAnalyticsService;
use App\Models\Session;

class AnalyticsService extends DomainAnalyticsService
{
    public function startSession(?string $visitorToken = null, ?string $countryCode = null): Session
    {
        $domainSession = parent::startSession($visitorToken, $countryCode);

        return Session::find($domainSession->id);
    }

    public function recordSession(string $visitorToken, ?string $countryCode = null): Session
    {
        return $this->startSession($visitorToken, $countryCode);
    }
}
