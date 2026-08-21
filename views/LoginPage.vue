<template>
 <div class="auth-page" dir="rtl">
 <!-- Background orbs -->
 <div class="auth-orb auth-orb--1" aria-hidden="true"></div>
 <div class="auth-orb auth-orb--2" aria-hidden="true"></div>

 <div class="auth-container">
 <!-- Brand -->
 <RouterLink to="/" class="auth-brand">
 <span class="brand-icon"></span>
 <span class="brand-name" style="background: linear-gradient(120deg, #ffffff 30%, #38bdf8 100%); 
 -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text; display: inline-block;">ProLinker</span>
 </RouterLink>

 <div class="auth-card">
 <div class="auth-header">
 <h1 class="auth-title">مرحباً بعودتك</h1>
 <p class="auth-subtitle">أدخل بياناتك للوصول إلى حسابك</p>
 </div>

 <!-- Error alert -->
 <Transition name="alert">
 <div v-if="errorMsg" class="alert alert--error" role="alert">
 <span></span> {{ errorMsg }}
 </div>
 </Transition>

 <form @submit.prevent="handleLogin" novalidate>
 <!-- Email -->
 <div class="field" :class="{ 'field--error': errors.email }">
 <label class="field-label" for="email">البريد الإلكتروني</label>
 <div class="field-input-wrap">
 <span class="field-icon"></span>
 <input
 id="email"
 v-model="form.email"
 type="email"
 class="field-input"
 placeholder="example@email.com"
 autocomplete="email"
 :disabled="loading"
 @input="errors.email = ''"
 dir="ltr"
 />
 </div>
 <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
 </div>

 <!-- Password -->
 <div class="field" :class="{ 'field--error': errors.password }">
 <div class="field-label-row">
 <label class="field-label" for="password">كلمة المرور</label>
 <RouterLink to="/forgot-password" class="forgot-link">نسيت كلمة المرور</RouterLink>
 </div>
 <div class="field-input-wrap">
 <span class="field-icon"></span>
 <input
 id="password"
 v-model="form.password"
 :type="showPassword ? 'text' : 'password'"
 class="field-input"
 placeholder="••••••••"
 autocomplete="current-password"
 :disabled="loading"
 @input="errors.password = ''"
 dir="ltr"
 />
 <button
 type="button"
 class="field-toggle"
 @click="showPassword = !showPassword"
 :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
 >{{ showPassword ? '' : '' }}</button>
 </div>
 <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
 </div>

 <!-- Submit -->
 <button type="submit" class="auth-btn" :disabled="loading">
 <Transition name="btn-content" mode="out-in">
 <span v-if="loading" class="btn-spinner" key="loading">
 <span class="spinner"></span> جاري الدخول...
 </span>
 <span v-else key="idle">تسجيل الدخول</span>
 </Transition>
 </button>
 </form>

 <div class="auth-footer">
 <span>ليس لديك حساب؟</span>
 <RouterLink to="/register" class="auth-link">إنشاء حساب مجاني</RouterLink>
 </div>
 </div>
 </div>
 </div>
</template>

<script setup> import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const notifStore = useNotificationsStore()

const loading = ref(false)
const errorMsg = ref('')
const showPassword = ref(false)

const form = reactive({ email: '', password: '' })
const errors = reactive({ email: '', password: '' })

function validate() {
 let valid = true
 errors.email = ''
 errors.password = ''
 if (!form.email.trim()) { errors.email = 'البريد الإلكتروني مطلوب'; valid = false }
 else if (!/\S+@\S+\.\S+/.test(form.email)) { errors.email = 'بريد إلكتروني غير صحيح'; valid = false }
 if (!form.password) { errors.password = 'كلمة المرور مطلوبة'; valid = false }
 return valid
}

async function handleLogin() {
 errorMsg.value = ''
 if (!validate()) return
 loading.value = true
 try {
 const detectedRole = route.query.mode || null
 
 await auth.login(form.email, form.password, detectedRole)
 notifStore.startPolling(30_000)
 
 const currentToken = localStorage.getItem('pl_token') || auth.token
 const currentUser = auth.user
 const activeRole = auth.activeRole
 
 if (currentToken && currentUser && activeRole) {
 const savedAccounts = JSON.parse(localStorage.getItem('pl_saved_accounts') || '{}')
 savedAccounts[activeRole] = {
 token: currentToken,
 user: currentUser,
 activeRole: activeRole
 }
 localStorage.setItem('pl_saved_accounts', JSON.stringify(savedAccounts))
 }

 const redirect = route.query.redirect || '/dashboard'
 router.push(redirect)
 } catch (e) {
 errorMsg.value = e.response?.data?.message ?? 'حدث خطأ، يرجى المحاولة مجدداً'
 } finally {
 loading.value = false
 }
}
</script>

