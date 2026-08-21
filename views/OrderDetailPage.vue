<template>
 <div class="order-detail" dir="rtl">
 <button class="back-link" @click="router.back()">→ رجوع</button>

 <div v-if="loading" class="od-loading">جاري التحميل...</div>

 <div v-else-if="!order" class="od-error">
 <span></span>
 <p>ما قدرنا نلاقي هالطلب</p>
 <RouterLink to="/dashboard/orders" class="btn-outline">رجوع للطلبات</RouterLink>
 </div>

 <div v-else class="od-layout">
 <!-- Order info -->
 <div class="od-main">
 <div class="od-card">
 <div class="od-card__header">
 <h1>طلب #{{ order.id }} — {{ order.service?.title }}</h1>
 <span class="status-pill" :class="`status--${order.status}`">{{ statusIcon(order.status) }} {{ statusLabel(order.status) }}</span>
 </div>

 <div class="od-grid">
 <div class="od-field"><span class="od-label">السعر النهائي</span><span class="od-value">${{ order.final_price }}</span></div>
 <div v-if="order.custom_price" class="od-field"><span class="od-label">سعر مقترح من العميل</span><span class="od-value">${{ order.custom_price }}</span></div>
 <div class="od-field"><span class="od-label">تاريخ الإنشاء</span><span class="od-value">{{ formatDate(order.created_at) }}</span></div>
 <div v-if="order.deadline_at" class="od-field"><span class="od-label">الموعد النهائي</span><span class="od-value">{{ formatDate(order.deadline_at) }}</span></div>
 <div class="od-field"><span class="od-label">العميل</span><span class="od-value">{{ order.client?.name }}</span></div>
 <div class="od-field"><span class="od-label">المستقل</span><span class="od-value">{{ order.seller?.name }}</span></div>
 </div>

 <div v-if="order.order_details?.requirements" class="od-requirements">
 <span class="od-label">تفاصيل الطلب</span>
 <p>{{ order.order_details.requirements }}</p>
 </div>

 <div v-if="order.dispute" class="od-dispute">
 <span class="od-label"> نزاع مفتوح على هذا الطلب</span>
 <p class="od-dispute__reason">{{ order.dispute.reason }}</p>
 <span class="od-dispute__status">الحالة: {{ disputeStatusLabel(order.dispute.status) }}</span>

 <div v-if="order.dispute.admin_notes" class="od-dispute__resolution">
 <span class="od-label"> قرار الإدارة</span>
 <p>{{ order.dispute.admin_notes }}</p>
 <span v-if="order.dispute.resolved_at" class="od-dispute__resolved-date">{{ formatDate(order.dispute.resolved_at) }}</span>
 </div>
 </div>

 <div v-if="order.delivery_files?.length" class="od-delivery">
 <span class="od-label"> ملفات التسليم</span>
 <p v-if="order.delivery_notes" class="od-delivery__notes">{{ order.delivery_notes }}</p>
 <ul class="file-list">
 <li v-for="(f, i) in order.delivery_files" :key="i" class="file-row">
 <a :href="f.url" target="_blank" rel="noopener"> {{ f.name }}</a>
 </li>
 </ul>
 </div>
 </div>
 </div>

 <!-- Conversation thread -->
 <aside class="od-thread">
 <div class="od-thread__header"><h2> المحادثة</h2></div>

 <div v-if="!order.conversation_id" class="thread-empty">
 <p>ما في محادثة مرتبطة بهذا الطلب بعد.</p>
 </div>
 <template v-else>
 <div class="thread__messages" ref="threadBody">
 <div v-if="loadingThread" class="thread-empty"><p>جاري التحميل...</p></div>
 <div v-for="m in messages" :key="m.id" class="bubble" :class="{ 'bubble--mine': m.sender_id === auth.user?.id }">
 <p v-if="m.body" class="bubble__body">{{ m.body }}</p>
 <div v-if="m.attachments?.length" class="bubble__attachments">
 <a v-for="(a, i) in m.attachments" :key="i" :href="a.url" target="_blank" rel="noopener" class="attachment-chip"> {{ a.name }}
 </a>
 </div>
 <span class="bubble__time">{{ formatTime(m.created_at) }}</span>
 </div>
 </div>

 <form class="thread__composer" @submit.prevent="sendReply">
 <div v-if="replyFiles.length" class="file-chip-list">
 <span v-for="(f,i) in replyFiles" :key="i" class="file-chip"> {{ f.name }} <button type="button" @click="replyFiles.splice(i,1)">✕</button></span>
 </div>
 <div class="composer-row">
 <label class="attach-btn">
 
 <input type="file" multiple hidden @change="onReplyFiles" />
 </label>
 <input v-model="replyText" type="text" placeholder="اكتب رسالة..." :disabled="sending" />
 <button type="submit" :disabled="sending || (!replyText.trim() && !replyFiles.length)">إرسال</button>
 </div>
 </form>
 </template>
 </aside>
 </div>
 </div>
