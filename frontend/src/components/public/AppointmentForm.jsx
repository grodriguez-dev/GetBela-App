// src/components/public/AppointmentModal.jsx
import React, { useState, useId } from "react";
import api from "../../api/axios"; // Asegúrate de ajustar la ruta de tu instancia de axios
import PhoneInput from "../PhoneInput";
import Modal from "../Modal";
import { Clock, DollarSign, Store, FileText } from "lucide-react";

// Devuelve la fecha en formato YYYY-MM-DD usando la zona horaria LOCAL del navegador.
// Evita el bug de toISOString(), que convierte a UTC y puede adelantar un día.
export const getLocalDateString = (date = new Date()) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// Hora actual en formato HH:MM (24 h, zona local del navegador).
export const getLocalTimeString = (date = new Date()) =>
  `${String(date.getHours()).padStart(2, "0")}:${String(
    date.getMinutes(),
  ).padStart(2, "0")}`;

// Convierte "HH:MM" (24 h) a una etiqueta de 12 horas (ej. "10:15 p. m.").
// Sirve para que el usuario vea sin ambigüedad si eligió a. m. o p. m.
export const formatTo12h = (hhmm) => {
  if (!hhmm || !hhmm.includes(":")) return "";
  const [h, m] = hhmm.split(":").map(Number);
  if (Number.isNaN(h) || Number.isNaN(m)) return "";
  const suffix = h < 12 ? "a. m." : "p. m.";
  const hour12 = h % 12 === 0 ? 12 : h % 12;
  return `${hour12}:${String(m).padStart(2, "0")} ${suffix}`;
};

