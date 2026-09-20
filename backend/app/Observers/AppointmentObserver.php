<?php

namespace App\Observers;

use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

class AppointmentObserver
{
    /**
     * Se dispara cuando una cita entra al sistema por primera vez.
     */
    public function created(Appointment $appointment)
    {
        // Si el cliente agenda desde el Front de la web, entra en 'pending'
        if ($appointment->status === 'pending') {
            Log::info("ALERTA INTERNA - Negocio #{$appointment->business_id}: El cliente asignó una nueva cita (#{$appointment->id}) esperando aprobación.");
        }
    }

    /**
     * Se dispara automáticamente cuando el Owner actualiza cualquier columna de la cita.
     */
    public function updated(Appointment $appointment)
    {
        // Filtro de seguridad: Evaluamos ÚNICAMENTE si el 'status' cambió de valor
        if ($appointment->isDirty('status')) {
            $nuevoEstado = $appointment->status;

            switch ($nuevoEstado) {
                case 'confirmed':
                    $this->enviarNotificacionWhatsApp($appointment, "¡Hola! Tu cita para el día {$appointment->date} ha sido confirmada con éxito. ¡Te esperamos!");
                    break;

                case 'cancelled':
                    $this->enviarNotificacionWhatsApp($appointment, "Hola. Tu cita programada para el día {$appointment->date} ha sido cancelada.");
                    break;

                case 'completed':
                    Log::info("HISTORIAL FINANCIERO: Cita #{$appointment->id} completada. Ingresos y comisiones procesados.");
                    break;

                case 'no_show':
                    Log::info("HISTORIAL CLIENTES: Cita #{$appointment->id} marcada como Inasistencia (No-Show).");
                    break;
            }
        }
    }

    /**
     * Abstracción del envío a la API externa de mensajería (Simulador)
     */
    protected function enviarNotificacionWhatsApp(Appointment $appointment, string $mensaje)
    {
        // Cargamos la relación del cliente de forma segura si no está en la memoria caché de Eloquent
        $appointment->loadMissing('client');

        if ($appointment->client && $appointment->client->phone) {
            $telefono = $appointment->client->phone;
            $nombreCliente = $appointment->client->name;

            Log::info("=====================================================================");
            Log::info(" WHATSAPP OUTBOUND GATEWAY SIMULATOR");
            Log::info("Destinatario: {$nombreCliente} ({$telefono})");
            Log::info("Contenido: {$mensaje}");
            Log::info("=====================================================================");
        } else {
            Log::warning("ADVERTENCIA: La cita #{$appointment->id} cambió de estado pero el cliente no posee un número telefónico asignado.");
        }
    }
}