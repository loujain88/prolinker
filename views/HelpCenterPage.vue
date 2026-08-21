<template>
 <div class="help-page" dir="rtl">
 <AppNavbar />
 <div class="help-content">
 <h1> مركز المساعدة</h1>
 <p class="help-sub">إذا واجهت أي مشكلة أو عندك استفسار، تقدر تتواصل معنا مباشرة.</p>

 <button v-if="!auth.isAdmin" class="btn-primary" :disabled="starting" @click="startChat"> {{ starting ? 'جاري التحضير...' : ' بدء محادثة مع الإدارة' }}
 </button>

 <div class="admin-emails">
 <h3>أو راسلنا مباشرة عبر البريد:</h3>
 <ul>
 <li v-for="email in adminEmails" :key="email"> {{ email }}</li>
 </ul>
 </div>
 </div>
 </div>
</template>

<script setup> import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'
import AppNavbar from '@/components/shared/AppNavbar.vue'

const router = useRouter()
const auth = useAuthStore()
const starting = ref(false)

const adminEmails = [
 'ferasasaly@gmail.com',
 'enasalmobakher@gmail.com',
 'ayashail@gmail.com',
 'loujainsniter@gmail.com',
]

async function startChat() {
 if (!auth.isLoggedIn) {
 router.push({ name: 'login', query: { redirect: '/help' } })
 return
 }
 starting.value = true
 try {
 const { data } = await api.post('/support/conversations')
 router.push(`/dashboard/support/${data.data.id}`)
 } finally { starting.value = false }
}
</script>

<style scoped> .help-page { min-height: 100vh; }
.help-content { max-width: 640px; margin: 0 auto; padding: 130px 20px 60px; display: flex; flex-direction: column; gap: 20px; text-align: center; }
.help-content h1 { font-family: var(--font-display); font-size: 26px; font-weight: 800; }
.help-sub { color: var(--color-text-2); font-size: 14px; }
.btn-primary { align-self: center; padding: 14px 28px; border-radius: var(--radius-md); border: none; background: var(--grad-primary); color: white; font-weight: 700; cursor: pointer; font-size: 14px; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.admin-emails { margin-top: 20px; border-top: 1px solid var(--color-border); padding-top: 20px; }
.admin-emails h3 { font-size: 14px; margin-bottom: 10px; color: var(--color-text-2); }
.admin-emails ul { list-style: none; display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: var(--color-text-2); }
</style>
