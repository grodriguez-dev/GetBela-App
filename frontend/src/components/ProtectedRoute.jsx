import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const ProtectedRoute = ({ children }) => {
  const { user, loading } = useAuth();

  // Mientras el AuthContext verifica el usuario, mostramos un cargando
  if (loading) return <div>Cargando...</div>;

  // Si no hay usuario, redirigimos al login
  if (!user) return <Navigate to="/login" />;

  // Si hay usuario, permitimos ver el Dashboard
  return children;
};

export default ProtectedRoute;