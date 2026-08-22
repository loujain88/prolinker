import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // ☀️ النهاري هو الوضع الافتراضي عند فتح الموقع لأول مرة
  const theme = ref(localStorage.getItem('pl_theme') ?? 'light')

  function apply() {
    document.documentElement.setAttribute('data-theme', theme.value)
  }

  function toggle() {
    // التبديل بين النهاري (light) والداكن (dark)
    theme.value = theme.value === 'light' ? 'dark' : 'light'
    localStorage.setItem('pl_theme', theme.value)
    apply()
  }

  function init() {
    apply()
  }

  return { theme, toggle, init }
})