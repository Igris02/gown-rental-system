<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One simple, plain black and white email used for every reservation message.
 */
class ReservationMail extends Mailable
{
    /**
     * @param string[] $lines
     * @param array{name:string, phone:?string, address:?string} $shop
     */
    public function __construct(
        public string $mailSubject,
        public string $heading,
        public array $lines,
        public array $shop,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation',
            text: 'emails.reservation-text',
        );
    }
}
