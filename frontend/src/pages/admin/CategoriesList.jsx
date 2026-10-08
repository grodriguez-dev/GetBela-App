import React, { useEffect, useState } from "react";
import api from "../../api/axios";
import { Plus, Pencil, Trash2 } from "lucide-react";
import CategoriesForm from "../../components/admin/CategoriesForm";

const CategoriesList = () => {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [editingData, setEditingData] = useState(null);
  const [errorMsg, setErrorMsg] = useState("");
  const [successMsg, setSuccessMsg] = useState("");

  const fetchCategories = async () => {
    try {
      const res = await api.get("/api/admin/categories");
      setCategories(res.data.categories || []);
    } catch (error) {
      console.error("Error al cargar categorías:", error);
      setErrorMsg("Error al cargar las categorías");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCategories();
  }, []);

  const handleOpenModal = (category = null) => {
    setErrorMsg("");
    setSuccessMsg("");
    if (category) {
      setEditingId(category.id);
      setEditingData(category);
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
        await api.put(`/api/admin/categories/${editingId}`, formData);
        setSuccessMsg("Categoría actualizada correctamente");
      } else {
        await api.post("/api/admin/categories", formData);
        setSuccessMsg("Categoría creada correctamente");
      }
      fetchCategories();
      setTimeout(() => {
        handleCloseModal();
      }, 1000);
      return null;
    } catch (error) {
      if (error.response?.status === 422 && error.response?.data?.errors) {
        return error.response.data.errors;
      } else {
        setErrorMsg(
          error.response?.data?.message || "Error al guardar la categoría"
        );
        return null;
      }
    }
  };

  const handleDelete = async (id) => {
    if (window.confirm("¿Estás seguro de eliminar esta categoría?")) {
      try {
        await api.delete(`/api/admin/categories/${id}`);
        setSuccessMsg("Categoría eliminada correctamente");
        fetchCategories();
        setTimeout(() => setSuccessMsg(""), 2000);
      } catch (error) {
        setErrorMsg(
          error.response?.data?.message || "Error al eliminar la categoría"
        );
        setTimeout(() => setErrorMsg(""), 2000);
      }
    }
  };

  return (
    <div className="max-w-5xl w-full">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-extrabold text-purple-950">
          Gestión de Categorías
        </h1>
        <button
          onClick={() => handleOpenModal()}
          className="flex items-center gap-2 bg-purple-950 text-white px-4 py-2 rounded-lg hover:bg-purple-800 transition shadow-sm"
        >
          <Plus size={18} /> Nueva Categoría
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
              <th className="p-4">Nombre</th>
              <th className="p-4">Slug</th>
              <th className="p-4 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {loading ? (
              <tr>
                <td colSpan="3" className="text-center py-8 text-gray-500">
                  Cargando...
                </td>
              </tr>
            ) : categories.length === 0 ? (
              <tr>
                <td colSpan="3" className="text-center py-8 text-gray-500 italic">
                  No hay categorías registradas
                </td>
              </tr>
            ) : (
              categories.map((category) => (
                <tr key={category.id} className="hover:bg-gray-50">
                  <td className="p-4 font-medium text-gray-800">
                    {category.name}
                  </td>
                  <td className="p-4 text-gray-600">{category.slug}</td>
                  <td className="p-4 text-center flex justify-center gap-2">
                    <button
                      onClick={() => handleOpenModal(category)}
                      className="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition"
                      title="Editar"
                    >
                      <Pencil size={16} />
                    </button>
                    <button
                      onClick={() => handleDelete(category.id)}
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

      <CategoriesForm
        isOpen={isModalOpen}
        onClose={handleCloseModal}
        onSubmit={handleSubmit}
        initialData={editingData}
        editingId={editingId}
      />
    </div>
  );
};

export default CategoriesList;
