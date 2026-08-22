<template>
 <nav class="navbar" :class="{ 'navbar--scrolled': scrolled }">
 <div class="navbar__inner">

 <!-- Logo -->
 <RouterLink to="/" class="navbar__logo">
 <span class="logo-icon"></span>
 <span class="logo-text">ProLinker</span>
 </RouterLink>

 <!-- Desktop Nav -->
 <div class="navbar__links">
 <RouterLink to="/" class="nav-link">الرئيسية</RouterLink>
 <RouterLink to="/services" class="nav-link">الخدمات</RouterLink>
 <RouterLink to="/how" class="nav-link">كيف يعمل؟</RouterLink>
 </div>

 <!-- Actions -->
 <div class="navbar__actions">
 <!-- Theme toggle -->
 <button class="icon-btn theme-toggle" @click="themeStore.toggle()" :aria-label="themeStore.theme === 'dark' ? 'تفعيل الوضع الفاتح' : 'تفعيل الوضع الداكن'">
 <svg v-if="themeStore.theme === 'dark'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
 <circle cx="12" cy="12" r="4.5"/>
 <path d="M12 2.5v2.5M12 19v2.5M4.6 4.6l1.8 1.8M17.6 17.6l1.8 1.8M2.5 12H5M19 12h2.5M4.6 19.4l1.8-1.8M17.6 6.4l1.8-1.8"/>
 </svg>
 <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
 <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>
 </svg>
 </button>

 <!-- Single account entry point -->
 <div v-if="authStore.isLoggedIn" class="avatar-menu" ref="menuRef">
 <RouterLink to="/dashboard" class="avatar-name">{{ authStore.user?.name?.split(' ')[0] }}</RouterLink>
 <button class="chevron-btn" @click="menuOpen = !menuOpen">
 <span class="chevron" :class="{ open: menuOpen }">▾</span>
 </button>

 <Transition name="dropdown">
 <div v-if="menuOpen" class="dropdown">
 <button class="dropdown-item dropdown-item--danger" @click="handleLogout">
 <span></span> تسجيل الخروج
 </button>
 </div>
 </Transition>
 </div>

 <!-- Guest buttons -->
 <template v-else>
 <RouterLink to="/login" class="btn-ghost">تسجيل الدخول</RouterLink>
 <RouterLink to="/register" class="btn-primary">انضم مجاناً</RouterLink>
 </template>
 </div>

 <!-- Mobile hamburger -->
 <button class="hamburger" @click="mobileOpen = !mobileOpen" aria-label="القائمة">
 <span :class="{ open: mobileOpen }"></span>
 </button>
 </div>

 <!-- Mobile Menu -->
 <Transition name="mobile-menu">
 <div v-if="mobileOpen" class="mobile-menu">
 <RouterLink to="/" class="mobile-link" @click="mobileOpen=false">الرئيسية</RouterLink>
 <RouterLink to="/services" class="mobile-link" @click="mobileOpen=false">الخدمات</RouterLink>
 <RouterLink to="/how" class="mobile-link" @click="mobileOpen=false">كيف يعمل؟</RouterLink>
 <template v-if="!authStore.isLoggedIn">
 <RouterLink to="/login" class="mobile-link mobile-link--primary" @click="mobileOpen=false">تسجيل الدخول</RouterLink>
 <RouterLink to="/register" class="mobile-link mobile-link--gold" @click="mobileOpen=false">انضم مجاناً</RouterLink>
 </template>
 <template v-else>
 <RouterLink to="/dashboard" class="mobile-link" @click="mobileOpen=false">حسابي</RouterLink>
 <button class="mobile-link mobile-link--danger" @click="handleLogout">تسجيل الخروج</button>
 </template>
 </div>
 </Transition>
 </nav>
</template>

<script setup> import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { useThemeStore } from '@/stores/theme'

defineEmits(['toggle-notifications'])

const authStore = useAuthStore()
const notifStore = useNotificationsStore()
const themeStore = useThemeStore()
const router = useRouter()

const scrolled = ref(false)
const menuOpen = ref(false)
const mobileOpen = ref(false)
const menuRef = ref(null)

function handleScroll() {
 scrolled.value = window.scrollY > 20
}

function handleClickOutside(e) {
 if (menuRef.value && !menuRef.value.contains(e.target)) menuOpen.value = false
}

