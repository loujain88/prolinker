<template>
 <div class="all-services-page" dir="rtl">
 <AppNavbar />
 <div class="all-services" dir="rtl">
 <div class="as-header">
 <h1>تصفّح الخدمات</h1>
 <div class="as-search">
 <input v-model="search" type="text" placeholder="ابحث عن خدمة..." @keyup.enter="fetchServices(1)" />
 <button @click="fetchServices(1)">بحث</button>
 </div>
 <div class="as-cats">
 <button class="cat-pill" :class="{ active: !categoryId }" @click="selectCategory(null)">الكل</button>
 <button v-for="c in categories" :key="c.id" class="cat-pill" :class="{ active: categoryId === c.id }" @click="selectCategory(c.id)">{{ c.name }}</button>
 </div>
 </div>

 <div v-if="loading" class="as-state">جاري التحميل...</div>
 <div v-else-if="services.length === 0" class="as-state">
 <span></span>
 <p>ما في خدمات مطابقة</p>
 </div>

 <div v-else class="as-grid">
 <ServiceCard v-for="s in services" :key="s.id" :service="s" />
 </div>

 <div v-if="meta.last_page > 1" class="as-pagination">
 <button :disabled="meta.current_page <= 1" @click="fetchServices(meta.current_page - 1)">→ السابق</button>
 <span>صفحة {{ meta.current_page }} من {{ meta.last_page }}</span>
 <button :disabled="meta.current_page >= meta.last_page" @click="fetchServices(meta.current_page + 1)">التالي ←</button>
 </div>
 </div>
 </div>
</template>

<script setup> import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'
import ServiceCard from '@/components/shared/ServiceCard.vue'
import AppNavbar from '@/components/shared/AppNavbar.vue'

const route = useRoute()
const router = useRouter()

const services = ref([])
const categories = ref([])
const loading = ref(false)
const search = ref(route.query.q ?? '')
const categoryId = ref(route.query.category ? Number(route.query.category) : null)
const meta = ref({ current_page: 1, last_page: 1 })

async function fetchCategories() {
 try {
 const { data } = await api.get('/categories')
 categories.value = data.data ?? []
 } catch { categories.value = [] }
}

async function fetchServices(page = 1) {
 loading.value = true
 try {
 const { data } = await api.get('/services', {
 params: { page, q: search.value || undefined, category_id: categoryId.value || undefined },
 })
 services.value = data.data ?? []
 meta.value = data.meta ?? { current_page: 1, last_page: 1 }
 router.replace({ query: { q: search.value || undefined, category: categoryId.value || undefined } })
 window.scrollTo({ top: 0, behavior: 'smooth' })
 } catch {
 services.value = []
 } finally { loading.value = false }
}

function selectCategory(id) {
 categoryId.value = id
 fetchServices(1)
}

onMounted(() => {
 fetchCategories()
 fetchServices(route.query.page ? Number(route.query.page) : 1)
})
</script>

<style scoped> .all-services { width: 100%;
 min-height: 100vh; 
 padding: 108px 20px 60px;
 background: linear-gradient(160deg, #253a70 2%, #576ca1 50%, #364968 100%);
 }
.as-header { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }

/* العنوان الرئيسي تدرج أزرق/أبيض */
.as-header h1 { 
 font-family: var(--font-display); 
 font-size: 26px; 
 font-weight: 800; 
 background: linear-gradient(120deg, #ffffff 40%, #38bdf8 100%) !important;
 -webkit-background-clip: text !important;
 -webkit-text-fill-color: transparent !important;
}

.as-search { display: flex; gap: 8px; max-width: 420px; }

/* حقل البحث الزجاجي */
.as-search input {
 color: rgba(255, 255, 255, 0.8) !important; 
 flex: 1; 
 height: 42px; 
 padding: 0 16px; 
 background: rgba(255, 255, 255, 0.08) !important;
 border: 1px solid rgba(255, 255, 255, 0.2) !important;
 border-radius: 999px !important;
 backdrop-filter: blur(8px);
 outline: none;
 font-size: 17px !important;
}
.as-search input::placeholder { color: rgba(255, 255, 255, 0.6); }

/* زر البحث الزجاجي بيضاوي */
.as-search button { 
 padding: 0 22px; 
 border-radius: 999px !important; 
 border: 1px solid rgba(255, 255, 255, 0.3) !important; 
 background: rgba(255, 255, 255, 0.15) !important; 
 color: #ffffff !important; 
 font-weight: 700; 
 cursor: pointer; 
 backdrop-filter: blur(8px);
 transition: all 0.2s ease;
 font-size: 17px !important;
}
.as-search button:hover {
 background: rgba(255, 255, 255, 0.25) !important;
 border-color: rgba(255, 255, 255, 0.5) !important;
}

/* أزرار الفئات/التصنيفات (الكل، إلخ) */
.as-cats { display: flex; flex-wrap: wrap; gap: 8px; }

.cat-pill { 
 padding: 6px 16px; 
 border-radius: 999px !important; 
 border: 1px solid rgba(255, 255, 255, 0.18) !important; 
 background: rgba(255, 255, 255, 0.06) !important; 
 font-size: 16px; 
 cursor: pointer; 
 color: rgba(255, 255, 255, 0.8) !important; 
 backdrop-filter: blur(6px);
 transition: all 0.2s ease;
}

/* الفئة النشطة (Active) */
.cat-pill.active, .cat-pill:hover { 
 background: rgba(255, 255, 255, 0.22) !important; 
 color: #ffffff !important; 
 border-color: rgba(255, 255, 255, 0.45) !important; 
 font-weight: 600; 
}

.as-state { text-align: center; padding: 60px 20px; color: rgba(255, 255, 255, 0.7); display: flex; flex-direction: column; align-items: center; gap: 10px; }
.as-state span { font-size: 36px; }

.as-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }

/* أزرار التنقل بين الصفحات (Pagination) */
.as-pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 32px; font-size: 13px; color: #ffffff; }
.as-pagination button { 
 padding: 8px 18px; 
 border-radius: 999px !important; 
 border: 1px solid rgba(255, 255, 255, 0.2) !important; 
 background: rgba(255, 255, 255, 0.08) !important; 
 cursor: pointer; 
 color: #ffffff !important; 
 backdrop-filter: blur(8px);
 transition: all 0.2s ease;
}
.as-pagination button:hover:not(:disabled) {
 background: rgba(255, 255, 255, 0.2) !important;
}
.as-pagination button:disabled { opacity: 0.3; cursor: not-allowed; }
</style>
