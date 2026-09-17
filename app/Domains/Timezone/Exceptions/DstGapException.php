<?php

namespace App\Domains\Timezone\Exceptions;

use App\Domains\Booking\Exceptions\SlotUnavailableException;

class DstGapException extends SlotUnavailableException
{
    public function __construct(string $localTime, string $timezone, ?\Throwable $previous = null)
    {
        parent::__construct(
            "The requested local time '{$localTime}' does not exist in timezone '{$timezone}' due to daylight saving time transition (spring-forward gap).",
            0,
            $previous
        );
    }
}
