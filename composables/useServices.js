import { ref, reactive } from 'vue'
import api from './useApi'

/**
 * Composable for fetching and filtering services.
 * Used by both the landing page and the search results page.
 *
 * AI Hook: Replace the stub data in `stubServices` with a real API call,
 * or inject AI-ranked results by passing a `ranked=true` query param
 * once your recommendation model is ready.
 */

/* ── Stub data (replace with real API calls) ───────────────────────────── */
export const stubCategories = [
  { id: 1, name: 'تطوير الويب',     icon: '💻', slug: 'web-dev' },
  { id: 2, name: 'تصميم الجرافيك',  icon: '🎨', slug: 'design' },
  { id: 3, name: 'التسويق الرقمي',  icon: '📣', slug: 'marketing' },
  { id: 4, name: 'كتابة المحتوى',   icon: '✍️',  slug: 'content' },
  { id: 5, name: 'الترجمة',          icon: '🌐', slug: 'translation' },
  { id: 6, name: 'الفيديو والموشن',  icon: '🎬', slug: 'video' },
  { id: 7, name: 'الصوت والتعليق',   icon: '🎙️', slug: 'audio' },
  { id: 8, name: 'البيانات والـ AI', icon: '🤖', slug: 'data-ai' },
]

export const stubServices = [
  {
    id: 1, title: 'تصميم موقع ويب احترافي بـ Laravel + Vue',
    dynamic_price: 500, delivery_days: 7,
    average_rating: 4.9, total_orders: 128,
    thumbnail_url: null,
    category: 'تطوير الويب',
    seller: { id: 1, name: 'أحمد الراشد', is_verified: true, average_rating: 4.9 },
  },
  {
    id: 2, title: 'تصميم هوية بصرية كاملة وشعار احترافي',
    dynamic_price: 250, delivery_days: 5,
    average_rating: 4.8, total_orders: 214,
    thumbnail_url: null,
    category: 'تصميم الجرافيك',
    seller: { id: 2, name: 'سارة الأحمدي', is_verified: true, average_rating: 4.8 },
  },
  {
    id: 3, title: 'إدارة حملات إعلانية على جوجل وميتا',
    dynamic_price: 350, delivery_days: 3,
    average_rating: 4.7, total_orders: 89,
    thumbnail_url: null,
    category: 'التسويق الرقمي',
    seller: { id: 3, name: 'محمد العمري', is_verified: false, average_rating: 4.7 },
  },
  {
    id: 4, title: 'كتابة محتوى SEO لموقعك بالعربية والإنجليزية',
    dynamic_price: 150, delivery_days: 2,
    average_rating: 4.9, total_orders: 302,
    thumbnail_url: null,
    category: 'كتابة المحتوى',
    seller: { id: 4, name: 'نور الهاشمي', is_verified: true, average_rating: 5.0 },
  },
  {
    id: 5, title: 'ترجمة احترافية عربي-إنجليزي مع مراجعة',
    dynamic_price: 80, delivery_days: 1,
    average_rating: 4.8, total_orders: 445,
    thumbnail_url: null,
    category: 'الترجمة',
    seller: { id: 5, name: 'ليلى الزهراني', is_verified: true, average_rating: 4.8 },
  },
  {
    id: 6, title: 'مونتاج فيديو احترافي مع موشن جرافيك',
    dynamic_price: 400, delivery_days: 4,
    average_rating: 4.6, total_orders: 67,
    thumbnail_url: null,
    category: 'الفيديو والموشن',
    seller: { id: 6, name: 'خالد السعيد', is_verified: false, average_rating: 4.6 },
  },
]

/* ── Composable ─────────────────────────────────────────────────────────── */
export function useServices() {
  const services  = ref([])
  const loading   = ref(false)
  const error     = ref(null)
  const meta      = ref({ total: 0, last_page: 1, current_page: 1 })

  const filters = reactive({
    q:           '',
    category_id: null,
    min_price:   null,
    max_price:   null,
    sort:        'newest',
    per_page:    12,
    page:        1,
  })

  async function fetchServices(overrides = {}) {
    loading.value = true
    error.value   = null
    try {
      const params = { ...filters, ...overrides }
      // Remove null/empty params
      Object.keys(params).forEach(k => (params[k] == null || params[k] === '') && delete params[k])
      const { data } = await api.get('/services', { params })
      services.value = data.data
      meta.value     = data.meta
    } catch (e) {
      error.value    = e.response?.data?.message ?? 'حدث خطأ في تحميل الخدمات'
      // Fallback to stub data when API unavailable (development)
      services.value = stubServices
    } finally {
      loading.value = false
    }
  }

  function resetFilters() {
    Object.assign(filters, { q: '', category_id: null, min_price: null, max_price: null, sort: 'newest', page: 1 })
  }

  return { services, loading, error, meta, filters, fetchServices, resetFilters }
}
