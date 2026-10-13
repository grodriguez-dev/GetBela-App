import React, { useState, useEffect } from "react";
import { Outlet, Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { ChevronDown, LogOut, Settings, Bell } from "lucide-react";
import Sidebar from "../components/Sidebar";
import api from "../api/axios";

const DashboardLayout = () => {
  const { user, logout } = useAuth();
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [notifDropdownOpen, setNotifDropdownOpen] = useState(false);
  const navigate = useNavigate();

  // Accedemos a la relación: user -> business -> campo
  // Usamos el encadenamiento opcional (?.) para evitar errores si el objeto no ha cargado
  const businessId = user?.business?.id;
  const businessName = user?.business?.name || "Nombre del Negocio";
  const businessLogo = user?.business?.logo; // O el nombre del campo que uses para la ruta de la imagen

  const fetchNotifications = async () => {
    if (!businessId) return;

    try {
      const response = await api.get(`/api/business/${businessId}/notifications`);
      setNotifications(response.data.notifications);
      setUnreadCount(response.data.unread_count);
    } catch (error) {
      console.error("Error al cargar las notificaciones:", error);
    }
  };

  useEffect(() => {
    fetchNotifications();

    const interval = setInterval(fetchNotifications, 30000);
    return () => clearInterval(interval);
  }, [businessId]);

  const handleMarkAsRead = async (id) => {
    try {
      await api.patch(`/api/notifications/${id}/read`);

      setNotifications((prev) =>
        prev.map((notif) =>
          notif.id === id
            ? { ...notif, read_at: new Date().toISOString() }
            : notif,
        ),
      );

      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (error) {
      console.error("Error al marcar como leída:", error);
    }
  };

  // NUEVA FUNCIÓN: Maneja el clic en la notificación, marca leída, cierra el menú y redirige a citas
  const handleNotificationClick = async (notif) => {
    if (!notif.read_at) {
      await handleMarkAsRead(notif.id);
    }

    setNotifDropdownOpen(false);

    if (notif.appointment_id) {
      navigate(`/appointments?highlight=${notif.appointment_id}`);
    } else {
      navigate('/appointments');
    }
  };

  return (
    <div className="flex flex-col min-h-screen bg-gray-50">
      {/* NAVBAR */}
      <header className="h-16 bg-purple-950 flex items-center justify-between px-8 text-white shadow-md z-50 sticky top-0">
        <div className="font-bold text-xl">{businessName}</div>

        <div className="flex items-center space-x-4">
          {/* 🔔 CAMPANA DE NOTIFICACIONES */}
          <div className="relative">
            <button
              onClick={() => setNotifDropdownOpen(!notifDropdownOpen)}
              className="relative p-2 rounded-lg hover:bg-purple-900 transition-colors text-white focus:outline-none flex items-center justify-center"
              aria-label="Notificaciones"
            >
              <Bell size={20} />

              {/* Badge Rojo de No Leídas */}
              {unreadCount > 0 && (
                <span className="absolute top-1 right-1 bg-red-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center shadow-sm">
                  {unreadCount}
                </span>
              )}
            </button>

            {/* Menú Desplegable (Dropdown) de Notificaciones */}
            {notifDropdownOpen && (
              <div className="absolute right-0 mt-3 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-purple-100 py-3 text-gray-800 z-50">
                <div className="px-4 pb-2 border-b border-gray-100 flex justify-between items-center">
                  <h3 className="font-bold text-purple-950 text-sm">
                    Notificaciones de Citas
                  </h3>
                  <span className="text-xs text-gray-400 font-medium">
                    {unreadCount} nuevas
                  </span>
                </div>

                <div className="max-h-80 overflow-y-auto divide-y divide-gray-50">
                  {notifications.length > 0 ? (
                    notifications.map((notif) => (
                      <div
                        key={notif.id}
                        onClick={() => handleNotificationClick(notif)}
                        className={`p-3 transition-colors cursor-pointer flex gap-3 items-start ${
                          notif.read_at
                            ? "bg-white opacity-70"
                            : "bg-purple-50/60 hover:bg-purple-100/50"
                        }`}
                      >
                        {/* Puntito indicador si no está leída */}
                        <span
                          className={`w-2 h-2 mt-1.5 rounded-full flex-shrink-0 ${notif.read_at ? "bg-transparent" : "bg-purple-600"}`}
                        />

                        <div className="flex-1">
                          <p className="text-xs text-gray-800 font-medium leading-relaxed">
                            {notif.message}
                          </p>
                          <span className="text-[10px] text-gray-400 mt-1 block">
                            {new Date(notif.created_at).toLocaleTimeString([], {
                              hour: "2-digit",
                              minute: "2-digit",
                            })}{" "}
                            - {new Date(notif.created_at).toLocaleDateString()}
                          </span>
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="py-8 text-center text-xs text-gray-400 italic">
                      No tienes notificaciones recientes.
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          <div className="relative">
            <button
              onClick={() => setDropdownOpen(!dropdownOpen)}
              className="flex items-center gap-2 hover:bg-purple-900 px-3 py-2 rounded-lg transition"
            >
              <span>{user?.name}</span>
              <ChevronDown size={18} />
            </button>

            {dropdownOpen && (
              <div className="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg py-2 text-gray-800 z-50">
                {user?.role !== 'employee' && (
                  <Link
                    to="/configuracion"
                    className="flex items-center gap-2 px-4 py-2 hover:bg-gray-100"
                  >
                    <Settings size={16} /> Configuración
                  </Link>
                )}
                <button
                  onClick={logout}
                  className="flex items-center gap-2 w-full px-4 py-2 text-red-600 hover:bg-gray-100"
                >
                  <LogOut size={16} /> Cerrar Sesión
                </button>
              </div>
            )}
          </div>
        </div>
      </header>

      {/* CUERPO: Sidebar + Contenido */}
      <div className="flex flex-1 min-h-0">
        <Sidebar />

        <main className="flex-1 p-6 flex justify-center overflow-y-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default DashboardLayout;