<template>
  <div class="admin-layout" dir="rtl">

    <aside class="admin-sidebar" :class="{ collapsed: sidebarCollapsed }">

      <div class="sidebar-brand">
        <div class="brand-inner">
          <span class="brand-hex"></span>
          <Transition name="lf">
            <span style="background: linear-gradient(120deg, #ffffff 30%, #38bdf8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; display: inline-block; font-weight: 800; font-size: 22px;">ProLinker</span>
          </Transition>
        </div>
        <Transition name="lf">
          <span v-if="!sidebarCollapsed" class="admin-chip">أدمن</span>
        </Transition>
        <button class="collapse-btn" @click="sidebarCollapsed = !sidebarCollapsed"
          :aria-label="sidebarCollapsed ? 'توسيع' : 'طي'">
          <span :style="{ display:'inline-block', transition:'transform .3s', transform: sidebarCollapsed ? 'rotate(180deg)' : 'none' }">‹</span>
        </button>
      </div>

      <nav class="admin-nav">
        <RouterLink v-for="item in navItems" :key="item.name"
          :to="item.to" class="nav-item"
          :class="{ active: $route.path === item.to || $route.name === item.routeName }"
          :title="sidebarCollapsed ? item.label : ''">
          <span class="nav-icon">{{ item.icon }}</span>
          <Transition name="lf">
            <span v-if="!sidebarCollapsed" class="nav-label">{{ item.label }}</span>
          </Transition>
        </RouterLink>
      </nav>

      <div class="sidebar-footer">
        <div class="s-user" :class="{ 'centered': sidebarCollapsed }">
          <Transition name="lf">
            <div v-if="!sidebarCollapsed" class="s-info">
              <span class="s-name">{{ auth.user?.name }}</span>
              <span class="s-role">مسؤول المنصة</span>
            </div>
          </Transition>
        </div>
        <button class="logout-btn" @click="handleLogout">
          <span></span>
          <Transition name="lf"><span v-if="!sidebarCollapsed">خروج</span></Transition>
        </button>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <button class="mob-btn" @click="mobOpen = true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
        </button>
        <div class="topbar-left">
          <h1 class="topbar-heading">{{ pageTitle }}</h1>
          <span class="topbar-sub">لوحة الإدارة / {{ pageTitle }}</span>
        </div>
        <div class="topbar-right">
          <span class="topbar-date">{{ todayAr }}</span>

          <!-- 🔔 زر الجرس والمرتبط بـ NotificationStore -->
          <div class="notif-wrapper">
            <button class="notif-btn" @click.stop="showNotifications = !showNotifications" title="الإشعارات">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              <!-- عدّاد الإشعارات الأحمر بناءً على الـ Store -->
              <span v-if="notifStore.unreadCount > 0" class="notif-badge">
                {{ notifStore.unreadCount > 99 ? '99+' : notifStore.unreadCount }}
              </span>
            </button>

            <!-- 📋 استدعاء المكون -->
            <NotificationPanel 
              :open="showNotifications" 
              @close="showNotifications = false" 
            />
          </div>

          <a href="/" target="_blank" class="site-link">الموقع</a>
        </div>
      </header>

      <div class="admin-content" @click="showNotifications = false">
        <RouterView v-slot="{ Component }">
          <component :is="Component" :key="$route.path" />
        </RouterView>
      </div>
    </div>

    <Transition name="ov">
      <div v-if="mobOpen" class="mob-overlay" @click="mobOpen = false">
        <div class="mob-drawer" @click.stop>
          <div class="mob-brand">
            <span></span><span>ProLinker Admin</span>
            <button @click="mobOpen = false" class="mob-close">✕</button>
          </div>
          <RouterLink v-for="item in navItems" :key="item.name"
            :to="item.to" class="mob-link" @click="mobOpen = false"> {{ item.icon }} {{ item.label }}
          </RouterLink>
          <button class="mob-link mob-link--danger" @click="handleLogout"> تسجيل الخروج</button>
        </div>
      </div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'

import NotificationPanel from '../../components/shared/NotificationPanel.vue'

const auth = useAuthStore()
const notifStore = useNotificationsStore()
const router = useRouter()
const route = useRoute()

const sidebarCollapsed = ref(false)
const mobOpen = ref(false)
const showNotifications = ref(false)

// متغير لحفظ المؤقت الزمني
let notifTimer = null

onMounted(() => {
  // 1. جلب الإشعارات المباشرة أول فتح الواجهة
  notifStore.fetchAll()

  // 2. فحص وجلب الإشعارات التلقائي كل 8 ثوانٍ لضمان ظهور أي إشعار جديد
  notifTimer = setInterval(() => {
    notifStore.fetchAll()
  }, 8000)
})

