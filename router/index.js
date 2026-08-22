/**
 * src/router/index.js
 */

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore }                   from '@/stores/auth'
import adminRoutes                        from './admin-routes'

const routes = [
  // ── Public Routes ──────────────────────────────────────────────────────────
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/LandingPage.vue'),
    meta: { title: 'ProLinker — منصة العمل الحر' },
  },
  {
    path: '/services',
    name: 'services',
    component: () => import('@/views/AllServicesPage.vue'),
    meta: { title: 'تصفّح الخدمات' },
  },
  {
    path: '/categories',
    redirect: '/services',
  },
  {
    path: '/help',
    name: 'help',
    component: () => import('@/views/HelpCenterPage.vue'),
    meta: { title: 'مركز المساعدة' },
  },
  {
    path: '/terms',
    name: 'terms',
    component: () => import('@/views/StaticPage.vue'),
    meta: { title: 'الشروط والأحكام', staticKey: 'terms' },
  },
  {
    path: '/privacy',
    name: 'privacy',
    component: () => import('@/views/StaticPage.vue'),
    meta: { title: 'سياسة الخصوصية', staticKey: 'privacy' },
  },
  {
    path: '/how',
    name: 'how',
    component: () => import('@/views/StaticPage.vue'),
    meta: { title: 'كيف يعمل الموقع؟', staticKey: 'how' },
  },
  {
    path: '/escrow',
    name: 'escrow',
    component: () => import('@/views/StaticPage.vue'),
    meta: { title: 'نظام الضمان', staticKey: 'escrow' },
  },
  {
    path: '/services/:id',
    name: 'service-detail',
    component: () => import('@/views/ServiceDetailPage.vue'),
    meta: { title: 'تفاصيل الخدمة' },
  },

  // ── Auth Routes ────────────────────────────────────────────────────────────
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginPage.vue'),
    meta: { title: 'تسجيل الدخول', guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/RegisterPage.vue'),
    meta: { title: 'إنشاء حساب', guestOnly: true },
  },

  // ── Client / Seller Dashboard (User Dashboard) ────────────────────────────
  {
    path: '/dashboard',
    component: () => import('@/views/DashboardPage.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard-home',
        component: () => import('@/views/dashboard/DashboardHome.vue'),
        meta: { title: 'لوحة التحكم' },
      },
      {
        path: 'orders',
        name: 'dashboard-orders',
        component: () => import('@/views/dashboard/OrdersPage.vue'),
        meta: { title: 'الطلبات' },
      },
      {
        path: 'orders/:id',
        name: 'order-detail',
        component: () => import('@/views/OrderDetailPage.vue'),
        meta: { title: 'تفاصيل الطلب' },
      },
      {
        path: 'orders/:id/deliver',
        name: 'order-deliver',
        component: () => import('@/views/dashboard/DeliverOrderPage.vue'),
        meta: { title: 'تسليم الطلب' },
      },
      {
        path: 'services',
        name: 'dashboard-services',
        component: () => import('@/views/dashboard/MyServicesPage.vue'),
        meta: { title: 'خدماتي' },
      },
      {
        path: 'messages',
        name: 'dashboard-messages',
        component: () => import('@/views/dashboard/MessagesPage.vue'),
        meta: { title: 'الرسائل' },
      },
      {
        path: 'support/:id',
        name: 'dashboard-support',
        component: () => import('@/views/dashboard/SupportChatPage.vue'),
        meta: { title: 'محادثة الدعم' },
      },
      {
        path: 'wallet',
        name: 'dashboard-wallet',
        component: () => import('@/views/dashboard/WalletPage.vue'),
        meta: { title: 'المحفظة' },
      },
      {
        path: 'profile',
        name: 'dashboard-profile',
        component: () => import('@/views/dashboard/ProfilePage.vue'),
        meta: { title: 'الملف الشخصي' },
      },
    ]
  },

  // ── Admin Routes ──────────────────────────────────────────────────────────
  ...adminRoutes,

  // ── 404 Catch-All ──────────────────────────────────────────────────────────
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundPage.vue'),
    meta: { title: 'الصفحة غير موجودة' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0, behavior: 'smooth' }
  },
})

// ── Navigation Guard ─────────────────────────────────────────────────────────
router.beforeEach((to, from, next) => {
  const auth = useAuthStore()

  // 1. تحديث عنوان الصفحة
  const title = to.meta.title ?? to.matched.findLast(r => r.meta.title)?.meta.title
  document.title = title ? `${title} | ProLinker` : 'ProLinker'

  const isLoggedIn = auth.isLoggedIn
  const isAdmin    = isLoggedIn && auth.user?.role === 'admin'

  // ── Rule 1: زائر غير مسجل دخول يحاول التصفح ──────────────────────────────
  if (to.meta.requiresAuth && !isLoggedIn) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // ── Rule 2: غير الأدمن يحاول دخول صفحات الأدمن ───────────────────────────
  if (to.meta.requiresAdmin && !isAdmin) {
    return next(isLoggedIn ? { name: 'dashboard-home' } : { name: 'login' })
  }

  // ── Rule 3: الأدمن يفتح /dashboard المخصصة للزبائن/المستقلين ──────────────
  if (isAdmin && to.path.startsWith('/dashboard')) {
    return next({ name: 'AdminDashboard' })
  }

  // ── Rule 4: مستخدم مسجل دخول يحاول فتح صفحة الدخول/التسجيل ───────────────
  if (isLoggedIn && to.meta.guestOnly) {
    return next(isAdmin ? { name: 'AdminDashboard' } : { name: 'dashboard-home' })
  }

  next()
})

export default router