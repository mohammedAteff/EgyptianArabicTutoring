<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResourceVerificationPinMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $pin
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Resource Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resource-verification-pin',
        );
    }
}
