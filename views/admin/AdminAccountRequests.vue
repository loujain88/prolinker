<template>
 <div class="account-requests" dir="rtl">
 <h1> طلبات إنشاء الحسابات</h1>

 <div v-if="loading" class="ar-state">جاري التحميل...</div>
 <div v-else-if="requests.length === 0" class="ar-state">
 <span></span>
 <p>ما في طلبات معلّقة حالياً</p>
 </div>

 <div v-else class="ar-grid">
 <div v-for="r in requests" :key="r.id" class="ar-card">
 <div class="ar-card__header">
 <div>
 <h3>{{ r.name }}</h3>
 <span class="ar-role">{{ r.role === 'client' ? ' عميل' : ' مستقل' }}</span>
 </div>
 <span class="ar-date">{{ formatDate(r.created_at) }}</span>
 </div>

 <div class="ar-fields">
 <div><span>البريد:</span> {{ r.email }}</div>
 <div><span>الموبايل:</span> {{ r.phone }}</div>
 <div v-if="r.profile?.country"><span>الدولة:</span> {{ r.profile.country }}</div>
 </div>

 <button v-if="r.has_id_document" class="doc-btn" @click="viewDocument(r.id)" :disabled="docLoadingId === r.id"> {{ docLoadingId === r.id ? '...' : ' عرض صورة الهوية' }}
 </button>
 <span v-else class="no-doc"> لا توجد صورة هوية مرفقة</span>

 <div class="ar-actions">
 <button class="ar-btn ar-btn--approve" :disabled="actionId === r.id" @click="approve(r)"> موافقة</button>
 <button class="ar-btn ar-btn--reject" :disabled="actionId === r.id" @click="openReject(r)"> رفض</button>
 </div>
 </div>
 </div>

 <!-- Document viewer -->
 <Teleport to="body">
 <div v-if="docModal.open" class="modal-overlay" @mousedown.self="docModal.open = false">
 <div class="modal modal--doc">
 <button class="modal__close" @click="docModal.open = false">✕</button>
 <img v-if="docModal.url && !docModal.isPdf" :src="docModal.url" alt="وثيقة الهوية" />
 <iframe v-else-if="docModal.url" :src="docModal.url" class="pdf-frame"></iframe>
 </div>
 </div>
 </Teleport>

 <!-- Reject modal -->
 <Teleport to="body">
 <div v-if="rejectModal.open" class="modal-overlay" @mousedown.self="rejectModal.open = false">
 <div class="modal">
 <h3>سبب الرفض</h3>
 <textarea v-model="rejectModal.reason" rows="4" placeholder="اشرح سبب رفض الطلب..."></textarea>
 <span v-if="rejectModal.err" class="field-error">{{ rejectModal.err }}</span>
 <div class="modal__footer">
 <button class="btn-outline" @click="rejectModal.open = false">إلغاء</button>
 <button class="btn-danger" :disabled="actionId === rejectModal.request?.id" @click="submitReject">تأكيد الرفض</button>
 </div>
 </div>
 </div>
 </Teleport>
 </div>
</template>

<script setup> import { ref, reactive, onMounted } from 'vue'
import adminApi from '@/composables/useAdminApi'

const requests = ref([])
const loading = ref(false)
const actionId = ref(null)
const docLoadingId = ref(null)
const docModal = reactive({ open: false, url: null, isPdf: false })
const rejectModal = reactive({ open: false, request: null, reason: '', err: '' })

function formatDate(iso) {
 return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { year: 'numeric', month: 'short', day: 'numeric' })
}

async function fetchRequests() {
 loading.value = true
 try {
 const { data } = await adminApi.getAccountRequests()
 requests.value = data.data ?? []
 } finally { loading.value = false }
}

async function viewDocument(id) {
 docLoadingId.value = id
 try {
 const { data } = await adminApi.getAccountIdDocument(id)
 docModal.url = data.url
 docModal.isPdf = data.url?.includes('.pdf')
 docModal.open = true
 } catch {
 // ignore — button stays available to retry
 } finally { docLoadingId.value = null }
}

async function approve(r) {
 actionId.value = r.id
 try {
 await adminApi.approveAccount(r.id)
 requests.value = requests.value.filter(x => x.id !== r.id)
 } finally { actionId.value = null }
}

function openReject(r) {
 Object.assign(rejectModal, { open: true, request: r, reason: '', err: '' })
}

async function submitReject() {
 if (!rejectModal.reason.trim() || rejectModal.reason.trim().length < 5) {
 rejectModal.err = 'الرجاء كتابة سبب واضح'
 return
 }
 actionId.value = rejectModal.request.id
 try {
 await adminApi.rejectAccount(rejectModal.request.id, rejectModal.reason.trim())
 requests.value = requests.value.filter(x => x.id !== rejectModal.request.id)
 rejectModal.open = false
 } finally { actionId.value = null }
}

onMounted(fetchRequests)
</script>

<style scoped> .account-requests h1 { font-family: var(--font-display); font-size: 20px; font-weight: 800; margin-bottom: 20px; }
.ar-state { text-align: center; padding: 60px 20px; color: var(--color-text-3); display: flex; flex-direction: column; align-items: center; gap: 10px; }
.ar-state span { font-size: 36px; }

.ar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.ar-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 18px; display: flex; flex-direction: column; gap: 12px; }
.ar-card__header { display: flex; justify-content: space-between; align-items: flex-start; }
.ar-card__header h3 { font-size: 14px; font-weight: 700; }
.ar-role { font-size: 11px; color: var(--color-text-3); }
.ar-date { font-size: 11px; color: var(--color-text-3); }
.ar-fields { font-size: 12.5px; color: var(--color-text-2); display: flex; flex-direction: column; gap: 4px; }
.ar-fields span { color: var(--color-text-3); }

.doc-btn { padding: 8px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background:rgb(80, 109, 190);color: white; cursor: pointer; font-size: 13px; }
.no-doc { font-size: 11.5px; color: var(--color-error); }

.ar-actions { display: flex; gap: 8px; }
.ar-btn { flex: 1; padding: 9px; border-radius: var(--radius-md); border: none; cursor: pointer; font-weight: 700; font-size: 12.5px; }
.ar-btn--approve { background: var(--color-success); color: white; }
.ar-btn--reject { background: #DC2626; color: white; border: 1px solid var(--color-error); }
.ar-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 600; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 20px; max-width: 480px; width: 100%; }
.modal--doc { max-width: 640px; position: relative; }
.modal--doc img { width: 100%; border-radius: var(--radius-md); }
.pdf-frame { width: 100%; height: 70vh; border: none; border-radius: var(--radius-md); }
.modal__close { position: absolute; top: 10px; left: 10px; background: var(--color-bg-2); border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; z-index: 2; }
.modal h3 { font-size: 14px; margin-bottom: 10px; }
.modal textarea { width: 100%; padding: 10px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-bg-2); color: var(--color-text); font-family: var(--font-body); resize: vertical; }
.field-error { font-size: 12px; color: var(--color-error); }
.modal__footer { display: flex; gap: 10px; margin-top: 14px; }
.btn-outline { flex: 1; padding: 10px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: none; color: var(--color-text); cursor: pointer; }
.btn-danger { flex: 1; padding: 10px; border-radius: var(--radius-md); border: none; background: var(--color-error); color: white; cursor: pointer; font-weight: 700; }
</style>
