<template>
  <div class="admin-disputes" dir="rtl">

    <div class="page-hdr">
      <div>
        <h2 class="page-title">إدارة النزاعات</h2>
        <p class="page-sub">راجع النزاعات واتخذ قرار حاسم بشأن الأموال المحتجزة</p>
      </div>
      <div class="status-filter">
        <button v-for="f in filters" :key="f.key" class="fpill" :class="{ active: activeFilter===f.key }" @click="activeFilter=f.key; loadDisputes()">
          <span v-if="hasActiveAlert(f.key)" class="status-dot-red"></span>
          {{ f.icon }} {{ f.label }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="sk-list">
      <div v-for="i in 4" :key="i" class="sk-card">
        <div class="sk-top">
          <div class="sk sk--av"></div>
          <div class="sk sk--ln" style="width:45%"></div>
          <div class="sk sk--bd"></div>
        </div>
        <div class="sk sk--ln" style="width:70%;margin-top:10px"></div>
      </div>
    </div>

    <div v-else-if="disputes.length === 0" class="empty-state">
      <span></span>
      <h3>لا توجد نزاعات</h3>
      <p>كل شيء يسير على ما يرام</p>
    </div>

    <div v-else class="disputes-list">
      <div v-for="d in disputes" :key="d.id" class="dispute-card" :class="`dc--${d.status}`">

        <!-- Card header -->
        <div class="dc-header">
          <div class="dc-id">نزاع #{{ d.id }}</div>
          <span class="dc-badge" :class="`db--${d.status}`">
            <span v-if="d.status === 'open' || d.status === 'under_review'" class="badge-red-dot"></span>
            {{ dStatusLabel(d.status) }}
          </span>
          <span class="dc-opener">فُتح بواسطة: {{ d.opened_by === 'client' ? 'العميل' : 'المستقل' }}</span>
          <div class="dc-date">{{ fdate(d.created_at) }}</div>
        </div>

        <!-- Order info -->
        <div class="dc-order" v-if="d.order">
          <div class="dc-parties">
            <div class="party">
              <span class="party-icon"></span>
              <span class="party-name">{{ d.order.client?.name }}</span>
            </div>
            <span class="vs">VS</span>
            <div class="party">
              <span class="party-icon"></span>
              <span class="party-name">{{ d.order.seller?.name }}</span>
            </div>
          </div>
          <div class="dc-service">{{ d.order.service ?? `طلب #${d.request_id}` }}</div>
          <div class="dc-escrow">
            <span class="escrow-label">مبلغ الضمان المحتجز</span>
            <span class="escrow-amount">${{ d.order.escrow_amount }}</span>
          </div>
        </div>

        <!-- Reason preview -->
        <div class="dc-reason" v-if="d.reason">
          <span class="reason-label">سبب النزاع:</span>
          <span class="reason-text">{{ truncate(d.reason, 160) }}</span>
          <button v-if="d.reason.length > 160" class="read-more" @click="openDetail(d)">اقرأ المزيد</button>
        </div>

        <!-- Admin assigned -->
        <div class="dc-admin" v-if="d.admin">
          <span>مسؤول المراجعة:</span>
          <strong>{{ d.admin }}</strong>
        </div>

        <!-- Admin notes -->
        <div class="dc-notes" v-if="d.admin_notes">
          <span class="notes-label">قرار الإدارة:</span>
          <span class="notes-text">{{ d.admin_notes }}</span>
        </div>

        <!-- Action buttons -->
        <div class="dc-actions" v-if="!isResolved(d)">
          <button v-if="!d.admin" class="dact dact--assign" @click="assignSelf(d)" :disabled="actionId===d.id">
            <span v-if="actionId===d.id" class="bsp"></span>
            {{ actionId===d.id ? 'جاري...' : 'تعيين نفسي للمراجعة' }}
          </button>

          <button v-if="d.status === 'under_review' || d.admin" class="dact dact--details" @click="openDetail(d)" :disabled="actionId===d.id">
            عرض التفاصيل واتخاذ القرار
          </button>
        </div>

        <!-- Resolved banner -->
        <div class="dc-resolved" v-else>
          <span>تم حسم هذا النزاع</span>
        </div>

      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="pg-btn" :disabled="meta.current_page<=1" @click="loadDisputes(meta.current_page-1)">← السابق</button>
      <span class="pg-info">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="pg-btn" :disabled="meta.current_page>=meta.last_page" @click="loadDisputes(meta.current_page+1)">التالي →</button>
    </div>

    <!-- Resolve Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="resolveModal.open" class="modal-overlay" @mousedown.self="resolveModal.open=false">
          <div class="modal">
            <div class="modal-hdr">
              <h2>{{ resolveModal.favour ? resolveTitle : ' تفاصيل النزاع' }}</h2>
              <button class="mcls" @click="resolveModal.open=false">✕</button>
            </div>
            <div class="modal-body">
              <div class="resolve-ctx" v-if="resolveModal.dispute?.order">
                <div class="ctx-row"><span>العميل:</span><strong>{{ resolveModal.dispute.order.client?.name }}</strong></div>
                <div class="ctx-row"><span>المستقل:</span><strong>{{ resolveModal.dispute.order.seller?.name }}</strong></div>
                <div class="ctx-row"><span>مبلغ الضمان:</span><strong class="escrow-hl">${{ resolveModal.dispute.order.escrow_amount }}</strong></div>
                <div class="ctx-row"><span>تاريخ الطلب:</span><strong>{{ formatDate(resolveModal.dispute.order.requested_at) }}</strong></div>
                <div class="ctx-row" v-if="resolveModal.dispute.order.requested_delivery_days"><span>المهلة التي طلبها العميل:</span><strong>{{ resolveModal.dispute.order.requested_delivery_days }} يوم</strong></div>
                <div class="ctx-row" v-if="resolveModal.dispute.order.delivered_at"><span>تاريخ التسليم:</span><strong>{{ formatDate(resolveModal.dispute.order.delivered_at) }}</strong></div>
                <div class="ctx-row" v-else><span>تاريخ التسليم:</span><strong class="warn-txt">لم يُسلَّم بعد</strong></div>
                <div class="ctx-row" v-if="resolveModal.dispute.order.conversation_id">
                  <span>المحادثة:</span>
                  <RouterLink :to="`/admin/conversations/${resolveModal.dispute.order.conversation_id}?dispute=${resolveModal.dispute.id}&order=${resolveModal.dispute.order.id}`" class="ok-txt" target="_blank">
                    عرض الرسائل والمرفقات ↗
                  </RouterLink>
                </div>
                <div class="ctx-row ctx-row--files" v-if="resolveModal.dispute.order.delivery_files?.length">
                  <span>ملفات سلّمها المستقل:</span>
                  <div class="ctx-files">
                    <a v-for="(f,i) in resolveModal.dispute.order.delivery_files" :key="i" :href="f.url" target="_blank" rel="noopener" class="ctx-file-chip">{{ f.name }}</a>
                  </div>
                </div>
                <div class="ctx-row" v-else><span>ملفات المستقل:</span><strong class="warn-txt">لم يسلّم أي ملفات بعد</strong></div>
                <div class="ctx-row" v-if="resolveModal.favour === 'client'">
                  <span>النتيجة:</span><strong class="ok-txt">استرداد كامل 100% للعميل (${{ Number(resolveModal.dispute.order.escrow_amount).toFixed(2) }})</strong>
                </div>
                <div class="ctx-row" v-if="resolveModal.favour === 'seller'">
                  <span>النتيجة:</span><strong class="ok-txt">تحويل كامل 100% للمستقل (${{ Number(resolveModal.dispute.order.escrow_amount).toFixed(2) }})</strong>
                </div>
                <div class="ctx-row" v-if="resolveModal.favour === 'split'">
                  <span>النتيجة:</span><strong class="ok-txt">حل وسطي 50/50 — ${{ (resolveModal.dispute.order.escrow_amount / 2).toFixed(2) }} لكل طرف</strong>
                </div>
                
              </div>

              <div v-if="!resolveModal.favour" class="favour-picker">
                <p class="favour-picker__hint">راجع التفاصيل فوق (والمحادثة إذا موجودة)، بعدين اختر القرار:</p>
                <button class="dact dact--client" @click="resolveModal.favour = 'client'">حل لصالح العميل (استرداد 100%)</button>
                <button class="dact dact--seller" @click="resolveModal.favour = 'seller'">حل لصالح المستقل (تحويل 100%)</button>
                <button class="dact dact--split" @click="resolveModal.favour = 'split'">حل وسطي (50% لكل طرف)</button>
              </div>

              <template v-else>
                <button class="back-link" @click="resolveModal.favour = null">← رجوع لاختيار القرار</button>
                <div class="field" :class="{ 'field--err': resolveModal.err }">
                  <label class="flabel">قرار الإدارة <span class="req">*</span> <small>(مرئي لكلا الطرفين)</small></label>
                  <textarea v-model="resolveModal.notes" class="ftxtarea" rows="5"
                    placeholder="اشرح سبب قرارك بالتفصيل. سيصل هذا القرار كإشعار لكلا الطرفين..."
                    @input="resolveModal.err=''"></textarea>
                  <span v-if="resolveModal.err" class="ferr">{{ resolveModal.err }}</span>
                </div>
              </template>
            </div>
            <div class="modal-ftr" v-if="resolveModal.favour">
              <button class="mbtn mbtn--ghost" @click="resolveModal.open=false">إلغاء</button>
              <button class="mbtn" :class="resolveBtnClass" @click="submitResolve" :disabled="!!actionId">
                <span v-if="actionId" class="bsp"></span> {{ actionId ? 'جاري التنفيذ...' : resolveTitle }}
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

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import adminApi from '@/composables/useAdminApi'

const route = useRoute()

const disputes = ref([])
const loading = ref(false)
const actionId = ref(null)
const activeFilter = ref('open')
const meta = ref({ total:0, current_page:1, last_page:1 })
const toast = reactive({ msg:'', type:'success' })

// متغيّر احتفاظ حقيقي بوجود نزاعات للحالات
const statusCounts = ref({ open: 0, under_review: 0 })

const filters = [
  { key:'open', icon:'', label:'مفتوحة' },
  { key:'under_review', icon:'', label:'قيد المراجعة' },
  { key:'resolved_client', icon:'', label:'لصالح العميل' },
  { key:'resolved_seller', icon:'', label:'لصالح المستقل' },
  { key:'', icon:'', label:'الكل' },
]

// التثبيت الدائم للنقطة بناءً على العدادات المستقلة
function hasActiveAlert(key) {
  if (key === 'open') return statusCounts.value.open > 0
  if (key === 'under_review') return statusCounts.value.under_review > 0
  return false
}

const resolveModal = reactive({ open:false, dispute:null, favour:null, notes:'', err:'' })
function formatDate(iso) {
  return iso ? new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—'
}

const resolveTitle = computed(() => ({
  client: 'تأكيد الحل لصالح العميل',
  seller: 'تأكيد الحل لصالح المستقل',
  split: 'تأكيد الحل الوسطي (50/50)',
}[resolveModal.favour] ?? ''))

const resolveBtnClass = computed(() => ({
  client: 'mbtn--client',
  seller: 'mbtn--seller',
  split: 'mbtn--split',
  close: 'mbtn--ghost',
}[resolveModal.favour] ?? ''))

let tTimer = null
function showToast(msg, type='success') { toast.msg=msg; toast.type=type; clearTimeout(tTimer); tTimer=setTimeout(()=>{toast.msg=''},4500) }

async function loadDisputes(page=1) {
  loading.value = true
  try {
    const params = { page }
    if (activeFilter.value) params.status = activeFilter.value
    const { data } = await adminApi.getDisputes(params)
    disputes.value = data.data
    meta.value = data.meta

    // إذا كان السيرفر يعيد العدادات الشاملة في meta.counts
    if (data.meta?.counts) {
      statusCounts.value.open = data.meta.counts.open ?? 0
      statusCounts.value.under_review = data.meta.counts.under_review ?? 0
    } else {
      // الاحتفاظ بحالة وجود نزاعات وعدم تصفيرها عند التنقل بين الفلاتر
      disputes.value.forEach(d => {
        if (d.status === 'open' || d.status === 'under_review') {
          statusCounts.value[d.status] = Math.max(statusCounts.value[d.status], 1)
        }
      })
    }
  } catch { showToast('تعذّر تحميل النزاعات','error') }
  finally { loading.value = false }
}

async function assignSelf(d) {
  actionId.value = d.id
  try {
    await adminApi.assignDispute(d.id)
    d.status = 'under_review'
    showToast('تم تعيينك مسؤولاً عن هذا النزاع')
    loadDisputes()
  } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ','error') }
  finally { actionId.value = null }
}

function openResolve(dispute, favour) {
  Object.assign(resolveModal, { open:true, dispute, favour, notes:'', err:'' })
  document.body.style.overflow = 'hidden'
}

function openDetail(d) { openResolve(d, null) }

async function submitResolve() {
  if (!resolveModal.notes.trim() || resolveModal.notes.trim().length < 10) {
    resolveModal.err = 'يجب كتابة قرار واضح (10 أحرف على الأقل)'; return
  }
  actionId.value = resolveModal.dispute.id
  try {
    const notes = resolveModal.notes.trim()
    if (resolveModal.favour === 'client') await adminApi.resolveForClient(resolveModal.dispute.id, notes)
    else if (resolveModal.favour === 'seller') await adminApi.resolveForSeller(resolveModal.dispute.id, notes)
    else if (resolveModal.favour === 'split') await adminApi.resolveSplit(resolveModal.dispute.id, notes)
    else await adminApi.closeDispute(resolveModal.dispute.id, notes)

    resolveModal.open = false; document.body.style.overflow = ''
    showToast('تم اتخاذ القرار وإرسال الإشعارات لكلا الطرفين')
    loadDisputes()
  } catch (e) { showToast(e.response?.data?.message ?? 'حدث خطأ','error') }
  finally { actionId.value = null }
}

const isResolved = d => ['resolved_client','resolved_seller','resolved_split','closed_no_action'].includes(d.status)
const dStatusLabel = s => ({
  open:'مفتوح', under_review:'قيد المراجعة',
  resolved_client:'حُسم للعميل', resolved_seller:'حُسم للمستقل',
  resolved_split:'تقسيم', closed_no_action:'مغلق'
}[s] ?? s)

function fdate(iso) { return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) }
const truncate = (str, n) => str?.length > n ? str.slice(0,n)+'...' : str

