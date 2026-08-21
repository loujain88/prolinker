<template>
 <div class="admin-fin" dir="rtl">

 <!-- Tabs -->
 <div class="tabs">
 <button v-for="t in tabs" :key="t.key" class="tab" :class="{ active: activeTab === t.key }" @click="activeTab = t.key"> {{ t.icon }} {{ t.label }}
 <span v-if="t.count" class="tab-count">{{ t.count }}</span>
 </button>
 </div>

 <!-- ══════ DEPOSITS ══════ -->
 <div v-show="activeTab === 'deposits'" class="tbl-section">
 <div class="tbl-header">
 <h2 class="tbl-title"> طلبات الإيداع</h2>
 <div class="tbl-controls">
 <select v-model="dFilter" class="flt-select" @change="loadDeposits">
 <option value="pending">معلقة</option><option value="approved">مقبولة</option>
 <option value="rejected">مرفوضة</option><option value="">الكل</option>
 </select>
 <button class="refresh-btn" @click="loadDeposits" :disabled="dLoading"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg></button>
 </div>
 </div>

 <div v-if="dLoading" class="tbl-state"><div class="spinner"></div> جاري التحميل...</div>
 <div v-else-if="deposits.length === 0" class="tbl-state tbl-empty"><span></span><p>لا توجد طلبات إيداع</p></div>

 <div v-else class="tbl-wrap">
 <table class="dtbl">
 <thead><tr><th>#</th><th>العميل</th><th>المبلغ</th><th>طريقة الدفع</th><th>الإيصال</th><th>التاريخ</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
 <tbody>
 <tr v-for="d in deposits" :key="d.id" class="drow">
 <td class="td-id">#{{ d.id }}</td>
 <td>
 <div class="ucell">
 <div class="uav">{{ d.user?.name?.[0] }}</div>
 <div><div class="uname">{{ d.user?.name }}</div><div class="uemail">{{ d.user?.email }}</div><div class="ubal">رصيده: ${{ d.user?.wallet_balance }}</div></div>
 </div>
 </td>
 <td><span class="amt amt--in">${{ d.amount }}</span></td>
 <td><span class="mtag">{{ mLabel(d.payment_method) }}</span></td>
 <td>
 <button v-if="d.has_receipt" class="rec-btn" @click="viewReceipt(d.id, 'deposit')" :disabled="receiptId === d.id"> {{ receiptId === d.id ? '...' : ' عرض الإيصال' }}
 </button>
 <span v-else class="no-rec">لا يوجد</span>
 </td>
 <td class="td-date">{{ fdate(d.created_at) }}</td>
 <td><span class="sbadge" :class="`s--${d.status}`">{{ sLabel(d.status) }}</span></td>
 <td>
 <div class="abts" v-if="d.status === 'pending'">
 <button class="abt abt--ok" @click="approveDeposit(d)" :disabled="actionId === d.id"> موافقة</button>
 <button class="abt abt--no" @click="openReject('deposit', d)" :disabled="actionId === d.id"> رفض</button>
 </div>
 <span v-else class="done-label">{{ d.status === 'approved' ? ' مقبول' : ' مرفوض' }}</span>
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>

 <!-- ══════ WITHDRAWALS ══════ -->
 <div v-show="activeTab === 'withdrawals'" class="tbl-section">
 <div class="tbl-header">
 <h2 class="tbl-title"> طلبات السحب</h2>
 <div class="tbl-controls">
 <select v-model="wFilter" class="flt-select" @change="loadWithdrawals">
 <option value="pending">معلقة</option><option value="approved">مقبولة</option>
 <option value="rejected">مرفوضة</option><option value="">الكل</option>
 </select>
 <button class="refresh-btn" @click="loadWithdrawals" :disabled="wLoading"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg></button>
 </div>
 </div>

 <div v-if="wLoading" class="tbl-state"><div class="spinner"></div> جاري التحميل...</div>
 <div v-else-if="withdrawals.length === 0" class="tbl-state tbl-empty"><span></span><p>لا توجد طلبات سحب</p></div>

 <div v-else class="tbl-wrap">
 <table class="dtbl">
 <thead><tr><th>#</th><th>البائع</th><th>المبلغ</th><th>بيانات الدفع</th><th>التاريخ</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
 <tbody>
 <tr v-for="w in withdrawals" :key="w.id" class="drow">
 <td class="td-id">#{{ w.id }}</td>
 <td>
 <div class="ucell">
 <div class="uav">{{ w.user?.name?.[0] }}</div>
 <div><div class="uname">{{ w.user?.name }}</div><div class="uemail">{{ w.user?.email }}</div></div>
 </div>
 </td>
 <td><span class="amt amt--out">${{ w.amount }}</span></td>
 <td>
 <div><span class="mtag">{{ mLabel(w.payout_method) }}</span></div>
 <div class="pinfo" v-if="w.payout_details">
 <span v-if="w.payout_details.account_number">{{ w.payout_details.account_name }} · {{ w.payout_details.account_number }}</span>
 <span v-if="w.payout_details.paypal_email">{{ w.payout_details.paypal_email }}</span>
 </div>
 </td>
 <td class="td-date">{{ fdate(w.created_at) }}</td>
 <td><span class="sbadge" :class="`s--${w.status}`">{{ sLabel(w.status) }}</span></td>
 <td>
 <div class="abts" v-if="w.status === 'pending'">
 <button class="rec-btn" @click="viewReceipt(w.id, 'payout_image')" :disabled="receiptId === w.id"> {{ receiptId === w.id ? '...' : ' صورة الاستلام' }}
 </button>
 <button class="abt abt--ok" @click="openApproveWd(w)" :disabled="actionId === w.id"> تم التحويل</button>
 <button class="abt abt--no" @click="openReject('withdrawal', w)" :disabled="actionId === w.id"> رفض</button>
 </div>
 <template v-else>
 <button v-if="w.status === 'approved' && w.has_receipt" class="rec-btn" @click="viewReceipt(w.id, 'withdrawal')" :disabled="receiptId === w.id"> {{ receiptId === w.id ? '...' : ' عرض الإيصال' }}
 </button>
 <span v-else class="done-label">{{ w.status === 'approved' ? ' مكتمل' : ' مرفوض' }}</span>
 </template>
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>

 <!-- Receipt Modal -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="receiptModal.open" class="modal-overlay" @mousedown.self="receiptModal.open = false">
 <div class="modal modal--receipt">
 <div class="modal-hdr"><h2> إيصال الإيداع #{{ receiptModal.id }}</h2><button class="mcls" @click="receiptModal.open = false">✕</button></div>
 <div class="modal-body modal-body--center">
 <div v-if="receiptModal.loading" class="spinner"></div>
 <img v-else-if="receiptModal.url && !receiptModal.isPdf" :src="receiptModal.url" class="rec-img" alt="إيصال" />
 <a v-else-if="receiptModal.url && receiptModal.isPdf" :href="receiptModal.url" target="_blank" class="pdf-btn"> فتح PDF</a>
 <p v-else class="err-txt">تعذّر تحميل الإيصال.</p>
 <p class="exp-note">⏱ ينتهي الرابط خلال 15 دقيقة</p>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <!-- Approve Withdrawal Modal (upload receipt) -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="awModal.open" class="modal-overlay" @mousedown.self="awModal.open = false">
 <div class="modal">
 <div class="modal-hdr"><h2> تأكيد تحويل السحب #{{ awModal.wd?.id }}</h2><button class="mcls" @click="awModal.open = false">✕</button></div>
 <div class="modal-body">
 <div class="info-box">
 <p>البائع: <strong>{{ awModal.wd?.user?.name }}</strong></p>
 <p>المبلغ: <strong class="red-amt">${{ awModal.wd?.amount }}</strong></p>
 </div>
 <div class="field">
 <label class="flabel">ارفع إيصال الدفع <span class="req">*</span></label>
 <div class="dropzone" :class="{ over: awModal.drag, filled: awModal.file }"
 @dragenter.prevent="awModal.drag = true" @dragover.prevent="awModal.drag = true"
 @dragleave.prevent="awModal.drag = false" @drop.prevent="onWdDrop"
 @click="$refs.wdInput.click()" role="button" tabindex="0">
 <input ref="wdInput" type="file" accept="image/jpeg,image/png,application/pdf" class="fi-hidden" @change="onWdFile" />
 <Transition name="fade" mode="out-in">
 <div v-if="!awModal.file" key="e" class="dz-empty">
 <div class="dz-icon" :class="{ bounce: awModal.drag }"></div>
 <p>اسحب الإيصال هنا أو <span class="dz-link">انقر للاختيار</span></p>
 <p class="dz-sub">JPG · PNG · PDF — بحد أقصى 5 MB</p>
 </div>
 <div v-else key="f" class="fp">
 <span>{{ awModal.file.type === 'application/pdf' ? '' : '' }}</span>
 <span class="fp-name">{{ awModal.file.name }}</span>
 <button type="button" class="fp-rm" @click.stop="awModal.file = null">✕</button>
 </div>
 </Transition>
 </div>
 <span v-if="awModal.fileErr" class="ferr">{{ awModal.fileErr }}</span>
 </div>
 <div class="field">
 <label class="flabel">ملاحظات (اختياري)</label>
 <textarea v-model="awModal.notes" class="ftxtarea" rows="2" placeholder="رقم المرجع أو ملاحظة..."></textarea>
 </div>
 </div>
 <div class="modal-ftr">
 <button class="mbtn mbtn--ghost" @click="awModal.open = false" :disabled="!!actionId">إلغاء</button>
 <button class="mbtn mbtn--ok" @click="submitWdApproval" :disabled="!!actionId">
 <span v-if="actionId" class="bsp"></span> {{ actionId ? 'جاري...' : ' تأكيد الموافقة' }}
 </button>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <!-- Reject Modal -->
 <Teleport to="body">
 <Transition name="modal">
 <div v-if="rjModal.open" class="modal-overlay" @mousedown.self="rjModal.open = false">
 <div class="modal modal--sm">
 <div class="modal-hdr"><h2> رفض الطلب</h2><button class="mcls" @click="rjModal.open = false">✕</button></div>
 <div class="modal-body">
 <div class="field" :class="{ 'field--err': rjModal.err }">
 <label class="flabel">سبب الرفض <span class="req">*</span></label>
 <textarea v-model="rjModal.notes" class="ftxtarea" rows="4"
 placeholder="اكتب سبباً واضحاً سيُرسَل للمستخدم..." @input="rjModal.err = ''"></textarea>
 <span v-if="rjModal.err" class="ferr">{{ rjModal.err }}</span>
 </div>
 </div>
 <div class="modal-ftr">
 <button class="mbtn mbtn--ghost" @click="rjModal.open = false">إلغاء</button>
 <button class="mbtn mbtn--danger" @click="submitReject" :disabled="!!actionId">
 <span v-if="actionId" class="bsp"></span> {{ actionId ? 'جاري...' : 'تأكيد الرفض' }}
 </button>
 </div>
 </div>
 </div>
 </Transition>
 </Teleport>

 <!-- Toast -->
 <Transition name="toast">
 <div v-if="toast.msg" class="toast" :class="`toast--${toast.type}`">{{ toast.msg }}</div>
 </Transition>

 </div>
