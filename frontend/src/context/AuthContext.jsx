import { createContext, useContext, useState, useEffect } from "react";
import api from "../api/axios";

const AuthContext = createContext({});

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Función para obtener el usuario actual (útil al recargar la página)
  const getUser = async () => {
    try {
      const token = localStorage.getItem("getbela_token");

      if (token) {
        api.defaults.headers.common.Authorization = `Bearer ${token}`;
      }

      const { data } = await api.get("/api/user");
      setUser(data);
    } catch (error) {
      localStorage.removeItem("getbela_token");
      delete api.defaults.headers.common.Authorization;
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getUser();
  }, []);

  const login = async (formData) => {
    await api.get("/sanctum/csrf-cookie");

    const payload = {
      email: String(formData?.email ?? "")
        .trim()
        .toLowerCase(),
      password: String(formData?.password ?? "").trim(),
    };

    const response = await api.post("/api/login", payload);
    const token = response.data?.access_token;

    if (token) {
      localStorage.setItem("getbela_token", token);
      api.defaults.headers.common.Authorization = `Bearer ${token}`;
    }

    await getUser();
  };

  const logout = async () => {
    await api.post("/api/logout");
    localStorage.removeItem("getbela_token");
    delete api.defaults.headers.common.Authorization;
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
