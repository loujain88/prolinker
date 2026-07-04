<template>
  <div class="wallet-page" dir="rtl">

    <!-- Balance hero -->
    <div class="balance-hero">
      <div class="balance-hero__orb" aria-hidden="true"></div>
      <div class="balance-hero__body">
        <p class="balance-hero__label">الرصيد المتاح</p>
        <div class="balance-hero__amount">
          <span class="balance-currency">$</span>
          <span class="balance-num">{{ walletStore.formattedBalance }}</span>
        </div>
        <p class="balance-hero__sub">
          {{ activeRole === 'client'
            ? 'يُستخدم لدفع تكاليف الطلبات عبر نظام الضمان'
            : 'أرباحك الصافية بعد خصم عمولة المنصة' }}
        </p>
      </div>
      <div class="balance-hero__actions">
        <button
          v-if="activeRole === 'client'"
          class="action-btn action-btn--primary"
          @click="openModal('deposit')"
        >💳 شحن المحفظة</button>
        <button
          v-if="activeRole === 'seller'"
          class="action-btn action-btn--primary"
          @click="openModal('withdraw')"
        >💸 سحب الأرباح</button>
      </div>
    </div>

    <!-- Tab switcher -->
    <div class="tab-bar" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ active: activeTab === tab.key }"
        role="tab" :aria-selected="activeTab === tab.key"
        @click="activeTab = tab.key"
      >
        {{ tab.icon }} {{ tab.label }}
        <span v-if="tab.count" class="tab-count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Transactions list -->
    <div class="transactions-panel">
      <div v-if="walletStore.loading" class="tx-loading">
        <div class="loading-spinner"></div>
        <span>جاري التحميل...</span>
      </div>

      <div v-else-if="displayedList.length === 0" class="tx-empty">
        <span class="tx-empty__icon">💳</span>
        <h3>لا توجد معاملات بعد</h3>
        <p>{{ activeRole === 'client' ? 'اشحن محفظتك للبدء' : 'ستظهر أرباحك هنا بعد إتمام الطلبات' }}</p>
      </div>

      <TransitionGroup v-else name="tx-list" tag="div" class="tx-list">
        <div v-for="tx in displayedList" :key="tx.id" class="tx-row">
          <div class="tx-row__icon-wrap" :class="`tx-icon--${tx.status}`">
            {{ txIcon(tx.status) }}
          </div>
          <div class="tx-row__body">
            <div class="tx-row__title">
              {{ activeTab === 'deposits' ? 'طلب شحن رصيد' : 'طلب سحب رصيد' }}
              <span class="tx-id">#{{ tx.id }}</span>
            </div>
            <div class="tx-row__meta">
              {{ formatDate(tx.created_at) }}
              <span v-if="tx.payment_method" class="tx-method">· {{ methodLabel(tx.payment_method) }}</span>
              <span v-if="tx.payout_method"  class="tx-method">· {{ methodLabel(tx.payout_method) }}</span>
            </div>
            <div v-if="tx.admin_notes" class="tx-notes">{{ tx.admin_notes }}</div>
          </div>
          <div class="tx-row__right">
            <span class="tx-status-badge" :class="`tx-status--${tx.status}`">{{ statusLabel(tx.status) }}</span>
            <div class="tx-amount" :class="activeTab === 'deposits' ? 'positive' : 'negative'">
              {{ activeTab === 'deposits' ? '+' : '-' }}${{ tx.amount }}
            </div>
            <!-- Receipt link -->
            <button
              v-if="tx.has_receipt && tx.status === 'approved'"
              class="receipt-link"
              @click="viewReceipt(tx.id)"
              :disabled="loadingReceiptId === tx.id"
            >
              {{ loadingReceiptId === tx.id ? '...' : '📄 الإيصال' }}
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>

    <!-- ─────────────────────────────────────────────────────────────
         DEPOSIT MODAL
    ───────────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="modalType === 'deposit'" class="modal-overlay" @mousedown.self="closeModal">
          <div class="modal" role="dialog" aria-modal="true" aria-label="شحن المحفظة">
            <div class="modal__header">
              <h2 class="modal__title">💳 شحن المحفظة</h2>
              <button class="modal__close" @click="closeModal" aria-label="إغلاق">✕</button>
            </div>

            <div class="modal__body">
              <!-- Amount -->
              <div class="field" :class="{ 'field--error': formErrors.amount }">
                <label class="field-label">المبلغ المراد إيداعه ($)</label>
                <div class="field-input-wrap">
                  <span class="field-prefix">$</span>
                  <input
                    v-model.number="depositForm.amount"
                    type="number" min="1" step="0.01"
                    class="field-input field-input--prefixed"
                    placeholder="0.00"
                    @input="formErrors.amount = ''"
                  />
                </div>
                <span v-if="formErrors.amount" class="field-error">{{ formErrors.amount }}</span>
              </div>

              <!-- Payment method -->
              <div class="field">
                <label class="field-label">طريقة الدفع</label>
                <div class="method-select">
                  <button
                    v-for="m in paymentMethods"
                    :key="m.key"
                    type="button"
                    class="method-opt"
                    :class="{ active: depositForm.payment_method === m.key }"
                    @click="depositForm.payment_method = m.key"
                  >
                    <span>{{ m.icon }}</span> {{ m.label }}
                  </button>
                </div>
              </div>

              <!-- Drag & drop receipt upload -->
              <div class="field" :class="{ 'field--error': formErrors.receipt }">
                <label class="field-label">إيصال التحويل <span class="required">*</span></label>
                <div
                  class="dropzone"
                  :class="{ 'dropzone--over': isDragging, 'dropzone--filled': depositForm.receipt }"
                  @dragenter.prevent="isDragging = true"
                  @dragover.prevent="isDragging = true"
                  @dragleave.prevent="isDragging = false"
                  @drop.prevent="handleDrop"
                  @click="$refs.fileInput.click()"
                  role="button"
                  tabindex="0"
                  @keyup.enter="$refs.fileInput.click()"
                  :aria-label="depositForm.receipt ? `ملف محدد: ${depositForm.receipt.name}` : 'انقر أو اسحب الإيصال هنا'"
                >
                  <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,application/pdf"
                    class="file-input-hidden"
                    @change="handleFileChange"
                  />

                  <!-- Empty state -->
                  <Transition name="fade" mode="out-in">
                    <div v-if="!depositForm.receipt" key="empty" class="dropzone__empty">
                      <div class="dropzone__icon" :class="{ bouncing: isDragging }">📁</div>
                      <p class="dropzone__primary">اسحب الإيصال هنا أو <span class="dropzone__link">انقر للاختيار</span></p>
                      <p class="dropzone__secondary">JPG · PNG · PDF — بحد أقصى 5 MB</p>
                    </div>

                    <!-- Filled state -->
                    <div v-else key="filled" class="dropzone__filled">
                      <div class="file-preview">
                        <div class="file-preview__icon">
                          {{ depositForm.receipt.type === 'application/pdf' ? '📄' : '🖼️' }}
                        </div>
                        <div class="file-preview__info">
                          <span class="file-preview__name">{{ depositForm.receipt.name }}</span>
                          <span class="file-preview__size">{{ formatFileSize(depositForm.receipt.size) }}</span>
                        </div>
                        <button
                          type="button"
                          class="file-preview__remove"
                          @click.stop="depositForm.receipt = null"
                          aria-label="حذف الملف"
                        >✕</button>
                      </div>
                      <!-- Image preview thumbnail -->
                      <img
                        v-if="previewUrl && depositForm.receipt.type !== 'application/pdf'"
                        :src="previewUrl"
                        alt="معاينة الإيصال"
                        class="file-thumbnail"
                      />
                    </div>
                  </Transition>
                </div>
                <span v-if="formErrors.receipt" class="field-error">{{ formErrors.receipt }}</span>
              </div>

              <!-- Upload progress -->
              <Transition name="fade">
                <div v-if="uploadProgress > 0 && uploadProgress < 100" class="upload-progress">
                  <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: uploadProgress + '%' }"></div>
                  </div>
                  <span class="progress-label">{{ uploadProgress }}%</span>
                </div>
              </Transition>
            </div>

            <!-- Modal footer -->
            <div class="modal__footer">
              <button class="modal-btn modal-btn--ghost" @click="closeModal" :disabled="submitting">إلغاء</button>
              <button class="modal-btn modal-btn--primary" @click="submitDeposit" :disabled="submitting">
                <Transition name="btn-content" mode="out-in">
                  <span v-if="submitting" key="l" class="inline-spinner-wrap"><span class="inline-spinner"></span> جاري الإرسال...</span>
                  <span v-else key="i">إرسال طلب الشحن</span>
                </Transition>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ─────────────────────────────────────────────────────────────
         WITHDRAW MODAL
    ───────────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="modalType === 'withdraw'" class="modal-overlay" @mousedown.self="closeModal">
          <div class="modal" role="dialog" aria-modal="true" aria-label="سحب الأرباح">
            <div class="modal__header">
              <h2 class="modal__title">💸 سحب الأرباح</h2>
              <button class="modal__close" @click="closeModal" aria-label="إغلاق">✕</button>
            </div>

            <div class="modal__body">
              <!-- Current balance display -->
              <div class="balance-inset">
                <span class="balance-inset__label">رصيدك المتاح للسحب</span>
                <span class="balance-inset__value">${{ walletStore.formattedBalance }}</span>
              </div>

              <!-- Amount -->
              <div class="field" :class="{ 'field--error': formErrors.amount }">
                <label class="field-label">المبلغ المراد سحبه ($)</label>
                <div class="field-input-wrap">
                  <span class="field-prefix">$</span>
                  <input
                    v-model.number="withdrawForm.amount"
                    type="number" min="10" step="0.01"
                    class="field-input field-input--prefixed"
                    placeholder="0.00"
                    @input="formErrors.amount = ''"
                  />
                </div>
                <span v-if="formErrors.amount" class="field-error">{{ formErrors.amount }}</span>
              </div>

              <!-- Payout method -->
              <div class="field">
                <label class="field-label">طريقة الدفع</label>
                <div class="method-select">
                  <button
                    v-for="m in payoutMethods"
                    :key="m.key"
                    type="button"
                    class="method-opt"
                    :class="{ active: withdrawForm.payout_method === m.key }"
                    @click="withdrawForm.payout_method = m.key"
                  >
                    <span>{{ m.icon }}</span> {{ m.label }}
                  </button>
                </div>
              </div>

              <!-- Bank details -->
              <Transition name="slide-down">
                <div v-if="withdrawForm.payout_method === 'bank_transfer'" class="bank-details">
                  <div class="field">
                    <label class="field-label">اسم صاحب الحساب</label>
                    <input v-model="withdrawForm.payout_details.account_name" type="text"
                      class="field-input-plain" placeholder="الاسم الكامل كما في البنك" />
                  </div>
                  <div class="field">
                    <label class="field-label">رقم الحساب / IBAN</label>
                    <input v-model="withdrawForm.payout_details.account_number" type="text"
                      class="field-input-plain" placeholder="SA00 0000 0000 0000 0000 0000" dir="ltr" />
                  </div>
                </div>
              </Transition>

              <!-- PayPal -->
              <Transition name="slide-down">
                <div v-if="withdrawForm.payout_method === 'paypal'" class="bank-details">
                  <div class="field">
                    <label class="field-label">بريد PayPal</label>
                    <input v-model="withdrawForm.payout_details.paypal_email" type="email"
                      class="field-input-plain" placeholder="paypal@email.com" dir="ltr" />
                  </div>
                </div>
              </Transition>

              <!-- Notes -->
              <div class="field">
                <label class="field-label">ملاحظات (اختياري)</label>
                <textarea v-model="withdrawForm.notes" class="field-textarea"
                  placeholder="أي تعليمات إضافية للإدارة..." rows="3"></textarea>
              </div>
            </div>

            <div class="modal__footer">
              <button class="modal-btn modal-btn--ghost" @click="closeModal" :disabled="submitting">إلغاء</button>
              <button class="modal-btn modal-btn--primary" @click="submitWithdraw" :disabled="submitting">
                <Transition name="btn-content" mode="out-in">
                  <span v-if="submitting" key="l" class="inline-spinner-wrap"><span class="inline-spinner"></span> جاري الإرسال...</span>
                  <span v-else key="i">تأكيد طلب السحب</span>
                </Transition>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Success toast -->
    <Transition name="toast">
      <div v-if="toastMsg" class="toast" role="status">✅ {{ toastMsg }}</div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, watchEffect } from 'vue'
