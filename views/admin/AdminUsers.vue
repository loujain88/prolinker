<template>
 <div class="admin-users" dir="rtl">

 <div class="page-hdr">
 <div><h2 class="page-title"> إدارة المستخدمين</h2><p class="page-sub">استعرض، حظر، أو احذف حسابات المستخدمين</p></div>
 </div>

 <!-- Filters -->
 <div class="filters-bar">
 <div class="srch-wrap">
 <span class="srch-icon"></span>
 <input v-model="search" type="text" class="srch-input"
 placeholder="ابحث بالاسم أو البريد الإلكتروني..."
 @input="debouncedLoad" />
 <button v-if="search" class="srch-clr" @click="search=''; loadUsers()">✕</button>
 </div>
 <div class="pill-group">
 <button v-for="f in roleFilters" :key="f.key" class="pill" :class="{ active: roleFilter===f.key }" @click="roleFilter=f.key; loadUsers()"> {{ f.icon }} {{ f.label }}
 </button>
 </div>
 <div class="pill-group">
 <button v-for="f in statusFilters" :key="f.key" class="pill" :class="{ active: statusFilter===f.key }" @click="statusFilter=f.key; loadUsers()"> {{ f.label }}
 </button>
 </div>
 </div>

 <!-- Loading -->
 <div v-if="loading" class="sk-list">
 <div v-for="i in 6" :key="i" class="sk-row">
 <div class="sk sk--av"></div>
 <div class="sk-body"><div class="sk sk--ln" style="width:45%"></div><div class="sk sk--ln" style="width:30%;margin-top:8px"></div></div>
 <div class="sk sk--bd"></div><div class="sk sk--bd"></div>
 </div>
 </div>

 <!-- Empty -->
 <div v-else-if="users.length === 0" class="empty-state">
 <span></span><h3>لا توجد نتائج</h3><p>جرب تغيير كلمة البحث أو الفلتر</p>
 </div>

 <!-- Table -->
 <div v-else class="tbl-card">
 <table class="dtbl">
 <thead>
 <tr><th>#</th><th>المستخدم</th><th>الدور</th><th>الرصيد</th><th>الحالة</th><th>التسجيل</th><th>الإجراءات</th></tr>
 </thead>
 <tbody>
 <tr v-for="u in users" :key="u.id" class="drow" :class="{ 'drow--blocked': !u.is_active }">
 <td class="td-id">#{{ u.id }}</td>
 <td>
 <div class="ucell">
 <div class="uav-wrap">
 <div class="uav">{{ u.name?.[0] }}</div>
 <span class="rdot" :class="`rdot--${u.role}`"></span>
 </div>
 <div><div class="uname">{{ u.name }}</div><div class="uemail">{{ u.email }}</div></div>
 </div>
 </td>
 <td><span class="rbadge" :class="`rb--${u.role}`">{{ rLabel(u.role) }}</span></td>
 <td><span class="bal">${{ u.profile?.wallet_balance ?? '—' }}</span></td>
 <td><span class="sbadge" :class="u.is_active ? 'sb--active' : 'sb--blocked'">{{ u.is_active ? '● نشط' : '● محظور' }}</span></td>
 <td class="td-date">{{ fdate(u.created_at) }}</td>
 <td>
 <div class="abts">
 <button v-if="u.is_active" class="abt abt--block" @click="blockUser(u)" :disabled="actionId===u.id"> حظر</button>
 <button v-else class="abt abt--unblock" @click="unblockUser(u)" :disabled="actionId===u.id"> رفع الحظر</button>
 <button class="abt abt--view" @click="openDetail(u)">
 <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
 </button>
 <button class="abt abt--del" @click="confirmDelete(u)" :disabled="actionId===u.id">
 <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
 </button>
 </div>
 </td>
 </tr>
 </tbody>
 </table>

 <div v-if="meta.last_page > 1" class="pagination">
 <button class="pg-btn" :disabled="meta.current_page<=1" @click="loadUsers(meta.current_page-1)">← السابق</button>
 <span class="pg-info">{{ meta.current_page }} / {{ meta.last_page }}</span>
 <button class="pg-btn" :disabled="meta.current_page>=meta.last_page" @click="loadUsers(meta.current_page+1)">التالي →</button>
 </div>
 </div>

 <!-- Detail Modal -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="detailModal.open" class="modal-overlay" @mousedown.self="detailModal.open=false">
 <div class="modal modal--detail">
 <div class="modal-hdr"><h2> تفاصيل المستخدم</h2><button class="mcls" @click="detailModal.open=false">✕</button></div>
 <div class="modal-body" v-if="detailModal.user">
 <div class="detail-hero">
 <div class="det-av">{{ detailModal.user.name?.[0] }}</div>
 <div>
 <div class="det-name">{{ detailModal.user.name }}</div>
 <div class="det-email">{{ detailModal.user.email }}</div>
 <span class="rbadge" :class="`rb--${detailModal.user.role}`">{{ rLabel(detailModal.user.role) }}</span>
 </div>
 </div>
 <div class="det-grid" v-if="detailModal.user.profile">
 <div class="det-item" v-for="(item, key) in detailFields" :key="key">
 <span class="det-lbl">{{ item.label }}</span>
 <span class="det-val">{{ item.value }}</span>
 </div>
 </div>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <!-- Delete Confirm Modal -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="delModal.open" class="modal-overlay" @mousedown.self="delModal.open=false">
 <div class="modal modal--sm">
 <div class="modal-hdr"><h2> حذف المستخدم نهائياً</h2><button class="mcls" @click="delModal.open=false">✕</button></div>
 <div class="modal-body">
 <div class="del-warn">
 <p>أنت على وشك حذف المستخدم:</p>
 <p class="del-name">«{{ delModal.user?.name }}»</p>
 <p class="del-email">{{ delModal.user?.email }}</p>
 <div class="del-alert"> هذا الإجراء لا يمكن التراجع عنه. السجلات المالية ستُحفظ.</div>
 </div>
 <div class="field">
 <label class="flabel">اكتب <strong>{{ delModal.user?.name }}</strong> للتأكيد:</label>
 <input v-model="delModal.confirm" type="text" class="finput" :placeholder="delModal.user?.name" @input="delModal.err=''" />
 <span v-if="delModal.err" class="ferr">{{ delModal.err }}</span>
 </div>
 </div>
 <div class="modal-ftr">
 <button class="mbtn mbtn--ghost" @click="delModal.open=false">إلغاء</button>
 <button class="mbtn mbtn--danger" @click="submitDelete"
 :disabled="!!actionId || delModal.confirm !== delModal.user?.name">
 <span v-if="actionId" class="bsp"></span> {{ actionId ? 'جاري الحذف...' : ' حذف نهائي' }}
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

