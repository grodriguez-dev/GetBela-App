import React from 'react';
import { Link } from 'react-router-dom';

const SettingsHub = () => {
  return (
    <div className="p-8">
      <h1 className="text-3xl font-bold text-purple-950 mb-8">Configuración</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Link 
          to="/configuracion/business" 
          className="p-6 bg-white rounded-2xl shadow hover:shadow-lg transition border border-gray-100"
        >
          <h2 className="text-xl font-bold text-purple-950">💼 Datos del Negocio</h2>
          <p className="text-gray-500 mt-2">Edita el logo, nombre y detalles de tu perfil comercial.</p>
        </Link>
        <Link 
          to="/configuracion/business-hours" 
          className="p-6 bg-white rounded-2xl shadow hover:shadow-lg transition border border-gray-100"
        >
          <h2 className="text-xl font-bold text-purple-950">🕒 Horario del Negocio</h2>
          <p className="text-gray-500 mt-2">Dias de la semana, hora de apertura y de cierre.</p>
        </Link>
        {/* Más tarjetas irán aquí a futuro */}
      </div>
    </div>
  );
};

export default SettingsHub;