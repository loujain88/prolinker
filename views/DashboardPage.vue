<template>
 <div class="dashboard-layout" dir="rtl">

 <!-- ── Sidebar ──────────────────────────────────────────────── -->
 <aside class="sidebar" :class="{ 'sidebar--collapsed': sidebarCollapsed }">
 <!-- Logo -->
 <div class="sidebar__logo">
 <RouterLink to="/" class="logo-link">
 <span class="logo-hex"></span>
 <Transition name="label-fade">
 <span v-if="!sidebarCollapsed" class="logo-text">ProLinker</span>
 </Transition>
 </RouterLink>
 <button class="collapse-btn" @click="sidebarCollapsed = !sidebarCollapsed"
 :aria-label="sidebarCollapsed ? 'توسيع القائمة' : 'طي القائمة'">
 <span class="collapse-icon" :class="{ flipped: sidebarCollapsed }">‹</span>
 </button>
 </div>

 <!-- Role status indicator (مؤشر الحالة الحالي فقط لمنع الأخطاء) -->
 <div class="role-pill-wrap" v-if="auth.user?.role !== 'admin'">
 <Transition name="label-fade">
 <span v-if="!sidebarCollapsed" class="role-pill-label">الوضع الحالي</span>
 </Transition>
 <div class="role-pill-status" :class="`role-pill-status--${activeRole}`">
 <span v-if="activeRole === 'client'"> <Transition name="label-fade"><span v-if="!sidebarCollapsed">وضع العميل</span></Transition></span>
 <span v-else> <Transition name="label-fade"><span v-if="!sidebarCollapsed">وضع المستقل</span></Transition></span>
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

 <!-- Sidebar footer: user card & actions -->
 <div class="sidebar__footer">
 <div class="user-card" :class="{ 'user-card--collapsed': sidebarCollapsed }">
 <Transition name="label-fade">
 <div v-if="!sidebarCollapsed" class="user-info">
 <span class="user-name">{{ auth.user?.name }}</span>
 <span class="user-role-tag">{{ roleLabel }}</span>
 </div>
 </Transition>
 </div>

 <!-- زر التبديل الذكي والديناميكي السفلي الجديد -->
 <button v-if="auth.user?.role !== 'admin'" class="smart-switch-btn" @click="handleSmartSwitch" :title="sidebarCollapsed ? 'تبديل الحساب' : ''">
 <span></span>
 <Transition name="label-fade">
 <span v-if="!sidebarCollapsed"> {{ activeRole === 'client' ? 'التبديل لحساب مستقل' : 'التبديل لحساب عميل' }}
 </span>
 </Transition>
 </button>

 <button class="logout-btn" @click="handleLogout" :title="sidebarCollapsed ? 'تسجيل الخروج' : ''">
 <span></span>
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
 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
 </button>

 <div class="topbar__greeting">
 <h1 class="topbar__title">{{ pageTitle }}</h1>
 <span class="topbar__date">{{ todayAr }}</span>
 </div>

 <div class="topbar__actions">
 <!-- Notification bell -->
 <button class="icon-btn" @click="notifOpen = !notifOpen"
 :aria-label="`الإشعارات${notifStore.unreadCount ? ' (' + notifStore.unreadCount + ')' : ''}`">
 <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
 <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
 <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
 </svg>
 <span v-if="notifStore.hasUnread" class="bell-badge"> {{ notifStore.unreadCount > 9 ? '9+' : notifStore.unreadCount }}
 </span>
 </button>
 <!-- Avatar -->
 
 </div>
 </header>

 <!-- Role-switch animated banner -->
 <Transition name="role-banner">
 <div v-if="roleSwitchMsg" class="role-banner" :class="`role-banner--${activeRole}`">
 <span>{{ activeRole === 'client' ? '' : '' }}</span> {{ roleSwitchMsg }}
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

<script setup> import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import NotificationPanel from '@/components/shared/NotificationPanel.vue'
import api from '@/composables/useApi'

const auth = useAuthStore()
const notifStore = useNotificationsStore()
const router = useRouter()
const route = useRoute()

const sidebarCollapsed = ref(false)
const mobileDrawer = ref(false)
const notifOpen = ref(false)

const activeRole = computed(() => auth.activeRole)
const roleSwitchMsg = ref('')

