<template>
 <div class="admin-services" dir="rtl">
 <div class="page-hdr">
 <div><h2 class="page-title"> مراجعة الخدمات</h2><p class="page-sub">راجع خدمات المستقلين وغيّر حالتها</p></div>
 <div class="hdr-controls">
 <select v-model="statusFilter" class="flt-select" @change="loadServices">
 <option value="paused">بانتظار المراجعة</option>
 <option value="active">نشطة</option>
 <option value="suspended">موقوفة</option>
 <option value="">الكل</option>
 </select>
 <div class="srch-wrap">
 <input v-model="search" type="text" class="srch-input" placeholder="بحث بعنوان الخدمة..." @input="debouncedLoad" />
 </div>
 </div>
 </div>

 <div v-if="loading" class="sk-grid">
 <div v-for="i in 6" :key="i" class="sk-card">
 <div class="sk sk--thumb"></div>
 <div class="sk-body"><div class="sk sk--ln" style="width:70%"></div><div class="sk sk--ln" style="width:45%;margin-top:8px"></div></div>
 </div>
 </div>

 <div v-else-if="services.length === 0" class="empty-state">
 <span></span><h3>لا توجد خدمات</h3><p>جرب تغيير الفلتر</p>
 </div>

 <div v-else class="svc-grid">
 <div v-for="svc in services" :key="svc.id" class="svc-card">
 <div class="svc-thumb">
 <div v-if="!svc.thumbnail_url" class="svc-thumb__ph"></div>
 <img v-else :src="svc.thumbnail_url" :alt="svc.title" />
 <span class="svc-status" :class="`ss--${svc.status}`">{{ sStatusLabel(svc.status) }}</span>
 </div>
 <div class="svc-body">
 <div class="svc-cat">{{ svc.category ?? 'غير مصنف' }}</div>
 <h3 class="svc-title">{{ svc.title }}</h3>
 <p class="svc-desc">{{ truncate(svc.description, 100) }}</p>
 <div class="svc-meta">
 <span> ${{ svc.dynamic_price }}</span>
 <span> {{ svc.delivery_days }} يوم</span>
 <span> {{ svc.average_rating > 0 ? svc.average_rating : 'جديد' }}</span>
 </div>
 <div class="seller-row">
 <div class="s-av">{{ svc.seller?.name?.[0] }}</div>
 <div><div class="s-name">{{ svc.seller?.name }}</div><div class="s-email">{{ svc.seller?.email }}</div></div>
 </div>
 </div>
 <div class="svc-footer">
 <button class="sfbtn sfbtn--ok" @click="updateStatus(svc,'active')" :disabled="actionId===svc.id"> نشر</button>
 <button class="sfbtn sfbtn--pause" @click="updateStatus(svc,'paused')" :disabled="actionId===svc.id">⏸ إيقاف</button>
 <button class="sfbtn sfbtn--sus" @click="openSuspend(svc)" :disabled="actionId===svc.id"> تعليق</button>
 </div>
 </div>
 </div>

 <!-- Suspend Modal (requires notes) -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="suspendModal.open" class="modal-overlay" @mousedown.self="suspendModal.open=false">
 <div class="modal modal--sm">
 <div class="modal-hdr"><h2> تعليق الخدمة</h2><button class="mcls" @click="suspendModal.open=false">✕</button></div>
 <div class="modal-body">
 <p class="suspend-title">«{{ suspendModal.svc?.title }}»</p>
 <div class="field">
 <label class="flabel">سبب التعليق (سيُرسَل للمستقل) <span class="req">*</span></label>
 <textarea v-model="suspendModal.notes" class="ftxtarea" rows="4"
 placeholder="وضّح سبب تعليق هذه الخدمة..." @input="suspendModal.err=''"></textarea>
 <span v-if="suspendModal.err" class="ferr">{{ suspendModal.err }}</span>
 </div>
 </div>
 <div class="modal-ftr">
 <button class="mbtn mbtn--ghost" @click="suspendModal.open=false">إلغاء</button>
 <button class="mbtn mbtn--danger" @click="submitSuspend" :disabled="!!actionId">
 <span v-if="actionId" class="bsp"></span>{{ actionId ? 'جاري...' : 'تأكيد التعليق' }}
 </button>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <Transition name="toast">
 <div v-if="toast.msg" class="toast" :class="`toast--${toast.type}`">{{ toast.msg }}</div>
 </Transition>
 </div>
</template>

<script setup> import { ref, reactive, onMounted } from 'vue'
import adminApi from '@/composables/useAdminApi'

const services = ref([])
const loading = ref(false)
const actionId = ref(null)
const statusFilter = ref('paused')
const search = ref('')
const toast = reactive({ msg:'', type:'success' })
const suspendModal = reactive({ open:false, svc:null, notes:'', err:'' })

