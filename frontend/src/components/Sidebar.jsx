import React from "react";
import { useLocation, Link } from "react-router-dom";
import { useAuth } from "../context/AuthContext"; // Tu contexto

const Sidebar = () => {
  const { user } = useAuth(); // Obtenemos el usuario aquí
  const location = useLocation();
  const isSettings = location.pathname.startsWith("/configuracion");

  return (
    <aside className="w-64 bg-purple-950 min-h-screen p-6 text-white">
      {/* Visualización del Logo del Negocio */}
      {user?.business && (
        <div className="flex flex-col items-center mb-8">
          <div className="w-20 h-20 rounded-full border-4 border-purple-800 bg-purple-900 flex items-center justify-center overflow-hidden mb-3 shadow-lg">
            {user?.business?.logo ? (
              <img
                src={`http://localhost:8000/storage/${user.business.logo}`}
                alt="Logo"
                className="w-full h-full object-cover"
                onError={(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'block'; }}
              />
            ):(
              <span className="text-4xl font-bold text-white">
                {user.business.name.charAt(0).toUpperCase()}
              </span>
            )}
          </div>
        </div>
      )}

      <nav className="space-y-4">
        {isSettings ? (
          <>
            <Link to="/dashboard" className="block text-purple-300">
              ⬅️ Volver
            </Link>
            <div className="border-t border-purple-800 my-4"></div>
            <Link to="/configuracion/business" className="block font-bold">
              💼 Mi Negocio
            </Link>
            <Link to="/configuracion/business-hours" className="block font-bold">
              🕒 Mi Horario
            </Link>
          </>
        ) : (
          <>
            <Link to="/dashboard" className="block">
              📊 Dashboard
            </Link>
            
            {/* Enlaces exclusivos para Super Admin */}
            {user?.role === 'admin' && (
              <>
                <Link to="/admin/categories" className="block text-purple-300">
                  🗂️ Categorías
                </Link>
                <Link to="/admin/banners" className="block text-purple-300">
                  🖼️ Carrusel
                </Link>
                <Link to="/admin/contacts" className="block text-purple-300">
                  📞 Contacto
                </Link>
                <div className="border-t border-purple-800 my-2"></div>
              </>
            )}
            
            {/* Enlaces para owner/employee */}
            <Link to="/appointments" className="block text-purple-300">
              📅 Citas
            </Link>
            <Link to="/configuracion" className="block text-purple-300">
              ⚙️ Configuración
            </Link>
            <Link to="/employees" className="block text-purple-300">
              👔 Empleados
            </Link>
            <Link to="/services" className="block text-purple-300">
              💅 Servicios
            </Link>
            <Link to="/products" className="block text-purple-300">
              🧴 Productos
            </Link>
            <Link to="/postview" className="block text-purple-300">
              🛒 Venta
            </Link>
          </>
        )}
      </nav>
    </aside>
  );
};

export default Sidebar;
