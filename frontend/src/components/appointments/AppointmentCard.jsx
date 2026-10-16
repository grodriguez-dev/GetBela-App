import React from "react";
import api from "../../api/axios";
import CountdownTimer from "./CountdownTimer";
import { Calendar, Clock, User, Phone, FileText, Play, CheckCircle, XCircle, Sparkles } from "lucide-react";

const AppointmentCard = ({ appointment, refreshList }) => {
  const handleAction = async (newStatus) => {
    try {
      await api.patch(`/api/appointments/${appointment.id}/status`, { status: newStatus });
      refreshList();
    } catch (error) {
      alert(error.response?.data?.message || "Error al actualizar la cita");
    }
  };

  // Limpiamos la fecha para que solo muestre Año-Mes-Día (YYYY-MM-DD)
  const cleanDate = appointment.date ? appointment.date.split("T")[0] : "";

  // Formatea el tiempo extra (en segundos) que duró la cita más allá de su hora
  // de fin programada. Devuelve null cuando no hubo tiempo extra para no mostrar nada.
  const formatOvertime = (totalSeconds) => {
    const seconds = Number(totalSeconds) || 0;
    if (seconds <= 0) return null;

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    const parts = [];
    if (hours > 0) parts.push(`${hours} h`);
    if (minutes > 0) parts.push(`${minutes} min`);
    if (secs > 0 || parts.length === 0) parts.push(`${secs} seg`);

    return parts.join(" ");
  };

  const overtimeLabel = formatOvertime(appointment.overtime_seconds);

  return (
    <div className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
      
      {/* Columna 1: Información del Cliente y Notas */}
      <div className="space-y-2 flex-1">
        <div className="flex items-center gap-2">
          <span className={`text-xs px-3 py-1 rounded-full font-bold uppercase tracking-wider ${
            appointment.status === 'completed' ? 'bg-green-100 text-green-800' :
            appointment.status === 'in_progress' ? 'bg-blue-100 text-blue-800 animate-pulse' :
            appointment.status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'
          }`}>
            {appointment.status}
          </span>
          <span className="text-xs text-black font-medium flex items-center gap-1">
            <Calendar className="w-3.5 h-3.5" /> {cleanDate}
          </span>
        </div>

        <h3 className="text-lg font-bold text-gray-900 flex items-center gap-2">
          <User className="w-4 h-4 text-purple-800" /> {appointment.client?.name}
        </h3>

        <div className="text-sm text-gray-600 flex items-center gap-4">
          <span className="flex items-center gap-1">
            <Phone className="w-3.5 h-3.5 text-gray-400" /> {appointment.client?.phone}
          </span>
        </div>

        {appointment.notes && (
          <div className="bg-gray-50 border border-gray-100 p-2.5 rounded-xl text-xs text-gray-600 flex items-start gap-2 mt-2">
            <FileText className="w-4 h-4 text-purple-700 shrink-0 mt-0.5" />
            <span><strong>Nota:</strong> {appointment.notes}</span>
          </div>
        )}

        {/* Estilista Asignado (Justo debajo del teléfono del cliente) */}
        <div className="pt-1 flex items-center gap-1.5 text-sm">
          <span className="text-gray-500 font-medium text-xs uppercase tracking-wider">Estilista:</span>
          <span className="font-bold text-purple-800">
            {appointment.service?.employee?.name || "No asignado"}
          </span>
        </div>
      </div>


      {/* Columna 2: Datos del Servicio e Insumos */}
      <div className="flex-1 border-t md:border-t-0 md:border-l border-gray-100 md:pl-6 pt-4 md:pt-0 space-y-2">
        <div>
          <span className="text-xs font-bold uppercase tracking-wider text-gray-400 block mb-1">Servicio contratado</span>
          <h4 className="font-extrabold text-purple-950 text-base">{appointment.service?.name}</h4>
        </div>

        {appointment.consumed_products && appointment.consumed_products.length > 0 && (
          <div>
            <span className="text-xs font-semibold text-purple-900 block mb-1 flex items-center gap-1">
              <Sparkles className="w-3 h-3" /> Insumos asociados:
            </span>
            <div className="flex flex-wrap gap-1">
              {appointment.consumed_products.map((prod) => (
                <span key={prod.id} className="bg-purple-50 border border-purple-100 text-purple-900 text-xs px-2 py-0.5 rounded-md">
                  {prod.name}
                </span>
              ))}
            </div>
          </div>
        )}

        <div className="flex items-center gap-3 text-xs font-medium text-gray-500 pt-2">
          {/* 2. SI ESTÁ COMPLETADA: Mostrar texto de Finalizado y, si aplica, el tiempo extra */}
          {appointment.status === 'completed' && (
            <div className="flex flex-wrap items-center gap-2">
              <div className="bg-green-50 border border-green-200 text-green-700 px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5">
                <span>Cita Finalizada</span>
              </div>
              {overtimeLabel && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5">
                  <Clock className="w-3.5 h-3.5" />
                  <span>Tiempo extra: +{overtimeLabel}</span>
                </div>
              )}
            </div>
          )}

          {/* 3. SI ESTÁ PENDIENTE O CONFIRMADA: Mostrar los horarios de inicio y fin */}
          {(appointment.status === 'pending' || appointment.status === 'confirmed') && (
            <div className="flex items-center gap-3 text-xs font-medium text-gray-500">
              <span className="flex items-center gap-1">
                <Clock className="w-3.5 h-3.5 text-purple-800" /> Inicio: {appointment.start_time}
              </span>
              <span>•</span>
              <span>Fin aprox: {appointment.end_time}</span>
            </div>
          )}

          {/* Si está cancelada, no se muestra ningún bloque de tiempo arriba */}
        </div>
      </div>

      {/* Columna 3: Contador Dinámico y Botones de Acción según el estado */}
      <div className="flex flex-col items-end justify-between gap-4 w-full md:w-auto border-t md:border-t-0 pt-4 md:pt-0">
        
        {appointment.status === 'in_progress' && (
          <CountdownTimer endTime={appointment.end_time} />
        )}

        <div className="flex items-center gap-2 w-full md:w-auto">
          
          {/* SI ESTÁ PENDIENTE: Mostrar Confirmar y Rechazar/Cancelar */}
          {appointment.status === 'pending' && (
            <>
              <button 
                onClick={() => handleAction('confirmed')}
                className="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5"
              >
                <CheckCircle className="w-3.5 h-3.5" /> Confirmar
              </button>
              <button 
                onClick={() => handleAction('cancelled')}
                className="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-bold text-xs px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5"
              >
                <XCircle className="w-3.5 h-3.5" /> Rechazar
              </button>
            </>
          )}

          {/* SI ESTÁ CONFIRMADA: Mostrar Empezar y Cancelar */}
          {appointment.status === 'confirmed' && (
            <>
              <button 
                onClick={() => handleAction('in_progress')}
                className="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5"
              >
                <Play className="w-3.5 h-3.5" /> Empezar
              </button>
              <button 
                onClick={() => handleAction('cancelled')}
                className="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-bold text-xs px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5"
              >
                <XCircle className="w-3.5 h-3.5" /> Cancelar
              </button>
            </>
          )}

          {/* SI ESTÁ EN PROGRESO: Mostrar botón para Completar y opción de Cancelar */}
          {appointment.status === 'in_progress' && (
            <>
              <button 
                onClick={() => handleAction('completed')}
                className="bg-green-600 hover:bg-green-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5"
              >
                <CheckCircle className="w-3.5 h-3.5" /> Completar
              </button>
              <button 
                onClick={() => handleAction('cancelled')}
                className="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-bold text-xs px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5"
              >
                <XCircle className="w-3.5 h-3.5" /> Cancelar
              </button>
            </>
          )}

        </div>
      </div>

    </div>
  );
};

export default AppointmentCard;