// إيقاف المؤقت فور إغلاق الصفحة أو تبديلها لحفظ أداء المتصفح
onUnmounted(() => {
  if (notifTimer) {
    clearInterval(notifTimer)
  }
})

const navItems = computed(() => {
  const items = [
    { icon: '', label: 'الإحصائيات', name: 'dashboard', routeName: 'admin-home', to: '/admin/dashboard' },
    { icon: '', label: 'العمليات المالية', name: 'financials', routeName: 'admin-financials', to: '/admin/financials' },
    { icon: '', label: 'مراجعة الخدمات', name: 'services', routeName: 'admin-services', to: '/admin/services' },
    { icon: '', label: 'التصنيفات', name: 'categories', routeName: 'admin-categories', to: '/admin/categories' },
    { icon: '', label: 'المستخدمون', name: 'users', routeName: 'admin-users', to: '/admin/users' },
    { icon: '', label: 'النزاعات', name: 'disputes', routeName: 'admin-disputes', to: '/admin/disputes' },
    { icon: '', label: 'الرسائل', name: 'conversations', routeName: 'AdminConversations', to: '/admin/conversations' },
    { icon: '🆘', label: 'محادثات الدعم', name: 'support', routeName: 'AdminSupport', to: '/admin/support' },
    { icon: '', label: 'طلبات الحسابات', name: 'account-requests', routeName: 'AdminAccountRequests', to: '/admin/account-requests' },
  ]
  if (auth.user?.is_super_admin) {
    items.push({ icon: '', label: 'إدارة الموظفين', name: 'staff', routeName: 'AdminStaff', to: '/admin/staff' })
  }
  return items
})

const titleMap = {
  'admin-home': 'الإحصائيات',
  'admin-financials': 'العمليات المالية',
  'admin-services': 'مراجعة الخدمات',
  'admin-categories': 'التصنيفات',
  'admin-users': 'المستخدمون',
  'admin-disputes': 'النزاعات',
}

const pageTitle = computed(() => titleMap[route.name] ?? 'الإدارة')

