<template>
 <RouterLink :to="`/services/${service.id}`" class="service-card" :class="{ featured }">
 <!-- Thumbnail -->
 <div class="card-thumb">
 <div v-if="!service.thumbnail_url" class="card-thumb__placeholder">
 <span>{{ categoryIcon }}</span>
 </div>
 <img v-else :src="service.thumbnail_url" :alt="service.title" loading="lazy" />
 <span v-if="featured" class="card-badge card-badge--featured"> مميز</span>
 <span v-if="service.seller?.is_verified" class="card-badge card-badge--verified">✓ موثق</span>
 </div>

 <!-- Seller -->
 <div class="card-seller">
 <div class="seller-avatar">{{ sellerInitials }}</div>
 <span class="seller-name">{{ service.seller?.name }}</span>
 </div>

 <!-- Content -->
 <h3 class="card-title">{{ service.title }}</h3>

 <!-- Rating -->
 <div class="card-rating" v-if="service.average_rating">
 <span class="stars">{{ starsDisplay }}</span>
 <span class="rating-num">{{ service.average_rating }}</span>
 <span class="rating-count">({{ service.total_orders }})</span>
 </div>

 <!-- Footer -->
 <div class="card-footer">
 <div class="card-meta">
 <span class="meta-item"> {{ service.delivery_days }} يوم</span>
 <span class="meta-item">{{ service.category }}</span>
 </div>
 <div class="card-price">
 <span class="price-label">يبدأ من</span>
 <span class="price-amount">${{ service.dynamic_price }}</span>
 </div>
 </div>
 </RouterLink>
</template>

<script setup> import { computed } from 'vue'

const props = defineProps({
 service: { type: Object, required: true },
 featured: { type: Boolean, default: false },
})

const categoryIcons = {
 'تطوير الويب': '', 'تصميم الجرافيك': '', 'التسويق الرقمي': '',
 'كتابة المحتوى': '', 'الترجمة': '', 'الفيديو والموشن': '',
 'الصوت والتعليق': '', 'البيانات والـ AI': '',
}

const categoryIcon = computed(() => categoryIcons[props.service.category] ?? '')
const sellerInitials = computed(() => {
 const name = props.service.seller?.name ?? ''
 return name.split(' ').map(w => w[0]).slice(0,2).join('')
})
const starsDisplay = computed(() => {
 const r = Math.round(props.service.average_rating)
 return ''.repeat(r) + ''.repeat(5 - r)
})
</script>

<style scoped> .service-card {
 display: flex; flex-direction: column; gap: 10px;
 background: var(--color-bg-card);
 border: 1px solid var(--color-border);
 border-radius: var(--radius-xl);
 overflow: hidden;
 text-decoration: none;
 color: inherit;
 cursor: pointer;
 position: relative;
 transition:
 transform 0.25s var(--ease-spring),
 border-color 0.25s var(--ease-smooth),
 box-shadow 0.25s var(--ease-smooth);
}
.service-card:hover {
 transform: translateY(-6px);
 border-color: var(--color-border-hover);
 box-shadow: var(--shadow-glow-hover);
}
.service-card.featured {
 border-color: rgba(255, 253, 251, 0.3);
}
.service-card.featured:hover {
 box-shadow: var(--shadow-gold);
 border-color: rgba(11, 160, 229, 0.6);
}
/* Thumb */
.card-thumb {
 width: 100%; height: 160px; overflow: hidden;
 background: var(--color-bg-2);
 position: relative; flex-shrink: 0;
}
.card-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.service-card:hover .card-thumb img { transform: scale(1.04); }
.card-thumb__placeholder {
 width: 100%; height: 100%;
 display: flex; align-items: center; justify-content: center;
 font-size: 48px;
 background: linear-gradient(135deg, var(--color-bg-2), var(--color-bg-card-hover));
}
/* Badges */
.card-badge {
 position: absolute; bottom: 8px; right: 8px;
 padding: 3px 10px;
 border-radius: var(--radius-full);
 font-size: 11px; font-weight: 700;
}
.card-badge--featured { background: rgba(245,158,11,0.2); color: var(--color-gold); }
.card-badge--verified { background: rgba(16,185,129,0.15); color: #10B981; right: auto; left: 8px; }
/* Content padding */
.card-seller, .card-title, .card-rating, .card-footer { padding: 0 14px; }
.card-footer { padding-bottom: 14px; }
.card-seller { display: flex; align-items: center; gap: 8px; }
.seller-avatar {
 width: 26px; height: 26px;
 border-radius: var(--radius-full);
 background: var(--grad-primary);
 display: flex; align-items: center; justify-content: center;
 font-size: 10px; font-weight: 700; color: white; flex-shrink: 0;
}
.seller-name { font-size: 12px; color: var(--color-text-3); font-weight: 500; }
/* Title */
.card-title {
 font-family: var(--font-display);
 font-size: 15px; font-weight: 700;
 color: var(--color-text);
 line-height: 1.5;
 display: -webkit-box;
 -webkit-line-clamp: 2;
 -webkit-box-orient: vertical;
 overflow: hidden;
}
/* Rating */
.card-rating { display: flex; align-items: center; gap: 5px; }
.stars { color: var(--color-gold); font-size: 12px; letter-spacing: 1px; }
.rating-num { font-size: 13px; font-weight: 700; color: var(--color-text); }
.rating-count { font-size: 11px; color: var(--color-text-3); }
/* Footer */
.card-footer { display: flex; align-items: flex-end; justify-content: space-between; margin-top: auto; }
.card-meta { display: flex; flex-direction: column; gap: 2px; }
.meta-item { font-size: 11px; color: var(--color-text-3); }
.card-price { text-align: left; }
.price-label { display: block; font-size: 10px; color: var(--color-text-3); }
.price-amount { font-family: var(--font-display); font-size: 18px; font-weight: 800; color:var(--color-text) ; }
</style>
