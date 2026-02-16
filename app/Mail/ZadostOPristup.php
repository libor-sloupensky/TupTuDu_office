<?php

namespace App\Mail;

use App\Models\Firma;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ZadostOPristup extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $zadatelJmeno,
        public string $zadatelEmail,
        public Firma $firma,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "TupTuDu - Žádost o přístup do firmy {$this->firma->nazev}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.zadost',
        );
    }
}
