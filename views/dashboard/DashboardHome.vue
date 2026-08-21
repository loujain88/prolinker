<template>
 <div class="dash-home" dir="rtl">

 <!-- Welcome hero card -->
 <div class="welcome-card">
 <div class="welcome-orb" aria-hidden="true"></div>
 <div class="welcome-body">
 <p class="welcome-sub">{{ roleLabel }}</p>
 <h2 class="welcome-title"> مرحباً، <span class="grad-text">{{ firstName }}</span> 
 </h2>
 <p class="welcome-desc"> {{ auth.activeRole === 'client'
 ? 'استعرض الخدمات المتاحة أو تابع طلباتك الحالية من هنا.'
 : 'راجع طلباتك الواردة وأدر خدماتك المنشورة.' }}
 </p>
 <RouterLink v-if="auth.activeRole === 'client'" to="/services" class="welcome-cta"> استعرض الخدمات
 </RouterLink>
 </div>
 <div class="welcome-visual" aria-hidden="true">
 </div>
 </div>

 <!-- Unified stats card -->
 <div class="unified-stats">
 <div class="us-header" @click="statsExpanded = !statsExpanded">
 <div class="us-headline">
 <span class="us-headline__num">{{ stats[0]?.value ?? '—' }}</span>
 <span class="us-headline__label">{{ stats[0]?.label }}</span>
 </div>
 <button class="us-toggle" :class="{ open: statsExpanded }">{{ statsExpanded ? 'إخفاء التفاصيل' : 'عرض كل التفاصيل' }}</button>
 </div>
 <Transition name="expand">
 <div v-if="statsExpanded" class="us-body">
 <div class="us-row" v-for="stat in stats.slice(1)" :key="stat.label">
 <span class="us-row__label">{{ stat.label }}</span>
 <span class="us-row__val">{{ stat.value }}</span>
 </div>
 </div>
 </Transition>
 </div>

 <!-- Two column: recent orders + quick actions -->
 <div class="home-grid">

 <!-- Recent orders -->
 <div class="panel">
 <div class="panel__header">
 <h3 class="panel__title">آخر الطلبات</h3>
 <RouterLink to="/dashboard/orders" class="panel__link">عرض الكل ←</RouterLink>
 </div>
 <div v-if="ordersStore.loading" class="panel__loading">
 <div class="mini-spinner"></div>
 </div>
 <div v-else-if="recentOrders.length === 0" class="panel__empty">
 
 <p>لا توجد طلبات بعد</p>
 </div>
 <div v-else class="orders-list">
 <div v-for="order in recentOrders" :key="order.id" class="order-row">
 <div class="order-row__icon">{{ statusIcon(order.status) }}</div>
 <div class="order-row__body">
 <div class="order-row__title">{{ order.service?.title ?? `طلب #${order.id}` }}</div>
 <div class="order-row__meta"> {{ auth.activeRole === 'client' ? order.seller?.name : order.client?.name }}
 · {{ formatDate(order.created_at) }}
 </div>
 </div>
 <div class="order-row__right">
 <span class="status-badge" :class="`status-badge--${order.status}`"> {{ statusLabel(order.status) }}
 </span>
 <div class="order-row__price">${{ order.final_price }}</div>
 </div>
 </div>
 </div>
 </div>

 <!-- Wallet summary -->
 <div class="quick-actions">
 <!-- Wallet balance mini -->
 <div class="wallet-mini">
 <div class="wallet-mini__header">
 <span class="wallet-mini__label">رصيدك الحالي</span>
 <RouterLink to="/dashboard/wallet" class="wallet-mini__link">إدارة المحفظة</RouterLink>
 </div>
 <div class="wallet-mini__amount">
 <span class="currency">$</span>
 <span class="amount">{{ walletStore.formattedBalance }}</span>
 </div>
 <div class="wallet-mini__bar">
 <div class="wallet-mini__fill" :style="{ width: walletFill }"></div>
 </div>
 </div>
 </div>

 </div>

 <!-- Recommended for you (clients only) -->
 <div v-if="auth.activeRole === 'client'" class="panel reco-panel">
 <div class="panel__header">
 <h3 class="panel__title"> مقترح لك</h3>
 <RouterLink to="/services" class="panel__link">تصفح الكل ←</RouterLink>
 </div>
 <div v-if="loadingReco" class="panel__loading"><div class="mini-spinner"></div></div>
 <div v-else-if="recommendations.length === 0" class="panel__empty">
 <p>ما في اقتراحات كافية بعد — جرب تستعرض بعض الخدمات</p>
 </div>
 <div v-else class="reco-grid">
 <RouterLink v-for="s in recommendations" :key="s.id" :to="`/services/${s.id}`" class="reco-card">
 <div class="reco-card__thumb">
 <img v-if="s.thumbnail_url" :src="s.thumbnail_url" :alt="s.title" />
 <span v-else></span>
 </div>
 <div class="reco-card__body">
 <span class="reco-card__title">{{ s.title }}</span>
 <span class="reco-card__price">${{ s.dynamic_price }}</span>
 </div>
 </RouterLink>
 </div>
 </div>

 </div>
