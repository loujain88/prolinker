<template>
 <div class="profile-page" dir="rtl">

 <!-- Profile hero -->
 <div class="profile-hero">
 <div class="profile-hero__orb" aria-hidden="true"></div>

 <!-- Avatar section -->
 <div class="avatar-section">
 <div class="avatar-wrap">
 <div class="avatar-circle">{{ userInitials }}</div>
 <div class="avatar-verified" v-if="auth.user?.seller?.is_verified" title="حساب موثق">✓</div>
 </div>
 <div class="avatar-info">
 <h2 class="profile-name">{{ auth.user?.name }}</h2>
 <span class="profile-role-tag">{{ roleLabel }}</span>
 <div class="profile-stats" v-if="auth.user?.role === 'seller'">
 <span> {{ auth.user?.seller?.average_rating ?? '—' }}</span>
 <span>·</span>
 <span>{{ auth.user?.seller?.total_orders_completed ?? 0 }} طلب مكتمل</span>
 <span>·</span>
 <span>{{ auth.user?.seller?.total_reviews ?? 0 }} مراجعة</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Tabs -->
 <div class="profile-tabs">
 <button v-for="tab in tabs" :key="tab.key"
 class="profile-tab" :class="{ active: activeTab === tab.key }"
 @click="activeTab = tab.key"> {{ tab.label }}
 </button>
 </div>

 <!-- Tab: Personal info -->
 <Transition name="tab-content" mode="out-in">
 <div v-if="activeTab === 'info'" key="info" class="form-card">
 <h3 class="form-card__title">المعلومات الشخصية</h3>

 <div class="form-grid">
 <div class="field">
 <label class="field-label">الاسم الكامل</label>
 <input v-model="infoForm.name" type="text" class="field-input" placeholder="الاسم الكامل" />
 </div>
 <div class="field">
 <label class="field-label">البريد الإلكتروني</label>
 <input v-model="infoForm.email" type="email" class="field-input" dir="ltr" disabled />
 <span class="field-hint">لا يمكن تعديل البريد الإلكتروني</span>
 </div>
 <div class="field">
 <label class="field-label">رقم الهاتف</label>
 <input v-model="infoForm.phone" type="tel" class="field-input" placeholder="+966 5XX XXX XXX" dir="ltr" />
 </div>
 <div class="field">
 <label class="field-label">الدولة</label>
 <select v-model="infoForm.country" class="field-select">
 <option value="">— اختر الدولة —</option>
 <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
 </select>
 </div>
 <div class="field field--full">
 <label class="field-label">نبذة شخصية</label>
 <textarea v-model="infoForm.bio" class="field-textarea" rows="4"
 placeholder="اكتب نبذة مختصرة عن نفسك وخبراتك..."></textarea>
 </div>
 <div class="field" v-if="auth.user?.role === 'client'">
 <label class="field-label">اسم الشركة (اختياري)</label>
 <input v-model="infoForm.company_name" type="text" class="field-input" placeholder="اسم شركتك أو مؤسستك" />
 </div>
 <div class="field" v-if="auth.user?.role === 'seller'">
 <label class="field-label">رابط المحفظة / Portfolio</label>
 <input v-model="infoForm.portfolio_url" type="url" class="field-input" placeholder="https://yourportfolio.com" dir="ltr" />
 </div>
 <div class="field" v-if="auth.user?.role === 'seller'">
 <label class="field-label">الشعار المهني (Tagline)</label>
 <input v-model="infoForm.tagline" type="text" class="field-input" placeholder="مثال: مطور Full-Stack بخبرة 5 سنوات" />
 </div>
 </div>

 <div class="form-footer">
 <button class="save-btn" @click="saveInfo" :disabled="savingInfo">
 <span v-if="savingInfo" class="inline-spinner"></span> {{ savingInfo ? 'جاري الحفظ...' : ' حفظ التغييرات' }}
 </button>
 </div>
 </div>

 <!-- Tab: Security -->
 <div v-else-if="activeTab === 'security'" key="security" class="form-card">
 <h3 class="form-card__title">تغيير كلمة المرور</h3>

 <Transition name="alert">
 <div v-if="securityAlert.msg" class="alert" :class="`alert--${securityAlert.type}`"> {{ securityAlert.msg }}
 </div>
 </Transition>

 <div class="form-grid form-grid--narrow">
 <div class="field field--full">
 <label class="field-label">كلمة المرور الحالية</label>
 <div class="field-input-wrap">
 <input v-model="pwForm.current" :type="showCurrent ? 'text' : 'password'"
 class="field-input" placeholder="••••••••" dir="ltr" />
 <button type="button" class="field-toggle" @click="showCurrent = !showCurrent">{{ showCurrent ? '':'' }}</button>
 </div>
 </div>
 <div class="field">
 <label class="field-label">كلمة المرور الجديدة</label>
 <div class="field-input-wrap">
 <input v-model="pwForm.new" :type="showNew ? 'text' : 'password'"
 class="field-input" placeholder="••••••••" dir="ltr" />
 <button type="button" class="field-toggle" @click="showNew = !showNew">{{ showNew ? '':'' }}</button>
 </div>
 <div v-if="pwForm.new" class="strength-bar">
 <div class="strength-fill" :style="{ width: strengthWidth, background: strengthColor }"></div>
 </div>
 </div>
 <div class="field">
 <label class="field-label">تأكيد كلمة المرور الجديدة</label>
 <div class="field-input-wrap">
 <input v-model="pwForm.confirm" :type="showNew ? 'text' : 'password'"
 class="field-input" placeholder="••••••••" dir="ltr" />
 </div>
 <span v-if="pwForm.confirm && pwForm.new !== pwForm.confirm" class="field-error">كلمتا المرور غير متطابقتين</span>
 </div>
 </div>

 <div class="form-footer">
 <button class="save-btn" @click="changePassword" :disabled="savingPw">
 <span v-if="savingPw" class="inline-spinner"></span> {{ savingPw ? 'جاري التحديث...' : ' تحديث كلمة المرور' }}
 </button>
 </div>
 </div>

 <!-- Tab: Account -->
 <div v-else-if="activeTab === 'account'" key="account" class="form-card">
 <h3 class="form-card__title">إعدادات الحساب</h3>
 <div class="account-info-grid">
 <div class="ainfo-item">
 <span class="ainfo-label">نوع الحساب</span>
 <span class="ainfo-value">{{ roleLabel }}</span>
 </div>
 <div class="ainfo-item">
 <span class="ainfo-label">تاريخ التسجيل</span>
 <span class="ainfo-value">{{ formatDate(auth.user?.created_at) }}</span>
 </div>
 <div class="ainfo-item">
 <span class="ainfo-label">حالة الحساب</span>
 <span class="ainfo-value ainfo-value--active">● نشط</span>
 </div>
 <div class="ainfo-item" v-if="auth.user?.role === 'seller'">
 <span class="ainfo-label">حالة التوثيق</span>
 <span class="ainfo-value" :class="auth.user?.seller?.is_verified ? 'ainfo-value--active' : 'ainfo-value--pending'"> {{ auth.user?.seller?.is_verified ? '✓ موثق' : '⏳ في انتظار التوثيق' }}
 </span>
 </div>
 </div>

 <!-- Danger zone -->
 <div class="danger-zone">
 <h4 class="danger-zone__title"> منطقة الخطر</h4>
 <p class="danger-zone__desc">حذف الحساب نهائي ولا يمكن التراجع عنه. جميع بياناتك وطلباتك ستُحذف.</p>
 <button class="danger-btn" @click="confirmDeleteAccount"> حذف الحساب نهائياً</button>
 </div>
 </div>
 </Transition>

 <!-- Toast -->
 <Transition name="toast">
 <div v-if="toastMsg" class="toast" :class="`toast--${toastType}`">{{ toastMsg }}</div>
 </Transition>

 </div>
