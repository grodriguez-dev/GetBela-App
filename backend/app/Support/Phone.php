<?php

namespace App\Support;

/**
 * Utilidades para normalizar números de teléfono al formato venezolano
 * E.164: "+58" seguido de 10 dígitos (ej. "+584123334567").
 */
class Phone
{
    public const PREFIX = '+58';

    /**
     * Normaliza un número a "+58XXXXXXXXXX".
     *
     * Acepta entradas como:
     *   "4123334567", "04123334567", "+58 412-3334567", "+584123334567".
     *
     * Devuelve null cuando no queda ningún dígito utilizable (p. ej. "" o "abc").
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === null || $digits === '') {
            return null;
        }

        // Quita el código de país (58) cuando viene incluido. Se decide por la
        // presencia del "+" (formato internacional) y no solo por la longitud,
        // para soportar también valores parciales mientras se escribe ("+584").
        if (str_starts_with($raw, '+') && str_starts_with($digits, '58')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '58') && strlen($digits) > 10) {
            // Formato guardado sin "+" pero con código de país (ej. "584123334567").
            $digits = substr($digits, 2);
        }

        // Quita ceros iniciales del formato local ("0412..." -> "412...").
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        // Nos quedamos con la parte local (máximo 10 dígitos).
        $digits = substr($digits, 0, 10);

        return self::PREFIX . $digits;
    }
}
