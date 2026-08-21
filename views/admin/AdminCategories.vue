<template>
 <div class="admin-categories" dir="rtl">

 <div class="page-hdr">
 <div><h2 class="page-title"> إدارة التصنيفات</h2><p class="page-sub">أضف أو عدّل أو احذف تصنيفات الخدمات</p></div>
 </div>

 <div class="layout-grid">

 <!-- ── Add / Edit Form ── -->
 <div class="form-panel">
 <h3 class="form-panel__title">{{ editing ? ' تعديل التصنيف' : ' إضافة تصنيف جديد' }}</h3>

 <div class="field" :class="{ 'field--err': errors.name }">
 <label class="flabel">اسم التصنيف <span class="req">*</span></label>
 <input v-model="form.name" type="text" class="finput"
 placeholder="مثال: تصميم الجرافيك" @input="errors.name = ''" />
 <span v-if="errors.name" class="ferr">{{ errors.name }}</span>
 </div>

 

 <div class="form-actions">
 <button v-if="editing" class="cancel-btn" @click="cancelEdit">إلغاء التعديل</button>
 <button class="submit-btn" @click="submitForm" :disabled="submitting">
 <span v-if="submitting" class="bsp"></span> {{ submitting ? 'جاري الحفظ...' : editing ? ' حفظ التعديلات' : ' إضافة التصنيف' }}
 </button>
 </div>
 </div>

 <!-- ── Categories List ── -->
 <div class="list-panel">
 <div class="list-panel__header">
 <h3 class="list-panel__title">التصنيفات الحالية</h3>
 <span class="list-count">{{ categories.length }} تصنيف</span>
 </div>

 <div v-if="loading" class="list-loading">
 <div v-for="i in 5" :key="i" class="sk-row">
 <div class="sk sk--icon-sm"></div>
 <div class="sk-body"><div class="sk sk--ln" style="width:50%"></div><div class="sk sk--ln" style="width:35%;margin-top:6px"></div></div>
 <div class="sk sk--btn"></div>
 </div>
 </div>

 <div v-else-if="categories.length === 0" class="list-empty">
 <span></span><p>لا توجد تصنيفات بعد</p>
 </div>

 <TransitionGroup v-else name="cat-list" tag="div" class="cat-list">
 <div v-for="cat in categories" :key="cat.id" class="cat-item"
 :class="{ 'cat-item--editing': editing?.id === cat.id }">
 <div class="cat-info">
 <div class="cat-name">{{ cat.name }}</div>
 <div class="cat-meta">
 <span class="cat-slug">{{ cat.slug }}</span>
 <span class="cat-count">{{ cat.services_count }} خدمة</span>
 </div>
 </div>
 <div class="cat-actions">
 <button class="cat-btn cat-btn--edit" @click="startEdit(cat)" title="تعديل">
 <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
 </button>
 <button 
    class="cat-btn cat-btn--del"
    @click="confirmDelete(cat)"
    :disabled="deleteId === cat.id"
    title="حذف"
  >
    <span v-if="deleteId === cat.id" class="bsp-sm">...</span>
    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
    </svg>
  </button>
 </div>
 </div>
 </TransitionGroup>
 </div>

 </div>

 <!-- Delete Confirm Modal -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="delModal.open" class="modal-overlay" @mousedown.self="delModal.open = false">
 <div class="modal modal--sm">
 <div class="modal-hdr"><h2> حذف التصنيف</h2><button class="mcls" @click="delModal.open = false">✕</button></div>
 <div class="modal-body">
 <div class="del-warn">
 <p>هل تريد حذف التصنيف:</p>
 <p class="del-name">{{ delModal.cat?.icon }} «{{ delModal.cat?.name }}»</p>
 <p class="del-count" v-if="delModal.cat?.services_count > 0"> هذا التصنيف يحتوي على <strong>{{ delModal.cat.services_count }}</strong> خدمة ولا يمكن حذفه.
 </p>
 </div>
 </div>
 <div class="modal-ftr">
 <button class="mbtn mbtn--ghost" @click="delModal.open = false">إلغاء</button>
 <button class="mbtn mbtn--danger" @click="submitDelete"
 :disabled="!!deleteId || (delModal.cat?.services_count > 0)">
 <span v-if="deleteId" class="bsp"></span> {{ deleteId ? 'جاري الحذف...' : ' تأكيد الحذف' }}
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

const categories = ref([])
const loading = ref(false)
const submitting = ref(false)
const deleteId = ref(null)
const editing = ref(null)
const toast = reactive({ msg:'', type:'success' })
const delModal = reactive({ open:false, cat:null })

const form = reactive({ name:'', icon:'' })
const errors = reactive({ name:'' })

let tTimer = null
function showToast(msg, type='success') {
 toast.msg = msg; toast.type = type
 clearTimeout(tTimer); tTimer = setTimeout(() => { toast.msg = '' }, 4000)
}

