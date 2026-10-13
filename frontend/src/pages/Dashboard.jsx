import React, { useEffect, useState, useCallback } from "react";
import { useAuth } from "../context/AuthContext";
import api from "../api/axios";
import {
  Wallet,
  CalendarCheck,
  ShoppingBag,
  TrendingUp,
  CalendarDays,
  Ticket,
  Loader2,
  AlertCircle,
  RefreshCw,
} from "lucide-react";
import {
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  Tooltip,
  Legend,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  AreaChart,
  Area,
} from "recharts";

/* ------------------------------------------------------------------ */
/*  Colores de la plataforma (paleta morada de Tailwind)               */
/* ------------------------------------------------------------------ */
const COLORS = {
  purple950: "#3b0764",
  purple800: "#6b21a8",
  purple600: "#9333ea",
  purple500: "#a855f7",
  purple400: "#c084fc",
  purple300: "#d8b4fe",
  pink: "#ec4899",
  amber: "#f59e0b",
  green: "#34d399",
};

const PIE_COLORS = [COLORS.purple800, COLORS.purple400, COLORS.pink, COLORS.amber];
const STAFF_COLORS = [
  COLORS.purple950,
  COLORS.purple800,
  COLORS.purple600,
  COLORS.purple500,
  COLORS.purple400,
  COLORS.purple300,
  COLORS.pink,
  COLORS.amber,
  COLORS.green,
];

const PERIODS = [
  { value: "today", label: "Hoy" },
  { value: "week", label: "Semana" },
  { value: "month", label: "Mes" },
  { value: "year", label: "Año" },
  { value: "all", label: "Todo" },
];

const tooltipStyle = {
  borderRadius: "0.75rem",
  border: "1px solid #e9d5ff",
  boxShadow: "0 10px 25px rgba(88,28,135,0.18)",
  fontSize: "12px",
  color: "#374151",
};

