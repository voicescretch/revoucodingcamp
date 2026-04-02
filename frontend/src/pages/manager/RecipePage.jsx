import { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import MainLayout from '../../components/layout/MainLayout'
import Button from '../../components/ui/Button'
import Modal from '../../components/ui/Modal'
import Table from '../../components/ui/Table'
import api from '../../services/api'

// ── Recipe Form Modal ──────────────────────────────────────────────────────────
const RecipeFormModal = ({ isOpen, onClose, onSaved, editItem, productId, allProducts }) => {
  const EMPTY = { raw_material_id: '', quantity_required: '', unit: '' }
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!isOpen) return
    setForm(
      editItem
        ? {
            raw_material_id: editItem.raw_material?.id ?? '',
            quantity_required: editItem.quantity_required ?? '',
            unit: editItem.unit ?? '',
          }
        : EMPTY
    )
    setErrors({})
  }, [isOpen, editItem])

  const validate = () => {
    const e = {}
    if (!form.raw_material_id) e.raw_material_id = 'Bahan baku wajib dipilih'
    if (form.quantity_required === '' || Number(form.quantity_required) <= 0)
      e.quantity_required = 'Jumlah harus lebih dari 0'
    if (!form.unit.trim()) e.unit = 'Satuan wajib diisi'
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
      const payload = {
        raw_material_id: Number(form.raw_material_id),
        quantity_required: Number(form.quantity_required),
        unit: form.unit,
      }
      if (editItem) {
        await api.put(`recipes/${editItem.id}`, payload)
      } else {
        await api.post(`products/${productId}/recipes`, payload)
      }
      onSaved()
    } catch (err) {
      const serverErrors = err.response?.data?.errors ?? {}
      const mapped = {}
      Object.entries(serverErrors).forEach(([k, v]) => {
        mapped[k] = Array.isArray(v) ? v[0] : v
      })
      if (Object.keys(mapped).length) setErrors(mapped)
      else setErrors({ _general: err.response?.data?.message ?? 'Gagal menyimpan resep' })
    } finally {
      setSaving(false)
    }
  }

  const inputClass = (name) =>
    `w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
      errors[name] ? 'border-red-400' : 'border-gray-300'
    }`

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={editItem ? 'Edit Bahan Baku' : 'Tambah Bahan Baku'}
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {errors._general && (
          <div className="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
            {errors._general}
          </div>
        )}

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Bahan Baku *</label>
          <select
            name="raw_material_id"
            value={form.raw_material_id}
            onChange={handleChange}
            className={inputClass('raw_material_id')}
          >
            <option value="">Pilih bahan baku...</option>
            {allProducts.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name} ({p.sku})
              </option>
            ))}
          </select>
          {errors.raw_material_id && (
            <p className="mt-1 text-xs text-red-600">{errors.raw_material_id}</p>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Jumlah *</label>
            <input
              type="number"
              name="quantity_required"
              value={form.quantity_required}
              onChange={handleChange}
              min="0"
              step="any"
              className={inputClass('quantity_required')}
            />
            {errors.quantity_required && (
              <p className="mt-1 text-xs text-red-600">{errors.quantity_required}</p>
            )}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Satuan *</label>
            <input
              type="text"
              name="unit"
              value={form.unit}
              onChange={handleChange}
              placeholder="cth: gram, ml, pcs"
              className={inputClass('unit')}
            />
            {errors.unit && <p className="mt-1 text-xs text-red-600">{errors.unit}</p>}
          </div>
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={onClose}>
            Batal
          </Button>
          <Button type="submit" loading={saving}>
            {editItem ? 'Simpan Perubahan' : 'Tambah'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

// ── Delete Confirm Modal ───────────────────────────────────────────────────────
const DeleteConfirmModal = ({ isOpen, onClose, onConfirm, item, deleting }) => (
  <Modal isOpen={isOpen} onClose={onClose} title="Hapus Bahan Baku">
    <div className="space-y-4">
      <p className="text-sm text-gray-600">
        Yakin ingin menghapus{' '}
        <span className="font-semibold">{item?.raw_material?.name}</span> dari resep ini?
      </p>
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onClose}>
          Batal
        </Button>
        <Button variant="danger" onClick={onConfirm} loading={deleting}>
          Hapus
        </Button>
      </div>
    </div>
  </Modal>
)

// ── Main Page ──────────────────────────────────────────────────────────────────
const RecipePage = () => {
  const { id: productId } = useParams()
  const navigate = useNavigate()

  const [product, setProduct] = useState(null)
  const [recipes, setRecipes] = useState([])
  const [allProducts, setAllProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [showFormModal, setShowFormModal] = useState(false)
  const [editItem, setEditItem] = useState(null)

  const [showDeleteModal, setShowDeleteModal] = useState(false)
  const [deleteItem, setDeleteItem] = useState(null)
  const [deleting, setDeleting] = useState(false)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [productRes, recipesRes, productsRes] = await Promise.all([
        api.get(`products/${productId}`),
        api.get(`products/${productId}/recipes`),
        api.get('products'),
      ])
      setProduct(productRes.data.data ?? productRes.data)
      setRecipes(recipesRes.data.data ?? recipesRes.data ?? [])
      setAllProducts(productsRes.data.data ?? productsRes.data ?? [])
    } catch {
      setError('Gagal memuat data resep.')
    } finally {
      setLoading(false)
    }
  }, [productId])

  useEffect(() => { fetchData() }, [fetchData])

  const handleDelete = async () => {
    setDeleting(true)
    try {
      await api.delete(`recipes/${deleteItem.id}`)
      setShowDeleteModal(false)
      setDeleteItem(null)
      fetchData()
    } catch (err) {
      setError(err.response?.data?.message ?? 'Gagal menghapus bahan baku')
      setShowDeleteModal(false)
    } finally {
      setDeleting(false)
    }
  }

  const columns = [
    { key: '_name', label: 'Bahan Baku' },
    { key: '_sku', label: 'SKU' },
    { key: 'quantity_required', label: 'Qty Dibutuhkan' },
    { key: 'unit', label: 'Satuan' },
  ]

  const tableData = recipes.map((r) => ({
    ...r,
    _name: r.raw_material?.name ?? '—',
    _sku: r.raw_material?.sku ?? '—',
  }))

  return (
    <MainLayout>
      <div className="space-y-6">
        {/* Back + Header */}
        <div className="flex items-start justify-between">
          <div>
            <button
              onClick={() => navigate('/manager/products')}
              className="mb-2 flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
            >
              ← Kembali ke Produk
            </button>
            {product ? (
              <>
                <h1 className="text-2xl font-bold text-gray-800">{product.name}</h1>
                <p className="mt-0.5 text-sm text-gray-500">SKU: {product.sku}</p>
              </>
            ) : (
              <h1 className="text-2xl font-bold text-gray-800">Resep Produk</h1>
            )}
            <p className="mt-1 text-sm text-gray-500">
              Kelola daftar bahan baku (Bill of Materials) untuk produk ini
            </p>
          </div>
          <Button
            onClick={() => { setEditItem(null); setShowFormModal(true) }}
            disabled={loading}
          >
            + Tambah Bahan Baku
          </Button>
        </div>

        {error && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

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
                    onClick={() => {
                      setEditItem(recipes.find((r) => r.id === row.id) ?? row)
                      setShowFormModal(true)
                    }}
                  >
                    Edit
                  </Button>
                  <Button
                    size="sm"
                    variant="danger"
                    onClick={() => {
                      setDeleteItem(recipes.find((r) => r.id === row.id) ?? row)
                      setShowDeleteModal(true)
                    }}
                  >
                    Hapus
                  </Button>
                </div>
              )}
            />
          )}
        </div>
      </div>

      <RecipeFormModal
        isOpen={showFormModal}
        onClose={() => setShowFormModal(false)}
        onSaved={() => { setShowFormModal(false); fetchData() }}
        editItem={editItem}
        productId={productId}
        allProducts={allProducts}
      />

      <DeleteConfirmModal
        isOpen={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        onConfirm={handleDelete}
        item={deleteItem}
        deleting={deleting}
      />
    </MainLayout>
  )
}

export default RecipePage
