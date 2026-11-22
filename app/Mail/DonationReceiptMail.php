<?php

namespace App\Mail;

use App\Models\Donation;
use App\Support\PdfReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonationReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Donation $donation,
        public string $campaignName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank you for your donation - Receipt',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.donation-receipt',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => PdfReceipt::render('pdfs.donation-receipt', [
                    'donation' => $this->donation,
                    'campaignName' => $this->campaignName,
                ]),
                'donation-receipt-'.$this->donation->id.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
