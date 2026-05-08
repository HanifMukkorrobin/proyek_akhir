import axios from 'axios'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()

  const api = axios.create({
    baseURL: config.public.apiBase,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json'
    }
  })

  api.interceptors.request.use(
    (requestConfig) => {
      if (import.meta.client) {
        const token = localStorage.getItem('auth_token')
        if (token && requestConfig.headers) {
          requestConfig.headers.Authorization = `Bearer ${token}`
        }
      }

      return requestConfig
    },
    (error) => Promise.reject(error)
  )

  return {
    provide: {
      api
    }
  }
})
