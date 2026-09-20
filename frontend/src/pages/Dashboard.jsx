import React from 'react';
import { useAuth } from '../context/AuthContext';

const Dashboard = () => {
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-gray-100 p-8">
      <div className="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-lg">
        <h1 className="text-3xl font-bold text-purple-950">Bienvenido al Dashboard</h1>
        <p className="mt-4">Hola, <span className="font-bold">{user?.name}</span>.</p>
        <p>Tu correo es: {user?.email}</p>
        
        <button 
          onClick={logout}
          className="mt-6 bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition"
        >
          Cerrar Sesión
        </button>
      </div>
    </div>
  );
};

export default Dashboard;