<template>
 <div class="landing" dir="rtl">
 <AppNavbar />

 <!-- ══════════════════════════════════════════════════════════════
 HERO SECTION
 ═══════════════════════════════════════════════════════════════ -->
 <section class="hero">
 <!-- Background image slider -->
 <div class="hero-bg-slider" aria-hidden="true">
 <div
 v-for="(img, i) in heroImages" :key="i"
 class="hero-bg-slide"
 :class="{ 'hero-bg-slide--active': i === activeImageIndex }"
 :style="{ backgroundImage: `url(${img})` }"
 ></div>
 <div class="hero-bg-gradient"></div>
 </div>

 <!-- Ambient background orbs -->
 <div class="hero-orb hero-orb--1" aria-hidden="true"></div>
 <div class="hero-orb hero-orb--2" aria-hidden="true"></div>
 <div class="hero-orb hero-orb--3" aria-hidden="true"></div>

 <div class="hero__inner">
 <!-- Hero Headline Section (5 lines layout & uniform font size) -->
 <div class="hero-headline-stack" v-motion-slide-bottom>
   <div class="hero-line">
     منصة <span class="brand-highlight">ProLinker</span>
   </div>
   <div class="hero-line">
     تجمع أفضل المستقلين بأصحاب
   </div>
   <div class="hero-line">
     المشاريع
   </div>
   <div class="hero-line">
     في بيئة آمنة مع نظام دفع
   </div>
   <div class="hero-line">
     بالضمان المُحكم.
   </div>
 </div>

 <!-- Search bar -->
 <div class="hero-search" v-motion-slide-bottom>
 <div class="search-input-wrapper">
 <span class="search-icon"></span>
 <input
 v-model="searchQuery"
 type="text"
 placeholder="ابحث عن خدمة... (تصميم، برمجة، تسويق)"
 class="search-input"
 @keyup.enter="handleSearch"
 @input="handleSearchInput"
 aria-label="بحث عن خدمة"
 />
 <Transition name="fade">
 <button v-if="searchQuery" class="search-clear" @click="searchQuery = ''" aria-label="مسح البحث">✕</button>
 </Transition>
 </div>
 <button class="search-btn" @click="handleSearch">ابحث الآن</button>
 </div>

 <!-- Quick tags -->
 <div class="hero-tags" v-motion-fade>
 <span class="tags-label">الأكثر طلباً:</span>
 <button
 v-for="tag in quickTags"
 :key="tag"
 class="quick-tag"
 @click="searchQuery = tag; handleSearch()"
 >{{ tag }}</button>
 </div>

 <!-- Stats strip -->
 <div class="hero-stats" v-motion-slide-bottom>
 <div v-for="stat in stats" :key="stat.label" class="stat-item">
 <span class="stat-num">{{ stat.num }}</span>
 <span class="stat-label">{{ stat.label }}</span>
 </div>
 </div>
 </div>

 <!-- Hero visual: floating cards -->
 <div class="hero-visual" aria-hidden="true">
 <div class="float-card float-card--1">
 <div class="fc-icon"></div>
 <div class="fc-body">
 <div class="fc-label">طلب مكتمل</div>
 <div class="fc-sub">تصميم الهوية البصرية</div>
 </div>
 </div>
 <div class="float-card float-card--2">
 <div class="fc-icon"></div>
 <div class="fc-body">
 <div class="fc-label">أرباح هذا الشهر</div>
 <div class="fc-value grad-text">$2,450</div>
 </div>
 </div>
 <div class="float-card float-card--3">
 <div class="fc-icon"></div>
 <div class="fc-body">
 <div class="fc-label">تقييم جديد</div>
 <div class="fc-stars"></div>
 </div>
 </div>
 </div>
 </section>

 <!-- ══════════════════════════════════════════════════════════════
 CATEGORIES SECTION
 ═══════════════════════════════════════════════════════════════ -->
 <section class="section categories-section">
 <div class="section__inner">
 <div class="section-header">
 <h2 class="section-title">تصفح حسب الفئة</h2>
 <RouterLink to="/services" class="see-all-link">عرض الكل ←</RouterLink>
 </div>

 <div class="categories-grid">
 <button
 v-for="cat in categories"
 :key="cat.id"
 class="cat-card"
 :class="{ active: activeCategory === cat.id }"
 @click="selectCategory(cat)"
 :aria-pressed="activeCategory === cat.id"
 >
 <span class="cat-icon">{{ cat.icon }}</span>
 <span class="cat-name">{{ cat.name }}</span>
 </button>
 </div>
 </div>
 </section>

 <!-- ══════════════════════════════════════════════════════════════
 FEATURED SERVICES
 ═══════════════════════════════════════════════════════════════ -->
 <section class="section suggested-section">
 <div class="section__inner">
 <div class="section-header">
 <div>
 <span class="section-eyebrow gold-text"> مقترح لك</span>
 <h2 class="section-title"> {{ activeCategory ? `خدمات في "${activeCategoryName}"` : 'الخدمات الأعلى تقييماً' }}
 </h2>
 </div>
 <div class="sort-controls">
 <select v-model="sortBy" class="sort-select" @change="applySort">
 <option value="newest">الأحدث</option>
 <option value="rating">الأعلى تقييماً</option>
 <option value="price_asc">السعر: الأقل أولاً</option>
 <option value="price_desc">السعر: الأعلى أولاً</option>
 <option value="popular">الأكثر طلباً</option>
 </select>
 </div>
 </div>

 <!-- Loading skeleton -->
 <div v-if="loading" class="services-grid">
 <div v-for="i in 6" :key="i" class="skeleton-card">
 <div class="skeleton skeleton--thumb"></div>
 <div class="skeleton-body">
 <div class="skeleton skeleton--line" style="width:60%"></div>
 <div class="skeleton skeleton--line"></div>
 <div class="skeleton skeleton--line" style="width:80%"></div>
 </div>
 </div>
 </div>

 <!-- Services grid -->
 <TransitionGroup v-else name="services-grid" tag="div" class="services-grid">
 <ServiceCard
 v-for="(svc, idx) in displayedServices"
 :key="svc.id"
 :service="svc"
 :featured="idx < 2 && !activeCategory"
 />
 </TransitionGroup>

 <!-- Empty state -->
 <div v-if="!loading && displayedServices.length === 0" class="empty-state">
 <span class="empty-icon"></span>
 <h3>لم نجد خدمات مطابقة</h3>
 <p>جرب تغيير الفئة أو البحث بكلمات مختلفة</p>
 <button class="btn-outline" @click="resetAll">عرض جميع الخدمات</button>
 </div>

 <!-- Load more -->
 <div v-if="!loading && displayedServices.length > 0" class="load-more-wrapper">
 <RouterLink to="/services" class="btn-outline-large"> استعرض جميع الخدمات
 <span class="btn-arrow">←</span>
 </RouterLink>
 </div>
 </div>
 </section>

 <!-- ══════════════════════════════════════════════════════════════
 HOW IT WORKS
 ═══════════════════════════════════════════════════════════════ -->
 <section class="section how-section">
 <div class="section__inner">
 <div class="section-header section-header--center">
 <span class="section-eyebrow">كيف يعمل ProLinker؟</span>
 <h2 class="section-title">ثلاث خطوات فقط</h2>
 <p class="section-desc">نظام بسيط وآمن يحمي حقوق الجميع</p>
 </div>

 <div class="steps-grid">
 <div v-for="step in howSteps" :key="step.num" class="step-card">
 <div class="step-num">{{ step.num }}</div>
 <div class="step-icon">{{ step.icon }}</div>
 <h3 class="step-title">{{ step.title }}</h3>
 <p class="step-desc">{{ step.desc }}</p>
 </div>
 </div>

 <!-- Escrow explainer -->
 <div class="escrow-banner">
 <div class="escrow-icon"></div>
 <div class="escrow-body">
 <h3 class="escrow-title">نظام الضمان المُحكم (Escrow)</h3>
 <p class="escrow-desc"> أموالك لا تُحوَّل للمستقل إلا بعد موافقتك على التسليم.
 في حالة النزاع، يتدخل فريق الإدارة لضمان حقوقك.
 </p>
 </div>
 <RouterLink to="/escrow" class="escrow-link">تعرف أكثر</RouterLink>
 </div>
 </div>
 </section>

 <!-- ══════════════════════════════════════════════════════════════
 TRUST STATS
 ═══════════════════════════════════════════════════════════════ -->
 <section class="trust-section">
 <div class="section__inner">
 <div class="trust-grid">
 <div v-for="t in trustStats" :key="t.label" class="trust-item">
 <div class="trust-icon">{{ t.icon }}</div>
 <div class="trust-num">{{ t.num }}</div>
 <div class="trust-label">{{ t.label }}</div>
 </div>
 </div>
 </div>
 </section>

 <!-- ══════════════════════════════════════════════════════════════
 CTA SECTION
 ═══════════════════════════════════════════════════════════════ -->
 <section class="cta-section">
 <div class="cta-orb" aria-hidden="true"></div>
 <div class="section__inner">
 <div class="cta-box">
 <h2 class="cta-title">هل أنت مستعد للبدء؟</h2>
 <p class="cta-desc">انضم مجاناً وابدأ اليوم — سواء كنت تبحث عن عمل أو تحتاج خدمة</p>
 <div class="cta-actions">
 <RouterLink to="/register?role=seller" class="cta-btn cta-btn--primary">
 <span></span> ابدأ كمستقل
 </RouterLink>
 <RouterLink to="/register?role=client" class="cta-btn cta-btn--outline">
 <span></span> استعرض الخدمات
 </RouterLink>
 </div>
 </div>
 </div>
 </section>

 <!-- ══════════════════════════════════════════════════════════════
 FOOTER
 ═══════════════════════════════════════════════════════════════ -->
 <footer class="footer">
 <div class="section__inner">
 <div class="footer-top">
 <div class="footer-brand">
 <RouterLink to="/" class="footer-logo">
 <span class="logo-icon"></span>
 <span class="logo-text grad-text">ProLinker</span>
 </RouterLink>
 <p class="footer-tagline">منصة العمل الحر الأكثر أماناً في المنطقة العربية</p>
 </div>
 <div class="footer-links">
 <div class="footer-col">
 <h4>الخدمات</h4>
 <RouterLink to="/services">استعراض الخدمات</RouterLink>
 <RouterLink to="/how">كيف يعمل؟</RouterLink>
 <RouterLink to="/categories">الفئات</RouterLink>
 </div>
 <div class="footer-col">
 <h4>الحساب</h4>
 <template v-if="!authStore.isLoggedIn">
 <RouterLink to="/register">إنشاء حساب</RouterLink>
 <RouterLink to="/login">تسجيل الدخول</RouterLink>
 </template>
 </div>
 <div class="footer-col">
 <h4>الدعم</h4>
 <RouterLink to="/help">مركز المساعدة</RouterLink>
 <RouterLink to="/terms">الشروط والأحكام</RouterLink>
 <RouterLink to="/privacy">سياسة الخصوصية</RouterLink>
 </div>
 </div>
 </div>
 <div class="footer-bottom">
 <p>© {{ new Date().getFullYear() }} ProLinker. جميع الحقوق محفوظة.</p>
 <p class="footer-made">صُنع بـ للمنطقة العربية</p>
 </div>
 </div>
 </footer>

 </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ServiceCard from '@/components/shared/ServiceCard.vue'
