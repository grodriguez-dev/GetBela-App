// src/App.jsx
import { Outlet } from 'react-router-dom';

function App() {
  return (
    <div className="bg-purple-950 min-h-screen">
      <Outlet /> {/* Aquí es donde se renderizarán tus rutas */}
    </div>
  );
}

export default App;