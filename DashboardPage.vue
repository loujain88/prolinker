<template>
  <div class="dashboard-layout" dir="rtl">

    <!-- ── Sidebar ──────────────────────────────────────────────── -->
    <aside class="sidebar" :class="{ 'sidebar--collapsed': sidebarCollapsed }">
      <!-- Logo -->
      <div class="sidebar__logo">
        <RouterLink to="/" class="logo-link">
          <span class="logo-hex">⬡</span>
          <Transition name="label-fade">
            <span v-if="!sidebarCollapsed" class="logo-text grad-text">ProLinker</span>
          </Transition>
        </RouterLink>
        <button class="collapse-btn" @click="sidebarCollapsed = !sidebarCollapsed"
          :aria-label="sidebarCollapsed ? 'توسيع القائمة' : 'طي القائمة'">
          <span class="collapse-icon" :class="{ flipped: sidebarCollapsed }">‹</span>
        </button>
      </div>

      <!-- Role toggle pill -->
      <div class="role-pill-wrap" v-if="auth.user?.role !== 'admin'">
        <Transition name="label-fade">
          <span v-if="!sidebarCollapsed" class="role-pill-label">الوضع الحالي</span>
        </Transition>
        <div class="role-pill" :class="{ 'role-pill--seller': activeRole === 'seller' }">
          <button
            class="role-pill__btn"
            :class="{ active: activeRole === 'client' }"
            @click="switchRole('client')"
            title="وضع العميل"
          >🛍️ <Transition name="label-fade"><span v-if="!sidebarCollapsed">عميل</span></Transition></button>
          <button
            class="role-pill__btn"
            :class="{ active: activeRole === 'seller' }"
            @click="switchRole('seller')"
            title="وضع المستقل"
          >💼 <Transition name="label-fade"><span v-if="!sidebarCollapsed">مستقل</span></Transition></button>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="sidebar__nav">
        <template v-for="item in navItems" :key="item.name">
          <RouterLink
            :to="item.to"
            class="nav-item"
            :class="{ 'nav-item--active': $route.name === item.routeName }"
            :title="item.label"
          >
            <span class="nav-icon">{{ item.icon }}</span>
            <Transition name="label-fade">
              <span v-if="!sidebarCollapsed" class="nav-label">{{ item.label }}</span>
            </Transition>
            <span v-if="!sidebarCollapsed && item.badge" class="nav-badge">{{ item.badge }}</span>
          </RouterLink>
        </template>
      </nav>

      <!-- Sidebar footer: user card -->
      <div class="sidebar__footer">
        <div class="user-card" :class="{ 'user-card--collapsed': sidebarCollapsed }">
          <div class="user-avatar">{{ userInitials }}</div>
          <Transition name="label-fade">
            <div v-if="!sidebarCollapsed" class="user-info">
              <span class="user-name">{{ auth.user?.name }}</span>
              <span class="user-role-tag">{{ roleLabel }}</span>
            </div>
          </Transition>
        </div>
        <button class="logout-btn" @click="handleLogout" :title="sidebarCollapsed ? 'تسجيل الخروج' : ''">
          <span>🚪</span>
          <Transition name="label-fade">
            <span v-if="!sidebarCollapsed">خروج</span>
          </Transition>
        </button>
      </div>
    </aside>

    <!-- ── Main content ──────────────────────────────────────────── -->
    <div class="dashboard-main">
      <!-- Top bar -->
      <header class="topbar">
        <!-- Mobile menu toggle -->
        <button class="mobile-menu-btn" @click="mobileDrawer = true" aria-label="القائمة">
          ☰
        </button>

        <div class="topbar__greeting">
          <h1 class="topbar__title">{{ pageTitle }}</h1>
          <span class="topbar__date">{{ todayAr }}</span>
        </div>

        <div class="topbar__actions">
          <!-- Notification bell -->
          <button class="icon-btn" @click="notifOpen = !notifOpen"
            :aria-label="`الإشعارات${notifStore.unreadCount ? ' (' + notifStore.unreadCount + ')' : ''}`">
            🔔
            <span v-if="notifStore.hasUnread" class="bell-badge">
              {{ notifStore.unreadCount > 9 ? '9+' : notifStore.unreadCount }}
            </span>
          </button>
          <!-- Avatar -->
          <div class="topbar__avatar">{{ userInitials }}</div>
        </div>
      </header>

      <!-- Role-switch animated banner -->
      <Transition name="role-banner">
        <div v-if="roleSwitchMsg" class="role-banner" :class="`role-banner--${activeRole}`">
          <span>{{ activeRole === 'client' ? '🛍️' : '💼' }}</span>
          {{ roleSwitchMsg }}
        </div>
      </Transition>

      <!-- Page content -->
      <div class="dashboard-content">
        <RouterView v-slot="{ Component, route }">
          <Transition name="dash-page" mode="out-in">
            <component :is="Component" :key="route.fullPath" :active-role="activeRole" />
          </Transition>
        </RouterView>
      </div>
    </div>

    <!-- Mobile drawer overlay -->
    <Transition name="overlay">
      <div v-if="mobileDrawer" class="mobile-overlay" @click="mobileDrawer = false"></div>
    </Transition>

    <!-- Notification panel -->
    <NotificationPanel :open="notifOpen" @close="notifOpen = false" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore }          from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import NotificationPanel         from '@/components/shared/NotificationPanel.vue'