</template>

<script setup> import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useOrdersStore } from '@/stores/orders'
import { useWalletStore } from '@/stores/wallet'
import api from '@/composables/useApi'

const auth = useAuthStore()
const ordersStore = useOrdersStore()
const walletStore = useWalletStore()

// التعديل: أصبحنا نأخذ الدور الفعّال مباشرة من الـ store المحفوظ في المتصفح
const currentRole = computed(() => auth.activeRole)

const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')
const roleLabel = computed(() => currentRole.value === 'client' ? 'وضع العميل' : 'وضع المستقل')

const dashStats = ref(null)
const statsExpanded = ref(false)
async function fetchDashboardStats() {
 try {
 const prefix = currentRole.value === 'seller' ? 'seller' : 'client'
 const { data } = await api.get(`/${prefix}/dashboard-stats`)
 dashStats.value = data.data
 } catch { dashStats.value = null }
}

const stats = computed(() => currentRole.value === 'client' ? [
 { label: 'إجمالي الطلبات', value: dashStats.value?.total_orders ?? '—' },
 { label: 'طلبات مكتملة', value: dashStats.value?.completed_orders ?? '—' },
 { label: 'طلبات جارية', value: dashStats.value?.active_orders ?? '—' },
 { label: 'إجمالي الإنفاق', value: dashStats.value ? `$${Number(dashStats.value.total_spent).toFixed(2)}` : '$—' },
] : [
 { label: 'طلبات واردة', value: dashStats.value?.total_orders ?? '—' },
 { label: 'متوسط التقييم', value: dashStats.value?.average_rating ?? '—' },
 { label: 'طلبات مكتملة', value: dashStats.value?.completed_orders ?? '—' },
 { label: 'إجمالي الأرباح', value: dashStats.value ? `$${Number(dashStats.value.total_earned).toFixed(2)}` : '$—' },
])

// التعديل الآمن لمنع انهيار الصفحة واختفائها عند الضغط على الرئيسية
const recentOrders = computed(() => {
 if (ordersStore?.orders && Array.isArray(ordersStore.orders)) {
 return ordersStore.orders.slice(0, 5)
 }
 return []
})

const walletFill = computed(() => {
 const b = parseFloat(walletStore.balance)
 return b > 0 ? `${Math.min((b / 1000) * 100, 100)}%` : '0%'
})

// Helpers
const statusMap = {
 pending: { label: 'معلق', icon: '' },
 in_progress: { label: 'جارٍ', icon: '' },
 delivered: { label: 'مسلَّم', icon: '' },
 completed: { label: 'مكتمل', icon: '' },
 cancelled: { label: 'ملغى', icon: '' },
 refunded: { label: 'مُسترَد', icon: '↩' },
}
const statusLabel = s => statusMap[s]?.label ?? s
const statusIcon = s => statusMap[s]?.icon ?? ''

function formatDate(iso) {
 if (!iso) return '—'
 return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { month: 'short', day: 'numeric' })
}


// دالة جلب الطلبات الذكية: تمنع إرسال طلب 403 للسيرفر كلياً
async function safeFetchOrders(role) {
 // إذا كان الوضع الحالي seller ولكن الحساب الحقيقي عميل client فقط، اطلب طلبات العميل مباشرة بدون إزعاج السيرفر
 const userRole = auth.user?.role
 const isSellerUser = userRole === 'seller' || auth.user?.is_seller

 if (role === 'seller' && !isSellerUser) {
 // جلب طلبات العميل لتجنب خطأ 403 في الكونسول
 await ordersStore.fetchMyOrders('client')
 } else {
 try {
 await ordersStore.fetchMyOrders(role)
 } catch (err) {
 if (err.response?.status === 403) {
 await ordersStore.fetchMyOrders('client')
 }
 }
 }
}