import { useWalletStore } from '@/stores/wallet'

const props = defineProps({ activeRole: { type: String, default: 'client' } })

const walletStore = useWalletStore()

// ── Tabs ───────────────────────────────────────────────────────────────────
const activeTab = ref(props.activeRole === 'client' ? 'deposits' : 'withdrawals')
watch(() => props.activeRole, r => { activeTab.value = r === 'client' ? 'deposits' : 'withdrawals' })

const tabs = computed(() => {
  const list = []
  if (props.activeRole === 'client')
    list.push({ key: 'deposits',    icon: '💳', label: 'الإيداعات',  count: walletStore.deposits.length })
  if (props.activeRole === 'seller')
    list.push({ key: 'withdrawals', icon: '💸', label: 'السحوبات', count: walletStore.withdrawals.length })
  return list
})

const displayedList = computed(() =>
  activeTab.value === 'deposits' ? walletStore.deposits : walletStore.withdrawals
)

// ── Modal state ────────────────────────────────────────────────────────────
const modalType = ref(null)
const submitting = ref(false)
const toastMsg   = ref('')
const isDragging = ref(false)
const uploadProgress = ref(0)
const loadingReceiptId = ref(null)
const previewUrl = ref(null)
const fileInput  = ref(null)

const formErrors = ref({ amount: '', receipt: '' })

