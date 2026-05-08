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
          const savedUser = localStorage.getItem('auth_user')

          this.token = token
          this.isAuthenticated = true

          if (savedUser) {
            try {
              this.user = JSON.parse(savedUser)
            } catch {
              localStorage.removeItem('auth_user')
              this.user = null
            }
          }

          return
        }
      }

      this.user = null
      this.token = null
      this.isAuthenticated = false
    },

    async login(identifier: string, password: string, rememberMe = false) {
      const nuxtApp = useNuxtApp()
      const $api = nuxtApp.$api
      this.isLoading = true
      this.error = ''

      try {
        const response = await $api.post('/auth/login', {
          identifier,
          password,
          remember_me: rememberMe
        })

        const payload = response.data?.data ?? {}
        const token = payload.access_token || payload.token

        if (token) {
          this.token = token
          this.isAuthenticated = true
          this.user = payload.user || null
          
          if (import.meta.client) {
            localStorage.setItem('auth_token', token)
            localStorage.setItem('auth_user', JSON.stringify(this.user))
          }

          return payload
        } else {
          throw new Error('Token tidak ditemukan dari server.')
        }
      } catch (error: unknown) {
        const err = error as {
          response?: {
            status?: number
            data?: {
              message?: string
            }
          }
          message?: string
        }

        let errorMessage = 'Terjadi kesalahan tidak terduga.'
        if (err.response?.status === 401) {
          errorMessage = 'Kredensial login tidak valid.'
        } else if (err.response?.status === 422) {
          errorMessage = 'Identifier dan kata sandi wajib diisi.'
        } else if (err.response?.data?.message) {
          errorMessage = err.response.data.message
        } else if (err.message) {
          errorMessage = err.message
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
        localStorage.removeItem('auth_user')
      }
    }
  }
})
