<template>
 <div class="not-found" dir="rtl">
 <div class="nf-orb nf-orb--1" aria-hidden="true"></div>
 <div class="nf-orb nf-orb--2" aria-hidden="true"></div>
 <div class="nf-content">
 <div class="nf-code grad-text">404</div>
 <h1 class="nf-title">عذراً، الصفحة غير موجودة</h1>
 <p class="nf-desc">الرابط الذي تبحث عنه غير موجود أو تم نقله.</p>
 <RouterLink to="/" class="nf-btn">← العودة إلى الرئيسية</RouterLink>
 </div>
 </div>
</template>
<style scoped> .not-found { min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 24px; position: relative; overflow: hidden; background: var(--color-bg); }
.nf-orb { position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none; }
.nf-orb--1 { width: 400px; height: 400px; background: rgba(99,102,241,0.1); top: -100px; right: -100px; }
.nf-orb--2 { width: 300px; height: 300px; background: rgba(139,92,246,0.08); bottom: -80px; left: -80px; }
.nf-content { position: relative; z-index: 1; }
.nf-code { font-family: var(--font-display); font-size: 120px; font-weight: 900; line-height: 1; margin-bottom: 16px; }
.nf-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; margin-bottom: 12px; }
.nf-desc { font-size: 16px; color: var(--color-text-3); margin-bottom: 32px; }
.nf-btn { display: inline-flex; align-items: center; gap: 8px; padding: 13px 30px; border-radius: 9999px; background: var(--grad-primary); color: white; text-decoration: none; font-size: 15px; font-weight: 700; box-shadow: 0 0 24px rgba(99,102,241,0.4); transition: opacity 0.2s, transform 0.2s; }
.nf-btn:hover { opacity: 0.9; transform: translateY(-2px); }
</style>
