<template>
 <div class="orders-page" dir="rtl">

 <!-- Header -->
 <div class="page-header">
 <div>
 <h2 class="page-title">{{ activeRole === 'client' ? ' طلباتي' : ' الطلبات الواردة' }}</h2>
 <p class="page-sub">{{ activeRole === 'client' ? 'تتبع جميع طلباتك المرسلة' : 'أدر الطلبات الواردة من العملاء' }}</p>
 </div>
 <RouterLink v-if="activeRole === 'client'" to="/services" class="new-order-btn"> طلب جديد
 </RouterLink>
 </div>

 <!-- Status filter tabs -->
 <div class="status-tabs" role="tablist">
 <button
 v-for="tab in statusTabs"
 :key="tab.key"
 class="status-tab"
 :class="{ active: activeStatus === tab.key }"
 role="tab" :aria-selected="activeStatus === tab.key"
 @click="changeStatus(tab.key)"
 >
 <span class="tab-icon">{{ tab?.icon }}</span> {{ tab?.label }}
 </button>
 </div>

 <!-- Loading -->
 <div v-if="ordersStore?.loading" class="loading-state">
 <div v-for="i in 4" :key="i" class="order-skeleton">
 <div class="skeleton sk-icon"></div>
 <div class="sk-body">
 <div class="skeleton sk-line" style="width:55%"></div>
 <div class="skeleton sk-line" style="width:35%"></div>
 </div>
 <div class="skeleton sk-badge"></div>
 </div>
 </div>

 <!-- Empty -->
 <div v-else-if="!ordersStore?.orders || ordersStore.orders.length === 0" class="empty-state">
 <!-- تم إصلاح السطر 158 المكسور بحمايته هنا لمنع الانهيار القاتل واختفاء الشاشة -->
 <span class="empty-icon">{{ activeStatus === 'all' ? '' : (statusTabs.find(t => t.key === activeStatus)?.icon ?? '') }}</span>
 <h3>لا توجد طلبات</h3>
 <p>{{ activeRole === 'client' ? 'ابدأ باستعراض الخدمات وتقديم طلبك الأول' : 'ستظهر الطلبات هنا بمجرد قيام العملاء بالشراء' }}</p>
 <RouterLink v-if="activeRole === 'client'" to="/services" class="empty-cta">استعرض الخدمات</RouterLink>
 </div>

 <!-- Orders list -->
 <TransitionGroup v-else name="order-list" tag="div" class="orders-list">
 <div
 v-for="order in ordersStore.orders"
 :key="order?.id"
 :id="`order-${order?.id}`"
 class="order-card"
 :class="{ 'order-card--urgent': isUrgent(order), 'order-card--highlight': order?.id === highlightId }"
 >
 <!-- Card header -->
 <div class="order-card__header">
 <div class="order-card__service">
 <div class="order-service-icon">{{ categoryEmoji(order?.service?.category) }}</div>
 <div>
 <div class="order-service-title">{{ order?.service?.title ?? `طلب #${order?.id}` }}</div>
 <div class="order-service-meta"> {{ activeRole === 'client' ? `المستقل: ${order?.seller?.name ?? 'غير معروف'}` : `العميل: ${order?.client?.name ?? 'غير معروف'}` }}
 · <span class="order-id">#{{ order?.id }}</span>
 </div>
 </div>
 </div>
 <div class="order-card__badges">
 <span v-if="order?.is_urgent || isUrgent(order)" class="urgent-badge"> عاجل</span>
 <span v-if="order?.has_dispute" class="dispute-badge"> نزاع</span>
 <span class="status-pill" :class="`status--${order?.status}`"> {{ statusIcon(order?.status) }} {{ statusLabel(order?.status) }}
 </span>
 </div>
 </div>

 <!-- Card body -->
 <div class="order-card__body">
 <div class="order-meta-grid">
 <div class="meta-item">
 <span class="meta-label">المبلغ</span>
 <span class="meta-value meta-value--price">${{ order?.final_price }}</span>
 </div>
 <div class="meta-item">
 <span class="meta-label">الضمان المحتجز</span>
 <span class="meta-value">${{ order?.escrow_amount }}</span>
 </div>
 <div class="meta-item">
 <span class="meta-label">الموعد النهائي</span>
 <span class="meta-value" :class="{ overdue: isOverdue(order?.deadline_at) }"> {{ formatDate(order?.deadline_at) }}
 </span>
 </div>
 <div class="meta-item" v-if="order?.rating">
 <span class="meta-label">التقييم</span>
 <span class="meta-value">{{ ''.repeat(order.rating) }}{{ ''.repeat(5-order.rating) }}</span>
 </div>
 </div>

 <!-- Order details preview -->
 <div v-if="order?.order_details?.requirements" class="order-requirements">
 <span class="req-label">متطلبات العميل:</span>
 <span class="req-text">{{ truncate(order.order_details.requirements, 120) }}</span>
 </div>

 <div v-if="order?.status === 'pending' && order?.deadline_at" class="order-deadline-hint"> ⏱ العميل طلب التسليم خلال <strong>{{ daysUntil(order.deadline_at) }}</strong> يوم (بحلول {{ formatDate(order.deadline_at) }})
 </div>
 </div>

 <!-- Card footer: action buttons -->
 <div class="order-card__footer">
 <span class="order-date">{{ timeAgo(order?.created_at) }}</span>
 <div class="order-actions">

 <!-- CLIENT actions -->
 <template v-if="activeRole === 'client'">
 <button
 v-if="order?.status === 'delivered' && !order?.has_dispute"
 class="action-btn action-btn--success"
 @click="openCompleteModal(order)"
 > قبول التسليم</button>

 <button
 v-if="order?.status === 'delivered' && !order?.has_dispute"
 class="action-btn action-btn--warning"
 @click="openDisputeModal(order)"
 > فتح نزاع</button>

 <button
 v-if="order?.status === 'pending'"
 class="action-btn action-btn--danger"
 @click="handleCancel(order)"
 :disabled="actionLoading === order?.id"
 > إلغاء</button>
 </template>

 <!-- SELLER actions -->
 <template v-if="activeRole === 'seller'">
 <button
 v-if="order?.status === 'pending'"
 class="action-btn action-btn--primary"
 @click="handleAccept(order)"
 :disabled="actionLoading === order?.id"
 > قبول الطلب</button>

 <button
 v-if="order?.status === 'pending'"
 class="action-btn action-btn--danger"
 @click="handleReject(order)"
 :disabled="actionLoading === order?.id"
 > رفض الطلب</button>

 <button
 v-if="order?.status === 'in_progress'"
 class="action-btn action-btn--success"
 @click="router.push(`/dashboard/orders/${order.id}/deliver`)"
 > تسليم العمل</button>
 </template>

 <button class="action-btn action-btn--ghost" @click="viewOrder(order)"> التفاصيل
 </button>
 </div>
 </div>
 </div>
 </TransitionGroup>

 <!-- Pagination -->
 <div v-if="ordersStore?.meta?.last_page > 1" class="pagination">
 <button
 class="page-btn"
 :disabled="ordersStore?.meta?.current_page <= 1"
 @click="changePage(ordersStore.meta.current_page - 1)"
 >← السابق</button>
 <span class="page-info"> صفحة {{ ordersStore?.meta?.current_page }} من {{ ordersStore?.meta?.last_page }}
 </span>
 <button
 class="page-btn"
 :disabled="ordersStore?.meta?.current_page >= ordersStore?.meta?.last_page"
 @click="changePage(ordersStore.meta.current_page + 1)"
 >التالي →</button>
 </div>

 <!-- ═══════ COMPLETE ORDER MODAL ═══════ -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="completeModal.open" class="modal-overlay" @mousedown.self="completeModal.open = false">
 <div class="modal">
 <div class="modal__header">
 <h2 class="modal__title"> قبول التسليم وإغلاق الطلب</h2>
 <button class="modal__close" @click="completeModal.open = false">✕</button>
 </div>
 <div class="modal__body">
 <div class="complete-info">
 <p>بمجرد قبولك، سيُحوَّل المبلغ <strong class="grad-text">${{ completeModal.order?.final_price }}</strong> إلى محفظة المستقل.</p>
 <p class="complete-info__sub">هذا الإجراء لا يمكن التراجع عنه.</p>
 </div>
 <!-- Star rating -->
 <div class="rating-section">
 <label class="field-label">قيّم تجربتك (اختياري)</label>
 <div class="star-rating" role="group" aria-label="التقييم">
 <button
 v-for="star in 5" :key="star"
 type="button"
 class="star-btn"
 :class="{ filled: star <= (hoverStar || completeModal.rating) }"
 @mouseenter="hoverStar = star"
 @mouseleave="hoverStar = 0"
 @click="completeModal.rating = star"
 :aria-label="`${star} نجمة`"
 >
 <svg width="24" height="24" viewBox="0 0 24 24" :fill="star <= (hoverStar || completeModal.rating) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
 </button>
 </div>
 <span class="rating-hint" v-if="completeModal.rating"> {{ ratingHints[completeModal.rating - 1] }}
 </span>
 </div>
 <!-- Review text -->
 <div class="field">
 <label class="field-label">اكتب مراجعتك (اختياري)</label>
 <textarea
 v-model="completeModal.reviewText"
 class="field-textarea"
 placeholder="شاركنا تجربتك مع هذا مستقل..."
 rows="3"
 ></textarea>
 </div>
 </div>
 <div class="modal__footer">
 <button class="modal-btn modal-btn--ghost" @click="completeModal.open = false">إلغاء</button>
 <button class="modal-btn modal-btn--success" @click="submitComplete" :disabled="actionLoading">
 <span v-if="actionLoading" class="inline-spinner"></span> {{ actionLoading ? 'جاري الإغلاق...' : 'تأكيد القبول وإغلاق الطلب' }}
 </button>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <!-- ═══════ DISPUTE MODAL ═══════ -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="disputeModal.open" class="modal-overlay" @mousedown.self="disputeModal.open = false">
 <div class="modal">
 <div class="modal__header">
 <h2 class="modal__title"> فتح نزاع</h2>
 <button class="modal__close" @click="disputeModal.open = false">✕</button>
 </div>
 <div class="modal__body">
 <div class="dispute-warning">
 <span></span>
 <p>سيتوقف المبلغ في الضمان حتى يبت فريق الإدارة في النزاع. يرجى توضيح سبب عدم رضاك.</p>
 </div>
 <div class="field" :class="{ 'field--error': disputeModal.error }">
 <label class="field-label">سبب النزاع <span class="required">*</span></label>
 <textarea
 v-model="disputeModal.reason"
 class="field-textarea"
 placeholder="اشرح بالتفصيل لماذا العمل المُسلَّم لا يستوفي المتطلبات..."
 rows="5"
 @input="disputeModal.error = ''"
 ></textarea>
 <span v-if="disputeModal.error" class="field-error">{{ disputeModal.error }}</span>
 </div>
 </div>
 <div class="modal__footer">
 <button class="modal-btn modal-btn--ghost" @click="disputeModal.open = false">إلغاء</button>
 <button class="modal-btn modal-btn--danger" @click="submitDispute" :disabled="actionLoading">
 <span v-if="actionLoading" class="inline-spinner"></span> {{ actionLoading ? 'جاري الإرسال...' : 'تقديم النزاع' }}
 </button>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <!-- Toast -->
 <Transition name="toast">
 <div v-if="toastMsg" class="toast" :class="`toast--${toastType}`"> {{ toastMsg }}
 </div>
 </Transition>

 </div>
