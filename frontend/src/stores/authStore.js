import { create } from 'zustand'

const getInitialState = () => {
  try {
    const token = localStorage.getItem('token')
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    return {
      token: token || null,
      user: user || null,
      role: user?.role || null,
    }
  } catch {
    return { token: null, user: null, role: null }
  }
}

const useAuthStore = create((set) => ({
  ...getInitialState(),

  login: (userData, token) => {
    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(userData))
    set({ token, user: userData, role: userData?.role || null })
  },

  logout: () => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    set({ token: null, user: null, role: null })
  },

  setUser: (userData) => {
    localStorage.setItem('user', JSON.stringify(userData))
    set({ user: userData, role: userData?.role || null })
  },
}))

export default useAuthStore
