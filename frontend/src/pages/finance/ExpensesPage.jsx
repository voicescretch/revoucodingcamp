import { useEffect, useState, useCallback } from 'react'
import MainLayout from '../../components/layout/MainLayout'
import Button from '../../components/ui/Button'
import Modal from '../../components/ui/Modal'
import Table from '../../components/ui/Table'
import api from '../../services/api'

const formatRupiah = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value ?? 0)

const getDefaultDates = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const lastDay = new Date(year, now.getMonth() + 1, 0).getDate()
  return {
    start_date: `${year}-${month}-01`,
    end_date: `${year}-${month}-${String(lastDay).padStart(2, '0')}`,
  }
}

const EMPTY_FORM = {
  date: new Date().toISOString().slice(0, 10),
  amount: '',
  category: '',
  description: '',
  receipt: null,
}

const AddExpenseModal = ({ isOpen, onClose, onSaved }) => {
  const [form, setForm] = useState(EMPTY_FORM)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (isOpen) {
      setForm({ ...EMPTY_FORM, date: new Date().toISOString().slice(0, 10) })
      setErrors({})
    }
  }, [isOpen])

  const validate = () => {
    const e = {}
    if (!form.date) e.date = 'Tanggal wajib diisi'
    if (!form.amount || Number(form.amount) <= 0) e.amount = 'Jumlah harus lebih dari 0'
    if (!form.category.trim()) e.category = 'Kategori wajib diisi'
    return e
  }

  const handleChange = (e) => {
    const { name, value, files } = e.target
    if (name === 'receipt') {
      setForm((prev) => ({ ...prev, receipt: files[0] ?? null }))
    } else {
      setForm((prev) => ({ ...prev, [name]: value }))
    }
    setErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }
    setSaving(true)
    try {
      const data = new FormData()
      data.append('date', form.date)
      data.append('amount', form.amount)
      data.append('category', form.category.trim())
      data.append('description', form.description.trim())
      if (form.receipt) data.append('receipt', form.receipt)

      await api.post('expenses', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      onSaved()
    } catch (err) {
      const serverErrors = err.response?.data?.errors ?? {}
      const mapped = {}
      Object.entries(serverErrors).forEach(([k, v]) => {
        mapped[k] = Array.isArray(v) ? v[0] : v
      })
      if (Object.keys(mapped).length) setErrors(mapped)
      else setErrors({ _general: err.response?.data?.message ?? 'Gagal menyimpan pengeluaran' })
    } finally {
      setSaving(false)
    }
  }

  const inputClass = (name) =>
    `w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
      errors[name] ? 'border-red-400' : 'border-gray-300'
    }`

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Tambah Pengeluaran">
      <form onSubmit={handleSubmit} className="space-y-4">
        {errors._general && (
          <div className="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
            {errors._general}
          </div>
        )}

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal *</label>
            <input
              type="date"
              name="date"
              value={form.date}
              onChange={handleChange}
              className={inputClass('date')}
            />
            {errors.date && <p className="mt-1 text-xs text-red-600">{errors.date}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp) *</label>
            <input
              type="number"
              name="amount"
              value={form.amount}
              onChange={handleChange}
              min="1"
              placeholder="0"
              className={inputClass('amount')}
            />
            {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Kategori *</label>
          <input
            type="text"
            name="category"
            value={form.category}
            onChange={handleChange}
            placeholder="Contoh: Bahan Baku, Operasional, Gaji..."
            className={inputClass('category')}
          />
          {errors.category && <p className="mt-1 text-xs text-red-600">{errors.category}</p>}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
          <textarea
            name="description"
            value={form.description}
            onChange={handleChange}
            rows={3}
            placeholder="Deskripsi pengeluaran..."
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Bukti Pengeluaran (opsional)</label>
          <input
            type="file"
            name="receipt"
            accept="image/*,application/pdf"
            onChange={handleChange}
            className="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
          />
          {form.receipt && (
            <p className="mt-1 text-xs text-gray-500">File dipilih: {form.receipt.name}</p>
          )}
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={onClose}>Batal</Button>
          <Button type="submit" loading={saving}>Simpan Pengeluaran</Button>
        </div>
      </form>
    </Modal>
  )
}

const ExpensesPage = () => {
  const defaults = getDefaultDates()
  const [startDate, setStartDate] = useState(defaults.start_date)
  const [endDate, setEndDate] = useState(defaults.end_date)
  const [expenses, setExpenses] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [showModal, setShowModal] = useState(false)

  const fetchExpenses = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('expenses', {
        params: { start_date: startDate, end_date: endDate },
      })
      setExpenses(res.data.data ?? res.data ?? [])
    } catch {
      setError('Gagal memuat data pengeluaran.')
    } finally {
      setLoading(false)
    }
  }, [startDate, endDate])

  useEffect(() => { fetchExpenses() }, [fetchExpenses])

  const total = expenses.reduce((sum, e) => sum + Number(e.amount ?? 0), 0)

  const columns = [
    { key: 'date', label: 'Tanggal' },
    { key: '_amount', label: 'Jumlah' },
    { key: 'category', label: 'Kategori' },
    { key: 'description', label: 'Keterangan' },
    { key: '_receipt', label: 'Bukti' },
    { key: '_created_by', label: 'Dicatat Oleh' },
  ]

  const tableData = expenses.map((exp) => ({
    ...exp,
    _amount: formatRupiah(exp.amount),
    _receipt: exp.receipt_path ? (
      <a
        href={exp.receipt_path}
        target="_blank"
        rel="noopener noreferrer"
        className="text-blue-600 hover:underline text-xs"
      >
        Lihat Bukti
      </a>
    ) : (
      <span className="text-gray-400 text-xs">—</span>
    ),
    _created_by: exp.created_by?.name ?? exp.created_by ?? '—',
  }))

  return (
    <MainLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">Pengeluaran</h1>
            <p className="mt-1 text-sm text-gray-500">Catat dan pantau pengeluaran operasional</p>
          </div>
          <Button onClick={() => setShowModal(true)}>+ Tambah Pengeluaran</Button>
        </div>

        {/* Date Filter */}
        <div className="rounded-xl bg-white shadow-sm border border-gray-100 p-4">
          <div className="flex flex-wrap items-end gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Dari Tanggal</label>
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Sampai Tanggal</label>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <Button variant="secondary" onClick={fetchExpenses}>Terapkan Filter</Button>
          </div>
        </div>

        {/* Summary Card */}
        <div className="rounded-xl bg-red-50 border border-red-100 px-6 py-4 flex items-center justify-between">
          <div>
            <p className="text-sm text-red-600 font-medium">Total Pengeluaran</p>
            <p className="text-xs text-red-400 mt-0.5">
              {startDate} s/d {endDate}
            </p>
          </div>
          <p className="text-2xl font-bold text-red-700">{formatRupiah(total)}</p>
        </div>

        {error && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Table */}
        <div className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
          {loading ? (
            <div className="flex items-center justify-center py-16 text-gray-400 text-sm">
              Memuat data...
            </div>
          ) : (
            <Table columns={columns} data={tableData} />
          )}
        </div>
      </div>

      <AddExpenseModal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        onSaved={() => { setShowModal(false); fetchExpenses() }}
      />
    </MainLayout>
  )
}

export default ExpensesPage
