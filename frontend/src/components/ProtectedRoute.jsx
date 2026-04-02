import { Navigate } from 'react-router-dom'
import useAuth from '../hooks/useAuth'

/**
 * ProtectedRoute — guards routes by authentication and optional role check.
 *
 * Props:
 *   children   — the component to render if access is granted
 *   roles      — array of allowed roles (e.g. ['head_manager', 'finance'])
 *                if omitted, any authenticated user is allowed
 */
const ProtectedRoute = ({ children, roles }) => {
  const { isAuthenticated, role } = useAuth()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (roles && roles.length > 0 && !roles.includes(role)) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-red-600">403</h1>
          <p className="mt-2 text-gray-600">
            Anda tidak memiliki akses ke halaman ini.
          </p>
        </div>
      </div>
    )
  }

  return children
}

export default ProtectedRoute
