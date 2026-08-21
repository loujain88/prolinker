<template>
 <div class="admin-staff" dir="rtl">
 <div class="as-header">
 <h1> إدارة الموظفين</h1>
 <button class="btn-primary" @click="openAdd"> إضافة موظف</button>
 </div>

 <p class="as-hint">الموظفين عندهم كل صلاحيات لوحة التحكم، ما عدا إضافة/تعديل/حذف موظفين آخرين — هاي محصورة فيك بس.</p>

 <div v-if="loading" class="as-state">جاري التحميل...</div>
 <div v-else-if="staff.length === 0" class="as-state">
 <span></span>
 <p>ما في موظفين مضافين بعد</p>
 </div>

 <table v-else class="as-table">
 <thead>
 <tr><th>الاسم</th><th>البريد</th><th>تاريخ الإضافة</th><th></th></tr>
 </thead>
 <tbody>
 <tr v-for="s in staff" :key="s.id">
 <td>{{ s.name }}</td>
 <td dir="ltr">{{ s.email }}</td>
 <td>{{ formatDate(s.created_at) }}</td>
 <td>
 <button class="del-btn" :disabled="actionId === s.id" @click="remove(s)"> حذف</button>
 </td>
 </tr>
 </tbody>
 </table>

 <!-- Add staff modal -->
 <Teleport to="body">
 <div v-if="addModal.open" class="modal-overlay" @mousedown.self="addModal.open = false">
 <div class="modal">
 <h3>إضافة موظف جديد</h3>
 <div class="field">
 <label>الاسم</label>
 <input v-model="addForm.name" type="text" />
 </div>
 <div class="field">
 <label>البريد الإلكتروني</label>
 <input v-model="addForm.email" type="email" dir="ltr" />
 </div>
 <div class="field">
 <label>كلمة المرور</label>
 <input v-model="addForm.password" type="password" dir="ltr" />
 </div>
 <div class="field">
 <label>تأكيد كلمة المرور</label>
 <input v-model="addForm.password_confirmation" type="password" dir="ltr" />
 </div>
 <span v-if="addModal.err" class="field-error">{{ addModal.err }}</span>
 <div class="modal__footer">
 <button class="btn-outline" @click="addModal.open = false">إلغاء</button>
 <button class="btn-primary" :disabled="submitting" @click="submitAdd"> {{ submitting ? 'جاري الإضافة...' : 'إضافة' }}
 </button>
 </div>
 </div>
 </div>
 </Teleport>
 </div>
</template>

<script setup> import { ref, reactive, onMounted } from 'vue'
import adminApi from '@/composables/useAdminApi'

const staff = ref([])
const loading = ref(false)
const actionId = ref(null)
const submitting = ref(false)
const addModal = reactive({ open: false, err: '' })
const addForm = reactive({ name: '', email: '', password: '', password_confirmation: '' })

function formatDate(iso) {
 return new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { year: 'numeric', month: 'short', day: 'numeric' })
}

async function fetchStaff() {
 loading.value = true
 try {
 const { data } = await adminApi.getStaff()
 staff.value = data.data ?? []
 } finally { loading.value = false }
}

function openAdd() {
 Object.assign(addForm, { name: '', email: '', password: '', password_confirmation: '' })
 addModal.err = ''
 addModal.open = true
}

async function submitAdd() {
 addModal.err = ''
 if (!addForm.name.trim() || !addForm.email.trim() || !addForm.password) {
 addModal.err = 'الرجاء تعبئة كل الحقول'
 return
 }
 if (addForm.password !== addForm.password_confirmation) {
 addModal.err = 'كلمتا المرور غير متطابقتين'
 return
 }
 submitting.value = true
 try {
 await adminApi.addStaff(addForm)
 addModal.open = false
 fetchStaff()
 } catch (e) {
 addModal.err = e.response?.data?.message ?? 'حدث خطأ أثناء الإضافة'
 } finally { submitting.value = false }
}

async function remove(s) {
 if (!confirm(`متأكد من حذف الموظف "${s.name}"؟`)) return
 actionId.value = s.id
 try {
 await adminApi.removeStaff(s.id)
 staff.value = staff.value.filter(x => x.id !== s.id)
 } finally { actionId.value = null }
}

onMounted(fetchStaff)
</script>

<style scoped> .as-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.as-header h1 { font-family: var(--font-display); font-size: 20px; font-weight: 800; }
.as-hint { font-size: 12.5px; color: var(--color-text-3); margin-bottom: 20px; }
.as-state { text-align: center; padding: 60px 20px; color: var(--color-text-3); display: flex; flex-direction: column; align-items: center; gap: 10px; }
.as-state span { font-size: 36px; }

.as-table { width: 100%; border-collapse: collapse; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; }
.as-table th { text-align: right; padding: 12px 16px; font-size: 12px; color: var(--color-text-3); border-bottom: 1px solid var(--color-border); }
.as-table td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid var(--color-border); }
.del-btn { background: none; border: 1px solid var(--color-error); color: var(--color-error); border-radius: var(--radius-md); padding: 5px 12px; cursor: pointer; font-size: 12px; }
.del-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-primary { padding: 9px 18px; border-radius: var(--radius-md); border: none; background: rgb(80, 109, 190); color: white; font-weight: 700; cursor: pointer; font-size: 13px; }
.btn-outline { padding: 9px 18px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: none; color: var(--color-text); cursor: pointer; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 600; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 20px; max-width: 400px; width: 100%; display: flex; flex-direction: column; gap: 12px; }
.modal h3 { font-size: 15px; font-weight: 700; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 12.5px; font-weight: 600; }

.field input { padding: 9px 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background:rgb(80, 109, 190); color: var(--color-text); font-family: var(--font-body); }
.field input:-webkit-autofill,
.field input:-webkit-autofill:hover, 
.field input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 30px rgb(80, 109, 190) inset !important;
  -webkit-text-fill-color: #ffffff !important;
}
.field-error { font-size: 12px; color: var(--color-error); }
.modal__footer { display: flex; gap: 10px; margin-top: 6px; }
.modal__footer button { flex: 1; }
</style>