const depositForm = ref({
  amount: null,
  payment_method: 'bank_transfer',
  receipt: null,
})
const withdrawForm = ref({
  amount: null,
  payout_method: 'bank_transfer',
  payout_details: { account_name: '', account_number: '', paypal_email: '' },
  notes: '',
})

function openModal(type) {
  modalType.value = type
  formErrors.value = { amount: '', receipt: '' }
  document.body.style.overflow = 'hidden'
}
function closeModal() {
  if (submitting.value) return
  modalType.value  = null
  depositForm.value = { amount: null, payment_method: 'bank_transfer', receipt: null }
  withdrawForm.value = { amount: null, payout_method: 'bank_transfer', payout_details: {}, notes: '' }
  previewUrl.value = null
  uploadProgress.value = 0
  document.body.style.overflow = ''
}

// ── File handling ──────────────────────────────────────────────────────────
function handleFileChange(e) {
  const file = e.target.files[0]
  if (file) setFile(file)
}
function handleDrop(e) {
  isDragging.value = false
  const file = e.dataTransfer.files[0]
  if (file) setFile(file)
}
function setFile(file) {
  if (file.size > 5 * 1024 * 1024) {
    formErrors.value.receipt = 'حجم الملف يتجاوز 5 MB'
    return
  }
  if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type)) {
    formErrors.value.receipt = 'نوع الملف غير مدعوم (JPG, PNG, PDF فقط)'
    return
  }
  formErrors.value.receipt = ''
  depositForm.value.receipt = file
  if (file.type !== 'application/pdf') {
    previewUrl.value = URL.createObjectURL(file)
  } else {
    previewUrl.value = null
  }
}
function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// ── Submit deposit ─────────────────────────────────────────────────────────
async function submitDeposit() {
  formErrors.value = { amount: '', receipt: '' }
  if (!depositForm.value.amount || depositForm.value.amount < 1) {
    formErrors.value.amount = 'أدخل مبلغاً صحيحاً (الحد الأدنى $1)'
    return
  }
  if (!depositForm.value.receipt) {
    formErrors.value.receipt = 'يجب رفع إيصال التحويل'
    return
  }
  submitting.value = true
  uploadProgress.value = 0

  try {
    const fd = new FormData()
    fd.append('amount',               depositForm.value.amount)
    fd.append('payment_method',       depositForm.value.payment_method)
    fd.append('transaction_receipt',  depositForm.value.receipt)

    // Simulate upload progress (real progress needs axios onUploadProgress)
    const progressInterval = setInterval(() => {
      if (uploadProgress.value < 85) uploadProgress.value += 15
    }, 200)

    await walletStore.submitDeposit(fd)
    clearInterval(progressInterval)
    uploadProgress.value = 100

    closeModal()
    showToast('تم إرسال طلب الشحن بنجاح، سيتم مراجعته من قِبل الإدارة')
  } catch (e) {
    const msg = e.response?.data?.message ?? 'حدث خطأ، يرجى المحاولة مجدداً'
    formErrors.value.amount = msg
  } finally {
    submitting.value = false
    setTimeout(() => { uploadProgress.value = 0 }, 1000)
  }
}

