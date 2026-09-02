<script setup>
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()
  const year = new Date().getFullYear()
</script>

<template>
  <v-footer app class="d-flex align-center justify-center px-4" height="40">
    <span class="text-caption text-medium-emphasis">
      &copy; {{ year }} proenergi.com. {{ t('footer.rights') }}
    </span>
  </v-footer>
</template>
