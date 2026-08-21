<template>
 <div class="admin-conv" dir="rtl">
 <div class="admin-conv__layout">
 <aside class="conv-list">
 <div class="conv-list__header">
 <h2>كل المحادثات</h2>
 <span class="conv-list__count">{{ conversations.length }}</span>
 </div>
 <div v-if="loadingList" class="conv-empty">جاري التحميل...</div>
 <div v-else-if="conversations.length === 0" class="conv-empty">لا توجد محادثات</div>
 <button
 v-for="c in conversations" :key="c.id"
 class="conv-item" :class="{ 'conv-item--active': activeId === c.id }"
 @click="openConversation(c.id)"
 >
 <div class="conv-item__row">
 <span class="conv-item__names">{{ c.client_name }} ⇄ {{ c.seller_name }}</span>
 <span v-if="c.request_id" class="conv-item__order">طلب #{{ c.request_id }}</span>
 </div>
 <span class="conv-item__service">{{ c.service?.title }}</span>
 <span class="conv-item__count">{{ c.messages_count }} رسالة</span>
 </button>
 </aside>

 <section class="thread">
 <div v-if="!activeId" class="thread-empty">اختر محادثة لعرض كل رسائلها</div>
 <template v-else>
 <div class="thread__header">
 <div>
 <RouterLink v-if="disputeId" :to="`/admin/disputes?open=${disputeId}`" class="back-to-dispute">← رجوع للنزاع #{{ disputeId }}</RouterLink>
 <h3>{{ activeConversation?.client_name }} ⇄ {{ activeConversation?.seller_name }}</h3>
 <span class="thread__service">{{ activeConversation?.service?.title }}</span>
 </div>
 <div class="thread__badges">
 <span v-if="disputeId" class="badge badge--dispute"> نزاع #{{ disputeId }}</span>
 <span v-if="orderId" class="badge">طلب #{{ orderId }}</span>
 </div>
 </div>
 <div class="thread__messages">
 <div v-if="loadingThread" class="thread-empty">جاري التحميل...</div>
 <div v-for="m in messages" :key="m.id" class="bubble" :class="{ 'bubble--seller': m.sender_role === 'seller' }">
 <span class="bubble__sender">{{ m.sender_name }} <em>({{ m.sender_role === 'seller' ? 'المستقل' : 'العميل' }})</em></span>
 <p class="bubble__body">{{ m.body }}</p>
 <div v-if="m.attachments?.length" class="bubble__attachments">
 <a v-for="(a, i) in m.attachments" :key="i" :href="a.url" target="_blank" rel="noopener" class="attachment-chip"> {{ a.name }}</a>
 </div>
 <span class="bubble__time">{{ formatTime(m.created_at) }}</span>
 </div>
 </div>
 </template>
 </section>
 </div>
 </div>
</template>

<script setup> import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import adminApi from '@/composables/useAdminApi'

const route = useRoute()
const disputeId = computed(() => route.query.dispute ? Number(route.query.dispute) : null)
const orderId = computed(() => route.query.order ? Number(route.query.order) : null)

const conversations = ref([])
const loadingList = ref(false)
const activeId = ref(null)
const messages = ref([])
const loadingThread = ref(false)

const activeConversation = computed(() => conversations.value.find(c => c.id === activeId.value) ?? null)

function formatTime(iso) {
 if (!iso) return ''
 return new Date(iso).toLocaleString('ar-EG-u-nu-latn', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' })
}

async function fetchConversations() {
 loadingList.value = true
 try {
 const { data } = await adminApi.getConversations()
 conversations.value = data.data ?? []
 } catch {
 conversations.value = []
 } finally { loadingList.value = false }
}

async function openConversation(id) {
 activeId.value = id
 loadingThread.value = true
 try {
 const { data } = await adminApi.getConversation(id)
 messages.value = data.data.messages ?? []
 } catch {
 messages.value = []
 } finally { loadingThread.value = false }
}

onMounted(async () => {
 await fetchConversations()
 if (route.params.id) openConversation(Number(route.params.id))
})
</script>

<style scoped> .admin-conv { height: calc(100vh - 100px); }
.admin-conv__layout { display: grid; grid-template-columns: 340px 1fr; height: 100%; border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; background: var(--color-bg-card); }

.conv-list { border-left: 1px solid var(--color-border); overflow-y: auto; }
.conv-list__header { padding: 16px 18px; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; }
.conv-list__header h2 { font-size: 15px; font-weight: 800; }
.conv-list__count { background: var(--color-bg-2); border-radius: 999px; padding: 2px 10px; font-size: 12px; }
.conv-empty { padding: 30px 16px; text-align: center; color: var(--color-text-3); }

.conv-item { display: flex; flex-direction: column; gap: 4px; width: 100%; text-align: right; padding: 12px 16px; background: none; border: none; border-bottom: 1px solid var(--color-border); cursor: pointer; }
.conv-item:hover { background: var(--color-bg-2); }
.conv-item--active { background: var(--color-bg-2); border-right: 3px solid var(--color-primary); }
.conv-item__row { display: flex; justify-content: space-between; align-items: center; }
.conv-item__names { font-size: 12.5px; font-weight: 700; }
.conv-item__order { font-size: 10px; background: var(--color-primary); color: white; border-radius: 999px; padding: 1px 8px; }
.conv-item__service { font-size: 11px; color: var(--color-text-3); }
.conv-item__count { font-size: 10px; color: var(--color-text-3); }

.thread { display: flex; flex-direction: column; }
.thread-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: var(--color-text-3); }
.thread__header { padding: 14px 20px; border-bottom: 1px solid var(--color-border); display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.thread__header h3 { font-size: 14px; font-weight: 800; }
.thread__service { font-size: 12px; color: var(--color-text-3); }
.back-to-dispute { display: block; font-size: 11.5px; color: var(--color-primary); text-decoration: none; margin-bottom: 6px; }
.back-to-dispute:hover { text-decoration: underline; }
.thread__badges { display: flex; gap: 6px; flex-shrink: 0; }
.badge { font-size: 11px; background: var(--color-bg-2); border-radius: 999px; padding: 4px 10px; color: var(--color-text-2); white-space: nowrap; }
.badge--dispute { background: rgba(239,68,68,0.12); color: #EF4444; font-weight: 700; }
.thread__messages { flex: 1; overflow-y: auto; padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; }
.bubble { max-width: 65%; padding: 10px 14px; border-radius: var(--radius-lg); background: var(--color-bg-2); align-self: flex-start; }
.bubble--seller { align-self: flex-end; background: var(--grad-primary); color: white; }
.bubble__sender { display: block; font-size: 10px; font-weight: 700; margin-bottom: 4px; opacity: 0.8; }
.bubble__body { font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
.bubble__attachments { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
.attachment-chip { font-size: 11px; text-decoration: underline; color: inherit; }
.bubble__time { display: block; font-size: 10px; opacity: 0.7; margin-top: 4px; }
</style>
