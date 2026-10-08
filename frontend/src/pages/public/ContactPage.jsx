import React, { useEffect, useState } from "react";
import MainLayout from "../../layouts/MainLayout";
import api from "../../api/axios";
import { Link } from "react-router-dom";
import { ArrowLeft, Phone, Mail, MessageCircle, MapPin, Globe } from "lucide-react";

const getIconComponent = (icon) => {
  switch (icon) {
    case "phone":
      return <Phone className="w-6 h-6" />;
    case "mail":
      return <Mail className="w-6 h-6" />;
    case "whatsapp":
    case "message":
      return <MessageCircle className="w-6 h-6" />;
    case "map":
    case "location":
      return <MapPin className="w-6 h-6" />;
    case "globe":
      return <Globe className="w-6 h-6" />;
    default:
      return <Phone className="w-6 h-6" />;
  }
};

const ContactPage = () => {
  const [contacts, setContacts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchContacts = async () => {
    try {
      const res = await api.get("/api/admin/contacts");
      setContacts(res.data.contacts || []);
    } catch (error) {
      console.error("Error al cargar datos de contacto:", error);
      setError("No se pudieron cargar los datos de contacto");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchContacts();
  }, []);

  return (
    <MainLayout>
      <div className="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
        <div className="mb-6">
          <Link
            to="/"
            className="inline-flex items-center gap-2 text-purple-950 hover:text-purple-700 transition-colors font-medium"
          >
            <ArrowLeft size={20} />
            Volver al inicio
          </Link>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8">
          <div className="text-center mb-8">
            <h1 className="text-3xl md:text-4xl font-extrabold text-purple-950 mb-3">
              Contáctanos
            </h1>
            <p className="text-gray-600">
              Aquí tienes todas las formas para ponerte en contacto con nosotros
            </p>
          </div>

          {loading ? (
            <div className="text-center py-12 text-gray-500">
              Cargando datos de contacto...
            </div>
          ) : error ? (
            <div className="text-center py-12 text-red-600 bg-red-50 rounded-xl border border-red-200">
              {error}
            </div>
          ) : contacts.length === 0 ? (
            <div className="text-center py-12 bg-gray-50 rounded-xl border border-gray-200">
              <p className="text-gray-500 italic">
                No hay datos de contacto disponibles en este momento.
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
              {contacts.map((contact) => (
                <a
                  key={contact.id}
                  href={contact.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-4 p-5 bg-purple-50/60 border border-purple-100 rounded-xl hover:shadow-md transition-shadow hover:bg-purple-100/60"
                >
                  <div className="w-12 h-12 bg-purple-950 text-white rounded-full flex items-center justify-center shrink-0">
                    {getIconComponent(contact.icon)}
                  </div>
                  <div className="min-w-0 flex-1">
                    <h3 className="font-bold text-purple-950">
                      {contact.name}
                    </h3>
                  </div>
                </a>
              ))}
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
};

export default ContactPage;
