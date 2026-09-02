import { createI18n } from 'vue-i18n'
import en from '@/locales/en.js'
import id from '@/locales/id.js'

const STORAGE_KEY = 'tms-locale'

function detectLocale () {
  const saved = localStorage.getItem(STORAGE_KEY)
  if (saved === 'id' || saved === 'en') {
    return saved
  }

  return 'id'
}

const i18n = createI18n({
  legacy: false,
  locale: detectLocale(),
  fallbackLocale: 'id',
  messages: { id, en },
})

export function setLocale (locale) {
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
}

export default i18n