</template>

<script setup> import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/composables/useApi'

const auth = useAuthStore()

const activeTab = ref('info')
const savingInfo = ref(false)
const savingPw = ref(false)
const toastMsg = ref('')
const toastType = ref('success')
const showCurrent = ref(false)
const showNew = ref(false)

const securityAlert = ref({ msg: '', type: 'success' })

const tabs = [
 { key: 'info', icon: '', label: 'المعلومات الشخصية' },
 { key: 'security', icon: '', label: 'كلمة المرور' },
 { key: 'account', icon: '', label: 'الحساب' },
]

const infoForm = ref({
 name: auth.user?.name ?? '',
 email: auth.user?.email ?? '',
 phone: '', bio: '', country: '',
 company_name: '', portfolio_url: '', tagline: '',
})

const pwForm = ref({ current: '', new: '', confirm: '' })

const userInitials = computed(() => {
 const name = auth.user?.name ?? ''
 return name.split(' ').map(w => w[0]).slice(0, 2).join('')
})

const roleLabel = computed(() => auth.user?.role === 'admin' ? 'مدير المنصة' :
 auth.user?.role === 'seller' ? 'مستقل' : 'عميل'
)

const strengthScore = computed(() => {
 const p = pwForm.value.new; let s = 0
 if (p.length >= 8) s++
 if (/[A-Z]/.test(p)) s++
 if (/[a-z]/.test(p)) s++
 if (/\d/.test(p)) s++
 if (/[^A-Za-z0-9]/.test(p)) s++
 return s
})
const strengthWidth = computed(() => `${(strengthScore.value / 5) * 100}%`)
const strengthColor = computed(() => ['#EF4444','#F59E0B','#F59E0B','#10B981','#10B981'][Math.max(0, strengthScore.value - 1)] || '#EF4444'
)

