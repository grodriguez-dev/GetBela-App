import React, { useEffect, useState } from "react";
import api from "../../api/axios";
import {
  ShoppingCart,
  Plus,
  Minus,
  Trash2,
  CheckCircle,
  AlertCircle,
  Package,
} from "lucide-react";

const PosView = () => {
  const [products, setProducts] = useState([]);
  const [cart, setCart] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [successMsg, setSuccessMsg] = useState("");
  const [errorMsg, setErrorMsg] = useState("");

  const fetchProducts = async () => {
    try {
      setLoading(true);
      const response = await api.get("/api/products");

      console.log("Respuesta completa de la API:", response.data);

      // Aseguramos que sea un arreglo sin importar cómo lo devuelva Laravel
      const rawData = response.data;
      const allProducts = Array.isArray(rawData)
        ? rawData
        : rawData.data || Object.values(rawData);

      console.log("Productos convertidos a array:", allProducts);

      // Verificación de seguridad por si acaso sigue sin ser un array
      if (!Array.isArray(allProducts)) {
        console.error(
          "La respuesta de la API no tiene un formato de lista válido:",
          rawData,
        );
        setProducts([]);
        return;
      }

      // FILTRO: Solo mostrar productos de tipo 'venta_publico' o 'mixto'
      const availableForSale = allProducts.filter(
        (prod) => prod.type == 'venta_publico' || prod.type == 'mixto',
      );

      console.log("Productos después del filtro:", availableForSale);

      setProducts(availableForSale);
    } catch (error) {
      console.error("Error al cargar productos:", error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, []);

  // Agregar producto al carrito o incrementar cantidad si ya existe
  const handleAddToCart = (product) => {
    if (product.stock_current <= 0) return;

    setCart((prevCart) => {
      const existing = prevCart.find((item) => item.id === product.id);
      if (existing) {
        // Validar que no rebase el stock actual
        if (existing.quantity >= product.stock_current) return prevCart;
        return prevCart.map((item) =>
          item.id === product.id
            ? { ...item, quantity: item.quantity + 1 }
            : item,
        );
      } else {
        return [...prevCart, { ...product, quantity: 1 }];
      }
    });
  };

  // Cambiar cantidad en el carrito
  const handleUpdateQuantity = (productId, delta) => {
    setCart((prevCart) => {
      return prevCart
        .map((item) => {
          if (item.id === productId) {
            const newQty = item.quantity + delta;
            if (newQty <= 0) return null;
            if (newQty > item.stock_current) return item; // Límite del stock
            return { ...item, quantity: newQty };
          }
          return item;
        })
        .filter(Boolean);
    });
  };

  // Eliminar item del carrito
  const handleRemoveFromCart = (productId) => {
    setCart((prevCart) => prevCart.filter((item) => item.id !== productId));
  };

  // Calcular total de la compra
  const calculateTotal = () => {
    return cart
      .reduce((acc, item) => acc + item.sale_price * item.quantity, 0)
      .toFixed(2);
  };

  // Procesar las ventas de todos los elementos del carrito
  const handleCheckout = async () => {
    if (cart.length === 0) return;
    setSubmitting(true);
    setErrorMsg("");
    setSuccessMsg("");

    try {
      // Recorremos el carrito enviando cada producto al backend (usando tu SaleController actual)
      for (const item of cart) {
        await api.post("/api/sales", {
          product_id: item.id,
          quantity: item.quantity,
        });
      }

      setSuccessMsg("¡Venta procesada con éxito!");
      setCart([]);
      fetchProducts(); // Refrescar stock de la vitrina
    } catch (error) {
      setErrorMsg(
        error.response?.data?.message || "Hubo un error al procesar la venta.",
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="text-center py-20 text-purple-950 font-medium">
        Cargando Punto de Venta...
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-8 w-full flex flex-col lg:flex-row gap-8">
      {/* SECCIÓN IZQUIERDA: Catálogo de productos disponibles para venta */}
      <div className="flex-1">
        <div className="mb-6">
          <h1 className="text-2xl font-extrabold text-purple-950">
            Punto de Venta (Vitrina)
          </h1>
          <p className="text-sm text-gray-500">
            Selecciona los productos disponibles para el público
          </p>
        </div>

        {products.length === 0 ? (
          <div className="bg-white p-8 rounded-2xl border border-gray-200 text-center text-gray-400 italic">
            No hay productos configurados para venta al público o mixtos.
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            {products.map((product) => {
              const isOutOfStock = product.stock_current <= 0;
              return (
                <div
                  key={product.id}
                  onClick={() => !isOutOfStock && handleAddToCart(product)}
                  className={`bg-white p-4 rounded-2xl border transition shadow-sm flex flex-col justify-between ${
                    isOutOfStock
                      ? "opacity-50 cursor-not-allowed border-gray-200"
                      : "cursor-pointer hover:border-purple-500 hover:shadow-md"
                  }`}
                >
                  <div>
                    <div className="flex justify-between items-start mb-2">
                      <span className="text-xs font-semibold px-2.5 py-1 bg-purple-50 text-purple-700 rounded-lg">
                        {product.type === "venta_publico" ? "venta_publico" : "mixto"}
                      </span>
                      <span className="text-xs font-bold text-gray-400">
                        Stock: {product.stock_current}
                      </span>
                    </div>
                    <h3 className="font-bold text-gray-800 text-sm mb-1">
                      {product.name}
                    </h3>
                  </div>

                  <div className="flex justify-between items-center mt-4 pt-3 border-t border-gray-100">
                    <span className="font-extrabold text-purple-950">
                      ${Number(product.sale_price).toFixed(2)}
                    </span>
                    <button
                      disabled={isOutOfStock}
                      className="p-2 bg-purple-50 text-purple-700 rounded-xl hover:bg-purple-950 hover:text-white transition"
                    >
                      <Plus size={16} />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* SECCIÓN DERECHA: Carrito de Compras / Ticket */}
      <div className="w-full lg:w-96 bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col justify-between h-fit sticky top-6">
        <div>
          <h2 className="text-lg font-bold text-purple-950 mb-4 flex items-center gap-2">
            <ShoppingCart size={20} className="text-purple-600" /> Carrito de
            Venta
          </h2>

          {successMsg && (
            <div className="mb-4 bg-green-50 border border-green-200 text-green-700 p-3 rounded-xl text-xs flex items-center gap-2">
              <CheckCircle size={16} className="shrink-0" /> {successMsg}
            </div>
          )}

          {errorMsg && (
            <div className="mb-4 bg-red-50 border border-red-200 text-red-600 p-3 rounded-xl text-xs flex items-center gap-2">
              <AlertCircle size={16} className="shrink-0" /> {errorMsg}
            </div>
          )}

          {cart.length === 0 ? (
            <div className="text-center py-12 text-gray-400 text-xs italic">
              El carrito está vacío. Haz clic en un producto para agregarlo.
            </div>
          ) : (
            <div className="space-y-3 max-h-96 overflow-y-auto pr-1">
              {cart.map((item) => (
                <div
                  key={item.id}
                  className="flex justify-between items-center bg-gray-50 p-3 rounded-xl border border-gray-100"
                >
                  <div className="flex-1 pr-2">
                    <h4 className="text-xs font-bold text-gray-800">
                      {item.name}
                    </h4>
                    <span className="text-xs text-purple-950 font-semibold">
                      ${Number(item.sale_price).toFixed(2)}
                    </span>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => handleUpdateQuantity(item.id, -1)}
                      className="p-1 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-100"
                    >
                      <Minus size={12} />
                    </button>
                    <span className="text-xs font-bold w-4 text-center">
                      {item.quantity}
                    </span>
                    <button
                      onClick={() => handleUpdateQuantity(item.id, 1)}
                      className="p-1 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-100"
                    >
                      <Plus size={12} />
                    </button>
                    <button
                      onClick={() => handleRemoveFromCart(item.id)}
                      className="p-1 text-red-500 hover:bg-red-50 rounded-lg ml-1"
                    >
                      <Trash2 size={14} />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Resumen y Botón de Pago */}
        <div className="mt-6 pt-4 border-t border-gray-100">
          <div className="flex justify-between items-center mb-4">
            <span className="text-sm font-medium text-gray-500">
              Total a Pagar:
            </span>
            <span className="text-2xl font-extrabold text-purple-950">
              ${calculateTotal()}
            </span>
          </div>

          <button
            onClick={handleCheckout}
            disabled={cart.length === 0 || submitting}
            className="w-full py-3 bg-purple-950 text-white rounded-xl font-medium hover:bg-purple-900 transition shadow-md disabled:opacity-50"
          >
            {submitting ? "Procesando..." : "Cobrar y Finalizar Venta"}
          </button>
        </div>
      </div>
    </div>
  );
};

export default PosView;
