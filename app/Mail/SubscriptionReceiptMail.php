<?php

namespace App\Mail;

use App\Models\PaymentTransaction;
use App\Support\PdfReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PaymentTransaction $transaction,
        public string $subscriberName,
        public string $planName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Subscription payment successful - Receipt',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-receipt',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => PdfReceipt::render('pdfs.subscription-receipt', [
                    'transaction' => $this->transaction,
                    'subscriberName' => $this->subscriberName,
                    'planName' => $this->planName,
                ]),
                'subscription-receipt-'.$this->transaction->id.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
