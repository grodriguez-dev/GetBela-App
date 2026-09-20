import React, { useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import logo from '../assets/logo.png';

const Login = () => {
  const [showPassword, setShowPassword] = useState(false);
  const [formData, setFormData] = useState({ email: '', password: '' });
  const [errors, setErrors] = useState({}); // 1. Estado para errores
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({}); // Limpiamos errores previos
    try {
      await login(formData);
      navigate('/dashboard');
    } catch (error) {
      // 2. Capturamos errores de validación (422) o credenciales incorrectas
      if (error.response && error.response.status === 422) {
        setErrors(error.response.data.errors);
      } else {
        alert("Error al iniciar sesión. Verifica tus credenciales.");
      }
    }
  };

  return (
    <div className="min-h-screen bg-purple-950 flex items-center justify-center p-6">
      <div className="bg-white p-8 rounded-3xl shadow-2xl w-full max-w-md border-t-4 border-purple-950">
        
        <div className="flex justify-center mb-4">
          <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center text-gray-400">
            <div className="logo-container">
                <img src={logo} alt="Logo de la aplicación" className="logo-img" />
            </div>
          </div>
        </div>

        <h2 className="text-3xl font-bold text-purple-950 mb-8 text-center">Login</h2>
        
        <form onSubmit={handleSubmit} className="space-y-6"> {/* 3. Ajuste de espacio */}
          
          {/* Email con error */}
          <div className="relative">
            <input 
              name="email" 
              placeholder="Correo electrónico" 
              onChange={handleChange} 
              className={`w-full p-3 border rounded-xl ${errors.email ? 'input-error' : ''}`}
            />
            {errors.email && <span className="error-message">{errors.email[0]}</span>}
          </div>
          
          {/* Contraseña con error */}
          <div className="relative">
            <input 
              type={showPassword ? "text" : "password"} 
              name="password" 
              placeholder="Contraseña" 
              onChange={handleChange} 
              className={`w-full p-3 border rounded-xl pr-10 ${errors.password ? 'input-error' : ''}`}
            />
            <button 
              type="button" 
              onClick={() => setShowPassword(!showPassword)} 
              className="absolute right-3 top-3.5 text-gray-400"
            >
              {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
            </button>
            {errors.password && <span className="error-message">{errors.password[0]}</span>}
          </div>

          <button type="submit" className="w-full bg-purple-950 text-white font-bold py-3 rounded-xl hover:bg-purple-900 transition">
            Ingresar
          </button>
          
          <p className="text-center text-sm text-gray-500 mt-4">
            ¿No tienes cuenta? <Link to="/register" className="text-purple-900 font-bold">Regístrate aquí</Link>
          </p>
        </form>
      </div>
    </div>
  );
};

export default Login;