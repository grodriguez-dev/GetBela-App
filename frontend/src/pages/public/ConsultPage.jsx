import React, { useState } from "react";
import MainLayout from "../../layouts/MainLayout";
import api from "../../api/axios";
import { Link } from "react-router-dom";
import {
  ArrowLeft,
  Search,
  Calendar,
  Clock,
  User,
  Phone,
  Scissors,
  Store,
  DollarSign,
  Package,
  StickyNote,
  BadgeCheck,
  XCircle,
  AlertCircle,
  Loader2,
} from "lucide-react";

const statusConfig = {
  pending: {
    label: "Pendiente",
    className: "bg-yellow-100 text-yellow-800 border-yellow-200",
    icon: <AlertCircle className="w-4 h-4" />,
  },
  confirmed: {
    label: "Confirmada",
    className: "bg-blue-100 text-blue-800 border-blue-200",
    icon: <BadgeCheck className="w-4 h-4" />,
  },
  in_progress: {
    label: "En progreso",
    className: "bg-purple-100 text-purple-800 border-purple-200",
    icon: <Clock className="w-4 h-4" />,
  },
  completed: {
    label: "Completada",
    className: "bg-green-100 text-green-800 border-green-200",
    icon: <BadgeCheck className="w-4 h-4" />,
  },
  cancelled: {
    label: "Cancelada",
    className: "bg-red-100 text-red-800 border-red-200",
    icon: <XCircle className="w-4 h-4" />,
  },
  no_show: {
    label: "No asistió",
    className: "bg-gray-100 text-gray-700 border-gray-200",
    icon: <XCircle className="w-4 h-4" />,
  },
};

const formatTime = (time) => {
  if (!time) return "";
  // Maneja tanto "HH:mm:ss" como "HH:mm"
  const parts = time.split(":");
  if (parts.length >= 2) return `${parts[0]}:${parts[1]}`;
  return time;
};

