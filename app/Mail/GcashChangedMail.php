<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/** Sent when a landlord's GCash payout details change, so a hijacked account cannot redirect money unnoticed. */
class GcashChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Carbon $holdUntil)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your GCash payout details changed — AbangananHub',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.gcash-changed',
        );
    }
}
