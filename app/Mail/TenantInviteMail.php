<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A landlord added this tenant by hand and invites them to set a password (App\Support\TenantInvites). */
class TenantInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $inviteUrl;

    public function __construct(public User $tenant, public User $landlord, string $token)
    {
        $this->inviteUrl = route('invite.show', ['token' => $token, 'email' => $tenant->email]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->landlord->publicName().' added you on AbangananHub');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tenant-invite');
    }
}