async function loadCategories() {
 loading.value = true
 try {
 const { data } = await adminApi.getCategories()
 categories.value = data.data
 } catch { showToast('تعذّر تحميل التصنيفات', 'error') }
 finally { loading.value = false }
}

function startEdit(cat) {
 editing.value = cat
 form.name = cat.name
 form.icon = cat.icon ?? ''
 window.scrollTo({ top: 0, behavior: 'smooth' })
}

function cancelEdit() {
 editing.value = null
 form.name = ''; form.icon = ''
 errors.name = ''
}

function validate() {
 errors.name = ''
 if (!form.name.trim() || form.name.trim().length < 2) {
 errors.name = 'اسم التصنيف يجب أن يكون حرفين على الأقل'
 return false
 }
 return true
}

async function submitForm() {
 if (!validate()) return
 submitting.value = true
 try {
 const payload = { name: form.name.trim(), icon: form.icon.trim() || null }
 if (editing.value) {
 const { data } = await adminApi.updateCategory(editing.value.id, payload)
 const idx = categories.value.findIndex(c => c.id === editing.value.id)
 if (idx !== -1) categories.value[idx] = { ...categories.value[idx], ...data.data }
 showToast('تم تحديث التصنيف بنجاح ')
 cancelEdit()
 } else {
 const { data } = await adminApi.createCategory(payload)
 categories.value.unshift(data.data)
 showToast(`تم إضافة التصنيف «${data.data.name}» بنجاح `)
 form.name = ''; form.icon = ''
 }
 } catch (e) {
 const msg = e.response?.data?.errors?.name?.[0] ?? e.response?.data?.message ?? 'حدث خطأ'
 errors.name = msg
 } finally { submitting.value = false }
}

function confirmDelete(cat) {
 delModal.cat = cat; delModal.open = true
 document.body.style.overflow = 'hidden'
}

async function submitDelete() {
 if (delModal.cat?.services_count > 0) return
 deleteId.value = delModal.cat.id
 try {
 await adminApi.deleteCategory(delModal.cat.id)
 categories.value = categories.value.filter(c => c.id !== delModal.cat.id)
 delModal.open = false; document.body.style.overflow = ''
 showToast(`تم حذف التصنيف «${delModal.cat.name}» بنجاح`)
 if (editing.value?.id === delModal.cat.id) cancelEdit()
 } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ', 'error') }
 finally { deleteId.value = null }
}

onMounted(loadCategories)
</script>

<style scoped> .admin-categories { display:flex; flex-direction:column; gap:22px; }
.page-hdr { display:flex; align-items:flex-start; justify-content:space-between; }
.page-title { font-family:var(--font-display); font-size:22px; font-weight:800; margin-bottom:4px; }
.page-sub { font-size:14px; color:var(--color-text-3); }

.layout-grid { display:grid; grid-template-columns:340px 1fr; gap:20px; align-items:start; }

/* Form Panel */
.form-panel { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; padding:24px; display:flex; flex-direction:column; gap:18px; position:sticky; top:80px; }
.form-panel__title { font-family:var(--font-display); font-size:16px; font-weight:700; }