async function saveInfo() {
 savingInfo.value = true
 try {
 const role = auth.user?.role
 const endpoint = role === 'seller' ? '/seller/profile' : '/client/profile'
 await api.put(endpoint, infoForm.value)
 await auth.fetchMe()
 showToast('تم حفظ المعلومات الشخصية ')
 } catch (e) {
 showToast(e.response?.data?.message ?? 'حدث خطأ', 'error')
 } finally { savingInfo.value = false }
}

async function changePassword() {
 securityAlert.value = { msg: '', type: 'success' }
 if (!pwForm.value.current) { securityAlert.value = { msg: 'أدخل كلمة المرور الحالية', type: 'error' }; return }
 if (pwForm.value.new.length < 8) { securityAlert.value = { msg: 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل', type: 'error' }; return }
 if (pwForm.value.new !== pwForm.value.confirm) { securityAlert.value = { msg: 'كلمتا المرور غير متطابقتين', type: 'error' }; return }
 savingPw.value = true
 try {
 await api.put('/auth/password', { current_password: pwForm.value.current, password: pwForm.value.new, password_confirmation: pwForm.value.confirm })
 pwForm.value = { current: '', new: '', confirm: '' }
 securityAlert.value = { msg: 'تم تحديث كلمة المرور بنجاح ', type: 'success' }
 } catch (e) {
 securityAlert.value = { msg: e.response?.data?.message ?? 'حدث خطأ', type: 'error' }
 } finally { savingPw.value = false }
}

function confirmDeleteAccount() {
 if (confirm('هل أنت متأكد تماماً من حذف حسابك؟ هذا الإجراء لا يمكن التراجع عنه.')) {
 showToast('يرجى التواصل مع الدعم لحذف الحساب نهائياً', 'error')
 }
}

function formatDate(iso) {
 if (!iso) return '—'
 return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { year: 'numeric', month: 'long', day: 'numeric' })
}

let toastTimer = null
function showToast(msg, type = 'success') {
 toastMsg.value = msg; toastType.value = type
 clearTimeout(toastTimer)
 toastTimer = setTimeout(() => { toastMsg.value = '' }, 4000)
}

onMounted(async () => {
 await auth.fetchMe()
 const profile = auth.profile
 if (profile) {
 infoForm.value.phone = profile.phone ?? ''
 infoForm.value.bio = profile.bio ?? ''
 infoForm.value.country = profile.country ?? ''
 infoForm.value.company_name = profile.company_name ?? ''
 infoForm.value.portfolio_url = profile.portfolio_url ?? ''
 infoForm.value.tagline = profile.tagline ?? ''
 }
})

const countries = ['المملكة العربية السعودية','الإمارات العربية المتحدة','الكويت','قطر','البحرين','عُمان','الأردن','مصر','العراق','سوريا','لبنان','اليمن','ليبيا','تونس','الجزائر','المغرب']
</script>

<style scoped> .profile-page { display: flex; flex-direction: column; gap: 22px; }

/* Hero */
.profile-hero { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 32px 36px; position: relative; overflow: hidden; }
.profile-hero__orb { position: absolute; top: -60px; right: -60px; width: 280px; height: 280px; background: radial-gradient(circle, rgba(99,102,241,0.12), transparent 70%); pointer-events: none; }
.avatar-section { display: flex; align-items: center; gap: 22px; position: relative; z-index: 1; }
.avatar-wrap { position: relative; flex-shrink: 0; }
.avatar-circle { width: 80px; height: 80px; border-radius: 50%; background: #5b74d6 100%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; color: white; box-shadow: 0 0 30px rgba(99,102,241,0.4); }
.avatar-verified { position: absolute; bottom: 2px; left: 2px; width: 22px; height: 22px; border-radius: 50%; background: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: white; border: 2px solid var(--color-bg-card); }
.profile-name { font-family: var(--font-display); font-size: 22px; font-weight: 800; margin-bottom: 5px; }
.profile-role-tag { display: inline-block; padding: 3px 12px; background: rgba(99,102,241,0.12); border-radius: var(--radius-full); font-size: 12px; font-weight: 600; color: var(--color-primary); margin-bottom: 8px; }
.profile-stats { font-size: 13px; color: var(--color-text-3); display: flex; gap: 8px; flex-wrap: wrap; }

/* Tabs */
.profile-tabs { display: flex; gap: 4px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 4px; width: fit-content; }
.profile-tab { padding: 9px 20px; border: none; border-radius: var(--radius-md); background: none; cursor: pointer; font-family: var(--font-body); font-size: 14px; font-weight: 500; color: var(--color-text-3); transition: background 0.2s, color 0.2s; white-space: nowrap; }
.profile-tab:hover { background: rgba(99,102,241,0.07); color: var(--color-text); }
.profile-tab.active { background: rgb(80, 109, 190); color: white; box-shadow: 0 2px 10px rgba(99,102,241,0.35); }

/* Form card */
.form-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; }
.form-card__title { font-family: var(--font-display); font-size: 16px; font-weight: 700; padding: 20px 28px; border-bottom: 1px solid var(--color-border); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding: 24px 28px; }
.form-grid--narrow { grid-template-columns: 1fr; }
.field--full { grid-column: 1 / -1; }
.form-footer { padding: 16px 28px; border-top: 1px solid var(--color-border); }

/* Fields */
.field { display: flex; flex-direction: column; gap: 7px; }
.field-label { font-size: 14px; font-weight: 600; color: var(--color-text-2); }
.field-hint { font-size: 11px; color: var(--color-text-3); }
.field-error { font-size: 12px; color: var(--color-error); }
.field-input, .field-select, .field-textarea {
 background: var(--color-bg-card); border: 1px solid var(--color-border);
 border-radius: var(--radius-md); color: var(--color-text);
 font-family: var(--font-body); font-size: 14px; outline: none;
 transition: border-color 0.2s, box-shadow 0.2s;
}
.field-input { height: 44px; padding: 0 14px; width: 100%; }
.field-select { height: 44px; padding: 0 14px; width: 100%; cursor: pointer; }
.field-textarea { width: 100%; padding: 12px 14px; resize: vertical; min-height: 100px; }
.field-input:focus, .field-select:focus, .field-textarea:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.field-input:disabled { opacity: 0.5; cursor: not-allowed; }
.field-input-wrap { position: relative; display: flex; align-items: center; }
.field-input-wrap .field-input { padding-left: 40px; }
.field-toggle { position: absolute; left: 12px; background: none; border: none; cursor: pointer; font-size: 16px; padding: 4px; }
.strength-bar { height: 3px; background: var(--color-border); border-radius: var(--radius-full); overflow: hidden; margin-top: 2px; }
.strength-fill { height: 100%; border-radius: var(--radius-full); transition: width 0.3s, background 0.3s; }
input::placeholder, textarea::placeholder {
  color: rgba(250, 249, 249, 0.6); 
}
/* Save button */
.save-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 28px; border-radius: var(--radius-full); background: rgb(80, 109, 190); border: none; color: white; font-family: var(--font-display); font-size: 15px; font-weight: 700; cursor: pointer; box-shadow: 0 0 20px rgba(99,102,241,0.35); transition: opacity 0.2s, transform 0.15s; }
.save-btn:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.save-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.inline-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Alert */
.alert { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: var(--radius-md); font-size: 14px; margin: 0 28px; }
.alert--success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: var(--color-success); }
.alert--error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: var(--color-error); }
.alert-enter-active,.alert-leave-active { transition: opacity 0.2s, transform 0.2s; }
.alert-enter-from,.alert-leave-to { opacity: 0; transform: translateY(-6px); }

