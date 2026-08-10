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

        // 🛡️ QR embebido como data URI (base64), no como adjunto CID: más simple
        // de generar dentro del Mailable sin manipular el mensaje Symfony
        // subyacente, y con soporte amplio en clientes de correo modernos
        // (verificado en Mailpit para esta tarea). Codifica exactamente
        // `token_qr` — el mismo valor que ya usa el QR del lado cliente en
        // ConfirmacionCompra.vue/MisBoletos.vue — nunca `hash_seguridad`
        // (ese campo nunca sale del servidor, ver CompraService::emitirAccesos()).
        $accesosConQr = $this->venta->accesos->map(fn ($acceso) => [
            'acceso'  => $acceso,
            'qr_data_uri' => $this->generarQrDataUri($acceso->token_qr),
        ]);

        return new Content(
            view: 'emails.confirmacion_compra',
            with: [
                'venta'          => $this->venta,
                'accesosConQr'   => $accesosConQr,
                'evento'         => $evento,
                'teatro'         => $evento?->teatro,
            ],
        );
    }

    /**
     * Genera el QR de un boleto como data URI PNG (sin escribir a disco ni
     * depender de un endpoint público) — requiere ext-gd, agregada al
     * Dockerfile específicamente para esta función (antes no había ningún uso
     * server-side de generación de imágenes en el proyecto).
     */
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
}
