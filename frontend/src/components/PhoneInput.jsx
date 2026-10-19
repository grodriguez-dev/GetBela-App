import React from "react";

// Indicativo fijo del país (Venezuela). Visible pero NO editable.
export const PHONE_PREFIX = "+58";

/**
 * Convierte cualquier valor guardado (p. ej. "+584123334567", "04123334567"
 * o "4123334567") a la parte local que se muestra en el input: 10 dígitos,
 * sin el +58 y sin el 0 inicial (ej. "4123334567").
 */
export const toLocalDigits = (value) => {
  const raw = String(value ?? "").trim();
  let digits = raw.replace(/\D/g, "");
  if (!digits) return "";

  // Si el valor viene en formato internacional ("+58...") quitamos el código
  // de país. Importante: se decide por la presencia del "+" y no por la
  // longitud, para que funcione mientras el usuario escribe (p. ej. "+584").
  if (raw.startsWith("+") && digits.startsWith("58")) {
    digits = digits.slice(2);
  } else if (digits.startsWith("58") && digits.length > 10) {
    // Formato guardado sin "+" pero con código de país (ej. "584123334567").
    digits = digits.slice(2);
  }

  // Quita ceros iniciales del formato local venezolano ("0412..." -> "412...").
  digits = digits.replace(/^0+/, "");

  return digits.slice(0, 10);
};

/**
 * Arma el número completo que se envía y se guarda en la BD:
 * "+58" + parte local (ej. "+584123334567").
 */
export const toFullPhone = (value) => {
  const digits = toLocalDigits(value);
  return digits ? `${PHONE_PREFIX}${digits}` : "";
};

/**
 * Input de teléfono con el indicativo +58 fijo (visible y no editable).
 *
 * El usuario solo escribe la parte local (ej. 4123334567) y el valor que se
 * propaga por `onChange` ya incluye el prefijo (ej. "+584123334567").
 *
 * Mantiene la validación de "solo números": filtra cualquier carácter que no
 * sea dígito y limita a 10 dígitos (parte local venezolana).
 */
const PhoneInput = ({
  name,
  value,
  onChange,
  disabled = false,
  required = false,
  placeholder = "4123334567",
  invalid = false,
  className = "",
}) => {
  const localValue = toLocalDigits(value);

  const handleChange = (e) => {
    const digits = e.target.value
      .replace(/\D/g, "") // solo números
      .replace(/^0+/, "") // sin ceros iniciales
      .slice(0, 10); // máximo 10 dígitos

    onChange(toFullPhone(digits));
  };

  return (
    <div
      className={`flex items-stretch w-full border rounded-xl overflow-hidden transition-colors focus-within:border-purple-700 ${
        disabled ? "bg-gray-100" : "bg-white"
      } ${invalid ? "border-red-400" : "border-gray-300"} ${className}`}
    >
      <span className="flex items-center px-3 bg-gray-50 text-gray-700 font-semibold border-r border-gray-200 select-none">
        {PHONE_PREFIX}
      </span>
      <input
        type="tel"
        inputMode="numeric"
        autoComplete="tel-national"
        name={name}
        value={localValue}
        onChange={handleChange}
        disabled={disabled}
        required={required}
        placeholder={placeholder}
        maxLength={10}
        className="flex-1 min-w-0 px-3 py-3 bg-transparent outline-none"
      />
    </div>
  );
};

export default PhoneInput;
