import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useWalletStore = defineStore('wallet', () => {
  const balance     = ref(0)
  const role        = ref(null)       // 'client' | 'seller'
  const deposits    = ref([])
  const withdrawals = ref([])
  const loading     = ref(false)
  const error       = ref(null)

  const formattedBalance = computed(() =>
    Number(balance.value).toLocaleString('ar-EG-u-nu-latn', { minimumFractionDigits: 2 })
  )

  async function fetchSummary() {
    loading.value = true
    error.value   = null
    try {
      const { data } = await api.get('/wallet/summary')
      balance.value      = data.wallet_balance ?? 0
      role.value         = data.role
      deposits.value     = data.recent_deposits    ?? []
      withdrawals.value  = data.recent_withdrawals ?? []
    } catch (e) {
      error.value = e.response?.data?.message ?? 'تعذّر تحميل بيانات المحفظة'
    } finally {
      loading.value = false
    }
  }

  async function submitDeposit(formData) {
    const { data } = await api.post('/client/wallet/deposit', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await fetchSummary()
    return data
  }

  async function submitWithdrawal(payload) {
    const isFormData = payload instanceof FormData
    const { data } = await api.post('/seller/wallet/withdraw', payload, isFormData ? { headers: { 'Content-Type': undefined } } : {})
    await fetchSummary()
    return data
  }

  async function fetchReceiptUrl(type, id) {
    const path = type === 'deposit'
      ? `/client/wallet/deposits/${id}/receipt`
      : `/seller/wallet/withdrawals/${id}/receipt`
    const { data } = await api.get(path)
    return data.url
  }

  return {
    balance, role, deposits, withdrawals, loading, error,
    formattedBalance,
    fetchSummary, submitDeposit, submitWithdrawal, fetchReceiptUrl,
  }
})
