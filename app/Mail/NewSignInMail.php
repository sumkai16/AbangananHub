<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells an admin or landlord about a sign-in from a browser they have not used lately. Plain values only, no geo-IP. */
class NewSignInMail extends Mailable
{
    use Queueable;

    public string $resetUrl;

    public function __construct(
        public string $when,
        public string $device,
        public ?string $ip,
    ) {
        $this->resetUrl = route('password.request');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New sign-in to your AbangananHub account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new-sign-in');
    }
}