const todayAr = computed(() => new Date().toLocaleDateString('ar-EG-u-nu-latn', { weekday:'long', year:'numeric', month:'long', day:'numeric' }))

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.admin-layout { display:flex; min-height:100vh; background:linear-gradient(145deg, #7394e7 2%, #9babd2 50%, #3b4a62 100%); font-family:var(--font-body); }

/* Sidebar */
.admin-sidebar {
  width:240px; flex-shrink:0;
  background: linear-gradient(145deg, #2c3b5f 2%, #9babd2 50%, #506d9c 100%); border-left:1px solid var(--color-border);
  display:flex; flex-direction:column;
  position:sticky; top:0; height:100vh; overflow:hidden;
  transition:width .3s cubic-bezier(.4,0,.2,1); z-index:50;
}
.admin-sidebar.collapsed { width:68px; }

.sidebar-brand {
  display:flex; align-items:center; gap:8px;
  padding:16px 14px; min-height:64px; flex-shrink:0;
  border-bottom:1px solid var(--color-border);
}
.brand-inner { display:flex; align-items:center; gap:8px; flex:1; overflow:hidden; }
.brand-hex { font-size:22px; flex-shrink:0; background:linear-gradient(135deg,#EF4444,#DC2626); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.brand-name { font-family:var(--font-display); font-size:16px; font-weight:800; white-space:nowrap; color:linear-gradient(120deg, #ffffff 30%, #38bdf8 100%); }
.admin-chip { font-size:10px; font-weight:700; color:rgb(64, 144, 229); padding:2px 8px; border-radius:20px; border:1px solid rgba(98, 120, 148, 0.25); white-space:nowrap; flex-shrink:0; }
.collapse-btn { background:none; border:none; cursor:pointer; color:var(--color-text-3); font-size:18px; padding:4px; border-radius:6px; flex-shrink:0; transition:background .15s; }
.collapse-btn:hover { background:rgba(239,68,68,.1); color:#a28b8b; }

.admin-nav { flex:1; padding:10px; display:flex; flex-direction:column; gap:2px; overflow-y:auto; }
.nav-item {
  display:flex; align-items:center; gap:12px; padding:10px 12px;
  border-radius:10px; text-decoration:none; color:white;
  font-size:14px; font-weight:500; white-space:nowrap; overflow:hidden;
  transition:background .2s, color .2s; position:relative;
}
.nav-item:hover { background:rgb(110, 150, 199); color:var(--color-text); }
.nav-item.active { background:rgb(53, 72, 118); color:#47a8e0; font-weight:600; }
.nav-item.active::before { content:''; position:absolute; right:0; top:50%; transform:translateY(-50%); width:3px; height:60%; background:rgb(28, 88, 229); border-radius:20px; }
.nav-icon { font-size:18px; flex-shrink:0; line-height:1; }
.nav-label { flex:1; }

.sidebar-footer { padding:12px 10px; border-top:1px solid var(--color-border); display:flex; flex-direction:column; gap:6px; flex-shrink:0; }
.s-user { display:flex; align-items:center; gap:10px; padding:8px 10px; overflow:hidden; }
.s-user.centered { justify-content:center; }
.s-name { display:block; font-size:15px; font-weight:600; color:var(--color-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.s-role { font-size:15px; color:#47a8e0; }
.logout-btn { display:flex; align-items:center; gap:8px; justify-content:center; padding:9px 12px; width:100%; background:none; border:1px solid transparent; border-radius:10px; cursor:pointer; color:white; font-family:var(--font-body); font-size:13px; transition:all .15s; }
.logout-btn:hover { background:rgba(239,68,68,.08); color:#EF4444; border-color:rgba(239,68,68,.2); }

/* Main */
.admin-main { flex:1; display:flex; flex-direction:column; overflow:hidden; min-width:0; }
.admin-topbar { height:64px; flex-shrink:0; display:flex; align-items:center; gap:16px; padding:0 28px; background:linear-gradient(145deg, #2c3b5f 2%, #9babd2 50%, #253651 100%); border-bottom:1px solid var(--color-border); position:sticky; top:0; z-index:40; }
.mob-btn { display:none; background:none; border:none; font-size:20px; cursor:pointer; color:var(--color-text-2); }
.topbar-left { flex:1; }
.topbar-heading { font-family:var(--font-display); font-size:17px; font-weight:700; line-height:1.2; }
.topbar-sub { font-size:11px; color:var(--color-text-3); }
.topbar-right { display:flex; align-items:center; gap:14px; }
.topbar-date { font-size:12px; color:white; }

/* Notification Button & Badge */
.notif-wrapper { position: relative; display: flex; align-items: center; }
.notif-btn {
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  padding: 6px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  transition: background .2s, color .2s;
}
.notif-btn:hover { background: rgba(255, 255, 255, 0.1); color: #38bdf8; }
.notif-badge {
  position: absolute;
  top: 2px;
  right: 2px;
  background: #EF4444;
  color: white;
  font-size: 10px;
  font-weight: 700;
  min-width: 16px;
  height: 16px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}

.site-link { font-size:13px; color:rgb(64, 144, 229); text-decoration:none; padding:6px 12px; border:1px solid var(--color-border); border-radius:20px; transition:border-color .2s; }
.site-link:hover { border-color:var(--color-primary); }
.admin-content { flex:1; overflow-y:auto; padding:28px; }

/* Transitions */
.lf-enter-active,.lf-leave-active { transition:opacity .2s; }
.lf-enter-from,.lf-leave-to { opacity:0; }
.ov-enter-active,.ov-leave-active { transition:opacity .25s; }
.ov-enter-from,.ov-leave-to { opacity:0; }

/* Mobile drawer */
.mob-overlay { position:fixed; inset:0; background:rgba(0,0,0,.72); z-index:200; display:flex; }
.mob-drawer { width:260px; background:var(--color-bg-2); height:100%; display:flex; flex-direction:column; padding:16px; gap:4px; }
.mob-brand { display:flex; align-items:center; gap:10px; padding-bottom:14px; border-bottom:1px solid var(--color-border); margin-bottom:8px; font-family:var(--font-display); font-size:16px; font-weight:800; }
.mob-close { background:none; border:none; font-size:18px; cursor:pointer; color:var(--color-text-3); margin-right:auto; }
.mob-link { display:block; padding:12px 14px; color:var(--color-text-2); text-decoration:none; font-size:15px; border-radius:10px; background:none; border:none; cursor:pointer; font-family:var(--font-body); text-align:right; transition:background .15s; }
.mob-link:hover { background:rgba(239,68,68,.08); color:#EF4444; }
.mob-link--danger { color:var(--color-error); }

@media (max-width:768px) {
  .admin-sidebar { display:none; }
  .mob-btn { display:flex; }
  .admin-content { padding:16px; }
  .admin-topbar { padding:0 16px; }
}
</style>