import AppNavbar from '@/components/shared/AppNavbar.vue'
import { useServices } from '@/composables/useServices'
import api from '@/composables/useApi'

const router = useRouter()
const authStore = useAuthStore()
const { services, loading, filters, fetchServices } = useServices()

// ── State ──────────────────────────────────────────────────────────────────
const searchQuery = ref('')
const activeCategory = ref(null)
const sortBy = ref('newest')
const categories = ref([])

async function fetchCategories() {
 try {
 const { data } = await api.get('/categories')
 categories.value = data.data ?? []
 } catch {
 categories.value = []
 }
}

// ── Computed ───────────────────────────────────────────────────────────────
const activeCategoryName = computed(() => categories.value.find(c => c.id === activeCategory.value)?.name ?? ''
)

const displayedServices = computed(() => {
 let list = services.value

 if (activeCategory.value) {
 list = list.filter(s => s.category_id === activeCategory.value)
 }

 if (searchQuery.value.trim()) {
 const q = searchQuery.value.trim().toLowerCase()
 list = list.filter(s => s.title.toLowerCase().includes(q) ||
 s.category?.toLowerCase().includes(q)
 )
 }

 return [...list].sort((a, b) => {
 switch (sortBy.value) {
 case 'rating': return b.average_rating - a.average_rating
 case 'price_asc': return a.dynamic_price - b.dynamic_price
 case 'price_desc': return b.dynamic_price - a.dynamic_price
 case 'popular': return b.total_orders - a.total_orders
 default: return b.id - a.id
 }
 })
})

