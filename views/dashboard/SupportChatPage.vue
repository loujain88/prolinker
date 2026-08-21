<template>
 <div class="support-chat" dir="rtl">
 <div class="sc-header">
 <h2> محادثة مع {{ conversation?.admin_name ?? 'الإدارة' }}</h2>
 <button v-if="conversation?.status === 'open'" class="close-btn" @click="closeChat">إنهاء المحادثة</button>
 <span v-else class="closed-badge">مغلقة</span>
 </div>

 <div class="sc-messages" ref="threadBody">
 <div v-if="loading" class="sc-empty">جاري التحميل...</div>
 <div v-for="m in messages" :key="m.id" class="bubble" :class="{ 'bubble--mine': m.sender_id === auth.user?.id }">
 <p class="bubble__body">{{ m.body }}</p>
 <span class="bubble__time">{{ formatTime(m.created_at) }}</span>
 </div>
 </div>

 <form v-if="conversation?.status === 'open'" class="sc-composer" @submit.prevent="sendReply">
 <input v-model="replyText" type="text" placeholder="اكتب رسالتك..." :disabled="sending" />
 <button type="submit" :disabled="sending || !replyText.trim()">إرسال</button>
 </form>
 </div>
</template>

<script setup> import { ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const conversation = ref(null)
const messages = ref([])
const loading = ref(true)
const replyText = ref('')
const sending = ref(false)
const threadBody = ref(null)

function formatTime(iso) {
 return new Date(iso).toLocaleString('ar-EG-u-nu-latn', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' })
}

async function fetchThread() {
 loading.value = true
 try {
 const { data } = await api.get(`/support/conversations/${route.params.id}`)
 conversation.value = data.data.conversation
 messages.value = data.data.messages ?? []
 await nextTick()
 if (threadBody.value) threadBody.value.scrollTop = threadBody.value.scrollHeight
 } finally { loading.value = false }
}

async function sendReply() {
 if (!replyText.value.trim()) return
 sending.value = true
 try {
 const { data } = await api.post(`/support/conversations/${route.params.id}/messages`, { body: replyText.value })
 messages.value.push(data.data)
 replyText.value = ''
 await nextTick()
 if (threadBody.value) threadBody.value.scrollTop = threadBody.value.scrollHeight
 } finally { sending.value = false }
}

async function closeChat() {
 await api.patch(`/support/conversations/${route.params.id}/close`)
 conversation.value.status = 'closed'
}

onMounted(fetchThread)
</script>

<style scoped> .support-chat { display: flex; flex-direction: column; height: calc(100vh - 140px); background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; }
.sc-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid var(--color-border); }
.sc-header h2 { font-size: 15px; font-weight: 800; }
.close-btn { font-size: 12px; background: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 6px 12px; cursor: pointer; color: var(--color-error); }
.closed-badge { font-size: 12px; color: var(--color-text-3); }
.sc-messages { flex: 1; overflow-y: auto; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; }
.sc-empty { text-align: center; color: var(--color-text-3); padding: 40px; }
.bubble { max-width: 70%; padding: 10px 14px; border-radius: var(--radius-lg); background: var(--color-bg-2); align-self: flex-start; }
.bubble--mine { align-self: flex-end; background: var(--grad-primary); color: white; }
.bubble__body { font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
.bubble__time { display: block; font-size: 10px; opacity: 0.7; margin-top: 4px; }
.sc-composer { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--color-border); }
.sc-composer input { flex: 1; height: 42px; padding: 0 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-bg-2); color: var(--color-text); font-family: var(--font-body); }
.sc-composer button { padding: 0 22px; border-radius: var(--radius-md); border: none; background: var(--grad-primary); color: white; font-weight: 700; cursor: pointer; }
.sc-composer button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
