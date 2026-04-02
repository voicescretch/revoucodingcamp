import { useState, useEffect } from 'react'
import Button from '../ui/Button'

const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)

const PAYMENT_METHODS = [
  { value: 'cash', label: 'Tunai (Cash)' },
  { value: 'card', label: 'Kartu Debit/Kredit' },
  { value: 'qris', label: 'QRIS' },
]

const PaymentForm = ({ order, onConfirm, loading }) => {
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [paidAmountStr, setPaidAmountStr] = useState('')
  const [error, setError] = useState(null)

  const total = order?.items?.reduce((sum, item) => sum + Number(item.subtotal), 0) ?? 0
  const paidAmount = parseFloat(paidAmountStr) || 0
  const changeAmount = paidAmount - total

  // Reset paid amount when switching payment method
  useEffect(() => {
    setPaidAmountStr('')
    setError(null)
  }, [paymentMethod])

  const handleSubmit = (e) => {
    e.preventDefault()
    setError(null)

    if (!paymentMethod) {
      setError('Pilih metode pembayaran.')
      return
    }

    if (paymentMethod === 'cash') {
      if (!paidAmountStr || paidAmount <= 0) {
        setError('Masukkan jumlah uang yang diterima.')
        return
      }
      if (paidAmount < total) {
        setError(`Jumlah bayar kurang. Kekurangan: ${formatRupiah(total - paidAmount)}`)
        return
      }
    }

    const payload = {
      order_code: order.order_code,
      payment_method: paymentMethod,
      paid_amount: paymentMethod === 'cash' ? paidAmount : total,
    }

    onConfirm(payload)
  }

  if (!order) return null

  return (
    <div className="rounded-lg border bg-white p-6 shadow-sm">
      <h2 className="mb-4 text-lg font-semibold text-gray-800">Pembayaran</h2>

      <form onSubmit={handleSubmit} className="space-y-5">
        {/* Payment method selector */}
        <div>
          <label className="mb-2 block text-sm font-medium text-gray-700">Metode Pembayaran</label>
          <div className="flex flex-wrap gap-3">
            {PAYMENT_METHODS.map((m) => (
              <button
                key={m.value}
                type="button"
                onClick={() => setPaymentMethod(m.value)}
                className={[
                  'rounded-md border px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                  paymentMethod === m.value
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                ].join(' ')}
              >
                {m.label}
              </button>
            ))}
          </div>
        </div>

        {/* Total */}
        <div className="rounded-md bg-gray-50 px-4 py-3">
          <div className="flex justify-between text-sm text-gray-600">
            <span>Total Tagihan</span>
            <span className="font-bold text-gray-900">{formatRupiah(total)}</span>
          </div>
        </div>

        {/* Cash: paid amount input + change */}
        {paymentMethod === 'cash' && (
          <div className="space-y-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Jumlah Uang Diterima (Rp)
              </label>
              <input
                type="number"
                min={0}
                step={1000}
                value={paidAmountStr}
                onChange={(e) => setPaidAmountStr(e.target.value)}
                placeholder="Contoh: 100000"
                className="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>

            {paidAmount >= total && paidAmount > 0 && (
              <div className="flex justify-between rounded-md bg-green-50 px-4 py-3 text-sm">
                <span className="font-medium text-green-700">Kembalian</span>
                <span className="font-bold text-green-700">{formatRupiah(changeAmount)}</span>
              </div>
            )}

            {paidAmount > 0 && paidAmount < total && (
              <div className="flex justify-between rounded-md bg-red-50 px-4 py-3 text-sm">
                <span className="font-medium text-red-600">Kekurangan</span>
                <span className="font-bold text-red-600">{formatRupiah(total - paidAmount)}</span>
              </div>
            )}
          </div>
        )}

        {/* Non-cash: just show total as paid */}
        {paymentMethod !== 'cash' && (
          <p className="text-sm text-gray-500">
            Jumlah yang akan ditagih: <span className="font-semibold text-gray-800">{formatRupiah(total)}</span>
          </p>
        )}

        {error && (
          <p className="rounded-md bg-red-50 px-4 py-2 text-sm text-red-600">{error}</p>
        )}

        <Button type="submit" size="lg" className="w-full" loading={loading}>
          Konfirmasi Pembayaran
        </Button>
      </form>
    </div>
  )
}

export default PaymentForm