onMounted(async () => {
  await loadDisputes()
  if (route.query.open) {
    try {
      const { data } = await adminApi.getDispute(Number(route.query.open))
      openDetail(data.data)
    } catch {
      // dispute not found / no longer accessible — silently ignore
    }
  }
})

onUnmounted(() => { document.body.style.overflow = '' })
</script>

<style scoped>
.admin-disputes { display:flex; flex-direction:column; gap:20px; }
.page-hdr { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.page-title { font-family:var(--font-display); font-size:22px; font-weight:800; margin-bottom:4px; }
.page-sub { font-size:14px; color:var(--color-text-3); }
.status-filter { display:flex; gap:6px; flex-wrap:wrap; }
.fpill { padding:7px 14px; border-radius:20px; border:1px solid var(--color-border); background:none; color:var(--color-text-3); font-family:var(--font-body); font-size:13px; cursor:pointer; transition:all .2s; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; }
.fpill:hover { border-color:var(--color-border-hover); color:var(--color-text); }
.fpill.active { background:var(--grad-primary); border-color:transparent; color:white; box-shadow:0 2px 8px rgba(99,102,241,.3); }

/* Red Dot for Filters */
.status-dot-red {
  width: 7px;
  height: 7px;
  background-color: #EF4444;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 0 6px rgba(239, 68, 68, 0.9);
  animation: pulse-red 1.5s infinite ease-in-out;
}

/* Badge Red Dot */
.badge-red-dot {
  width: 6px;
  height: 6px;
  background-color: #EF4444;
  border-radius: 50%;
  display: inline-block;
  margin-left: 4px;
  box-shadow: 0 0 5px rgba(239, 68, 68, 0.8);
  animation: pulse-red 1.5s infinite ease-in-out;
}

@keyframes pulse-red {
  0% {
    transform: scale(0.85);
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  }
  70% {
    transform: scale(1.1);
    box-shadow: 0 0 0 5px rgba(239, 68, 68, 0);
  }
  100% {
    transform: scale(0.85);
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
  }
}

/* Skeleton */
.sk-list { display:flex; flex-direction:column; gap:12px; }
.sk-card { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:16px; padding:20px; }
.sk-top { display:flex; align-items:center; gap:12px; }
.sk { background:linear-gradient(90deg,var(--color-bg-card) 25%,var(--color-bg-card-hover) 50%,var(--color-bg-card) 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:6px; }
@keyframes shimmer { to { background-position:-200% 0; } }
.sk--av { width:36px; height:36px; border-radius:50%; flex-shrink:0; }
.sk--ln { height:11px; flex:1; }
.sk--bd { width:80px; height:26px; border-radius:20px; }

.empty-state { display:flex; flex-direction:column; align-items:center; gap:12px; padding:70px; text-align:center; background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; }
.empty-state span { font-size:56px; }
.empty-state h3 { font-family:var(--font-display); font-size:20px; font-weight:700; }
.empty-state p { font-size:14px; color:var(--color-text-3); }

/* Dispute cards */
.disputes-list { display:flex; flex-direction:column; gap:14px; }
.dispute-card { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:18px; padding:22px; display:flex; flex-direction:column; gap:14px; transition:border-color .2s,box-shadow .2s; }
.dispute-card:hover { border-color:var(--color-border-hover); box-shadow:var(--shadow-glow); }
.dc--open { border-right:3px solid #EF4444; }
.dc--under_review { border-right:3px solid rgb(168, 100, 5); }
.dc--resolved_client, .dc--resolved_seller, .dc--closed_no_action { border-right:3px solid #10B981; opacity:.8; }

.dc-header { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.dc-id { font-family:var(--font-display); font-size:15px; font-weight:800; color:var(--color-text); }
.dc-badge { padding:3px 12px; border-radius:20px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; }
.db--open { background:rgba(239,68,68,.12); color:#EF4444; }
.db--under_review { color:rgb(168, 100, 5); }
.db--resolved_client,.db--resolved_seller { background:rgba(16,185,129,.12); color:#10B981; }
.db--closed_no_action { background:rgba(107,114,153,.15); color:var(--color-text-3); }
.dc-opener { font-size:12px; color:var(--color-text-3); }
.dc-date { font-size:12px; color:var(--color-text-3); margin-right:auto; }

.dc-order { background:var(--color-bg-2); border-radius:12px; padding:14px 16px; display:flex; flex-direction:column; gap:10px; }
.dc-parties { display:flex; align-items:center; gap:12px; }
.party { display:flex; align-items:center; gap:6px; }
.party-icon { font-size:18px; }
.party-name { font-size:14px; font-weight:600; color:var(--color-text); }
.vs { font-size:12px; font-weight:800; color:var(--color-text-3); padding:2px 8px; background:var(--color-bg-card); border-radius:20px; }
.dc-service { font-size:13px; color:var(--color-text-2); }
.dc-escrow { display:flex; align-items:center; gap:8px; }
.escrow-label { font-size:12px; color:var(--color-text-3); }
.escrow-amount { font-family:var(--font-display); font-size:18px; font-weight:900; background:rgb(173, 35, 35); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }

.dc-reason { font-size:13px; color:var(--color-text-2); line-height:1.6; }
.reason-label { font-weight:600; color:var(--color-text-3); margin-left:6px; }
.read-more { background:none; border:none; color:var(--color-primary); font-size:12px; cursor:pointer; margin-right:4px; text-decoration:underline; font-family:var(--font-body); }

.dc-admin { font-size:13px; color:var(--color-text-3); display:flex; gap:6px; align-items:center; }
.dc-notes { background:rgba(16,185,129,.06); border:1px solid rgba(16,185,129,.15); border-radius:10px; padding:12px 14px; font-size:13px; }
.notes-label { font-weight:600; color:var(--color-text-3); display:block; margin-bottom:4px; }
.notes-text { color:var(--color-text-2); line-height:1.6; }

.dc-actions { display:flex; gap:8px; flex-wrap:wrap; }
.dact { padding:9px 18px; border-radius:20px; border:none; cursor:pointer; font-family:var(--font-body); font-size:13px; font-weight:600; transition:opacity .2s,transform .15s; display:flex; align-items:center; gap:6px; white-space:nowrap; }
.dact:disabled { opacity:.5; cursor:not-allowed; }
.dact:hover:not(:disabled) { opacity:.85; transform:translateY(-1px); }
.dact--assign { background:rgba(99,102,241,.12); color:var(--color-primary); border:1px solid rgba(99,102,241,.25); }
.dact--details { background:var(--color-bg-2); color:var(--color-text); border:1px solid var(--color-border); }

.favour-picker { display: flex; flex-direction: column; gap: 10px; }
.favour-picker__hint { font-size: 12.5px; color: var(--color-text-3); margin-bottom: 4px; }
.favour-picker .dact { width: 100%; text-align: center; padding: 12px; }
.back-link { background: none; border: none; color: var(--color-text-3); font-size: 12px; cursor: pointer; margin-bottom: 10px; padding: 0; text-align: right; }
.back-link:hover { color: var(--color-primary); }
.dact--client { color:var(--color-primary); border:1px solid rgba(17, 21, 242, 0.25); }
.dact--seller { color:#03d84d; border:1px solid rgba(19, 243, 168, 0.25); }
.dact--split { color:#ffa408; border:1px solid rgba(247, 159, 6, 0.25); }

.dc-resolved { display:flex; align-items:center; gap:8px; font-size:13px; color:#10B981; font-weight:600; }

/* Pagination */
.pagination { display:flex; align-items:center; justify-content:center; gap:16px; }
.pg-btn { padding:8px 18px; border-radius:20px; background:var(--color-bg-card); border:1px solid var(--color-border); color:var(--color-text-2); font-family:var(--font-body); font-size:13px; cursor:pointer; transition:border-color .2s,color .2s; }
.pg-btn:hover:not(:disabled) { border-color:var(--color-primary); color:var(--color-primary); }
.pg-btn:disabled { opacity:.4; cursor:not-allowed; }
.pg-info { font-size:13px; color:var(--color-text-3); }

/* Modal */
.modal-overlay { position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.75); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; padding:20px; }
.modal { background:var(--color-bg); border:1px solid var(--color-border); border-radius:20px; width:100%; max-width:540px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,.6); }
.modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--color-border); flex-shrink:0; }
.modal-hdr h2 { font-family:var(--font-display); font-size:16px; font-weight:800; }
.mcls { background:none; border:none; cursor:pointer; font-size:16px; color:var(--color-text-3); padding:4px 8px; border-radius:6px; transition:background .15s; }
.mcls:hover { background:rgba(239,68,68,.1); color:#EF4444; }
.modal-body { flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:16px; }
.modal-ftr { display:flex; gap:10px; padding:16px 24px; border-top:1px solid var(--color-border); flex-shrink:0; }
.resolve-ctx { background:var(--color-bg-2); border-radius:12px; padding:14px 16px; display:flex; flex-direction:column; gap:8px; }
.ctx-row { display:flex; align-items:center; justify-content:space-between; font-size:13px; color:var(--color-text-2); }
.ctx-row strong { color:var(--color-text); font-weight:700; }
.escrow-hl { font-family:var(--font-display); font-size:16px; font-weight:900; color:#EF4444; }
.ok-txt { color:#10B981; }
.warn-txt { color:#F59E0B; }
.ctx-row--files { flex-direction: column; align-items: flex-start; gap: 6px; }
.ctx-files { display: flex; flex-wrap: wrap; gap: 6px; }
.ctx-file-chip { font-size: 11px; background: var(--color-bg); border: 2px solid black; border-radius: 999px; padding: 4px 10px; color: var(--color-text); text-decoration: none; }
.field { display:flex; flex-direction:column; gap:7px; }
.field--err .ftxtarea { border-color:#EF4444; }
.flabel { font-size:14px; font-weight:600; color:var(--color-text-2); }
.flabel small { font-size:11px; color:var(--color-text-3); font-weight:400; }
.req { color:#EF4444; }
.ferr { font-size:12px; color:#EF4444; }
.ftxtarea { width:100%; padding:12px 14px; background:var(--color-bg-2); border:1px solid var(--color-border); border-radius:10px; color:var(--color-text); font-family:var(--font-body); font-size:14px; outline:none; resize:vertical; min-height:110px; transition:border-color .2s; }
.ftxtarea:focus { border-color:var(--color-primary); }
.mbtn { padding:10px 22px; border-radius:10px; border:none; cursor:pointer; font-family:var(--font-display); font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; transition:opacity .2s,transform .15s; }
.mbtn:disabled { opacity:.55; cursor:not-allowed; }
.mbtn--client { background:linear-gradient(135deg,#6366F1,#8B5CF6); color:white; box-shadow:0 0 16px rgba(99,102,241,.3); }
.mbtn--seller { background:linear-gradient(135deg,#10B981,#059669); color:white; box-shadow:0 0 16px rgba(16,185,129,.3); }
.mbtn--split { background:linear-gradient(135deg,#F59E0B,#D97706); color:white; box-shadow:0 0 16px rgba(245,158,11,.3); }
.mbtn--client:hover:not(:disabled),.mbtn--seller:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
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
@media (max-width:640px) { .page-hdr { flex-direction:column; } .dc-parties { flex-direction:column; } .dc-actions { flex-direction:column; } }
</style>