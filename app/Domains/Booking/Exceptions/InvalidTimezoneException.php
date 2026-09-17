<?php

namespace App\Domains\Booking\Exceptions;

class InvalidTimezoneException extends \InvalidArgumentException
{
    public function __construct(string $timezone, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Invalid IANA timezone identifier: [{$timezone}]", $code, $previous);
    }
}