<style scoped> .auth-page {
 min-height: 100vh;
 display: flex; align-items: center; justify-content: center;
 padding: 24px;
 position: relative; overflow: hidden;
 background: linear-gradient(
145deg, #7394e7 2%, #9babd2 50%, #3b4a62 100%);
}
.auth-orb {
 position: absolute; border-radius: var(--radius-full);
 filter: blur(80px); pointer-events: none;
}
.auth-orb--1 { width: 400px; height: 400px; background: rgba(99,102,241,0.12); top: -100px; right: -100px; }
.auth-orb--2 { width: 300px; height: 300px; background: rgba(139,92,246,0.08); bottom: -80px; left: -80px; }

.auth-container {
 width: 100%; max-width: 440px;
 position: relative; z-index: 1;
 display: flex; flex-direction: column; align-items: center; gap: 28px;
}
.auth-brand {
 display: flex; align-items: center; gap: 10px;
 text-decoration: none;
}
.brand-icon { font-size: 28px; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.brand-name { font-family: var(--font-display); font-size: 24px; font-weight: 800 ; 
 
}
.auth-card {
 width: 100%;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-xl);
 padding: 36px 32px;
 box-shadow: var(--shadow-card), var(--shadow-glow);
}
.auth-header { text-align: center; margin-bottom: 28px; }
.auth-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; margin-bottom: 8px; }
.auth-subtitle { font-size: 14px; color: var(--color-text-3); }

/* Alert */
.alert {
 display: flex; align-items: center; gap: 10px;
 padding: 12px 16px;
 border-radius: var(--radius-md);
 font-size: 14px;
 margin-bottom: 20px;
}
.alert--error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #EF4444; }
.alert-enter-active,.alert-leave-active { transition: opacity 0.2s, transform 0.2s; }
.alert-enter-from,.alert-leave-to { opacity:0; transform:translateY(-6px); }

/* Fields */
.field { margin-bottom: 20px; }
.field-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.field-label { display: block; font-size: 14px; font-weight: 600; color: white; margin-bottom: 8px; }
.forgot-link { font-size: 12px; color: rgb(3, 107, 198); text-decoration: none; }
.forgot-link:hover { text-decoration: underline; }
.field-input-wrap { position: relative; display: flex; align-items: center; }
.field-icon { position: absolute; right: 14px; font-size: 16px;  pointer-events: none; z-index: 1; }
.field-input {
 width: 100%; height: 48px;
 padding: 0 44px 0 44px;
 background: var(--color-bg-2);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-md);
 color: var(--color-text);
 font-family: var(--font-body); font-size: 15px;
 outline: none;
 transition: border-color 0.2s, box-shadow 0.2s;
}
.field-input::placeholder { color: var(--color-text-3); }
.field-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.field--error .field-input { border-color: var(--color-error); }
.field-toggle {
 position: absolute; left: 12px;
 background: none; border: none; cursor: pointer; font-size: 16px; padding: 4px;
}
.field-error { display: block; font-size: 12px; color: var(--color-error); margin-top: 6px; }

/* Submit */
.auth-btn {
 width: 100%; height: 50px;
 background: rgb(92, 153, 206);
 border: none; border-radius: var(--radius-md);
 color: white; font-family: var(--font-display);
 font-size: 16px; font-weight: 700;
 cursor: pointer;
 box-shadow: 0 0 24px rgba(54, 55, 112, 0.35);
 transition: opacity 0.2s, box-shadow 0.2s, transform 0.15s;
 margin-top: 8px;
}
.auth-btn:hover:not(:disabled) { opacity: 0.9; box-shadow: 0 0 36px rgba(99,102,241,0.55); transform: translateY(-1px); }
.auth-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-spinner { display: flex; align-items: center; justify-content: center; gap: 10px; }
.spinner {
 width: 18px; height: 18px;
 border: 2px solid rgba(255,255,255,0.3);
 border-top-color: white;
 border-radius: var(--radius-full);
 animation: spin 0.7s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
.btn-content-enter-active,.btn-content-leave-active { transition: opacity 0.15s; }
.btn-content-enter-from,.btn-content-leave-to { opacity:0; }

.auth-footer { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 24px; font-size: 14px; color: var(--color-text-3); }
.auth-link { color:rgb(3, 107, 198) ; text-decoration: none; font-weight: 600; }
.auth-link:hover { text-decoration: underline; }

@media (max-width: 480px) {
 .auth-card { padding: 28px 20px; }
}
</style>