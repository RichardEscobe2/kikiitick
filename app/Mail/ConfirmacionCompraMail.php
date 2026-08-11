<?php

namespace App\Mail;

use App\Models\Venta;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
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
        $accesosConQr = $this->venta->accesos->map(fn ($acceso) => [
            'acceso'  => $acceso,
            'qr_data_uri' => $this->generarQrDataUri($acceso->token_qr),
        ]);

        return new Content(
            view: 'emails.confirmacion_compra',
            with: [
                'venta'           => $this->venta,
                'accesosConQr'    => $accesosConQr,
                'evento'          => $evento,
                'teatro'          => $evento?->teatro,
                'confirmacionUrl' => $this->urlConfirmacion(),
            ],
        );
    }

    private function generarQrDataUri(string $token): string
    {
        $qrCode = new QrCode(
            data: $token,
            size: 220,
            margin: 10,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            foregroundColor: new Color(30, 27, 75), // indigo-950, consistente con la marca
        );

        return (new PngWriter())->write($qrCode)->getDataUri();
    }

    private function urlConfirmacion(): string
    {
        $frontendUrl = trim((string) config('app.frontend_url'));

        if ($frontendUrl !== '' && !preg_match('#^https?://#i', $frontendUrl)) {
            $frontendUrl = 'https://' . $frontendUrl;
        }

        return rtrim($frontendUrl, '/') . '/confirmacion/' . $this->venta->id;
    }
}
