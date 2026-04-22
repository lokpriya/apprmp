import { ref } from 'vue'
import en from './en.js'
import ne from './ne.js'

const messages = { en, ne }

export const locale = ref(
  (typeof localStorage !== 'undefined' ? localStorage.getItem('apprmp_locale') : null) ?? 'ne'
)

export function useLocale() {
  function t(key) {
    const keys = key.split('.')
    let obj = messages[locale.value]
    for (const k of keys) obj = obj?.[k]
    return obj ?? key
  }

  function setLocale(lang) {
    locale.value = lang
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('apprmp_locale', lang)
    }
  }

  return { locale, t, setLocale }
}
