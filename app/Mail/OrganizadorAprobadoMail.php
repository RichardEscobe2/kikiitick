<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizadorAprobadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;

    public function __construct(User $usuario)
    {
        $this->usuario = $usuario;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Tu solicitud de Organizador ha sido Aprobada! 🎉 - KikiiTick',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organizador_aprobado',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}