</template>

<script setup> import { ref, reactive, computed, onMounted } from 'vue'
import adminApi from '@/composables/useAdminApi'

const deposits = ref([])
const withdrawals = ref([])
const dLoading = ref(false)
const wLoading = ref(false)
const dFilter = ref('pending')
const wFilter = ref('pending')
const activeTab = ref('deposits')
const actionId = ref(null)
const receiptId = ref(null)
const wdInput = ref(null)

const tabs = computed(() => [
 { key:'deposits', icon:'', label:'الإيداعات', count: deposits.value.filter(d=>d.status==='pending').length },
 { key:'withdrawals', icon:'', label:'السحوبات', count: withdrawals.value.filter(w=>w.status==='pending').length },
])

const toast = reactive({ msg:'', type:'success' })
let tTimer = null
function showToast(msg, type='success') {
 toast.msg = msg; toast.type = type; clearTimeout(tTimer)
 tTimer = setTimeout(() => { toast.msg = '' }, 4500)
}

const receiptModal = reactive({ open:false, id:null, url:null, isPdf:false, loading:false })
const awModal = reactive({ open:false, wd:null, file:null, notes:'', drag:false, fileErr:'' })
const rjModal = reactive({ open:false, type:null, item:null, notes:'', err:'' })

async function loadDeposits() {
 dLoading.value = true
 try {
 const { data } = dFilter.value === 'pending' ? await adminApi.getPendingDeposits() : await adminApi.getAllDeposits({ status: dFilter.value })
 deposits.value = data.data ?? data
 } catch { showToast('تعذّر تحميل الإيداعات', 'error') }
 finally { dLoading.value = false }
}