/* ------------------------------------------------------------------ */
/*  Utilidades de formato                                              */
/* ------------------------------------------------------------------ */
const formatMoney = (value) => {
  const num = Number(value) || 0;
  return (
    "$" +
    num.toLocaleString("es", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
};

const truncate = (str, max = 14) =>
  str && str.length > max ? str.slice(0, max - 1) + "…" : str;

const Card = ({ title, subtitle, icon, children }) => (
  <div className="bg-white rounded-2xl shadow-sm border border-purple-100 p-5 h-full">
    <div className="flex items-start justify-between mb-4">
      <div>
        <h3 className="font-bold text-purple-950">{title}</h3>
        {subtitle && <p className="text-xs text-gray-500 mt-0.5">{subtitle}</p>}
      </div>
      {icon && (
        <div className="w-9 h-9 rounded-xl bg-purple-950 text-white flex items-center justify-center shrink-0">
          {icon}
        </div>
      )}
    </div>
    {children}
  </div>
);

const EmptyState = ({ message }) => (
  <div className="flex flex-col items-center justify-center h-64 text-gray-400">
    <CalendarDays size={36} strokeWidth={1.5} />
    <p className="mt-3 text-sm text-gray-500 text-center max-w-xs">{message}</p>
  </div>
);

/* ------------------------------------------------------------------ */
/*  Dashboard                                                          */
/* ------------------------------------------------------------------ */
const Dashboard = () => {
  const { user } = useAuth();
  const [period, setPeriod] = useState("month");
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchData = useCallback(async (selectedPeriod) => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get("/api/finances/dashboard", {
        params: { period: selectedPeriod },
      });
      setData(res.data);
    } catch (err) {
      setError(
        err.response?.status === 403
          ? "forbidden"
          : err.response?.status === 401
            ? "unauthorized"
            : "generic",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let isMounted = true;
    const load = async () => {
      if (!isMounted) return;
      await fetchData(period);
    };
    load();
    return () => {
      isMounted = false;
    };
  }, [period, fetchData]);

  const summary = data?.summary ?? {};
  const staffPerformance = data?.performance_by_staff ?? [];
  const topProducts = data?.top_products_sold ?? [];
  const timeline = data?.revenue_timeline ?? [];

  /* ---- Datos para gráficas ---- */
  const pieData = [
    { name: "Servicios (citas)", value: Number(summary.revenue_from_services) || 0 },
    { name: "Vitrina (productos)", value: Number(summary.revenue_from_vitrina) || 0 },
  ].filter((d) => d.value > 0);

  const staffData = staffPerformance.map((s, i) => ({
    name: truncate(s.employee_name),
    fullName: s.employee_name,
    gross: Number(s.gross_generated) || 0,
    commission: Number(s.commission_rate) || 0,
    net: Number(s.salon_net_profit) || 0,
    total_appointments: s.total_appointments,
    fill: STAFF_COLORS[i % STAFF_COLORS.length],
  }));

  const productsData = topProducts.map((p) => ({
    name: truncate(p.product_name),
    fullName: p.product_name,
    revenue: Number(p.total_revenue) || 0,
    units: p.total_units_sold,
    fill: COLORS.purple600,
  }));

  const statsCards = [
    {
      label: "Ingresos totales",
      value: summary.total_gross_combined,
      icon: <Wallet size={18} />,
      sub: "Citas completadas + vitrina",
    },
    {
      label: "Por servicios",
      value: summary.revenue_from_services,
      icon: <CalendarCheck size={18} />,
      sub: "Citas completadas",
    },
    {
      label: "Por vitrina",
      value: summary.revenue_from_vitrina,
      icon: <ShoppingBag size={18} />,
      sub: "Ventas de productos",
    },
    {
      label: "Proyección",
      value: summary.total_projected_services,
      icon: <TrendingUp size={18} />,
      sub: "Citas pendientes/confirmadas",
    },
  ];

  /* ---- Estados ---------------- */
  if (loading) {
    return (
      <div className="w-full max-w-6xl flex flex-col items-center justify-center py-24 text-purple-950">
        <Loader2 className="animate-spin" size={36} />
        <p className="mt-3 text-sm text-gray-500">Cargando métricas financieras...</p>
      </div>
    );
  }

  if (error === "forbidden") {
    return (
      <div className="w-full max-w-6xl flex flex-col items-center justify-center py-24">
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center max-w-md">
          <AlertCircle className="mx-auto text-amber-500" size={36} />
          <h2 className="mt-3 font-bold text-purple-950">Acceso restringido</h2>
          <p className="mt-2 text-sm text-gray-600">
            El módulo financiero está disponible solo para el dueño del negocio.
          </p>
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="w-full max-w-6xl flex flex-col items-center justify-center py-24">
        <div className="bg-red-50 border border-red-200 rounded-2xl p-8 text-center max-w-md">
          <AlertCircle className="mx-auto text-red-500" size={36} />
          <h2 className="mt-3 font-bold text-purple-950">No se pudieron cargar los datos</h2>
          <p className="mt-2 text-sm text-gray-600">
            Ocurrió un error al consultar el módulo financiero. Intenta de nuevo.
          </p>
          <button
            onClick={() => fetchData(period)}
            className="mt-4 inline-flex items-center gap-2 bg-purple-950 text-white px-4 py-2 rounded-lg hover:bg-purple-800 transition"
          >
            <RefreshCw size={16} /> Reintentar
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="w-full max-w-6xl space-y-6">
      {/* Header + selector de período */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-purple-950">
            Panel Financiero
          </h1>
          <p className="text-sm text-gray-500">
            Hola, <span className="font-semibold text-purple-950">{user?.name}</span> —
            resumen de{" "}
            {data.range?.start ? new Date(data.range.start).toLocaleDateString("es") : "…"}{" "}
            al{" "}
            {data.range?.end ? new Date(data.range.end).toLocaleDateString("es") : "…"}
          </p>
        </div>

        <div className="flex flex-wrap gap-1.5 bg-white border border-purple-100 rounded-xl p-1 shadow-sm">
          {PERIODS.map((p) => (
            <button
              key={p.value}
              onClick={() => setPeriod(p.value)}
              className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition ${
                period === p.value
                  ? "bg-purple-950 text-white shadow"
                  : "text-purple-900 hover:bg-purple-50"
              }`}
            >
              {p.label}
            </button>
          ))}
        </div>
      </div>

      {/* Tarjetas de resumen */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statsCards.map((card) => (
          <div
            key={card.label}
            className="bg-white rounded-2xl shadow-sm border border-purple-100 p-5"
          >
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-purple-950 text-white flex items-center justify-center shrink-0">
                {card.icon}
              </div>
              <p className="text-sm text-gray-500 font-medium">{card.label}</p>
            </div>
            <p className="mt-3 text-2xl font-extrabold text-purple-950">
              {formatMoney(card.value)}
            </p>
            <p className="mt-1 text-xs text-gray-400">{card.sub}</p>
          </div>
        ))}
      </div>

      {/* Estadísticas secundarias */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-gradient-to-br from-purple-950 to-purple-800 text-white rounded-2xl p-5 shadow-sm">
          <div className="flex items-center gap-2 text-purple-200">
            <CalendarDays size={16} />
            <span className="text-xs font-medium uppercase tracking-wide">
              Citas completadas
            </span>
          </div>
          <p className="mt-2 text-3xl font-extrabold">
            {summary.completed_appointments ?? 0}
          </p>
        </div>
        <div className="bg-gradient-to-br from-purple-600 to-purple-400 text-white rounded-2xl p-5 shadow-sm">
          <div className="flex items-center gap-2 text-purple-100">
            <Ticket size={16} />
            <span className="text-xs font-medium uppercase tracking-wide">
              Ticket promedio
            </span>
          </div>
          <p className="mt-2 text-3xl font-extrabold">
            {formatMoney(summary.average_ticket)}
          </p>
        </div>
      </div>

      {/* Fila 1: Pastel + Barras (staff) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* GRÁFICA DE PASTEL */}
        <Card
          title="Distribución de ingresos"
          subtitle="Participación de cada fuente de ingreso"
        >
          {pieData.length === 0 ? (
            <EmptyState message="Aún no hay ingresos registrados en este período." />
          ) : (
            <div className="h-72">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={pieData}
                    dataKey="value"
                    nameKey="name"
                    cx="50%"
                    cy="50%"
                    innerRadius={55}
                    outerRadius={95}
                    paddingAngle={4}
                    stroke="#ffffff"
                  >
                    {pieData.map((entry, index) => (
                      <Cell key={entry.name} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip
                    formatter={(value) => formatMoney(value)}
                    contentStyle={tooltipStyle}
                  />
                  <Legend
                    verticalAlign="bottom"
                    iconType="circle"
                    iconSize={8}
                    formatter={(value) => (
                      <span className="text-xs text-gray-600">{value}</span>
                    )}
                  />
                </PieChart>
              </ResponsiveContainer>
            </div>
          )}
        </Card>

        {/* GRÁFICA DE BARRAS (STAFF) */}
        <Card
          title="Rendimiento del staff"
          subtitle="Ingreso bruto generado por estilista"
        >
          {staffData.length === 0 ? (
            <EmptyState message="No hay datos de comisiones del personal en este período." />
          ) : (
            <div className="h-72">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={staffData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f3e8ff" vertical={false} />
                  <XAxis
                    dataKey="name"
                    tick={{ fontSize: 11, fill: "#6b7280" }}
                    axisLine={{ stroke: "#e9d5ff" }}
                    tickLine={false}
                    interval={0}
                  />
                  <YAxis
                    tick={{ fontSize: 11, fill: "#6b7280" }}
                    axisLine={false}
                    tickLine={false}
                    tickFormatter={(v) => "$" + Number(v).toLocaleString("es")}
                  />
                  <Tooltip
                    cursor={{ fill: "rgba(168,85,247,0.08)" }}
                    formatter={(value, name) => {
                      if (name === "Ingreso bruto") return [formatMoney(value), name];
                      return [value, name];
                    }}
                    labelFormatter={(label, payload) =>
                      payload?.[0]?.payload?.fullName || label
                    }
                    contentStyle={tooltipStyle}
                  />
                  <Bar
                    dataKey="gross"
                    name="Ingreso bruto"
                    radius={[6, 6, 0, 0]}
                    maxBarSize={46}
                  >
                    {staffData.map((entry) => (
                      <Cell key={entry.fullName} fill={entry.fill} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>
          )}
        </Card>
      </div>

      {/* Fila 2: Gráfica de coordenadas (área) */}
      <Card
        title="Evolución de ingresos"
        subtitle="Ingresos por servicios y vitrina en el tiempo"
      >
        {timeline.length === 0 || timeline.every((t) => t.total_revenue === 0) ? (
          <EmptyState message="Aún no hay movimientos de ingresos en este período." />
        ) : (
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={timeline} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="gradServices" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={COLORS.purple800} stopOpacity={0.35} />
                    <stop offset="100%" stopColor={COLORS.purple800} stopOpacity={0.02} />
                  </linearGradient>
                  <linearGradient id="gradSales" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={COLORS.purple400} stopOpacity={0.35} />
                    <stop offset="100%" stopColor={COLORS.purple400} stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f3e8ff" vertical={false} />
                <XAxis
                  dataKey="label"
                  tick={{ fontSize: 11, fill: "#6b7280" }}
                  axisLine={{ stroke: "#e9d5ff" }}
                  tickLine={false}
                  minTickGap={24}
                />
                <YAxis
                  tick={{ fontSize: 11, fill: "#6b7280" }}
                  axisLine={false}
                  tickLine={false}
                  tickFormatter={(v) => "$" + Number(v).toLocaleString("es")}
                />
                <Tooltip
                  cursor={{ stroke: COLORS.purple500, strokeWidth: 1, strokeDasharray: "4 4" }}
                  formatter={(value, name) => [
                    formatMoney(value),
                    name === "Servicios" ? "Servicios" : "Vitrina",
                  ]}
                  contentStyle={tooltipStyle}
                />
                <Legend
                  verticalAlign="top"
                  iconType="circle"
                  iconSize={8}
                  formatter={(value) => (
                    <span className="text-xs text-gray-600">{value}</span>
                  )}
                />
                <Area
                  type="monotone"
                  dataKey="services_revenue"
                  name="Servicios"
                  stroke={COLORS.purple800}
                  strokeWidth={2.5}
                  fill="url(#gradServices)"
                />
                <Area
                  type="monotone"
                  dataKey="sales_revenue"
                  name="Vitrina"
                  stroke={COLORS.purple500}
                  strokeWidth={2.5}
                  fill="url(#gradSales)"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        )}
      </Card>

      {/* Fila 3: Top productos (barras horizontales) */}
      <Card
        title="Top productos vendidos"
        subtitle="Productos con mayores ingresos por vitrina"
      >
        {productsData.length === 0 ? (
          <EmptyState message="No se registraron ventas de productos en este período." />
        ) : (
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart
                data={productsData}
                layout="vertical"
                margin={{ top: 5, right: 24, left: 8, bottom: 5 }}
              >
                <CartesianGrid strokeDasharray="3 3" stroke="#f3e8ff" horizontal={false} />
                <XAxis
                  type="number"
                  tick={{ fontSize: 11, fill: "#6b7280" }}
                  axisLine={false}
                  tickLine={false}
                  tickFormatter={(v) => "$" + Number(v).toLocaleString("es")}
                />
                <YAxis
                  type="category"
                  dataKey="name"
                  width={120}
                  tick={{ fontSize: 11, fill: "#4b5563" }}
                  axisLine={false}
                  tickLine={false}
                />
                <Tooltip
                  cursor={{ fill: "rgba(168,85,247,0.08)" }}
                  formatter={(value, name) =>
                    name === "Ingreso generado" ? [formatMoney(value), name] : [value, name]
                  }
                  labelFormatter={(label, payload) =>
                    payload?.[0]?.payload?.fullName || label
                  }
                  contentStyle={tooltipStyle}
                />
                <Bar
                  dataKey="revenue"
                  name="Ingreso generado"
                  radius={[0, 6, 6, 0]}
                  maxBarSize={22}
                >
                  {productsData.map((entry) => (
                    <Cell key={entry.fullName} fill={entry.fill} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        )}
      </Card>
    </div>
  );
};

export default Dashboard;