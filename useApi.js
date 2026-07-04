import axios from 'axios'

/**
 * Axios instance pre-configured for ProLinker API.
 * Token is injected from localStorage on every request via interceptor.
 * Import this directly: import api from '@/composables/useApi'
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  withCredentials: false,
})

/* Inject Sanctum token before every request */
api.interceptors.request.use(config => {
  const token = localStorage.getItem('pl_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/* Global error handler — redirect to login on 401 */
api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      localStorage.removeItem('pl_token')
      localStorage.removeItem('pl_user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api