// دالة التبديل الذكي متعدد الحسابات (Multi-Account)
// دالة التبديل: تحفظ الحساب الحالي وتنقل المستخدم لصفحة تسجيل الدخول
function handleSmartSwitch() {
 const targetRole = auth.activeRole === 'client' ? 'seller' : 'client'
 
 // 1. جلب الحسابات المحفوظة سابقاً
 const savedAccounts = JSON.parse(localStorage.getItem('pl_saved_accounts') || '{}')
 
 // 2. حفظ الحساب الحالي في الذاكرة لتسهيل العودة له لاحقاً
 const currentToken = localStorage.getItem('pl_token')
 if (currentToken && auth.user) {
 savedAccounts[auth.activeRole] = {
 token: currentToken,
 user: auth.user,
 activeRole: auth.activeRole
 }
 localStorage.setItem('pl_saved_accounts', JSON.stringify(savedAccounts))
 }

 // 3. تنظيف الجلسة الحالية مؤقتاً لتجهيز النقل لصفحة الدخول
 localStorage.removeItem('pl_token')
 localStorage.removeItem('pl_user')
 localStorage.removeItem('pl_active_role')
 
 if (auth.logoutState) {
 auth.logoutState() // تنظيف الـ Store إذا كانت الدالة متوفرة
 } else {
 auth.token = null
 auth.user = null
 }

 // 4. التوجيه لصفحة تسجيل الدخول مع توضيح الوضع المطلوب (seller أو client)
 router.push({ 
 path: '/login', 
 query: { mode: targetRole } 
 })
}
const navItems = computed(() => {
 const base = [
 { icon: '', label: 'الرئيسية', to: '/dashboard', routeName: 'dashboard-home' },
 { icon: '', label: 'المحفظة', to: '/dashboard/wallet', routeName: 'dashboard-wallet' },
 { icon: '', label: 'الطلبات', to: '/dashboard/orders', routeName: 'dashboard-orders' },
 ]
 if (activeRole.value === 'seller') {
 base.push({ icon: '', label: 'خدماتي', to: '/dashboard/services', routeName: 'dashboard-services' })
 }
 base.push({ icon: '', label: 'الرسائل', to: '/dashboard/messages', routeName: 'dashboard-messages' })
 base.push({ icon: '', label: 'الملف الشخصي', to: '/dashboard/profile', routeName: 'dashboard-profile' })
 return base
})

const pageTitle = computed(() => {
 const map = {
 'dashboard-home': `مرحباً، ${auth.user?.name?.split(' ')[0] ?? ''} `,
 'dashboard-wallet': 'المحفظة',
 'dashboard-orders': 'الطلبات',
 'dashboard-services': 'خدماتي',
 'dashboard-messages': 'الرسائل',
 'dashboard-profile': 'الملف الشخصي',
 }
 return map[route.name] ?? 'لوحة التحكم'
})

const roleLabel = computed(() => auth.user?.role === 'admin' ? 'مدير' :
 activeRole.value === 'seller' ? 'مستقل' : 'عميل'
)

const todayAr = computed(() => new Date().toLocaleDateString('ar-EG-u-nu-latn', { weekday:'long', year:'numeric', month:'long', day:'numeric' })
)

async function handleLogout() {
 localStorage.removeItem('saved_accounts') // تنظيف ذاكرة الحسابات التبادلية عند الخروج الكلي
 notifStore.stopPolling()
 await auth.logout()
 router.push('/login')
}

onMounted(() => {
 notifStore.startPolling(30_000)
})
</script>