<script setup> import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import adminApi from '@/composables/useAdminApi'

const users = ref([])
const loading = ref(false)
const actionId = ref(null)
const search = ref('')
const route = useRoute()
const roleFilter = ref(route.query.role ?? '')
const statusFilter = ref(route.query.status === 'blocked' ? 'false' : (route.query.status ?? ''))
const meta = ref({ total:0, current_page:1, last_page:1 })
const toast = reactive({ msg:'', type:'success' })
let tTimer = null
function showToast(msg, type='success') { toast.msg=msg; toast.type=type; clearTimeout(tTimer); tTimer=setTimeout(()=>{toast.msg=''},4500) }

const roleFilters = [{ key:'',icon:'',label:'الكل'},{key:'client',icon:'',label:'عملاء'},{key:'seller',icon:'',label:'مستقلون'},{key:'admin',icon:'',label:'مسؤولون'}]
const statusFilters = [{ key:'',label:'الكل'},{key:'true',label:'● نشط'},{key:'false',label:'● محظور'}]

const detailModal = reactive({ open:false, user:null })
const detailFields = computed(() => {
 const p = detailModal.user?.profile; if (!p) return {}
 const f = { wallet: { label:'الرصيد', value:`$${p.wallet_balance}` } }
 if (p.average_rating !== undefined) f.rating = { label:'متوسط التقييم', value: p.average_rating }
 if (p.total_orders_completed !== undefined) f.orders = { label:'الطلبات المكتملة', value: p.total_orders_completed }
 if (p.is_verified !== undefined) f.verified = { label:'موثق', value: p.is_verified ? 'نعم' : 'لا' }
 if (p.country) f.country = { label:'الدولة', value: p.country }
 if (p.company_name) f.company = { label:'الشركة', value: p.company_name }
 return f
})

