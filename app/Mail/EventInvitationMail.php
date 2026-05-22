<?php

namespace App\Mail;

use App\Models\EventInvitation;
use Illuminate\Mail\Mailable;

class EventInvitationMail extends Mailable
{
    public function __construct(public EventInvitation $invitation) {}

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(view: 'emails.event-invitation');
    }

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(subject: 'Invitation');
    }
}
