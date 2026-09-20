import React, { useState, useEffect } from 'react';
import EmployeeForm from '../../components/employees/EmployeeForm';
import api from "../../api/axios"; 

const EmployeeList = () => {
  const [employees, setEmployees] = useState([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState(null);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    fetchEmployees();
  }, []);

  const handleSaveEmployee = async (data) => {
    try {
        setErrors({});
        if (selectedEmployee) {
        // Si hay un empleado seleccionado, es edición (PUT)
        await api.put(`/api/employees/${selectedEmployee.id}`, data);
        } else {
        // Si no, es creación (POST)
        await api.post('/api/employees', data);
        }
        setIsModalOpen(false); // Cierra el modal
        fetchEmployees();      // Recarga la lista
    } catch (error) {
        if (error.response && error.response.status === 422) {
            // Si es error 422, guardamos los errores en el estado
            setErrors(error.response.data.errors);
        } else {
            console.error("Error al guardar", error);
        }
    }
   };

  const fetchEmployees = async () => {
    try {
      const response = await api.get('/api/employees');
      setEmployees(response.data.employees);
    } catch (error) {
      console.error("Error al cargar empleados", error);
    }
  };

  const handleDelete = async (id) => {
    if (window.confirm("¿Estás seguro de eliminar a este empleado? Esta acción no se puede deshacer.")) {
      try {
        await api.delete(`/api/employees/${id}`);
        fetchEmployees(); // Recargamos la lista tras borrar
      } catch (error) {
        console.error("Error al eliminar", error);
        alert("No se pudo eliminar el empleado.");
      }
    }
  };

  const handleOpenModal = (employee = null) => {
    setSelectedEmployee(employee);
    setIsModalOpen(true);
  };

  return (
    <div className="max-w-6xl mx-auto p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-blacck">Gestión de Empleados </h1>
        <button 
          onClick={() => handleOpenModal()}
          className="bg-purple-600 hover:bg-purple-500 text-white px-4 py-2 rounded-lg"
        >
          + Nuevo Empleado
        </button>
      </div>

      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <table className="w-full text-left">
          <thead className="bg-purple-900 text-white">
            <tr>
              <th className="p-4">Nombre</th>
              <th className="p-4">Especialidad</th>
              <th className="p-4">Teléfono</th>
              <th className="p-4">Comisión</th>
              <th className="p-4">Estado</th>
              <th className="p-4">Acceso Plataforma</th>
              <th className="p-4 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {Array.isArray(employees) && employees.map((emp) => (
              <tr key={emp.id}>
                <td className="p-4">{emp.name}</td>
                <td className="p-4">{emp.specialty}</td>
                <td className="p-4">{emp.phone}</td>
                <td className="p-4">{emp.commission_percentage}%</td>
                <td className="p-4">
                  <span className={`px-2 py-1 rounded text-sm ${emp.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                    {emp.is_active ? 'Disponible' : 'Fuera de servicio'}
                  </span>
                </td>
                <td className="p-4">
                  <span className={`px-2 py-1 rounded text-sm ${emp.user_id ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                    {emp.user_id ? 'Activado' : 'Desactivado'}
                  </span>
                </td>
                <td className="p-4 text-center">
                  <button 
                    onClick={() => handleOpenModal(emp)}
                    className="text-purple-600 hover:text-purple-900 font-semibold"
                  >
                    Editar
                  </button>
                  <button 
                    onClick={() => handleDelete(emp.id)}
                    className="text-red-600 hover:text-red-900 font-semibold"
                  >
                    Eliminar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg">
            <h2 className="text-xl font-bold mb-4">
                {selectedEmployee ? 'Editar Empleado' : 'Nuevo Empleado'}
            </h2>
            
            <EmployeeForm 
                initialData={selectedEmployee} 
                onSubmit={handleSaveEmployee}
                errors={errors} 
            />

            <button 
                onClick={() => setIsModalOpen(false)}
                className="mt-4 bg-gray-500 text-white px-4 py-2 rounded"
            >
                Cerrar
            </button>
            </div>
        </div>
       )}
    </div>
  );
};

export default EmployeeList;