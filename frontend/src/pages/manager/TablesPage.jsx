import { useEffect, useState, useCallback } from 'react'
import MainLayout from '../../components/layout/MainLayout'
import Button from '../../components/ui/Button'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import Table from '../../components/ui/Table'
import api from '../../services/api'

// ─── Status helpers ────────────────────────────────────────────────────────────

const STATUS_OPTIONS = ['available', 'occupied', 'reserved']

const STATUS_LABEL = {
  available: 'Tersedia',
  occupied: 'Terisi',
  reserved: 'Dipesan',
}

const STATUS_VARIANT = {
  available: 'success',
  occupied: 'warning',
  reserved: 'info',
}

// ─── Add Table Modal ───────────────────────────────────────────────────────────

const EMPTY_FORM = { table_number: '', name: '', capacity: '' }

const TableFormModal = ({ isOpen, onClose, onSaved }) => {
  const [form, setForm] = useState(EMPTY_FORM)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (isOpen) { setForm(EMPTY_FORM); setErrors({}) }
  }, [isOpen])

  const validate = () => {
    const e = {}
    if (!form.table_number.trim()) e.table_number = 'Nomor meja wajib diisi'
    if (!form.name.trim()) e.name = 'Nama meja wajib diisi'
    if (!form.capacity || Number(form.capacity) < 1) e.capacity = 'Kapasitas harus minimal 1'
    return e
  }

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
    setErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }
    setSaving(true)
    try {
      await api.post('tables', {
        table_number: form.table_number.trim(),
        name: form.name.trim(),
        capacity: Number(form.capacity),
      })
      onSaved()
    } catch (err) {
      const serverErrors = err.response?.data?.errors ?? {}
      const mapped = {}
      Object.entries(serverErrors).forEach(([k, v]) => {
        mapped[k] = Array.isArray(v) ? v[0] : v
      })
      if (Object.keys(mapped).length) setErrors(mapped)
      else setErrors({ _general: err.response?.data?.message ?? 'Gagal menyimpan meja' })
    } finally {
      setSaving(false)
    }
  }

  const inputClass = (name) =>
    `w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
      errors[name] ? 'border-red-400' : 'border-gray-300'
    }`

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Tambah Meja Baru">
      <form onSubmit={handleSubmit} className="space-y-4">
        {errors._general && (
          <div className="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
            {errors._general}
          </div>
        )}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Nomor Meja *</label>
          <input
            type="text"
            name="table_number"
            value={form.table_number}
            onChange={handleChange}
            placeholder="Contoh: T01"
            className={inputClass('table_number')}
          />
          {errors.table_number && <p className="mt-1 text-xs text-red-600">{errors.table_number}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Nama Meja *</label>
          <input
            type="text"
            name="name"
            value={form.name}
            onChange={handleChange}
            placeholder="Contoh: Meja VIP 1"
            className={inputClass('name')}
          />
          {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Kapasitas *</label>
          <input
            type="number"
            name="capacity"
            value={form.capacity}
            onChange={handleChange}
            min="1"
            placeholder="Jumlah kursi"
            className={inputClass('capacity')}
          />
          {errors.capacity && <p className="mt-1 text-xs text-red-600">{errors.capacity}</p>}
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={onClose}>Batal</Button>
          <Button type="submit" loading={saving}>Tambah Meja</Button>
        </div>
      </form>
    </Modal>
  )
}

// ─── QR Code Modal ─────────────────────────────────────────────────────────────

const QrModal = ({ isOpen, onClose, table }) => {
  const [qrContent, setQrContent] = useState(null)
  const [qrType, setQrType] = useState(null) // 'svg' | 'img'
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!isOpen || !table) return
    setQrContent(null)
    setQrType(null)
    setError(null)
    setLoading(true)

    api
      .get(`tables/${table.id}/qr`, { responseType: 'text', transformResponse: [(data) => data] })
      .then((res) => {
        const data = res.data
        // Detect SVG string (may start with <?xml or <svg)
        if (typeof data === 'string' && (data.trim().startsWith('<svg') || data.trim().startsWith('<?xml'))) {
          setQrContent(data)
          setQrType('svg')
        } else if (typeof data === 'string' && data.trim().startsWith('data:')) {
          // Already a data URL
          setQrContent(data)
          setQrType('img')
        } else if (typeof data === 'string') {
          // Treat as base64 PNG or raw string — wrap as data URL
          setQrContent(`data:image/png;base64,${data}`)
          setQrType('img')
        } else {
          setError('Format QR code tidak dikenali')
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message ?? 'Gagal memuat QR code')
      })
      .finally(() => setLoading(false))
  }, [isOpen, table])

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={`QR Code — ${table?.name ?? ''} (${table?.table_number ?? ''})`}>
      <div className="flex flex-col items-center gap-4 py-2">
        {loading && (
          <div className="text-sm text-gray-400 py-8">Memuat QR code...</div>
        )}
        {error && (
          <div className="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700 w-full">
            {error}
          </div>
        )}
        {!loading && !error && qrType === 'svg' && (
          <div
            className="w-64 h-64 flex items-center justify-center"
            dangerouslySetInnerHTML={{ __html: qrContent }}
          />
        )}
        {!loading && !error && qrType === 'img' && (
          <img
            src={qrContent}
            alt={`QR Code meja ${table?.table_number}`}
            className="w-64 h-64 object-contain"
          />
        )}
        {!loading && !error && qrContent && (
          <p className="text-xs text-gray-400 text-center">
            Scan QR code ini untuk memesan dari meja {table?.name}
          </p>
        )}
        <div className="flex justify-end w-full pt-2">
          <Button variant="secondary" onClick={onClose}>Tutup</Button>
        </div>
      </div>
    </Modal>
  )
}

