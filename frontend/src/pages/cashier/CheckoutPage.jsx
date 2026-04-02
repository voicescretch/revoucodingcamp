import { useState } from 'react'
import MainLayout from '../../components/layout/MainLayout'
import OrderCodeInput from '../../components/pos/OrderCodeInput'
import PaymentForm from '../../components/pos/PaymentForm'
import ReceiptModal from '../../components/pos/ReceiptModal'
import api from '../../services/api'

const CheckoutPage = () => {
  const [order, setOrder] = useState(null)
  const [receipt, setReceipt] = useState(null)
  const [checkoutLoading, setCheckoutLoading] = useState(false)
  const [checkoutError, setCheckoutError] = useState(null)

  const handleOrderLoaded = (data) => {
    setOrder(data)
    setCheckoutError(null)
  }

  const handleConfirmPayment = async (payload) => {
    setCheckoutLoading(true)
    setCheckoutError(null)

    try {
      const res = await api.post('transactions/checkout', payload)
      setReceipt(res.data.data)
    } catch (err) {
      const data = err.response?.data
      if (data?.items) {
        // Insufficient stock error
        const itemList = data.items.map((i) => i.name ?? i).join(', ')
        setCheckoutError(`${data.message} (${itemList})`)
      } else {
        setCheckoutError(data?.message || 'Terjadi kesalahan saat memproses pembayaran.')
      }
    } finally {
      setCheckoutLoading(false)
    }
  }

  const handleReceiptClose = () => {
    setReceipt(null)
    setOrder(null)
    setCheckoutError(null)
  }

  return (
    <MainLayout>
      <div className="mx-auto max-w-2xl space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">Checkout Kasir</h1>

        <OrderCodeInput onOrderLoaded={handleOrderLoaded} />

        {order && (
          <>
            {checkoutError && (
              <div className="rounded-md bg-red-50 px-4 py-3 text-sm text-red-600">
                {checkoutError}
              </div>
            )}
            <PaymentForm
              order={order}
              onConfirm={handleConfirmPayment}
              loading={checkoutLoading}
            />
          </>
        )}
      </div>

      <ReceiptModal receipt={receipt} onClose={handleReceiptClose} />
    </MainLayout>
  )
}

export default CheckoutPage
