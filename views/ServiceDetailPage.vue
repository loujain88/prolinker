<template>
 <div class="service-detail" dir="rtl">
 <div v-if="loading" class="sd-loading">جاري تحميل الخدمة...</div>

 <div v-else-if="!service" class="sd-error">
 <span class="sd-error__icon"></span>
 <h2>ما قدرنا نلاقي هالخدمة</h2>
 <RouterLink to="/services" class="btn-outline">تصفح الخدمات</RouterLink>
 </div>

 <div v-else class="sd-layout">
 <!-- Left: service info -->
 <div class="sd-main">
 <div class="sd-thumb">
 <img v-if="service.thumbnail_url" :src="service.thumbnail_url" :alt="service.title" />
 <div v-else class="sd-thumb__placeholder"></div>
 </div>

 <h1 class="sd-title">{{ service.title }}</h1>

 <div class="sd-seller">
 <div class="sd-seller__avatar">{{ initials(service.seller?.name) }}</div>
 <div class="sd-seller__info">
 <span class="sd-seller__name">{{ service.seller?.name }}
 <span v-if="service.seller?.is_verified" class="sd-verified" title="بائع موثق">✓</span>
 </span>
 <span class="sd-seller__rating"> {{ service.seller?.average_rating ?? '—' }} · {{ service.seller?.completed_orders_count ?? 0 }} طلب منفّذ</span>
 <p v-if="service.seller?.bio" class="sd-seller__bio">{{ service.seller.bio }}</p>
 </div>
 </div>

 <p class="sd-desc">{{ service.description }}</p>

 <div class="sd-meta">
 <div class="sd-meta__item"><span></span> التسليم خلال {{ service.delivery_days }} يوم</div>
 <div class="sd-meta__item"><span></span> {{ service.revisions_included }} مراجعة مجانية</div>
 <div class="sd-meta__item"><span></span> {{ service.average_rating > 0 ? service.average_rating : 'لا يوجد تقييم بعد' }} ({{ service.total_orders }} طلب)</div>
 </div>

 <div v-if="service.gallery_urls?.length" class="sd-gallery">
 <img v-for="(g, i) in service.gallery_urls" :key="i" :src="g" alt="صورة من معرض الخدمة" />
 </div>
 </div>

 <!-- Right: purchase + contact seller -->
 <aside class="sd-sidebar">
 <div class="sd-price-card">
 <span class="sd-price-label">يبدأ من</span>
 <span class="sd-price-value">${{ service.dynamic_price }}</span>

 <button v-if="!auth.isLoggedIn" class="btn-primary" @click="goLoginToOrder"> سجّل الدخول لطلب الخدمة
 </button>
 <template v-else-if="auth.isClient || (!auth.user?.role)">
 <button class="btn-primary" @click="openOrderModal"> اطلب الخدمة الآن</button>
 <button class="btn-outline" @click="contactOpen = !contactOpen"> تواصل مع البائع قبل الطلب
 </button>
 </template>
 <p v-else class="sd-note">تسجيل الدخول كعميل مطلوب لطلب هذه الخدمة.</p>
 </div>

 <!-- Contact-the-seller box: sends pre-purchase message with the project details -->
 <div v-if="contactOpen" class="contact-box">
 <h3>ابعت تفاصيل طلبك للبائع قبل الشراء</h3>
 <p class="contact-hint">اشرح البائع شو محتاج بالضبط (حجم المشروع، أي متطلبات خاصة...)، وهو ممكن يوافق أو يقترح سعر تاني قبل ما تشتري.</p>
 <textarea v-model="contactBody" rows="4" placeholder="مثلاً: بدي موقع فيه 5 صفحات ولوحة تحكم بسيطة..."></textarea>

 <label class="file-drop"> إرفاق ملفات (اختياري) — صور، PDF، مستندات...
 <input type="file" multiple @change="onContactFiles" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.zip,.rar,.fig,.psd,.ai,.txt" />
 </label>
 <ul v-if="contactFiles.length" class="file-chip-list">
 <li v-for="(f, i) in contactFiles" :key="i" class="file-chip"> {{ f.name }} <button type="button" @click="contactFiles.splice(i,1)">✕</button>
 </li>
 </ul>

 <button class="btn-primary" :disabled="sendingContact || !contactBody.trim()" @click="sendContactMessage"> {{ sendingContact ? 'جاري الإرسال...' : 'إرسال' }}
 </button>
 <p v-if="contactSent" class="contact-success"> تم الإرسال — تابع الرد من صفحة
 <RouterLink to="/dashboard/messages">الرسائل</RouterLink>
 </p>
 </div>
 </aside>
 </div>

 <!-- Order modal -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="orderModal.open" class="modal-overlay" @mousedown.self="orderModal.open = false">
 <div class="modal">
 <div class="modal__header">
 <h2> طلب الخدمة</h2>
 <button class="modal__close" @click="orderModal.open = false">✕</button>
 </div>
 <div class="modal__body">
 <div class="field">
 <label>تفاصيل الطلب (اختياري)</label>
 <textarea v-model="orderForm.requirements" rows="4" placeholder="اشرح متطلباتك بالتفصيل..."></textarea>
 </div>
 <div class="field">
 <label> مرفقات (اختياري) — ملفات، صور مرجعية...</label>
 <input type="file" multiple @change="onOrderFiles" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.zip,.rar,.fig,.psd,.ai,.txt" />
 <ul v-if="orderForm.files.length" class="file-chip-list">
 <li v-for="(f, i) in orderForm.files" :key="i" class="file-chip"> {{ f.name }} <button type="button" @click="orderForm.files.splice(i,1)">✕</button>
 </li>
 </ul>
 </div>
 <div class="field">
 <label>خلال كم يوم تحتاج التسليم؟</label>
 <input v-model.number="orderForm.deliveryDays" type="number" min="1" max="180" :placeholder="`الافتراضي: ${service.delivery_days} يوم`" />
 <span class="field-hint">هاد الرقم بيوصل للمستقل، وبيقرر يقبل الطلب أو يرفضه بناءً عليه.</span>
 </div>
 <div class="field">
 <label>السعر ($) — لازم يكون {{ service.dynamic_price }} أو أعلى</label>
 <input v-model.number="orderForm.customPrice" type="number" :min="service.dynamic_price" step="1" />
 <span class="field-hint">لو مشروعك أكبر من الوصف المعروض، فيك ترفع السعر هون كعرض للبائع.</span>
 <span v-if="orderError" class="field-error">{{ orderError }}</span>
 </div>
 </div>
 <div class="modal__footer">
 <button class="btn-outline" @click="orderModal.open = false" :disabled="placing">إلغاء</button>
 <button class="btn-primary" @click="placeOrder" :disabled="placing"> {{ placing ? 'جاري الإرسال...' : 'تأكيد الطلب' }}
 </button>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <Transition name="toast">
 <div v-if="toastMsg" class="toast" :class="`toast--${toastType}`">{{ toastMsg }}</div>
 </Transition>
 </div>
