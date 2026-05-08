import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    isAuthenticated: false,
    isLoading: false,
    error: ''
  }),

  actions: {
    initAuth() {
      if (import.meta.client) {
        const token = localStorage.getItem('auth_token')
        if (token) {
          this.token = token
          this.isAuthenticated = true
          return
        }
      }

      this.token = null
      this.isAuthenticated = false
    },

    async login(email, password) {
      const nuxtApp = useNuxtApp()
      const $api = nuxtApp.$api
      this.isLoading = true
      this.error = ''

      try {
        const response = await $api.post('/auth/login', { email, password })
        const token = response.data.token || response.data.access_token

        if (token) {
          this.token = token
          this.isAuthenticated = true
          
          if (import.meta.client) {
            localStorage.setItem('auth_token', token)
          }

          return response.data
        } else {
          throw new Error('Token tidak ditemukan dari server.')
        }
      } catch (error) {
        let errorMessage = 'Terjadi kesalahan tidak terduga.'
        if (error.response && error.response.status === 401) {
          errorMessage = 'Email atau kata sandi tidak sesuai.'
        } else if (error.response?.data?.message) {
          errorMessage = error.response.data.message
        } else if (error.message) {
          errorMessage = error.message
        }

        this.error = errorMessage
        throw new Error(errorMessage)
      } finally {
        this.isLoading = false
      }
    },

    logout() {
      this.user = null
      this.token = null
      this.isAuthenticated = false
      this.error = ''

      if (import.meta.client) {
        localStorage.removeItem('auth_token')
      }
    }
  }
})