</template>

<script setup> import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useOrdersStore } from '@/stores/orders'
import api from '@/composables/useApi'

const props = defineProps({ activeRole: { type: String, default: 'client' } })

const ordersStore = useOrdersStore()
const router = useRouter()
const route = useRoute()
const highlightId = ref(route.query.highlight ? Number(route.query.highlight) : null)
const activeStatus = ref('all')
const actionLoading = ref(null)
const toastMsg = ref('')
const toastType = ref('success')
const hoverStar = ref(0)

// Modals
const completeModal = ref({ open: false, order: null, rating: 0, reviewText: '' })
const disputeModal = ref({ open: false, order: null, reason: '', error: '' })

// Status tabs
const statusTabs = [
 { key: 'all', icon: '', label: 'الكل' },
 { key: 'pending', icon: '', label: 'معلق' },
 { key: 'in_progress', icon: '', label: 'جارٍ' },
 { key: 'delivered', icon: '', label:' مسلم' },
 { key: 'completed', icon: '', label: 'مكتمل' },
 { key: 'cancelled', icon: '', label: 'ملغى' },
]

const ratingHints = ['سيئ جداً', 'سيئ', 'مقبول', 'جيد', 'ممتاز ']

function changeStatus(status) {
 activeStatus.value = status
 ordersStore.fetchMyOrders(props.activeRole, status === 'all' ? '' : status)
}