.field { display:flex; flex-direction:column; gap:7px; }
.field--err .finput { border-color:#EF4444; }
.flabel { font-size:14px; font-weight:600; color:var(--color-text-2); }
.req { color:#EF4444; }
.ferr { font-size:12px; color:#EF4444; }
.finput { height:44px; padding:0 14px; background:rgb(80, 109, 190); border:1px solid var(--color-border); border-radius:10px; color:white; font-family:var(--font-body); font-size:14px; outline:none; width:100%; transition:border-color .2s,box-shadow .2s; }
.finput:focus { border-color:rgb(80, 80, 226); box-shadow:0 0 0 3px var(--color-primary-glow); }
.icon-input-wrap { display:flex; align-items:center; gap:10px; }
.icon-preview { font-size:28px; flex-shrink:0; width:44px; height:44px; display:flex; align-items:center; justify-content:center; background:rgb(80, 109, 190); border:1px solid var(--color-border); border-radius:10px; }
.finput--icon { flex:1; }
.form-actions { display:flex; gap:10px; flex-direction:column; }
.submit-btn { width:100%; padding:12px; border-radius:10px; background:rgb(80, 109, 190); border:none; color:white; font-family:var(--font-display); font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 0 20px rgba(99,102,241,.35); transition:opacity .2s,transform .15s; }
.submit-btn:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
.submit-btn:disabled { opacity:.55; cursor:not-allowed; }
.cancel-btn { width:100%; padding:11px; border-radius:10px; background:none; border:1px solid var(--color-border); color:var(--color-text-2); font-family:var(--font-body); font-size:14px; cursor:pointer; transition:border-color .2s,color .2s; }
.cancel-btn:hover { border-color:var(--color-border-hover); color:var(--color-text); }
input::placeholder {
  color: #bccade !important;
}
/* List Panel */
.list-panel { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; overflow:hidden; }
.list-panel__header { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--color-border); }
.list-panel__title { font-family:var(--font-display); font-size:16px; font-weight:700; }
.list-count { font-size:13px; color:white; background:rgb(80, 109, 190); padding:3px 12px; border-radius:20px; }

/* Skeleton */
.list-loading { display:flex; flex-direction:column; gap:0; }
.sk-row { display:flex; align-items:center; gap:14px; padding:16px 22px; border-bottom:1px solid var(--color-border); }
.sk { background:linear-gradient(90deg,var(--color-bg-card) 25%,var(--color-bg-card-hover) 50%,var(--color-bg-card) 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:6px; }
@keyframes shimmer { to { background-position:-200% 0; } }
.sk--icon-sm { width:36px; height:36px; border-radius:10px; flex-shrink:0; }
.sk-body { flex:1; display:flex; flex-direction:column; }
.sk--ln { height:10px; }
.sk--btn { width:60px; height:28px; border-radius:8px; }

.list-empty { display:flex; flex-direction:column; align-items:center; gap:10px; padding:50px; color:var(--color-text-3); }
.list-empty span { font-size:40px; }

/* Category items */
.cat-list { display:flex; flex-direction:column; }
.cat-item { display:flex; align-items:center; gap:14px; padding:14px 22px; border-bottom:1px solid var(--color-border); transition:background .15s; }
.cat-item:last-child { border-bottom:none; }
.cat-item:hover { background:rgba(99,102,241,.04); }
.cat-item--editing { background:rgba(99,102,241,.07); border-right:3px solid var(--color-primary); }
.cat-icon-wrap { width:40px; height:40px; border-radius:10px; background:var(--color-bg-2); display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.cat-info { flex:1; min-width:0; }
.cat-name { font-size:14px; font-weight:600; color:var(--color-text); margin-bottom:3px; }
.cat-meta { display:flex; gap:10px; }
.cat-slug { font-size:11px; color:var(--color-text-3); font-family:monospace; }
.cat-count { font-size:11px; color:white; font-weight:600; }
.cat-actions { display:flex; gap:6px; flex-shrink:0; }
.cat-btn { width:32px; height:32px; border-radius:8px; border:none; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; transition:background .15s,transform .15s; }
.cat-btn:disabled { opacity:.5; cursor:not-allowed; }
.cat-btn--edit { background:rgba(99,102,241,.1); }
.cat-btn--edit:hover:not(:disabled) { background:rgba(99,102,241,.2); transform:scale(.95); }
.cat-btn--del { background:rgba(233, 10, 10, 0.1); color: #DC2626; }
.cat-btn--del:hover:not(:disabled) { background:rgba(239,68,68,.2); transform:scale(.95); }

/* Cat list transition */
.cat-list-enter-active { transition:opacity .25s,transform .25s; }
.cat-list-enter-from { opacity:0; transform:translateX(10px); }
.cat-list-leave-active { transition:opacity .15s; position:absolute; width:100%; }
.cat-list-leave-to { opacity:0; }

/* Spinner */
.bsp { width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:white; border-radius:50%; animation:spin .7s linear infinite; display:inline-block; flex-shrink:0; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Modal */
.modal-overlay { position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.75); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; padding:20px; }
.modal { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:20px; width:100%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,.6); }
.modal--sm { max-width:420px; }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--color-border); flex-shrink:0; }
.modal-hdr h2 { font-family:var(--font-display); font-size:16px; font-weight:800; }
.mcls { background:none; border:none; cursor:pointer; font-size:16px; color:var(--color-text-3); padding:4px 8px; border-radius:6px; transition:background .15s; }
.mcls:hover { background:rgba(239,68,68,.1); color:#EF4444; }
.modal-body { flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:14px; }
.modal-ftr { display:flex; gap:10px; padding:16px 24px; border-top:1px solid var(--color-border); flex-shrink:0; }
.del-warn { display:flex; flex-direction:column; gap:8px; text-align:center; font-size:14px; color:var(--color-text-2); }
.del-name { font-family:var(--font-display); font-size:18px; font-weight:800; color:var(--color-text); }
.del-count { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); border-radius:10px; padding:10px 14px; font-size:13px; color:#EF4444; }
.mbtn { padding:10px 22px; border-radius:10px; border:none; cursor:pointer; font-family:var(--font-display); font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; transition:opacity .2s,transform .15s; }
.mbtn:disabled { opacity:.55; cursor:not-allowed; }
.mbtn--danger { background:linear-gradient(135deg,#EF4444,#DC2626); color:white; }
.mbtn--danger:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
.mbtn--ghost { background:var(--color-bg-2); border:1px solid var(--color-border); color:var(--color-text-2); }
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

@media (max-width:900px) { .layout-grid { grid-template-columns:1fr; } .form-panel { position:static; } }
</style>
