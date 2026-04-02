import { useEffect, useState, useCallback } from 'react'
import MainLayout from '../../components/layout/MainLayout'
import Button from '../../components/ui/Button'
import Badge from '../../components/ui/Badge'
import Table from '../../components/ui/Table'
import api from '../../services/api'

const formatRupiah = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value ?? 0)

const PERIODS = [
  { key: 'daily', label: 'Harian' },
  { key: 'weekly', label: 'Mingguan' },
  { key: 'monthly', label: 'Bulanan' },
]

/** Compute start_date / end_date from a period key */
const getDateRange = (period) => {
  const now = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

  if (period === 'daily') {
    const today = fmt(now)
    return { start_date: today, end_date: today }
  }

  if (period === 'weekly') {
    const day = now.getDay() // 0=Sun
    const diffToMon = (day === 0 ? -6 : 1 - day)
    const mon = new Date(now)
    mon.setDate(now.getDate() + diffToMon)
    const sun = new Date(mon)
    sun.setDate(mon.getDate() + 6)
    return { start_date: fmt(mon), end_date: fmt(sun) }
  }

  // monthly
  const year = now.getFullYear()
  const month = now.getMonth() + 1
  const lastDay = new Date(year, month, 0).getDate()
  return {
    start_date: `${year}-${pad(month)}-01`,
    end_date: `${year}-${pad(month)}-${pad(lastDay)}`,
  }
}

const SummaryCard = ({ title, value, colorClass, subtext }) => (
  <div className={`rounded-xl border px-6 py-5 flex flex-col gap-1 ${colorClass}`}>
    <p className="text-sm font-medium opacity-75">{title}</p>
    <p className="text-2xl font-bold">{value}</p>
    {subtext && <p className="text-xs opacity-60">{subtext}</p>}
  </div>
)

const FinanceSummaryPage = () => {
  const [period, setPeriod] = useState('monthly')
  const [summary, setSummary] = useState(null)
  const [incomeList, setIncomeList] = useState([])
  const [loadingSummary, setLoadingSummary] = useState(true)
  const [loadingIncome, setLoadingIncome] = useState(true)
  const [error, setError] = useState(null)
  const [validatingId, setValidatingId] = useState(null)

  const dateRange = getDateRange(period)

  const fetchSummary = useCallback(async () => {
    setLoadingSummary(true)
    try {
      const res = await api.get('finance/summary', { params: { period } })
      setSummary(res.data.data ?? res.data)
    } catch {
      setError('Gagal memuat rekap keuangan.')
    } finally {
      setLoadingSummary(false)
    }
  }, [period])

  const fetchIncome = useCallback(async () => {
    setLoadingIncome(true)
    try {
      const res = await api.get('income', {
        params: { start_date: dateRange.start_date, end_date: dateRange.end_date },
      })
      setIncomeList(res.data.data ?? res.data ?? [])
    } catch {
      setError('Gagal memuat daftar pemasukan.')
    } finally {
      setLoadingIncome(false)
    }
  }, [dateRange.start_date, dateRange.end_date])

  useEffect(() => {
    setError(null)
    fetchSummary()
    fetchIncome()
  }, [fetchSummary, fetchIncome])

  const handleValidate = async (id) => {
    setValidatingId(id)
    try {
      await api.put(`income/${id}/validate`)
      setIncomeList((prev) =>
        prev.map((item) => (item.id === id ? { ...item, status: 'validated' } : item))
      )
    } catch {
      setError('Gagal memvalidasi pemasukan.')
    } finally {
      setValidatingId(null)
    }
  }

  // --- Summary card config ---
  const isLoss = summary?.is_loss ?? false

  const netProfitColor = isLoss
    ? 'bg-red-50 border-red-200 text-red-800'
    : 'bg-green-50 border-green-200 text-green-800'

  // --- Income table ---
  const incomeColumns = [
    { key: '_date', label: 'Tanggal' },
    { key: '_amount', label: 'Jumlah' },
    { key: 'category', label: 'Kategori' },
    { key: 'description', label: 'Keterangan' },
    { key: '_source', label: 'Sumber' },
    { key: '_status', label: 'Status' },
  ]

  const incomeData = incomeList.map((item) => ({
    ...item,
    _date: item.date ?? '—',
    _amount: formatRupiah(item.amount),
    _source: (
      <Badge
        text={item.source === 'pos' ? 'POS' : 'Manual'}
        variant={item.source === 'pos' ? 'info' : 'default'}
      />
    ),
    _status: (
      <Badge
        text={item.status === 'validated' ? 'Tervalidasi' : 'Pending'}
        variant={item.status === 'validated' ? 'success' : 'warning'}
      />
    ),
  }))

  const incomeActions = (row) =>
    row.status === 'pending' ? (
      <Button
        size="sm"
        variant="secondary"
        loading={validatingId === row.id}
        onClick={() => handleValidate(row.id)}
      >
        Validasi
      </Button>
    ) : null

  return (
    <MainLayout>
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Rekap Keuangan</h1>
          <p className="mt-1 text-sm text-gray-500">
            Ringkasan pemasukan, pengeluaran, dan laba/rugi
          </p>
        </div>

        {/* Period Toggle */}
        <div className="flex gap-2">
          {PERIODS.map(({ key, label }) => (
            <button
              key={key}
              onClick={() => setPeriod(key)}
              className={[
                'px-4 py-2 rounded-lg text-sm font-medium border transition-colors',
                period === key
                  ? 'bg-blue-600 text-white border-blue-600'
                  : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50',
              ].join(' ')}
            >
              {label}
            </button>
          ))}
        </div>

        {error && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Summary Cards */}
        {loadingSummary ? (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="rounded-xl border border-gray-200 bg-gray-50 px-6 py-5 animate-pulse h-24" />
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <SummaryCard
              title="Total Pemasukan"
              value={formatRupiah(summary?.total_income)}
              colorClass="bg-green-50 border-green-200 text-green-800"
              subtext={`${dateRange.start_date} s/d ${dateRange.end_date}`}
            />
            <SummaryCard
              title="Total Pengeluaran"
              value={formatRupiah(summary?.total_expense)}
              colorClass="bg-red-50 border-red-200 text-red-800"
              subtext={`${dateRange.start_date} s/d ${dateRange.end_date}`}
            />
            <SummaryCard
              title={isLoss ? 'Rugi Bersih' : 'Laba Bersih'}
              value={formatRupiah(summary?.net_profit)}
              colorClass={netProfitColor}
              subtext={isLoss ? '⚠ Periode ini mengalami kerugian' : 'Periode ini mengalami keuntungan'}
            />
          </div>
        )}

        {/* Income List */}
        <div className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="text-base font-semibold text-gray-700">Daftar Pemasukan</h2>
            <p className="text-xs text-gray-400 mt-0.5">
              {dateRange.start_date} s/d {dateRange.end_date}
            </p>
          </div>

          {loadingIncome ? (
            <div className="flex items-center justify-center py-16 text-gray-400 text-sm">
              Memuat data...
            </div>
          ) : (
            <Table columns={incomeColumns} data={incomeData} actions={incomeActions} />
          )}
        </div>
      </div>
    </MainLayout>
  )
}

export default FinanceSummaryPage
