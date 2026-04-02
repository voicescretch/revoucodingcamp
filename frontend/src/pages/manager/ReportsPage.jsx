import { useState } from 'react'
import MainLayout from '../../components/layout/MainLayout'
import Button from '../../components/ui/Button'
import api from '../../services/api'

// ── Helpers ──────────────────────────────────────────────────────────────────

const getMonthStart = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

const getMonthEnd = () => {
  const d = new Date()
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}

const REPORT_TYPES = [
  { value: 'stock', label: 'Laporan Stok', requiresDates: false },
  { value: 'stock-movement', label: 'Laporan Arus Barang', requiresDates: true },
  { value: 'profit-loss', label: 'Laporan Laba-Rugi', requiresDates: true },
]

const FORMATS = [
  { value: 'pdf', label: 'PDF', icon: '📄' },
  { value: 'excel', label: 'Excel', icon: '📊' },
]

const ENDPOINT_MAP = {
  stock: 'reports/stock',
  'stock-movement': 'reports/stock-movement',
  'profit-loss': 'reports/profit-loss',
}

const FILE_EXT = { pdf: 'pdf', excel: 'xlsx' }
const MIME_TYPE = {
  pdf: 'application/pdf',
  excel: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
}

// ── Component ─────────────────────────────────────────────────────────────────

const ReportsPage = () => {
  const [reportType, setReportType] = useState('stock')
  const [format, setFormat] = useState('pdf')
  const [startDate, setStartDate] = useState(getMonthStart())
  const [endDate, setEndDate] = useState(getMonthEnd())
  const [loading, setLoading] = useState(false)
  const [feedback, setFeedback] = useState(null) // { type: 'success'|'error', message }
  const [dateError, setDateError] = useState(null)

  const selectedType = REPORT_TYPES.find((r) => r.value === reportType)

  const validateDates = () => {
    if (!selectedType.requiresDates) return true
    if (!startDate || !endDate) {
      setDateError('Rentang tanggal wajib diisi untuk jenis laporan ini.')
      return false
    }
    if (startDate > endDate) {
      setDateError('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.')
      return false
    }
    setDateError(null)
    return true
  }

  const handleDownload = async () => {
    if (!validateDates()) return

    setLoading(true)
    setFeedback(null)

    const params = { format }
    if (startDate) params.start_date = startDate
    if (endDate) params.end_date = endDate

    try {
      const response = await api.get(ENDPOINT_MAP[reportType], {
        params,
        responseType: 'blob',
      })

      const blob = new Blob([response.data], { type: MIME_TYPE[format] })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${reportType}-${startDate ?? 'all'}-${endDate ?? 'all'}.${FILE_EXT[format]}`
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)

      setFeedback({ type: 'success', message: 'Laporan berhasil diunduh.' })
    } catch (err) {
      const msg =
        err.response?.status === 422
          ? 'Parameter tidak valid. Periksa kembali rentang tanggal.'
          : 'Gagal mengunduh laporan. Silakan coba lagi.'
      setFeedback({ type: 'error', message: msg })
    } finally {
      setLoading(false)
    }
  }

  return (
    <MainLayout>
      <div className="space-y-6 max-w-2xl">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Laporan</h1>
          <p className="mt-1 text-sm text-gray-500">
            Unduh laporan stok, arus barang, atau laba-rugi dalam format PDF atau Excel.
          </p>
        </div>

        {/* Form Card */}
        <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">

          {/* Jenis Laporan */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Jenis Laporan
            </label>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
              {REPORT_TYPES.map((rt) => (
                <button
                  key={rt.value}
                  type="button"
                  onClick={() => {
                    setReportType(rt.value)
                    setDateError(null)
                    setFeedback(null)
                  }}
                  className={[
                    'rounded-lg border px-4 py-3 text-sm font-medium text-left transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                    reportType === rt.value
                      ? 'border-blue-500 bg-blue-50 text-blue-700'
                      : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
                  ].join(' ')}
                >
                  {rt.label}
                </button>
              ))}
            </div>
          </div>

          {/* Format */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Format
            </label>
            <div className="flex gap-3">
              {FORMATS.map((f) => (
                <button
                  key={f.value}
                  type="button"
                  onClick={() => {
                    setFormat(f.value)
                    setFeedback(null)
                  }}
                  className={[
                    'flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                    format === f.value
                      ? 'border-blue-500 bg-blue-50 text-blue-700'
                      : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
                  ].join(' ')}
                >
                  <span>{f.icon}</span>
                  {f.label}
                </button>
              ))}
            </div>
          </div>

          {/* Rentang Tanggal */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Rentang Tanggal
              {!selectedType.requiresDates && (
                <span className="ml-1 text-xs font-normal text-gray-400">(opsional untuk laporan stok)</span>
              )}
            </label>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="flex flex-1 flex-col gap-1">
                <label className="text-xs text-gray-500">Dari</label>
                <input
                  type="date"
                  value={startDate}
                  onChange={(e) => {
                    setStartDate(e.target.value)
                    setDateError(null)
                    setFeedback(null)
                  }}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <span className="hidden text-gray-400 sm:block mt-4">—</span>
              <div className="flex flex-1 flex-col gap-1">
                <label className="text-xs text-gray-500">Sampai</label>
                <input
                  type="date"
                  value={endDate}
                  onChange={(e) => {
                    setEndDate(e.target.value)
                    setDateError(null)
                    setFeedback(null)
                  }}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </div>

            {dateError && (
              <p className="mt-2 text-sm text-red-600">{dateError}</p>
            )}
          </div>

          {/* Feedback */}
          {feedback && (
            <div
              className={[
                'rounded-lg border px-4 py-3 text-sm',
                feedback.type === 'success'
                  ? 'border-green-200 bg-green-50 text-green-700'
                  : 'border-red-200 bg-red-50 text-red-700',
              ].join(' ')}
            >
              {feedback.message}
            </div>
          )}

          {/* Download Button */}
          <div className="pt-1">
            <Button
              onClick={handleDownload}
              loading={loading}
              disabled={loading}
              size="lg"
              className="w-full sm:w-auto"
            >
              {loading ? 'Mengunduh...' : `Unduh ${FORMATS.find((f) => f.value === format)?.label}`}
            </Button>
          </div>
        </div>
      </div>
    </MainLayout>
  )
}

export default ReportsPage
