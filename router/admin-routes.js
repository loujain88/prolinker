/**
 * src/router/admin-routes.js
 */

const adminRoutes = [
  {
    path: '/admin',
    component: () => import('@/views/admin/AdminLayout.vue'),
    meta: { requiresAdmin: true },
    children: [
      { path: '', redirect: { name: 'AdminDashboard' } },
      {
        path: 'dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/admin/AdminDashboard.vue'),
        meta: { title: 'لوحة الأدمن', requiresAdmin: true },
      },
      {
        path: 'financials',
        name: 'AdminFinancials',
        component: () => import('@/views/admin/AdminFinancials.vue'),
        meta: { title: 'العمليات المالية', requiresAdmin: true },
      },
      {
        path: 'services',
        name: 'AdminServices',
        component: () => import('@/views/admin/AdminServices.vue'),
        meta: { title: 'مراجعة الخدمات', requiresAdmin: true },
      },
      {
        path: 'categories',
        name: 'AdminCategories',
        component: () => import('@/views/admin/AdminCategories.vue'),
        meta: { title: 'التصنيفات', requiresAdmin: true },
      },
      {
        path: 'users',
        name: 'AdminUsers',
        component: () => import('@/views/admin/AdminUsers.vue'),
        meta: { title: 'المستخدمون', requiresAdmin: true },
      },
      {
        path: 'disputes',
        name: 'AdminDisputes',
        component: () => import('@/views/admin/AdminDisputes.vue'),
        meta: { title: 'النزاعات', requiresAdmin: true },
      },
      {
        path: 'disputes/:id',
        name: 'AdminDisputeDetail',
        component: () => import('@/views/admin/AdminDisputes.vue'),
        meta: { title: 'تفاصيل النزاع', requiresAdmin: true },
      },
      {
        path: 'conversations',
        name: 'AdminConversations',
        component: () => import('@/views/admin/AdminConversations.vue'),
        meta: { title: 'الرسائل', requiresAdmin: true },
      },
      {
        path: 'conversations/:id',
        name: 'AdminConversationDetail',
        component: () => import('@/views/admin/AdminConversations.vue'),
        meta: { title: 'الرسائل', requiresAdmin: true },
      },
      {
        path: 'support',
        name: 'AdminSupport',
        component: () => import('@/views/admin/AdminSupport.vue'),
        meta: { title: 'محادثات الدعم', requiresAdmin: true },
      },
      {
        path: 'account-requests',
        name: 'AdminAccountRequests',
        component: () => import('@/views/admin/AdminAccountRequests.vue'),
        meta: { title: 'طلبات إنشاء الحسابات', requiresAdmin: true },
      },
      {
        path: 'staff',
        name: 'AdminStaff',
        component: () => import('@/views/admin/AdminStaff.vue'),
        meta: { title: 'إدارة الموظفين', requiresAdmin: true },
      },
    ]
  },
]

export default adminRoutes