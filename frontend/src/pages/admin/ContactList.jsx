import React, { useEffect, useState } from "react";
import api from "../../api/axios";
import { Plus, Pencil, Trash2, Phone, Mail, MessageCircle, MapPin, Globe } from "lucide-react";
import ContactForm from "../../components/admin/ContactForm";

const getIconComponent = (icon) => {
  switch (icon) {
    case "phone":
      return <Phone className="w-5 h-5" />;
    case "mail":
      return <Mail className="w-5 h-5" />;
    case "whatsapp":
    case "message":
      return <MessageCircle className="w-5 h-5" />;
    case "map":
    case "location":
      return <MapPin className="w-5 h-5" />;
    case "globe":
      return <Globe className="w-5 h-5" />;
    default:
      return <Phone className="w-5 h-5" />;
  }
};

const ContactList = () => {
  const [contacts, setContacts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [editingData, setEditingData] = useState(null);
  const [errorMsg, setErrorMsg] = useState("");
  const [successMsg, setSuccessMsg] = useState("");

  const fetchContacts = async () => {
    try {
      const res = await api.get("/api/admin/contacts");
      setContacts(res.data.contacts || []);
    } catch (error) {
      console.error("Error al cargar contactos:", error);
      setErrorMsg("Error al cargar los datos de contacto");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchContacts();
  }, []);

  const handleOpenModal = (contact = null) => {
    setErrorMsg("");
    setSuccessMsg("");
    if (contact) {
      setEditingId(contact.id);
      setEditingData(contact);
    } else {
      setEditingId(null);
      setEditingData(null);
    }
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setEditingId(null);
    setEditingData(null);
    setErrorMsg("");
  };

  const handleSubmit = async (formData) => {
    setErrorMsg("");
    setSuccessMsg("");

    try {
      if (editingId) {
        await api.put(`/api/admin/contacts/${editingId}`, formData);
        setSuccessMsg("Dato de contacto actualizado correctamente");
      } else {
        await api.post("/api/admin/contacts", formData);
        setSuccessMsg("Dato de contacto creado correctamente");
      }
      fetchContacts();
      setTimeout(() => {
        handleCloseModal();
      }, 1000);
      return null;
    } catch (error) {
      if (error.response?.status === 422 && error.response?.data?.errors) {
        return error.response.data.errors;
      } else {
        setErrorMsg(
          error.response?.data?.message || "Error al guardar el dato de contacto"
        );
        return null;
      }
    }
  };

  const handleDelete = async (id) => {
    if (window.confirm("¿Estás seguro de eliminar este dato de contacto?")) {
      try {
        await api.delete(`/api/admin/contacts/${id}`);
        setSuccessMsg("Dato de contacto eliminado correctamente");
        fetchContacts();
        setTimeout(() => setSuccessMsg(""), 2000);
      } catch (error) {
        setErrorMsg(
          error.response?.data?.message || "Error al eliminar el dato de contacto"
        );
        setTimeout(() => setErrorMsg(""), 2000);
      }
    }
  };

  return (
    <div className="max-w-5xl w-full">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-extrabold text-purple-950">
          Gestión de Contacto
        </h1>
        <button
          onClick={() => handleOpenModal()}
          className="flex items-center gap-2 bg-purple-950 text-white px-4 py-2 rounded-lg hover:bg-purple-800 transition shadow-sm"
        >
          <Plus size={18} /> Nuevo Dato
        </button>
      </div>

      {errorMsg && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {errorMsg}
        </div>
      )}
      {successMsg && (
        <div className="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
          {successMsg}
        </div>
      )}

      <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-100">
        <table className="w-full text-left">
          <thead className="bg-purple-800 text-white">
            <tr>
              <th className="p-4">Icono</th>
              <th className="p-4">Nombre</th>
              <th className="p-4">URL</th>
              <th className="p-4 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {loading ? (
              <tr>
                <td colSpan="4" className="text-center py-8 text-gray-500">
                  Cargando...
                </td>
              </tr>
            ) : contacts.length === 0 ? (
              <tr>
                <td colSpan="4" className="text-center py-8 text-gray-500 italic">
                  No hay datos de contacto registrados
                </td>
              </tr>
            ) : (
              contacts.map((contact) => (
                <tr key={contact.id} className="hover:bg-gray-50">
                  <td className="p-4">
                    <div className="w-9 h-9 bg-purple-950 text-white rounded-lg flex items-center justify-center">
                      {getIconComponent(contact.icon)}
                    </div>
                  </td>
                  <td className="p-4 font-medium text-gray-800">
                    {contact.name}
                  </td>
                  <td className="p-4 text-gray-600 break-all">
                    {contact.url}
                  </td>
                  <td className="p-4 text-center flex justify-center gap-2">
                    <button
                      onClick={() => handleOpenModal(contact)}
                      className="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition"
                      title="Editar"
                    >
                      <Pencil size={16} />
                    </button>
                    <button
                      onClick={() => handleDelete(contact.id)}
                      className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                      title="Eliminar"
                    >
                      <Trash2 size={16} />
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <ContactForm
        isOpen={isModalOpen}
        onClose={handleCloseModal}
        onSubmit={handleSubmit}
        initialData={editingData}
        editingId={editingId}
      />
    </div>
  );
};

export default ContactList;
