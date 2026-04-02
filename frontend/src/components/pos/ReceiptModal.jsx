import Button from '../ui/Button'

const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)

const PAYMENT_METHOD_LABEL = {
  cash: 'Tunai',
  card: 'Kartu Debit/Kredit',
  qris: 'QRIS',
}

const ReceiptModal = ({ receipt, onClose }) => {
  if (!receipt) return null

  const handlePrint = () => window.print()

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="receipt-title"
    >
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/50" aria-hidden="true" />

      {/* Panel */}
      <div className="relative z-10 w-full max-w-md rounded-lg bg-white shadow-xl print:shadow-none">
        {/* Header */}
        <div className="flex items-center justify-between border-b px-6 py-4 print:hidden">
          <h2 id="receipt-title" className="text-lg font-semibold text-gray-900">
            Struk Pembayaran
          </h2>
          <button
            onClick={onClose}
            className="rounded p-1 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
            aria-label="Tutup struk"
          >
            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Receipt body */}
        <div className="px-6 py-5 font-mono text-sm" id="receipt-content">
          {/* Store header */}
          <div className="mb-4 text-center">
            <p className="text-base font-bold uppercase tracking-widest">Bar &amp; Resto</p>
            <p className="text-xs text-gray-500">Struk Pembayaran</p>
          </div>

          <div className="mb-3 space-y-1 text-xs text-gray-600">
            <div className="flex justify-between">
              <span>No. Transaksi</span>
              <span className="font-semibold text-gray-800">{receipt.transaction_number}</span>
            </div>
            <div className="flex justify-between">
              <span>Tanggal/Waktu</span>
              <span>{receipt.datetime}</span>
            </div>
            {receipt.table_number && (
              <div className="flex justify-between">
                <span>Meja</span>
                <span>{receipt.table_number}</span>
              </div>
            )}
            <div className="flex justify-between">
              <span>Metode Bayar</span>
              <span>{PAYMENT_METHOD_LABEL[receipt.payment_method] ?? receipt.payment_method}</span>
            </div>
          </div>

          <div className="my-3 border-t border-dashed" />

          {/* Items */}
          <table className="w-full text-xs">
            <thead>
              <tr className="text-gray-500">
                <th className="pb-1 text-left font-medium">Item</th>
                <th className="pb-1 text-center font-medium">Qty</th>
                <th className="pb-1 text-right font-medium">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {receipt.items.map((item, idx) => (
                <tr key={idx}>
                  <td className="py-0.5">{item.name}</td>
                  <td className="py-0.5 text-center">{item.qty}</td>
                  <td className="py-0.5 text-right">{formatRupiah(item.subtotal)}</td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="my-3 border-t border-dashed" />

          {/* Totals */}
          <div className="space-y-1 text-xs">
            <div className="flex justify-between">
              <span>Total</span>
              <span className="font-semibold">{formatRupiah(receipt.total_amount)}</span>
            </div>
            <div className="flex justify-between">
              <span>Bayar</span>
              <span>{formatRupiah(receipt.paid_amount)}</span>
            </div>
            <div className="flex justify-between font-bold text-gray-900">
              <span>Kembalian</span>
              <span>{formatRupiah(receipt.change_amount)}</span>
            </div>
          </div>

          <div className="my-3 border-t border-dashed" />

          <p className="text-center text-xs text-gray-400">Terima kasih atas kunjungan Anda!</p>
        </div>

        {/* Actions */}
        <div className="flex gap-3 border-t px-6 py-4 print:hidden">
          <Button variant="secondary" className="flex-1" onClick={onClose}>
            Tutup
          </Button>
          <Button className="flex-1" onClick={handlePrint}>
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Cetak Struk
          </Button>
        </div>
      </div>
    </div>
  )
}

export default ReceiptModal