// ── Methods ────────────────────────────────────────────────────────────────
function handleSearch() {
 if (searchQuery.value.trim()) {
 router.push({ path: '/services', query: { q: searchQuery.value } })
 }
}

function handleSearchInput() {
 // Debounced local filter
}

function selectCategory(cat) {
 activeCategory.value = activeCategory.value === cat.id ? null : cat.id
 filters.category_id = activeCategory.value
 fetchServices()
}

function applySort() {
 filters.sort = sortBy.value
}

function resetAll() {
 activeCategory.value = null
 searchQuery.value = ''
 sortBy.value = 'newest'
 fetchServices()
}

// ── Static Data ────────────────────────────────────────────────────────────
const fallbackTags = ['تصميم شعار', 'موقع ويب', 'فيديو اعلاني', 'ترجمة', 'SEO']
const quickTags = ref([...fallbackTags])

const stats = ref([
 { num: '—', label: 'مستقل نشط' },
 { num: '—', label: 'خدمة مكتملة' },
 { num: '—', label: 'متوسط التقييم' },
 { num: '100%', label: 'أمان بالضمان' },
])

const trustStats = ref([
 { icon: '', num: '100%', label: 'حماية ضد الاحتيال' },
 { icon: '', num: '24/7', label: 'دعم متواصل' },
 { icon: '', num: '—', label: 'مستقل موثق' },
 { icon: '', num: 'آمن', label: 'نظام دفع معتمد' },
])