onMounted(async () => {
 if (currentRole.value) {
 await Promise.all([
 safeFetchOrders(currentRole.value),
 walletStore.fetchSummary(),
 fetchDashboardStats(),
 currentRole.value === 'client' ? fetchRecommendations() : Promise.resolve(),
 ])
 }
})

const recommendations = ref([])
const loadingReco = ref(false)
async function fetchRecommendations() {
 loadingReco.value = true
 try {
 const { data } = await api.get('/client/recommendations')
 recommendations.value = (data.data ?? []).slice(0, 6)
 } catch {
 recommendations.value = []
 } finally { loadingReco.value = false }
}

// مراقبة التغير الفعلي
watch(() => currentRole.value, role => {
 if (role) {
 safeFetchOrders(role)
 fetchDashboardStats()
 if (role === 'client') fetchRecommendations()
 }
})
</script>

<style scoped> .dash-home { display: flex; flex-direction: column; gap: 24px; }

/* Welcome card */
.welcome-card {
background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-xl);
 padding: 32px 36px;
 display: flex; align-items: center; gap: 24px;
 position: relative; overflow: hidden;

}
.welcome-orb {
 position: absolute; top: -60px; left: -60px;
 width: 280px; height: 280px;
 pointer-events: none;
}
.welcome-body { flex: 1; position: relative; z-index: 1; }
.welcome-sub { font-size: 12px; color: var(--color-primary); font-weight: 600; letter-spacing: 0.5px; margin-bottom: 6px; }
.welcome-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; margin-bottom: 10px; line-height: 1.3; }
.welcome-desc { font-size: 14px; color: var(--color-text-2); margin-bottom: 20px; line-height: 1.7; }
.welcome-cta {
 display: inline-flex; align-items: center; gap: 6px;
 padding: 10px 22px;
 background: var(--color-bg-card);
 border-radius: var(--radius-full);
 color: white; text-decoration: none;
 font-size: 14px; font-weight: 700;
 box-shadow: 0 0 20px rgba(99,102,241,0.35);
 transition: opacity 0.2s, transform 0.2s var(--ease-spring);
}
.welcome-cta:hover { opacity: 0.9; transform: translateY(-2px); }
.welcome-visual {
 position: relative; width: 100px; height: 100px;
 display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.vis-ring {
 position: absolute; border-radius: var(--radius-full);
 border: 1.5px solid rgba(99,102,241,0.25);
 animation: spin-slow 8s linear infinite;
}
.vis-ring--1 { width: 100%; height: 100%; }
.vis-ring--2 { width: 75%; height: 75%; animation-direction: reverse; animation-duration: 5s; }
@keyframes spin-slow { to { transform: rotate(360deg); } }
.vis-emoji { font-size: 38px; z-index: 1; }

/* Stats row */
.unified-stats { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:16px; overflow:hidden; }
.us-header { display:flex; align-items:center; justify-content:space-between; padding:22px 24px; cursor:pointer; }
.us-headline { display:flex; align-items:baseline; gap:12px; }
.us-headline__num { font-family:var(--font-display); font-size:32px; font-weight:900; color:var(--color-text); }
.us-headline__label { font-size:13px; color:var(--color-text-3); }
.us-toggle { background:var(--color-bg-2); border:1px solid var(--color-border); border-radius:999px; padding:8px 16px; font-size:12.5px; color:var(--color-primary); cursor:pointer; font-weight:600; }
.us-body { border-top:1px solid var(--color-border); display:flex; flex-direction:column; }
.us-row { display:flex; align-items:center; justify-content:space-between; padding:14px 24px; border-bottom:1px solid var(--color-border); }
.us-row:last-child { border-bottom:none; }
.us-row__label { font-size:13px; color:var(--color-text-2); }
.us-row__val { font-family:var(--font-display); font-size:16px; font-weight:800; color:var(--color-text); }
.expand-enter-active, .expand-leave-active { transition: all 0.2s ease; }
.expand-enter-from, .expand-leave-to { opacity: 0; max-height: 0; }
.expand-enter-to, .expand-leave-from { opacity: 1; max-height: 500px; }

/* Home grid */
.home-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }

