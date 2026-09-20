import React, { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import api from "../../api/axios";
import { Calendar, Clock, User, Phone, FileText, Play, XCircle, Sparkles } from "lucide-react";
import AppointmentCard from "../../components/appointments/AppointmentCard";

const AppointmentsList = () => {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchParams] = useSearchParams();
  const highlightId = searchParams.get("highlight");

  const fetchAppointments = async () => {
    try {
      const response = await api.get("/api/appointments");
      // Si usas paginación de Laravel, recuerda que los datos suelen venir en response.data.data o response.data
      setAppointments(response.data.data || response.data);
    } catch (error) {
      console.error("Error al obtener las citas", error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAppointments();
  }, []);

  useEffect(() => {
    if (highlightId && appointments.length > 0) {
      // Damos un pequeño respiro para asegurarnos de que el DOM ya renderizó las tarjetas
      const timer = setTimeout(() => {
        const element = document.getElementById(`appointment-${highlightId}`);
        
        if (element) {
          // Hacemos scroll suave hasta centrar la cita en pantalla
          element.scrollIntoView({ behavior: "smooth", block: "center" });
          
          // Agregamos clases de Tailwind para un borde brillante temporal
          element.classList.add("ring-4", "ring-purple-500", "bg-purple-50/60", "transition-all", "duration-500");
          
          // Removemos el efecto después de 3 segundos para que vuelva a la normalidad
          setTimeout(() => {
            element.classList.remove("ring-4", "ring-purple-500", "bg-purple-50/60");
          }, 3000);
        }
      }, 100);

      return () => clearTimeout(timer);
    }
  }, [highlightId, appointments]);

  if (loading) {
    return <div className="text-center py-20 text-purple-950 font-medium">Cargando historial de citas...</div>;
  }

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-extrabold text-purple-950">Historial y Gestión de Citas</h1>
      </div>

      <div className="space-y-4">
        {appointments.length === 0 ? (
          <div className="text-center bg-white p-8 rounded-2xl border border-gray-200 text-gray-500">
            No hay citas registradas en el sistema.
          </div>
        ) : (
          appointments.map((appointment) => (
            <div 
              key={appointment.id} 
              // 4. Asignamos un ID único a cada contenedor para que el DOM lo localice fácilmente
              id={`appointment-${appointment.id}`} 
              className="rounded-2xl transition-all"
            >
              <AppointmentCard 
                appointment={appointment} 
                refreshList={fetchAppointments} 
              />
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default AppointmentsList;