async function fetchPlatformStats() {
 try {
 const { data } = await api.get('/platform-stats')
 const s = data.data
 stats.value = [
 { num: `+${s.active_sellers_count}`, label: 'مستقل نشط' },
 { num: `+${s.completed_services_count}`, label: 'خدمة مكتملة' },
 { num: s.average_rating ? `${s.average_rating}` : '—', label: 'متوسط التقييم' },
 { num: '100%', label: 'أمان بالضمان' },
 ]
 trustStats.value[2] = { icon: '', num: `+${s.verified_sellers_count}`, label: 'مستقل موثق' }
 if (s.top_categories?.length) quickTags.value = s.top_categories
 } catch {
 // Keep the neutral placeholders
 }
}
fetchPlatformStats()

const howSteps = [
 { num: '01', icon: '', title: 'ابحث واختر',
 desc: 'استعرض مئات الخدمات، قارن الأسعار والتقييمات واختر المناسب لك.' },
 { num: '02', icon: '', title: 'ادفع بأمان',
 desc: 'أموالك تُحتجز في نظام الضمان ولا تُحوَّل للمستقل إلا بعد موافقتك.' },
 { num: '03', icon: '', title: 'استلم وقيّم',
 desc: 'راجع العمل، وبعد موافقتك تُحوَّل الأموال فوراً للمستقل.' },
]

// ── Hero background slider ───────────────────────────────────────────────
const heroImages = [
 '/images/hero/1.jpg',
 '/images/hero/2.jpg',
 '/images/hero/3.jpg',
 '/images/hero/4.jpg',
 '/images/hero/5.jpg',
]
const activeImageIndex = ref(0)
let heroSliderTimer = null

// ── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(() => {
 fetchServices()
 fetchCategories()
 heroSliderTimer = setInterval(() => {
 activeImageIndex.value = (activeImageIndex.value + 1) % heroImages.length
 }, 3000)
})
onUnmounted(() => {
 clearInterval(heroSliderTimer)
})
</script>

<style scoped>
.landing { overflow-x: hidden; }

/* ── HERO HEADLINE STACK ─────────────────────────────────────────────── */
.hero-headline-stack {
 display: flex;
 flex-direction: column;
 gap: 6px;
 text-align: right;
 max-width: 800px;
 margin-bottom: 32px;
}

