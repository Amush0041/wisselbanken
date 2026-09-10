<?php

namespace App\Mail;

use App\Models\Rbac\OrgInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrgInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $inviteUrl;
    public string $orgName;
    public string $inviterName;
    public string $roleName;
    public string $recipientName;

    public function __construct(OrgInvite $invite)
    {
        $this->inviteUrl     = url('/register/invite/' . $invite->token);
        $this->orgName       = $invite->organization?->name ?? 'your organisation';
        $this->inviterName   = $invite->invitedBy?->name ?? 'A team member';
        $this->roleName      = $invite->role?->name ?? 'team member';
        $this->recipientName = $invite->name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to join ' . $this->orgName . ' on Wisselbanken',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.org-invite',
        );
    }
}