const auth       = useAuthStore()
const notifStore = useNotificationsStore()
const router     = useRouter()
const route      = useRoute()

// ── Sidebar state ──────────────────────────────────────────────────────────
const sidebarCollapsed = ref(false)
const mobileDrawer     = ref(false)
const notifOpen        = ref(false)

// ── Role toggle ────────────────────────────────────────────────────────────
const activeRole   = ref(auth.user?.role === 'seller' ? 'seller' : 'client')
const roleSwitchMsg = ref('')
let   roleMsgTimer  = null

function switchRole(role) {
  if (activeRole.value === role) return
  activeRole.value = role
  clearTimeout(roleMsgTimer)
  roleSwitchMsg.value = role === 'client'
    ? 'تم التبديل إلى وضع العميل — تصفح الخدمات وأدر طلباتك'
    : 'تم التبديل إلى وضع المستقل — أدر خدماتك وطلباتك الواردة'
  roleMsgTimer = setTimeout(() => { roleSwitchMsg.value = '' }, 3500)
}

// ── Navigation items ───────────────────────────────────────────────────────
const navItems = computed(() => {
  const base = [
    { icon: '🏠', label: 'الرئيسية',  to: '/dashboard',          routeName: 'dashboard-home' },
    { icon: '💰', label: 'المحفظة',   to: '/dashboard/wallet',   routeName: 'dashboard-wallet' },
    { icon: '📦', label: 'الطلبات',   to: '/dashboard/orders',   routeName: 'dashboard-orders' },
  ]
  if (activeRole.value === 'seller') {
    base.push({ icon: '🛠️', label: 'خدماتي',  to: '/dashboard/services', routeName: 'dashboard-services' })
  }
  base.push({ icon: '👤', label: 'الملف الشخصي', to: '/dashboard/profile', routeName: 'dashboard-profile' })
  return base
})

// ── Page title per route ───────────────────────────────────────────────────
const pageTitle = computed(() => {
  const map = {
    'dashboard-home':     `مرحباً، ${auth.user?.name?.split(' ')[0] ?? ''} 👋`,
    'dashboard-wallet':   'المحفظة',
    'dashboard-orders':   'الطلبات',
    'dashboard-services': 'خدماتي',
    'dashboard-profile':  'الملف الشخصي',
  }
  return map[route.name] ?? 'لوحة التحكم'
})

// ── Helpers ────────────────────────────────────────────────────────────────
const roleLabel = computed(() =>
  auth.user?.role === 'admin' ? 'مدير' :
  activeRole.value === 'seller' ? 'مستقل' : 'عميل'
)

const userInitials = computed(() => {
  const name = auth.user?.name ?? ''
  return name.split(' ').map(w => w[0]).slice(0, 2).join('')
})

const todayAr = computed(() =>
  new Date().toLocaleDateString('ar-EG', { weekday:'long', year:'numeric', month:'long', day:'numeric' })
)

