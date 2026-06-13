<?php

namespace App\Mail;

use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AppNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[RuangASN] ' . $this->notification->title);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notification',
            with: [
                'title'     => $this->notification->title,
                'body'      => $this->notification->body,
                'actionUrl' => $this->notification->data['url'] ?? null,
            ],
        );
    }
}
