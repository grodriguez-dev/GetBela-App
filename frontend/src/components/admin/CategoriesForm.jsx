import React, { useState, useEffect, useId } from "react";
import Modal from "../Modal";

const CategoriesForm = ({ isOpen, onClose, onSubmit, initialData, editingId }) => {
  const [formData, setFormData] = useState({ name: "" });
  const [validationErrors, setValidationErrors] = useState({});
  const formId = "category-form-" + useId().replace(/[^a-zA-Z0-9]/g, "");

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

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title={editingId ? "Editar Categoría" : "Nueva Categoría"}
      formId={formId}
      submitLabel={editingId ? "Actualizar" : "Crear"}
      maxWidth="max-w-md"
    >
      <form id={formId} onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">
            Nombre *
          </label>
          <input
            type="text"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
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
      </form>
    </Modal>
  );
};

export default CategoriesForm;
