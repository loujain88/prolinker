<template>
  <div class="messages-page" dir="rtl">
    <div class="msg-layout">
      <!-- Conversation list -->
      <aside class="conv-list">
        <div class="conv-list__header">
          <h2>المحادثات</h2>
        </div>

        <div v-if="loadingList" class="conv-empty">جاري التحميل...</div>
        <div v-else-if="conversations.length === 0" class="conv-empty">
          <span class="conv-empty__icon">💬</span>
          <p>لا توجد محادثات بعد</p>
        </div>

        <button
          v-for="c in conversations" :key="c.id"
          class="conv-item" :class="{ 'conv-item--active': activeId === c.id }"
          @click="openConversation(c.id)"
        >
          <div class="conv-item__avatar">{{ initials(otherPartyName(c)) }}</div>
          <div class="conv-item__body">
            <div class="conv-item__top">
              <span class="conv-item__name">{{ otherPartyName(c) }}</span>
              <span v-if="c.unread_count > 0" class="conv-item__unread">{{ c.unread_count }}</span>
            </div>
            <span class="conv-item__service">{{ c.service?.title }}</span>
          </div>
        </button>
      </aside>

      <!-- Thread -->
      <section class="thread">
        <div v-if="!activeId" class="thread-empty">
          <span class="thread-empty__icon">✉️</span>
          <p>اختر محادثة من القائمة لعرضها</p>
        </div>

        <template v-else>
          <div class="thread__header">
            <div>
              <h3>{{ activeConversation ? otherPartyName(activeConversation) : '' }}</h3>
              <span class="thread__service">{{ activeConversation?.service?.title }}</span>
            </div>
            <RouterLink v-if="activeConversation?.service?.id" :to="`/services/${activeConversation.service.id}`" class="view-service-link">
              عرض الخدمة ↗
            </RouterLink>
          </div>

          <!-- منطقة التمرير الرئيسية للرسائل -->
          <div class="thread__messages" ref="threadBody">
            <div v-if="loadingThread" class="thread-empty"><p>جاري التحميل...</p></div>
            <div
              v-for="m in messages" :key="m.id"
              class="bubble" :class="{ 'bubble--mine': m.sender_id === auth.user?.id }"
            >
              <p class="bubble__body">{{ m.body }}</p>
              <div v-if="m.attachments?.length" class="bubble__attachments">
                <a v-for="(a, i) in m.attachments" :key="i" :href="a.url" target="_blank" rel="noopener" class="attachment-chip">
                  📎 {{ a.name }}
                </a>
              </div>
              <span class="bubble__time">{{ formatTime(m.created_at) }}</span>
            </div>
          </div>

          <div v-if="replyFiles.length" class="file-chip-list">
            <span v-for="(f,i) in replyFiles" :key="i" class="file-chip">
              {{ f.name }} <button type="button" @click="replyFiles.splice(i,1)">✕</button>
            </span>
          </div>

          <form class="thread__composer" @submit.prevent="sendReply">
            <label class="attach-btn" title="إرفاق ملفات">
              + <input type="file" multiple hidden @change="onReplyFiles" />
            </label>
            <input v-model="replyText" type="text" placeholder="اكتب رسالة..." :disabled="sending" />
            <button type="submit" :disabled="sending || (!replyText.trim() && !replyFiles.length)">إرسال</button>
          </form>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()

const conversations = ref([])
const loadingList = ref(false)
const activeId = ref(null)
const messages = ref([])
const loadingThread = ref(false)
const replyText = ref('')
const replyFiles = ref([])
const sending = ref(false)
const threadBody = ref(null)

function onReplyFiles(e) {
  replyFiles.value.push(...Array.from(e.target.files ?? []))
  e.target.value = ''
}

const activeConversation = computed(() => conversations.value.find(c => c.id === activeId.value) ?? null)

function otherPartyName(c) {
  return auth.isSeller ? c.client_name : c.seller_name
}
function initials(name) {
  return (name ?? '').split(' ').map(w => w[0]).slice(0, 2).join('')
}
function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('ar-EG-u-nu-latn', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' })
}

const scrollToBottom = async () => {
  await nextTick()
  if (threadBody.value) {
    threadBody.value.scrollTop = threadBody.value.scrollHeight
  }
}

async function fetchConversations() {
  loadingList.value = true
  try {
    const { data } = await api.get('/conversations')
    conversations.value = data.data ?? []
  } catch {
    conversations.value = []
  } finally { loadingList.value = false }
}

async function openConversation(id) {
  activeId.value = id
  loadingThread.value = true
  try {
    const { data } = await api.get(`/conversations/${id}`)
    messages.value = data.data.messages ?? []
    const conv = conversations.value.find(c => c.id === id)
    if (conv) conv.unread_count = 0
    await scrollToBottom()
  } catch {
    messages.value = []
  } finally { loadingThread.value = false }
}

async function sendReply() {
  if ((!replyText.value.trim() && !replyFiles.value.length) || !activeId.value) return
  sending.value = true
  try {
    const fd = new FormData()
    fd.append('body', replyText.value.trim() || 'مرفق')
    replyFiles.value.forEach(f => fd.append('attachments[]', f))
    const { data } = await api.post(`/conversations/${activeId.value}/messages`, fd, { headers: { 'Content-Type': undefined } })
    messages.value.push(data.data)
    replyText.value = ''
    replyFiles.value = []
    await scrollToBottom()
  } catch {
    // silent fail
  } finally { sending.value = false }
}

