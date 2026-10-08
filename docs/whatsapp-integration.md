# Integración de WhatsApp para confirmación de citas

## Objetivo

Enviar un mensaje de WhatsApp automáticamente cuando un cliente agenda una cita en la aplicación.

Este proyecto ya tiene el flujo de reserva en:

- Frontend: `frontend/src/components/public/AppointmentForm.jsx`
- Backend: `backend/app/Http/Controllers/AppointmentController.php`

La mejor forma de hacerlo es desde el backend, tras guardar la cita, para evitar fallos del frontend y garantizar que el mensaje salga siempre que la reserva sea exitosa.

---

## 1) Requisitos

Necesitas:

- Un número de WhatsApp Business
- Acceso a Meta Business Suite
- Un token de acceso de WhatsApp Business API
- El ID del número de teléfono
- Una plantilla aprobada de WhatsApp

---

## 2) Crear la plantilla en Meta

En Meta Business Suite:

1. Ve a WhatsApp
2. Configuración > Plantillas de mensajes
3. Crea una nueva plantilla
4. Usa un nombre como: `appointment_confirmed`
5. Configura lenguaje español (`es_ES`)
6. Añade un mensaje tipo:

```text
Hola {{1}},
Tu cita para {{2}} quedó reservada para el {{3}} a las {{4}}.
Gracias por confiar en nosotros.
```

Esto permitirá enviar mensajes con los datos reales de la cita.

---

## 3) Configurar variables de entorno

Agrega estas variables al archivo `.env` de Laravel (`backend/.env`):

```env
WHATSAPP_TOKEN=TU_TOKEN_DE_META
WHATSAPP_PHONE_NUMBER_ID=TU_PHONE_NUMBER_ID
WHATSAPP_API_VERSION=v22.0
WHATSAPP_TEMPLATE_NAME=appointment_confirmed
```

Si el proyecto usa diferentes entornos, puedes repetirlas en `.env.local`, `.env.testing`, etc.

---

## 4) Lógica en Laravel

La reserva se procesa en `AppointmentController::store()`. Después de crear la cita, debes llamar a una función que envíe el mensaje por WhatsApp.

### Código base a agregar

En `backend/app/Http/Controllers/AppointmentController.php`:

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
```

Luego, dentro del método `store()`, justo después de crear la cita:

```php
$appointment = Appointment::create([
    'business_id' => $businessId,
    'service_id' => $request->service_id,
    'client_id' => $client->id,
    'date' => $request->date,
    'start_time' => $requestedStartTime,
    'end_time' => $calculatedEndTime,
    'status' => 'pending',
    'total_price' => $service->price,
]);

$this->sendWhatsAppConfirmation($appointment, $client, $service);
```

Y agregas el método dentro de la clase:

```php
private function sendWhatsAppConfirmation($appointment, $client, $service)
{
    $phone = preg_replace('/[^0-9]/', '', $client->phone);

    // Ajusta según tu país
    if (!str_starts_with($phone, '52') && !str_starts_with($phone, '57')) {
        $phone = '52' . $phone;
    }

    $token = env('WHATSAPP_TOKEN');
    $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
    $apiVersion = env('WHATSAPP_API_VERSION', 'v22.0');

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $phone,
        'type' => 'template',
        'template' => [
            'name' => env('WHATSAPP_TEMPLATE_NAME', 'appointment_confirmed'),
            'language' => [
                'code' => 'es_ES'
            ],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $client->name],
                        ['type' => 'text', 'text' => $service->name],
                        ['type' => 'text', 'text' => $appointment->date],
                        ['type' => 'text', 'text' => $appointment->start_time],
                    ],
                ]
            ]
        ]
    ];

    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ])->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", $payload);

    if (! $response->successful()) {
        Log::error('WhatsApp no enviado', [
            'status' => $response->status(),
            'body' => $response->body(),
            'appointment_id' => $appointment->id,
        ]);
    }
}
```

---

## 5) Consideraciones importantes

### Formato del teléfono

Tu cliente se guarda en `client_phone`, por ejemplo:

- `+52 555 123 4567`
- `5551234567`
- `521234567890`

Antes de enviarlo a Meta, conviene dejarlo solo con números y con código de país.

### Validación de la cita

La reserva ya se valida en:

- `business_id`
- `service_id`
- `client_name`
- `client_phone`
- `date`
- `start_time`

Si eso pasa, entonces se crea la cita y se dispara el mensaje de WhatsApp.

### Manejo de errores

Si Meta responde con error, no debe romper la reserva. El sistema debe:

- guardar la cita igual
- dejar log del error
- mostrar un mensaje de éxito o de aviso dependiente de tu UX

---

## 6) Flujo real del sistema

La secuencia correcta es:

1. El cliente llena el formulario en `AppointmentForm`
2. El frontend hace `POST /api/appointments`
3. Laravel valida la información
4. Laravel crea la cita en la base de datos
5. Laravel dispara el mensaje de WhatsApp
6. Laravel responde con éxito al frontend

---

## 7) Recomendación para producción

Si quieres evitar depender de la API web cada vez, puedes hacer lo siguiente:

- usar un job de Laravel
- poner el envío de WhatsApp en una cola (`queue`)
- ofrecer un reintento si falla

Ejemplo:

```php
// En el futuro
SendAppointmentWhatsAppJob::dispatch($appointment->id);
```

Esto hace que la reserva no se demore si la API de Meta está lenta.

---

## 8) Alternativa rápida para pruebas

Si solo quieres probar antes de usar Meta Business API, puedes usar:

- un número de prueba
- una plantilla aprobada
- una cuenta validada por Meta

No se recomienda enviar mensajes reales desde un entorno local sin aprobar la plantilla.

---

## 9) Resumen

La integración recomendada para este proyecto es:

- mantener la reserva actual
- crear la cita en Laravel
- enviar WhatsApp desde `AppointmentController`
- usar Meta WhatsApp Business API
- aprobar una plantilla en Meta antes de producción

---

## 10) Siguiente paso

Si quieres, el siguiente paso es que te preparemos exactamente:

- el código listo para pegar en `AppointmentController.php`
- el `.env` final
- la plantilla en español lista para Meta
- y la prueba de envío en Laravel para verificarlo localmente.
