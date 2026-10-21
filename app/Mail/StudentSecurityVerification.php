<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StudentSecurityVerification extends Mailable
{
    public function __construct(public string $verificationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your student authenticator setup');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-security-verification');
    }
}
