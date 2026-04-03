import { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import api from '../../services/api'

const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)

// ── Cart helpers ──────────────────────────────────────────────────────────────
const cartTotal = (cart) => cart.reduce((sum, item) => sum + item.sell_price * item.quantity, 0)

// ── Sub-components ────────────────────────────────────────────────────────────

const MenuCard = ({ item, qty, onAdd, onRemove }) => (
  <div className="rounded-xl border bg-white p-4 shadow-sm flex flex-col gap-2">
    <div className="flex-1">
      <h3 className="font-semibold text-gray-900">{item.name}</h3>
      {item.description && <p className="mt-1 text-sm text-gray-500 line-clamp-2">{item.description}</p>}
      <p className="mt-2 font-bold text-amber-600">{formatRupiah(item.sell_price)}</p>
    </div>
    <div className="flex items-center gap-3 mt-1">
      <button
        onClick={onRemove}
        disabled={qty === 0}
        className="w-8 h-8 rounded-full border border-gray-300 text-gray-600 font-bold disabled:opacity-30 hover:bg-gray-100 transition"
      >
        −
      </button>
      <span className="w-6 text-center font-semibold text-gray-800">{qty}</span>
      <button
        onClick={onAdd}
        className="w-8 h-8 rounded-full bg-amber-500 text-white font-bold hover:bg-amber-600 transition"
      >
        +
      </button>
    </div>
  </div>
)

const CartSummary = ({ cart, onSubmit, submitting }) => {
  const total = cartTotal(cart)
  const isEmpty = cart.length === 0

  return (
    <div className="rounded-xl border bg-white shadow-md p-5 space-y-4">
      <h2 className="text-lg font-bold text-gray-900">Pesanan Anda</h2>

      {isEmpty ? (
        <p className="text-sm text-gray-400 italic">Belum ada item dipilih.</p>
      ) : (
        <ul className="space-y-2 text-sm">
          {cart.map((item) => (
            <li key={item.product_id} className="flex justify-between">
              <span className="text-gray-700">{item.name} × {item.quantity}</span>
              <span className="font-medium text-gray-900">{formatRupiah(item.sell_price * item.quantity)}</span>
            </li>
          ))}
        </ul>
      )}

      <div className="border-t pt-3 flex justify-between font-bold text-gray-900">
        <span>Total</span>
        <span>{formatRupiah(total)}</span>
      </div>

      <button
        onClick={onSubmit}
        disabled={isEmpty || submitting}
        className="w-full rounded-xl bg-amber-500 py-3 text-white font-bold text-base hover:bg-amber-600 disabled:opacity-40 transition"
      >
        {submitting ? 'Memproses...' : 'Pesan Sekarang'}
      </button>
    </div>
  )
}

const OrderSuccess = ({ orderCode, tableName, onReset }) => (
  <div className="min-h-screen bg-amber-50 flex items-center justify-center p-6">
    <div className="max-w-sm w-full text-center space-y-6">
      <div className="text-6xl">🎉</div>
      <h1 className="text-2xl font-bold text-gray-900">Pesanan Berhasil!</h1>
      <p className="text-gray-600">Tunjukkan kode ini ke kasir saat pembayaran.</p>

      <div className="rounded-2xl bg-white border-2 border-amber-400 shadow-lg p-8">
        <p className="text-sm text-gray-500 mb-2">Kode Pesanan</p>
        <p className="text-5xl font-extrabold tracking-widest text-amber-600 select-all">{orderCode}</p>
      </div>

      {tableName && (
        <p className="text-sm text-gray-500">Meja: <span className="font-semibold text-gray-700">{tableName}</span></p>
      )}

      <button
        onClick={onReset}
        className="mt-4 text-sm text-amber-600 underline hover:text-amber-700"
      >
        Pesan lagi
      </button>
    </div>
  </div>
)

// ── Main Page ─────────────────────────────────────────────────────────────────