// ── Submit withdrawal ──────────────────────────────────────────────────────
async function submitWithdraw() {
  formErrors.value = { amount: '', receipt: '' }
  if (!withdrawForm.value.amount || withdrawForm.value.amount < 10) {
    formErrors.value.amount = 'الحد الأدنى للسحب هو $10'
    return
  }
  if (withdrawForm.value.amount > parseFloat(walletStore.balance)) {
    formErrors.value.amount = 'المبلغ المطلوب يتجاوز رصيدك المتاح'
    return
  }
  submitting.value = true
  try {
    await walletStore.submitWithdrawal({
      amount:         withdrawForm.value.amount,
      payout_method:  withdrawForm.value.payout_method,
      payout_details: withdrawForm.value.payout_details,
      notes:          withdrawForm.value.notes,
    })
    closeModal()
    showToast('تم إرسال طلب السحب، سيتم تحويل المبلغ خلال 24-48 ساعة')
  } catch (e) {
    formErrors.value.amount = e.response?.data?.message ?? 'حدث خطأ'
  } finally {
    submitting.value = false
  }
}

// ── View receipt ───────────────────────────────────────────────────────────
async function viewReceipt(id) {
  loadingReceiptId.value = id
  try {
    const url = await walletStore.fetchReceiptUrl(activeTab.value === 'deposits' ? 'deposit' : 'withdrawal', id)
    window.open(url, '_blank', 'noopener')
  } catch {
    showToast('تعذّر فتح الإيصال')
  } finally {
    loadingReceiptId.value = null
  }
}