</template>

<script setup> import { ref, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const order = ref(null)
const loading = ref(true)

const messages = ref([])
const loadingThread = ref(false)
const replyText = ref('')
const replyFiles = ref([])
const sending = ref(false)
const threadBody = ref(null)

const statusMap = {
 pending: { label: 'معلق', icon: '' },
 in_progress: { label: 'جارٍ', icon: '' },
 delivered: { label: 'مُسلَّم', icon: '' },
 completed: { label: 'مكتمل', icon: '' },
 cancelled: { label: 'ملغى', icon: '' },
 refunded: { label: 'مُسترَد', icon: '↩' },
}
const statusLabel = s => statusMap[s]?.label ?? s
const statusIcon = s => statusMap[s]?.icon ?? ''
function disputeStatusLabel(s) {
 return ({ open: 'قيد الفتح', under_review: 'قيد المراجعة', resolved_client: 'حُلّ لصالح العميل', resolved_seller: 'حُلّ لصالح المستقل', closed_no_action: 'أُغلق بدون إجراء' })[s] ?? s
}
function formatDate(iso) { return iso ? new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { year:'numeric', month:'short', day:'numeric' }) : '—' }
function formatTime(iso) { return iso ? new Date(iso).toLocaleString('ar-EG-u-nu-latn', { hour:'2-digit', minute:'2-digit', day:'numeric', month:'short' }) : '' }

function onReplyFiles(e) {
 replyFiles.value.push(...Array.from(e.target.files ?? []))
 e.target.value = ''
}

async function fetchOrder() {
 loading.value = true
 try {
 const { data } = await api.get(`/orders/${route.params.id}`)
 order.value = data.data
 if (order.value.conversation_id) await fetchThread()
 } catch {
 order.value = null
 } finally { loading.value = false }
}

async function fetchThread() {
 loadingThread.value = true
 try {
 const { data } = await api.get(`/conversations/${order.value.conversation_id}`)
 messages.value = data.data.messages ?? []
 await nextTick()
 if (threadBody.value) threadBody.value.scrollTop = threadBody.value.scrollHeight
 } catch {
 messages.value = []
 } finally { loadingThread.value = false }
}

async function sendReply() {
 if (!replyText.value.trim() && !replyFiles.value.length) return
 sending.value = true
 try {
 const fd = new FormData()
 fd.append('body', replyText.value.trim() || ' مرفق')
 replyFiles.value.forEach(f => fd.append('attachments[]', f))
 const { data } = await api.post(`/conversations/${order.value.conversation_id}/messages`, fd, { headers: { 'Content-Type': undefined } })
 messages.value.push(data.data)
 replyText.value = ''
 replyFiles.value = []
 await nextTick()
 if (threadBody.value) threadBody.value.scrollTop = threadBody.value.scrollHeight
 } catch {
 // keep typed text so the user can retry
 } finally { sending.value = false }
}

onMounted(fetchOrder)
</script>

