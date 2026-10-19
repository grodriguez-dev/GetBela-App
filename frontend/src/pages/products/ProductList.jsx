import React, { useState, useEffect } from "react";
import api from "../../api/axios";
import ProductForm from "../../components/products/ProductForm";

const ProductList = () => {
  const [products, setProducts] = useState([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    fetchProducts();
  }, []);

  const fetchProducts = async () => {
    try {
      const response = await api.get("/api/products");
      setProducts(response.data.products || response.data);
    } catch (error) {
      console.error("Error al cargar Productos", error);
    }
  };

  const handleSaveProduct = async (data) => {
    try {
      setErrors({});
      if (data.id) {
        await api.put(`/api/products/${data.id}`, data);
      } else {
        await api.post("/api/products", data);
      }
      fetchProducts();
      setIsModalOpen(false);
    } catch (error) {
      if (error.response && error.response.status === 422) {
        // Capturamos los errores que envía Laravel
        setErrors(error.response.data.errors);
      } else {
        console.error("Error inesperado:", error);
      }
    }
  };

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-800">
          Gestión de Productos
        </h1>
        <button
          onClick={() => {
            setSelectedProduct(null);
            setIsModalOpen(true);
          }}
          className="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition"
        >
          + Nuevo Producto
        </button>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden border border-gray-100">
        <table className="w-full text-left">
          <thead className="bg-purple-800 text-white">
            <tr>
              <th className="p-4">Nombre</th>
              <th className="p-4">Marca</th>
              <th className="p-4 text-center">Stock</th>
              <th className="p-4">Precio Venta</th>
              <th className="p-4 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {products.map((product) => (
              <tr key={product.id} className="hover:bg-gray-50">
                <td className="p-4 font-medium text-gray-800">
                  {product.name}
                </td>
                <td className="p-4 text-gray-600">{product.brand}</td>
                <td
                  className={`p-4 text-center font-bold ${product.stock_current <= product.stock_min ? "text-red-500" : "text-green-600"}`}
                >
                  {product.stock_current}
                </td>
                <td className="p-4 text-gray-700">
                  ${parseFloat(product.sale_price).toFixed(2)}
                </td>
                <td className="p-4 text-center">
                  <button
                    onClick={() => {
                      setSelectedProduct(product);
                      setIsModalOpen(true);
                    }}
                    className="text-purple-600 hover:text-purple-800 font-medium mr-3"
                  >
                    Editar
                  </button>
                  <button className="text-red-500 hover:text-red-700 font-medium">
                    Eliminar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ProductForm
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        initialData={selectedProduct}
        onSubmit={handleSaveProduct}
        errors={errors}
      />
    </div>
  );
};

export default ProductList;