const AppointmentForm = ({ isOpen, onClose, service }) => {
  // 1. Estados para los campos que el usuario llenará
  const [formData, setFormData] = useState({
    client_name: "",
    client_phone: "",
    date: getLocalDateString(),
    start_time: "",
    notes: "", // <--- Nuevo campo para comentarios/notas
  });

  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const formId = "appointment-form-" + useId().replace(/[^a-zA-Z0-9]/g, "");

  // Si la cita es para hoy, la hora mínima permitida es la hora actual.
  const isToday = formData.date === getLocalDateString();
  const minTimeForToday = isToday ? getLocalTimeString() : undefined;

  if (!isOpen || !service) return null;

  // 2. Manejador de cambios en los inputs
  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  // 3. Función matemática para calcular la hora de finalización en tiempo real
  const calculateEndTime = () => {
    if (!formData.start_time || !service.duration_minutes) return "--:--";

    // Parseamos la hora de inicio seleccionada
    const [hours, minutes] = formData.start_time.split(":").map(Number);
    const startDate = new Date();
    startDate.setHours(hours, minutes, 0, 0);

    // Sumamos los minutos de duración del servicio
    startDate.setMinutes(
      startDate.getMinutes() + Number(service.duration_minutes),
    );

    // Retornamos en formato HH:MM
    return startDate.toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
      hour12: true,
    });
  };

  const type = () => {
    if (service.business.type == "independent") {
      return "Estilista Independiente";
    }
    return "Salon de Bellesa";
  };

  // 4. Enviar formulario al backend
  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrorMessage("");

    // Refuerzo: no permitir agendar hoy en una hora ya pasada. Esto evita la
    // confusión típica entre 12 h y 24 h (elegir "10:15" y que en realidad sea
    // 10:15 a. m. cuando se quería 10:15 p. m.).
    if (
      isToday &&
      formData.start_time &&
      formData.start_time < getLocalTimeString()
    ) {
      setErrorMessage(
        `La hora ${formatTo12h(formData.start_time)} (${formData.start_time}) ya pasó hoy. ` +
          "Elige una hora futura. Recuerda que, si quieres la tarde/noche, en 24 h se escribe sumando 12 (ej. 10:15 p. m. = 22:15).",
      );
      return;
    }

    setLoading(true);

    try {
      const payload = {
        business_id: service.business.id,
        service_id: service.id,
        client_name: formData.client_name,
        client_phone: formData.client_phone,
        date: formData.date,
        start_time: `${formData.start_time}:00`, // Formato H:i:s que exige tu backend
        notes: formData.notes,
      };

      // Petición a tu endpoint store del AppointmentController
      const response = await api.post("/api/appointments", payload);

      alert("¡Cita agendada con éxito!");
      onClose(); // Cierra el modal
    } catch (error) {
      console.error("Error al agendar cita:", error);
      setErrorMessage(
        error.response?.data?.message ||
          "Ocurrió un error al procesar la reserva. Verifica los datos.",
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      eyebrow="Confirmación de Reserva"
      title="Detalles de tu Cita"
      formId={formId}
      submitLabel={loading ? "Procesando reserva..." : "Confirmar Cita"}
      submitDisabled={loading}
      cancelDisabled={loading}
      maxWidth="max-w-2xl"
    >
        <form id={formId} onSubmit={handleSubmit} className="space-y-6">
          {/* SECCIÓN 1: DETALLES FIJOS DEL SERVICIO Y NEGOCIO */}
          <div className="bg-purple-900/5 border border-purple-100 p-5 rounded-2xl">
            <h4 className="text-sm font-bold text-purple-950 uppercase mb-3 flex items-center gap-2">
              <Store className="w-4 h-4 text-purple-800" /> Información del
              Establecimiento y Servicio
            </h4>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <span className="text-xs text-gray-500 block">
                  {service.business.type == 'independent' 
                    ? "Estilista Independiente"
                    : "Salon de Bellesa"}
                </span>
                <span className="font-bold text-gray-800 text-base">
                  {service.business
                    ? service.business.name
                    : "Estilista Independiente"}
                </span>
                {service.business?.address && (
                  <span className="text-xs text-gray-500 block mt-0.5">
                    {service.business.address}
                  </span>
                )}
              </div>

              <div>
                <span className="text-xs text-gray-500 block">
                  Servicio Elegido
                </span>
                <span className="font-bold text-purple-950 text-base">
                  {service.name}
                </span>
              </div>
            </div>

            {/* Fila de Precio y Duración */}
            <div className="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-purple-100/60">
              <div className="flex items-center gap-2">
                <DollarSign className="w-5 h-5 text-purple-800" />
                <div>
                  <span className="text-xs text-gray-500 block">
                    Precio Total
                  </span>
                  <span className="font-extrabold text-purple-950">
                    ${Number(service.price).toFixed(2)}
                  </span>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <Clock className="w-5 h-5 text-purple-800" />
                <div>
                  <span className="text-xs text-gray-500 block">
                    Duración Estimada
                  </span>
                  <span className="font-extrabold text-purple-950">
                    {service.duration_minutes} min
                  </span>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-purple-100/60 mt-4">
              {/* Estilista Asignado */}
              <div>
                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-2">
                  Estilista a cargo:
                </span>
                <div className="flex items-center gap-2">
                  <span className="text-sm font-bold text-purple-900 bg-purple-50 border border-purple-100 px-3 py-1 rounded-lg">
                    {service.employee?.name || "Profesional asignado"}
                  </span>
                </div>
              </div>

              {/* SECCIÓN EXTRA: Productos / Insumos que utiliza el servicio */}
              {service.products && service.products.length > 0 && (
                <div>
                  <span className="text-xs font-bold text-gray-500 block mb-1 uppercase tracking-wider">
                    Insumos / Productos de calidad utilizados:
                  </span>
                  <div className="flex flex-wrap gap-1.5">
                    {service.products.map((prod) => (
                      <span
                        key={prod.id}
                        className="bg-white border border-purple-200 text-purple-900 text-xs px-2.5 py-1 rounded-lg font-medium shadow-2xs"
                      >
                        {prod.name}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* SECCIÓN 2: FECHA, HORA Y CÁLCULO DE FINALIZACIÓN */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase mb-1">
                Fecha de la Cita *
              </label>
              <input
                type="date"
                name="date"
                required
                min={getLocalDateString()}
                value={formData.date}
                onChange={handleChange}
                className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-purple-900 font-medium"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase mb-1">
                Hora de Inicio *
              </label>
              <input
                type="time"
                name="start_time"
                required
                step="60"
                min={minTimeForToday}
                value={formData.start_time}
                onChange={handleChange}
                className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-purple-900 font-medium"
              />
              {formData.start_time && (
                <p className="text-xs text-gray-500 mt-1">
                  Hora elegida:{" "}
                  <strong className="text-purple-900">
                    {formatTo12h(formData.start_time)}
                  </strong>{" "}
                  <span className="text-gray-400">
                    ({formData.start_time} en 24 h)
                  </span>
                </p>
              )}
            </div>
          </div>

          {/* Indicador visual automático de término */}
          <div className="bg-gray-50 border border-gray-200 rounded-xl p-3.5 flex justify-between items-center">
            <span className="text-xs text-gray-500 font-semibold uppercase">
              Tu cita terminará apróx. a las:
            </span>
            <span className="font-extrabold text-purple-950 bg-purple-100 px-3 py-1 rounded-lg text-sm">
              {calculateEndTime()}
            </span>
          </div>

          {/* SECCIÓN 3: DATOS DEL CLIENTE AL FINAL Y NOTAS */}
          <div className="space-y-4 pt-2 border-t border-gray-100">
            <h4 className="text-sm font-bold text-purple-950 uppercase">
              Tus Datos de Contacto
            </h4>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase mb-1">
                  Nombre Completo *
                </label>
                <input
                  type="text"
                  name="client_name"
                  required
                  placeholder="Ej. Cecilia Rodriguez"
                  value={formData.client_name}
                  onChange={handleChange}
                  className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-purple-900 font-medium"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase mb-1">
                  Teléfono / WhatsApp *
                </label>
                <PhoneInput
                  name="client_phone"
                  required
                  value={formData.client_phone}
                  onChange={(val) =>
                    setFormData((prev) => ({ ...prev, client_phone: val }))
                  }
                />
              </div>
            </div>

            {/* Campo de notas o comentarios */}
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase mb-1 flex items-center gap-1">
                <FileText className="w-3.5 h-3.5" /> Notas o Comentarios
                Adicionales (Opcional)
              </label>
              <textarea
                name="notes"
                rows="2"
                placeholder="Ej. Tengo sensibilidad en el cuero cabelludo, prefiero tono oscuro..."
                value={formData.notes}
                onChange={handleChange}
                className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-purple-900 font-medium text-sm"
              />
            </div>
          </div>

          {/* Mensaje de Error si la API rechaza algo (ej. solapamiento o cerrado) */}
          {errorMessage && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
              {errorMessage}
            </div>
          )}
        </form>
    </Modal>
  );
};

export default AppointmentForm;