// ─── Update Status Modal ────────────────────────────────────────────────────────

const UpdateStatusModal = ({ isOpen, onClose, onSaved, table }) => {
  const [status, setStatus] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (isOpen && table) { setStatus(table.status ?? 'available'); setError(null) }
  }, [isOpen, table])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await api.put(`tables/${table.id}`, { status })
      onSaved()
    } catch (err) {
      setError(err.response?.data?.message ?? 'Gagal memperbarui status')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={`Update Status — ${table?.name ?? ''}`}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {error && (
          <div className="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
            {error}
          </div>
        )}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Status Meja</label>
          <div className="flex flex-col gap-2">
            {STATUS_OPTIONS.map((s) => (
              <label key={s} className="flex items-center gap-3 cursor-pointer rounded-lg border px-4 py-3 hover:bg-gray-50 transition-colors">
                <input
                  type="radio"
                  name="status"
                  value={s}
                  checked={status === s}
                  onChange={() => setStatus(s)}
                  className="h-4 w-4 text-blue-600 focus:ring-blue-500"
                />
                <Badge text={STATUS_LABEL[s]} variant={STATUS_VARIANT[s]} />
                <span className="text-sm text-gray-600 capitalize">{STATUS_LABEL[s]}</span>
              </label>
            ))}
          </div>
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={onClose}>Batal</Button>
          <Button type="submit" loading={saving}>Simpan Status</Button>
        </div>
      </form>
    </Modal>
  )
}

// ─── Main Page ─────────────────────────────────────────────────────────────────

const TablesPage = () => {
  const [tables, setTables] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [showAddModal, setShowAddModal] = useState(false)
  const [showQrModal, setShowQrModal] = useState(false)
  const [qrTable, setQrTable] = useState(null)
  const [showStatusModal, setShowStatusModal] = useState(false)
  const [statusTable, setStatusTable] = useState(null)

  const fetchTables = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('tables')
      setTables(res.data.data ?? res.data ?? [])
    } catch {
      setError('Gagal memuat data meja.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { fetchTables() }, [fetchTables])

  const openQr = (table) => { setQrTable(table); setShowQrModal(true) }
  const openStatus = (table) => { setStatusTable(table); setShowStatusModal(true) }

  const columns = [
    { key: 'table_number', label: 'No. Meja' },
    { key: 'name', label: 'Nama' },
    { key: '_capacity', label: 'Kapasitas' },
    { key: '_status', label: 'Status' },
  ]

  const tableData = tables.map((t) => ({
    ...t,
    _capacity: `${t.capacity} orang`,
    _status: (
      <Badge
        text={STATUS_LABEL[t.status] ?? t.status}
        variant={STATUS_VARIANT[t.status] ?? 'default'}
      />
    ),
  }))

  return (
    <MainLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">Manajemen Meja</h1>
            <p className="mt-1 text-sm text-gray-500">Kelola meja, QR code, dan status meja</p>
          </div>
          <Button onClick={() => setShowAddModal(true)}>+ Tambah Meja</Button>
        </div>

        {/* Error */}
        {error && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Status summary */}
        {!loading && tables.length > 0 && (
          <div className="grid grid-cols-3 gap-4">
            {STATUS_OPTIONS.map((s) => {
              const count = tables.filter((t) => t.status === s).length
              return (
                <div key={s} className="rounded-xl bg-white border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-3">
                  <Badge text={STATUS_LABEL[s]} variant={STATUS_VARIANT[s]} />
                  <span className="text-2xl font-bold text-gray-800">{count}</span>
                  <span className="text-sm text-gray-500">meja</span>
                </div>
              )
            })}
          </div>
        )}

        {/* Table */}
        <div className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
          {loading ? (
            <div className="flex items-center justify-center py-16 text-gray-400 text-sm">
              Memuat data...
            </div>
          ) : (
            <Table
              columns={columns}
              data={tableData}
              actions={(row) => (
                <div className="flex items-center justify-end gap-2">
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => openQr(tables.find((t) => t.id === row.id) ?? row)}
                  >
                    Lihat QR
                  </Button>
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => openStatus(tables.find((t) => t.id === row.id) ?? row)}
                  >
                    Update Status
                  </Button>
                </div>
              )}
            />
          )}
        </div>
      </div>

      {/* Modals */}
      <TableFormModal
        isOpen={showAddModal}
        onClose={() => setShowAddModal(false)}
        onSaved={() => { setShowAddModal(false); fetchTables() }}
      />

      <QrModal
        isOpen={showQrModal}
        onClose={() => setShowQrModal(false)}
        table={qrTable}
      />

      <UpdateStatusModal
        isOpen={showStatusModal}
        onClose={() => setShowStatusModal(false)}
        onSaved={() => { setShowStatusModal(false); fetchTables() }}
        table={statusTable}
      />
    </MainLayout>
  )
}

export default TablesPage
