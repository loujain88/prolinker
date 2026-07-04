import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useNotificationsStore = defineStore('notifications', () => {
  const items        = ref([])
  const unreadCount  = ref(0)
  const loading      = ref(false)
  let   pollTimer    = null

  const hasUnread = computed(() => unreadCount.value > 0)

  async function fetchUnreadCount() {
    try {
      const { data } = await api.get('/notifications/unread-count')
      unreadCount.value = data.unread_count
    } catch (_) {}
  }

  async function fetchAll() {
    loading.value = true
    try {
      const { data } = await api.get('/notifications?per_page=10')
      items.value       = data.data
      unreadCount.value = data.meta?.unread_count ?? unreadCount.value
    } finally {
      loading.value = false
    }
  }

  async function markAsRead(id) {
    await api.patch(`/notifications/${id}/read`)
    const n = items.value.find(n => n.id === id)
    if (n) { n.is_read = true; n.read_at = new Date().toISOString() }
    if (unreadCount.value > 0) unreadCount.value--
  }

  async function markAllAsRead() {
    await api.patch('/notifications/read-all')
    items.value.forEach(n => { n.is_read = true })
    unreadCount.value = 0
  }

  function startPolling(intervalMs = 30000) {
    fetchUnreadCount()
    pollTimer = setInterval(fetchUnreadCount, intervalMs)
  }

  function stopPolling() {
    if (pollTimer) clearInterval(pollTimer)
  }

  return { items, unreadCount, loading, hasUnread,
           fetchUnreadCount, fetchAll, markAsRead, markAllAsRead,
           startPolling, stopPolling }
})
