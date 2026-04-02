import { useState } from 'react'
import api from '../../services/api'
import Button from '../ui/Button'

// Format number as Indonesian Rupiah
const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)

const ORDER_TYPE_LABEL = {
  self_order: 'Self Order (QR)',
  take_away: 'Take Away',
  dine_in: 'Dine In',
}

const OrderCodeInput = ({ onOrderLoaded }) => {
  const [code, setCode] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [order, setOrder] = useState(null)

  const handleSearch = async (e) => {
    e.preventDefault()
    const trimmed = code.trim().toUpperCase()
    if (!trimmed) return

    setLoading(true)
    setError(null)
    setOrder(null)

    try {
      const res = await api.get(`orders/by-code/${trimmed}`)
      const data = res.data.data

      if (data.status === 'completed') {
        setError(`Order ${trimmed} sudah selesai diproses.`)
        return
      }
      if (data.status === 'cancelled') {
        setError(`Order ${trimmed} telah dibatalkan.`)
        return
      }

      setOrder(data)
      onOrderLoaded(data)
    } catch (err) {
      const msg = err.response?.data?.message
      setError(msg || 'Order tidak ditemukan. Periksa kembali kode order.')
    } finally {
      setLoading(false)
    }
  }

  const handleReset = () => {
    setCode('')
    setOrder(null)
    setError(null)
    onOrderLoaded(null)
  }

  const total = order?.items?.reduce((sum, item) => sum + Number(item.subtotal), 0) ?? 0

  return (
    <div className="rounded-lg border bg-white p-6 shadow-sm">
      <h2 className="mb-4 text-lg font-semibold text-gray-800">Cari Order</h2>

      <form onSubmit={handleSearch} className="flex gap-3">
        <input
          type="text"
          value={code}
          onChange={(e) => setCode(e.target.value.toUpperCase())}
          placeholder="Masukkan atau scan Order Code..."
          className="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm uppercase tracking-widest focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          disabled={loading}
          autoFocus
        />
        <Button type="submit" loading={loading} disabled={!code.trim()}>
          Cari
        </Button>
        {order && (
          <Button type="button" variant="secondary" onClick={handleReset}>
            Reset
          </Button>
        )}
      </form>

      {error && (
        <p className="mt-3 rounded-md bg-red-50 px-4 py-2 text-sm text-red-600">{error}</p>
      )}

      {order && (
        <div className="mt-5">
          <div className="mb-3 flex flex-wrap items-center gap-4 text-sm text-gray-600">
            <span>
              <span className="font-medium text-gray-800">Kode:</span>{' '}
              <span className="font-mono font-semibold text-blue-700">{order.order_code}</span>
            </span>
            <span>
              <span className="font-medium text-gray-800">Tipe:</span>{' '}
              {ORDER_TYPE_LABEL[order.order_type] ?? order.order_type}
            </span>
            {order.table?.table_number && (
              <span>
                <span className="font-medium text-gray-800">Meja:</span>{' '}
                {order.table.table_number}
              </span>
            )}
            <span>
              <span className="font-medium text-gray-800">Status:</span>{' '}
              <span className="capitalize">{order.status}</span>
            </span>
          </div>

          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-gray-500">
                <th className="pb-2 font-medium">Item</th>
                <th className="pb-2 text-center font-medium">Qty</th>
                <th className="pb-2 text-right font-medium">Harga Satuan</th>
                <th className="pb-2 text-right font-medium">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {order.items.map((item, idx) => (
                <tr key={idx} className="border-b last:border-0">
                  <td className="py-2">{item.product?.name ?? '-'}</td>
                  <td className="py-2 text-center">{item.quantity}</td>
                  <td className="py-2 text-right">{formatRupiah(item.unit_price)}</td>
                  <td className="py-2 text-right">{formatRupiah(item.subtotal)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={3} className="pt-3 text-right font-semibold text-gray-800">
                  Total
                </td>
                <td className="pt-3 text-right font-bold text-blue-700">
                  {formatRupiah(total)}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </div>
  )
}

export default OrderCodeInput
