<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza los teléfonos ya existentes al formato venezolano (+58XXXXXXXXXX).
     *
     * Es conservador: SOLO toca los valores que no empiezan con "+" (números
     * locales tipo "04123334567"). Los números que ya traen un código de país
     * (por ejemplo "+1...") se dejan intactos.
     */
    public function up(): void
    {
        $this->normalizeSimpleTable('businesses');
        $this->normalizeSimpleTable('employees');
        $this->normalizeClients();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // La normalización de datos no es reversible de forma segura.
    }

    /**
     * Normaliza columnas de teléfono sin restricción de unicidad.
     */
    private function normalizeSimpleTable(string $table): void
    {
        DB::table($table)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', 'not like', '+%')
            ->orderBy('id')
            ->get(['id', 'phone'])
            ->each(function ($row) use ($table) {
                $normalized = Phone::normalize($row->phone);

                if ($normalized && $normalized !== $row->phone) {
                    DB::table($table)->where('id', $row->id)->update(['phone' => $normalized]);
                }
            });
    }

    /**
     * Los clientes tienen un índice único (business_id, phone). Evitamos crear
     * duplicados: si al normalizar el número ya existe otro cliente con ese
     * teléfono en el mismo negocio, dejamos el valor original sin tocar.
     */
    private function normalizeClients(): void
    {
        $seen = [];

        // Registramos primero los teléfonos que ya están en formato "+...".
        DB::table('clients')
            ->where('phone', 'like', '+%')
            ->get(['business_id', 'phone'])
            ->each(function ($client) use (&$seen) {
                $seen[$client->business_id][$client->phone] = true;
            });

        DB::table('clients')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', 'not like', '+%')
            ->orderBy('id')
            ->get(['id', 'business_id', 'phone'])
            ->each(function ($client) use (&$seen) {
                $normalized = Phone::normalize($client->phone);

                if (!$normalized) {
                    return;
                }

                // Ya existe otro cliente del mismo negocio con ese número -> no tocar.
                if (isset($seen[$client->business_id][$normalized])) {
                    return;
                }

                DB::table('clients')->where('id', $client->id)->update(['phone' => $normalized]);
                $seen[$client->business_id][$normalized] = true;
            });
    }
};