function changePage(page) {
 ordersStore.fetchMyOrders(props.activeRole, activeStatus.value === 'all' ? '' : activeStatus.value, page)
}

// التعديل الجديد والمحمي لمنع اختفاء الشاشة والانهيار
function viewOrder(order) {
 if (!order || !order.id) {
 showToast('بيانات هذا الطلب غير مكتملة حالياً ', 'error')
 return
 }
 router.push(`/dashboard/orders/${order.id}`)
}

// Actions
async function handleReject(order) {
 if (!order || !order.id) return
 if (!confirm('متأكد إنك بدك ترفض هذا الطلب؟ رح يترد المبلغ كامل للعميل.')) return
 actionLoading.value = order.id
 try {
 await ordersStore.rejectOrder(order.id)
 showToast('تم رفض الطلب، وتم رد المبلغ للعميل', 'success')
 ordersStore.fetchMyOrders(props.activeRole, '')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
 } finally { actionLoading.value = null }
}

async function handleAccept(order) {
 if (!order || !order.id) return
 actionLoading.value = order.id
 try {
 await ordersStore.acceptOrder(order.id)
 showToast('تم قبول الطلب بنجاح ', 'success')
 ordersStore.fetchMyOrders(props.activeRole, '')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
 } finally { actionLoading.value = null }
}

