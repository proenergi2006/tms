<script setup>
  import { useI18n } from 'vue-i18n'
  import { setLocale } from '@/plugins/i18n'

  const { locale } = useI18n()

  const languages = [
    { value: 'id', title: 'Indonesia', flag: '🇮🇩' },
    { value: 'en', title: 'English', flag: '🇬🇧' },
  ]
</script>

<template>
  <div class="language-switcher">
    <button
      v-for="lang in languages"
      :key="lang.value"
      :aria-label="lang.title"
      :aria-pressed="locale === lang.value"
      class="language-switcher-flag"
      :class="{ 'language-switcher-flag--active': locale === lang.value }"
      :title="lang.title"
      type="button"
      @click="setLocale(lang.value)"
    >
      {{ lang.flag }}
    </button>
  </div>
</template>

<style scoped>
.language-switcher {
  display: flex;
  align-items: center;
  gap: 4px;
}

.language-switcher-flag {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: transparent;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  opacity: 0.45;
  transition: opacity 0.15s ease, box-shadow 0.15s ease;
}

.language-switcher-flag:hover {
  opacity: 0.75;
}

.language-switcher-flag--active {
  opacity: 1;
  box-shadow: 0 0 0 2px rgb(var(--v-theme-primary));
}
</style>