let tTimer=null, debTimer=null
function showToast(msg, type='success') { toast.msg=msg; toast.type=type; clearTimeout(tTimer); tTimer=setTimeout(()=>{toast.msg=''},4500) }
function debouncedLoad() { clearTimeout(debTimer); debTimer=setTimeout(loadServices,400) }

async function loadServices() {
 loading.value = true
 try {
 const params = {}
 if (statusFilter.value) params.status = statusFilter.value
 if (search.value.trim()) params.search = search.value.trim()
 const { data } = statusFilter.value === 'paused' && !search.value
 ? await adminApi.getPendingServices()
 : await adminApi.getAllServices(params)
 services.value = data.data ?? data
 } catch { showToast('تعذّر تحميل الخدمات','error') }
 finally { loading.value = false }
}

async function updateStatus(svc, status, notes='') {
 actionId.value = svc.id
 try {
 await adminApi.updateServiceStatus(svc.id, { status, notes })
 svc.status = status
 const label = { active:'تم نشر الخدمة ', paused:'تم إيقاف الخدمة ⏸', suspended:'تم تعليق الخدمة ' }[status]
 showToast(label)
 } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ','error') }
 finally { actionId.value = null }
}

function openSuspend(svc) { Object.assign(suspendModal,{ open:true, svc, notes:'', err:'' }); document.body.style.overflow='hidden' }
async function submitSuspend() {
 if (!suspendModal.notes.trim()) { suspendModal.err='يجب كتابة سبب التعليق'; return }
 await updateStatus(suspendModal.svc, 'suspended', suspendModal.notes)
 suspendModal.open=false; document.body.style.overflow=''
}

const sStatusLabel = s => ({ active:'نشطة', paused:'معلقة', suspended:'موقوفة' }[s] ?? s)
const truncate = (str, n) => str?.length > n ? str.slice(0,n)+'...' : str

onMounted(loadServices)
</script>