<style scoped> .dashboard-layout {
 display: flex;
 min-height: 100vh;
 background: linear-gradient(145deg, #7394e7 2%, #9babd2 50%, #3b4a62 100%);
 color: #ffffff;
 font-family: var(--font-body);
 -webkit-font-smoothing: antialiased;
}

/* ═══════════════════════════════════════════════
 SIDEBAR
═══════════════════════════════════════════════ */
.sidebar {
 width: 260px;
 flex-shrink: 0;
 background: linear-gradient(145deg, #2c3b5f 2%, #9babd2 50%, #253651 100%);
 backdrop-filter: blur(20px);
 border-left: 1px solid rgba(87, 76, 76, 0.05);
 display: flex;
 flex-direction: column;
 position: sticky;
 top: 0;
 height: 100vh;
 overflow: hidden;
 transition: width 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
 z-index: 50;
}
.sidebar--collapsed { width: 78px; }

.sidebar__logo {
 display: flex;
 align-items: center;
 justify-content: space-between;
 padding: 24px 20px;
 border-bottom: 1px solid rgba(91, 125, 143, 0.05);
 flex-shrink: 0;
 min-height: 70px;
}
.logo-link { display: flex; align-items: center; gap: 12px; text-decoration: none; overflow: hidden; }
.logo-hex {
 font-size: 24px; flex-shrink: 0;
 color: #3c9ec8;
 text-shadow: 0 0 15px rgba(56, 189, 248, 0.4);
}
.logo-text { 
 font-size: 20px; 
 font-weight: 800; 
 white-space: nowrap; 
 letter-spacing: -0.5px;
 background: linear-gradient(120deg, #ffffff 30%, #38bdf8 100%);
 -webkit-background-clip: text;
 -webkit-text-fill-color: transparent;
}
.collapse-btn {
 background: rgba(255, 255, 255, 0.02); 
 border: 1px solid rgba(255, 255, 255, 0.05); 
 cursor: pointer;
 width: 28px; height: 28px; border-radius: 8px;
 display: flex; align-items: center; justify-content: center;
 color: #64748b; font-size: 16px;
 transition: all 0.2s ease;
 flex-shrink: 0;
}
.collapse-btn:hover { background: rgba(56, 189, 248, 0.1); color: #38bdf8; border-color: rgba(56, 189, 248, 0.2); }
.collapse-icon { display: inline-block; transition: transform 0.3s; }
.collapse-icon.flipped { transform: rotate(180deg); }

.role-pill-wrap { padding: 20px 16px 12px; }
.role-pill-label { display: block; font-size: 11px; color: #f5f6f8; margin-bottom: 8px; font-weight: 700; letter-spacing: 0.5px; }

/* تصميم لوحة مؤشر الحالة الثابتة والآمنة */
.role-pill-status {
 display: flex;
 align-items: center;
 justify-content: center;
 padding: 10px;
 border-radius: 12px;
 font-size: 14px;
 font-weight: 700;
 white-space: nowrap;
 overflow: hidden;
}
.role-pill-status--client {
background: rgba(60, 146, 183, 0.08);
 color: #eef1f2;
 border: 1px solid rgba(42, 60, 66, 0.2);
}
.role-pill-status--seller {
 background: rgba(60, 146, 183, 0.08);
 color: #eef1f2;
 border: 1px solid rgba(42, 60, 66, 0.2);
}

.sidebar__nav { flex: 1; padding: 12px 12px; display: flex; flex-direction: column; gap: 4px; overflow-y: auto; }
.nav-item {
 display: flex; align-items: center; gap: 14px;
 padding: 12px 16px;
 border-radius: 14px;
 text-decoration: none;
 color: #f1f2f3;
 font-size: 14px; font-weight: 600;
 transition: all 0.2s ease;
 white-space: nowrap; overflow: hidden;
 position: relative;
 background: transparent;
 border: 1px solid transparent;
}
.nav-item:hover { 
 background:rgb(136, 168, 214); 
 
}
.nav-item--active { 
 background: rgb(53, 72, 118);
color: #47a8e0;
 font-weight: 700;
 border-color: rgba(255, 255, 255, 0.06);
}
.nav-item--active::before {
 content: ''; position: absolute; right: 0; top: 50%; transform: translateY(-50%);
 width: 4px; height: 40%; background: #38bdf8; border-radius: 4px;
}
.nav-icon { font-size: 18px; flex-shrink: 0; line-height: 1; }
.nav-label { flex: 1; }
.nav-badge {
 font-size: 11px; font-weight: 700;
 background: rgba(56, 189, 248, 0.1); color: #38bdf8;
 padding: 2px 8px; border-radius: 8px;
 border: 1px solid rgba(56, 189, 248, 0.15);
}

.sidebar__footer { padding: 16px 12px; border-top: 1px solid rgba(25, 76, 114, 0.05); display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
.user-card { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 14px; overflow: hidden; background: rgba(255, 255, 255, 0.01); border: 1px solid rgba(255, 255, 255, 0.03); }
.user-card--collapsed { justify-content: center; }
.user-avatar {
 width: 36px; height: 36px; flex-shrink: 0;
 border-radius: 50%;
 background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
 border: 1px solid rgba(255, 255, 255, 0.08);
 display: flex; align-items: center; justify-content: center;
 font-size: 13px; font-weight: 700; color: #ffffff;
}
.user-info { display: flex; flex-direction: column; overflow: hidden; }
.user-name { font-size: 13px; font-weight: 700; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role-tag { font-size: 11px; color: #dee3eb; font-weight: 500; }

/* تصميم زر التبديل السفلي الذكي الجديد */
.smart-switch-btn {
 display: flex; align-items: center; gap: 10px; justify-content: flex-start;
 padding: 11px 16px; width: 100%;
 background: rgba(255, 255, 255, 0.02);
 border: 1px solid rgba(255, 255, 255, 0.06);
 border-radius: 14px; cursor: pointer;
 color: #e2e8f0; font-size: 13px; font-weight: 600;
 transition: all 0.2s ease;
}
.smart-switch-btn:hover {
 background: rgba(56, 189, 248, 0.08);
 border-color: rgba(56, 189, 248, 0.2);
 color: #38bdf8;
}

.logout-btn {
 display: flex; align-items: center; gap: 10px; justify-content: center;
 padding: 11px 14px; width: 100%;
 background: transparent; 
 border: 1px solid transparent;
 border-radius: 14px; cursor: pointer;
 color: #e6e9ed; font-size: 14px; font-weight: 600;
 transition: all 0.2s ease;
}
.logout-btn:hover { background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.1); color: #ef4444; }

/* ═══════════════════════════════════════════════
 MAIN AREA
═══════════════════════════════════════════════ */
.dashboard-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

.topbar {
 height: 75px; flex-shrink: 0;
 display: flex; align-items: center; gap: 20px;
 padding: 0 32px;
 background: linear-gradient(145deg, #2c3b5f 2%, #9babd2 50%, #253651 100%);
 backdrop-filter: blur(20px);
 border-bottom: 1px solid rgba(255, 255, 255, 0.05);
 position: sticky; top: 0; z-index: 40;
}
.mobile-menu-btn { display: none; background: none; border: none; font-size: 22px; cursor: pointer; color: #ffffff; }
.topbar__greeting { flex: 1;  }
.topbar__title { font-size: 20px; font-weight: 800; line-height: 1.2;  color: #ffffff; letter-spacing: -0.5px; }
.topbar__date { font-size: 12px; color: #f6f7f8; font-weight: 600; }
.topbar__actions { display: flex; align-items: center; gap: 14px; }
.icon-btn { 
 background: rgba(255, 255, 255, 0.02); 
 border: 1px solid rgba(255, 255, 255, 0.05); 
 font-size: 18px; cursor: pointer; position: relative; 
 padding: 8px; border-radius: 12px; transition: all 0.2s ease; 
 display: flex; align-items: center; justify-content: center;
}
.icon-btn:hover { background: rgba(255, 255, 255, 0.04); border-color: rgba(255, 255, 255, 0.1); }
.bell-badge {
 position: absolute; top: -4px; left: -4px;
 background: #38bdf8; color: #0b0f19;
 font-size: 10px; font-weight: 800;
 min-width: 18px; height: 18px; padding: 0 4px;
 border-radius: 50%;
 display: flex; align-items: center; justify-content: center;
 border: 2px solid #0b0f19;
}
.topbar__avatar {
 width: 38px; height: 38px; border-radius: 50%;
 background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
 border: 1px solid rgba(255, 255, 255, 0.08);
 display: flex; align-items: center; justify-content: center;
 font-size: 13px; font-weight: 700; color: #ffffff; flex-shrink: 0;
}

.role-banner {
 display: flex; align-items: center; gap: 12px;
 padding: 14px 32px;
 font-size: 14px; font-weight: 600;
 backdrop-filter: blur(10px);
}
.role-banner--client { background: rgba(56, 189, 248, 0.06); color: #38bdf8; border-bottom: 1px solid rgba(56, 189, 248, 0.1); }
.role-banner--seller { background: rgba(56, 189, 248, 0.06); color: #38bdf8; border-bottom: 1px solid rgba(56, 189, 248, 0.1); }
.role-banner-enter-active { transition: opacity 0.3s, transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), max-height 0.3s; max-height: 60px; }
.role-banner-leave-active { transition: opacity 0.2s, max-height 0.25s; overflow: hidden; max-height: 60px; }
.role-banner-enter-from { opacity: 0; transform: translateY(-8px); max-height: 0; }
.role-banner-leave-to { opacity: 0; max-height: 0; }

.dashboard-content { flex: 1; overflow-y: auto; padding: 32px; }

.dash-page-enter-active { transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.25, 0.8, 0.25, 1); }
.dash-page-leave-active { transition: opacity 0.15s ease; }
.dash-page-enter-from { opacity: 0; transform: translateY(10px); }
.dash-page-leave-to { opacity: 0; }

.label-fade-enter-active,.label-fade-leave-active { transition: opacity 0.2s; }
.label-fade-enter-from,.label-fade-leave-to { opacity:0; }

.mobile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 49; }
.overlay-enter-active,.overlay-leave-active { transition: opacity 0.25s; }
.overlay-enter-from,.overlay-leave-to { opacity: 0; }

@media (max-width: 768px) {
 .sidebar { position: fixed; right: -260px; transition: right 0.3s, width 0.3s; }
 .sidebar.mobile-open { right: 0; }
 .sidebar--collapsed { width: 260px; right: -260px; }
 .mobile-menu-btn { display: flex; }
 .dashboard-content { padding: 20px; }
 .topbar { padding: 0 20px; }
}
</style>