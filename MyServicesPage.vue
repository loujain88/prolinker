<template>
  <div class="services-page" dir="rtl">

    <!-- Header -->
    <div class="page-header">
      <div>
        <h2 class="page-title">🛠️ خدماتي</h2>
        <p class="page-sub">أدر خدماتك المنشورة وتابع أداءها</p>
      </div>
      <button class="create-btn" @click="openModal()">➕ خدمة جديدة</button>
    </div>

    <!-- Stats bar -->
    <div class="services-stats">
      <div class="sstat" v-for="s in serviceStats" :key="s.label">
        <span class="sstat__icon">{{ s.icon }}</span>
        <div class="sstat__body">
          <span class="sstat__num">{{ s.value }}</span>
          <span class="sstat__label">{{ s.label }}</span>
        </div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs">
      <button
        v-for="f in filterOptions" :key="f.key"
        class="filter-tab" :class="{ active: activeFilter === f.key }"
        @click="changeFilter(f.key)"
      >{{ f.icon }} {{ f.label }}</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="svc-grid">
      <div v-for="i in 3" :key="i" class="svc-skeleton">
        <div class="skeleton sk-thumb"></div>
        <div class="sk-body">
          <div class="skeleton sk-line" style="width:70%"></div>
          <div class="skeleton sk-line" style="width:45%"></div>
          <div class="skeleton sk-line" style="width:55%"></div>
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="services.length === 0" class="empty-state">
      <span class="empty-icon">🛠️</span>
      <h3>لا توجد خدمات بعد</h3>
      <p>أنشئ خدمتك الأولى واجعل عملاءك يجدونك</p>
      <button class="create-btn" @click="openModal()">➕ أنشئ خدمتك الأولى</button>
    </div>

    <!-- Services grid -->
    <TransitionGroup v-else name="svc-list" tag="div" class="svc-grid">
      <div v-for="svc in services" :key="svc.id" class="svc-card">

        <!-- Thumbnail -->
        <div class="svc-thumb">
          <div v-if="!svc.thumbnail_url" class="svc-thumb__placeholder">
            {{ categoryEmoji(svc.category) }}
          </div>
          <img v-else :src="svc.thumbnail_url" :alt="svc.title" />
          <div class="svc-thumb__overlay">
            <button class="thumb-action" @click="openModal(svc)" title="تعديل">✏️</button>
            <button class="thumb-action thumb-action--danger" @click="confirmDelete(svc)" title="حذف">🗑️</button>
          </div>
          <span class="svc-status-dot" :class="`dot--${svc.status}`" :title="statusLabel(svc.status)"></span>
        </div>

        <!-- Body -->
        <div class="svc-body">
          <div class="svc-category">{{ svc.category ?? 'غير مصنف' }}</div>
          <h3 class="svc-title">{{ svc.title }}</h3>

          <div class="svc-metrics">
            <div class="metric">
              <span class="metric-icon">⭐</span>
              <span>{{ svc.average_rating > 0 ? svc.average_rating : '—' }}</span>
            </div>
            <div class="metric">
              <span class="metric-icon">📦</span>
              <span>{{ svc.total_orders }} طلب</span>
            </div>
            <div class="metric">
              <span class="metric-icon">🕐</span>
              <span>{{ svc.delivery_days }} يوم</span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="svc-footer">
          <div class="svc-price">
            <span class="price-from">يبدأ من</span>
            <span class="price-val">${{ svc.dynamic_price }}</span>
          </div>
          <div class="svc-actions">
            <button
              class="toggle-btn"
              :class="svc.status === 'active' ? 'toggle-btn--pause' : 'toggle-btn--activate'"
              @click="toggleStatus(svc)"
              :disabled="actionId === svc.id"
            >
              {{ svc.status === 'active' ? '⏸ إيقاف' : '▶ تفعيل' }}
            </button>
            <button class="edit-btn" @click="openModal(svc)">تعديل ✏️</button>
          </div>
        </div>
      </div>
    </TransitionGroup>

    <!-- ═══════ CREATE / EDIT MODAL ═══════ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="modal.open" class="modal-overlay" @mousedown.self="closeModal">
          <div class="modal modal--wide" role="dialog" :aria-label="modal.isEdit ? 'تعديل الخدمة' : 'خدمة جديدة'">

            <div class="modal__header">
              <h2 class="modal__title">{{ modal.isEdit ? '✏️ تعديل الخدمة' : '➕ خدمة جديدة' }}</h2>
              <button class="modal__close" @click="closeModal">✕</button>
            </div>

            <div class="modal__body">
              <!-- Title -->
              <div class="field" :class="{ 'field--error': errors.title }">
                <label class="field-label">عنوان الخدمة <span class="required">*</span></label>
                <input v-model="form.title" type="text" class="field-input"
                  placeholder="مثال: تصميم موقع ويب احترافي بـ Vue.js + Laravel"
                  @input="errors.title = ''" />
                <span v-if="errors.title" class="field-error">{{ errors.title }}</span>
                <span class="char-count">{{ form.title.length }}/255</span>
              </div>

              <!-- Category -->
              <div class="field">
                <label class="field-label">الفئة</label>
                <select v-model="form.category_id" class="field-select">
                  <option :value="null">— اختر فئة —</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
                </select>
              </div>

              <!-- Description -->
              <div class="field" :class="{ 'field--error': errors.description }">
                <label class="field-label">وصف الخدمة <span class="required">*</span></label>
                <textarea v-model="form.description" class="field-textarea" rows="5"
                  placeholder="اشرح بالتفصيل ما ستقدمه للعميل، وما يميز خدمتك..."
                  @input="errors.description = ''"></textarea>
                <span v-if="errors.description" class="field-error">{{ errors.description }}</span>
                <span class="char-count">{{ form.description.length }}/5000</span>
              </div>

              <!-- Pricing row -->
              <div class="form-row">
                <div class="field" :class="{ 'field--error': errors.dynamic_price }">
                  <label class="field-label">السعر المعروض ($) <span class="required">*</span></label>
                  <div class="field-input-wrap">
                    <span class="field-prefix">$</span>
                    <input v-model.number="form.dynamic_price" type="number" min="1"
                      class="field-input field-input--prefixed"
                      placeholder="150" @input="errors.dynamic_price = ''" />
                  </div>
                  <span v-if="errors.dynamic_price" class="field-error">{{ errors.dynamic_price }}</span>
                </div>
                <div class="field" :class="{ 'field--error': errors.rate }">
                  <label class="field-label">معدل الساعة ($) <span class="required">*</span></label>
                  <div class="field-input-wrap">
                    <span class="field-prefix">$</span>
                    <input v-model.number="form.rate" type="number" min="1"
                      class="field-input field-input--prefixed"
                      placeholder="50" @input="errors.rate = ''" />
                  </div>
                  <span v-if="errors.rate" class="field-error">{{ errors.rate }}</span>
                </div>
              </div>

              <!-- Delivery & revisions row -->
              <div class="form-row">
                <div class="field" :class="{ 'field--error': errors.delivery_days }">
                  <label class="field-label">مدة التسليم (أيام) <span class="required">*</span></label>
                  <input v-model.number="form.delivery_days" type="number" min="1" max="90"
                    class="field-input" placeholder="7" @input="errors.delivery_days = ''" />
                  <span v-if="errors.delivery_days" class="field-error">{{ errors.delivery_days }}</span>
                </div>
                <div class="field">
                  <label class="field-label">عدد المراجعات المجانية</label>
                  <input v-model.number="form.revisions_included" type="number" min="0" max="20"
                    class="field-input" placeholder="2" />
                </div>
              </div>

              <!-- Thumbnail upload -->
              <div class="field">
                <label class="field-label">صورة الغلاف</label>
                <div
                  class="dropzone"
                  :class="{ 'dropzone--over': thumbDragging, 'dropzone--filled': thumbFile || form.thumbnail_url }"
                  @dragenter.prevent="thumbDragging = true"
                  @dragover.prevent="thumbDragging = true"
                  @dragleave.prevent="thumbDragging = false"
                  @drop.prevent="handleThumbDrop"
                  @click="$refs.thumbInput.click()"
                  role="button" tabindex="0" @keyup.enter="$refs.thumbInput.click()"
                >
                  <input ref="thumbInput" type="file" accept="image/jpeg,image/png,image/webp"
                    class="file-input-hidden" @change="handleThumbChange" />
                  <Transition name="fade" mode="out-in">
                    <div v-if="!thumbFile && !form.thumbnail_url" key="empty" class="dropzone__empty">
                      <div class="dropzone__icon" :class="{ bouncing: thumbDragging }">🖼️</div>
                      <p class="dropzone__primary">اسحب صورة الغلاف أو <span class="dropzone__link">انقر للاختيار</span></p>
                      <p class="dropzone__secondary">JPG · PNG · WebP — بحد أقصى 2 MB</p>
                    </div>
                    <div v-else key="filled" class="dropzone__filled-preview">
                      <img :src="thumbPreview || form.thumbnail_url" alt="معاينة الغلاف" class="thumb-preview-img" />
                      <button type="button" class="remove-thumb" @click.stop="removeThumb">✕ إزالة الصورة</button>
                    </div>
                  </Transition>
                </div>
              </div>
            </div>

            <div class="modal__footer">
              <button class="modal-btn modal-btn--ghost" @click="closeModal" :disabled="submitting">إلغاء</button>
              <button class="modal-btn modal-btn--primary" @click="submitForm" :disabled="submitting">
                <Transition name="btn-content" mode="out-in">
                  <span v-if="submitting" key="l" class="inline-spinner-wrap">
                    <span class="inline-spinner"></span>
                    {{ modal.isEdit ? 'جاري الحفظ...' : 'جاري النشر...' }}
                  </span>
                  <span v-else key="i">{{ modal.isEdit ? '💾 حفظ التعديلات' : '🚀 نشر الخدمة' }}</span>
                </Transition>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete confirm -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="deleteTarget" class="modal-overlay" @mousedown.self="deleteTarget = null">
          <div class="modal modal--sm">
            <div class="modal__header">
              <h2 class="modal__title">🗑️ حذف الخدمة</h2>
              <button class="modal__close" @click="deleteTarget = null">✕</button>
            </div>
            <div class="modal__body">
              <div class="delete-warning">
                <p>هل أنت متأكد من حذف الخدمة:</p>
                <p class="delete-title">«{{ deleteTarget?.title }}»</p>
                <p class="delete-note">لا يمكن حذف الخدمات التي لديها طلبات نشطة.</p>
              </div>
            </div>
            <div class="modal__footer">
              <button class="modal-btn modal-btn--ghost" @click="deleteTarget = null">إلغاء</button>
              <button class="modal-btn modal-btn--danger" @click="deleteService" :disabled="submitting">
                <span v-if="submitting" class="inline-spinner"></span>
                {{ submitting ? 'جاري الحذف...' : 'نعم، احذف' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toastMsg" class="toast" :class="`toast--${toastType}`">{{ toastMsg }}</div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/composables/useApi'
import { stubCategories } from '@/composables/useServices'

const loading    = ref(false)
const submitting = ref(false)
const actionId   = ref(null)
const toastMsg   = ref('')
const toastType  = ref('success')
const services   = ref([])
const activeFilter = ref('all')
const deleteTarget = ref(null)
const thumbDragging = ref(false)
const thumbFile     = ref(null)
const thumbPreview  = ref(null)
const thumbInput    = ref(null)

const categories = ref(stubCategories)  // swap with API call when ready

const filterOptions = [
  { key: 'all',       icon: '📋', label: 'الكل' },
  { key: 'active',    icon: '✅', label: 'نشطة' },
  { key: 'paused',    icon: '⏸', label: 'موقوفة' },
  { key: 'suspended', icon: '🚫', label: 'موقوفة من الإدارة' },
]

const serviceStats = computed(() => [
  { icon: '📋', label: 'إجمالي الخدمات', value: services.value.length },
  { icon: '✅', label: 'نشطة',            value: services.value.filter(s => s.status === 'active').length },
  { icon: '⏸', label: 'موقوفة',          value: services.value.filter(s => s.status === 'paused').length },
  { icon: '⭐', label: 'متوسط التقييم',   value: avgRating.value },
])

const avgRating = computed(() => {
  const rated = services.value.filter(s => s.average_rating > 0)
  if (!rated.length) return '—'
  return (rated.reduce((a, s) => a + parseFloat(s.average_rating), 0) / rated.length).toFixed(1)
})

// Modal state
const modal = ref({ open: false, isEdit: false, serviceId: null })
const errors = ref({ title: '', description: '', dynamic_price: '', rate: '', delivery_days: '' })
const form = ref({
  title: '', description: '', category_id: null,
  dynamic_price: '', rate: '', delivery_days: '', revisions_included: 1,
  thumbnail_url: null,
})

function openModal(svc = null) {
  errors.value = { title: '', description: '', dynamic_price: '', rate: '', delivery_days: '' }
  thumbFile.value    = null
  thumbPreview.value = null
  if (svc) {
    modal.value = { open: true, isEdit: true, serviceId: svc.id }
    form.value  = {
      title: svc.title, description: svc.description,
      category_id: svc.category_id ?? null,
      dynamic_price: svc.dynamic_price, rate: svc.rate,
      delivery_days: svc.delivery_days,
      revisions_included: svc.revisions_included,
      thumbnail_url: svc.thumbnail_url,
    }
  } else {
    modal.value = { open: true, isEdit: false, serviceId: null }
    form.value  = { title: '', description: '', category_id: null, dynamic_price: '', rate: '', delivery_days: '', revisions_included: 1, thumbnail_url: null }
  }
  document.body.style.overflow = 'hidden'
}
function closeModal() {
  if (submitting.value) return
  modal.value.open = false
  document.body.style.overflow = ''
}

// Thumbnail handling
function handleThumbChange(e) { const f = e.target.files[0]; if (f) setThumb(f) }
function handleThumbDrop(e) {
  thumbDragging.value = false
  const f = e.dataTransfer.files[0]; if (f) setThumb(f)
}
function setThumb(file) {
  if (file.size > 2 * 1024 * 1024) { showToast('حجم الصورة يتجاوز 2 MB', 'error'); return }
  thumbFile.value    = file
  thumbPreview.value = URL.createObjectURL(file)
}
function removeThumb() {
  thumbFile.value = null; thumbPreview.value = null; form.value.thumbnail_url = null
  if (thumbInput.value) thumbInput.value.value = ''
}

function validate() {
  let ok = true
  errors.value = { title: '', description: '', dynamic_price: '', rate: '', delivery_days: '' }
  if (!form.value.title.trim() || form.value.title.length < 10) { errors.value.title = 'العنوان يجب أن يكون 10 أحرف على الأقل'; ok = false }
  if (!form.value.description.trim() || form.value.description.length < 50) { errors.value.description = 'الوصف يجب أن يكون 50 حرفاً على الأقل'; ok = false }
  if (!form.value.dynamic_price || form.value.dynamic_price < 1) { errors.value.dynamic_price = 'أدخل سعراً صحيحاً'; ok = false }
  if (!form.value.rate || form.value.rate < 1)                   { errors.value.rate = 'أدخل معدل ساعة صحيح'; ok = false }
  if (!form.value.delivery_days || form.value.delivery_days < 1) { errors.value.delivery_days = 'أدخل مدة تسليم صحيحة'; ok = false }
  return ok
}

async function submitForm() {
  if (!validate()) return
  submitting.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => { if (v !== null && v !== '') fd.append(k, v) })
    if (thumbFile.value) fd.append('thumbnail', thumbFile.value)

    if (modal.value.isEdit) {
      // PUT doesn't support FormData natively in some servers — use POST with _method spoof
      fd.append('_method', 'PUT')
      await api.post(`/seller/services/${modal.value.serviceId}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      showToast('تم تحديث الخدمة بنجاح ✅')
    } else {
      await api.post('/seller/services', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      showToast('تم نشر الخدمة بنجاح 🚀')
    }
    closeModal()
    fetchServices()
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      Object.keys(errs).forEach(k => { if (errors.value[k] !== undefined) errors.value[k] = errs[k][0] })
    } else {
      showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
    }
  } finally { submitting.value = false }
}

function confirmDelete(svc) { deleteTarget.value = svc; document.body.style.overflow = 'hidden' }

async function deleteService() {
  submitting.value = true
  try {
    await api.delete(`/seller/services/${deleteTarget.value.id}`)
    deleteTarget.value = null; document.body.style.overflow = ''
    showToast('تم حذف الخدمة')
    fetchServices()
  } catch (e) {
    showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
  } finally { submitting.value = false }
}

async function toggleStatus(svc) {
  actionId.value = svc.id
  const newStatus = svc.status === 'active' ? 'paused' : 'active'
  try {
    await api.put(`/seller/services/${svc.id}`, { status: newStatus })
    svc.status = newStatus
    showToast(newStatus === 'active' ? 'تم تفعيل الخدمة ✅' : 'تم إيقاف الخدمة ⏸')
  } catch (e) {
    showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
  } finally { actionId.value = null }
}

async function changeFilter(f) {
  activeFilter.value = f; await fetchServices()
}

async function fetchServices() {
  loading.value = true
  try {
    const params = activeFilter.value !== 'all' ? { status: activeFilter.value } : {}
    const { data } = await api.get('/seller/services', { params })
    services.value = data.data ?? []
  } catch {
    // fallback stub
    services.value = []
  } finally { loading.value = false }
}

const statusLabel = s => ({ active: 'نشطة', paused: 'موقوفة', suspended: 'موقوفة من الإدارة' }[s] ?? s)
const categoryEmoji = c => ({ 'تطوير الويب':'💻','تصميم الجرافيك':'🎨','التسويق الرقمي':'📣','كتابة المحتوى':'✍️','الترجمة':'🌐' }[c] ?? '⚡')

let toastTimer = null
function showToast(msg, type = 'success') {
  toastMsg.value = msg; toastType.value = type
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toastMsg.value = '' }, 4000)
}

onMounted(fetchServices)
</script>

<style scoped>
.services-page { display: flex; flex-direction: column; gap: 22px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-title  { font-family: var(--font-display); font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.page-sub    { font-size: 14px; color: var(--color-text-3); }
.create-btn  { padding: 11px 24px; border-radius: var(--radius-full); background: var(--grad-primary); border: none; color: white; font-family: var(--font-display); font-size: 14px; font-weight: 700; cursor: pointer; white-space: nowrap; box-shadow: 0 0 20px rgba(99,102,241,0.35); transition: opacity 0.2s, transform 0.2s var(--ease-spring); }
.create-btn:hover { opacity: 0.9; transform: translateY(-2px); }

/* Stats bar */
.services-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; }
.sstat { display: flex; align-items: center; gap: 12px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 14px 18px; }
.sstat__icon { font-size: 24px; }
.sstat__num  { display: block; font-family: var(--font-display); font-size: 20px; font-weight: 800; }
.sstat__label { display: block; font-size: 11px; color: var(--color-text-3); }

/* Filter tabs */
.filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-tab  { padding: 8px 18px; border-radius: var(--radius-full); border: 1px solid var(--color-border); background: none; color: var(--color-text-3); font-family: var(--font-body); font-size: 13px; cursor: pointer; transition: all 0.2s; }
.filter-tab:hover  { border-color: var(--color-border-hover); color: var(--color-text); }
.filter-tab.active { background: var(--grad-primary); border-color: transparent; color: white; box-shadow: 0 2px 10px rgba(99,102,241,0.35); }

/* Grid */
.svc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 18px; }

/* Skeleton */
.svc-skeleton { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; }
.skeleton { background: linear-gradient(90deg, var(--color-bg-card) 25%, var(--color-bg-card-hover) 50%, var(--color-bg-card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: var(--radius-sm); }
@keyframes shimmer { to { background-position: -200% 0; } }
.sk-thumb { height: 160px; border-radius: 0; }
.sk-body  { padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.sk-line  { height: 11px; }

/* Empty */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 70px 24px; text-align: center; }
.empty-icon  { font-size: 58px; }
.empty-state h3 { font-family: var(--font-display); font-size: 20px; font-weight: 700; }
.empty-state p  { font-size: 14px; color: var(--color-text-3); }

/* Service card */
.svc-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; display: flex; flex-direction: column; transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s var(--ease-spring); }
.svc-card:hover { border-color: var(--color-border-hover); box-shadow: var(--shadow-glow); transform: translateY(-3px); }

/* Thumbnail */
.svc-thumb { position: relative; height: 150px; overflow: hidden; background: var(--color-bg-2); flex-shrink: 0; }
.svc-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.svc-card:hover .svc-thumb img { transform: scale(1.04); }
.svc-thumb__placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 48px; background: linear-gradient(135deg, var(--color-bg-2), var(--color-bg-card-hover)); }
.svc-thumb__overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; gap: 12px; opacity: 0; transition: opacity 0.2s; }
.svc-card:hover .svc-thumb__overlay { opacity: 1; }
.thumb-action { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: var(--radius-md); padding: 8px 12px; cursor: pointer; font-size: 16px; transition: background 0.15s; backdrop-filter: blur(4px); }
.thumb-action:hover { background: rgba(255,255,255,0.25); }
.thumb-action--danger:hover { background: rgba(239,68,68,0.4); }
.svc-status-dot { position: absolute; top: 10px; right: 10px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--color-bg-card); }
.dot--active    { background: var(--color-success); }
.dot--paused    { background: var(--color-gold); }
.dot--suspended { background: var(--color-error); }

/* Body */
.svc-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
.svc-category { font-size: 11px; color: var(--color-primary); font-weight: 600; letter-spacing: 0.3px; }
.svc-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--color-text); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.svc-metrics { display: flex; gap: 14px; flex-wrap: wrap; }
.metric { display: flex; align-items: center; gap: 4px; font-size: 12px; color: var(--color-text-2); }
.metric-icon { font-size: 13px; }

/* Footer */
.svc-footer { padding: 12px 16px; border-top: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.08); }
.price-from { display: block; font-size: 10px; color: var(--color-text-3); }
.price-val  { font-family: var(--font-display); font-size: 18px; font-weight: 800; color: var(--color-primary); }
.svc-actions { display: flex; gap: 6px; }
.toggle-btn, .edit-btn { padding: 6px 14px; border-radius: var(--radius-full); font-size: 12px; font-weight: 600; cursor: pointer; font-family: var(--font-body); transition: opacity 0.2s, transform 0.15s; border: 1px solid transparent; }
.toggle-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.toggle-btn--pause    { background: rgba(245,158,11,0.12); color: var(--color-gold); border-color: rgba(245,158,11,0.3); }
.toggle-btn--activate { background: rgba(16,185,129,0.12); color: var(--color-success); border-color: rgba(16,185,129,0.3); }
.edit-btn { background: rgba(99,102,241,0.1); color: var(--color-primary); border-color: rgba(99,102,241,0.25); }
.toggle-btn:hover:not(:disabled), .edit-btn:hover { opacity: 0.8; transform: scale(0.97); }

/* svc-list transition */
.svc-list-enter-active { transition: opacity 0.25s, transform 0.25s var(--ease-smooth); }
.svc-list-enter-from   { opacity: 0; transform: scale(0.97); }
.svc-list-leave-active { transition: opacity 0.15s; position: absolute; }
.svc-list-leave-to     { opacity: 0; }

/* ── MODAL ── */
.modal-overlay { position: fixed; inset: 0; z-index: 500; background: rgba(0,0,0,0.72); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 24px 80px rgba(0,0,0,0.6), var(--shadow-glow); }
.modal--wide { max-width: 620px; }
.modal--sm   { max-width: 420px; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 28px; border-bottom: 1px solid var(--color-border); flex-shrink: 0; }
.modal__title  { font-family: var(--font-display); font-size: 17px; font-weight: 800; }
.modal__close  { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--color-text-3); padding: 4px 8px; border-radius: var(--radius-sm); transition: background 0.15s; }
.modal__close:hover { background: rgba(239,68,68,0.1); color: var(--color-error); }
.modal__body   { flex: 1; overflow-y: auto; padding: 22px 28px; display: flex; flex-direction: column; gap: 16px; }
.modal__footer { display: flex; gap: 10px; padding: 16px 28px; border-top: 1px solid var(--color-border); flex-shrink: 0; }

/* Fields */
.field { display: flex; flex-direction: column; gap: 7px; }
.field-label { font-size: 14px; font-weight: 600; color: var(--color-text-2); }
.required { color: var(--color-error); }
.field--error .field-input, .field--error .field-textarea { border-color: var(--color-error); }
.field-input-wrap { position: relative; display: flex; align-items: center; }
.field-prefix { position: absolute; right: 14px; color: var(--color-text-3); font-size: 14px; pointer-events: none; z-index: 1; }
.field-input  { width: 100%; height: 44px; padding: 0 14px; background: var(--color-bg-2); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); font-family: var(--font-body); font-size: 14px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.field-input--prefixed { padding-right: 34px; }
.field-input:focus  { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.field-select { width: 100%; height: 44px; padding: 0 14px; background: var(--color-bg-2); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); font-family: var(--font-body); font-size: 14px; outline: none; cursor: pointer; transition: border-color 0.2s; }
.field-select:focus { border-color: var(--color-primary); }
.field-textarea { width: 100%; padding: 12px 14px; background: var(--color-bg-2); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); font-family: var(--font-body); font-size: 14px; outline: none; resize: vertical; min-height: 110px; transition: border-color 0.2s; }
.field-textarea:focus { border-color: var(--color-primary); }
.field-error  { font-size: 12px; color: var(--color-error); }
.char-count   { font-size: 11px; color: var(--color-text-3); text-align: left; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* Dropzone */
.dropzone { border: 2px dashed var(--color-border); border-radius: var(--radius-lg); padding: 24px; cursor: pointer; transition: border-color 0.2s, background 0.2s; text-align: center; min-height: 120px; display: flex; align-items: center; justify-content: center; position: relative; }
.dropzone--over   { border-color: var(--color-primary); background: rgba(99,102,241,0.06); }
.dropzone--filled { border-color: var(--color-success); border-style: solid; background: rgba(16,185,129,0.04); }
.file-input-hidden { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.dropzone__empty  { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.dropzone__icon   { font-size: 32px; transition: transform 0.2s var(--ease-spring); }
.dropzone__icon.bouncing { animation: bounce 0.5s var(--ease-spring) infinite alternate; }
@keyframes bounce { to { transform: translateY(-6px); } }
.dropzone__primary  { font-size: 14px; color: var(--color-text-2); }
.dropzone__link     { color: var(--color-primary); font-weight: 600; }
.dropzone__secondary{ font-size: 12px; color: var(--color-text-3); }
.dropzone__filled-preview { display: flex; flex-direction: column; align-items: center; gap: 10px; width: 100%; }
.thumb-preview-img  { max-height: 140px; max-width: 100%; border-radius: var(--radius-md); object-fit: cover; }
.remove-thumb { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: var(--color-error); border-radius: var(--radius-full); padding: 5px 14px; font-size: 12px; cursor: pointer; font-family: var(--font-body); transition: background 0.15s; }
.remove-thumb:hover { background: rgba(239,68,68,0.2); }

/* Delete warning */
.delete-warning { text-align: center; display: flex; flex-direction: column; gap: 10px; font-size: 14px; color: var(--color-text-2); }
.delete-title   { font-family: var(--font-display); font-size: 16px; font-weight: 700; color: var(--color-text); }
.delete-note    { font-size: 12px; color: var(--color-text-3); }

/* Modal buttons */
.modal-btn { padding: 10px 22px; border-radius: var(--radius-md); border: none; cursor: pointer; font-family: var(--font-display); font-size: 14px; font-weight: 700; transition: opacity 0.2s, transform 0.15s; display: flex; align-items: center; gap: 8px; }
.modal-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.modal-btn--primary { background: var(--grad-primary); color: white; box-shadow: 0 0 18px rgba(99,102,241,0.35); }
.modal-btn--primary:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.modal-btn--danger  { background: linear-gradient(135deg, #EF4444, #DC2626); color: white; }
.modal-btn--danger:hover:not(:disabled) { opacity: 0.9; }
.modal-btn--ghost   { background: var(--color-bg-2); border: 1px solid var(--color-border); color: var(--color-text-2); }
.inline-spinner-wrap { display: flex; align-items: center; gap: 8px; }
.inline-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-content-enter-active, .btn-content-leave-active { transition: opacity 0.15s; }
.btn-content-enter-from, .btn-content-leave-to { opacity: 0; }

.modal-enter-active { transition: opacity 0.25s; }
.modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal { animation: modal-pop 0.3s var(--ease-spring); }
@keyframes modal-pop { from { transform: scale(0.93) translateY(14px); } to { transform: scale(1); } }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Toast */
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); border-radius: var(--radius-full); padding: 13px 28px; font-size: 14px; font-weight: 600; box-shadow: var(--shadow-card); z-index: 600; white-space: nowrap; }
.toast--success { background: var(--color-bg-card); border: 1px solid var(--color-success); color: var(--color-success); }
.toast--error   { background: var(--color-bg-card); border: 1px solid var(--color-error); color: var(--color-error); }
.toast-enter-active { transition: opacity 0.3s, transform 0.3s var(--ease-spring); }
.toast-leave-active { transition: opacity 0.2s, transform 0.2s; }
.toast-enter-from   { opacity: 0; transform: translateX(-50%) translateY(16px); }
.toast-leave-to     { opacity: 0; transform: translateX(-50%) translateY(8px); }

@media (max-width: 768px) {
  .services-stats { grid-template-columns: 1fr 1fr; }
  .svc-grid { grid-template-columns: 1fr; }
  .form-row { grid-template-columns: 1fr; }
}
</style>
