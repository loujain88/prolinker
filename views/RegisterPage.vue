<template>
 <div class="auth-page" dir="rtl">
 <div class="auth-orb auth-orb--1" aria-hidden="true"></div>
 <div class="auth-orb auth-orb--2" aria-hidden="true"></div>

 <div class="auth-container">
 <RouterLink to="/" class="auth-brand">
 <span class="brand-icon"></span>
 <span class="brand-name grad-text">ProLinker</span>
 </RouterLink>

 <div class="auth-card">
 <div class="auth-header">
 <h1 class="auth-title">انضم مجاناً</h1>
 <p class="auth-subtitle">أنشئ حسابك وابدأ رحلتك في أقل من دقيقة</p>
 </div>

 <!-- Role toggle -->
 <div class="role-toggle" role="group" aria-label="نوع الحساب">
 <button
 type="button"
 class="role-btn"
 :class="{ active: form.role === 'client' }"
 @click="form.role = 'client'"
 >
 <span></span>
 <span class="role-label">أنا أبحث عن خدمة</span>
 <span class="role-sub">عميل</span>
 </button>
 <button
 type="button"
 class="role-btn"
 :class="{ active: form.role === 'seller' }"
 @click="form.role = 'seller'"
 >
 <span></span>
 <span class="role-label">أنا أقدم خدمة</span>
 <span class="role-sub">مستقل</span>
 </button>
 </div>

 <Transition name="alert">
 <div v-if="errorMsg" class="alert alert--error" role="alert">
 <span></span> {{ errorMsg }}
 </div>
 </Transition>

 <!-- Post-submission: pending approval -->
 <div v-if="submitted" class="pending-approval">
 <span class="pending-approval__icon"></span>
 <h2>تم استلام طلبك</h2>
 <p>{{ successMessage }}</p>
 <RouterLink to="/login" class="auth-btn">رجوع لتسجيل الدخول</RouterLink>
 </div>

 <form v-else @submit.prevent="handleRegister" novalidate>
 <!-- Name -->
 <div class="field" :class="{ 'field--error': errors.name }">
 <label class="field-label" for="name">الاسم الكامل</label>
 <div class="field-input-wrap">
 <span class="field-icon"></span>
 <input id="name" v-model="form.name" type="text" class="field-input"
 placeholder="محمد أحمد" autocomplete="name" :disabled="loading" @input="errors.name = ''" />
 </div>
 <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
 </div>

 <!-- Email -->
 <div class="field" :class="{ 'field--error': errors.email }">
 <label class="field-label" for="reg-email">البريد الإلكتروني</label>
 <div class="field-input-wrap">
 <span class="field-icon"></span>
 <input id="reg-email" v-model="form.email" type="email" class="field-input"
 placeholder="example@email.com" autocomplete="email" :disabled="loading" @input="errors.email = ''" dir="ltr" />
 </div>
 <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
 </div>

 <!-- Phone -->
 <div class="field" :class="{ 'field--error': errors.phone }">
 <label class="field-label" for="phone">رقم الموبايل</label>
 <div class="field-input-wrap">
 <span class="field-icon"></span>
 <input id="phone" v-model="form.phone" type="tel" class="field-input"
 placeholder="09xxxxxxxx" autocomplete="tel" :disabled="loading" @input="errors.phone = ''" dir="ltr" />
 </div>
 <span v-if="errors.phone" class="field-error">{{ errors.phone }}</span>
 </div>

 <!-- ID document -->
 <div class="field" :class="{ 'field--error': errors.id_document }">
 <label class="field-label" for="id-document">صورة الهوية الشخصية</label>
 <input id="id-document" type="file" class="field-file" accept=".jpg,.jpeg,.png,.pdf"
 :disabled="loading" @change="onIdDocumentChange" />
 <span class="field-hint">مطلوبة للتحقق من الهوية — صورة أو PDF، حتى 5 ميغابايت</span>
 <span v-if="idDocumentName" class="file-chip"> {{ idDocumentName }}</span>
 <span v-if="errors.id_document" class="field-error">{{ errors.id_document }}</span>
 </div>

 <!-- Password -->
 <div class="field" :class="{ 'field--error': errors.password }">
 <label class="field-label" for="reg-password">كلمة المرور</label>
 <div class="field-input-wrap">
 <span class="field-icon"></span>
 <input id="reg-password" v-model="form.password" :type="showPassword ? 'text' : 'password'"
 class="field-input" placeholder="8 أحرف على الأقل" autocomplete="new-password"
 :disabled="loading" @input="errors.password = ''" dir="ltr" />
 <button type="button" class="field-toggle" @click="showPassword = !showPassword"> {{ showPassword ? '' : '' }}
 </button>
 </div>
 <!-- Strength indicator -->
 <div v-if="form.password" class="strength-bar">
 <div class="strength-fill" :style="{ width: strengthWidth, background: strengthColor }"></div>
 </div>
 <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
 </div>

 <!-- Confirm password -->
 <div class="field" :class="{ 'field--error': errors.password_confirmation }">
 <label class="field-label" for="confirm">تأكيد كلمة المرور</label>
 <div class="field-input-wrap">
 <span class="field-icon"></span>
 <input id="confirm" v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'"
 class="field-input" placeholder="أعد إدخال كلمة المرور" autocomplete="new-password"
 :disabled="loading" @input="errors.password_confirmation = ''" dir="ltr" />
 </div>
 <span v-if="errors.password_confirmation" class="field-error">{{ errors.password_confirmation }}</span>
 </div>

 <!-- Client-only: preferred categories, used for personalized recommendations -->
 <div v-if="form.role === 'client' && categories.length" class="field">
 <label class="field-label">إيش نوع الخدمات يهمك؟ (اختياري)</label>
 <p class="field-hint">اخترنا لك اقتراحات أفضل بناءً على اهتماماتك</p>
 <div class="cat-grid">
 <button
 v-for="c in categories" :key="c.id" type="button"
 class="cat-chip" :class="{ 'cat-chip--active': form.preferred_category_ids.includes(c.id) }"
 @click="toggleCategory(c.id)"
 > {{ c.name }}
 </button>
 </div>
 </div>

 <button type="submit" class="auth-btn" :disabled="loading">
 <Transition name="btn-content" mode="out-in">
 <span v-if="loading" class="btn-spinner" key="loading">
 <span class="spinner"></span> جاري إنشاء الحساب...
 </span>
 <span v-else key="idle">إنشاء الحساب</span>
 </Transition>
 </button>
 </form>

 <div class="auth-footer">
 <span>لديك حساب بالفعل؟</span>
 <RouterLink to="/login" class="auth-link">تسجيل الدخول</RouterLink>
 </div>
 </div>
 </div>
 </div>