// ── Toast ──────────────────────────────────────────────────────────────────
let toastTimer = null
function showToast(msg) {
  toastMsg.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toastMsg.value = '' }, 4000)
}

// ── Static data ────────────────────────────────────────────────────────────
const paymentMethods = [
  { key: 'bank_transfer', icon: '🏦', label: 'حوالة بنكية' },
  { key: 'cash',          icon: '💵', label: 'كاش' },
]
const payoutMethods = [
  { key: 'bank_transfer', icon: '🏦', label: 'تحويل بنكي' },
  { key: 'paypal',        icon: '🅿️',  label: 'PayPal' },
  { key: 'wise',          icon: '🌐', label: 'Wise' },
]

const statusLabel = s => ({ pending:'معلق', approved:'مقبول', rejected:'مرفوض' }[s] ?? s)
const txIcon = s => ({ pending:'⏳', approved:'✅', rejected:'❌' }[s] ?? '💳')
const methodLabel = m => ({
  bank_transfer:'تحويل بنكي', cash:'كاش', paypal:'PayPal', wise:'Wise', crypto:'عملة رقمية'
}[m] ?? m)

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('ar-EG', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' })
}

onMounted(() => walletStore.fetchSummary())
</script>

<style scoped>
.wallet-page { display: flex; flex-direction: column; gap: 24px; }

/* Balance hero */
.balance-hero {
  background: linear-gradient(135deg, #1a1f3a 0%, #1d1440 100%);
  border: 1px solid rgba(99,102,241,0.25);
  border-radius: var(--radius-xl);
  padding: 36px 40px;
  display: flex; align-items: center; gap: 24px;
  position: relative; overflow: hidden;
}
.balance-hero__orb {
  position: absolute; top: -80px; left: -80px;
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(99,102,241,0.2), transparent 70%);
  pointer-events: none;
}
.balance-hero__body  { flex: 1; position: relative; z-index: 1; }
.balance-hero__label { font-size: 13px; color: var(--color-text-3); margin-bottom: 8px; }
.balance-hero__amount { display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px; }
.balance-currency { font-size: 24px; color: var(--color-text-3); }
.balance-num { font-family: var(--font-display); font-size: 52px; font-weight: 900; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; }
.balance-hero__sub { font-size: 13px; color: var(--color-text-3); }
.balance-hero__actions { position: relative; z-index: 1; }
.action-btn {
  padding: 12px 28px; border-radius: var(--radius-full);
  border: none; cursor: pointer;
  font-family: var(--font-display); font-size: 15px; font-weight: 700;
  transition: opacity 0.2s, transform 0.2s var(--ease-spring), box-shadow 0.2s;
}
.action-btn:hover { opacity: 0.9; transform: translateY(-2px); }
.action-btn--primary { background: var(--grad-primary); color: white; box-shadow: 0 0 24px rgba(99,102,241,0.4); }
.action-btn--primary:hover { box-shadow: 0 0 36px rgba(99,102,241,0.6); }

