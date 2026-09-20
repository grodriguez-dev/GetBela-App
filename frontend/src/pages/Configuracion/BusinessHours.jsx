import React, { useState, useEffect } from 'react';
import api from "../../api/axios";

const DAYS_OF_WEEK = [
  { id: 1, name: 'Lunes' },
  { id: 2, name: 'Martes' },
  { id: 3, name: 'Miércoles' },
  { id: 4, name: 'Jueves' },
  { id: 5, name: 'Viernes' },
  { id: 6, name: 'Sábado' },
  { id: 0, name: 'Domingo' },
];

const BusinessHours = () => {
  const [schedule, setSchedule] = useState(
    DAYS_OF_WEEK.map((day) => ({
      day_of_week: day.id,
      day_name: day.name,
      opening_time: '08:00',
      closing_time: '18:00',
      is_closed: false,
    }))
  );

  const [isEditing, setIsEditing] = useState(false);
  const [isCreated, setIsCreated] = useState(false);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    fetchBusinessHours();
  }, []);

  const fetchBusinessHours = async () => {
    try {
      const response = await api.get('/api/business-hours');
      const hoursFromApi = response.data.hours;

      if (hoursFromApi && Array.isArray(hoursFromApi) && hoursFromApi.length > 0) {
        const mappedData = DAYS_OF_WEEK.map((day) => {
          const found = hoursFromApi.find((h) => Number(h.day_of_week) === Number(day.id));
          return found
            ? {
                day_of_week: day.id,
                day_name: day.name,
                opening_time: found.opening_time ? found.opening_time.substring(0, 5) : '08:00',
                closing_time: found.closing_time ? found.closing_time.substring(0, 5) : '18:00',
                is_closed: Boolean(found.is_closed),
              }
            : {
                day_of_week: day.id,
                day_name: day.name,
                opening_time: '08:00',
                closing_time: '18:00',
                is_closed: true,
              };
        });
        setSchedule(mappedData);
        setIsCreated(true);
        setIsEditing(false);
      } else {
        setIsCreated(false);
        setIsEditing(true);
      }
    } catch (error) {
      console.error('Error al cargar horarios', error);
      setIsCreated(false);
      setIsEditing(true);
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (index, field, value) => {
    const updated = [...schedule];
    updated[index][field] = value;
    setSchedule(updated);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    
    const cleanedSchedule = schedule.map(({ day_name, ...rest }) => ({
      ...rest,
      opening_time: rest.is_closed ? '00:00' : rest.opening_time,
      closing_time: rest.is_closed ? '00:00' : rest.closing_time,
    }));

    try {
      // CORRECCIÓN: Usamos PUT en lugar de POST para ajustarnos al backend
      await api.put('/api/business-hours', { hours: cleanedSchedule });
      setIsCreated(true);
      setIsEditing(false);
      alert('Jornada laboral actualizada correctamente en GetBela.');
      fetchBusinessHours();
    } catch (error) {
      console.error('Error al guardar los horarios', error);
      if (error.response && error.response.data) {
        // Capturamos el mensaje de error o los errores de validación de Laravel
        const data = error.response.data;
        if (data.errors) {
          // Extraemos el primer error detallado de validación
          const firstKey = Object.keys(data.errors)[0];
          setErrorMessage(data.errors[firstKey][0]);
        } else {
          setErrorMessage(data.message || 'Ocurrió un error al procesar la solicitud.');
        }
      } else {
        setErrorMessage('Error de conexión con el servidor.');
      }
    }
  };

  if (loading) {
    return <div className="p-6 text-center text-gray-500">Cargando horarios...</div>;
  }

  return (
    <div className="w-full max-w-5xl mx-auto p-6 bg-white shadow rounded-lg mt-6">
      <div className="flex justify-between items-center mb-6 border-b pb-4">
        <div>
          <h2 className="text-xl font-bold text-gray-800">🕒 Horarios de Atención</h2>
          <p className="text-sm text-gray-500">Configura las horas de apertura y cierre para cada día de la semana.</p>
        </div>

        <div>
          {!isEditing && isCreated && (
            <button
              type="button"
              onClick={() => setIsEditing(true)}
              className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium transition"
            >
              Editar
            </button>
          )}
        </div>
      </div>

      {errorMessage && (
        <div className="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded shadow-sm">
          <p className="font-bold">Atención:</p>
          <p>{errorMessage}</p>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {schedule.map((item, index) => (
            <div
              key={item.day_of_week}
              className={`p-4 rounded-lg border transition ${
                item.is_closed ? 'bg-gray-50 border-gray-200' : 'bg-white border-gray-300 shadow-sm'
              }`}
            >
              <div className="flex items-center justify-between mb-3">
                <span className="font-semibold text-gray-700 text-base">{item.day_name}</span>
                
                <label className="flex items-center space-x-2 text-sm text-gray-600 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={item.is_closed}
                    disabled={!isEditing}
                    onChange={(e) => handleChange(index, 'is_closed', e.target.checked)}
                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                  />
                  <span className={item.is_closed ? 'text-red-500 font-medium' : ''}>Cerrado</span>
                </label>
              </div>

              {!item.is_closed ? (
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Apertura</label>
                    <input
                      type="time"
                      min="07:00"
                      max="23:00"
                      value={item.opening_time}
                      disabled={!isEditing}
                      onChange={(e) => handleChange(index, 'opening_time', e.target.value)}
                      className="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Cierre</label>
                    <input
                      type="time"
                      min="07:00"
                      max="23:00"
                      value={item.closing_time}
                      disabled={!isEditing}
                      onChange={(e) => handleChange(index, 'closing_time', e.target.value)}
                      className="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>
                </div>
              ) : (
                <div className="py-2 text-center text-sm text-gray-400 italic">
                  No laborable / Cerrado
                </div>
              )}
            </div>
          ))}
        </div>

        {isEditing && (
          <div className="flex justify-end gap-3 pt-4 border-t">
            <button
              type="button"
              onClick={() => {
                setIsEditing(false);
                setErrorMessage('');
                fetchBusinessHours();
              }}
              className="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium text-sm transition"
            >
              Cancelar
            </button>

            <button
              type="submit"
              className="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md font-medium text-sm transition shadow"
            >
              {isCreated ? 'Guardar Cambios' : 'Crear'}
            </button>
          </div>
        )}
      </form>
    </div>
  );
}
export default BusinessHours;