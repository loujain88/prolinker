<template>
 <div class="admin-dashboard" dir="rtl">

 <div v-if="loading" class="stats-grid">
 <div v-for="i in 8" :key="i" class="sk-card">
 <div class="sk sk--icon"></div>
 <div><div class="sk sk--line" style="width:55%"></div><div class="sk sk--line" style="width:35%;margin-top:8px"></div></div>
 </div>
 </div>

 <template v-else>
 <!-- Pending-actions alert -->
 <div v-if="hasPending" class="alert-bar">
 <span></span>
 <span> لديك <strong>{{ stats.financials.pending_deposits }}</strong> إيداع
 و<strong>{{ stats.financials.pending_withdrawals }}</strong> سحب
 و<strong>{{ stats.disputes.open }}</strong> نزاع بانتظار المراجعة.
 </span>
 <RouterLink to="/admin/financials" class="alert-link">راجعها الآن ←</RouterLink>
 </div>

 <!-- Unified stats card -->
 <div class="unified-stats">
 <div class="us-header" @click="statsExpanded = !statsExpanded">
 <div class="us-headline">
 <span class="us-headline__num">{{ stats.users.total }}</span>
 <span class="us-headline__label">إجمالي المستخدمين على المنصة</span>
 </div>
 <button class="us-toggle" :class="{ open: statsExpanded }">{{ statsExpanded ? 'إخفاء التفاصيل' : 'عرض كل التفاصيل' }}</button>
 </div>

 <Transition name="expand">
 <div v-if="statsExpanded" class="us-body">
 <div class="us-row" v-for="row in kpiCards" :key="row.label">
 <span class="us-row__label">{{ row.label }}</span>
 <span class="us-row__val">{{ row.value }}</span>
 </div>
 </div>
 </Transition>
 </div>

 <!-- Bottom panels -->
 <div class="panels-row">

 <!-- Financial summary -->
 <div class="panel">
 <div class="panel-hdr"><h3> الملخص المالي</h3></div>
 <div class="fin-rows">
 <div class="fin-row" v-for="r in finRows" :key="r.label">
 <span class="fin-row__label">{{ r.label }}</span>
 <span class="fin-row__val" :style="{ color: r.color }"> ${{ Number(r.value ?? 0).toLocaleString('ar-EG-u-nu-latn', { minimumFractionDigits: 2 }) }}
 </span>
 </div>
 </div>
 </div>

 <!-- Orders breakdown -->
 <div class="panel">
 <div class="panel-hdr"><h3> حالة الطلبات</h3></div>
 <div class="breakdown-rows">
 <div class="brow" v-for="o in ordersBreakdown" :key="o.label">
 <div class="brow__left">
 <span class="brow__dot" :style="{ background: o.color }"></span>
 <span class="brow__label">{{ o.label }}</span>
 </div>
 <span class="brow__val">{{ o.value }}</span>
 </div>
 </div>
 </div>

 <!-- Pending actions -->
 <div class="panel panel--red">
 <div class="panel-hdr"><h3> إجراءات معلقة</h3></div>
 <div class="pending-rows">
 <RouterLink to="/admin/financials" class="prow">
 <span class="prow__icon"></span>
 <span class="prow__label">إيداعات معلقة</span>
 <span class="prow__count">{{ stats.financials.pending_deposits }}</span>
 </RouterLink>
 <RouterLink to="/admin/financials" class="prow">
 <span class="prow__icon"></span>
 <span class="prow__label">سحوبات معلقة</span>
 <span class="prow__count">{{ stats.financials.pending_withdrawals }}</span>
 </RouterLink>
 <RouterLink to="/admin/disputes" class="prow">
 <span class="prow__icon"></span>
 <span class="prow__label">نزاعات مفتوحة</span>
 <span class="prow__count">{{ stats.disputes.open }}</span>
 </RouterLink>
 <RouterLink to="/admin/services" class="prow">
 <span class="prow__icon"></span>
 <span class="prow__label">خدمات بانتظار مراجعة</span>
 <span class="prow__count">{{ stats.services.paused }}</span>
 </RouterLink>
 </div>
 </div>

 </div>
 </template>
 </div>
</template>

<script setup> import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import adminApi from '@/composables/useAdminApi'

const router = useRouter()

const stats = ref(null)
const loading = ref(true)

const hasPending = computed(() => (stats.value?.financials?.pending_deposits ?? 0) > 0 ||
 (stats.value?.financials?.pending_withdrawals ?? 0) > 0 ||
 (stats.value?.disputes?.open ?? 0) > 0
)

const statsExpanded = ref(false)

const kpiCards = computed(() => {
 if (!stats.value) return []
 const s = stats.value
 return [
 { label:'إجمالي المستخدمين', value: s.users.total, link:'/admin/users' },
 { label:'عملاء', value: s.users.clients, link:'/admin/users?role=client' },
 { label:'مستقلون', value: s.users.sellers, link:'/admin/users?role=seller' },
 { label:'حسابات محظورة', value: s.users.blocked, link:'/admin/users?status=blocked' },
 { label:'إجمالي الطلبات', value: s.orders.total, link:null },
 { label:'طلبات مكتملة', value: s.orders.completed, link:null },
 { label:'إيرادات المنصة', value: `$${Number(s.financials.platform_revenue_total).toFixed(2)}`, link:'/admin/financials' },
 { label:'ضمان محتجز', value: `$${Number(s.financials.total_escrow_held).toFixed(2)}`, link:'/admin/financials' },
 ]
})