/* Panel */
.panel {
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-xl); overflow: hidden;
}
.panel__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 22px; border-bottom: 1px solid var(--color-border); }
.panel__title { font-family: var(--font-display); font-size: 15px; font-weight: 700; }
.panel__link { font-size: 13px; color: var(--color-primary); text-decoration: none; font-weight: 600; }
.panel__link:hover { text-decoration: underline; }
.panel__loading { display: flex; justify-content: center; padding: 40px; }
.mini-spinner { width: 24px; height: 24px; border: 2px solid var(--color-border); border-top-color: var(--color-primary); border-radius: var(--radius-full); animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.panel__empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 40px; color: var(--color-text-3); font-size: 14px; }
.panel__empty span { font-size: 32px; }

.reco-panel { margin-top: 20px; }
.reco-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; padding: 16px; }
.reco-card { display: flex; flex-direction: column; border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; text-decoration: none; color: inherit; transition: transform .15s, border-color .15s; }
.reco-card:hover { transform: translateY(-2px); border-color: var(--color-primary); }
.reco-card__thumb { height: 90px; background: var(--color-bg-2); display: flex; align-items: center; justify-content: center; font-size: 24px; }
.reco-card__thumb img { width: 100%; height: 100%; object-fit: cover; }
.reco-card__body { padding: 10px; display: flex; flex-direction: column; gap: 4px; }
.reco-card__title { font-size: 12px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.reco-card__price { font-size: 12px; font-weight: 700; color: var(--color-primary); }

/* Orders list */
.orders-list { display: flex; flex-direction: column; }
.order-row {
 display: flex; align-items: center; gap: 14px;
 padding: 14px 22px;
 border-bottom: 1px solid var(--color-border);
 transition: background 0.15s;
}
.order-row:last-child { border-bottom: none; }
.order-row:hover { background: rgba(99,102,241,0.04); }
.order-row__icon { font-size: 20px; flex-shrink: 0; }
.order-row__body { flex: 1; min-width: 0; }
.order-row__title { font-size: 13px; font-weight: 600; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.order-row__meta { font-size: 11px; color: var(--color-text-3); margin-top: 2px; }
.order-row__right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.order-row__price { font-family: var(--font-display); font-size: 14px; font-weight: 700; color: var(--color-primary); }

/* Status badges */
.status-badge { padding: 2px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 600; }
.status-badge--pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.status-badge--in_progress { background: rgba(99,102,241,0.15); color: var(--color-primary); }
.status-badge--delivered { background: rgba(139,92,246,0.15); color: var(--color-primary-2); }
.status-badge--completed { background: rgba(16,185,129,0.15); color: var(--color-success); }
.status-badge--cancelled { background: rgba(239,68,68,0.12); color: var(--color-error); }
.status-badge--refunded { background: rgba(107,114,153,0.15); color: var(--color-text-3); }

/* Quick actions */
.quick-actions { display: flex; flex-direction: column; gap: 16px; }
.qa-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; }
.qa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.qa-card {
 display: flex; flex-direction: column; align-items: center; gap: 8px;
 padding: 18px 12px;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-lg);
 text-decoration: none; color: var(--color-text-2);
 font-size: 13px; font-weight: 500;
 transition: border-color 0.2s, background 0.2s, transform 0.2s var(--ease-spring);
 text-align: center;
}
.qa-card:hover { border-color: var(--color-border-hover); background: var(--color-bg-card-hover); transform: translateY(-2px); color: var(--color-text); }
.qa-card__icon { font-size: 24px; }

/* Wallet mini */
.wallet-mini {
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-lg);
 padding: 18px 20px;
}
.wallet-mini__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.wallet-mini__label { font-size: 13px; color: var(--color-text-3); }
.wallet-mini__link { font-size: 12px; color: var(--color-primary); text-decoration: none; font-weight: 600; }
.wallet-mini__link:hover { text-decoration: underline; }
.wallet-mini__amount { display: flex; align-items: baseline; gap: 4px; margin-bottom: 14px; }
.currency { font-size: 18px; color: var(--color-text-3); }
.amount { font-family: var(--font-display); font-size: 32px; font-weight: 900; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.wallet-mini__bar { height: 6px; background: var(--color-border); border-radius: var(--radius-full); overflow: hidden; }
.wallet-mini__fill { height: 100%; background: var(--grad-primary); border-radius: var(--radius-full); transition: width 0.8s var(--ease-smooth); }

@media (max-width: 1100px) {
 .stats-row { grid-template-columns: repeat(2, 1fr); }
 .home-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
 .stats-row { grid-template-columns: 1fr 1fr; }
 .welcome-card { flex-direction: column; }
 .welcome-visual { display: none; }
}
</style>