async function handleLogout() {
 menuOpen.value = false
 mobileOpen.value = false
 notifStore.stopPolling()
 await authStore.logout()
 router.push('/login')
}

onMounted(() => {
 window.addEventListener('scroll', handleScroll, { passive: true })
 document.addEventListener('click', handleClickOutside)
})
onUnmounted(() => {
 window.removeEventListener('scroll', handleScroll)
 document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped> .navbar {
 position: fixed;
 top: 0; right: 0; left: 0;
 z-index: 100;
 padding: 0 24px;
 height: 68px;
 display: flex;
 align-items: center;
 transition: background 0.3s var(--ease-smooth), box-shadow 0.3s var(--ease-smooth);
}
.navbar--scrolled {
 background: rgba(29, 37, 56, 0.92);
 backdrop-filter: blur(16px);
 -webkit-backdrop-filter: blur(16px);
 box-shadow: 0 1px 0 var(--color-border), 0 4px 24px rgba(0,0,0,0.3);
}
.navbar__inner {
 width: 100%; max-width: 1280px; margin: 0 auto;
 display: flex; align-items: center; gap: 32px;
}
/* Logo */
.navbar__logo {
 display: flex; align-items: center; gap: 10px;
 text-decoration: none; flex-shrink: 0;
}
.logo-icon {
 font-size: 24px;
 background: linear-gradient(120deg, #38bdf8 100%);
 -webkit-background-clip: text;
 -webkit-text-fill-color: transparent;
 background-clip: text;
 line-height: 1;
}
.logo-text {
 font-family: var(--font-display);
 font-size: 20px;
 font-weight: 800;
 background: linear-gradient(120deg, #ffffff 30%, #38bdf8 100%);
 -webkit-background-clip: text;
 -webkit-text-fill-color: transparent;
 background-clip: text;
 letter-spacing: -0.3px;
}
/* Links */
.navbar__links {
 display: flex; gap: 8px; align-items: center; flex: 1;
 justify-content: center;
}
.nav-link {
 color: var(--color-text-2);
 text-decoration: none;
 font-size: 14px;
 font-weight: 700;
 padding: 6px 14px;
 border-radius: var(--radius-full);
 transition: color 0.2s, background 0.2s;
}
.nav-link:hover, .nav-link.router-link-active {
 color: var(--color-text);
 background: rgba(99,102,241,0.1);
}
/* Actions */
.navbar__actions {
 display: flex; align-items: center; gap: 12px; margin-right: auto;
}
.icon-btn {
 background: none; border: none; cursor: pointer;
 position: relative; padding: 6px;
 display: flex; align-items: center; justify-content: center;
}
.theme-toggle { font-size: 20px; border-radius: 999px; transition: background 0.15s; color: #efeff2; }
.theme-toggle:hover { background: var(--color-bg-2); }
.btn-account {
 padding: 8px 22px;
 border-radius: 999px !important; 
 background: linear-gradient( #5b74d6 100%) !important; /* تدرج موف/أزرق ناعم */
 color: #ffffff !important;
 font-weight: 700;
 font-size: 13px;
 text-decoration: none;
 white-space: nowrap;
 box-shadow: 0 4px 14px rgba(50, 51, 99, 0.35); /* ظل خفيف يعطي بعد */
 transition: all 0.2s ease;
}

.bell-icon { font-size: 20px; }
.notif-badge {
 position: absolute;
 top: 0; left: 0;
 background: var(--color-primary);
 color: white;
 font-size: 10px;
 font-weight: 700;
 min-width: 18px; height: 18px;
 border-radius: var(--radius-full);
 display: flex; align-items: center; justify-content: center;
 padding: 0 4px;
 border: 2px solid var(--color-bg);
}
/* Avatar menu */
.avatar-menu {
 display: flex; align-items: center; gap: 8px;
 cursor: pointer; position: relative;
 padding: 6px 12px;
 border-radius: var(--radius-lg);
 border: 2px solid var(--color-border);
 transition: border-color 0.2s, background 0.2s;
 user-select: none;
 background: #5d74ad5a;
}
.avatar-menu:hover { border-color: var(--color-border-hover); background: rgba(99,102,241,0.06); }
.avatar {
 width: 32px; height: 32px;
 border-radius: var(--radius-full);
 background: #5b74d6 100%;
 display: flex; align-items: center; justify-content: center;
 font-size: 12px; font-weight: 700; color: white;
}
.avatar-name { font-size: 14px; font-weight: 600; color: var(--color-text); text-decoration: none; }
.chevron-btn { background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; }
.chevron { font-size: 12px; color: var(--color-text-3); transition: transform 0.2s; }
.chevron.open { transform: rotate(180deg); }
/* Dropdown */
.dropdown {
 position: absolute;
 top: calc(100% + 10px);
 left: 0;
 min-width: 200px;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-lg);
 box-shadow: var(--shadow-card);
 overflow: hidden;
 z-index: 200;
}
.dropdown-item {
 display: flex; align-items: center; gap: 10px;
 padding: 12px 16px;
 font-size: 14px; color: var(--color-text-2);
 text-decoration: none;
 background: none; border: none; width: 100%;
 cursor: pointer; text-align: right;
 transition: background 0.15s, color 0.15s;
 font-family: var(--font-body);
}
.dropdown-item:hover { background: rgba(99,102,241,0.1); color: var(--color-text); }
.dropdown-item--danger:hover { background: rgba(239,68,68,0.1); color: var(--color-error); }
.dropdown-divider { height: 1px; background: var(--color-border); margin: 4px 0; }
/* Buttons */
.btn-ghost {
 padding: 8px 18px;
 border-radius: var(--radius-full);
 border: 1px solid var(--color-border);
 color:  var(--color-text) ;
 text-decoration: none;
 font-size: 14px; font-weight: 600;
 transition: border-color 0.2s, color 0.2s;
  background: rgb(80, 109, 190);

}
.btn-ghost:hover { border-color: var(--color-primary); color: var(--color-text); }
.btn-primary {
 padding: 8px 20px;
 border-radius: var(--radius-full);
 background: rgb(80, 109, 190);
 color: var(--color-text);
 text-decoration: none;
 font-size: 14px; font-weight: 600;
 transition: opacity 0.2s, box-shadow 0.2s;
 box-shadow: 0 0 20px rgba(99,102,241,0.3);
}
.btn-primary:hover { opacity: 0.9; box-shadow: 0 0 28px rgba(99,102,241,0.5); }
/* Hamburger */
.hamburger { display: none; }
/* Mobile menu */
.mobile-menu {
 position: fixed;
 top: 68px; right: 0; left: 0;
 background: rgba(15,22,40,0.97);
 backdrop-filter: blur(20px);
 padding: 16px;
 display: flex; flex-direction: column; gap: 4px;
 border-bottom: 1px solid var(--color-border);
}
.mobile-link {
 display: block; padding: 14px 16px;
 color: var(--color-text-2);
 text-decoration: none;
 font-size: 16px; font-weight: 500;
 border-radius: var(--radius-md);
 border: none; background: none; cursor: pointer;
 text-align: right; width: 100%;
 font-family: var(--font-body);
 transition: background 0.15s, color 0.15s;
}
.mobile-link:hover { background: rgba(99,102,241,0.1); color: var(--color-text); }
.mobile-link--primary { color: var(--color-primary); }
.mobile-link--gold { color: var(--color-gold); }
.mobile-link--danger { color: var(--color-error); }
/* Transitions */
.dropdown-enter-active, .dropdown-leave-active { transition: opacity 0.15s, transform 0.15s var(--ease-smooth); }
.dropdown-enter-from { opacity: 0; transform: translateY(-6px); }
.dropdown-leave-to { opacity: 0; transform: translateY(-4px); }
.mobile-menu-enter-active, .mobile-menu-leave-active { transition: opacity 0.2s, transform 0.2s; }
.mobile-menu-enter-from { opacity: 0; transform: translateY(-10px); }
.mobile-menu-leave-to { opacity: 0; transform: translateY(-6px); }
@media (max-width: 768px) {
 .navbar__links { display: none; }
 .btn-ghost, .btn-primary, .avatar-name { display: none; }
 .hamburger {
 display: flex; flex-direction: column; gap: 5px;
 background: none; border: none; cursor: pointer;
 padding: 6px; margin-right: auto;
 }
 .hamburger span,
 .hamburger span::before,
 .hamburger span::after {
 display: block;
 width: 22px; height: 2px;
 background: var(--color-text-2);
 border-radius: 2px;
 transition: transform 0.25s;
 }
}
</style>
