import React from "react";
import BusinessForm from "../../components/BusinessForm";
import { useAuth } from "../../context/AuthContext"; // Asegúrate de importar tu AuthContext
import api from "../../api/axios";
import { useNavigate } from "react-router-dom";

const BusinessSettings = () => {
  // Extraemos el usuario del contexto
  const { user } = useAuth();

  const navigate = useNavigate();

  // Accedemos directamente a la relación 'business'
  const businessData = user?.business;

  // Si por alguna razón el usuario aún no tiene negocio (o está cargando), manejamos el estado
  if (!businessData) {
    return <div className="p-8">Cargando datos del negocio...</div>;
  }

  return (
    <div className="p-8">
      <BusinessForm
        initialData={businessData}
        isEditable={true}
        title="Editar Información del Negocio"
        onSubmit={async (formData) => {
          try {
            // Realizamos el envío al endpoint de actualización
            await api.post("/api/business/update", formData, {
              headers: { "Content-Type": "multipart/form-data" },
            });
            alert("¡Negocio actualizado con éxito!");
            navigate("/configuracion");
            window.location.reload();
          } catch (error) {
            console.error("Error al actualizar:", error);
            alert("Hubo un error al guardar los cambios.");
          }
        }}
      />
    </div>
  );
};

export default BusinessSettings;
