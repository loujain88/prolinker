<template>
 <div class="deliver-page" dir="rtl">
 <button class="back-link" @click="router.back()">→ رجوع</button>

 <div v-if="loading" class="dp-loading">جاري التحميل...</div>
 <div v-else-if="!order" class="dp-error">ما قدرنا نلاقي هالطلب</div>

 <div v-else class="dp-card">
 <h1> تسليم الطلب #{{ order.id }}</h1>
 <p class="dp-sub">{{ order.service?.title }} — للعميل {{ order.client?.name }}</p>

 <div v-if="order.status !== 'in_progress'" class="dp-warning"> هذا الطلب حالته حالياً "{{ statusLabel(order.status) }}" — التسليم متاح فقط للطلبات الجارية.
 </div>

 <template v-else>
 <p class="dp-hint"> الملفات يلي بترفعها هون ما رح توصل للعميل إلا بعد ما تضغط "تسليم الطلب" بالأسفل —
 هيدي مختلفة عن رسائل المحادثة العادية.
 </p>

 <label class="file-drop-lg"> اسحب الملفات هون أو اضغط للاختيار (حتى 10 ملفات، 20MB لكل وحد)
 <input type="file" multiple @change="onFiles" />
 </label>

 <ul v-if="files.length" class="file-list">
 <li v-for="(f, i) in files" :key="i" class="file-row">
 <span> {{ f.name }} <em>({{ formatSize(f.size) }})</em></span>
 <button type="button" @click="files.splice(i, 1)">✕</button>
 </li>
 </ul>
 <span v-if="filesError" class="field-error">{{ filesError }}</span>

 <div class="field">
 <label>ملاحظات التسليم (اختياري)</label>
 <textarea v-model="deliveryNotes" rows="4" placeholder="أي توضيح تحب تضيفه مع الملفات..."></textarea>
 </div>

 <button class="btn-primary" :disabled="submitting" @click="submitDelivery"> {{ submitting ? 'جاري التسليم...' : ' تسليم الطلب للعميل' }}
 </button>
 </template>

 <!-- Already delivered -->
 <div v-if="order.status !== 'pending' && order.status !== 'in_progress' && order.delivery_files?.length" class="already-delivered">
 <h3> تم تسليم هذا الطلب</h3>
 <ul class="file-list">
 <li v-for="(f, i) in order.delivery_files" :key="i" class="file-row">
 <a :href="f.url" target="_blank" rel="noopener"> {{ f.name }}</a>
 </li>
 </ul>
 </div>
 </div>

 <Transition name="toast">
 <div v-if="toastMsg" class="toast" :class="`toast--${toastType}`">{{ toastMsg }}</div>
 </Transition>
 </div>
</template>

<script setup> import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'

const route = useRoute()
const router = useRouter()

const order = ref(null)
const loading = ref(true)
const files = ref([])
const filesError = ref('')
const deliveryNotes = ref('')
const submitting = ref(false)

const toastMsg = ref('')
const toastType = ref('success')
function showToast(msg, type = 'success') {
 toastMsg.value = msg; toastType.value = type
 setTimeout(() => (toastMsg.value = ''), 4000)
}

const statusMap = {
 pending: 'معلق', in_progress: 'جارٍ', delivered: 'مُسلَّم',
 completed: 'مكتمل', cancelled: 'ملغى', refunded: 'مُسترَد',
}
const statusLabel = s => statusMap[s] ?? s

function onFiles(e) {
 files.value.push(...Array.from(e.target.files ?? []))
 filesError.value = ''
 e.target.value = ''
}
function formatSize(bytes) {
 if (!bytes) return ''
 const kb = bytes / 1024
 return kb < 1024 ? `${kb.toFixed(0)} KB` : `${(kb / 1024).toFixed(1)} MB`
}

async function fetchOrder() {
 loading.value = true
 try {
 const { data } = await api.get(`/orders/${route.params.id}`)
 order.value = data.data
 } catch {
 order.value = null
 } finally { loading.value = false }
}

async function submitDelivery() {
 if (!files.value.length) {
 filesError.value = 'لازم ترفع ملف واحد على الأقل قبل التسليم'
 return
 }
 submitting.value = true
 try {
 const fd = new FormData()
 fd.append('_method', 'PATCH') // Laravel method spoofing — required for multipart on non-POST verbs
 files.value.forEach(f => fd.append('files[]', f))
 if (deliveryNotes.value.trim()) fd.append('delivery_notes', deliveryNotes.value.trim())

 await api.post(`/seller/orders/${route.params.id}/deliver`, fd, { headers: { 'Content-Type': undefined } })
 showToast('تم تسليم الطلب بنجاح، بانتظار موافقة العميل ')
 setTimeout(() => router.push('/dashboard/orders'), 1400)
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ أثناء التسليم', 'error')
 } finally { submitting.value = false }
}

onMounted(fetchOrder)
</script>

<style scoped> .deliver-page { max-width: 720px; margin: 0 auto; padding: 24px 20px 60px; }
.back-link {background: none;  border: none; color:white; font-size: 15px; cursor: pointer; margin-bottom: 16px; }
.dp-loading, .dp-error { text-align: center; padding: 60px 20px; color: var(--color-text-3); }
.dp-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 26px; display: flex; flex-direction: column; gap: 16px; }
.dp-card h1 { font-family: var(--font-display); font-size: 19px; font-weight: 800; }
.dp-sub { font-size: 13px; color: var(--color-text-3); margin-top: -8px; }
.dp-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: var(--radius-md); padding: 12px; font-size: 13px; }
.dp-hint { font-size: 12.5px; color: black; background: rgb(80, 109, 190,0.50); border-radius: var(--radius-md); padding: 12px; line-height: 1.7; }

.file-drop-lg {background: white; display: block; text-align: center; padding: 30px; border: 2px dashed var(--color-border); border-radius: var(--radius-lg); cursor: pointer; font-size: 13px; color: gray; }
.file-drop-lg input { display: none; }
.file-list { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.file-row { display: flex; justify-content: space-between; align-items: center; background: var(--color-bg-2); border-radius: var(--radius-md); padding: 8px 12px; font-size: 12.5px; }
.file-row em { color: var(--color-text-3); font-style: normal; font-size: 11px; }
.file-row button { background: none; border: none; color: var(--color-error); cursor: pointer; }
.file-row a { color: inherit; text-decoration: underline; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 13px; font-weight: 600; }
.field textarea { padding: 10px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: white; color:black; font-family: var(--font-body); resize: vertical; }
.field-error { font-size: 12px; color: var(--color-error); }

.btn-primary { padding: 13px; border-radius: var(--radius-md); border: none; background: rgb(80, 109, 190); color: white; font-weight: 700; cursor: pointer; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.already-delivered { border-top: 1px solid var(--color-border); padding-top: 16px; }
.already-delivered h3 { font-size: 14px; font-weight: 700; margin-bottom: 10px; }

.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); border-radius: 999px; padding: 12px 24px; font-size: 13px; font-weight: 600; z-index: 600; background: var(--color-bg-card); border: 1px solid var(--color-border); }
.toast--success { border-color: var(--color-success); color: var(--color-success); }
.toast--error { border-color: var(--color-error); color: var(--color-error); }
</style>