async function loadWithdrawals() {
 wLoading.value = true
 try {
 const { data } = wFilter.value === 'pending' ? await adminApi.getPendingWithdrawals() : await adminApi.getAllWithdrawals({ status: wFilter.value })
 withdrawals.value = data.data ?? data
 } catch { showToast('تعذّر تحميل السحوبات', 'error') }
 finally { wLoading.value = false }
}

async function viewReceipt(id, type = 'deposit') {
 receiptId.value = id
 Object.assign(receiptModal, { open:true, id, url:null, isPdf:false, loading:true })
 try {
 const { data } = type === 'withdrawal'
 ? await adminApi.getWithdrawalReceipt(id)
 : type === 'payout_image'
 ? await adminApi.getWithdrawalPayoutImage(id)
 : await adminApi.getDepositReceipt(id)
 receiptModal.url = data.url
 receiptModal.isPdf = data.url?.includes('.pdf') || data.url?.includes('pdf')
 } catch { receiptModal.url = null }
 finally { receiptModal.loading = false; receiptId.value = null }
}

async function approveDeposit(dep) {
 if (!confirm(`هل تؤكد الموافقة على إيداع $${dep.amount} للعميل «${dep.user?.name}»؟`)) return
 actionId.value = dep.id
 try {
 await adminApi.approveDeposit(dep.id)
 showToast(` تمت الموافقة — تم شحن محفظة ${dep.user?.name}`)
 loadDeposits()
 } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ', 'error') }
 finally { actionId.value = null }
}