.hero-line {
 font-family: var(--font-display);
 font-size: clamp(22px, 3.2vw, 36px);
 font-weight: 800;
 line-height: 1.3;
 color: var(--color-text, #ffffff);
 margin: 0;
}

/* التدرج اللوني المطابق للوغو ProLinker */
.brand-highlight {
 background: linear-gradient(120deg, #0593ec 30%, #94cbe2 100%);
 -webkit-background-clip: text;
 -webkit-text-fill-color: transparent;
 font-weight: 950; 
 font-size: xx-large;
}

/* ── HERO ──────────────────────────────────────────────────────────────── */
.hero {
 min-height: 100vh;
 display: flex;
 align-items: center;
 position: relative;
 overflow: hidden;
 padding: 120px 24px 80px;
 background: var(--grad-hero), var(--color-bg);
}
.hero-bg-slider { position: absolute; inset: 0; z-index: 0; overflow: hidden; }
.hero-bg-slide {
 position: absolute; inset: 0;
 background-size: cover; background-position: center;
 opacity: 0; transition: opacity 1.4s ease-in-out;
}
.hero-bg-slide--active { opacity: 0.55; }
.hero-bg-gradient {
 position: absolute; inset: 0;
 background:
 linear-gradient(to bottom, rgba(8,10,20,0.55) 0%, rgba(8,10,20,0.75) 55%, var(--color-bg) 100%),
 linear-gradient(to right, transparent 0%, rgba(8,10,20,0.35) 45%, var(--color-bg) 88%),
 var(--grad-hero);
}
.hero-orb {
 position: absolute;
 border-radius: var(--radius-full);
 pointer-events: none;
 filter: blur(80px);
}
.hero-orb--1 {
 width: 500px; height: 500px;
 background: rgba(99,102,241,0.15);
 top: -100px; right: -100px;
 animation: float1 8s ease-in-out infinite;
}
.hero-orb--2 {
 width: 300px; height: 300px;
 background: rgba(139,92,246,0.1);
 bottom: 0; left: 10%;
 animation: float2 10s ease-in-out infinite;
}
.hero-orb--3 {
 width: 200px; height: 200px;
 background: rgba(245,158,11,0.08);
 top: 30%; left: 40%;
 animation: float1 12s ease-in-out infinite reverse;
}
@keyframes float1 {
 0%,100% { transform: translate(0,0) scale(1); }
 50% { transform: translate(20px, -30px) scale(1.05); }
}
@keyframes float2 {
 0%,100% { transform: translate(0,0); }
 50% { transform: translate(-20px, 20px); }
}
.hero__inner {
 max-width: 1280px; margin: 0 auto;
 display: grid;
 grid-template-columns: 1fr 380px;
 gap: 60px; align-items: center;
 width: 100%; position: relative; z-index: 1;
}

/* Search */
.hero-search {
 display: flex; gap: 10px; align-items: stretch;
 max-width: 560px;
 margin-bottom: 20px;
}
.search-input-wrapper {
 flex: 1; position: relative;
 display: flex; align-items: center;
}
.search-icon {
 position: absolute; right: 16px;
 font-size: 18px; pointer-events: none;
 z-index: 1;
}
.search-input {
 width: 100%; height: 52px;
 padding: 0 48px 0 44px;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-lg);
 color: var(--color-text);
 font-family: var(--font-body);
 font-size: 15px;
 outline: none;
 transition: border-color 0.2s, box-shadow 0.2s;
}
.search-input::placeholder { color: var(--color-text-3); }
.search-input:focus {
 border-color: var(--color-primary);
 box-shadow: 0 0 0 3px var(--color-primary-glow);
}
.search-clear {
 position: absolute; left: 12px;
 background: none; border: none;
 color: var(--color-text-3); cursor: pointer;
 font-size: 14px; padding: 4px;
 transition: color 0.15s;
}
.search-clear:hover { color: var(--color-text); }
.search-btn {
 height: 52px; padding: 0 28px;
 background: rgb(80, 109, 190);
 border: none; border-radius: var(--radius-lg);
 color: white; font-family: var(--font-display);
 font-size: 15px; font-weight: 700;
 cursor: pointer; white-space: nowrap;
 transition: opacity 0.2s, box-shadow 0.2s;
 box-shadow: 0 0 24px rgba(99,102,241,0.35);
}
.search-btn:hover { opacity: 0.9; box-shadow: 0 0 36px rgba(99,102,241,0.55); }

/* Quick tags */
.hero-tags {
 display: flex; flex-wrap: wrap; align-items: center;
 gap: 8px; 
 margin-top: 50px;
 margin-bottom: 40px;
}
.tags-label { font-size: 15px; color: var(--color-text-2); font-weight: 700; }
.quick-tag {
 padding: 6px 16px;
 border-radius: var(--radius-full);
 border: 1px solid var(--color-border);
 background: rgba(255, 255, 255, 0.08);
 color: var(--color-text);
 font-size: 14px; cursor: pointer;
 font-family: var(--font-body);
 font-weight: 600;
 backdrop-filter: blur(4px);
 transition: border-color 0.2s, color 0.2s, background 0.2s;
}
.quick-tag:hover { border-color: var(--color-primary); color: var(--color-primary); background: var(--color-primary-glow); }

/* Stats */
.hero-stats {
 display: flex; gap: 32px;
 padding-top: 32px;
 border-top: 1px solid var(--color-border);
}
.stat-item { display: flex; flex-direction: column; }
.stat-num { font-family: var(--font-display); font-size: 24px; font-weight: 800; color: var(--color-text); }
.stat-label { font-size: 12px; color: var(--color-text-3); }

/* Hero visual: floating cards */
.hero-visual {
 position: relative; height: 400px;
 display: flex; align-items: center; justify-content: center;
}
.float-card {
 position: absolute;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-lg);
 padding: 16px 20px;
 display: flex; align-items: center; gap: 14px;
 box-shadow: var(--shadow-card);
 min-width: 200px;
}
.float-card--1 { top: 40px; right: 0; animation: floatCard1 6s ease-in-out infinite; }
.float-card--2 { top: 50%; right: 30px; transform: translateY(-50%); animation: floatCard2 7s ease-in-out infinite; }
.float-card--3 { bottom: 40px; right: 20px; animation: floatCard3 8s ease-in-out infinite; }
@keyframes floatCard1 { 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-12px); } }
@keyframes floatCard2 { 0%,100%{ transform:translateY(-50%); } 50%{ transform:translateY(calc(-50% + 10px)); } }
@keyframes floatCard3 { 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-8px); } }
.fc-icon { font-size: 28px; flex-shrink: 0; }
.fc-label { font-size: 12px; color: var(--color-text-3); }
.fc-sub { font-size: 13px; color: var(--color-text); font-weight: 600; margin-top: 2px; }
.fc-value { font-family: var(--font-display); font-size: 20px; font-weight: 800; margin-top: 2px; }
.fc-stars { color: var(--color-gold); font-size: 16px; letter-spacing: 2px; margin-top: 2px; }

