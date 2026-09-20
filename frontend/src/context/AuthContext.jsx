import { createContext, useContext, useState, useEffect } from 'react';
import api from '../api/axios';

const AuthContext = createContext({});

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Función para obtener el usuario actual (útil al recargar la página)
  const getUser = async () => {
    try {
      const { data } = await api.get('/api/user');
      setUser(data);
    } catch (error) {
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getUser();
  }, []);

  const login = async (formData) => {
    await api.get('/sanctum/csrf-cookie');
    await api.post('/api/login', formData);
    await getUser();
  };

  const logout = async () => {
    await api.post('/api/logout');
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);