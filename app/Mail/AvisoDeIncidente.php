<?php

namespace App\Mail;

use App\Models\Incidente;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * El mail de caída y el de recuperación.
 *
 * **No lleva `ShouldQueue` a propósito.** Lo dispara un comando del scheduler,
 * que ya es asíncrono; encolarlo obligaría a tener un worker corriendo y en
 * hosting compartido eso se cae en silencio y deja los avisos sin enviar sin que
 * nadie se entere. Es la misma decisión que en huella con los recordatorios.
 */
class AvisoDeIncidente extends Mailable
{
    public function __construct(
        public readonly Incidente $incidente,
        public readonly bool $seRecupero = false,
    ) {}

    public function envelope(): Envelope
    {
        $proyecto = $this->incidente->proyecto->nombre;
        $qué = $this->incidente->tipo->etiqueta();

        return new Envelope(
            subject: $this->seRecupero
                ? "[Centinela] {$proyecto} se recuperó: {$qué}"
                : "[Centinela] {$proyecto} con falla: {$qué}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.incidente',
            with: [
                // La vista recibe `aviso` y no `incidente`: Laravel pasa a la
                // vista las propiedades públicas **y** las claves de `with`, y una
                // propiedad pisa la clave homónima sin dar ningún error.
                'aviso' => $this->incidente,
                'seRecupero' => $this->seRecupero,
            ],
        );
    }
}
