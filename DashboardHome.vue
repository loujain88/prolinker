<template>
  <div class="dash-home" dir="rtl">

    <!-- Welcome hero card -->
    <div class="welcome-card">
      <div class="welcome-orb" aria-hidden="true"></div>
      <div class="welcome-body">
        <p class="welcome-sub">{{ roleLabel }}</p>
        <h2 class="welcome-title">
          مرحباً، <span class="grad-text">{{ firstName }}</span> 👋
        </h2>
        <p class="welcome-desc">
          {{ activeRole === 'client'
            ? 'استعرض الخدمات المتاحة أو تابع طلباتك الحالية من هنا.'
            : 'راجع طلباتك الواردة وأدر خدماتك المنشورة.' }}
        </p>
        <RouterLink :to="activeRole === 'client' ? '/services' : '/dashboard/services'" class="welcome-cta">
          {{ activeRole === 'client' ? '🔍 استعرض الخدمات' : '🛠️ أدر خدماتي' }}
        </RouterLink>
      </div>
      <div class="welcome-visual" aria-hidden="true">
        <div class="vis-ring vis-ring--1"></div>
        <div class="vis-ring vis-ring--2"></div>
        <span class="vis-emoji">{{ activeRole === 'client' ? '🛍️' : '💼' }}</span>
      </div>
    </div>

    <!-- Stats row -->
    <div class="stats-row">
      <div v-for="stat in stats" :key="stat.label" class="stat-card">
        <div class="stat-card__icon" :style="{ background: stat.bg }">{{ stat.icon }}</div>
        <div class="stat-card__body">
          <div class="stat-card__num">{{ stat.value }}</div>
          <div class="stat-card__label">{{ stat.label }}</div>
        </div>
        <div class="stat-card__trend" :class="stat.trend > 0 ? 'up' : stat.trend < 0 ? 'down' : ''">
          <span v-if="stat.trend !== undefined">{{ stat.trend > 0 ? '↑' : '↓' }} {{ Math.abs(stat.trend) }}%</span>
        </div>
      </div>
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
          <span>📭</span>
          <p>لا توجد طلبات بعد</p>
        </div>
        <div v-else class="orders-list">
          <div v-for="order in recentOrders" :key="order.id" class="order-row">
            <div class="order-row__icon">{{ statusIcon(order.status) }}</div>
            <div class="order-row__body">
              <div class="order-row__title">{{ order.service?.title ?? `طلب #${order.id}` }}</div>
              <div class="order-row__meta">
                {{ activeRole === 'client' ? order.seller?.name : order.client?.name }}
                · {{ formatDate(order.created_at) }}
              </div>
            </div>
            <div class="order-row__right">
              <span class="status-badge" :class="`status-badge--${order.status}`">
                {{ statusLabel(order.status) }}
              </span>
              <div class="order-row__price">${{ order.final_price }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="quick-actions">
        <h3 class="qa-title">إجراءات سريعة</h3>
        <div class="qa-grid">
          <RouterLink v-for="action in quickActions" :key="action.label"
            :to="action.to" class="qa-card">
            <span class="qa-card__icon">{{ action.icon }}</span>
            <span class="qa-card__label">{{ action.label }}</span>
          </RouterLink>
        </div>

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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore }   from '@/stores/auth'
import { useOrdersStore } from '@/stores/orders'
import { useWalletStore } from '@/stores/wallet'

const props = defineProps({ activeRole: { type: String, default: 'client' } })

const auth        = useAuthStore()
const ordersStore = useOrdersStore()
const walletStore = useWalletStore()

const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')
const roleLabel = computed(() => props.activeRole === 'client' ? 'وضع العميل' : 'وضع المستقل')

// Stats — stub values; wire to real API endpoints as needed
const stats = computed(() => props.activeRole === 'client' ? [
  { icon: '📦', label: 'إجمالي الطلبات',    value: ordersStore.meta?.total ?? '—',  bg: 'rgba(99,102,241,0.15)',  trend: undefined },
  { icon: '✅', label: 'طلبات مكتملة',       value: '—',  bg: 'rgba(16,185,129,0.15)',  trend: undefined },
  { icon: '⏳', label: 'طلبات جارية',        value: '—',  bg: 'rgba(245,158,11,0.15)', trend: undefined },
  { icon: '💰', label: 'إجمالي الإنفاق',     value: `$—`, bg: 'rgba(139,92,246,0.15)', trend: undefined },
] : [
  { icon: '📥', label: 'طلبات واردة',        value: '—',  bg: 'rgba(99,102,241,0.15)',  trend: undefined },
  { icon: '⭐', label: 'متوسط التقييم',      value: auth.user?.seller?.average_rating ?? '—', bg: 'rgba(245,158,11,0.15)', trend: undefined },
  { icon: '🏆', label: 'طلبات مكتملة',       value: auth.user?.seller?.total_orders_completed ?? '—', bg: 'rgba(16,185,129,0.15)', trend: undefined },
  { icon: '💵', label: 'إجمالي الأرباح',     value: `$—`, bg: 'rgba(139,92,246,0.15)', trend: undefined },
])

const recentOrders = computed(() => ordersStore.orders.slice(0, 5))

const quickActions = computed(() => props.activeRole === 'client' ? [
  { icon: '🔍', label: 'استعرض الخدمات',  to: '/services' },
  { icon: '💳', label: 'شحن المحفظة',     to: '/dashboard/wallet' },
  { icon: '📦', label: 'طلباتي',           to: '/dashboard/orders' },
  { icon: '👤', label: 'ملفي الشخصي',     to: '/dashboard/profile' },
] : [
  { icon: '➕', label: 'خدمة جديدة',      to: '/dashboard/services' },
  { icon: '📥', label: 'الطلبات الواردة',  to: '/dashboard/orders' },
  { icon: '💸', label: 'سحب الأرباح',      to: '/dashboard/wallet' },
  { icon: '👤', label: 'ملفي الشخصي',     to: '/dashboard/profile' },
])

