<template>
 <div class="admin-support" dir="rtl">
 <div class="as-layout">
 <aside class="conv-list">
 <div class="conv-list__header"><h2>محادثات الدعم المخصّصة لي</h2></div>
 <div v-if="loadingList" class="conv-empty">جاري التحميل...</div>
 <div v-else-if="conversations.length === 0" class="conv-empty">ما في محادثات دعم حالياً</div>
 <button
 v-for="c in conversations" :key="c.id"
 class="conv-item" :class="{ 'conv-item--active': activeId === c.id }"
 @click="openConversation(c.id)"
 >
 <span class="conv-item__name">{{ c.user_name }}</span>
 <span class="conv-item__status" :class="`status--${c.status}`">{{ c.status === 'open' ? 'مفتوحة' : 'مغلقة' }}</span>
 </button>
 </aside>

 <section class="thread">
 <div v-if="!activeId" class="thread-empty">اختر محادثة لعرضها</div>
 <template v-else>
 <div class="thread__messages" ref="threadBody">
 <div v-if="loadingThread" class="thread-empty">جاري التحميل...</div>
 <div v-for="m in messages" :key="m.id" class="bubble" :class="{ 'bubble--mine': m.sender_role === 'admin' }">
 <p class="bubble__body">{{ m.body }}</p>
 <span class="bubble__time">{{ formatTime(m.created_at) }}</span>
 </div>
 </div>
 <form v-if="activeConversation?.status === 'open'" class="thread__composer" @submit.prevent="sendReply">
 <input v-model="replyText" type="text" placeholder="اكتب رداً..." :disabled="sending" />
 <button type="submit" :disabled="sending || !replyText.trim()">إرسال</button>
 </form>
 </template>
 </section>
 </div>
 </div>
</template>

<script setup> import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/composables/useApi'

const conversations = ref([])
const loadingList = ref(false)
const activeId = ref(null)
const messages = ref([])
const loadingThread = ref(false)
const replyText = ref('')
const sending = ref(false)
const threadBody = ref(null)

const activeConversation = computed(() => conversations.value.find(c => c.id === activeId.value) ?? null)

function formatTime(iso) {
 return new Date(iso).toLocaleString('ar-EG-u-nu-latn', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' })
}

async function fetchConversations() {
 loadingList.value = true
 try {
 const { data } = await api.get('/admin/support-conversations')
 conversations.value = data.data ?? []
 } finally { loadingList.value = false }
}

async function openConversation(id) {
 activeId.value = id
 loadingThread.value = true
 try {
 const { data } = await api.get(`/support/conversations/${id}`)
 messages.value = data.data.messages ?? []
 await nextTick()
 if (threadBody.value) threadBody.value.scrollTop = threadBody.value.scrollHeight
 } finally { loadingThread.value = false }
}

async function sendReply() {
 if (!replyText.value.trim()) return
 sending.value = true
 try {
 const { data } = await api.post(`/support/conversations/${activeId.value}/messages`, { body: replyText.value })
 messages.value.push(data.data)
 replyText.value = ''
 await nextTick()
 if (threadBody.value) threadBody.value.scrollTop = threadBody.value.scrollHeight
 } finally { sending.value = false }
}

onMounted(fetchConversations)
</script>

<style scoped> .admin-support { height: calc(100vh - 100px); }
.as-layout { display: grid; grid-template-columns: 300px 1fr; height: 100%; border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; background: var(--color-bg-card); }
.conv-list { border-left: 1px solid var(--color-border); overflow-y: auto; }
.conv-list__header { padding: 16px 18px; border-bottom: 1px solid var(--color-border); }
.conv-list__header h2 { font-size: 14px; font-weight: 800; }
.conv-empty { padding: 30px 16px; text-align: center; color: var(--color-text-3); font-size: 13px; }
.conv-item { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 12px 16px; background: none; border: none; border-bottom: 1px solid var(--color-border); cursor: pointer; text-align: right; }
.conv-item:hover { background: var(--color-bg-2); }
.conv-item--active { background: var(--color-bg-2); border-right: 3px solid var(--color-primary); }
.conv-item__name { font-size: 13px; font-weight: 600; }
.conv-item__status { font-size: 10px; padding: 2px 8px; border-radius: 999px; background: var(--color-bg-2); }
.status--open { color: var(--color-success); }
.status--closed { color: var(--color-text-3); }
.thread { display: flex; flex-direction: column; }
.thread-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: var(--color-text-3); }
.thread__messages { flex: 1; overflow-y: auto; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; }
.bubble { max-width: 70%; padding: 10px 14px; border-radius: var(--radius-lg); background: var(--color-bg-2); align-self: flex-start; }
.bubble--mine { align-self: flex-end; background: var(--grad-primary); color: white; }
.bubble__body { font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
.bubble__time { display: block; font-size: 10px; opacity: 0.7; margin-top: 4px; }
.thread__composer { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--color-border); }
.thread__composer input { flex: 1; height: 42px; padding: 0 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-bg-2); color: var(--color-text); font-family: var(--font-body); }
.thread__composer button { padding: 0 22px; border-radius: var(--radius-md); border: none; background: var(--grad-primary); color: white; font-weight: 700; cursor: pointer; }
.thread__composer button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
