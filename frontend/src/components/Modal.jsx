import React from "react";
import { X } from "lucide-react";

/**
 * Modal genérico y reutilizable para todos los formularios.
 *
 * Estructura consistente:
 * - Cabecera fija con título (+ eyebrow opcional) y botón de cerrar.
 * - Cuerpo con scroll (los formularios largos hacen scroll sin mover la cabecera ni el pie).
 * - Pie fijo con el botón "Cancelar" (gris, izquierda) y el botón de guardar
 *   (morado como el navbar, derecha). El botón de guardar envía el <form> cuyo
 *   id coincide con `formId` (atributo nativo `form`), por lo que el formulario
 *   sigue viviendo en el cuerpo y mantiene su propio estado.
 */
const Modal = ({
  isOpen = true,
  onClose,
  title,
  eyebrow,
  formId,
  submitLabel = "Guardar",
  cancelLabel = "Cancelar",
  maxWidth = "max-w-lg",
  submitDisabled = false,
  cancelDisabled = false,
  children,
}) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div
        className={`bg-white rounded-2xl shadow-xl w-full ${maxWidth} max-h-[90vh] flex flex-col overflow-hidden`}
      >
        {/* Cabecera */}
        <div className="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-gray-100">
          <div>
            {eyebrow && (
              <span className="inline-block text-xs font-bold uppercase tracking-wider text-purple-800 bg-purple-50 px-3 py-1 rounded-full">
                {eyebrow}
              </span>
            )}
            <h2
              className={`text-lg font-bold text-purple-950 ${eyebrow ? "mt-2" : ""}`}
            >
              {title}
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition shrink-0"
            aria-label="Cerrar"
          >
            <X size={20} />
          </button>
        </div>

        {/* Cuerpo con scroll */}
        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>

        {/* Pie de acciones: Cancelar (izquierda, gris) / Guardar (derecha, morado) */}
        <div className="flex justify-between items-center gap-3 px-6 py-4 border-t border-gray-100 bg-white">
          <button
            type="button"
            onClick={onClose}
            disabled={cancelDisabled}
            className="px-5 py-2 rounded-lg bg-gray-100 text-gray-700 font-medium border border-gray-300 hover:bg-gray-200 transition disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {cancelLabel}
          </button>
          <button
            type="submit"
            form={formId}
            disabled={submitDisabled}
            className="px-5 py-2 rounded-lg bg-purple-950 text-white font-semibold hover:bg-purple-800 transition shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {submitLabel}
          </button>
        </div>
      </div>
    </div>
  );
};

export default Modal;