const delModal = reactive({ open:false, user:null, confirm:'', err:'' })

let debTimer = null
function debouncedLoad() { clearTimeout(debTimer); debTimer = setTimeout(()=>loadUsers(), 400) }

async function loadUsers(page=1) {
 loading.value = true
 try {
 const params = { page }
 if (search.value.trim()) params.search = search.value.trim()
 if (roleFilter.value) params.role = roleFilter.value
 if (statusFilter.value!=='') params.is_active = statusFilter.value
 const { data } = await adminApi.getUsers(params)
 users.value = data.data; meta.value = data.meta
 } catch { showToast('تعذّر تحميل المستخدمين','error') }
 finally { loading.value = false }
}

async function openDetail(u) {
 detailModal.user = u; detailModal.open = true
 try { const { data } = await adminApi.getUser(u.id); detailModal.user = data.data } catch {}
}

async function blockUser(u) {
 if (!confirm(`هل تريد حظر «${u.name}»؟ سيتم تسجيل خروجه فوراً.`)) return
 actionId.value = u.id
 try { await adminApi.blockUser(u.id); u.is_active = false; showToast(`تم حظر «${u.name}» `) }
 catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ','error') }
 finally { actionId.value = null }
}

async function unblockUser(u) {
 actionId.value = u.id
 try { await adminApi.unblockUser(u.id); u.is_active = true; showToast(`تم رفع الحظر عن «${u.name}» `) }
 catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ','error') }
 finally { actionId.value = null }
}

function confirmDelete(u) { Object.assign(delModal,{ open:true, user:u, confirm:'', err:'' }); document.body.style.overflow='hidden' }

async function submitDelete() {
 if (delModal.confirm !== delModal.user?.name) { delModal.err='الاسم غير مطابق'; return }
 actionId.value = delModal.user.id
 try {
 await adminApi.deleteUser(delModal.user.id)
 users.value = users.value.filter(u => u.id !== delModal.user.id)
 delModal.open=false; document.body.style.overflow=''
 showToast(`تم حذف «${delModal.user.name}» نهائياً`)
 } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ','error') }
 finally { actionId.value = null }
}

