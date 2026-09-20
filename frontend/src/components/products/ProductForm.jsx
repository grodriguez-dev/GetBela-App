import React, { useState } from "react";

const ProductForm = ({ initialData = {}, onSubmit, errors = {} }) => {
  const [formData, setFormData] = useState(
    initialData || {
      name: "",
      brand: "",
      description: "",
      stock_current: "",
      stock_min: "",
      purchase_price: "",
      sale_price: "",
      type: "uso_interno", // valor por defecto
    },
  );

  const handleChange = (e) => {
    const { name, value, type } = e.target;

const numericFields = ['stock_current', 'stock_min', 'purchase_price', 'sale_price'];
    if (numericFields.includes(name)) {
      const cleanValue = value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
    
      setFormData((prev) => ({ ...prev, [name]: cleanValue }));
    } else {
        setFormData({ ...formData, [name]: value });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    // Aquí llamarías a tu función de API (POST o PUT)
    // await api.post('/products', formData);
    onSubmit(formData);
  };

  const renderError = (field) =>
    errors[field] ? (
      <span className="text-red-500 text-xs mt-1 block">
        {errors[field][0]}
      </span>
    ) : null;

  return (
    <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4">
      {/* Nombre y Marca */}
      <div className="col-span-2">
        <label className="block text-sm font-medium text-gray-700">
          Nombre del Producto
        </label>
        <input
          name="name"
          value={formData.name}
          onChange={handleChange}
          className="w-full border p-2 rounded"
          required
        />
        {renderError("name")}
      </div>

      <div className="col-span-1">
        <label className="block text-sm font-medium text-gray-700">Marca</label>
        <input
          name="brand"
          value={formData.brand}
          onChange={handleChange}
          className="w-full border p-2 rounded"
        />
        {renderError("brand")}
      </div>

      <div className="col-span-1">
        <label className="block text-sm font-medium text-gray-700">Tipo</label>
        <select
          name="type"
          value={formData.type}
          onChange={handleChange}
          className="w-full border p-2 rounded"
        >
          <option value="uso_interno">Uso Interno</option>
          <option value="venta_publico">Venta Al Publico</option>
          <option value="mixto">Mixto</option>
        </select>
        {renderError("type")}
      </div>

      {/* Stock */}
      <div className="col-span-1">
        <label className="block text-sm font-medium text-gray-700">
          Stock Actual
        </label>
        <input
          type="text"
          name="stock_current"
          value={formData.stock_current}
          onChange={handleChange}
          className="w-full border p-2 rounded"
          placeholder="0"
        />
        {renderError("stock_current")}
      </div>

      <div className="col-span-1">
        <label className="block text-sm font-medium text-gray-700">
          Stock Mínimo
        </label>
        <input
          type="text"
          name="stock_min"
          value={formData.stock_min}
          onChange={handleChange}
          className="w-full border p-2 rounded"
          placeholder="0"
        />
        {renderError("stock_min")}
      </div>

      {/* Precios */}
      <div className="col-span-1">
        <label className="block text-sm font-medium text-gray-700">
          Precio Compra
        </label>
        <div className="flex items-center w-full border border-gray-300 rounded focus-within:ring-2 focus-within:ring-purple-500 focus-within:border-purple-500 overflow-hidden bg-white">
          <span className="px-3 text-gray-500 bg-gray-50 border-r border-gray-300">$</span>
          <input
            type="text"
            name="purchase_price"
            value={formData.purchase_price}
            onChange={handleChange}
            className="w-full p-2 outline-none"
            placeholder="0,00"
          />
        </div>
        {renderError("purchase_price")}
      </div>

      <div className="col-span-1">
        <label className="block text-sm font-medium text-gray-700">
          Precio Venta
        </label>
          <div className="flex items-center w-full border border-gray-300 rounded focus-within:ring-2 focus-within:ring-purple-500 focus-within:border-purple-500 overflow-hidden bg-white">
            <span className="px-3 text-gray-500 bg-gray-50 border-r border-gray-300">$</span>
            <input
              type="text"
              step="0.01"
              name="sale_price"
              value={formData.sale_price}
              onChange={handleChange}
              className="w-full p-2 outline-none"
              placeholder="0,00"
            />
          </div>
        {renderError("sale_price")}
      </div>

      <div className="col-span-2">
        <label className="block text-sm font-medium text-gray-700">
          Descripción
        </label>
        <textarea
          name="description"
          value={formData.description}
          onChange={handleChange}
          className="w-full border p-2 rounded"
          rows="2"
        />
        {renderError("description")}
      </div>

      {/* Acciones */}
      <div className="col-span-2 flex justify-end gap-3 mt-4">
        <button
          type="submit"
          className="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
        >
          Guardar
        </button>
      </div>
    </form>
  );
};

export default ProductForm;