async function handleLogout() {
  await auth.logout()
  router.push('/')
}

onMounted(() => {
  notifStore.startPolling(30_000)
})
</script>

<style scoped>
/* ── Layout shell ──────────────────────────────────────────────────────── */
.dashboard-layout {
  display: flex;
  min-height: 100vh;
  background: var(--color-bg);
}

/* ═══════════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════════ */
.sidebar {
  width: 240px;
  flex-shrink: 0;
  background: var(--color-bg-2);
  border-left: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow: hidden;
  transition: width 0.3s cubic-bezier(0.4,0,0.2,1);
  z-index: 50;
}
.sidebar--collapsed { width: 68px; }

/* Logo row */
.sidebar__logo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 16px 16px;
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
  min-height: 64px;
}
.logo-link { display: flex; align-items: center; gap: 10px; text-decoration: none; overflow: hidden; }
.logo-hex {
  font-size: 22px; flex-shrink: 0;
  background: var(--grad-primary);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.logo-text { font-family: var(--font-display); font-size: 18px; font-weight: 800; white-space: nowrap; }
.collapse-btn {
  background: none; border: none; cursor: pointer;
  width: 28px; height: 28px; border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  color: var(--color-text-3); font-size: 18px;
  transition: background 0.15s, color 0.15s;
  flex-shrink: 0;
}
.collapse-btn:hover { background: rgba(99,102,241,0.1); color: var(--color-primary); }
.collapse-icon { display: inline-block; transition: transform 0.3s; }
.collapse-icon.flipped { transform: rotate(180deg); }

/* Role pill */
.role-pill-wrap { padding: 14px 12px 10px; }
.role-pill-label { display: block; font-size: 10px; color: var(--color-text-3); margin-bottom: 8px; letter-spacing: 0.5px; }
.role-pill {
  display: flex;
  background: var(--color-bg);
  border-radius: var(--radius-full);
  padding: 3px;
  border: 1px solid var(--color-border);
  position: relative;
}
.role-pill__btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
  padding: 7px 6px;
  border: none; background: none; cursor: pointer;
  font-family: var(--font-body); font-size: 12px; font-weight: 600;
  color: var(--color-text-3); border-radius: var(--radius-full);
  transition: color 0.25s, background 0.25s;
  white-space: nowrap; overflow: hidden;
}
.role-pill__btn.active {
  background: var(--grad-primary);
  color: white;
  box-shadow: 0 2px 12px rgba(99,102,241,0.4);
}

/* Nav */
.sidebar__nav { flex: 1; padding: 8px 10px; display: flex; flex-direction: column; gap: 2px; overflow-y: auto; }
.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  text-decoration: none;
  color: var(--color-text-3);
  font-size: 14px; font-weight: 500;
  transition: background 0.2s, color 0.2s;
  white-space: nowrap; overflow: hidden;
  position: relative;
}
.nav-item:hover { background: rgba(99,102,241,0.08); color: var(--color-text); }
.nav-item--active { background: rgba(99,102,241,0.12); color: var(--color-primary); font-weight: 600; }
.nav-item--active::before {
  content: ''; position: absolute; right: 0; top: 50%; transform: translateY(-50%);
  width: 3px; height: 60%; background: var(--grad-primary); border-radius: var(--radius-full);
}
.nav-icon { font-size: 18px; flex-shrink: 0; line-height: 1; }
.nav-label { flex: 1; }
.nav-badge {
  font-size: 10px; font-weight: 700;
  background: var(--color-primary); color: white;
  padding: 2px 7px; border-radius: var(--radius-full);
}

