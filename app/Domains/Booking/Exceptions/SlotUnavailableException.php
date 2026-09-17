<?php

namespace App\Domains\Booking\Exceptions;

class SlotUnavailableException extends \Exception
{
    public function __construct(string $message = 'This time was just taken. Please choose another available time.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
