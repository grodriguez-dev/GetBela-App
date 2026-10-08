import React, { useState, useEffect } from "react";
import { X } from "lucide-react";

const BannersForm = ({ isOpen, onClose, onSubmit, initialData, editingId }) => {
  const [formData, setFormData] = useState({
    link: "",
    image: null,
  });
  const [preview, setPreview] = useState(null);
  const [validationErrors, setValidationErrors] = useState({});

  useEffect(() => {
    if (initialData) {
      setFormData({
        link: initialData.link || "",
        image: null,
      });
      setPreview(
        initialData.image?.startsWith("http")
          ? initialData.image
          : initialData.image?.startsWith("/storage/")
          ? `http://localhost:8000${initialData.image}`
          : initialData.image
      );
    } else {
      setFormData({ link: "", image: null });
      setPreview(null);
    }
    setValidationErrors({});
  }, [initialData, isOpen]);

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setFormData({ ...formData, image: file });
      const reader = new FileReader();
      reader.onloadend = () => setPreview(reader.result);
      reader.readAsDataURL(file);
    }
  };

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
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 relative max-h-[90vh] overflow-y-auto">
        <button
          onClick={handleClose}
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
        >
          <X size={20} />
        </button>
        <h2 className="text-lg font-bold text-purple-950 mb-4">
          {editingId ? "Editar Banner" : "Nuevo Banner"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              Imagen {editingId ? "" : "*"}
            </label>
            <input
              type="file"
              accept="image/jpeg,image/png,image/jpg,image/webp"
              onChange={handleFileChange}
              className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 ${
                validationErrors.image ? "border-red-500" : "border-gray-300"
              }`}
            />
            <p className="text-xs text-gray-500 mt-1">
              Formatos: JPG, PNG, WEBP. Máx. 2MB
            </p>
            {validationErrors.image &&
              validationErrors.image.map((error, index) => (
                <p key={index} className="text-sm text-red-600 mt-1">
                  {error}
                </p>
              ))}
          </div>

          {preview && (
            <div className="mt-2">
              <p className="text-sm font-medium text-gray-700 mb-2">
                Vista previa
              </p>
              <img
                src={preview}
                alt="Preview"
                className="w-full h-40 object-cover rounded-lg border border-gray-200"
              />
            </div>
          )}

          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              Enlace (Link)
            </label>
            <input
              type="url"
              value={formData.link}
              onChange={(e) =>
                setFormData({ ...formData, link: e.target.value })
              }
              className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 ${
                validationErrors.link ? "border-red-500" : "border-gray-300"
              }`}
              placeholder="https://ejemplo.com"
            />
            <p className="text-xs text-gray-500 mt-1">
              Opcional. Si se agrega, el banner será clicable
            </p>
            {validationErrors.link &&
              validationErrors.link.map((error, index) => (
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

export default BannersForm;
