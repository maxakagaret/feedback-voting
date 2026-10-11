import { defineStore } from 'pinia'
import { api, tokenStorage } from '../api/client'
import type { User } from '../api/types'

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null as User | null }),
  getters: {
    isAdmin: (s) => s.user?.role === 'admin',
  },
  actions: {
    async login(email: string, password: string) {
      const { token, user } = await api.login(email, password)
      tokenStorage.set(token)
      this.user = user
    },
    /** Restores the session from a stored token; false if there is none or it expired. */
    async restore(): Promise<boolean> {
      if (this.user) return true
      if (!tokenStorage.get()) return false
      try {
        this.user = await api.me()
        return true
      } catch {
        tokenStorage.clear()
        return false
      }
    },
    async logout() {
      try {
        await api.logout()
      } finally {
        tokenStorage.clear()
        this.user = null
      }
    },
  },
})