/* Account info */
.account-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 24px 28px; }
.ainfo-item { display: flex; flex-direction: column; gap: 4px; padding: 14px 16px; background: var(--color-bg-2); border-radius: var(--radius-md); }
.ainfo-label { font-size: 12px; color: var(--color-text-3); }
.ainfo-value { font-size: 15px; font-weight: 600; color: var(--color-text); }
.ainfo-value--active { color: var(--color-success); }
.ainfo-value--pending { color: var(--color-gold); }

/* Danger zone */
.danger-zone { margin: 0 28px 28px; padding: 20px; border: 1px solid rgba(239,68,68,0.25); border-radius: var(--radius-lg); background: rgba(239,68,68,0.04); }
.danger-zone__title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--color-error); margin-bottom: 8px; }
.danger-zone__desc { font-size: 13px; color: var(--color-text-3); margin-bottom: 16px; line-height: 1.6; }
.danger-btn { padding: 10px 22px; border-radius: var(--radius-full); background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--color-error); font-family: var(--font-body); font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.danger-btn:hover { background: rgba(239,68,68,0.2); }

/* Tab content transition */
.tab-content-enter-active { transition: opacity 0.2s, transform 0.2s var(--ease-smooth); }
.tab-content-leave-active { transition: opacity 0.15s; }
.tab-content-enter-from { opacity: 0; transform: translateY(8px); }
.tab-content-leave-to { opacity: 0; }

/* Toast */
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); border-radius: var(--radius-full); padding: 13px 28px; font-size: 14px; font-weight: 600; box-shadow: var(--shadow-card); z-index: 600; white-space: nowrap; }
.toast--success { background: var(--color-bg-card); border: 1px solid var(--color-success); color: var(--color-success); }
.toast--error { background: var(--color-bg-card); border: 1px solid var(--color-error); color: var(--color-error); }
.toast-enter-active { transition: opacity 0.3s, transform 0.3s var(--ease-spring); }
.toast-leave-active { transition: opacity 0.2s, transform 0.2s; }
.toast-enter-from { opacity: 0; transform: translateX(-50%) translateY(16px); }
.toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }

@media (max-width: 768px) {
 .form-grid { grid-template-columns: 1fr; }
 .account-info-grid { grid-template-columns: 1fr; }
 .profile-tabs { flex-wrap: wrap; width: 100%; }
 .form-card__title, .form-grid, .danger-zone, .form-footer { padding-right: 16px; padding-left: 16px; }
}
</style>
