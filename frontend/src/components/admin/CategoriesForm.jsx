import React, { useState, useEffect } from "react";
import { X } from "lucide-react";

const CategoriesForm = ({ isOpen, onClose, onSubmit, initialData, editingId }) => {
  const [formData, setFormData] = useState({ name: "" });
  const [validationErrors, setValidationErrors] = useState({});

  useEffect(() => {
    if (initialData) {
      setFormData({ name: initialData.name || "" });
    } else {
      setFormData({ name: "" });
    }
    setValidationErrors({});
  }, [initialData, isOpen]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const errors = await onSubmit(formData);
    if (errors) {
      setValidationErrors(errors);
    } else {
      setValidationErrors({});
    }
  };

  const handleClose = () => {
    setValidationErrors({});
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative">
        <button
          onClick={handleClose}
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
        >
          <X size={20} />
        </button>
        <h2 className="text-lg font-bold text-purple-950 mb-4">
          {editingId ? "Editar Categoría" : "Nueva Categoría"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              Nombre *
            </label>
            <input
              type="text"
              value={formData.name}
              onChange={(e) =>
                setFormData({ ...formData, name: e.target.value })
              }
              className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 ${
                validationErrors.name ? "border-red-500" : "border-gray-300"
              }`}
              placeholder="Nombre de la categoría"
              required
            />
            {validationErrors.name &&
              validationErrors.name.map((error, index) => (
                <p key={index} className="text-sm text-red-600 mt-1">
                  {error}
                </p>
              ))}
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={handleClose}
              className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
            >
              Cancelar
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-purple-950 text-white rounded-lg hover:bg-purple-800 transition"
            >
              {editingId ? "Actualizar" : "Crear"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CategoriesForm;