const rLabel = r => ({ client:'عميل', seller:'مستقل', admin:'مسؤول' }[r] ?? r)
function fdate(iso) { return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn',{year:'numeric',month:'short',day:'numeric'}) }

onMounted(()=>loadUsers())
</script>

<style scoped> .admin-users { display:flex; flex-direction:column; gap:20px; }
.page-hdr { display:flex; align-items:flex-start; justify-content:space-between; }
.page-title { font-family:var(--font-display); font-size:22px; font-weight:800; margin-bottom:4px; }
.page-sub { font-size:14px; color:var(--color-text-3); }
.filters-bar { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
.srch-wrap { position:relative; display:flex; align-items:center; flex:1; min-width:240px; }
.srch-icon { position:absolute; right:14px; font-size:15px; pointer-events:none; }
.srch-input { width:100%; height:42px; padding:0 42px 0 36px; background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:10px; color:var(--color-text); font-family:var(--font-body); font-size:14px; outline:none; transition:border-color .2s,box-shadow .2s; }
.srch-input:focus { border-color:var(--color-primary); box-shadow:0 0 0 3px var(--color-primary-glow); }
.srch-input::placeholder { color:var(--color-text-3); }
.srch-clr { position:absolute; left:10px; background:none; border:none; cursor:pointer; color:var(--color-text-3); font-size:13px; padding:2px 6px; border-radius:4px; }
.pill-group { display:flex; gap:6px; flex-wrap:wrap; }
.pill { padding:7px 14px; border-radius:20px; border:1px solid var(--color-border); background:none; color:var(--color-text-3); font-family:var(--font-body); font-size:13px; cursor:pointer; transition:all .2s; white-space:nowrap; }
.pill:hover { border-color:var(--color-border-hover); color:var(--color-text); }
.pill.active { background:rgb(80, 109, 190); border-color:transparent; color:white; box-shadow:0 2px 8px rgba(99,102,241,.3); }
.sk-list { display:flex; flex-direction:column; gap:10px; }
.sk-row { display:flex; align-items:center; gap:14px; padding:16px 20px; background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:12px; }
.sk { background:linear-gradient(90deg,var(--color-bg-card) 25%,var(--color-bg-card-hover) 50%,var(--color-bg-card) 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:6px; }
@keyframes shimmer { to { background-position:-200% 0; } }
.sk--av { width:40px; height:40px; border-radius:50%; flex-shrink:0; }
.sk-body { flex:1; display:flex; flex-direction:column; }
.sk--ln { height:11px; }
.sk--bd { width:70px; height:26px; border-radius:20px; }
.empty-state { display:flex; flex-direction:column; align-items:center; gap:12px; padding:60px; text-align:center; background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; }
.empty-state span { font-size:48px; }
.empty-state h3 { font-family:var(--font-display); font-size:18px; font-weight:700; }
.empty-state p { font-size:14px; color:var(--color-text-3); }
.tbl-card { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; overflow:hidden; }
.dtbl { width:100%; border-collapse:collapse; font-size:13px; }
.dtbl thead tr { border-bottom:1px solid var(--color-border); background:rgba(0,0,0,.1); }
.dtbl th { padding:12px 16px; text-align:right; font-size:12px; font-weight:600; color:var(--color-text-3); white-space:nowrap; }
.drow { border-bottom:1px solid var(--color-border); transition:background .15s; }
.drow:last-child { border-bottom:none; }
.drow:hover { background:rgba(99,102,241,.04); }
.drow--blocked { opacity:.65; background:rgba(239,68,68,.03); }
.dtbl td { padding:13px 16px; vertical-align:middle; }
.td-id { font-family:var(--font-display); font-weight:700; color:var(--color-text-2); }
.td-date { font-size:12px; color:var(--color-text-3); white-space:nowrap; }
.ucell { display:flex; align-items:center; gap:10px; }
.uav-wrap { position:relative; flex-shrink:0; }
.uav { width:36px; height:36px; border-radius:50%; background:var(--grad-primary); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:white; }
.rdot { position:absolute; bottom:-1px; left:-1px; width:10px; height:10px; border-radius:50%; border:2px solid var(--color-bg-card); }
.rdot--client { background:#6366F1; } .rdot--seller { background:#10B981; } .rdot--admin { background:#EF4444; }
.uname { font-size:13px; font-weight:600; color:var(--color-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }
.uemail { font-size:11px; color:var(--color-text-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }
.rbadge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.rb--client { background:rgba(99,102,241,.12); color:#3639f9; }
.rb--seller { background:rgba(16,185,129,.12); color:#08f4a5; }
.rb--admin { background:rgba(239,68,68,.12); color:#f11212; }
.sbadge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.sb--active { background:rgba(16,185,129,.12); color:#08f4a5; }
.sb--blocked { background:rgba(239,68,68,.12); color:#fa0a0a; }
.bal { font-family:var(--font-display); font-size:14px; font-weight:700; color:white; }
.abts { display:flex; gap:5px; flex-wrap:wrap; }
.abt { padding:5px 12px; border-radius:20px; border:none; cursor:pointer; font-family:var(--font-body); font-size:11px; font-weight:600; transition:opacity .2s,transform .15s; white-space:nowrap; }
.abt:disabled { opacity:.5; cursor:not-allowed; }
.abt:hover:not(:disabled) { opacity:.8; transform:scale(.97); }
.abt--block { background:rgba(239,68,68,.1); color:#EF4444; border:1px solid rgba(239,68,68,.25); }
.abt--unblock { background:rgba(16,185,129,.1); color:#10B981; border:1px solid rgba(16,185,129,.25); }
.abt--view { background:rgba(99,102,241,.1); color:var(--color-primary); border:1px solid rgba(99,102,241,.2); }
.abt--del { background:rgba(239,68,68,.08); color:#EF4444; border:1px solid rgba(239,68,68,.2); }
.pagination { display:flex; align-items:center; justify-content:center; gap:16px; padding:16px; border-top:1px solid var(--color-border); }
.pg-btn { padding:8px 18px; border-radius:20px; background:var(--color-bg-2); border:1px solid var(--color-border); color:var(--color-text-2); font-family:var(--font-body); font-size:13px; cursor:pointer; transition:border-color .2s,color .2s; }
.pg-btn:hover:not(:disabled) { border-color:var(--color-primary); color:var(--color-primary); }
.pg-btn:disabled { opacity:.4; cursor:not-allowed; }
.pg-info { font-size:13px; color:var(--color-text-3); }
.modal-overlay { position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.75); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; padding:20px; }
.modal { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:20px; width:100%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,.6); }
.modal--sm { max-width:460px; }
.modal--detail { max-width:500px; }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--color-border); flex-shrink:0; }
.modal-hdr h2 { font-family:var(--font-display); font-size:16px; font-weight:800; }
.mcls { background:none; border:none; cursor:pointer; font-size:16px; color:var(--color-text-3); padding:4px 8px; border-radius:6px; transition:background .15s; }
.mcls:hover { background:rgba(239,68,68,.1); color:#EF4444; }
.modal-body { flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:16px; }
.modal-ftr { display:flex; gap:10px; padding:16px 24px; border-top:1px solid var(--color-border); flex-shrink:0; }
.detail-hero { display:flex; align-items:center; gap:16px; }
.det-av { width:56px; height:56px; border-radius:50%; background:var(--grad-primary); display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:white; flex-shrink:0; }
.det-name { font-family:var(--font-display); font-size:18px; font-weight:700; margin-bottom:4px; }
.det-email { font-size:13px; color:var(--color-text-3); margin-bottom:6px; }
.det-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.det-item { background:var(--color-bg-2); border-radius:10px; padding:12px 14px; display:flex; flex-direction:column; gap:4px; }
.det-lbl { font-size:11px; color:var(--color-text-3); }
.det-val { font-size:15px; font-weight:700; color:var(--color-text); }
.del-warn { display:flex; flex-direction:column; gap:6px; text-align:center; font-size:14px; color:var(--color-text-2); }
.del-name { font-family:var(--font-display); font-size:17px; font-weight:800; color:var(--color-text); }
.del-email { font-size:12px; color:var(--color-text-3); }
.del-alert { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); border-radius:10px; padding:10px 14px; font-size:13px; color:#EF4444; margin-top:6px; }
.field { display:flex; flex-direction:column; gap:7px; }
.flabel { font-size:14px; font-weight:600; color:var(--color-text-2); }
.ferr { font-size:12px; color:#EF4444; }
.finput { height:44px; padding:0 14px; background:var(--color-bg-2); border:1px solid var(--color-border); border-radius:10px; color:var(--color-text); font-family:var(--font-body); font-size:14px; outline:none; width:100%; transition:border-color .2s; }
.finput:focus { border-color:var(--color-primary); }
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
@media (max-width:768px) { .filters-bar { flex-direction:column; } .uname,.uemail { max-width:100px; } }
</style>
