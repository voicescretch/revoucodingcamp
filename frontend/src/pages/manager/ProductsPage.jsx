import { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import MainLayout from '../../components/layout/MainLayout'
import Button from '../../components/ui/Button'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import Table from '../../components/ui/Table'
import api from '../../services/api'

const formatRupiah = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value ?? 0)

const EMPTY_FORM = {
  sku: '',
  name: '',
  category_id: '',
  unit: '',
  buy_price: '',
  sell_price: '',
  stock: '',
  low_stock_threshold: '',
  is_available: true,
}

const EMPTY_STOCK_FORM = { type: 'in', quantity: '', notes: '' }

const ProductFormModal = ({ isOpen, onClose, onSaved, editProduct, allSkus }) => {
  const [form, setForm] = useState(EMPTY_FORM)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [categories, setCategories] = useState([])

  useEffect(() => {
    if (!isOpen) return
    setForm(
      editProduct
        ? {
            sku: editProduct.sku ?? '',
            name: editProduct.name ?? '',
            category_id: editProduct.category?.id ?? '',
            unit: editProduct.unit ?? '',
            buy_price: editProduct.buy_price ?? '',
            sell_price: editProduct.sell_price ?? '',
            stock: editProduct.stock ?? '',
            low_stock_threshold: editProduct.low_stock_threshold ?? '',
            is_available: editProduct.is_available ?? true,
          }
        : EMPTY_FORM
    )
    setErrors({})
  }, [isOpen, editProduct])

  useEffect(() => {
    if (!isOpen) return
    api
      .get('categories?type=product')
      .then((res) => setCategories(res.data.data ?? res.data ?? []))
      .catch(() => setCategories([]))
  }, [isOpen])

  const validate = () => {
    const e = {}
    if (!form.sku.trim()) {
      e.sku = 'SKU wajib diisi'
    } else {
      const skuExists = allSkus.includes(form.sku.trim())
      const skuChanged = !editProduct || form.sku.trim() !== editProduct.sku
      if (skuExists && skuChanged) e.sku = `SKU "${form.sku.trim()}" sudah digunakan`
    }
    if (!form.name.trim()) e.name = 'Nama wajib diisi'
    if (!form.unit.trim()) e.unit = 'Satuan wajib diisi'
    if (form.buy_price === '' || Number(form.buy_price) < 0) e.buy_price = 'Harga beli tidak valid'
    if (form.sell_price === '' || Number(form.sell_price) < 0) e.sell_price = 'Harga jual tidak valid'
    if (form.stock === '' || Number(form.stock) < 0) e.stock = 'Stok tidak valid'
    if (form.low_stock_threshold === '' || Number(form.low_stock_threshold) < 0)
      e.low_stock_threshold = 'Threshold tidak valid'
    return e
  }

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }))
    setErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }
    setSaving(true)
    try {
      const payload = {
        ...form,
        buy_price: Number(form.buy_price),
        sell_price: Number(form.sell_price),
        stock: Number(form.stock),
        low_stock_threshold: Number(form.low_stock_threshold),
        category_id: form.category_id ? Number(form.category_id) : undefined,
      }
      if (editProduct) {
        await api.put(`products/${editProduct.id}`, payload)
      } else {
        await api.post('products', payload)
      }
      onSaved()
    } catch (err) {
      const serverErrors = err.response?.data?.errors ?? {}
      const mapped = {}
      Object.entries(serverErrors).forEach(([k, v]) => {
        mapped[k] = Array.isArray(v) ? v[0] : v
      })
      if (Object.keys(mapped).length) setErrors(mapped)
      else setErrors({ _general: err.response?.data?.message ?? 'Gagal menyimpan produk' })
    } finally {
      setSaving(false)
    }
  }

  const inputClass = (name) =>
    `w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
      errors[name] ? 'border-red-400' : 'border-gray-300'
    }`

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={editProduct ? 'Edit Produk' : 'Tambah Produk'}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {errors._general && (
          <div className="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
            {errors._general}
          </div>
        )}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">SKU *</label>
            <input type="text" name="sku" value={form.sku} onChange={handleChange} className={inputClass('sku')} />
            {errors.sku && <p className="mt-1 text-xs text-red-600">{errors.sku}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nama Produk *</label>
            <input type="text" name="name" value={form.name} onChange={handleChange} className={inputClass('name')} />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
            {categories.length > 0 ? (
              <select
                name="category_id"
                value={form.category_id}
                onChange={handleChange}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Pilih Kategori</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            ) : (
              <input
                type="number"
                name="category_id"
                value={form.category_id}
                onChange={handleChange}
                placeholder="ID Kategori"
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            )}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Satuan *</label>
            <input type="text" name="unit" value={form.unit} onChange={handleChange} className={inputClass('unit')} />
            {errors.unit && <p className="mt-1 text-xs text-red-600">{errors.unit}</p>}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Harga Beli (Rp) *</label>
            <input type="number" name="buy_price" value={form.buy_price} onChange={handleChange} className={inputClass('buy_price')} />
            {errors.buy_price && <p className="mt-1 text-xs text-red-600">{errors.buy_price}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Harga Jual (Rp) *</label>
            <input type="number" name="sell_price" value={form.sell_price} onChange={handleChange} className={inputClass('sell_price')} />
            {errors.sell_price && <p className="mt-1 text-xs text-red-600">{errors.sell_price}</p>}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Stok Awal *</label>
            <input type="number" name="stock" value={form.stock} onChange={handleChange} className={inputClass('stock')} />
            {errors.stock && <p className="mt-1 text-xs text-red-600">{errors.stock}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Threshold Stok Kritis *</label>
            <input type="number" name="low_stock_threshold" value={form.low_stock_threshold} onChange={handleChange} className={inputClass('low_stock_threshold')} />
            {errors.low_stock_threshold && <p className="mt-1 text-xs text-red-600">{errors.low_stock_threshold}</p>}
          </div>
        </div>
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="is_available"
            name="is_available"
            checked={form.is_available}
            onChange={handleChange}
            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <label htmlFor="is_available" className="text-sm text-gray-700">Tersedia (tampil di menu)</label>
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={onClose}>Batal</Button>
          <Button type="submit" loading={saving}>
            {editProduct ? 'Simpan Perubahan' : 'Tambah Produk'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

const StockAdjustModal = ({ isOpen, onClose, onSaved, product }) => {
  const [form, setForm] = useState(EMPTY_STOCK_FORM)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (isOpen) { setForm(EMPTY_STOCK_FORM); setError(null) }
  }, [isOpen])

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.quantity || Number(form.quantity) <= 0) {
      setError('Jumlah harus lebih dari 0')
      return
    }
    setSaving(true)
    try {
      await api.post('stock-movements', {
        product_id: product.id,
        type: form.type,
        quantity: Number(form.quantity),
        notes: form.notes,
      })
      onSaved()
    } catch (err) {
      setError(err.response?.data?.message ?? 'Gagal menyesuaikan stok')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={`Sesuaikan Stok - ${product?.name ?? ''}`}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {error && (
          <div className="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{error}</div>
        )}
        <div className="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
          Stok saat ini:{' '}
          <span className="font-semibold text-gray-800">
            {product?.stock ?? 0} {product?.unit}
          </span>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Jenis Penyesuaian</label>
          <div className="flex gap-6">
            <label className="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="type" value="in" checked={form.type === 'in'} onChange={handleChange} />
              <span className="text-sm font-medium text-green-700">Tambah Stok (Masuk)</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="type" value="out" checked={form.type === 'out'} onChange={handleChange} />
              <span className="text-sm font-medium text-red-700">Kurangi Stok (Keluar)</span>
            </label>
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Jumlah *</label>
          <input
            type="number"
            name="quantity"
            value={form.quantity}
            onChange={handleChange}
            min="1"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <textarea
            name="notes"
            value={form.notes}
            onChange={handleChange}
            rows={2}
            placeholder="Alasan penyesuaian stok..."
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
          />
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={onClose}>Batal</Button>
          <Button type="submit" loading={saving}>Simpan</Button>
        </div>
      </form>
    </Modal>
  )
}

const DeleteConfirmModal = ({ isOpen, onClose, onConfirm, product, deleting }) => (
  <Modal isOpen={isOpen} onClose={onClose} title="Hapus Produk">
    <div className="space-y-4">
      <p className="text-sm text-gray-600">
        Yakin ingin menghapus produk{' '}
        <span className="font-semibold">{product?.name}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onClose}>Batal</Button>
        <Button variant="danger" onClick={onConfirm} loading={deleting}>Hapus</Button>
      </div>
    </div>
  </Modal>
)