</template>

<script setup> import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const service = ref(null)
const loading = ref(true)

const contactOpen = ref(false)
const contactBody = ref('')
const contactFiles = ref([])
const sendingContact = ref(false)
const contactSent = ref(false)
let conversationId = null

const orderModal = ref({ open: false })
const orderForm = reactive({ requirements: '', customPrice: null, files: [], deliveryDays: null })
const orderError = ref('')
const placing = ref(false)

const toastMsg = ref('')
const toastType = ref('success')
let toastTimer = null
function showToast(msg, type = 'success') {
 toastMsg.value = msg; toastType.value = type
 clearTimeout(toastTimer)
 toastTimer = setTimeout(() => (toastMsg.value = ''), 4000)
}

function initials(name) {
 return (name ?? '').split(' ').map(w => w[0]).slice(0, 2).join('')
}

function onContactFiles(e) {
 contactFiles.value.push(...Array.from(e.target.files ?? []))
 e.target.value = ''
}
function onOrderFiles(e) {
 orderForm.files.push(...Array.from(e.target.files ?? []))
 e.target.value = ''
}

function buildMessageFormData(serviceId, body, files) {
 const fd = new FormData()
 fd.append('service_id', serviceId)
 fd.append('body', body)
 files.forEach(f => fd.append('attachments[]', f))
 return fd
}

