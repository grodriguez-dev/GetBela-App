import React from "react";
import logo from '../assets/logo1.png';
import { useSearch } from "../context/SearchContext";

const MainLayout = ({ children }) => {
  const { searchQuery, setSearchQuery } = useSearch();

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      {/* NAVBAR COMPARTIDO */}
      <header className="bg-purple-950 text-white shadow-md sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
          
          {/* Logo y Nombre GetBela */}
          <div className="flex items-center gap-2 cursor-pointer">
            <img 
              src={logo} 
              alt="GetBela Logo" 
              className="w-15 h-15 object-contain rounded-full bg-white/10 p-1" 
              onError={(e) => { e.target.style.display = 'none'; }}
            />
            <a href="/" className="text-xl font-bold tracking-wider text-white">GetBela</a>
          </div>

          {/* Buscador Independiente Global */}
          <div className="flex-1 max-w-md relative hidden sm:block">
            <input
              type="text"
              placeholder="🔍 Buscar servicios, salones o estilistas..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-purple-900 text-white placeholder-purple-300 text-sm rounded-full px-4 py-2 border border-purple-800 focus:outline-none focus:border-purple-500"
            />
          </div>

          {/* Enlaces de Navegación */}
          <div className="flex items-center gap-6 text-sm font-medium">
            <a href="#conocenos" className="hover:text-purple-300 transition-colors hidden md:inline">Conócenos</a>
            <a href="#contacto" className="hover:text-purple-300 transition-colors hidden md:inline">Contáctanos</a>
            <a href="/login" className="bg-purple-900 hover:bg-purple-800 border border-purple-700 px-4 py-1.5 rounded-lg transition-colors">Ingresar</a>
          </div>

        </div>
      </header>

      {/* Input móvil */}
      <div className="bg-purple-950 px-4 pb-4 sm:hidden">
        <input
          type="text"
          placeholder="🔍 Buscar servicios, salones..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="w-full bg-purple-900 text-white placeholder-purple-300 text-sm rounded-full px-4 py-2 border border-purple-800 focus:outline-none"
        />
      </div>

      {/* CONTENIDO DINÁMICO */}
      <main className="flex-1 w-full">
        {children}
      </main>

      {/* FOOTER COMPARTIDO */}
      <footer className="bg-purple-950 text-purple-300 text-center py-6 mt-12 text-sm border-t border-purple-900">
        <p>&copy; {new Date().getFullYear()} GetBela. Todos los derechos reservados.</p>
      </footer>
    </div>
  );
};

export default MainLayout;