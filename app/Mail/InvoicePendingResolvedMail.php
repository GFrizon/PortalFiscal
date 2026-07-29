<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePendingResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public User $submitter
    ) {
        $this->invoice->loadMissing(['businessUnit:id,name', 'submitter:id,name,email']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pendencia respondida na nota '.$this->invoice->protocol
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.pending-resolved'
        );
    }
}
