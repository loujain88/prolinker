import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const user    = ref(JSON.parse(localStorage.getItem('pl_user') || 'null'))
  const token   = ref(localStorage.getItem('pl_token') || null)
  const profile = ref(null)

  const isLoggedIn = computed(() => !!token.value)
  const isClient   = computed(() => user.value?.role === 'client')
  const isSeller   = computed(() => user.value?.role === 'seller')
  const isAdmin    = computed(() => user.value?.role === 'admin')

  async function login(email, password) {
    const { data } = await api.post('/auth/login', { email, password })
    token.value = data.token
    user.value  = data.user
    localStorage.setItem('pl_token', data.token)
    localStorage.setItem('pl_user',  JSON.stringify(data.user))
    return data
  }

  async function register(payload) {
    const { data } = await api.post('/auth/register', payload)
    token.value = data.token
    user.value  = data.user
    localStorage.setItem('pl_token', data.token)
    localStorage.setItem('pl_user',  JSON.stringify(data.user))
    return data
  }

  async function logout() {
    try { await api.post('/auth/logout') } catch (_) {}
    token.value   = null
    user.value    = null
    profile.value = null
    localStorage.removeItem('pl_token')
    localStorage.removeItem('pl_user')
  }

  async function fetchMe() {
    if (!token.value) return
    const { data } = await api.get('/auth/me')
    user.value    = data.user
    profile.value = data.profile
  }

  return { user, token, profile, isLoggedIn, isClient, isSeller, isAdmin,
           login, register, logout, fetchMe }
})