function openApproveWd(wd) {
 Object.assign(awModal, { open:true, wd, file:null, notes:'', drag:false, fileErr:'' })
 document.body.style.overflow = 'hidden'
}
function onWdDrop(e) { awModal.drag = false; const f = e.dataTransfer.files[0]; if (f) setWdFile(f) }
function onWdFile(e) { const f = e.target.files[0]; if (f) setWdFile(f) }
function setWdFile(f) {
 if (f.size > 5*1024*1024) { awModal.fileErr = 'الحجم يتجاوز 5 MB'; return }
 awModal.fileErr = ''; awModal.file = f
}
async function submitWdApproval() {
 if (!awModal.file) { awModal.fileErr = 'يجب رفع إيصال الدفع'; return }
 actionId.value = awModal.wd.id
 try {
 const fd = new FormData()
 fd.append('transaction_receipt', awModal.file)
 if (awModal.notes) fd.append('notes', awModal.notes)
 await adminApi.approveWithdrawal(awModal.wd.id, fd)
 awModal.open = false; document.body.style.overflow = ''
 showToast(` تم تأكيد السحب — تم إشعار ${awModal.wd.user?.name}`)
 loadWithdrawals()
 } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ', 'error') }
 finally { actionId.value = null }
}

function openReject(type, item) {
 Object.assign(rjModal, { open:true, type, item, notes:'', err:'' })
 document.body.style.overflow = 'hidden'
}
async function submitReject() {
 if (!rjModal.notes.trim()) { rjModal.err = 'يجب كتابة سبب الرفض'; return }
 actionId.value = rjModal.item.id
 try {
 if (rjModal.type === 'deposit') { await adminApi.rejectDeposit(rjModal.item.id, rjModal.notes); loadDeposits() }
 else { await adminApi.rejectWithdrawal(rjModal.item.id, rjModal.notes); loadWithdrawals() }
 rjModal.open = false; document.body.style.overflow = ''
 showToast('تم الرفض وإرسال إشعار للمستخدم')
 } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ', 'error') }
 finally { actionId.value = null }
}