async function handleDeliver(order) {
 if (!order || !order.id) return
 actionLoading.value = order.id
 try {
 await ordersStore.deliverOrder(order.id)
 showToast('تم تسليم العمل بنجاح، في انتظار موافقة العميل ', 'success')
 ordersStore.fetchMyOrders(props.activeRole, '')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
 } finally { actionLoading.value = null }
}

async function handleCancel(order) {
 if (!order || !order.id) return
 if (!confirm('هل أنت متأكد من إلغاء هذا الطلب؟ سيُعاد المبلغ إلى محفظتك.')) return
 actionLoading.value = order.id
 try {
 await ordersStore.cancelOrder(order.id, 'إلغاء من قِبل العميل')
 showToast('تم إلغاء الطلب وإعادة المبلغ إلى محفظتك ↩', 'success')
 ordersStore.fetchMyOrders(props.activeRole, '')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
 } finally { actionLoading.value = null }
}

function openCompleteModal(order) {
 completeModal.value = { open: true, order, rating: 0, reviewText: '' }
 hoverStar.value = 0
}

async function submitComplete() {
 if (!completeModal.value.order?.id) return
 actionLoading.value = completeModal.value.order.id
 try {
 await ordersStore.completeOrder(completeModal.value.order.id, {
 rating: completeModal.value.rating || null,
 review_text: completeModal.value.reviewText || null,
 })
 completeModal.value.open = false
 showToast('تم قبول التسليم وتحويل المبلغ للمستقل ', 'success')
 ordersStore.fetchMyOrders(props.activeRole, '')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
 } finally { actionLoading.value = null }
}

function openDisputeModal(order) {
 disputeModal.value = { open: true, order, reason: '', error: '' }
}