const ProductsPage = () => {
  const navigate = useNavigate()
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [activeTab, setActiveTab] = useState('all')
  const [search, setSearch] = useState('')

  const [showProductModal, setShowProductModal] = useState(false)
  const [editProduct, setEditProduct] = useState(null)

  const [showStockModal, setShowStockModal] = useState(false)
  const [stockProduct, setStockProduct] = useState(null)

  const [showDeleteModal, setShowDeleteModal] = useState(false)
  const [deleteProduct, setDeleteProduct] = useState(null)
  const [deleting, setDeleting] = useState(false)

  const fetchProducts = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const url = activeTab === 'low_stock' ? 'products/low-stock' : 'products'
      const res = await api.get(url)
      setProducts(res.data.data ?? res.data ?? [])
    } catch {
      setError('Gagal memuat data produk.')
    } finally {
      setLoading(false)
    }
  }, [activeTab])

  useEffect(() => { fetchProducts() }, [fetchProducts])

  const allSkus = products.map((p) => p.sku)

  const handleDelete = async () => {
    setDeleting(true)
    try {
      await api.delete(`products/${deleteProduct.id}`)
      setShowDeleteModal(false)
      setDeleteProduct(null)
      fetchProducts()
    } catch (err) {
      setError(err.response?.data?.message ?? 'Gagal menghapus produk')
      setShowDeleteModal(false)
    } finally {
      setDeleting(false)
    }
  }

  const filtered = products.filter((p) => {
    if (!search.trim()) return true
    const q = search.toLowerCase()
    return (
      p.sku?.toLowerCase().includes(q) ||
      p.name?.toLowerCase().includes(q) ||
      p.category?.name?.toLowerCase().includes(q)
    )
  })

  const columns = [
    { key: 'sku', label: 'SKU' },
    { key: 'name', label: 'Nama' },
    { key: '_category', label: 'Kategori' },
    { key: '_stock', label: 'Stok' },
    { key: '_threshold', label: 'Threshold' },
    { key: '_buy_price', label: 'Harga Beli' },
    { key: '_sell_price', label: 'Harga Jual' },
    { key: '_status', label: 'Status' },
  ]

  const tableData = filtered.map((p) => ({
    ...p,
    _category: p.category?.name ?? '-',
    _stock: (
      <span className={p.stock <= p.low_stock_threshold ? 'font-semibold text-red-600' : 'text-gray-700'}>
        {p.stock} {p.unit}
      </span>
    ),
    _threshold: `${p.low_stock_threshold} ${p.unit}`,
    _buy_price: formatRupiah(p.buy_price),
    _sell_price: formatRupiah(p.sell_price),
    _status: (
      <Badge
        text={p.is_available ? 'Tersedia' : 'Tidak Tersedia'}
        variant={p.is_available ? 'success' : 'danger'}
      />
    ),
  }))

  return (
    <MainLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">Produk &amp; Inventaris</h1>
            <p className="mt-1 text-sm text-gray-500">Kelola stok bahan baku dan produk</p>
          </div>
          <Button onClick={() => { setEditProduct(null); setShowProductModal(true) }}>
            + Tambah Produk
          </Button>
        </div>

        {error && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="flex gap-1 border-b border-gray-200">
          {[
            { key: 'all', label: 'Semua Produk' },
            { key: 'low_stock', label: 'Stok Kritis' },
          ].map((tab) => (
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

        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="Cari SKU, nama, atau kategori..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full max-w-sm rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {activeTab === 'low_stock' && !loading && (
            <span className="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
              {filtered.length} item kritis
            </span>
          )}
        </div>

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
                    onClick={() => navigate(`/manager/products/${row.id}/recipes`)}
                  >
                    Kelola Resep
                  </Button>
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                      setStockProduct(products.find((p) => p.id === row.id) ?? row)
                      setShowStockModal(true)
                    }}
                  >
                    Stok
                  </Button>
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                      setEditProduct(products.find((p) => p.id === row.id) ?? row)
                      setShowProductModal(true)
                    }}
                  >
                    Edit
                  </Button>
                  <Button
                    size="sm"
                    variant="danger"
                    onClick={() => { setDeleteProduct(row); setShowDeleteModal(true) }}
                  >
                    Hapus
                  </Button>
                </div>
              )}
            />
          )}
        </div>
      </div>

      <ProductFormModal
        isOpen={showProductModal}
        onClose={() => setShowProductModal(false)}
        onSaved={() => { setShowProductModal(false); fetchProducts() }}
        editProduct={editProduct}
        allSkus={allSkus}
      />

      <StockAdjustModal
        isOpen={showStockModal}
        onClose={() => setShowStockModal(false)}
        onSaved={() => { setShowStockModal(false); fetchProducts() }}
        product={stockProduct}
      />

      <DeleteConfirmModal
        isOpen={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        onConfirm={handleDelete}
        product={deleteProduct}
        deleting={deleting}
      />
    </MainLayout>
  )
}

export default ProductsPage