/* Sidebar footer */
.sidebar__footer { padding: 12px 10px; border-top: 1px solid var(--color-border); display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; }
.user-card { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: var(--radius-md); overflow: hidden; }
.user-card--collapsed { justify-content: center; }
.user-avatar {
  width: 34px; height: 34px; flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--grad-primary);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: white;
}
.user-info { display: flex; flex-direction: column; overflow: hidden; }
.user-name { font-size: 13px; font-weight: 600; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role-tag { font-size: 10px; color: var(--color-text-3); }
.logout-btn {
  display: flex; align-items: center; gap: 8px; justify-content: center;
  padding: 9px 12px; width: 100%;
  background: none; border: 1px solid transparent;
  border-radius: var(--radius-md); cursor: pointer;
  color: var(--color-text-3); font-family: var(--font-body); font-size: 13px;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.logout-btn:hover { background: rgba(239,68,68,0.08); color: var(--color-error); border-color: rgba(239,68,68,0.2); }

/* ═══════════════════════════════════════════════
   MAIN AREA
═══════════════════════════════════════════════ */
.dashboard-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

/* Topbar */
.topbar {
  height: 64px; flex-shrink: 0;
  display: flex; align-items: center; gap: 16px;
  padding: 0 28px;
  background: var(--color-bg-2);
  border-bottom: 1px solid var(--color-border);
  position: sticky; top: 0; z-index: 40;
}
.mobile-menu-btn { display: none; background: none; border: none; font-size: 20px; cursor: pointer; color: var(--color-text-2); }
.topbar__greeting { flex: 1; }
.topbar__title { font-family: var(--font-display); font-size: 18px; font-weight: 700; line-height: 1.2; }
.topbar__date   { font-size: 11px; color: var(--color-text-3); }
.topbar__actions { display: flex; align-items: center; gap: 10px; }
.icon-btn { background: none; border: none; font-size: 20px; cursor: pointer; position: relative; padding: 6px; border-radius: var(--radius-md); transition: background 0.15s; }
.icon-btn:hover { background: rgba(99,102,241,0.1); }
.bell-badge {
  position: absolute; top: 2px; left: 2px;
  background: var(--color-primary); color: white;
  font-size: 9px; font-weight: 700;
  min-width: 16px; height: 16px; padding: 0 3px;
  border-radius: var(--radius-full);
  display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--color-bg-2);
}
.topbar__avatar {
  width: 36px; height: 36px; border-radius: var(--radius-full);
  background: var(--grad-primary);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: white; flex-shrink: 0;
}

/* Role switch banner */
.role-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 28px;
  font-size: 13px; font-weight: 500;
}
.role-banner--client { background: rgba(99,102,241,0.1); color: var(--color-primary); border-bottom: 1px solid rgba(99,102,241,0.2); }
.role-banner--seller { background: rgba(245,158,11,0.08); color: var(--color-gold); border-bottom: 1px solid rgba(245,158,11,0.2); }
.role-banner-enter-active { transition: opacity 0.3s, transform 0.3s var(--ease-spring), max-height 0.3s; max-height: 60px; }
.role-banner-leave-active  { transition: opacity 0.2s, max-height 0.25s; overflow: hidden; max-height: 60px; }
.role-banner-enter-from { opacity: 0; transform: translateY(-8px); max-height: 0; }
.role-banner-leave-to   { opacity: 0; max-height: 0; }

/* Content area */
.dashboard-content { flex: 1; overflow-y: auto; padding: 28px; }

/* Page transitions */
.dash-page-enter-active { transition: opacity 0.2s ease, transform 0.25s var(--ease-smooth); }
.dash-page-leave-active { transition: opacity 0.15s ease; }
.dash-page-enter-from   { opacity: 0; transform: translateY(10px); }
.dash-page-leave-to     { opacity: 0; }

/* Label fade (sidebar text) */
.label-fade-enter-active,.label-fade-leave-active { transition: opacity 0.2s; }
.label-fade-enter-from,.label-fade-leave-to { opacity:0; }

/* Mobile overlay */
.mobile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 49; }
.overlay-enter-active,.overlay-leave-active { transition: opacity 0.25s; }
.overlay-enter-from,.overlay-leave-to { opacity: 0; }

@media (max-width: 768px) {
  .sidebar { position: fixed; right: -240px; transition: right 0.3s, width 0.3s; }
  .sidebar.mobile-open { right: 0; }
  .sidebar--collapsed { width: 240px; right: -240px; }
  .mobile-menu-btn { display: flex; }
  .dashboard-content { padding: 16px; }
  .topbar { padding: 0 16px; }
}
</style>
