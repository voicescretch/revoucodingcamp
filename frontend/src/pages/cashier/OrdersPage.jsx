import { useState, useEffect, useCallback } from 'react'
import MainLayout from '../../components/layout/MainLayout'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Table from '../../components/ui/Table'
import api from '../../services/api'

// ── Constants ────────────────────────────────────────────────────────────────

const STATUS_TABS = [
  { key: '', label: 'Semua' },
  { key: 'pending', label: 'Pending' },
  { key: 'confirmed', label: 'Confirmed' },
  { key: 'preparing', label: 'Preparing' },
  { key: 'ready', label: 'Ready' },
]

const STATUS_BADGE = {
  pending: 'warning',
  confirmed: 'info',
  preparing: 'info',
  ready: 'success',
  completed: 'default',
  cancelled: 'danger',
}

const STATUS_LABEL = {
  pending: 'Pending',
  confirmed: 'Confirmed',
  preparing: 'Preparing',
  ready: 'Ready',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
}

const NEXT_STATUS = {
  pending: { status: 'confirmed', label: 'Konfirmasi' },
  confirmed: { status: 'preparing', label: 'Mulai Proses' },
  preparing: { status: 'ready', label: 'Siap' },
  ready: { status: 'completed', label: 'Selesai' },
}

const ORDER_TYPE_LABEL = {
  self_order: 'QR Order',
  take_away: 'Take Away',
  dine_in: 'Dine In',
}

const COLUMNS = [
  { key: 'order_code', label: 'Kode Order' },
  { key: 'order_type_label', label: 'Tipe' },
  { key: 'table_number', label: 'Meja' },
  { key: 'item_count', label: 'Item' },
  { key: 'status_badge', label: 'Status' },
  { key: 'created_at_fmt', label: 'Waktu' },
]

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function mapRow(order) {
  return {
    ...order,
    order_type_label: ORDER_TYPE_LABEL[order.order_type] ?? order.order_type,
    table_number: order.table?.table_number ? `Meja ${order.table.table_number}` : '—',
    item_count: Array.isArray(order.order_items) ? order.order_items.length : '—',
    status_badge: (
      <Badge
        text={STATUS_LABEL[order.status] ?? order.status}
        variant={STATUS_BADGE[order.status] ?? 'default'}
      />
    ),
    created_at_fmt: formatDate(order.created_at),
  }
}

// ── Cancel Modal ──────────────────────────────────────────────────────────────

function CancelModal({ order, onClose, onConfirm, loading }) {
  const [reason, setReason] = useState('')

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-1">Batalkan Order</h2>
        <p className="text-sm text-gray-500 mb-4">
          Order <span className="font-medium text-gray-700">{order.order_code}</span> akan dibatalkan.
        </p>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Alasan Pembatalan <span className="text-red-500">*</span>
        </label>
        <textarea
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
          rows={3}
          placeholder="Masukkan alasan pembatalan..."
          value={reason}
          onChange={(e) => setReason(e.target.value)}
        />
        <div className="flex justify-end gap-2 mt-4">
          <Button variant="secondary" size="sm" onClick={onClose} disabled={loading}>
            Batal
          </Button>
          <Button
            variant="danger"
            size="sm"
            loading={loading}
            disabled={!reason.trim()}
            onClick={() => onConfirm(reason)}
          >
            Batalkan Order
          </Button>
        </div>
      </div>
    </div>
  )
}

// ── Main Page ─────────────────────────────────────────────────────────────────

const OrdersPage = () => {
  const [orders, setOrders] = useState([])
  const [activeTab, setActiveTab] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [updatingId, setUpdatingId] = useState(null)
  const [cancelTarget, setCancelTarget] = useState(null)
  const [cancelLoading, setCancelLoading] = useState(false)

  const fetchOrders = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const params = activeTab ? { status: activeTab } : {}
      const { data } = await api.get('orders', { params })
      const list = data?.data ?? data ?? []
      setOrders(list)
    } catch (err) {
      setError(err.response?.data?.message ?? 'Gagal memuat data order.')
    } finally {
      setLoading(false)
    }
  }, [activeTab])

  // Initial fetch + re-fetch on tab change
  useEffect(() => {
    fetchOrders()
  }, [fetchOrders])

  // Auto-refresh every 30 seconds
  useEffect(() => {
    const timer = setInterval(fetchOrders, 30_000)
    return () => clearInterval(timer)
  }, [fetchOrders])

  const updateStatus = async (order, status, cancellationReason) => {
    setUpdatingId(order.id)
    try {
      const body = { status }
      if (cancellationReason) body.cancellation_reason = cancellationReason
      await api.put(`orders/${order.id}/status`, body)
      await fetchOrders()
    } catch (err) {
      alert(err.response?.data?.message ?? 'Gagal memperbarui status.')
    } finally {
      setUpdatingId(null)
    }
  }

  const handleNextStatus = (order) => {
    const next = NEXT_STATUS[order.status]
    if (!next) return
    updateStatus(order, next.status)
  }

  const handleCancelConfirm = async (reason) => {
    setCancelLoading(true)
    await updateStatus(cancelTarget, 'cancelled', reason)
    setCancelLoading(false)
    setCancelTarget(null)
  }

  const tableData = orders.map(mapRow)

  const renderActions = (row) => {
    const isUpdating = updatingId === row.id
    const next = NEXT_STATUS[row.status]
    const canCancel = row.status === 'pending' || row.status === 'confirmed'

    return (
      <div className="flex items-center justify-end gap-2">
        {next && (
          <Button
            size="sm"
            variant="primary"
            loading={isUpdating}
            onClick={() => handleNextStatus(row)}
          >
            {next.label}
          </Button>
        )}
        {canCancel && (
          <Button
            size="sm"
            variant="danger"
            disabled={isUpdating}
            onClick={() => setCancelTarget(row)}
          >
            Batalkan
          </Button>
        )}
      </div>
    )
  }

  return (
    <MainLayout>
      <div className="space-y-5">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-semibold text-gray-900">Daftar Order</h1>
            <p className="text-sm text-gray-500 mt-0.5">Kelola dan perbarui status order aktif</p>
          </div>
          <Button variant="secondary" size="sm" onClick={fetchOrders} disabled={loading}>
            {loading ? 'Memuat...' : 'Refresh'}
          </Button>
        </div>

        {/* Filter Tabs */}
        <div className="flex gap-1 border-b border-gray-200">
          {STATUS_TABS.map((tab) => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={[
                'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
                activeTab === tab.key
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ].join(' ')}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Error */}
        {error && (
          <div className="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Table */}
        <Table columns={COLUMNS} data={tableData} actions={renderActions} />
      </div>

      {/* Cancel Modal */}
      {cancelTarget && (
        <CancelModal
          order={cancelTarget}
          onClose={() => setCancelTarget(null)}
          onConfirm={handleCancelConfirm}
          loading={cancelLoading}
        />
      )}
    </MainLayout>
  )
}

export default OrdersPage
