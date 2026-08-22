import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const user    = ref(JSON.parse(localStorage.getItem('pl_user') || 'null'))
  const token   = ref(localStorage.getItem('pl_token') || null)
  const profile = ref(null)

  const activeRole = ref(localStorage.getItem('pl_active_role') || 'client')

  const isLoggedIn = computed(() => !!token.value)
  const isClient   = computed(() => user.value?.role === 'client')
  const isSeller   = computed(() => user.value?.role === 'seller')
  const isAdmin    = computed(() => user.value?.role === 'admin')

  // 🔄 دالة للتبديل بين وضع العميل والمستقل وتثبيته في المتصفح
  function toggleRole() {
    activeRole.value = activeRole.value === 'client' ? 'seller' : 'client'
    localStorage.setItem('pl_active_role', activeRole.value)
  }

  async function login(email, password, targetRole = null) {
    const { data } = await api.post('/auth/login', { email, password })
    token.value = data.token
    user.value  = data.user
    localStorage.setItem('pl_token', data.token)
    localStorage.setItem('pl_user',  JSON.stringify(data.user))
    
    // يسمح بالانتقال للوضع المطلوب أو الوضع المخزن
    const finalRole = targetRole || data.user?.role || 'client'

    localStorage.setItem('pl_active_role', finalRole) 
    activeRole.value = finalRole
    
    return data
  }

  async function register(payload) {
    const isFormData = payload instanceof FormData
    const { data } = await api.post('/auth/register', payload, isFormData ? { headers: { 'Content-Type': undefined } } : {})
    // No token/auto-login anymore — the account needs admin approval first.
    return data
  }

  async function logout() {
    try { await api.post('/auth/logout') } catch (_) {}
    token.value   = null
    user.value    = null
    profile.value = null
    localStorage.removeItem('pl_token')
    localStorage.removeItem('pl_user')
    localStorage.removeItem('pl_active_role')
    localStorage.removeItem('pl_saved_accounts')
  }

  async function fetchMe() {
    if (!token.value) return
    const { data } = await api.get('/auth/me')
    user.value    = data.user
    profile.value = data.profile
  }

  return { 
    user, token, profile, activeRole, isLoggedIn, isClient, isSeller, isAdmin,
    login, register, logout, fetchMe, toggleRole
  }
})