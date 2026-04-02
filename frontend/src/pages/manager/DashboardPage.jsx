import { useEffect, useState, useCallback } from 'react'
import MainLayout from '../../components/layout/MainLayout'
import SalesChart from '../../components/charts/SalesChart'
import api from '../../services/api'

const REFRESH_INTERVAL_MS = 60_000

const formatRupiah = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value ?? 0)

// ── Stat Card ────────────────────────────────────────────────────────────────
const StatCard = ({ label, value, icon, colorClass }) => (
  <div className="flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm border border-gray-100">
    <div className={`flex h-12 w-12 items-center justify-center rounded-full text-xl ${colorClass}`}>
      {icon}
    </div>
    <div>
      <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
      <p className="mt-0.5 text-2xl font-bold text-gray-800">{value}</p>
    </div>
  </div>
)

// ── Critical Stock Table ──────────────────────────────────────────────────────
const CriticalStockTable = ({ items }) => (
  <div className="overflow-x-auto rounded-lg border border-gray-200">
    <table className="min-w-full divide-y divide-gray-200 text-sm">
      <thead className="bg-gray-50">
        <tr>
          {['Produk', 'SKU', 'Stok Saat Ini', 'Threshold'].map((h) => (
            <th
              key={h}
              className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-600"
            >
              {h}
            </th>
          ))}
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-100 bg-white">
        {items.length === 0 ? (
          <tr>
            <td colSpan={4} className="px-4 py-8 text-center text-gray-400">
              Tidak ada stok kritis
            </td>
          </tr>
        ) : (
          items.map((item) => (
            <tr key={item.id} className="hover:bg-gray-50 transition-colors">
              <td className="px-4 py-3 font-medium text-gray-800">{item.name}</td>
              <td className="px-4 py-3 text-gray-500">{item.sku}</td>
              <td className="px-4 py-3">
                <span className="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                  {item.stock}
                </span>
              </td>
              <td className="px-4 py-3 text-gray-500">{item.low_stock_threshold}</td>
            </tr>
          ))
        )}
      </tbody>
    </table>
  </div>
)

// ── Page ─────────────────────────────────────────────────────────────────────
const DashboardPage = () => {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const fetchDashboard = useCallback(async () => {
    try {
      const res = await api.get('dashboard')
      setData(res.data.data ?? res.data)
      setError(null)
    } catch (err) {
      setError('Gagal memuat data dashboard.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchDashboard()
    const timer = setInterval(fetchDashboard, REFRESH_INTERVAL_MS)
    return () => clearInterval(timer)
  }, [fetchDashboard])

  return (
    <MainLayout>
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Dashboard</h1>
          <p className="mt-1 text-sm text-gray-500">Ringkasan performa bisnis hari ini</p>
        </div>

        {error && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Stat Cards */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Total Penjualan Hari Ini"
            value={loading ? '—' : formatRupiah(data?.total_sales_today)}
            icon="💰"
            colorClass="bg-indigo-100 text-indigo-600"
          />
          <StatCard
            label="Total Transaksi"
            value={loading ? '—' : (data?.total_transactions_today ?? 0)}
            icon="🧾"
            colorClass="bg-green-100 text-green-600"
          />
          <StatCard
            label="Stok Kritis"
            value={loading ? '—' : (data?.critical_stock_items?.length ?? 0)}
            icon="⚠️"
            colorClass="bg-yellow-100 text-yellow-600"
          />
          <StatCard
            label="Meja Terisi"
            value={loading ? '—' : (data?.occupied_tables_count ?? 0)}
            icon="🪑"
            colorClass="bg-blue-100 text-blue-600"
          />
        </div>

        {/* Sales Chart */}
        <div className="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
          <h2 className="mb-4 text-base font-semibold text-gray-700">Penjualan 7 Hari Terakhir</h2>
          {loading ? (
            <div className="flex h-64 items-center justify-center text-gray-400 text-sm">
              Memuat grafik...
            </div>
          ) : (
            <SalesChart data={data?.sales_chart_7days ?? []} />
          )}
        </div>

        {/* Critical Stock */}
        <div className="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
          <h2 className="mb-4 text-base font-semibold text-gray-700">
            Stok Kritis
            {!loading && data?.critical_stock_items?.length > 0 && (
              <span className="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                {data.critical_stock_items.length} item
              </span>
            )}
          </h2>
          {loading ? (
            <p className="text-sm text-gray-400">Memuat data stok...</p>
          ) : (
            <CriticalStockTable items={data?.critical_stock_items ?? []} />
          )}
        </div>
      </div>
    </MainLayout>
  )
}

export default DashboardPage
