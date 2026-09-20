import React, { useState } from "react";
import { Eye, EyeOff } from "lucide-react";
import { useNavigate, Link } from "react-router-dom"; // 1. Importamos herramientas de navegación
import { useAuth } from "../context/AuthContext"; // 2. Importamos el AuthContext
import BusinessForm from "../components/BusinessForm";
import logo from "../assets/logo.png";
import api from "../api/axios";

const Register = () => {
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [strength, setStrength] = useState(0);
  const [showBusinessSetup, setShowBusinessSetup] = useState(false); 
  const [registeredData, setRegisteredData] = useState(null);
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    business_name: "",
    business_business_type: "",
    modality: "",
  });
  const [errors, setErrors] = useState({});

  const { login } = useAuth(); // 3. Usamos el login del contexto
  const navigate = useNavigate(); // Para redirigir al dashboard

  const calculateStrength = (password) => {
    let score = 0;
    if (password.length > 8) score += 25;
    if (/[A-Z]/.test(password)) score += 25;
    if (/[0-9]/.test(password)) score += 25;
    if (/[^A-Za-z0-9]/.test(password)) score += 25;
    return score;
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
    if (name === "password") setStrength(calculateStrength(value));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});
    if (formData.password !== formData.password_confirmation) {
      setErrors({
        password_confirmation: ["La contraseña no coincide"]
      });
      return; // Detenemos la ejecución si no coinciden
    }
    try {
      // Primero registramos al usuario en la base de datos
      await api.post("/api/register", formData);

      // Luego, usamos la función 'login' del AuthContext para iniciar sesión automáticamente
      await login({ email: formData.email, password: formData.password });

      setRegisteredData({
        name: formData.business_name,
        type: formData.business_type,
        modality: formData.modality,
        description: "",
        address: "",
        phone: "",
        logo: ""
      });
      setShowBusinessSetup(true);
    } catch (error) {
      if (error.response && error.response.status === 422) {
        setErrors(error.response.data.errors);
      }
    }
  };

  const handleFinalSetup = async (formData) => {
    try {
      // Usamos el endpoint que crearemos en el backend
      // 'formData' aquí ya es el objeto FormData completo listo para subir
      await api.post("/api/business/update", formData, {
        headers: { 
          'Content-Type': 'multipart/form-data' 
        }
      });

      // Si todo sale bien, ahora sí lo enviamos al dashboard
      navigate("/dashboard");
    } catch (error) {
      if (error.response && error.response.status === 422) {
        setErrors(error.response.data.errors);
      }
    }
  };

  const ErrorMessage = ({ message }) => (
    <span
      style={{
        color: "red",
        fontSize: "12px",
        marginTop: "5px",
        display: "block",
      }}
    >
      {message}
    </span>
  );

  return (
    <div className="min-h-screen bg-purple-950 flex items-center justify-center p-6">
      <div className="bg-white p-8 rounded-3xl shadow-2xl w-full max-w-2xl border-t-4 border-purple-950">
        {/* Espacio reservado para tu logo */}
        {!showBusinessSetup && (
          <>
          <div className="flex justify-center mb-4">
            <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center text-gray-400">
              <div className="logo-container">
                <img
                  src={logo}
                  alt="Logo de la aplicación"
                  className="logo-img"
                />
              </div>
            </div>
          </div>
          <h2 className="text-3xl font-bold text-purple-950 mb-8 text-center">
            ¡Regístrate!
          </h2>
          </>
        )}
        {!showBusinessSetup ? (
        <form onSubmit={handleSubmit} className="space-y-6"> {/* Aumenté space-y para dar aire */}
  
          {/* Nombre */}
          <div className="relative">
            <input
              name="name"
              placeholder="Nombre completo"
              onChange={handleChange}
              className={`w-full p-3 border rounded-xl ${errors.name ? 'input-error' : ''}`}
            />
            {errors.name && <span className="error-message">{errors.name[0]}</span>}
          </div>

          {/* Nombre del Salón */}
          <div className="relative">
            <input
              name="business_name"
              placeholder="Nombre del Salón/Estilista"
              onChange={handleChange}
              className={`w-full p-3 border rounded-xl ${errors.business_name ? 'input-error' : ''}`}
            />
            {errors.business_name && <span className="error-message">{errors.business_name[0]}</span>}
          </div>

          {/* Correo y Tipo de negocio */}
          <div className="grid grid-cols-2 gap-4">
            <div className="relative">
              <input
                name="email"
                placeholder="Correo electrónico"
                onChange={handleChange}
                className={`w-full p-3 border rounded-xl ${errors.email ? 'input-error' : ''}`}
              />
              {errors.email && <span className="error-message">{errors.email[0]}</span>}
            </div>
            <div className="relative">
              <select
                name="business_type"
                onChange={handleChange}
                className={`w-full p-3 border rounded-xl text-gray-500 ${errors.business_type ? 'input-error' : ''}`}
              >
                <option value="">Tipo de negocio</option>
                <option value="salon">Salón de Belleza</option>
                <option value="independent">Estilista Independiente</option>
              </select>
              {errors.business_type && <span className="error-message">{errors.business_type[0]}</span>}
            </div>
          </div>

         {/* Sección de Contraseñas */}
          <div className="grid grid-cols-2 gap-4">
            
            {/* Campo Contraseña */}
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
                className="absolute right-3 top-3.5 text-gray-400 hover:text-purple-950 transition"
              >
                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
              </button>
              {errors.password && <span className="error-message">{errors.password[0]}</span>}
            </div>

            {/* Campo Confirmar Contraseña */}
            <div className="relative">
              <input
                type={showConfirmPassword ? "text" : "password"}
                name="password_confirmation"
                placeholder="Confirmar contraseña"
                onChange={handleChange}
                className={`w-full p-3 border rounded-xl pr-10 ${errors.password_confirmation ? 'input-error' : ''}`}
              />
              <button
                type="button"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                className="absolute right-3 top-3.5 text-gray-400 hover:text-purple-950 transition"
              >
                {showConfirmPassword ? <EyeOff size={20} /> : <Eye size={20} />}
              </button>
              {errors.password_confirmation && (
                <span className="error-message">{errors.password_confirmation[0]}</span>
              )}
            </div>
          </div>

          {/* Barra de seguridad */}
          <div className="w-full h-2 bg-gray-200 rounded-full overflow-hidden mt-2">
            <div
              className={`h-full transition-all duration-300 ${
                strength < 50 ? "bg-red-500" : strength < 100 ? "bg-yellow-500" : "bg-green-500"
              }`}
              style={{ width: `${strength}%` }}
            ></div>
          </div>

          {/* Modalidad */}
          <div className="relative">
            <select
              name="modality"
              onChange={handleChange}
              className={`w-full p-3 border rounded-xl text-gray-500 ${errors.modality ? 'input-error' : ''}`}
            >
              <option value="">Modalidad</option>
              <option value="local">En Local</option>
              <option value="domicilio">A Domicilio</option>
              <option value="hibrido">Híbrido</option>
            </select>
            {errors.modality && <span className="error-message">{errors.modality[0]}</span>}
          </div>

          <button type="submit" className="w-full bg-purple-950 text-white font-bold py-3 rounded-xl">
            Crear cuenta
          </button>
        </form>
        ) : (
        <BusinessForm 
          initialData={registeredData} 
          isEditable={false} 
          title="Completa tu perfil"
          onSubmit={handleFinalSetup} // Esta función la definiremos en el siguiente paso
          errors={errors}
        />
      )}
      </div>
    </div>
  );
};

export default Register;
