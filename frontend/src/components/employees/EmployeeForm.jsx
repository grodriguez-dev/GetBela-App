import React, { useState } from 'react';
import PhoneInput from '../PhoneInput';

const EmployeeForm = ({ initialData, onSubmit, errors = {} }) => {
  const [formData, setFormData] = useState(initialData || {
    name: '', specialty: '', phone: '', commission_percentage: '', is_active: true,
    give_access: false, email: '', password: ''
  });

  const renderError = (field) => {
        return errors[field] ? (
            <span className="text-red-500 text-xs mt-1 block">{errors[field][0]}</span>
        ) : null;
    };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;

    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));

  };

  return (
    <form onSubmit={(e) => {
                e.preventDefault(); // Evita que la página se recargue
                onSubmit(formData); // Envía los datos a la función del padre
            }}
          className="grid grid-cols-2 gap-4"
    >
      <div className="col-span-1">
        <label className="block text-sm font-medium">Nombre</label>
        <input name="name" value={formData.name} onChange={handleChange} className="w-full border p-2 rounded" required />
        {renderError('name')}
      </div>
      <div className="col-span-1">
        <label className="block text-sm font-medium">Especialidad</label>
        <input name="specialty" value={formData.specialty} onChange={handleChange} className="w-full border p-2 rounded" />
        {renderError('specialty')}
      </div>
      <div className="col-span-1">
        <label className="block text-sm font-medium">Teléfono</label>
        <PhoneInput
            name="phone"
            value={formData.phone}
            onChange={(val) => setFormData(prev => ({ ...prev, phone: val }))}
            invalid={!!errors.phone}
        />
        {renderError('phone')}
      </div>
      <div className="col-span-1">
        <label className="block text-sm font-medium">Comisión (%)</label>
        <div className="flex border rounded overflow-hidden">
          <input name="commission_percentage" type="number" value={formData.commission_percentage} onChange={handleChange} className="w-full p-2 outline-none" />
          <span className="bg-gray-100 p-2 text-gray-500 border-l">%</span>
          {renderError('commission_percentage')}
        </div>
      </div>

      {/* Switch de Estado Activo/Inactivo */}
      <div className="col-span-2 flex items-center justify-between bg-gray-50 p-3 rounded-lg border mb-2">
        <span className="font-medium text-gray-700">¿Empleado activo?</span>
        <button
            type="button"
            onClick={() => setFormData(prev => ({ ...prev, is_active: !prev.is_active }))}
            className={`${
            formData.is_active ? 'bg-green-600' : 'bg-gray-300'
            } relative inline-flex h-6 w-11 items-center rounded-full transition-colors`}
        >
            <span className={`${
            formData.is_active ? 'translate-x-6' : 'translate-x-1'
            } inline-block h-4 w-4 transform rounded-full bg-white transition-transform`} />
        </button>
      </div>
      
      {/* Switch de acceso */}
      {formData.is_active && (
        <div className="col-span-2 flex items-center justify-between bg-gray-50 p-3 rounded-lg border">
            <span className="font-medium text-gray-700">¿Dar acceso a la plataforma?</span>
            <button
                type="button"
                onClick={() => setFormData(prev => ({ ...prev, give_access: !prev.give_access }))}
                className={`${
                formData.give_access ? 'bg-purple-600' : 'bg-gray-300'
                } relative inline-flex h-6 w-11 items-center rounded-full transition-colors`}
            >
                <span className={`${
                formData.give_access ? 'translate-x-6' : 'translate-x-1'
                } inline-block h-4 w-4 transform rounded-full bg-white transition-transform`} />
            </button>
            {renderError('give_access')}
        </div>
        )}

      {/* Campos de usuario (solo si give_access es true) */}
      {formData.give_access && (
        <>
          <div className="col-span-2 bg-gray-50 p-4 rounded border">
            <p className="text-sm text-gray-600 mb-2">Usuario vinculado a: <strong>{formData.name}</strong></p>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm">Email</label>
                <input name="email" type="email" value={formData.email} onChange={handleChange} className="w-full border p-2 rounded" />
                {renderError('email')}
              </div>
              <div>
                <label className="block text-sm">Password</label>
                <input name="password" type="password" value={formData.password} onChange={handleChange} className="w-full border p-2 rounded" />
                {renderError('password')}
              </div>
            </div>
          </div>
        </>
      )}

      <button type="submit" className="col-span-2 bg-purple-600 text-white py-2 rounded-lg mt-4">
        Guardar Empleado
      </button>
    </form>
  );
};

export default EmployeeForm;