<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StudentSecondaryEmailVerification extends Mailable
{
    public function __construct(public string $verificationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your secondary student email');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-secondary-verification');
    }
}
