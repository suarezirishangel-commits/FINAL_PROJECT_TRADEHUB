<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $reference,
        public array $items,
        public float $total,
        public string $paymentMethod,
        public string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->siteName.' order confirmation '.$this->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
        );
    }
}
