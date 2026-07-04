import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/LandingPage.vue'),
    meta: { title: 'ProLinker — منصة العمل الحر' },
  },
  {
    path: '/services',
    name: 'services',
    component: () => import('@/views/LandingPage.vue'), // reuse landing with search pre-filled
    meta: { title: 'الخدمات' },
  },
  {
    path: '/services/:id',
    name: 'service-detail',
    component: () => import('@/views/LandingPage.vue'), // placeholder until Phase 3-C
    meta: { title: 'تفاصيل الخدمة' },
  },
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
  {
    path: '/dashboard',
    component: () => import('@/views/DashboardPage.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard-home',
        component: () => import('@/views/dashboard/DashboardHome.vue'),
        meta: { title: 'الرئيسية' },
      },
      {
        path: 'wallet',
        name: 'dashboard-wallet',
        component: () => import('@/views/dashboard/WalletPage.vue'),
        meta: { title: 'المحفظة' },
      },
      {
        path: 'orders',
        name: 'dashboard-orders',
        component: () => import('@/views/dashboard/OrdersPage.vue'),
        meta: { title: 'الطلبات' },
      },
      {
        path: 'services',
        name: 'dashboard-services',
        component: () => import('@/views/dashboard/MyServicesPage.vue'),
        meta: { title: 'خدماتي' },
      },
      {
        path: 'profile',
        name: 'dashboard-profile',
        component: () => import('@/views/dashboard/ProfilePage.vue'),
        meta: { title: 'الملف الشخصي' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundPage.vue'),
    meta: { title: '404' },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0, behavior: 'smooth' }
  },
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()

  // Page title
  const title = to.meta.title ?? to.matched.find(r => r.meta.title)?.meta.title
  document.title = title ? `${title} | ProLinker` : 'ProLinker'

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }
  if (to.meta.guestOnly && auth.isLoggedIn) {
    return next({ name: 'dashboard-home' })
  }
  next()
})

export default router