async function fetchService() {
 loading.value = true
 try {
 const { data } = await api.get(`/services/${route.params.id}`)
 service.value = data.data
 } catch {
 service.value = null
 } finally { loading.value = false }
}

function goLoginToOrder() {
 router.push({ name: 'login', query: { redirect: route.fullPath } })
}

function openOrderModal() {
 orderForm.requirements = contactBody.value || ''
 orderForm.customPrice = Number(service.value.dynamic_price)
 orderForm.files = [...contactFiles.value]
 orderError.value = ''
 orderModal.value.open = true
}

// Lock the page's own scroll while the modal is open, so scrolling/zooming
// inside the card never "leaks" through to the background page.
watch(() => orderModal.value.open, (isOpen) => {
 document.body.style.overflow = isOpen ? 'hidden' : ''
})

async function sendContactMessage() {
 if (!contactBody.value.trim()) return
 sendingContact.value = true
 try {
 const fd = buildMessageFormData(service.value.id, contactBody.value, contactFiles.value)
 const { data } = await api.post('/client/conversations', fd, { headers: { 'Content-Type': undefined } })
 conversationId = data.data.id
 contactSent.value = true
 showToast('تم إرسال تفاصيل الطلب للبائع ')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ أثناء الإرسال', 'error')
 } finally { sendingContact.value = false }
}

async function placeOrder() {
 orderError.value = ''
 if (orderForm.customPrice && orderForm.customPrice < Number(service.value.dynamic_price)) {
 orderError.value = `السعر لا يمكن أن يكون أقل من ${service.value.dynamic_price}$`
 return
 }
 placing.value = true
 try {
 // Make sure the order's requirements (and any attached files) reach the
 // seller as an actual message in their shared conversation — creating
 // one now if the client skipped "تواصل مع البائع" and went straight to ordering.
 if (!conversationId && (orderForm.requirements?.trim() || orderForm.files.length)) {
 const fd = buildMessageFormData(
 service.value.id,
 orderForm.requirements?.trim() || 'طلب خدمة جديد',
 orderForm.files
 )
 const { data } = await api.post('/client/conversations', fd, { headers: { 'Content-Type': undefined } })
 conversationId = data.data.id
 } else if (conversationId && orderForm.files.length) {
 // Conversation already exists (from the contact box) — send any newly
 // added files as a follow-up message before placing the order.
 const fd = buildMessageFormData(service.value.id, orderForm.requirements?.trim() || ' مرفقات الطلب', orderForm.files)
 await api.post(`/conversations/${conversationId}/messages`, fd, { headers: { 'Content-Type': undefined } })
 }

 const payload = {
 service_id: service.value.id,
 order_details: { requirements: orderForm.requirements || undefined },
 custom_price: orderForm.customPrice || undefined,
 delivery_days: orderForm.deliveryDays || undefined,
 }
 if (conversationId) payload.conversation_id = conversationId

 await api.post('/client/orders', payload)
 conversationId = null // this conversation is now tied to the order just placed — don't reuse it
 contactSent.value = false
 orderModal.value.open = false
 showToast('تم إنشاء الطلب بنجاح ')
 setTimeout(() => router.push('/dashboard/orders'), 1200)
 } catch (e) {
 orderError.value = e.response?.data?.message ?? 'حدث خطأ أثناء إنشاء الطلب'
 } finally { placing.value = false }
}

onMounted(fetchService)
</script>

<style scoped> 
.service-detail { max-width: 1100px; margin: 0 auto; padding: 32px 20px 60px; }
.sd-loading, .sd-error { text-align: center; padding: 80px 20px; color: var(--color-text-3); display: flex; flex-direction: column; align-items: center; gap: 12px; }
.sd-error__icon { font-size: 48px; }