<style scoped> .admin-services { display:flex; flex-direction:column; gap:20px; }
.page-hdr { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.page-title { font-family:var(--font-display); font-size:22px; font-weight:800; margin-bottom:4px; }
.page-sub { font-size:14px; color:var(--color-text-3); }
.hdr-controls { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.flt-select { background:rgb(80, 109, 190); border:1px solid var(--color-border); border-radius:10px; color:var(--color-text-2); font-family:var(--font-body); font-size:13px; padding:9px 14px; cursor:pointer; outline:none; }
.srch-wrap { position:relative; }
.srch-input { height:42px; padding:0 14px; background:rgb(80, 109, 190); border:1px solid var(--color-border); border-radius:10px; color:var(--color-text); font-family:var(--font-body); font-size:14px; outline:none; width:220px; transition:border-color .2s; }
.srch-input:focus { border-color:var(--color-primary); }
.srch-input::placeholder { color:var(--color-text-3); }
.sk-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.sk-card { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:16px; overflow:hidden; }
.sk { background:linear-gradient(90deg,var(--color-bg-card) 25%,var(--color-bg-card-hover) 50%,var(--color-bg-card) 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:4px; }
@keyframes shimmer { to { background-position:-200% 0; } }
.sk--thumb { height:140px; border-radius:0; }
.sk-body { padding:14px; display:flex; flex-direction:column; gap:10px; }
.sk--ln { height:11px; }
.empty-state { display:flex; flex-direction:column; align-items:center; gap:12px; padding:60px; text-align:center; background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; }
.empty-state span { font-size:48px; } .empty-state h3 { font-family:var(--font-display); font-size:18px; font-weight:700; } .empty-state p { font-size:14px; color:var(--color-text-3); }
.svc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:16px; }
.svc-card { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; overflow:hidden; display:flex; flex-direction:column; transition:border-color .2s,box-shadow .2s; }
.svc-card:hover { border-color:var(--color-border-hover); box-shadow:var(--shadow-glow); }
.svc-thumb { position:relative; height:140px; overflow:hidden; background:var(--color-bg-2); flex-shrink:0; }
.svc-thumb img { width:100%; height:100%; object-fit:cover; }
.svc-thumb__ph { width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:40px; }
.svc-status { position:absolute; top:8px; right:8px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.ss--active { background:rgba(16,185,129,.85); color:white; }
.ss--paused { background:rgba(245,158,11,.85); color:white; }
.ss--suspended { background:rgba(239,68,68,.85); color:white; }
.svc-body { padding:14px 16px; flex:1; display:flex; flex-direction:column; gap:8px; }
.svc-cat { font-size:11px; color:var(--color-primary); font-weight:600; }
.svc-title { font-family:var(--font-display); font-size:14px; font-weight:700; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.svc-desc { font-size:12px; color:var(--color-text-3); line-height:1.5; }
.svc-meta { display:flex; gap:12px; flex-wrap:wrap; }
.svc-meta span { font-size:12px; color:var(--color-text-2); }
.seller-row { display:flex; align-items:center; gap:8px; padding-top:6px; border-top:1px solid var(--color-border); margin-top:auto; }
.s-av { width:28px; height:28px; border-radius:50%; background:var(--grad-primary); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:white; flex-shrink:0; }
.s-name { font-size:12px; font-weight:600; color:var(--color-text); }
.s-email { font-size:10px; color:var(--color-text-3); }
.svc-footer { display:flex; gap:6px; padding:12px 14px; border-top:1px solid var(--color-border); background:rgba(0,0,0,.06); }
.sfbtn { flex:1; padding:7px 10px; border-radius:20px; border:none; cursor:pointer; font-family:var(--font-body); font-size:12px; font-weight:600; transition:opacity .2s,transform .15s; }
.sfbtn:disabled { opacity:.5; cursor:not-allowed; }
.sfbtn:hover:not(:disabled) { opacity:.85; transform:scale(.97); }
.sfbtn--ok { background:rgba(16,185,129,.15); color:#10B981; border:1px solid rgba(16,185,129,.3); }
.sfbtn--pause { background:rgba(245,158,11,.12); color:#F59E0B; border:1px solid rgba(245,158,11,.3); }
.sfbtn--sus { background:rgba(239,68,68,.1); color:#EF4444; border:1px solid rgba(239,68,68,.25); }
.suspend-title { font-family:var(--font-display); font-size:16px; font-weight:700; color:var(--color-text); text-align:center; }
.modal-overlay { position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.75); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; padding:20px; }
.modal { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:20px; width:100%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,.6); }
.modal--sm { max-width:440px; }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--color-border); flex-shrink:0; }
.modal-hdr h2 { font-family:var(--font-display); font-size:16px; font-weight:800; }
.mcls { background:none; border:none; cursor:pointer; font-size:16px; color:var(--color-text-3); padding:4px 8px; border-radius:6px; transition:background .15s; }
.mcls:hover { background:rgba(239,68,68,.1); color:#EF4444; }
.modal-body { flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:14px; }
.modal-ftr { display:flex; gap:10px; padding:16px 24px; border-top:1px solid var(--color-border); flex-shrink:0; }
.field { display:flex; flex-direction:column; gap:7px; }
.flabel { font-size:14px; font-weight:600; color:var(--color-text-2); }
.req { color:#EF4444; }
.ferr { font-size:12px; color:#EF4444; }
.ftxtarea { width:100%; padding:10px 14px; background:var(--color-bg-2); border:1px solid var(--color-border); border-radius:10px; color:var(--color-text); font-family:var(--font-body); font-size:14px; outline:none; resize:vertical; min-height:90px; transition:border-color .2s; }
.ftxtarea:focus { border-color:var(--color-primary); }
.mbtn { padding:10px 22px; border-radius:10px; border:none; cursor:pointer; font-family:var(--font-display); font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; transition:opacity .2s,transform .15s; }
.mbtn:disabled { opacity:.55; cursor:not-allowed; }
.mbtn--danger { background:linear-gradient(135deg,#EF4444,#DC2626); color:white; }
.mbtn--danger:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
.mbtn--ghost { background:var(--color-bg-2); border:1px solid var(--color-border); color:var(--color-text-2); }
.bsp { width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:white; border-radius:50%; animation:spin .7s linear infinite; display:inline-block; flex-shrink:0; }
@keyframes spin { to { transform:rotate(360deg); } }
.modal-enter-active,.modal-leave-active { transition:opacity .25s; }
.modal-enter-from,.modal-leave-to { opacity:0; }
.modal-enter-active .modal { animation:pop .3s cubic-bezier(.34,1.56,.64,1); }
@keyframes pop { from { transform:scale(.93) translateY(14px); } to { transform:scale(1); } }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); border-radius:9999px; padding:13px 28px; font-size:14px; font-weight:600; box-shadow:0 4px 24px rgba(0,0,0,.35); z-index:600; white-space:nowrap; }
.toast--success { background:var(--color-bg-card); border:1px solid #10B981; color:#10B981; }
.toast--error { background:var(--color-bg-card); border:1px solid #EF4444; color:#EF4444; }
.toast-enter-active { transition:opacity .3s,transform .3s cubic-bezier(.34,1.56,.64,1); }
.toast-leave-active { transition:opacity .2s,transform .2s; }
.toast-enter-from { opacity:0; transform:translateX(-50%) translateY(16px); }
.toast-leave-to { opacity:0; transform:translateX(-50%) translateY(8px); }
</style>