/* Tabs */
.tab-bar { display: flex; gap: 4px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 4px; width: fit-content; }
.tab-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 9px 20px; border: none; border-radius: var(--radius-md);
  background: none; cursor: pointer;
  font-family: var(--font-body); font-size: 14px; font-weight: 500; color: var(--color-text-3);
  transition: background 0.2s, color 0.2s;
}
.tab-btn.active { background: var(--grad-primary); color: white; box-shadow: 0 2px 12px rgba(99,102,241,0.35); }
.tab-count { font-size: 11px; background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: var(--radius-full); }

/* Transactions */
.transactions-panel { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; }
.tx-loading { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px; color: var(--color-text-3); }
.loading-spinner { width: 28px; height: 28px; border: 2px solid var(--color-border); border-top-color: var(--color-primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.tx-empty { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 60px; text-align: center; }
.tx-empty__icon { font-size: 48px; }
.tx-empty h3 { font-family: var(--font-display); font-size: 18px; font-weight: 700; }
.tx-empty p  { font-size: 14px; color: var(--color-text-3); }
.tx-list { display: flex; flex-direction: column; }
.tx-row {
  display: flex; align-items: center; gap: 16px;
  padding: 18px 24px; border-bottom: 1px solid var(--color-border);
  transition: background 0.15s;
}
.tx-row:last-child { border-bottom: none; }
.tx-row:hover { background: rgba(99,102,241,0.04); }
.tx-row__icon-wrap { width: 42px; height: 42px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.tx-icon--pending  { background: rgba(245,158,11,0.12); }
.tx-icon--approved { background: rgba(16,185,129,0.12); }
.tx-icon--rejected { background: rgba(239,68,68,0.12); }
.tx-row__body { flex: 1; min-width: 0; }
.tx-row__title { font-size: 14px; font-weight: 600; color: var(--color-text); }
.tx-id { font-size: 12px; color: var(--color-text-3); margin-right: 6px; }
.tx-row__meta { font-size: 12px; color: var(--color-text-3); margin-top: 3px; }
.tx-method { margin-right: 4px; }
.tx-notes { font-size: 12px; color: var(--color-text-3); background: var(--color-bg-2); padding: 6px 10px; border-radius: var(--radius-sm); margin-top: 6px; }
.tx-row__right { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
.tx-status-badge { padding: 3px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 600; }
.tx-status--pending  { background: rgba(245,158,11,0.15); color: #F59E0B; }
.tx-status--approved { background: rgba(16,185,129,0.15); color: var(--color-success); }
.tx-status--rejected { background: rgba(239,68,68,0.12); color: var(--color-error); }
.tx-amount { font-family: var(--font-display); font-size: 16px; font-weight: 800; }
.tx-amount.positive { color: var(--color-success); }
.tx-amount.negative { color: var(--color-error); }
.receipt-link {
  font-size: 12px; color: var(--color-primary); background: none; border: none; cursor: pointer;
  padding: 3px 8px; border-radius: var(--radius-sm);
  transition: background 0.15s;
}
.receipt-link:hover { background: rgba(99,102,241,0.1); }
.receipt-link:disabled { opacity: 0.5; cursor: not-allowed; }

/* tx list transitions */
.tx-list-enter-active { transition: opacity 0.25s, transform 0.25s var(--ease-smooth); }
.tx-list-enter-from   { opacity: 0; transform: translateX(10px); }

/* ═══════ MODAL ═══════ */
.modal-overlay {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.modal {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  width: 100%; max-width: 500px;
  max-height: 90vh; display: flex; flex-direction: column;
  box-shadow: 0 24px 80px rgba(0,0,0,0.6), var(--shadow-glow);
}
.modal__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 22px 28px; border-bottom: 1px solid var(--color-border); flex-shrink: 0;
}
.modal__title { font-family: var(--font-display); font-size: 18px; font-weight: 800; }
.modal__close {
  background: none; border: none; cursor: pointer; font-size: 16px;
  color: var(--color-text-3); padding: 4px 8px; border-radius: var(--radius-sm);
  transition: background 0.15s, color 0.15s;
}
.modal__close:hover { background: rgba(239,68,68,0.1); color: var(--color-error); }
.modal__body   { flex: 1; overflow-y: auto; padding: 24px 28px; display: flex; flex-direction: column; gap: 18px; }
.modal__footer { display: flex; gap: 10px; justify-content: flex-start; padding: 18px 28px; border-top: 1px solid var(--color-border); flex-shrink: 0; }

/* Fields inside modal */
.field { display: flex; flex-direction: column; gap: 8px; }
.field-label { font-size: 14px; font-weight: 600; color: var(--color-text-2); }
.required { color: var(--color-error); }
.field-input-wrap { position: relative; display: flex; align-items: center; }
.field-prefix { position: absolute; right: 14px; font-size: 15px; color: var(--color-text-3); pointer-events: none; z-index: 1; }
.field-input {
  width: 100%; height: 46px; padding: 0 14px;
  background: var(--color-bg-2); border: 1px solid var(--color-border);
  border-radius: var(--radius-md); color: var(--color-text);
  font-family: var(--font-body); font-size: 15px; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.field-input--prefixed { padding-right: 36px; }
.field-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.field--error .field-input { border-color: var(--color-error); }
.field-error { font-size: 12px; color: var(--color-error); }
.field-input-plain {
  width: 100%; height: 44px; padding: 0 14px;
  background: var(--color-bg-2); border: 1px solid var(--color-border);
  border-radius: var(--radius-md); color: var(--color-text);
  font-family: var(--font-body); font-size: 14px; outline: none;
  transition: border-color 0.2s;
}
.field-input-plain:focus { border-color: var(--color-primary); }
.field-textarea {
  width: 100%; padding: 12px 14px;
  background: var(--color-bg-2); border: 1px solid var(--color-border);
  border-radius: var(--radius-md); color: var(--color-text);
  font-family: var(--font-body); font-size: 14px; outline: none;
  resize: vertical; min-height: 80px;
  transition: border-color 0.2s;
}
.field-textarea:focus { border-color: var(--color-primary); }

/* Method selector */
.method-select { display: flex; gap: 8px; flex-wrap: wrap; }
.method-opt {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: var(--radius-full);
  border: 1px solid var(--color-border);
  background: var(--color-bg-2); cursor: pointer;
  font-family: var(--font-body); font-size: 13px; color: var(--color-text-2);
  transition: border-color 0.2s, color 0.2s, background 0.2s;
}
.method-opt.active { border-color: var(--color-primary); color: var(--color-primary); background: rgba(99,102,241,0.1); }

/* Drop zone */
.dropzone {
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-lg);
  padding: 28px 20px;
  cursor: pointer; transition: border-color 0.2s, background 0.2s;
  text-align: center; position: relative;
  min-height: 130px; display: flex; align-items: center; justify-content: center;
}
.dropzone--over   { border-color: var(--color-primary); background: rgba(99,102,241,0.06); }
.dropzone--filled { border-color: var(--color-success); background: rgba(16,185,129,0.04); border-style: solid; }
.dropzone:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
.file-input-hidden { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.dropzone__empty { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.dropzone__icon { font-size: 36px; transition: transform 0.2s var(--ease-spring); }
.dropzone__icon.bouncing { animation: bounce 0.5s var(--ease-spring) infinite alternate; }
@keyframes bounce { to { transform: translateY(-6px); } }
.dropzone__primary { font-size: 14px; color: var(--color-text-2); }
.dropzone__link { color: var(--color-primary); font-weight: 600; }
.dropzone__secondary { font-size: 12px; color: var(--color-text-3); }

/* File preview */
.dropzone__filled { width: 100%; }
.file-preview {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 16px;
  background: var(--color-bg-2); border-radius: var(--radius-md);
  margin-bottom: 10px;
}
.file-preview__icon { font-size: 28px; flex-shrink: 0; }
.file-preview__info { flex: 1; min-width: 0; text-align: right; }
.file-preview__name { display: block; font-size: 13px; font-weight: 600; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-preview__size { font-size: 11px; color: var(--color-text-3); }
.file-preview__remove {
  background: none; border: none; cursor: pointer; font-size: 14px;
  color: var(--color-text-3); padding: 4px 6px; border-radius: var(--radius-sm);
  transition: background 0.15s, color 0.15s; flex-shrink: 0;
}
.file-preview__remove:hover { background: rgba(239,68,68,0.1); color: var(--color-error); }
.file-thumbnail { width: 100%; max-height: 120px; object-fit: cover; border-radius: var(--radius-md); }

/* Upload progress */
.upload-progress { display: flex; align-items: center; gap: 12px; }
.progress-bar { flex: 1; height: 6px; background: var(--color-border); border-radius: var(--radius-full); overflow: hidden; }
.progress-fill { height: 100%; background: var(--grad-primary); border-radius: var(--radius-full); transition: width 0.3s; }
.progress-label { font-size: 12px; color: var(--color-text-3); min-width: 30px; }

/* Bank details */
.bank-details { display: flex; flex-direction: column; gap: 14px; padding: 16px; background: var(--color-bg-2); border-radius: var(--radius-lg); border: 1px solid var(--color-border); }
.slide-down-enter-active,.slide-down-leave-active { transition: opacity 0.25s, max-height 0.3s, transform 0.25s; overflow: hidden; max-height: 200px; }
.slide-down-enter-from,.slide-down-leave-to { opacity: 0; max-height: 0; transform: translateY(-8px); }

/* Balance inset */
.balance-inset {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px;
  background: rgba(99,102,241,0.08);
  border: 1px solid rgba(99,102,241,0.2);
  border-radius: var(--radius-md);
}
.balance-inset__label { font-size: 13px; color: var(--color-text-3); }
.balance-inset__value { font-family: var(--font-display); font-size: 20px; font-weight: 800; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

/* Modal buttons */
.modal-btn {
  padding: 11px 24px; border-radius: var(--radius-md);
  border: none; cursor: pointer;
  font-family: var(--font-display); font-size: 14px; font-weight: 700;
  transition: opacity 0.2s, transform 0.15s;
}
.modal-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.modal-btn--primary { background: var(--grad-primary); color: white; box-shadow: 0 0 18px rgba(99,102,241,0.35); }
.modal-btn--primary:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.modal-btn--ghost { background: var(--color-bg-2); border: 1px solid var(--color-border); color: var(--color-text-2); }
.modal-btn--ghost:hover:not(:disabled) { border-color: var(--color-border-hover); color: var(--color-text); }
.inline-spinner-wrap { display: flex; align-items: center; gap: 8px; }
.inline-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; }
.btn-content-enter-active,.btn-content-leave-active { transition: opacity 0.15s; }
.btn-content-enter-from,.btn-content-leave-to { opacity: 0; }

/* Modal transition */
.modal-enter-active { transition: opacity 0.25s, transform 0.3s var(--ease-spring); }
.modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from   { opacity: 0; }
.modal-leave-to     { opacity: 0; }
.modal-enter-active .modal { animation: modal-pop 0.3s var(--ease-spring); }
@keyframes modal-pop { from { transform: scale(0.94) translateY(16px); } to { transform: scale(1) translateY(0); } }

/* Toast */
.toast {
  position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
  background: var(--color-bg-card);
  border: 1px solid var(--color-success);
  border-radius: var(--radius-full);
  padding: 12px 28px; font-size: 14px; font-weight: 600;
  box-shadow: var(--shadow-card);
  z-index: 600; white-space: nowrap;
  color: var(--color-success);
}
.toast-enter-active { transition: opacity 0.3s, transform 0.3s var(--ease-spring); }
.toast-leave-active { transition: opacity 0.2s, transform 0.2s; }
.toast-enter-from   { opacity: 0; transform: translateX(-50%) translateY(16px); }
.toast-leave-to     { opacity: 0; transform: translateX(-50%) translateY(8px); }

/* Fade */
.fade-enter-active,.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,.fade-leave-to { opacity: 0; }

@media (max-width: 640px) {
  .balance-hero { flex-direction: column; align-items: flex-start; padding: 24px; }
  .balance-num  { font-size: 40px; }
}
</style>
