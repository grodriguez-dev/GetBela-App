import React, { useCallback, useEffect, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import api from "../../api/axios";
import Modal from "../../components/Modal";
import {
  Wallet,
  CalendarCheck,
  Users,
  PiggyBank,
  Loader2,
  AlertCircle,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
  History,
  Receipt,
  CalendarDays,
} from "lucide-react";

/* ------------------------------------------------------------------ */
/*  Utilidades                                                         */
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

// Trabajamos con fechas en local (YYYY-MM-DD) para no desfasar por zona horaria.
const parseLocalDate = (str) => {
  const [y, m, d] = String(str).split("-").map(Number);
  return new Date(y, m - 1, d);
};

const toISODate = (d) => {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
};

const todayISO = () => toISODate(new Date());

const shiftDate = (iso, period, dir) => {
  const d = parseLocalDate(iso);
  if (period === "day") {
    d.setDate(d.getDate() + dir);
  } else if (period === "week") {
    d.setDate(d.getDate() + 7 * dir);
  } else {
    d.setDate(1);
    d.setMonth(d.getMonth() + dir);
  }
  return toISODate(d);
};

// Etiqueta corta de un día (p. ej. "mié 14 oct").
const formatDayShort = (iso) =>
  parseLocalDate(iso).toLocaleDateString("es", {
    weekday: "short",
    day: "numeric",
    month: "short",
  });

// Etiqueta de un período guardado en el historial.
const historyPeriodLabel = (s) => {
  const start = parseLocalDate(s.period_start);
  const end = parseLocalDate(s.period_end);
  if (s.period_type === "day") {
    return start.toLocaleDateString("es", {
      day: "numeric",
      month: "long",
      year: "numeric",
    });
  }
  if (s.period_type === "week") {
    return `Semana del ${start.toLocaleDateString("es", {
      day: "numeric",
      month: "short",
    })} al ${end.toLocaleDateString("es", {
      day: "numeric",
      month: "short",
      year: "numeric",
    })}`;
  }
  return start.toLocaleDateString("es", { month: "long", year: "numeric" });
};

const PERIODS = [
  { value: "day", label: "Día" },
  { value: "week", label: "Semana" },
  { value: "month", label: "Mes" },
];

const Card = ({ children, className = "" }) => (
  <div
    className={`bg-white rounded-2xl shadow-sm border border-purple-100 ${className}`}
  >
    {children}
  </div>
);

const StatCard = ({ label, value, sub, icon, highlight = false }) => (
  <div
    className={`rounded-2xl shadow-sm p-5 border ${
      highlight
        ? "bg-purple-950 border-purple-950 text-white"
        : "bg-white border-purple-100 text-purple-950"
    }`}
  >
    <div className="flex items-center justify-between gap-3">
      <span
        className={`text-xs font-medium uppercase tracking-wide ${
          highlight ? "text-purple-200" : "text-gray-500"
        }`}
      >
        {label}
      </span>
      <span
        className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 ${
          highlight ? "bg-purple-800 text-white" : "bg-purple-950 text-white"
        }`}
      >
        {icon}
      </span>
    </div>
    <p className="mt-2 text-2xl font-extrabold">{formatMoney(value)}</p>
    {sub && (
      <p
        className={`text-xs mt-0.5 ${highlight ? "text-purple-200" : "text-gray-400"}`}
      >
        {sub}
      </p>
    )}
  </div>
);

/* ------------------------------------------------------------------ */
/*  Página                                                             */
/* ------------------------------------------------------------------ */
const SettlementsPage = () => {
  const { user } = useAuth();

  const [period, setPeriod] = useState("day");
  const [date, setDate] = useState(todayISO());

  const [preview, setPreview] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState("");

  const [confirmOpen, setConfirmOpen] = useState(false);
  const [notes, setNotes] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState(null);

  const [history, setHistory] = useState([]);
  const [historyPage, setHistoryPage] = useState(1);
  const [historyLastPage, setHistoryLastPage] = useState(1);
  const [historyTotal, setHistoryTotal] = useState(0);
  const [historyLoading, setHistoryLoading] = useState(true);
  const [expandedId, setExpandedId] = useState(null);

  const isOwner = !user || user.role === "owner";

  /* ---- Preview (pendiente del período) ---- */
  const fetchPreview = useCallback(async (selectedPeriod, selectedDate) => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get("/api/settlements/preview", {
        params: { period: selectedPeriod, date: selectedDate },
      });
      setPreview(res.data);
    } catch (err) {
      setPreview(null);
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
    if (!isOwner) return;
    fetchPreview(period, date);
  }, [period, date, isOwner, fetchPreview]);

  /* ---- Historial ---- */
  const fetchHistory = useCallback(async (page = 1) => {
    setHistoryLoading(true);
    try {
      const res = await api.get("/api/settlements", { params: { page } });
      setHistory(res.data?.data ?? []);
      setHistoryPage(res.data?.current_page ?? 1);
      setHistoryLastPage(res.data?.last_page ?? 1);
      setHistoryTotal(res.data?.total ?? 0);
    } catch (err) {
      console.error("Error al cargar el historial de liquidaciones", err);
    } finally {
      setHistoryLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isOwner) return;
    fetchHistory(1);
  }, [isOwner, fetchHistory]);

  useEffect(() => {
    if (!successMessage) return;
    const t = setTimeout(() => setSuccessMessage(""), 4000);
    return () => clearTimeout(t);
  }, [successMessage]);

  const handleConfirm = async (e) => {
    e.preventDefault();
    setSaving(true);
    setSaveError(null);
    try {
      await api.post("/api/settlements", {
        period_type: period,
        date,
        notes: notes.trim() ? notes.trim() : null,
      });
      setConfirmOpen(false);
      setNotes("");
      setSuccessMessage("Liquidación registrada correctamente.");
      fetchPreview(period, date);
      fetchHistory(1);
    } catch (err) {
      setSaveError(
        err.response?.data?.message ||
          "No se pudo registrar la liquidación. Intenta de nuevo.",
      );
    } finally {
      setSaving(false);
    }
  };

  const items = preview?.items ?? [];
  const days = preview?.days ?? [];
  const summary = preview?.summary ?? {};
  const hasPending = items.length > 0;

  /* ---- Guardia de rol ---- */
  if (user && user.role !== "owner") {
    return (
      <div className="w-full max-w-6xl flex flex-col items-center justify-center py-24">
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center max-w-md">
          <AlertCircle className="mx-auto text-amber-500" size={36} />
          <h2 className="mt-3 font-bold text-purple-950">Acceso restringido</h2>
          <p className="mt-2 text-sm text-gray-600">
            El módulo de liquidaciones está disponible solo para el dueño del
            negocio.
          </p>
        </div>
      </div>
    );
  }

  if (error === "forbidden" || error === "unauthorized") {
    const sessionExpired = error === "unauthorized";
    return (
      <div className="w-full max-w-6xl flex flex-col items-center justify-center py-24">
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center max-w-md">
          <AlertCircle className="mx-auto text-amber-500" size={36} />
          <h2 className="mt-3 font-bold text-purple-950">
            {sessionExpired ? "Sesión expirada" : "Acceso restringido"}
          </h2>
          <p className="mt-2 text-sm text-gray-600">
            {sessionExpired
              ? "Tu sesión expiró. Inicia sesión de nuevo para continuar."
              : "El módulo de liquidaciones está disponible solo para el dueño del negocio."}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="w-full max-w-6xl space-y-6">
      {/* Encabezado + controles */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-purple-950">
            Liquidaciones
          </h1>
          <p className="text-sm text-gray-500">
            Calcula y registra el pago de comisiones por período.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {/* Tabs de período */}
          <div className="inline-flex rounded-xl bg-purple-100 p-1">
            {PERIODS.map((p) => (
              <button
                key={p.value}
                onClick={() => setPeriod(p.value)}
                className={`px-4 py-1.5 rounded-lg text-sm font-semibold transition ${
                  period === p.value
                    ? "bg-purple-950 text-white shadow-sm"
                    : "text-purple-900 hover:bg-purple-200"
                }`}
              >
                {p.label}
              </button>
            ))}
          </div>

          {/* Navegación de fecha */}
          <div className="inline-flex items-center rounded-xl border border-purple-200 bg-white">
            <button
              onClick={() => setDate((d) => shiftDate(d, period, -1))}
              className="p-2 text-purple-900 hover:bg-purple-50 rounded-l-xl"
              aria-label="Período anterior"
            >
              <ChevronLeft size={18} />
            </button>
            <button
              onClick={() => setDate(todayISO())}
              className="px-3 py-1.5 text-sm font-semibold text-purple-950 hover:bg-purple-50"
            >
              Hoy
            </button>
            <button
              onClick={() => setDate((d) => shiftDate(d, period, 1))}
              className="p-2 text-purple-900 hover:bg-purple-50 rounded-r-xl"
              aria-label="Período siguiente"
            >
              <ChevronRight size={18} />
            </button>
          </div>
        </div>
      </div>

      {/* Etiqueta del período */}
      <div className="flex items-center gap-2 text-sm text-gray-600">
        <span className="font-semibold text-purple-950">
          {preview?.range?.label ?? "…"}
        </span>
        {loading && <Loader2 className="animate-spin" size={14} />}
        {!loading && preview?.previous_settlements > 0 && (
          <span className="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full">
            Ya hay {preview.previous_settlements} liquidación(es) que tocan este
            período
          </span>
        )}
      </div>

      {successMessage && (
        <div className="flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
          <CheckCircle2 size={18} />
          {successMessage}
        </div>
      )}

      {error && error !== "forbidden" && error !== "unauthorized" && (
        <div className="flex items-center justify-between gap-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
          <span>No se pudo calcular la liquidación del período.</span>
          <button
            onClick={() => fetchPreview(period, date)}
            className="inline-flex items-center gap-1 font-semibold"
          >
            <RefreshCw size={14} /> Reintentar
          </button>
        </div>
      )}

      {/* Tarjetas resumen */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div className="rounded-2xl shadow-sm p-5 border bg-white border-purple-100 text-purple-950">
          <div className="flex items-center justify-between gap-3">
            <span className="text-xs font-medium uppercase tracking-wide text-gray-500">
              Citas pendientes
            </span>
            <span className="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 bg-purple-950 text-white">
              <CalendarCheck size={18} />
            </span>
          </div>
          <p className="mt-2 text-2xl font-extrabold">
            {summary.appointments_count ?? 0}
          </p>
          <p className="text-xs mt-0.5 text-gray-400">Completadas por liquidar</p>
        </div>

        <StatCard
          label="Bruto generado"
          value={summary.total_gross}
          sub="Servicios completados"
          icon={<Wallet size={18} />}
        />
        <StatCard
          label="Comisiones a pagar"
          value={summary.total_commission}
          sub="Total para los trabajadores"
          icon={<Users size={18} />}
        />
        <StatCard
          label="Le queda al negocio"
          value={summary.business_net}
          sub="Bruto − comisiones"
          icon={<PiggyBank size={18} />}
          highlight
        />
      </div>

      {/* Desglose por día: qué está pendiente y qué ya se liquidó */}
      {days.length > 0 && (
        <Card>
          <div className="flex items-center gap-2 p-5 border-b border-purple-100">
            <CalendarDays size={18} className="text-purple-950" />
            <div>
              <h3 className="font-bold text-purple-950">Días del período</h3>
              <p className="text-xs text-gray-500 mt-0.5">
                Día por día: lo que falta por pagar y lo que ya se liquidó.
              </p>
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-purple-50 text-purple-950">
                <tr>
                  <th className="p-3">Día</th>
                  <th className="p-3 text-right">Citas</th>
                  <th className="p-3 text-right">Pendiente</th>
                  <th className="p-3 text-right">Ya liquidado</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {days.map((d) => (
                  <tr key={d.date}>
                    <td className="p-3 font-medium text-gray-800 capitalize">
                      {formatDayShort(d.date)}
                    </td>
                    <td className="p-3 text-right text-gray-600">
                      {d.appointments_count}
                    </td>
                    <td className="p-3 text-right">
                      {d.pending_count > 0 ? (
                        <span className="font-semibold text-purple-900">
                          {formatMoney(d.pending_gross)}
                        </span>
                      ) : (
                        <span className="text-gray-300">—</span>
                      )}
                    </td>
                    <td className="p-3 text-right">
                      {d.settled_count > 0 ? (
                        <span className="inline-flex items-center gap-1 font-semibold text-green-700">
                          <CheckCircle2 size={14} />
                          {formatMoney(d.settled_gross)}
                        </span>
                      ) : (
                        <span className="text-gray-300">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {/* Detalle por trabajador */}
      <Card>
        <div className="flex items-center justify-between gap-4 p-5 border-b border-purple-100">
          <div>
            <h3 className="font-bold text-purple-950">
              Detalle por trabajador
            </h3>
            <p className="text-xs text-gray-500 mt-0.5">
              Comisión = bruto × % del trabajador.
            </p>
          </div>
          <button
            onClick={() => {
              setSaveError(null);
              setConfirmOpen(true);
            }}
            disabled={!hasPending || loading}
            className="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-purple-950 text-white font-semibold hover:bg-purple-800 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <CheckCircle2 size={18} />
            Marcar como pagado
          </button>
        </div>

        {loading && !preview ? (
          <div className="flex flex-col items-center justify-center py-16 text-purple-950">
            <Loader2 className="animate-spin" size={32} />
            <p className="mt-3 text-sm text-gray-500">Calculando liquidación…</p>
          </div>
        ) : !hasPending ? (
          <div className="flex flex-col items-center justify-center py-16 text-gray-400">
            <PiggyBank size={36} strokeWidth={1.5} />
            <p className="mt-3 text-sm text-gray-500 text-center max-w-sm">
              No hay movimientos pendientes en este período. Todo lo completado
              ya fue liquidado.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-purple-900 text-white">
                <tr>
                  <th className="p-4">Trabajador</th>
                  <th className="p-4 text-right">% Comisión</th>
                  <th className="p-4 text-right">Citas</th>
                  <th className="p-4 text-right">Bruto generado</th>
                  <th className="p-4 text-right">Comisión a pagar</th>
                  <th className="p-4 text-right">Le queda al negocio</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {items.map((it, idx) => (
                  <tr key={it.employee_id ?? `na-${idx}`} className="hover:bg-purple-50/40">
                    <td className="p-4 font-semibold text-purple-950">
                      {it.employee_name}
                      {it.employee_id === null && (
                        <span className="ml-2 text-xs font-normal text-gray-400">
                          (sin asignar)
                        </span>
                      )}
                    </td>
                    <td className="p-4 text-right text-gray-600">
                      {Number(it.commission_percentage).toFixed(2)}%
                    </td>
                    <td className="p-4 text-right text-gray-600">
                      {it.appointments_count}
                    </td>
                    <td className="p-4 text-right">
                      {formatMoney(it.gross_generated)}
                    </td>
                    <td className="p-4 text-right font-semibold text-purple-900">
                      {formatMoney(it.commission_payout)}
                    </td>
                    <td className="p-4 text-right font-semibold text-purple-950">
                      {formatMoney(it.business_net)}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-purple-50 font-bold text-purple-950">
                <tr>
                  <td className="p-4">TOTALES</td>
                  <td className="p-4" />
                  <td className="p-4 text-right">
                    {summary.appointments_count ?? 0}
                  </td>
                  <td className="p-4 text-right">
                    {formatMoney(summary.total_gross)}
                  </td>
                  <td className="p-4 text-right">
                    {formatMoney(summary.total_commission)}
                  </td>
                  <td className="p-4 text-right">
                    {formatMoney(summary.business_net)}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </Card>

      {/* Historial */}
      <Card>
        <div className="flex items-center gap-2 p-5 border-b border-purple-100">
          <History size={18} className="text-purple-950" />
          <div>
            <h3 className="font-bold text-purple-950">Historial de liquidaciones</h3>
            <p className="text-xs text-gray-500 mt-0.5">
              {historyTotal} liquidación(es) registrada(s).
            </p>
          </div>
        </div>

        {historyLoading ? (
          <div className="flex justify-center py-12 text-purple-950">
            <Loader2 className="animate-spin" size={28} />
          </div>
        ) : history.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-12 text-gray-400">
            <Receipt size={32} strokeWidth={1.5} />
            <p className="mt-3 text-sm text-gray-500">
              Aún no has registrado ninguna liquidación.
            </p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {history.map((s) => {
              const expanded = expandedId === s.id;
              return (
                <div key={s.id}>
                  <button
                    onClick={() => setExpandedId(expanded ? null : s.id)}
                    className="w-full flex flex-wrap items-center justify-between gap-3 p-4 text-left hover:bg-purple-50/40 transition"
                  >
                    <div>
                      <p className="font-semibold text-purple-950 capitalize">
                        {historyPeriodLabel(s)}
                      </p>
                      <p className="text-xs text-gray-500 mt-0.5">
                        Pagado el{" "}
                        {s.paid_at
                          ? new Date(s.paid_at).toLocaleDateString("es", {
                              day: "numeric",
                              month: "long",
                              year: "numeric",
                            })
                          : "—"}
                        {s.creator?.name ? ` · por ${s.creator.name}` : ""}
                        {s.notes ? ` · ${s.notes}` : ""}
                      </p>
                    </div>
                    <div className="flex items-center gap-6 text-sm">
                      <div className="text-right">
                        <p className="text-xs text-gray-400">Bruto</p>
                        <p className="font-semibold text-gray-700">
                          {formatMoney(s.total_gross)}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-xs text-gray-400">Comisiones</p>
                        <p className="font-semibold text-purple-900">
                          {formatMoney(s.total_commission)}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-xs text-gray-400">Negocio</p>
                        <p className="font-semibold text-purple-950">
                          {formatMoney(s.business_net)}
                        </p>
                      </div>
                      <ChevronRight
                        size={18}
                        className={`text-gray-400 transition-transform ${
                          expanded ? "rotate-90" : ""
                        }`}
                      />
                    </div>
                  </button>

                  {expanded && (
                    <div className="px-4 pb-4">
                      <div className="overflow-x-auto rounded-xl border border-purple-100">
                        <table className="w-full text-left text-sm">
                          <thead className="bg-purple-50 text-purple-950">
                            <tr>
                              <th className="p-3">Trabajador</th>
                              <th className="p-3 text-right">% Comisión</th>
                              <th className="p-3 text-right">Citas</th>
                              <th className="p-3 text-right">Bruto</th>
                              <th className="p-3 text-right">Comisión pagada</th>
                              <th className="p-3 text-right">Negocio</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-gray-100">
                            {(s.items ?? []).map((it) => (
                              <tr key={it.id}>
                                <td className="p-3 font-medium text-gray-800">
                                  {it.employee_name}
                                </td>
                                <td className="p-3 text-right text-gray-600">
                                  {Number(it.commission_percentage).toFixed(2)}%
                                </td>
                                <td className="p-3 text-right text-gray-600">
                                  {it.appointments_count}
                                </td>
                                <td className="p-3 text-right">
                                  {formatMoney(it.gross_generated)}
                                </td>
                                <td className="p-3 text-right font-semibold text-purple-900">
                                  {formatMoney(it.commission_payout)}
                                </td>
                                <td className="p-3 text-right font-semibold text-purple-950">
                                  {formatMoney(it.business_net)}
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}

            {historyLastPage > 1 && (
              <div className="flex items-center justify-between gap-4 p-4">
                <button
                  onClick={() => fetchHistory(historyPage - 1)}
                  disabled={historyPage <= 1}
                  className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-purple-200 text-sm text-purple-900 disabled:opacity-40"
                >
                  <ChevronLeft size={16} /> Anterior
                </button>
                <span className="text-xs text-gray-500">
                  Página {historyPage} de {historyLastPage}
                </span>
                <button
                  onClick={() => fetchHistory(historyPage + 1)}
                  disabled={historyPage >= historyLastPage}
                  className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-purple-200 text-sm text-purple-900 disabled:opacity-40"
                >
                  Siguiente <ChevronRight size={16} />
                </button>
              </div>
            )}
          </div>
        )}
      </Card>

      {/* Modal de confirmación */}
      <Modal
        isOpen={confirmOpen}
        onClose={() => (saving ? null : setConfirmOpen(false))}
        title="Confirmar liquidación"
        eyebrow="Marcar como pagado"
        formId="settlement-form"
        submitLabel={saving ? "Guardando…" : "Confirmar pago"}
        submitDisabled={saving}
        cancelDisabled={saving}
        maxWidth="max-w-lg"
      >
        <form id="settlement-form" onSubmit={handleConfirm} className="space-y-4">
          <p className="text-sm text-gray-600">
            Vas a registrar el pago de{" "}
            <span className="font-semibold text-purple-950">
              {items.length} trabajador(es)
            </span>{" "}
            del período{" "}
            <span className="font-semibold text-purple-950">
              {preview?.range?.label}
            </span>
            .
          </p>

          <div className="bg-purple-50 rounded-xl p-4 space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-600">Bruto generado</span>
              <span className="font-semibold text-gray-800">
                {formatMoney(summary.total_gross)}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Comisiones a pagar</span>
              <span className="font-semibold text-purple-900">
                {formatMoney(summary.total_commission)}
              </span>
            </div>
            <div className="flex justify-between border-t border-purple-200 pt-2">
              <span className="text-gray-600">Le queda al negocio</span>
              <span className="font-bold text-purple-950">
                {formatMoney(summary.business_net)}
              </span>
            </div>
          </div>

          <div className="text-sm">
            <p className="text-gray-600 mb-1">
              Días que se incluyen en este pago:
            </p>
            <ul className="rounded-xl border border-gray-200 divide-y divide-gray-100">
              {days
                .filter((d) => d.pending_count > 0)
                .map((d) => (
                  <li
                    key={d.date}
                    className="flex items-center justify-between px-3 py-2"
                  >
                    <span className="capitalize text-gray-700">
                      {formatDayShort(d.date)}
                    </span>
                    <span className="font-semibold text-gray-800">
                      {formatMoney(d.pending_gross)}
                    </span>
                  </li>
                ))}
            </ul>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Notas (opcional)
            </label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={2}
              maxLength={500}
              placeholder="Ej.: Pago semanal en efectivo"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
          </div>

          {saveError && (
            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
              {saveError}
            </p>
          )}
        </form>
      </Modal>
    </div>
  );
};

export default SettlementsPage;
