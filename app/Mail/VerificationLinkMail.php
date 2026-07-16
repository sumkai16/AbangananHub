<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerificationLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;

    public function __construct(public User $user)
    {
        $this->verificationUrl = URL::temporarySignedRoute(
            'landlord.verification.viaemail',
            now()->addHour(),
            ['user' => $user->user_id]
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Continue Your Landlord Verification — AbangananHub',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-link',
        );
    }
}