/* ── COMMON SECTION ───────────────────────────────────────────────────── */
.section { padding: 80px 24px; }
.section__inner { max-width: 1280px; margin: 0 auto; }
.section-header {
 display: flex; align-items: flex-end; justify-content: space-between;
 margin-bottom: 40px; gap: 16px;
}
.section-header--center { flex-direction: column; align-items: center; text-align: center; }
.section-eyebrow { font-size: 13px; font-weight: 700; letter-spacing: 1px; display: block; margin-bottom: 8px; }
.section-title {
 font-family: var(--font-display);
 font-size: clamp(24px, 3vw, 36px);
 font-weight: 800;
 color: var(--color-text);
 line-height: 1.3;
}
.section-desc { font-size: 16px; color: var(--color-text-2); margin-top: 12px; max-width: 500px; }
.see-all-link { color: var(--color-primary); text-decoration: none; font-size: 14px; font-weight: 600; white-space: nowrap; }
.see-all-link:hover { text-decoration: underline; }

/* ── CATEGORIES ──────────────────────────────────────────────────────── */
.categories-section { background: var(--color-bg-2); }
.categories-grid {
 display: grid;
 grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
 gap: 12px;
}
.cat-card {
 display: flex; flex-direction: column; align-items: center; gap: 10px;
 padding: 20px 12px;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-lg);
 cursor: pointer; font-family: var(--font-body);
 transition: border-color 0.2s, background 0.2s, transform 0.2s var(--ease-spring);
}
.cat-card:hover { border-color: var(--color-border-hover); transform: translateY(-3px); }
.cat-card.active {
 border-color: var(--color-primary);
 background: rgba(99,102,241,0.1);
 box-shadow: 0 0 20px var(--color-primary-glow);
}
.cat-icon { font-size: 28px; line-height: 1; }
.cat-name { font-size: 13px; color: var(--color-text-2); font-weight: 500; text-align: center; }
.cat-card.active .cat-name { color: var(--color-primary); }

/* ── SERVICES GRID ───────────────────────────────────────────────────── */
.sort-select {
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-md);
 color: var(--color-text-2);
 font-family: var(--font-body);
 font-size: 14px; padding: 8px 14px;
 cursor: pointer; outline: none;
}
.sort-select:focus { border-color: var(--color-primary); }
.services-grid {
 display: grid;
 grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
 gap: 20px;
}

