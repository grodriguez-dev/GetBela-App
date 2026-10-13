// src/components/public/AppointmentModal.jsx
import React, { useState } from "react";
import api from "../../api/axios"; // Asegúrate de ajustar la ruta de tu instancia de axios
import {
  Sparkles,
  Clock,
  DollarSign,
  Store,
  Calendar,
  FileText,
  CheckCircle2,
  X,
} from "lucide-react";

// Devuelve la fecha en formato YYYY-MM-DD usando la zona horaria LOCAL del navegador.
// Evita el bug de toISOString(), que convierte a UTC y puede adelantar un día.
export const getLocalDateString = (date = new Date()) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
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
    setLoading(true);
    setErrorMessage("");

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
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 py-12 z-50 overflow-y-auto">
      <div className="bg-white rounded-3xl max-w-2xl w-full p-6 md:p-8 shadow-2xl relative my-auto">
        {/* Cabecera */}
        <div className="mb-6">
          <span className="text-xs font-bold uppercase tracking-wider text-purple-800 bg-purple-50 px-3 py-1 rounded-full">
            Confirmación de Reserva
          </span>
          <h3 className="text-2xl font-extrabold text-purple-950 mt-2">
            Detalles de tu Cita
          </h3>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
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
                value={formData.start_time}
                onChange={handleChange}
                className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-purple-900 font-medium"
              />
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
                <input
                  type="tel"
                  name="client_phone"
                  required
                  placeholder="Ej. 04141234567"
                  value={formData.client_phone}
                  onChange={handleChange}
                  className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-purple-900 font-medium"
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

          {/* Botones de acción finales */}
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              disabled={loading}
              className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3.5 rounded-xl transition-all"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={loading}
              className="flex-1 bg-purple-950 hover:bg-purple-900 text-white font-bold py-3.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2"
            >
              {loading ? (
                "Procesando reserva..."
              ) : (
                <>
                  <CheckCircle2 className="w-5 h-5" /> Confirmar Cita
                </>
              )}
            </button>
          </div>
        </form>

        {/* Mensaje de Error si la API rechaza algo (ej. solapamiento o cerrado) */}
        {errorMessage && (
          <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
            {errorMessage}
          </div>
        )}
      </div>
    </div>
  );
};

export default AppointmentForm;
