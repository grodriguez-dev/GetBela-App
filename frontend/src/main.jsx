// src/main.jsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import App from './App';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import DashboardLayout from './layouts/DashboardLayout';
import ProtectedRoute from './components/ProtectedRoute';
import { AuthProvider } from './context/AuthContext';
import { SearchProvider } from './context/SearchContext';
import SettingsHub from './pages/Configuracion/SettingsHub';
import BusinessSettings from './pages/Configuracion/BusinessSettings';
import BusinessHours from './pages/Configuracion/BusinessHours';
import EmployeeList from './pages/employees/EmployeeList';
import ServiceList from './pages/services/ServiceList';
import ProductList from './pages/products/ProductList';
import HomeCatalog from "./pages/public/HomeCatalog";
import ServiceDetail from "./pages/public/ServiceDetail";
import AppointmentsList from "./pages/appointments/AppointmentsList";
import BusinessProfileView from "./pages/public/BusinessProfileView";
import PostView from "./pages/sales/PostView";
import CategoriesList from './pages/admin/CategoriesList';
import BannersList from './pages/admin/BannersList';
import ContactList from './pages/admin/ContactList';
import ContactPage from './pages/public/ContactPage';
import ConsultPage from './pages/public/ConsultPage';
import './index.css';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <AuthProvider>
      <SearchProvider>
        <BrowserRouter>
          <Routes>
            {/* Rutas públicas */}
            <Route>
              <Route path="login" element={<Login />} />
              <Route path="register" element={<Register />} />
              <Route path="/" element={<HomeCatalog />} />
              <Route path="contacto" element={<ContactPage />} />
              <Route path="consultar" element={<ConsultPage />} />
              <Route path="servicio/:id" element={<ServiceDetail />} />
              <Route path="business/:id" element={<BusinessProfileView />} />
            </Route>

            {/* Rutas protegidas dentro del Layout */}
            <Route element={<ProtectedRoute><DashboardLayout /></ProtectedRoute>}>
              <Route path="dashboard" element={<Dashboard />} />
              <Route path="employees" element={<EmployeeList />} />
              <Route path="/services" element={<ServiceList />} />
              <Route path="/products" element={<ProductList />} />
              <Route path="/appointments" element={<AppointmentsList />} />
              <Route path="/postview" element={<PostView />} />
              
              {/* Rutas del Panel Super Admin */}
              <Route path="admin/categories" element={<CategoriesList />} />
              <Route path="admin/banners" element={<BannersList />} />
              <Route path="admin/contacts" element={<ContactList />} />
              
              <Route path="configuracion">
              <Route index element={<SettingsHub />} />
                <Route path="business" element={<BusinessSettings />} />
                <Route path="business-hours" element={<BusinessHours />} />
              </Route>
            </Route>

          </Routes>
        </BrowserRouter>
      </SearchProvider>
    </AuthProvider>
  </React.StrictMode>
);