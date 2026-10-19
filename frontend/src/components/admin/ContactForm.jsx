import React, { useState, useEffect, useId } from "react";
import { Phone, Mail, MessageCircle, MapPin, Globe } from "lucide-react";
import Modal from "../Modal";

const iconOptions = [
  { value: "phone", label: "Teléfono", icon: Phone },
  { value: "mail", label: "Correo", icon: Mail },
  { value: "whatsapp", label: "WhatsApp", icon: MessageCircle },
  { value: "message", label: "Mensaje", icon: MessageCircle },
  { value: "map", label: "Ubicación", icon: MapPin },
  { value: "location", label: "Dirección", icon: MapPin },
  { value: "globe", label: "Web", icon: Globe },
];

const ContactForm = ({ isOpen, onClose, onSubmit, initialData, editingId }) => {
  const [formData, setFormData] = useState({ name: "", icon: "phone", url: "" });
  const [validationErrors, setValidationErrors] = useState({});
  const formId = "contact-form-" + useId().replace(/[^a-zA-Z0-9]/g, "");

  useEffect(() => {
    if (initialData) {
      setFormData({
        name: initialData.name || "",
        icon: initialData.icon || "phone",
        url: initialData.url || "",
      });
    } else {
      setFormData({ name: "", icon: "phone", url: "" });
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

  const renderIconPreview = (iconValue) => {
    const option = iconOptions.find((opt) => opt.value === iconValue);
    const IconComponent = option?.icon || Phone;
    return <IconComponent className="w-5 h-5" />;
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title={editingId ? "Editar Contacto" : "Nuevo Dato de Contacto"}
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
            placeholder="Nombre del canal de contacto"
            required
          />
          {validationErrors.name &&
            validationErrors.name.map((error, index) => (
              <p key={index} className="text-sm text-red-600 mt-1">
                {error}
              </p>
            ))}
        </div>

        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">
            Icono *
          </label>
          <div className="flex gap-2 items-center">
            <select
              value={formData.icon}
              onChange={(e) => setFormData({ ...formData, icon: e.target.value })}
              className={`flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 ${
                validationErrors.icon ? "border-red-500" : "border-gray-300"
              }`}
              required
            >
              {iconOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <div className="w-10 h-10 bg-purple-950 text-white rounded-lg flex items-center justify-center shrink-0">
              {renderIconPreview(formData.icon)}
            </div>
          </div>
          {validationErrors.icon &&
            validationErrors.icon.map((error, index) => (
              <p key={index} className="text-sm text-red-600 mt-1">
                {error}
              </p>
            ))}
        </div>

        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">
            URL / Valor *
          </label>
          <input
            type="text"
            value={formData.url}
            onChange={(e) => setFormData({ ...formData, url: e.target.value })}
            className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 ${
              validationErrors.url ? "border-red-500" : "border-gray-300"
            }`}
            placeholder="Ej: https://wa.me/3000000000 o +57 300 000 0000"
            required
          />
          {validationErrors.url &&
            validationErrors.url.map((error, index) => (
              <p key={index} className="text-sm text-red-600 mt-1">
                {error}
              </p>
            ))}
        </div>
      </form>
    </Modal>
  );
};

export default ContactForm;
