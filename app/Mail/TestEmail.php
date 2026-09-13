<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Proof that email leaves the building.
 *
 * A real Mailable rather than a raw string so it goes through the same
 * rendering, from-address and transport a receipt does — a test that takes a
 * different path proves less than it appears to.
 */
class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $storeName, public readonly string $transport) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.$this->storeName);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.test', with: [
            'storeName' => $this->storeName,
            'transport' => $this->transport,
            'sentAt' => now()->toDayDateTimeString(),
        ]);
    }
}