const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return dateStr;
  return date.toLocaleDateString("es-ES", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

const ConsultPage = () => {
  const [phone, setPhone] = useState("");
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [error, setError] = useState("");
  const [validationError, setValidationError] = useState("");

  const handleSearch = async (e) => {
    e.preventDefault();
    setError("");
    setValidationError("");

    if (!phone.trim()) {
      setValidationError("Ingresa tu número de teléfono");
      return;
    }

    setLoading(true);
    try {
      const res = await api.get(
        `/api/public/appointments/search?phone=${encodeURIComponent(phone.trim())}`
      );
      setAppointments(res.data.appointments || []);
      setSearched(true);
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setValidationError(err.response.data.errors.phone?.[0] || "");
      } else {
        setError("No se pudieron consultar las citas. Intenta de nuevo.");
      }
      setAppointments([]);
      setSearched(true);
    } finally {
      setLoading(false);
    }
  };

  return (
    <MainLayout>
      <div className="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
        <div className="mb-6">
          <Link
            to="/"
            className="inline-flex items-center gap-2 text-purple-950 hover:text-purple-700 transition-colors font-medium"
          >
            <ArrowLeft size={20} />
            Volver al inicio
          </Link>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6">
          <div className="text-center mb-6">
            <h1 className="text-3xl md:text-4xl font-extrabold text-purple-950 mb-3">
              Consulta tus Citas
            </h1>
            <p className="text-gray-600">
              Ingresa el número de teléfono con el que registraste tu cita para
              ver toda la información
            </p>
          </div>

          <form
            onSubmit={handleSearch}
            className="flex flex-col sm:flex-row gap-3 max-w-xl mx-auto"
          >
            <div className="flex-1">
              <input
                type="text"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="Ej: +57 300 000 0000"
                className={`w-full px-4 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 ${
                  validationError ? "border-red-500" : "border-gray-300"
                }`}
              />
              {validationError && (
                <p className="text-sm text-red-600 mt-1">{validationError}</p>
              )}
            </div>
            <button
              type="submit"
              disabled={loading}
              className="flex items-center justify-center gap-2 bg-purple-950 text-white px-6 py-3 rounded-xl hover:bg-purple-800 transition shadow-sm disabled:opacity-60"
            >
              {loading ? (
                <Loader2 className="w-5 h-5 animate-spin" />
              ) : (
                <Search className="w-5 h-5" />
              )}
              {loading ? "Buscando..." : "Consultar"}
            </button>
          </form>

          {error && (
            <div className="mt-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm text-center">
              {error}
            </div>
          )}
        </div>

        {searched && appointments.length === 0 && !loading && (
          <div className="text-center py-12 bg-white rounded-2xl border border-gray-200">
            <p className="text-gray-500 text-lg">
              No encontramos citas registradas con ese número de teléfono.
            </p>
          </div>
        )}

        {appointments.length > 0 && (
          <div className="space-y-6">
            <h2 className="text-lg font-bold text-purple-950">
              Encontramos {appointments.length}{" "}
              {appointments.length === 1 ? "cita" : "citas"}
            </h2>

            {appointments.map((appointment) => {
              const status =
                statusConfig[appointment.status] || statusConfig.pending;
              const service = appointment.service || {};
              const business = service.business || {};
              const employee = service.employee || {};
              const client = appointment.client || {};

              return (
                <div
                  key={appointment.id}
                  className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden"
                >
                  {/* Encabezado con estado */}
                  <div className="flex items-center justify-between px-5 py-4 bg-purple-50/60 border-b border-purple-100">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-bold text-purple-950">
                        Cita #{appointment.id}
                      </span>
                    </div>
                    <span
                      className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border ${status.className}`}
                    >
                      {status.icon}
                      {status.label}
                    </span>
                  </div>

                  <div className="p-5 space-y-5">
                    {/* Servicio y negocio */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <InfoRow
                        icon={<Scissors className="w-4 h-4" />}
                        label="Servicio"
                        value={service.name || "Servicio"}
                      />
                      <InfoRow
                        icon={<Store className="w-4 h-4" />}
                        label="Salón / Estilista"
                        value={business.name || "No disponible"}
                      />
                      <InfoRow
                        icon={<User className="w-4 h-4" />}
                        label="Profesional"
                        value={employee.name || "Por asignar"}
                      />
                      <InfoRow
                        icon={<Package className="w-4 h-4" />}
                        label="Categoría"
                        value={service.category?.name || "General"}
                      />
                    </div>

                    {/* Fecha y hora */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-gray-100">
                      <InfoRow
                        icon={<Calendar className="w-4 h-4" />}
                        label="Fecha"
                        value={formatDate(appointment.date)}
                      />
                      <InfoRow
                        icon={<Clock className="w-4 h-4" />}
                        label="Horario"
                        value={`${formatTime(appointment.start_time)} - ${formatTime(
                          appointment.end_time
                        )}`}
                      />
                      <InfoRow
                        icon={<DollarSign className="w-4 h-4" />}
                        label="Precio total"
                        value={`$${Number(appointment.total_price || 0).toFixed(2)}`}
                      />
                    </div>

                    {/* Datos del cliente */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                      <InfoRow
                        icon={<User className="w-4 h-4" />}
                        label="Cliente"
                        value={client.name || "Cliente"}
                      />
                      <InfoRow
                        icon={<Phone className="w-4 h-4" />}
                        label="Teléfono"
                        value={client.phone || "No disponible"}
                      />
                    </div>

                    {/* Insumos consumidos */}
                    {appointment.consumed_products &&
                      appointment.consumed_products.length > 0 && (
                        <div className="pt-4 border-t border-gray-100">
                          <span className="flex items-center gap-1.5 text-xs font-bold text-purple-900 uppercase tracking-wider mb-2">
                            <Package className="w-3.5 h-3.5" /> Insumos
                            utilizados
                          </span>
                          <div className="flex flex-wrap gap-1.5">
                            {appointment.consumed_products.map((prod) => (
                              <span
                                key={prod.id}
                                className="bg-purple-50 border border-purple-100 text-purple-900 text-xs px-2.5 py-1 rounded-lg font-medium"
                              >
                                {prod.name}
                                {prod.pivot?.quantity_used
                                  ? ` x${prod.pivot.quantity_used}`
                                  : ""}
                              </span>
                            ))}
                          </div>
                        </div>
                      )}

                    {/* Notas */}
                    {appointment.notes && (
                      <div className="pt-4 border-t border-gray-100">
                        <InfoRow
                          icon={<StickyNote className="w-4 h-4" />}
                          label="Notas"
                          value={appointment.notes}
                        />
                      </div>
                    )}

                    {/* Precio total destacado */}
                    <div className="flex items-center justify-between pt-4 border-t border-gray-100">
                      <span className="flex items-center gap-2 text-gray-600 font-medium">
                        <DollarSign className="w-5 h-5 text-purple-700" />
                        Total de la cita
                      </span>
                      <span className="text-2xl font-extrabold text-purple-950">
                        ${Number(appointment.total_price || 0).toFixed(2)}
                      </span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </MainLayout>
  );
};

const InfoRow = ({ icon, label, value }) => (
  <div className="flex items-start gap-3">
    <div className="w-8 h-8 rounded-lg bg-purple-100 text-purple-800 flex items-center justify-center shrink-0">
      {icon}
    </div>
    <div className="min-w-0">
      <span className="block text-xs font-semibold text-gray-400 uppercase tracking-wider">
        {label}
      </span>
      <span className="block text-sm font-medium text-gray-800 break-words">
        {value}
      </span>
    </div>
  </div>
);

export default ConsultPage;