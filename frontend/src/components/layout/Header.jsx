import { useNavigate } from 'react-router-dom'
import useAuthStore from '../../stores/authStore'
import api from '../../services/api'

const ROLE_LABELS = {
  head_manager: 'Head Manager',
  kasir: 'Kasir',
  finance: 'Finance',
  pelanggan: 'Pelanggan',
}

const Header = () => {
  const { user, role, logout } = useAuthStore()
  const navigate = useNavigate()

  const handleLogout = async () => {
    try {
      await api.post('auth/logout')
    } catch {
      // ignore — still clear local state
    } finally {
      logout()
      navigate('/login', { replace: true })
    }
  }

  return (
    <header className="flex h-16 items-center justify-between border-b bg-white px-6 shadow-sm">
      <div />

      <div className="flex items-center gap-4">
        <div className="text-right">
          <p className="text-sm font-medium text-gray-800">{user?.name ?? '—'}</p>
          <p className="text-xs text-gray-500">{ROLE_LABELS[role] ?? role}</p>
        </div>

        <button
          onClick={handleLogout}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          Keluar
        </button>
      </div>
    </header>
  )
}

export default Header