const finRows = computed(() => {
 if (!stats.value) return []
 const f = stats.value.financials
 return [
 { label:'إجمالي الإيداعات المعتمدة', value: f.total_deposits_approved, color:'#10B981' },
 { label:'إجمالي السحوبات المعتمدة', value: f.total_withdrawals_approved, color:'#EF4444' },
 { label:'إيرادات المنصة (الكلية)', value: f.platform_revenue_total, color:'#F59E0B' },
 { label:'إيرادات هذا الشهر', value: f.platform_revenue_month, color:'#F59E0B' },
 { label:'ضمان محتجز حالياً', value: f.total_escrow_held, color:'#6366F1' },
 ]
})

const ordersBreakdown = computed(() => {
 if (!stats.value) return []
 const o = stats.value.orders
 return [
 { label:'معلقة', value: o.pending, color:'#F59E0B' },
 { label:'جارية', value: o.in_progress, color:'#6366F1' },
 { label:'مكتملة', value: o.completed, color:'#10B981' },
 { label:'ملغاة', value: o.cancelled, color:'#EF4444' },
 { label:'منازعات',value: o.disputed, color:'#F97316' },
 ]
})

onMounted(async () => {
 try { const { data } = await adminApi.getStats(); stats.value = data }
 catch (e) { console.error(e) }
 finally { loading.value = false }
})
</script>

<style scoped> .admin-dashboard { display:flex; flex-direction:column; gap:22px; }

.alert-bar { display:flex; align-items:center; gap:12px; padding:13px 20px; background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.25); border-radius:14px; font-size:14px; color:var(--color-text-2); }
.alert-link { margin-right:auto; color:var(--color-gold); text-decoration:none; font-weight:700; white-space:nowrap; }

.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.unified-stats { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:16px; overflow:hidden; }
.us-header { display:flex; align-items:center; justify-content:space-between; padding:22px 24px; cursor:pointer; }
.us-headline { display:flex; align-items:baseline; gap:12px; }
.us-headline__num { font-family:var(--font-display); font-size:32px; font-weight:900; color:var(--color-text); }
.us-headline__label { font-size:13px; color:var(--color-text-3); }
.us-toggle { background:var(--color-bg-2); border:1px solid var(--color-border); border-radius:999px; padding:8px 16px; font-size:12.5px; color:var(--color-primary); cursor:pointer; font-weight:600; }
.us-body { border-top:1px solid var(--color-border); display:flex; flex-direction:column; }
.us-row { display:flex; align-items:center; justify-content:space-between; padding:14px 24px; border-bottom:1px solid var(--color-border); }
.us-row:last-child { border-bottom:none; }
.us-row--link { cursor:pointer; transition:background .15s; }
.us-row--link:hover { background:var(--color-bg-2); }
.us-row__label { font-size:13px; color:var(--color-text-2); }
.us-row__val { font-family:var(--font-display); font-size:16px; font-weight:800; color:var(--color-text); }
.expand-enter-active, .expand-leave-active { transition: all 0.2s ease; }
.expand-enter-from, .expand-leave-to { opacity: 0; max-height: 0; }
.expand-enter-to, .expand-leave-from { opacity: 1; max-height: 500px; }

/* Skeleton */
.sk-card { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:16px; padding:18px 20px; display:flex; align-items:center; gap:14px; }
.sk { background:linear-gradient(90deg,var(--color-bg-card) 25%,var(--color-bg-card-hover) 50%,var(--color-bg-card) 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:6px; }
@keyframes shimmer { to { background-position:-200% 0; } }
.sk--icon { width:40px; height:40px; border-radius:10px; flex-shrink:0; }
.sk--line { height:12px; }

/* Panels row */
.panels-row { display:grid; grid-template-columns:1fr 1fr 290px; gap:16px; }
.panel { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; overflow:hidden; }
.panel--red { border-color:rgba(239,68,68,.2); }
.panel-hdr { padding:16px 20px; border-bottom:1px solid var(--color-border); }
.panel-hdr h3 { font-family:var(--font-display); font-size:15px; font-weight:700; }

.fin-rows { display:flex; flex-direction:column; }
.fin-row { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-bottom:1px solid var(--color-border); }
.fin-row:last-child { border-bottom:none; }
.fin-row__label { font-size:13px; color:var(--color-text-2); }
.fin-row__val { font-family:var(--font-display); font-size:14px; font-weight:800; }

.breakdown-rows { display:flex; flex-direction:column; }
.brow { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; }
.brow__left { display:flex; align-items:center; gap:10px; }
.brow__dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.brow__label { font-size:13px; color:var(--color-text-2); }
.brow__val { font-family:var(--font-display); font-size:16px; font-weight:800; color:var(--color-text); }

.pending-rows { display:flex; flex-direction:column; }
.prow { display:flex; align-items:center; gap:12px; padding:13px 18px; text-decoration:none; color:var(--color-text-2); border-bottom:1px solid var(--color-border); transition:background .15s; }
.prow:last-child { border-bottom:none; }
.prow:hover { background:rgba(239,68,68,.05); color:var(--color-text); }
.prow__icon { font-size:20px; flex-shrink:0; }
.prow__label { flex:1; font-size:13px; }
.prow__count { font-family:var(--font-display); font-size:18px; font-weight:800; color:#EF4444; }

@media (max-width:1200px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:900px) { .panels-row { grid-template-columns:1fr; } }
@media (max-width:640px) { .stats-grid { grid-template-columns:1fr 1fr; } }
</style>