const SelfOrderPage = () => {
  const [searchParams] = useSearchParams()
  const tableUuid = searchParams.get('table')

  const [tableInfo, setTableInfo] = useState(null)
  const [menuItems, setMenuItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  // cart: [{ product_id, name, sell_price, quantity }]
  const [cart, setCart] = useState([])
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState(null)
  const [orderCode, setOrderCode] = useState(null)

  // Group menu by category
  const categories = [...new Set(menuItems.map((m) => m.category?.name ?? 'Lainnya'))]

  useEffect(() => {
    if (!tableUuid) {
      setError('QR code tidak valid. Parameter meja tidak ditemukan.')
      setLoading(false)
      return
    }

    const fetchMenu = async () => {
      try {
        const res = await api.get(`tables/${tableUuid}/menu`)
        setTableInfo(res.data.data?.table ?? res.data.table)
        setMenuItems(res.data.data?.menu ?? res.data.menu ?? [])
      } catch (err) {
        const status = err.response?.status
        if (status === 404) {
          setError('Meja tidak ditemukan. Pastikan QR code yang Anda scan benar.')
        } else {
          setError(err.response?.data?.message || 'Gagal memuat menu. Silakan coba lagi.')
        }
      } finally {
        setLoading(false)
      }
    }

    fetchMenu()
  }, [tableUuid])

  const getQty = (productId) => cart.find((c) => c.product_id === productId)?.quantity ?? 0

  const handleAdd = (item) => {
    setCart((prev) => {
      const existing = prev.find((c) => c.product_id === item.id)
      if (existing) {
        return prev.map((c) => c.product_id === item.id ? { ...c, quantity: c.quantity + 1 } : c)
      }
      return [...prev, { product_id: item.id, name: item.name, sell_price: item.sell_price, quantity: 1 }]
    })
  }

  const handleRemove = (item) => {
    setCart((prev) => {
      const existing = prev.find((c) => c.product_id === item.id)
      if (!existing || existing.quantity <= 1) {
        return prev.filter((c) => c.product_id !== item.id)
      }
      return prev.map((c) => c.product_id === item.id ? { ...c, quantity: c.quantity - 1 } : c)
    })
  }

  const handleSubmit = async () => {
    if (cart.length === 0) return
    setSubmitting(true)
    setSubmitError(null)

    try {
      const payload = {
        order_type: 'self_order',
        table_identifier: tableInfo.table_number,
        items: cart.map(({ product_id, quantity }) => ({ product_id, quantity })),
      }
      const res = await api.post('orders', payload)
      setOrderCode(res.data.data?.order_code ?? res.data.order_code)
    } catch (err) {
      const data = err.response?.data
      if (data?.items) {
        const names = data.items.map((i) => i.name ?? i).join(', ')
        setSubmitError(`${data.message} (${names})`)
      } else {
        setSubmitError(data?.message || 'Gagal membuat pesanan. Silakan coba lagi.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleReset = () => {
    setCart([])
    setOrderCode(null)
    setSubmitError(null)
  }

  // ── Render states ──────────────────────────────────────────────────────────

  if (orderCode) {
    return (
      <OrderSuccess
        orderCode={orderCode}
        tableName={tableInfo?.name ?? tableInfo?.table_number}
        onReset={handleReset}
      />
    )
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-amber-50">
        <p className="text-gray-500 animate-pulse">Memuat menu...</p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-amber-50 p-6">
        <div className="max-w-sm w-full text-center space-y-4">
          <div className="text-5xl">⚠️</div>
          <h2 className="text-xl font-bold text-gray-800">Terjadi Kesalahan</h2>
          <p className="text-gray-600">{error}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-amber-50">
      {/* Header */}
      <header className="bg-amber-500 text-white px-4 py-4 shadow">
        <div className="max-w-4xl mx-auto">
          <h1 className="text-xl font-bold">Self-Order</h1>
          {tableInfo && (
            <p className="text-amber-100 text-sm mt-0.5">
              {tableInfo.name ?? `Meja ${tableInfo.table_number}`}
            </p>
          )}
        </div>
      </header>

      <div className="max-w-4xl mx-auto px-4 py-6 lg:flex lg:gap-6 lg:items-start">
        {/* Menu list */}
        <div className="flex-1 space-y-6">
          {categories.map((cat) => (
            <section key={cat}>
              <h2 className="text-base font-bold text-gray-700 uppercase tracking-wide mb-3">{cat}</h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {menuItems
                  .filter((m) => (m.category?.name ?? 'Lainnya') === cat)
                  .map((item) => (
                    <MenuCard
                      key={item.id}
                      item={item}
                      qty={getQty(item.id)}
                      onAdd={() => handleAdd(item)}
                      onRemove={() => handleRemove(item)}
                    />
                  ))}
              </div>
            </section>
          ))}
        </div>

        {/* Cart — sticky on desktop, bottom on mobile */}
        <div className="mt-6 lg:mt-0 lg:w-80 lg:sticky lg:top-6">
          {submitError && (
            <div className="mb-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
              {submitError}
            </div>
          )}
          <CartSummary cart={cart} onSubmit={handleSubmit} submitting={submitting} />
        </div>
      </div>
    </div>
  )
}

export default SelfOrderPage
