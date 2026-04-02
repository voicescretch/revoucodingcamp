import useAuthStore from '../stores/authStore'

const useAuth = () => {
  const { user, token, role, login, logout, setUser } = useAuthStore()

  return {
    user,
    token,
    role,
    isAuthenticated: !!token,
    login,
    logout,
    setUser,
  }
}

export default useAuth