<style scoped> .order-detail { max-width: 1100px; margin: 0 auto; padding: 24px 20px 60px; }
.back-link { background: none; border: none; color: var(--color-text-2); font-size: 13px; cursor: pointer; margin-bottom: 16px; }
.od-loading, .od-error { text-align: center; padding: 80px 20px; color: var(--color-text-3); display: flex; flex-direction: column; align-items: center; gap: 10px; }
.od-error span { font-size: 40px; }

.od-layout { display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start; }
.od-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 22px; }
.od-card__header { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.od-card__header h1 { font-family: var(--font-display); font-size: 18px; font-weight: 800; }
.status-pill { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 999px; background: var(--color-bg-2); white-space: nowrap; }

.od-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 18px; }
.od-field { display: flex; flex-direction: column; gap: 2px; }
.od-label { font-size: 11px; color: var(--color-text-3); }
.od-value { font-size: 14px; font-weight: 700; }
.od-requirements { border-top: 1px solid var(--color-border); padding-top: 14px; }
.od-requirements p { font-size: 13px; line-height: 1.8; color: var(--color-text-2); white-space: pre-wrap; margin-top: 6px; }
.od-dispute { border-top: 1px solid var(--color-border); padding-top: 14px; margin-top: 14px; background: rgba(239,68,68,0.06); border-radius: var(--radius-md); padding: 14px; }
.od-dispute__reason { font-size: 13px; line-height: 1.8; color: var(--color-text-2); margin-top: 6px; white-space: pre-wrap; }
.od-dispute__status { font-size: 11.5px; color: var(--color-error); display: block; margin-top: 6px; font-weight: 600; }
.od-dispute__resolution { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--color-border); }
.od-dispute__resolution p { font-size: 13px; line-height: 1.8; color: var(--color-text); margin-top: 6px; white-space: pre-wrap; }
.od-dispute__resolved-date { font-size: 11px; color: var(--color-text-3); display: block; margin-top: 6px; }

.od-thread { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); display: flex; flex-direction: column; height: 560px; position: sticky; top: 20px; }
.od-thread__header { padding: 14px 18px; border-bottom: 1px solid var(--color-border); }
.od-thread__header h2 { font-size: 14px; font-weight: 800; }
.thread-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: var(--color-text-3); font-size: 13px; padding: 20px; text-align: center; }

.thread__messages { flex: 1; overflow-y: auto; padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
.bubble { max-width: 85%; padding: 9px 12px; border-radius: var(--radius-lg); background: var(--color-bg-2); align-self: flex-start; }
.bubble--mine { align-self: flex-end; background: var(--grad-primary); color: white; }
.bubble__body { font-size: 12.5px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
.bubble__attachments { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
.attachment-chip { font-size: 11px; text-decoration: underline; color: inherit; }
.bubble__time { display: block; font-size: 10px; opacity: 0.7; margin-top: 4px; }

.thread__composer { border-top: 1px solid var(--color-border); padding: 10px 14px; }
.composer-row { display: flex; gap: 8px; align-items: center; }
.attach-btn { cursor: pointer; font-size: 16px; padding: 6px; }
.thread__composer input[type=text] { flex: 1; height: 38px; padding: 0 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-bg-2); color: var(--color-text); font-size: 12.5px; outline: none; }
.thread__composer button[type=submit] { padding: 0 16px; height: 38px; border-radius: var(--radius-md); border: none; background: var(--grad-primary); color: white; font-weight: 700; font-size: 12.5px; cursor: pointer; }
.thread__composer button:disabled { opacity: 0.5; cursor: not-allowed; }
.file-chip-list { list-style: none; display: flex; flex-wrap: wrap; gap: 6px; padding: 0; margin: 0 0 8px; }
.file-chip { display: flex; align-items: center; gap: 6px; background: var(--color-bg-2); border: 1px solid var(--color-border); border-radius: 999px; padding: 3px 9px; font-size: 10.5px; }
.file-chip button { background: none; border: none; cursor: pointer; color: var(--color-error); }

@media (max-width: 860px) {
 .od-layout { grid-template-columns: 1fr; }
 .od-thread { position: static; height: 420px; }
}
</style>
