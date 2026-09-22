<?php

namespace App\Notifications;

use App\Models\Soporte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Un solo correo por persona con todo lo que se le está venciendo.
 *
 * La tentación es mandar un correo por caso. En un día movido eso son quince
 * correos al mismo técnico, que es la forma más rápida de que los archive sin
 * leer. Aquí va una sola pieza: primero lo vencido, después lo que está por
 * vencerse, cada caso en una línea con lo justo para decidir.
 */
class CasosEnRiesgoDeSla extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Soporte>  $vencidos
     * @param  Collection<int, Soporte>  $enRiesgo
     */
    public function __construct(
        public readonly Collection $vencidos,
        public readonly Collection $enRiesgo,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject($this->asunto())
            ->greeting('Hola '.str($notifiable->name)->before(' ').',');

        if ($this->vencidos->isNotEmpty()) {
            $mensaje->line('**Ya se pasaron del tiempo acordado:**');

            foreach ($this->vencidos as $caso) {
                $mensaje->line($this->linea($caso, 'vencido'));
            }
        }

        if ($this->enRiesgo->isNotEmpty()) {
            $mensaje->line($this->vencidos->isNotEmpty() ? ' ' : '');
            $mensaje->line('**Están por vencerse:**');

            foreach ($this->enRiesgo as $caso) {
                $mensaje->line($this->linea($caso, $caso->restanteLegible()));
            }
        }

        $unico = $this->vencidos->count() + $this->enRiesgo->count() === 1;

        return $mensaje
            ->action(
                $unico ? 'Abrir el caso' : 'Ver la bandeja',
                $unico
                    ? route('soportes.show', $this->vencidos->first() ?? $this->enRiesgo->first())
                    : route('soportes.index'),
            )
            ->line('El reloj solo corre en horario hábil y se detiene mientras el caso espera una visita, una respuesta de redes o al cliente.');
    }

    /** "TCF-000412 · Marta Ospina · Sin internet · vencido" */
    private function linea(Soporte $caso, string $estadoDelReloj): string
    {
        return sprintf(
            '%s · %s · %s · %s',
            $caso->numero_soporte ?? "#{$caso->id}",
            str($caso->cliente->nombre ?? 'Sin cliente')->limit(30),
            $caso->tipoFalla->nombre ?? $caso->etiquetaTipo(),
            $estadoDelReloj,
        );
    }

    private function asunto(): string
    {
        $vencidos = $this->vencidos->count();
        $riesgo = $this->enRiesgo->count();

        return match (true) {
            $vencidos > 0 && $riesgo > 0 => "SLA: {$vencidos} vencidos y {$riesgo} por vencerse",
            $vencidos > 0 => $vencidos === 1
                ? 'SLA: un caso vencido'
                : "SLA: {$vencidos} casos vencidos",
            default => $riesgo === 1
                ? 'SLA: un caso está por vencerse'
                : "SLA: {$riesgo} casos están por vencerse",
        };
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'vencidos' => $this->vencidos->pluck('id')->all(),
            'en_riesgo' => $this->enRiesgo->pluck('id')->all(),
        ];
    }
}
