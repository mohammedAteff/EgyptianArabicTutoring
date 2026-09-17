<?php

namespace App\Domains\Booking\Exceptions;

class InvalidBookingStatusTransitionException extends \DomainException
{
    public function __construct(string $message = 'The requested booking status transition is invalid.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
