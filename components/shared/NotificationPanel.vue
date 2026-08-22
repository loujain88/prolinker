<template>
 <Transition name="panel">
 <div v-if="open" class="notif-panel" role="dialog" aria-label="الإشعارات">
 <div class="panel-header">
 <h2 class="panel-title">الإشعارات</h2>
 <div class="panel-actions">
 <button v-if="store.hasUnread" class="mark-all-btn" @click="store.markAllAsRead()"> تحديد الكل كمقروء
 </button>
 <button class="close-btn" @click="$emit('close')" aria-label="إغلاق">✕</button>
 </div>
 </div>

 <!-- Loading -->
 <div v-if="store.loading" class="panel-empty">
 <div class="spinner"></div>
 <span>جاري التحميل...</span>
 </div>

 <!-- Empty -->
 <div v-else-if="store.items.length === 0" class="panel-empty">
 <span class="empty-icon"></span>
 <p>لا توجد إشعارات بعد</p>
 </div>

 <!-- List -->
 <div v-else class="notif-list">
 <TransitionGroup name="notif-item">
 <div
 v-for="n in store.items"
 :key="n.id"
 class="notif-item"
 :class="{ unread: !n.is_read }"
 @click="handleClick(n)"
 >
 <div class="notif-dot" v-if="!n.is_read"></div>
 <div class="notif-icon">{{ typeIcon(n.type) }}</div>
 <div class="notif-body">
 <p class="notif-content">{{ n.content }}</p>
 <time class="notif-time">{{ timeAgo(n.created_at) }}</time>
 </div>
 </div>
 </TransitionGroup>
 </div>
 </div>
 </Transition>

 <!-- Backdrop -->
 <Transition name="fade">
 <div v-if="open" class="notif-backdrop" @click="$emit('close')"></div>
 </Transition>
</template>

<script setup> import { onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'

const props = defineProps({ open: Boolean })
const emit = defineEmits(['close'])
const store = useNotificationsStore()
const router = useRouter()

watch(() => props.open, val => { if (val) store.fetchAll() })

async function handleClick(n) {
 if (!n.is_read) await store.markAsRead(n.id)
 if (n.action_url) { router.push(n.action_url); emit('close') }
}

function typeIcon(type) {
 const map = {
 'order.placed': '', 'order.accepted': '', 'order.delivered': '',
 'order.completed': '', 'order.cancelled': '',
 'dispute.opened': '', 'dispute.resolved': '',
 'deposit.pending': '', 'deposit.approved': '', 'deposit.rejected': '',
 'withdrawal.pending': '', 'withdrawal.approved': '✔', 'withdrawal.rejected': '',
 }
 return map[type] ?? ''
}

function timeAgo(iso) {
 const d = new Date(iso)
 const diff = Math.floor((Date.now() - d) / 1000)
 if (diff < 60) return 'الآن'
 if (diff < 3600) return `منذ ${Math.floor(diff / 60)} دقيقة`
 if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} ساعة`
 return `منذ ${Math.floor(diff / 86400)} يوم`
}
</script>

<style scoped> .notif-panel {
 position: fixed;
 top: 80px; left: 16px;
 width: 360px; max-height: 500px;
 background: rgb(53, 72, 118);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-xl);
 box-shadow: var(--shadow-card), var(--shadow-glow);
 z-index: 300;
 display: flex; flex-direction: column;
 overflow: hidden;
}
.panel-header {
 display: flex; align-items: center; justify-content: space-between;
 padding: 18px 20px;
 border-bottom: 1px solid var(--color-border);
 flex-shrink: 0;
 background:rgb(53, 72, 118) ;
}
.panel-title { font-family: var(--font-display); font-size: 16px; font-weight: 700; }
.panel-actions { display: flex; align-items: center; gap: 12px; }
.mark-all-btn {
 background: none; border: none; cursor: pointer;
 font-size: 12px; color: #47a8e0;
 font-family: var(--font-body); font-weight: 600;
}
.mark-all-btn:hover { text-decoration: underline; }
.close-btn {
 background: none; border: none; cursor: pointer;
 color: var(--color-text-3); font-size: 16px; padding: 2px 6px;
 border-radius: var(--radius-sm);
 transition: background 0.15s;
}
.close-btn:hover { background: rgba(255,255,255,0.08); }
.panel-empty {
 flex: 1; display: flex; flex-direction: column;
 align-items: center; justify-content: center;
 gap: 12px; padding: 40px;
 color: var(--color-text-3);
}
.empty-icon { font-size: 40px; }
.notif-list { overflow-y: auto; flex: 1; }
.notif-item {
 display: flex; align-items: flex-start; gap: 12px;
 padding: 14px 20px;
 border-bottom: 1px solid var(--color-border);
 cursor: pointer; position: relative;
 transition: background 0.15s;
}
.notif-item:hover { background: rgba(99,102,241,0.06); }
.notif-item.unread { background: rgba(99,102,241,0.05); }
.notif-dot {
 position: absolute; top: 18px; right: 8px;
 width: 6px; height: 6px;
 border-radius: var(--radius-full);
 background: var(--color-primary);
 flex-shrink: 0;
}
.notif-icon { font-size: 22px; flex-shrink: 0; line-height: 1; margin-top: 2px; }
.notif-body { flex: 1; min-width: 0; }
.notif-content { font-size: 13px; color: var(--color-text); line-height: 1.5; }
.notif-time { font-size: 11px; color: var(--color-text-3); display: block; margin-top: 4px; }
.spinner {
 width: 28px; height: 28px;
 border: 2px solid var(--color-border);
 border-top-color: var(--color-primary);
 border-radius: var(--radius-full);
 animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.notif-backdrop {
 position: fixed; inset: 0; z-index: 250;
 background: transparent;
}
/* Transitions */
.panel-enter-active { transition: opacity 0.2s, transform 0.25s var(--ease-spring); }
.panel-leave-active { transition: opacity 0.15s, transform 0.15s; }
.panel-enter-from { opacity: 0; transform: translateY(-12px) scale(0.97); }
.panel-leave-to { opacity: 0; transform: translateY(-8px); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.notif-item-enter-active { transition: opacity 0.2s, transform 0.2s; }
.notif-item-enter-from { opacity: 0; transform: translateX(12px); }
@media (max-width: 480px) {
 .notif-panel { left: 8px; right: 8px; width: auto; }
}
</style>