const sLabel = s => ({ pending:'معلق', approved:'مقبول', rejected:'مرفوض' }[s] ?? s)
const mLabel = m => ({ bank_transfer:'تحويل بنكي', cash:'كاش', paypal:'PayPal', wise:'Wise' }[m] ?? m)
function fdate(iso) { return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) }

onMounted(() => { loadDeposits(); loadWithdrawals() })
</script>

<style scoped> .admin-fin { display:flex; flex-direction:column; gap:22px; }
.tabs { display:flex; gap:4px; background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:12px; padding:4px; width:fit-content; }
.tab { display:flex; align-items:center; gap:6px; padding:9px 22px; border:none; border-radius:9px; background:none; cursor:pointer; font-family:var(--font-body); font-size:14px; font-weight:500; color:white; transition:all .2s; }
.tab.active { background:rgb(80, 109, 190); color:white; box-shadow:0 2px 10px rgba(55, 56, 144, 0.35); }
.tab-count { font-size:10px; font-weight:700; background:rgba(255,255,255,.25); padding:2px 7px; border-radius:20px; }

.tbl-section { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; overflow:hidden; }
.tbl-header { display:flex; align-items:center; justify-content:space-between; padding:16px 22px; border-bottom:1px solid var(--color-border); }
.tbl-title { font-family:var(--font-display); font-size:16px; font-weight:700; }
.tbl-controls { display:flex; gap:8px; }
.flt-select { background:rgb(80, 109, 190); border:1px solid var(--color-border); border-radius:8px; color:var(--color-text-2); font-family:var(--font-body); font-size:13px; padding:7px 12px; cursor:pointer; outline:none; }
.refresh-btn { background:rgb(80, 109, 190); border:1px solid var(--color-border); border-radius:8px; padding:7px 10px; cursor:pointer; font-size:14px; color:white; transition:all .15s; }
.refresh-btn:hover:not(:disabled) { border-color:rgb(128, 164, 213); color:rgb(128, 164, 213); }
.tbl-state { display:flex; align-items:center; justify-content:center; gap:12px; padding:60px; color:var(--color-text-3); font-size:14px; }
.tbl-empty { flex-direction:column; }
.tbl-empty span { font-size:40px; }
.tbl-wrap { overflow-x:auto; }

