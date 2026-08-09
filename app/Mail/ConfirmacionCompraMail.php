<?php

namespace App\Mail;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmacionCompraMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Venta $venta)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de tu compra #' . $this->venta->id . ' - KikiiTick',
        );
    }

    public function content(): Content
    {
        $primerDetalle = $this->venta->detalles->first();
        $evento = $primerDetalle?->boletoEvento?->evento;

        return new Content(
            view: 'emails.confirmacion_compra',
            with: [
                'venta'   => $this->venta,
                'accesos' => $this->venta->accesos,
                'evento'  => $evento,
                'teatro'  => $evento?->teatro,
            ],
        );
    }
}
