<?php

namespace App\Domains\Booking\Exceptions;

class BookingPolicyViolationException extends \DomainException
{
    public function __construct(string $message = 'This action violates the booking policy.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