</template>

<script setup> import { ref, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import api from '@/composables/useApi'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const notifStore = useNotificationsStore()

const loading = ref(false)
const errorMsg = ref('')
const submitted = ref(false)
const successMessage = ref('')
const showPassword = ref(false)

const form = reactive({
 name: '', email: '', password: '', password_confirmation: '', phone: '',
 role: route.query.role === 'seller' ? 'seller' : 'client',
 preferred_category_ids: [],
})
const errors = reactive({ name:'', email:'', password:'', password_confirmation:'', phone:'', id_document:'' })
const idDocumentFile = ref(null)
const idDocumentName = ref('')

function onIdDocumentChange(e) {
 const file = e.target.files?.[0] ?? null
 idDocumentFile.value = file
 idDocumentName.value = file?.name ?? ''
 errors.id_document = ''
}

const categories = ref([])
async function fetchCategories() {
 try {
 const { data } = await api.get('/categories')
 categories.value = data.data ?? []
 } catch { categories.value = [] }
}
fetchCategories()

function toggleCategory(id) {
 const i = form.preferred_category_ids.indexOf(id)
 if (i === -1) {
 if (form.preferred_category_ids.length >= 8) return
 form.preferred_category_ids.push(id)
 } else {
 form.preferred_category_ids.splice(i, 1)
 }
}

// Password strength
const strengthScore = computed(() => {
 const p = form.password
 let s = 0
 if (p.length >= 8) s++
 if (/[A-Z]/.test(p)) s++
 if (/[a-z]/.test(p)) s++
 if (/\d/.test(p)) s++
 if (/[^A-Za-z0-9]/.test(p)) s++
 return s
})
const strengthWidth = computed(() => `${(strengthScore.value / 5) * 100}%`)
const strengthColor = computed(() => {
 const c = ['#EF4444','#F59E0B','#F59E0B','#10B981','#10B981']
 return c[Math.max(0, strengthScore.value - 1)] || '#EF4444'
})

function validate() {
 let valid = true
 Object.keys(errors).forEach(k => errors[k] = '')
 if (!form.name.trim()) { errors.name = 'الاسم مطلوب'; valid = false }
 if (!form.email.trim()) { errors.email = 'البريد الإلكتروني مطلوب'; valid = false }
 else if (!/\S+@\S+\.\S+/.test(form.email)) { errors.email = 'بريد إلكتروني غير صحيح'; valid = false }
 if (form.password.length < 8) { errors.password = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'; valid = false }
 if (form.password !== form.password_confirmation) { errors.password_confirmation = 'كلمتا المرور غير متطابقتين'; valid = false }
 if (!form.phone.trim()) { errors.phone = 'رقم الموبايل مطلوب'; valid = false }
 if (!idDocumentFile.value) { errors.id_document = 'صورة الهوية مطلوبة'; valid = false }
 return valid
}

async function handleRegister() {
 errorMsg.value = ''
 if (!validate()) return
 loading.value = true
 try {
 const fd = new FormData()
 Object.entries(form).forEach(([key, val]) => {
 if (key === 'preferred_category_ids') {
 val.forEach(id => fd.append('preferred_category_ids[]', id))
 } else {
 fd.append(key, val)
 }
 })
 fd.append('id_document', idDocumentFile.value)

 const data = await auth.register(fd)
 submitted.value = true
 successMessage.value = data.message ?? 'تم تقديم طلب إنشاء حساب، راجع بريدك الإلكتروني خلال مدة أقصاها 5 أيام.'
 } catch (e) {
 const data = e.response?.data
 if (data?.errors) {
 Object.keys(data.errors).forEach(k => { if (errors[k] !== undefined) errors[k] = data.errors[k][0] })
 } else {
 errorMsg.value = data?.message ?? 'حدث خطأ، يرجى المحاولة مجدداً'
 }
 } finally {
 loading.value = false
 }
}
</script>

<style scoped> /* Inherits base from LoginPage — shared styles extracted to global when needed */
.auth-page {
 min-height: 100vh; display: flex; align-items: center; justify-content: center;
 padding: 24px; position: relative; overflow: hidden; background: linear-gradient(145deg, #7394e7 2%, #9babd2 50%, #3b4a62 100%);
}
.auth-orb { position: absolute; border-radius: var(--radius-full); filter: blur(80px); pointer-events: none; }
.auth-orb--1 { width: 400px; height: 400px; background: rgba(139,92,246,0.12); top: -100px; left: -100px; }
.auth-orb--2 { width: 280px; height: 280px; background: rgba(99,102,241,0.08); bottom: -60px; right: -60px; }
.auth-container { width: 100%; max-width: 460px; position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 28px; }
.auth-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.brand-icon { font-size: 28px; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.brand-name { font-family: var(--font-display); font-size: 24px; font-weight: 800; }
.auth-card {
 width: 100%; background: var(--color-bg-card);
 border: 1px solid var(--color-border); border-radius: var(--radius-xl);
 padding: 32px 28px; box-shadow: var(--shadow-card), var(--shadow-glow);
}
.auth-header { text-align: center; margin-bottom: 24px; }
.auth-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; margin-bottom: 8px; }
.auth-subtitle { font-size: 14px; color: var(--color-text-3); }

/* Role toggle */
.role-toggle { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 24px; }
.role-btn {
 display: flex; flex-direction: column; align-items: center; gap: 6px;
 padding: 16px 12px;
 background: rgb(92, 153, 206);
 border: 2px solid var(--color-border);
 border-radius: var(--radius-lg);
 cursor: pointer; font-family: var(--font-body);
 transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.role-btn:hover { border-color: var(--color-border-hover); }
.role-btn.active { border-color: var(--color-primary); background: rgb(18, 70, 116); box-shadow: 0 0 20px var(--color-primary-glow); }
.role-btn > span:first-child { font-size: 26px; }
.role-label { font-size: 13px; color: white; font-weight: 600; }
.role-sub { font-size: 11px; color: var(--color-text-3); }
.role-btn.active .role-label { color: white; }

/* Alert */
.alert { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: var(--radius-md); font-size: 14px; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #EF4444; }
.alert-enter-active,.alert-leave-active { transition: opacity 0.2s, transform 0.2s; }
.alert-enter-from,.alert-leave-to { opacity:0; transform:translateY(-6px); }

/* Fields */
.field { margin-bottom: 18px; }
.field-label { display: block; font-size: 14px; font-weight: 600; color: var(--color-text-2); margin-bottom: 8px; }
.field-input-wrap { position: relative; display: flex; align-items: center; }
.field-icon { position: absolute; right: 14px; font-size: 15px; pointer-events: none; z-index: 1; }
.field-input {
 width: 100%; height: 46px; padding: 0 44px 0 44px;
 background: white; border: 1px solid var(--color-border);
 border-radius: var(--radius-md); color: black;
 font-family: var(--font-body); font-size: 14px; outline: none;
 transition: border-color 0.2s, box-shadow 0.2s;
}
.field-input::placeholder { color: gray; }
.field-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.field--error .field-input { border-color: var(--color-error); }
.field-toggle { position: absolute; left: 12px; background: none; border: none; cursor: pointer; font-size: 16px; }
.field-error { display: block; font-size: 12px; color: var(--color-error); margin-top: 5px; }
.field-hint { font-size: 11px; color: var(--color-text-3); margin: 2px 0 8px; }
.field-file { width: 100%; font-size: 12px; padding: 8px; border: 1px dashed var(--color-border); border-radius: var(--radius-md); background: white; color:gray; cursor: pointer; }
.file-chip { display: inline-block; margin-top: 6px; font-size: 11px; background: var(--color-bg-2); border: 1px solid var(--color-border); border-radius: 999px; padding: 3px 10px; }
.cat-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.cat-chip { padding: 7px 14px; border-radius: 999px; border: 1px solid var(--color-border); background: var(--color-bg-2); color: var(--color-text-2); font-size: 12px; cursor: pointer; transition: all .15s; }
.cat-chip:hover { border-color: var(--color-primary); }
.cat-chip--active { background: var(--grad-primary); border-color: transparent; color: white; font-weight: 600; }

/* Strength */
.strength-bar { height: 3px; background: var(--color-border); border-radius: var(--radius-full); margin-top: 6px; overflow: hidden; }
.strength-fill { height: 100%; border-radius: var(--radius-full); transition: width 0.3s, background 0.3s; }

/* Submit */
.auth-btn {
 width: 100%; height: 50px; background: rgb(92, 153, 206); border: none;
 border-radius: var(--radius-md); color: white; font-family: var(--font-display);
 font-size: 16px; font-weight: 700; cursor: pointer;
 box-shadow: 0 0 24px rgba(99,102,241,0.35);
 transition: opacity 0.2s, box-shadow 0.2s, transform 0.15s; margin-top: 8px;
}
.auth-btn:hover:not(:disabled) { opacity: 0.9; box-shadow: 0 0 36px rgba(99,102,241,0.55); transform: translateY(-1px); }
.pending-approval { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 20px 0; }
.pending-approval__icon { font-size: 48px; }
.pending-approval h2 { font-family: var(--font-display); font-size: 18px; font-weight: 800; }
.pending-approval p { font-size: 13px; color: var(--color-text-2); line-height: 1.8; max-width: 320px; }
.pending-approval .auth-btn { max-width: 240px; text-decoration: none; display: flex; align-items: center; justify-content: center; }
.auth-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-spinner { display: flex; align-items: center; justify-content: center; gap: 10px; }
.spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: var(--radius-full); animation: spin 0.7s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-content-enter-active,.btn-content-leave-active { transition: opacity 0.15s; }
.btn-content-enter-from,.btn-content-leave-to { opacity:0; }
.auth-footer { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; font-size: 14px; color: var(--color-text-3); }
.auth-link { color: rgb(3, 107, 198); text-decoration: none; font-weight: 600; }
.auth-link:hover { text-decoration: underline; }
@media (max-width: 480px) { .auth-card { padding: 24px 16px; } }
</style>