/* Skeleton */
.skeleton-card {
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-xl);
 overflow: hidden;
}
.skeleton { background: linear-gradient(90deg, var(--color-bg-card) 25%, var(--color-bg-card-hover) 50%, var(--color-bg-card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: var(--radius-sm); }
@keyframes shimmer { to { background-position: -200% 0; } }
.skeleton--thumb { height: 160px; border-radius: 0; }
.skeleton-body { padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.skeleton--line { height: 12px; }

/* Empty */
.empty-state {
 display: flex; flex-direction: column; align-items: center;
 gap: 12px; padding: 60px 24px; text-align: center;
}
.empty-icon { font-size: 56px; }
.empty-state h3 { font-family: var(--font-display); font-size: 20px; font-weight: 700; }
.empty-state p { color: var(--color-text-2); font-size: 15px; }

/* Buttons */
.btn-outline {
 padding: 10px 24px;
 border-radius: var(--radius-full);
 border: 1px solid var(--color-border);
 background: none;
 color: var(--color-text-2);
 font-family: var(--font-body); font-size: 14px;
 cursor: pointer; transition: border-color 0.2s, color 0.2s;
}
.btn-outline:hover { border-color: var(--color-primary); color: var(--color-primary); }
.load-more-wrapper { display: flex; justify-content: center; margin-top: 48px; }
.btn-outline-large {
 display: inline-flex; align-items: center; gap: 8px;
 padding: 14px 36px;
 border-radius: var(--radius-full);
 border: 1px solid var(--color-border);
 background: none;
 color: var(--color-text-2);
 text-decoration: none; font-size: 15px; font-weight: 600;
 transition: border-color 0.2s, color 0.2s, box-shadow 0.2s;
}
.btn-outline-large:hover { border-color: var(--color-primary); color: var(--color-primary); box-shadow: 0 0 20px var(--color-primary-glow); }
.btn-arrow { transition: transform 0.2s; }
.btn-outline-large:hover .btn-arrow { transform: translateX(-4px); }

/* ── HOW IT WORKS ────────────────────────────────────────────────────── */
.how-section { background: var(--color-bg-2); }
.steps-grid {
 display: grid; grid-template-columns: repeat(3, 1fr);
 gap: 24px; margin-bottom: 40px;
}
.step-card {
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-xl);
 padding: 32px 24px;
 position: relative;
 transition: border-color 0.2s, box-shadow 0.2s;
}
.step-card:hover { border-color: var(--color-border-hover); box-shadow: var(--shadow-glow); }
.step-num {
 font-family: var(--font-display);
 font-size: 42px; font-weight: 900;
 background: var(--grad-primary);
 -webkit-background-clip: text;
 -webkit-text-fill-color: transparent;
 background-clip: text;
 line-height: 1; margin-bottom: 12px; opacity: 0.6;
}
.step-icon { font-size: 36px; margin-bottom: 16px; }
.step-title { font-family: var(--font-display); font-size: 18px; font-weight: 700; margin-bottom: 10px; }
.step-desc { font-size: 14px; color: var(--color-text-2); line-height: 1.7; }

/* Escrow banner */
.escrow-banner {
 display: flex; align-items: center; gap: 20px;
 padding: 24px 28px;
 background: rgba(99,102,241,0.06);
 border: 1px solid rgba(99,102,241,0.2);
 border-radius: var(--radius-xl);
}
.escrow-icon { font-size: 36px; flex-shrink: 0; }
.escrow-body { flex: 1; }
.escrow-title { font-family: var(--font-display); font-size: 17px; font-weight: 700; margin-bottom: 6px; }
.escrow-desc { font-size: 14px; color: var(--color-text-2); line-height: 1.6; }
.escrow-link {
 padding: 10px 22px;
 border-radius: var(--radius-full);
 background: var(--grad-primary);
 color: white; text-decoration: none;
 font-size: 14px; font-weight: 600;
 white-space: nowrap;
 transition: opacity 0.2s;
}
.escrow-link:hover { opacity: 0.85; }

/* ── TRUST STATS ─────────────────────────────────────────────────────── */
.trust-section {
 padding: 60px 24px;
 background: linear-gradient(180deg, var(--color-bg) 0%, var(--color-bg-2) 100%);
 border-top: 1px solid var(--color-border);
 border-bottom: 1px solid var(--color-border);
}
.trust-grid {
 display: grid; grid-template-columns: repeat(4, 1fr);
 gap: 24px;
}
.trust-item {
 display: flex; flex-direction: column; align-items: center;
 gap: 10px; text-align: center; padding: 24px 16px;
}
.trust-icon { font-size: 36px; }
.trust-num { font-family: var(--font-display); font-size: 32px; font-weight: 900; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.trust-label { font-size: 14px; color: var(--color-text-2); }

/* ── CTA ─────────────────────────────────────────────────────────────── */
.cta-section {
 padding: 100px 24px;
 position: relative; overflow: hidden;
 text-align: center;
}
.cta-orb {
 position: absolute; inset: -50%;
 background: radial-gradient(ellipse 60% 50% at 50% 50%, rgba(99,102,241,0.15), transparent);
 pointer-events: none;
}
.cta-box { position: relative; z-index: 1; max-width: 640px; margin: 0 auto; }
.cta-title { font-family: var(--font-display); font-size: clamp(28px, 4vw, 48px); font-weight: 900; margin-bottom: 16px; }
.cta-desc { font-size: 17px; color: var(--color-text-2); margin-bottom: 40px; line-height: 1.7; }
.cta-actions { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
.cta-btn {
 display: inline-flex; align-items: center; gap: 10px;
 padding: 14px 32px;
 border-radius: var(--radius-full);
 text-decoration: none;
 font-size: 16px; font-weight: 700;
 transition: opacity 0.2s, transform 0.2s var(--ease-spring), box-shadow 0.2s;
}
.cta-btn:hover { transform: translateY(-2px); }
.cta-btn--primary { background: var(--grad-primary); color: white; box-shadow: 0 0 30px rgba(99,102,241,0.4); }
.cta-btn--primary:hover { box-shadow: 0 0 44px rgba(99,102,241,0.6); }
.cta-btn--outline { border: 1px solid var(--color-border); color: var(--color-text-2); background: var(--color-bg-card); }
.cta-btn--outline:hover { border-color: var(--color-primary); color: var(--color-primary); }

/* ── FOOTER ──────────────────────────────────────────────────────────── */
.footer { padding: 60px 24px 32px; background: var(--color-bg-2); border-top: 1px solid var(--color-border); }
.footer-top { display: flex; gap: 60px; margin-bottom: 48px; }
.footer-brand { flex: 1; }
.footer-logo { display: flex; align-items: center; gap: 8px; text-decoration: none; margin-bottom: 12px; }
.footer-logo .logo-icon { font-size: 22px; background: var(--grad-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.footer-logo .logo-text { font-family: var(--font-display); font-size: 18px; font-weight: 800; }
.footer-tagline { font-size: 14px; color: var(--color-text-3); line-height: 1.6; max-width: 240px; }
.footer-links { display: flex; gap: 48px; }
.footer-col { display: flex; flex-direction: column; gap: 12px; min-width: 120px; }
.footer-col h4 { font-family: var(--font-display); font-size: 14px; font-weight: 700; color: var(--color-text); margin-bottom: 4px; }
.footer-col a { color: var(--color-text-3); text-decoration: none; font-size: 14px; transition: color 0.15s; }
.footer-col a:hover { color: var(--color-primary); }
.footer-bottom { display: flex; justify-content: space-between; padding-top: 24px; border-top: 1px solid var(--color-border); font-size: 13px; color: var(--color-text-3); }

/* Transition utilities */
.fade-enter-active,.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,.fade-leave-to { opacity:0; }

/* ── RESPONSIVE ──────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
 .hero__inner { grid-template-columns: 1fr; }
 .hero-visual { display: none; }
 .steps-grid { grid-template-columns: 1fr; }
 .trust-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 640px) {
 .hero { padding: 100px 16px 60px; }
 .hero-stats { gap: 20px; flex-wrap: wrap; }
 .hero-search { flex-direction: column; }
 .search-btn { height: 48px; }
 .hero-tags { margin-top: 30px; }
 .section { padding: 56px 16px; }
 .categories-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
 .trust-grid { grid-template-columns: repeat(2,1fr); }
 .escrow-banner { flex-direction: column; text-align: center; }
 .cta-actions { flex-direction: column; align-items: center; }
 .footer-top { flex-direction: column; gap: 32px; }
 .footer-links { flex-direction: column; gap: 24px; }
 .footer-bottom { flex-direction: column; gap: 8px; text-align: center; }
}
</style>