.sd-layout { display: grid; grid-template-columns: 1fr 320px; gap: 28px; align-items: start; }
.sd-main { display: flex; flex-direction: column; gap: 16px; }
.sd-thumb { width: 100%; height: 340px; border-radius: var(--radius-xl); overflow: hidden; background: var(--color-bg-2); }
.sd-thumb img { width: 100%; height: 100%; object-fit: cover; }
.sd-thumb__placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 64px; }
.sd-title { font-family: var(--font-display); font-size: 24px; font-weight: 800; }
.sd-seller { display: flex; align-items: center; gap: 10px; }
.sd-seller__avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--grad-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.sd-seller__name { font-weight: 700; }
.sd-verified { color: #10B981; margin-right: 4px; }
.sd-seller__rating { display: block; font-size: 12px; color: var(--color-text-3); }
.sd-seller__info { display: flex; flex-direction: column; gap: 2px; }
.sd-seller__bio { font-size: 12.5px; color: var(--color-text-2); margin-top: 4px; line-height: 1.6; max-width: 480px; }
.sd-desc { line-height: 1.9; color: var(--color-text-2); white-space: pre-wrap; }
.sd-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px; color: var(--color-text-3); }
.sd-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap: 10px; }
.sd-gallery img { width: 100%; height: 100px; object-fit: cover; border-radius: var(--radius-md); }

.sd-sidebar { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 20px; }
.sd-price-card { background: var(--color-bg-card); border: 1px solid rgb(97, 132, 237); border-radius: var(--radius-xl); padding: 20px; display: flex; flex-direction: column; gap: 10px; }
.sd-price-label { font-size: 12px; color: var(--color-text-3); }
.sd-price-value { font-family: var(--font-display); font-size: 28px; font-weight: 800; color: white; margin-bottom: 8px; }
.sd-note { font-size: 12px; color: var(--color-text-3); }

.btn-primary { padding: 12px; border-radius: var(--radius-md); border: none; background: rgb(80, 109, 190); color: var(--color-text); font-weight: 700; cursor: pointer; }
.btn-outline { padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: rgb(80, 109, 190); color: var(--color-text); font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
.btn-primary:disabled, .btn-outline:disabled { opacity: 0.6; cursor: not-allowed; }

.contact-box { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 18px; display: flex; flex-direction: column; gap: 10px; }
.contact-box h3 { font-size: 14px; font-weight: 700; }
.contact-hint { font-size: 12px; color: var(--color-text-3); }
.contact-box textarea { width: 100%; padding: 10px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-bg-2); color: var(--color-text); font-family: var(--font-body); resize: vertical; }
.contact-success { font-size: 12px; color: var(--color-success); }
.contact-success a { color: var(--color-primary); }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.90); z-index: 500; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-xl); width: 100%; max-width: 480px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid var(--color-border); flex-shrink: 0; }
.modal__close { background: none; border: none; cursor: pointer; font-size: 15px; }
.modal__body { padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; overflow-y: auto; flex: 1; min-height: 0; }
.modal__footer { display: flex; gap: 10px; padding: 16px 22px; border-top: 1px solid var(--color-border); flex-shrink: 0; }
.field { display: flex; flex-direction: column; gap: 8px; }
.field label { font-size: 13px; font-weight: 600; }
.field textarea, .field input { padding: 10px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-bg-2); color: white; font-family: var(--font-body); }
.field-hint { font-size: 11px; color: rgb(99, 99, 239); }
.field-error { font-size: 12px; color: var(--color-error); }
.file-drop { display: block; font-size: 12px; color: var(--color-text-2); border: 1px dashed var(--color-border); border-radius: var(--radius-md); padding: 10px; cursor: pointer; text-align: center; }
.file-drop input { display: block; margin-top: 6px; width: 100%; font-size: 11px; }
.file-chip-list { list-style: none; display: flex; flex-wrap: wrap; gap: 6px; padding: 0; margin: 4px 0 0; }
.file-chip { display: flex; align-items: center; gap: 6px; background: var(--color-bg-2); border: 1px solid var(--color-border); border-radius: 999px; padding: 4px 10px; font-size: 11px; }
.file-chip button { background: none; border: none; cursor: pointer; color: var(--color-error); font-size: 11px; }

.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); border-radius: 999px; padding: 12px 24px; font-size: 13px; font-weight: 600; z-index: 600; background: var(--color-bg-card); border: 1px solid var(--color-border); }
.toast--success { border-color: var(--color-success); color: var(--color-success); }
.toast--error { border-color: var(--color-error); color: var(--color-error); }

@media (max-width: 860px) {
 .sd-layout { grid-template-columns: 1fr; }
 .sd-sidebar { position: static; }
}
</style>
