import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/composables/useApi'

export const useOrdersStore = defineStore('orders', () => {
  const orders  = ref([])
  const current = ref(null)
  const loading = ref(false)
  const meta    = ref({ total: 0, current_page: 1, last_page: 1 })
  const error   = ref(null)

  async function fetchMyOrders(role = 'client', status = '', page = 1) {
    loading.value = true
    error.value   = null
    try {
      const endpoint = role === 'client' ? '/client/orders' : '/seller/orders'
      const params   = { page, ...(status ? { status } : {}) }
      const { data } = await api.get(endpoint, { params })
      orders.value = data.data ?? []
      meta.value   = data.meta ?? {}
    } catch (e) {
      error.value = e.response?.data?.message ?? 'تعذّر تحميل الطلبات'
    } finally {
      loading.value = false
    }
  }

  async function fetchOrder(id) {
    const { data } = await api.get(`/orders/${id}`)
    current.value = data.data
    return data.data
  }

  async function acceptOrder(id)   { return (await api.patch(`/seller/orders/${id}/accept`)).data }
  async function rejectOrder(id)   { return (await api.patch(`/seller/orders/${id}/reject`)).data }
  async function deliverOrder(id)  { return (await api.patch(`/seller/orders/${id}/deliver`)).data }
  async function completeOrder(id, payload) {
    return (await api.patch(`/client/orders/${id}/complete`, payload)).data
  }
  async function cancelOrder(id, reason) {
    return (await api.patch(`/client/orders/${id}/cancel`, { reason })).data
  }

  return {
    orders, current, loading, meta, error,
    fetchMyOrders, fetchOrder,
    acceptOrder, rejectOrder, deliverOrder, completeOrder, cancelOrder,
  }
})