async function submitDispute() {
 if (!disputeModal.value.order?.id) return
 if (disputeModal.value.reason.trim().length < 20) {
 disputeModal.value.error = 'يجب كتابة سبب واضح (20 حرفاً على الأقل)'
 return
 }
 actionLoading.value = disputeModal.value.order.id
 try {
 await api.post(`/client/orders/${disputeModal.value.order.id}/dispute`, {
 reason: disputeModal.value.reason,
 })
 disputeModal.value.open = false
 showToast('تم فتح النزاع، ستتواصل معك الإدارة قريباً ', 'success')
 ordersStore.fetchMyOrders(props.activeRole, '')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
 } finally { actionLoading.value = null }
}

// Helpers
const statusMap = {
 pending: { label: 'معلق', icon: '⏳' },
 in_progress: { label: 'جارٍ', icon: '' },
 delivered: { label: 'مسلم', icon: '' },
 completed: { label: 'مكتمل', icon: '' },
 cancelled: { label: 'ملغى', icon: '' },
 refunded: { label: 'مُسترَد', icon: '↩' },
}
const statusLabel = s => statusMap[s]?.label ?? s
const statusIcon = s => statusMap[s]?.icon ?? ''

function isUrgent(order) {
 if (!order || !order.deadline_at || order.status === 'completed' || order.status === 'cancelled') return false
 const diff = new Date(order.deadline_at) - Date.now()
 return diff > 0 && diff < 48 * 60 * 60 * 1000
}
function isOverdue(dateStr) {
 return dateStr && new Date(dateStr) < Date.now()
}
function formatDate(iso) {
 if (!iso) return '—'
 return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function daysUntil(iso) {
 if (!iso) return '—'
 const diff = new Date(iso) - Date.now()
 return Math.max(1, Math.round(diff / (24 * 60 * 60 * 1000)))
}
function timeAgo(iso) {
 if (!iso) return '—'
 const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
 if (diff < 60) return 'منذ لحظات'
 if (diff < 3600) return `منذ ${Math.floor(diff/60)} دقيقة`
 if (diff < 86400) return `منذ ${Math.floor(diff/3600)} ساعة`
 return `منذ ${Math.floor(diff/86400)} يوم`
}
function truncate(str, len) {
 return str?.length > len ? str.slice(0, len) + '...' : str
}
function categoryEmoji(cat) {
 const m = { 'تطوير الويب':'','تصميم الجرافيك':'','التسويق الرقمي':'','كتابة المحتوى':'','الترجمة':'','الفيديو والموشن':'' }
 return m[cat] ?? ''
}

let toastTimer = null
function showToast(msg, type = 'success') {
 toastMsg.value = msg
 toastType.value = type
 clearTimeout(toastTimer)
 toastTimer = setTimeout(() => { toastMsg.value = '' }, 4500)
}

onMounted(() => {
 if (props.activeRole) {
 ordersStore.fetchMyOrders(props.activeRole).then(() => {
 if (highlightId.value) {
 setTimeout(() => {
 document.getElementById(`order-${highlightId.value}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
 }, 150)
 setTimeout(() => { highlightId.value = null }, 4000)
 }
 })
 }
})

watch(() => props.activeRole, r => { 
 activeStatus.value = 'all'
 if (r) ordersStore.fetchMyOrders(r) 
})
</script>

<style scoped> .orders-page { display: flex; flex-direction: column; gap: 22px; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-title { font-family: var(--font-display); font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.page-sub { font-size: 14px; color: var(--color-text-3); }
.new-order-btn {
 display: inline-flex; align-items: center; gap: 6px;
 padding: 10px 22px; border-radius: var(--radius-full);
 background: rgb(80, 109, 190); color: white; text-decoration: none;
 font-size: 14px; font-weight: 700;
 box-shadow: 0 0 20px rgba(99,102,241,0.35);
 transition: opacity 0.2s, transform 0.2s var(--ease-spring);
 white-space: nowrap;
}
.new-order-btn:hover { opacity: 0.9; transform: translateY(-2px); }

/* Status tabs */
.status-tabs {
 display: flex; gap: 4px; flex-wrap: wrap;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-lg);
 padding: 4px; width: fit-content;
}
.status-tab {
 display: flex; align-items: center; gap: 5px;
 padding: 8px 16px; border: none; border-radius: var(--radius-md);
 background: none; cursor: pointer;
 font-family: var(--font-body); font-size: 13px; font-weight: 500; color: var(--color-text-3);
 transition: background 0.2s, color 0.2s;
 white-space: nowrap;
}
.status-tab:hover { background: rgba(99,102,241,0.07); color: var(--color-text); }
.status-tab.active { background: rgb(80, 109, 190); color: white; box-shadow: 0 2px 10px rgba(99,102,241,0.35); }
.tab-icon { font-size: 14px; }

/* Loading skeletons */
.loading-state { display: flex; flex-direction: column; gap: 12px; }
.order-skeleton { display: flex; align-items: center; gap: 16px; padding: 20px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); }
.skeleton { background: linear-gradient(90deg, var(--color-bg-card) 25%, var(--color-bg-card-hover) 50%, var(--color-bg-card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: var(--radius-sm); }
@keyframes shimmer { to { background-position: -200% 0; } }
.sk-icon { width: 44px; height: 44px; border-radius: var(--radius-md); flex-shrink: 0; }
.sk-body { flex: 1; display: flex; flex-direction: column; gap: 10px; }
.sk-line { height: 12px; }
.sk-badge { width: 80px; height: 28px; border-radius: var(--radius-full); }

/* Empty */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 70px 24px; text-align: center; }
.empty-icon { font-size: 60px; }
.empty-state h3 { font-family: var(--font-display); font-size: 20px; font-weight: 700; }
.empty-state p { font-size: 14px; color: var(--color-text-3); }
.empty-cta {
 margin-top: 8px; padding: 10px 28px; border-radius: var(--radius-full);
 background: rgb(80, 109, 190); color: white; text-decoration: none;
 font-size: 14px; font-weight: 700; transition: opacity 0.2s;
}
.empty-cta:hover { opacity: 0.9; }

/* Order cards */
.orders-list { display: flex; flex-direction: column; gap: 14px; }
.order-card {
 background: var(--color-bg-card); border: 1px solid var(--color-border);
 border-radius: var(--radius-xl); overflow: hidden;
 transition: border-color 0.2s, box-shadow 0.2s;
}
.order-card:hover { border-color: var(--color-border-hover); box-shadow: var(--shadow-glow); }
.order-card--urgent { border-color: rgba(245,158,11,0.4); }
.order-card--urgent:hover { box-shadow: 0 0 24px rgba(245,158,11,0.2); }
.order-card--highlight { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.25); animation: highlight-pulse 2s ease-in-out 2; }
@keyframes highlight-pulse { 0%, 100% { box-shadow: 0 0 0 3px rgba(99,102,241,0.25); } 50% { box-shadow: 0 0 0 6px rgba(99,102,241,0.1); } }

/* Card header */
.order-card__header {
 display: flex; align-items: flex-start; justify-content: space-between;
 gap: 16px; padding: 18px 22px;
 border-bottom: 1px solid var(--color-border);
}
.order-card__service { display: flex; align-items: flex-start; gap: 14px; flex: 1; min-width: 0; }
.order-service-icon { font-size: 28px; flex-shrink: 0; line-height: 1; margin-top: 2px; }
.order-service-title { font-size: 15px; font-weight: 700; color: var(--color-text); margin-bottom: 4px; }
.order-service-meta { font-size: 12px; color: var(--color-text-3); }
.order-id { color: var(--color-primary); }
.order-card__badges { display: flex; align-items: center; gap: 6px; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }
.urgent-badge { padding: 3px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700; background: rgba(245,158,11,0.15); color: var(--color-gold); }
.dispute-badge { padding: 3px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700; background: rgba(239,68,68,0.12); color: var(--color-error); }

/* Status pill */
.status-pill { padding: 4px 12px; border-radius: var(--radius-full); font-size: 12px; font-weight: 600;  }
.status--pending { background: rgba(245,158,11,0.15); color: #5f5647; }
.status--in_progress { color: rgb(167, 117, 24); }
.status--delivered {  color: rgb(90, 85, 85); }
.status--completed { color: green; }
.status--cancelled {  color: rgb(202, 76, 76); }
.status--refunded { background: rgba(107,114,153,0.15); color: var(--color-text-3); }

/* Card body */
.order-card__body { padding: 16px 22px; display: flex; flex-direction: column; gap: 12px; }
.order-meta-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.meta-item { display: flex; flex-direction: column; gap: 3px; }
.meta-label { font-size: 11px; color: var(--color-text-3); }
.meta-value { font-size: 14px; font-weight: 600; color: var(--color-text); }
.meta-value--price { color: white; font-family: var(--font-display); font-size: 16px; font-weight: 800; }
.meta-value.overdue { color: var(--color-error); }
.order-requirements { font-size: 13px; color: var(--color-text-2); background: rgb(80, 109, 190); border-radius: var(--radius-md); padding: 10px 14px; line-height: 1.6; }
.order-deadline-hint { font-size: 12.5px; color: var(--color-text-2); background: rgb(80, 109, 190,0.50); border: 1px solid; border-radius: var(--radius-md); padding: 8px 14px; margin-top: 8px; }
.req-label { font-weight: 600; color: var(--color-text-3); margin-left: 6px; }

/* Card footer */
.order-card__footer {
 display: flex; align-items: center; justify-content: space-between;
 padding: 14px 22px; border-top: 1px solid var(--color-border);
 background: rgba(0,0,0,0.1);
}
.order-date { font-size: 12px; color: var(--color-text-3); }
.order-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.action-btn {
 padding: 8px 18px; border-radius: var(--radius-full);
 border: none; cursor: pointer;
 font-family: var(--font-body); font-size: 13px; font-weight: 600;
 transition: opacity 0.2s, transform 0.15s var(--ease-spring);
 white-space: nowrap;
}
.action-btn:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.action-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.action-btn--primary { background:rgb(80, 109, 190) ; color: white; box-shadow: 0 0 16px rgba(99,102,241,0.3); }
.action-btn--success { background: white; color: var(--color-success); border: 1px solid rgba(16,185,129,0.3); }
.action-btn--warning { background: white; color: red; border: 1px solid ; }
.action-btn--danger { background: rgb(80, 109, 190); color: white; border: 1px solid rgba(239,68,68,0.25); }
.action-btn--ghost { background: rgb(80, 109, 190); color: white; border: 1px solid var(--color-border); }
.action-btn--ghost:hover:not(:disabled) { border-color: var(--color-border-hover); color: var(--color-text); }

/* Pagination */
.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; padding: 8px 0; }
.page-btn {
 padding: 9px 20px; border-radius: var(--radius-full);
 background: var(--color-bg-card); border: 1px solid var(--color-border);
 color: var(--color-text-2); font-family: var(--font-body); font-size: 14px; cursor: pointer;
 transition: border-color 0.2s, color 0.2s;
}
.page-btn:hover:not(:disabled) { border-color: var(--color-primary); color: var(--color-primary); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 14px; color: var(--color-text-3); }

/* Transition */
.order-list-enter-active { transition: opacity 0.25s, transform 0.25s var(--ease-smooth); }
.order-list-enter-from { opacity: 0; transform: translateY(8px); }
.order-list-leave-active { transition: opacity 0.15s; position: absolute; width: 100%; }
.order-list-leave-to { opacity: 0; }

/* ── MODALS ── */
.modal-overlay {
 position: fixed; inset: 0; z-index: 500;
 background: rgba(0,0,0,0.72); backdrop-filter: blur(4px);
 display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal {
 background: var(--color-bg-card); border: 1px solid var(--color-border);
 border-radius: var(--radius-xl); width: 100%; max-width: 480px;
 max-height: 90vh; display: flex; flex-direction: column;
 box-shadow: 0 24px 80px rgba(0,0,0,0.6), var(--shadow-glow);
}
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 26px; border-bottom: 1px solid var(--color-border); }
.modal__title { font-family: var(--font-display); font-size: 17px; font-weight: 800; }
.modal__close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--color-text-3); padding: 4px 8px; border-radius: var(--radius-sm); transition: background 0.15s; }
.modal__close:hover { background: rgba(239,68,68,0.1); color: var(--color-error); }
.modal__body { flex: 1; overflow-y: auto; padding: 22px 26px; display: flex; flex-direction: column; gap: 18px; }
.modal__footer { display: flex; gap: 10px; padding: 16px 26px; border-top: 1px solid var(--color-border); }

.complete-info { background: rgba(99,102,241,0.07); border: 1px solid rgba(99,102,241,0.2); border-radius: var(--radius-md); padding: 14px 16px; font-size: 14px; line-height: 1.7; }
.complete-info__sub { font-size: 12px; color: var(--color-text-3); margin-top: 6px; }
.rating-section { display: flex; flex-direction: column; gap: 10px; }
.field-label { font-size: 14px; font-weight: 600; color: var(--color-text-2); }
.star-rating { display: flex; gap: 6px; flex-direction: row-reverse; justify-content: flex-end; }
.star-btn { background: none; border: none; cursor: pointer; font-size: 32px; color: var(--color-border); transition: color 0.15s, transform 0.15s var(--ease-spring); }
.star-btn.filled { color: var(--color-gold); }
.star-btn:hover { transform: scale(1.2); }
.rating-hint { font-size: 13px; color: var(--color-gold); font-weight: 600; }
.field { display: flex; flex-direction: column; gap: 8px; }
.field--error .field-textarea { border-color: var(--color-error); }
.field-textarea { width: 100%; padding: 12px 14px; background: white; border: 1px solid var(--color-border); border-radius: var(--radius-md); color: black; font-family: var(--font-body); font-size: 14px; outline: none; resize: vertical; min-height: 80px; transition: border-color 0.2s; }
.field-textarea:focus { border-color: var(--color-primary); }
.field-error { font-size: 12px; color: var(--color-error); }
.required { color: var(--color-error); }
.dispute-warning { display: flex; gap: 10px; align-items: flex-start; background: rgb(80, 109, 190,0.50); border: 1px solid ; border-radius: var(--radius-md); padding: 14px 16px; font-size: 14px; color:white; line-height: 1.7; }
.modal-btn { padding: 11px 22px; border-radius: var(--radius-md); border: none; cursor: pointer; font-family: var(--font-display); font-size: 14px; font-weight: 700; transition: opacity 0.2s, transform 0.15s; display: flex; align-items: center; gap: 8px; }
.modal-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.modal-btn--success { background: linear-gradient(135deg, #10B981, #059669); color: white; box-shadow: 0 0 18px rgba(16,185,129,0.3); }
.modal-btn--success:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.modal-btn--danger { background: rgb(80, 109, 190); color: red; }
.modal-btn--danger:hover:not(:disabled) { opacity: 0.9; }
.modal-btn--ghost { background: rgb(80, 109, 190); border: 1px solid var(--color-border); color: white; }
.inline-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

.modal-enter-active { transition: opacity 0.25s; }
.modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from,.modal-leave-to { opacity: 0; }
.modal-enter-active .modal { animation: modal-pop 0.3s var(--ease-spring); }
@keyframes modal-pop { from { transform: scale(0.94) translateY(14px); } to { transform: scale(1); } }

/* Toast */
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); border-radius: var(--radius-full); padding: 13px 28px; font-size: 14px; font-weight: 600; box-shadow: var(--shadow-card); z-index: 600; white-space: nowrap; }
.toast--success { background: var(--color-bg-card); border: 1px solid var(--color-success); color: var(--color-success); }
.toast--error { background: var(--color-bg-card); border: 1px solid var(--color-error); color: var(--color-error); }
.toast-enter-active { transition: opacity 0.3s, transform 0.3s var(--ease-spring); }
.toast-leave-active { transition: opacity 0.2s, transform 0.2s; }
.toast-enter-from { opacity: 0; transform: translateX(-50%) translateY(16px); }
.toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }

@media (max-width: 640px) {
 .status-tabs { width: 100%; overflow-x: auto; }
 .order-card__header { flex-direction: column; }
 .order-card__footer { flex-direction: column; gap: 10px; align-items: flex-start; }
 .order-meta-grid { grid-template-columns: 1fr 1fr; }
}
</style>
