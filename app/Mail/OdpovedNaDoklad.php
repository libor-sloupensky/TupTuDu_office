<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OdpovedNaDoklad extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $bodyText,
        public string $originalSubject,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re: ' . $this->originalSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.odpoved-doklad',
        );
    }
}