const walletFill = computed(() => {
  const b = parseFloat(walletStore.balance)
  return b > 0 ? `${Math.min((b / 1000) * 100, 100)}%` : '0%'
})

// Helpers
const statusMap = {
  pending:     { label: 'معلق',    icon: '⏳' },
  in_progress: { label: 'جارٍ',   icon: '🔄' },
  delivered:   { label: 'مسلَّم',  icon: '📬' },
  completed:   { label: 'مكتمل',  icon: '✅' },
  cancelled:   { label: 'ملغى',   icon: '❌' },
  refunded:    { label: 'مُسترَد', icon: '↩️' },
}
const statusLabel = s => statusMap[s]?.label ?? s
const statusIcon  = s => statusMap[s]?.icon  ?? '📦'

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' })
}

onMounted(async () => {
  await Promise.all([
    ordersStore.fetchMyOrders(props.activeRole),
    walletStore.fetchSummary(),
  ])
})

watch(() => props.activeRole, role => {
  ordersStore.fetchMyOrders(role)
})
</script>

<style scoped>
.dash-home { display: flex; flex-direction: column; gap: 24px; }

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
  background: radial-gradient(circle, rgba(99,102,241,0.15), transparent 70%);
  pointer-events: none;
}
.welcome-body { flex: 1; position: relative; z-index: 1; }
.welcome-sub  { font-size: 12px; color: var(--color-primary); font-weight: 600; letter-spacing: 0.5px; margin-bottom: 6px; }
.welcome-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; margin-bottom: 10px; line-height: 1.3; }
.welcome-desc  { font-size: 14px; color: var(--color-text-2); margin-bottom: 20px; line-height: 1.7; }
.welcome-cta {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 22px;
  background: var(--grad-primary);
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
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.stat-card {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 18px 20px;
  display: flex; align-items: center; gap: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.stat-card:hover { border-color: var(--color-border-hover); box-shadow: var(--shadow-glow); }
.stat-card__icon {
  width: 44px; height: 44px; border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.stat-card__body { flex: 1; }
.stat-card__num   { font-family: var(--font-display); font-size: 22px; font-weight: 800; color: var(--color-text); }
.stat-card__label { font-size: 12px; color: var(--color-text-3); margin-top: 2px; }
.stat-card__trend { font-size: 11px; font-weight: 600; }
.stat-card__trend.up   { color: var(--color-success); }
.stat-card__trend.down { color: var(--color-error); }

/* Home grid */
.home-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }

/* Panel */
.panel {
  background: var(--color-bg-card); border: 1px solid var(--color-border);
  border-radius: var(--radius-xl); overflow: hidden;
}
.panel__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 22px; border-bottom: 1px solid var(--color-border); }
.panel__title  { font-family: var(--font-display); font-size: 15px; font-weight: 700; }
.panel__link   { font-size: 13px; color: var(--color-primary); text-decoration: none; font-weight: 600; }
.panel__link:hover { text-decoration: underline; }
.panel__loading { display: flex; justify-content: center; padding: 40px; }
.mini-spinner  { width: 24px; height: 24px; border: 2px solid var(--color-border); border-top-color: var(--color-primary); border-radius: var(--radius-full); animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.panel__empty  { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 40px; color: var(--color-text-3); font-size: 14px; }
.panel__empty span { font-size: 32px; }

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
.order-row__meta  { font-size: 11px; color: var(--color-text-3); margin-top: 2px; }
.order-row__right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.order-row__price { font-family: var(--font-display); font-size: 14px; font-weight: 700; color: var(--color-primary); }

/* Status badges */
.status-badge { padding: 2px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 600; }
.status-badge--pending     { background: rgba(245,158,11,0.15); color: #F59E0B; }
.status-badge--in_progress { background: rgba(99,102,241,0.15); color: var(--color-primary); }
.status-badge--delivered   { background: rgba(139,92,246,0.15); color: var(--color-primary-2); }
.status-badge--completed   { background: rgba(16,185,129,0.15); color: var(--color-success); }
.status-badge--cancelled   { background: rgba(239,68,68,0.12); color: var(--color-error); }
.status-badge--refunded    { background: rgba(107,114,153,0.15); color: var(--color-text-3); }

/* Quick actions */
.quick-actions { display: flex; flex-direction: column; gap: 16px; }
.qa-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; }
.qa-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.qa-card  {
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
.wallet-mini__label  { font-size: 13px; color: var(--color-text-3); }
.wallet-mini__link   { font-size: 12px; color: var(--color-primary); text-decoration: none; font-weight: 600; }
.wallet-mini__link:hover { text-decoration: underline; }
.wallet-mini__amount { display: flex; align-items: baseline; gap: 4px; margin-bottom: 14px; }
.currency { font-size: 18px; color: var(--color-text-3); }
.amount   { font-family: var(--font-display); font-size: 32px; font-weight: 900; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.wallet-mini__bar  { height: 6px; background: var(--color-border); border-radius: var(--radius-full); overflow: hidden; }
.wallet-mini__fill { height: 100%; background: var(--grad-primary); border-radius: var(--radius-full); transition: width 0.8s var(--ease-smooth); }

@media (max-width: 1100px) {
  .stats-row  { grid-template-columns: repeat(2, 1fr); }
  .home-grid  { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .stats-row  { grid-template-columns: 1fr 1fr; }
  .welcome-card { flex-direction: column; }
  .welcome-visual { display: none; }
}
</style>
