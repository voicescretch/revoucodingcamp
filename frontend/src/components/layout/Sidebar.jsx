import { NavLink } from 'react-router-dom'
import useAuthStore from '../../stores/authStore'

const NAV_ITEMS = {
  head_manager: [
    { label: 'Dashboard', to: '/dashboard', icon: '🏠' },
    { label: 'Produk', to: '/manager/products', icon: '📦' },
    { label: 'Meja', to: '/manager/tables', icon: '🪑' },
    { label: 'Laporan', to: '/manager/reports', icon: '📊' },
    { label: 'Ringkasan Keuangan', to: '/finance/summary', icon: '💰' },
  ],
  kasir: [
    { label: 'Pesanan', to: '/cashier/orders', icon: '🧾' },
    { label: 'Checkout', to: '/cashier/checkout', icon: '💳' },
  ],
  finance: [
    { label: 'Pengeluaran', to: '/finance/expenses', icon: '📋' },
    { label: 'Ringkasan Keuangan', to: '/finance/summary', icon: '💰' },
    { label: 'Laporan', to: '/manager/reports', icon: '📊' },
  ],
}

const Sidebar = () => {
  const role = useAuthStore((s) => s.role)
  const items = NAV_ITEMS[role] ?? []

  return (
    <aside className="flex h-full w-60 flex-col bg-gray-900 text-gray-100">
      {/* Brand */}
      <div className="flex h-16 items-center px-6 border-b border-gray-700">
        <span className="text-lg font-bold tracking-tight">Bar/Resto POS</span>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        {items.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) =>
              [
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-blue-600 text-white'
                  : 'text-gray-300 hover:bg-gray-700 hover:text-white',
              ].join(' ')
            }
          >
            <span aria-hidden="true">{item.icon}</span>
            {item.label}
          </NavLink>
        ))}
      </nav>
    </aside>
  )
}

export default Sidebar
