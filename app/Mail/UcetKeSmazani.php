<?php

namespace App\Mail;

use App\Models\User;
use App\Services\SmazaniUctu;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Zpráva o tom, že účet byl uzavřen a čeká na smazání.
 *
 * Posílá se hlavně kvůli případu, kdy o smazání požádal někdo jiný než majitel
 * účtu — ten se to musí dozvědět a stihnout to vrátit.
 */
class UcetKeSmazani extends Mailable
{
    use Queueable, SerializesModels;

    public string $obnoveniUrl;
    public string $smazaniK;

    public function __construct(public User $user)
    {
        $this->obnoveniUrl = url('/ucet/obnovit/' . $user->obnoveni_token);
        $this->smazaniK = $user->smazani_k->timezone('Europe/Prague')->format('j. n. Y H:i');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TupTuDu - účet je uzavřený a za ' . SmazaniUctu::DNI_LHUTY . ' dní se smaže',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ucet-ke-smazani');
    }
}
