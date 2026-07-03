<template>
  <div id="app" dir="rtl">
    <AppNavbar @toggle-notifications="notifOpen = !notifOpen" />

    <NotificationPanel
      :open="notifOpen"
      @close="notifOpen = false"
    />

    <main class="app-main">
      <RouterView v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="route.path" />
        </Transition>
      </RouterView>
    </main>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute }              from 'vue-router'
import AppNavbar                 from '@/components/shared/AppNavbar.vue'
import NotificationPanel         from '@/components/shared/NotificationPanel.vue'
import { useAuthStore }          from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'

const notifOpen  = ref(false)
const authStore  = useAuthStore()
const notifStore = useNotificationsStore()
const route      = useRoute()

// Close notification panel on route change
watch(() => route.path, () => { notifOpen.value = false })

// Boot: restore session + start notification polling
onMounted(async () => {
  if (authStore.isLoggedIn) {
    await authStore.fetchMe()
    notifStore.startPolling(30_000)
  }
})
</script>

<style>
.app-main { min-height: 100vh; }

/* Page transition */
.page-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.page-leave-active { transition: opacity 0.15s ease; }
.page-enter-from   { opacity: 0; transform: translateY(8px); }
.page-leave-to     { opacity: 0; }
</style>