onMounted(async () => {
  await fetchConversations()
  const target = route.query.conversation ? Number(route.query.conversation) : conversations.value[0]?.id
  if (target) openConversation(target)
})
</script>

<style scoped>
.messages-page { 
  height: calc(100vh - 120px); 
  display: flex;
  flex-direction: column;
}

.msg-layout { 
  display: grid; 
  grid-template-columns: 300px 1fr; 
  height: 100%; 
  border: 1px solid var(--color-border); 
  border-radius: var(--radius-xl); 
  overflow: hidden; 
  background: var(--color-bg-card); 
}

/* قائمة المحادثات */
.conv-list { 
  border-left: 1px solid var(--color-border); 
  overflow-y: auto; 
  display: flex; 
  flex-direction: column; 
  height: 100%;
}
.conv-list__header { padding: 16px 18px; border-bottom: 1px solid var(--color-border); flex-shrink: 0; }
.conv-list__header h2 { font-family: var(--font-display); font-size: 16px; font-weight: 800; }
.conv-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 40px 16px; color: white; text-align: center; }
.conv-empty__icon { font-size: 32px; }

.conv-item { display: flex; gap: 10px; align-items: center; padding: 12px 16px; background: none; border: none; border-bottom: 1px solid var(--color-border); cursor: pointer; text-align: right; width: 100%; transition: background 0.15s; flex-shrink: 0; }
.conv-item:hover { background: var(--color-bg-2); }
.conv-item--active { background: rgb(80, 109, 190); border-right: 3px solid var(--color-primary); }
.conv-item__avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--grad-primary); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: white; flex-shrink: 0; }
.conv-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.conv-item__top { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.conv-item__name { font-size: 13px; font-weight: 700; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv-item__unread { background: var(--color-primary); color: white; font-size: 10px; font-weight: 700; border-radius: 999px; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }
.conv-item__service { font-size: 11px; color: var(--color-text-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* منطقة المحادثة الحالية (Thread) */
.thread { 
  display: flex; 
  flex-direction: column; 
  height: 100%; 
  min-height: 0; /* منع الحاوية الأبوية من التمدد خارج الإطار */
  overflow: hidden;
}
.thread-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: white; }
.thread-empty__icon { font-size: 40px; }

.thread__header { 
  display: flex; 
  align-items: center; 
  justify-content: space-between; 
  padding: 14px 20px; 
  border-bottom: 1px solid var(--color-border); 
  flex-shrink: 0; 
}
.thread__header h3 { font-family: var(--font-display); font-size: 15px; font-weight: 800; }
.thread__service { font-size: 12px; color: var(--color-text-3); }
.view-service-link { font-size: 12px; color: white; text-decoration: none; font-weight: 600; }

/* تفعيل التمرير العمودي (Scroll) للرسائل فقط */
.thread__messages { 
  flex: 1; 
  overflow-y: auto; 
  padding: 18px 20px; 
  display: flex; 
  flex-direction: column; 
  gap: 10px; 
  min-height: 0; 
  scroll-behavior: smooth;
}

/* تخصيص شريط التمرير ليصبح أنيقاً */
.thread__messages::-webkit-scrollbar,
.conv-list::-webkit-scrollbar {
  width: 6px;
}
.thread__messages::-webkit-scrollbar-thumb,
.conv-list::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
}

.bubble { max-width: 70%; padding: 10px 14px; border-radius: var(--radius-lg); background: rgb(142, 82, 225); align-self: flex-start; }
.bubble--mine { align-self: flex-end; background: var(--grad-primary); color: white; }
.bubble__body { font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
.bubble__attachments { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
.attachment-chip { font-size: 11px; text-decoration: underline; color: inherit; }
.bubble__time { display: block; font-size: 10px; opacity: 0.7; margin-top: 4px; }

.attach-btn { cursor: pointer; font-size: 17px; padding: 0 6px; display: flex; align-items: center; }
.file-chip-list { list-style: none; display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 20px 0; margin: 0; flex-shrink: 0; }
.file-chip { display: flex; align-items: center; gap: 6px; background: var(--color-bg-2); border: 1px solid var(--color-border); border-radius: 999px; padding: 3px 10px; font-size: 11px; }
.file-chip button { background: none; border: none; cursor: pointer; color: var(--color-error); }

.thread__composer { 
  display: flex; 
  gap: 10px; 
  padding: 14px 20px; 
  border-top: 1px solid var(--color-border); 
  flex-shrink: 0; 
}
.thread__composer input { flex: 1; height: 42px; padding: 0 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: white; color: black; font-family: var(--font-body); font-size: 13px; outline: none; }
.thread__composer input:focus { border-color: var(--color-primary); }
.thread__composer button { padding: 0 22px; border-radius: var(--radius-md); border: none; background: rgb(80, 109, 190); color: rgb(255, 255, 255); font-weight: 700; font-size: 13px; cursor: pointer; }
.thread__composer button:disabled { opacity: 0.5; cursor: not-allowed; }

@media (max-width: 768px) {
  .msg-layout { grid-template-columns: 1fr; }
  .conv-list { display: none; }
}
</style>