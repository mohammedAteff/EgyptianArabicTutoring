<?php

namespace App\Domains\Timezone\Exceptions;

use App\Domains\Booking\Exceptions\SlotUnavailableException;

class DstFoldAmbiguityException extends SlotUnavailableException
{
    public function __construct(string $localTime, string $timezone, ?\Throwable $previous = null)
    {
        parent::__construct(
            "The requested local time '{$localTime}' is ambiguous in timezone '{$timezone}' due to daylight saving time transition (fall-back repeated hour).",
            0,
            $previous
        );
    }
}
