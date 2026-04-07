import { create } from 'zustand'
import axios from '@/lib/axios'

interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at: string
}

interface AuthState {
  token: string | null
  user: User | null
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string, password_confirmation: string) => Promise<void>
  logout: () => Promise<void>
  fetchUser: () => Promise<void>
  reset: () => void
}

export const useAuthStore = create<AuthState>((set) => ({
  token: localStorage.getItem('token'),
  user: null,
  isLoading: true,

  login: async (email, password) => {
    const { data } = await axios.post('/auth/login', { email, password })
    const token = data.token
    localStorage.setItem('token', token)
    set({ token, user: data.user, isLoading: false })
  },

  register: async (name, email, password, password_confirmation) => {
    const { data } = await axios.post('/auth/register', {
      name,
      email,
      password,
      password_confirmation,
    })
    const token = data.token
    localStorage.setItem('token', token)
    set({ token, user: data.user, isLoading: false })
  },

  logout: async () => {
    try {
      await axios.post('/auth/logout')
    } finally {
      localStorage.removeItem('token')
      set({ token: null, user: null })
    }
  },

  fetchUser: async () => {
    try {
      const token = localStorage.getItem('token')
      if (!token) {
        set({ isLoading: false })
        return
      }
      const { data } = await axios.get('/auth/me')
      set({ user: data, isLoading: false })
    } catch {
      localStorage.removeItem('token')
      set({ token: null, user: null, isLoading: false })
    }
  },

  reset: () => {
    localStorage.removeItem('token')
    set({ token: null, user: null, isLoading: false })
  },
}))