.dtbl { width:100%; border-collapse:collapse; font-size:13px; }
.dtbl thead tr { border-bottom:1px solid var(--color-border); background:rgba(0,0,0,.1); }
.dtbl th { padding:12px 16px; text-align:right; font-size:12px; font-weight:600; color:white; white-space:nowrap; }
.drow { border-bottom:1px solid var(--color-border); transition:background .15s; }
.drow:last-child { border-bottom:none; }
.drow:hover { background:rgba(99,102,241,.04); }
.dtbl td { padding:13px 16px; vertical-align:middle; color:white; }
.td-id { font-family:var(--font-display); font-weight:700; color:var(--color-primary); }
.td-date { font-size:12px; white-space:nowrap; }
.ucell { display:flex; align-items:center; gap:10px; }
.uav { width:32px; height:32px; border-radius:50%; background:var(--grad-primary); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:white; flex-shrink:0; }
.uname { font-size:13px; font-weight:600; color:var(--color-text); }
.uemail { font-size:11px; color:var(--color-text-3); }
.ubal { font-size:11px; color:var(--color-primary); }
.amt { font-family:var(--font-display); font-size:16px; font-weight:800; }
.amt--in { color:#05be39; }
.amt--out { color:#EF4444; }
.mtag { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; color:var(--color-primary); }
.pinfo { font-size:11px; color:var(--color-text-3); margin-top:3px; }
.sbadge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.s--pending {  color:yellow; }
.s--approved {  color:green; }
.s--rejected {  color:red; }
.abts { display:flex; gap:6px; flex-wrap:wrap; }
.abt { padding:6px 14px; border-radius:20px; border:none; cursor:pointer; font-family:var(--font-body); font-size:12px; font-weight:600; transition:opacity .2s; white-space:nowrap; }
.abt:disabled { opacity:.5; cursor:not-allowed; }
.abt:hover:not(:disabled) { opacity:.85; }
.abt--ok { background:rgba(16,185,129,.15); color:#10B981; border:1px solid rgba(16,185,129,.3); }
.abt--no { background:rgba(239,68,68,.1); color:#EF4444; border:1px solid rgba(239,68,68,.25); }
.rec-btn { padding:6px 14px; border-radius:20px; background:rgba(99,102,241,.1); border:1px solid rgba(99,102,241,.25); color:var(--color-primary); font-family:var(--font-body); font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
.rec-btn:disabled { opacity:.5; }
.no-rec { font-size:11px; color:var(--color-text-3); }
.done-label { font-size:12px; color:white; }
.spinner { width:24px; height:24px; border:2px solid var(--color-border); border-top-color:var(--color-primary); border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Modals */
.modal-overlay { position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.75); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; padding:20px; }
.modal { background:var(--color-bg); border:1px solid var(--color-border); border-radius:20px; width:100%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,.6); }
.modal--sm { max-width:440px; }
.modal--receipt { max-width:700px; }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--color-border); flex-shrink:0; }
.modal-hdr h2 { font-family:var(--font-display); font-size:16px; font-weight:800; }
.mcls { background:none; border:none; cursor:pointer; font-size:16px; color:var(--color-text-3); padding:4px 8px; border-radius:6px; transition:background .15s; }
.mcls:hover { background:rgba(239,68,68,.1); color:#EF4444; }
.modal-body { flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:16px; }
.modal-body--center { align-items:center; justify-content:center; }
.modal-ftr { display:flex; gap:10px; padding:16px 24px; border-top:1px solid var(--color-border); flex-shrink:0; }
.rec-img { max-width:100%; max-height:500px; object-fit:contain; border-radius:10px; border:1px solid var(--color-border); }
.pdf-btn { display:inline-flex; align-items:center; gap:8px; padding:14px 24px; background:var(--grad-primary); color:white; text-decoration:none; border-radius:20px; font-weight:700; }
.exp-note { font-size:11px; color:var(--color-text-3); margin-top:8px; }
.err-txt { color:#EF4444; font-size:14px; }
.info-box { background:rgba(99,102,241,.07); border:1px solid rgba(99,102,241,.2); border-radius:10px; padding:14px 16px; font-size:14px; color:var(--color-text-2); display:flex; flex-direction:column; gap:4px; }
.red-amt { color:#EF4444; font-family:var(--font-display); font-size:18px; font-weight:800; }
.field { display:flex; flex-direction:column; gap:7px; }
.field--err .dropzone { border-color:#EF4444; }
.flabel { font-size:14px; font-weight:600; color:var(--color-text-2); }
.req { color:#EF4444; }
.ferr { font-size:12px; color:#EF4444; }
.ftxtarea { width:100%; padding:10px 14px; background:var(--color-bg-2); border:1px solid var(--color-border); border-radius:10px; color:var(--color-text); font-family:var(--font-body); font-size:14px; outline:none; resize:vertical; min-height:70px; transition:border-color .2s; }
.ftxtarea:focus { border-color:var(--color-primary); }
.dropzone { border:2px dashed var(--color-border); border-radius:12px; padding:24px; cursor:pointer; transition:all .2s; text-align:center; min-height:110px; display:flex; align-items:center; justify-content:center; position:relative; }
.dropzone.over { border-color:var(--color-primary); background:rgba(99,102,241,.06); }
.dropzone.filled { border-color:#10B981; border-style:solid; background:rgba(16,185,129,.04); }
.fi-hidden { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.dz-empty { display:flex; flex-direction:column; align-items:center; gap:6px; }
.dz-icon { font-size:30px; transition:transform .2s; }
.dz-icon.bounce { animation:bonce .5s ease infinite alternate; }
@keyframes bonce { to { transform:translateY(-5px); } }
.dz-link { color:var(--color-primary); font-weight:600; }
.dz-sub { font-size:12px; color:var(--color-text-3); }
.fp { display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--color-bg-2); border-radius:10px; width:100%; }
.fp-name { flex:1; font-size:13px; font-weight:600; color:var(--color-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fp-rm { background:none; border:none; cursor:pointer; color:var(--color-text-3); font-size:14px; padding:2px 6px; border-radius:4px; transition:background .15s; flex-shrink:0; }
.fp-rm:hover { background:rgba(239,68,68,.1); color:#EF4444; }
.mbtn { padding:10px 22px; border-radius:10px; border:none; cursor:pointer; font-family:var(--font-display); font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; transition:opacity .2s, transform .15s; }
.mbtn:disabled { opacity:.55; cursor:not-allowed; }
.mbtn--ok { background:linear-gradient(135deg,#10B981,#059669); color:white; box-shadow:0 0 16px rgba(16,185,129,.3); }
.mbtn--ok:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
.mbtn--danger { background:linear-gradient(135deg,#EF4444,#DC2626); color:white; }
.mbtn--ghost { background:var(--color-bg-2); border:1px solid var(--color-border); color:var(--color-text-2); }
.bsp { width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:white; border-radius:50%; animation:spin .7s linear infinite; display:inline-block; flex-shrink:0; }
.modal-enter-active,.modal-leave-active { transition:opacity .25s; }
.modal-enter-from,.modal-leave-to { opacity:0; }
.modal-enter-active .modal { animation:pop .3s cubic-bezier(.34,1.56,.64,1); }
@keyframes pop { from { transform:scale(.93) translateY(14px); } to { transform:scale(1); } }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; }
.fade-enter-from,.fade-leave-to { opacity:0; }
.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); border-radius:9999px; padding:13px 28px; font-size:14px; font-weight:600; box-shadow:0 4px 24px rgba(0,0,0,.35); z-index:600; white-space:nowrap; }
.toast--success { background:var(--color-bg-card); border:1px solid #10B981; color:#10B981; }
.toast--error { background:var(--color-bg-card); border:1px solid #EF4444; color:#EF4444; }
.toast-enter-active { transition:opacity .3s, transform .3s cubic-bezier(.34,1.56,.64,1); }
.toast-leave-active { transition:opacity .2s, transform .2s; }
.toast-enter-from { opacity:0; transform:translateX(-50%) translateY(16px); }
.toast-leave-to { opacity:0; transform:translateX(-50%) translateY(8px); }
</style>
