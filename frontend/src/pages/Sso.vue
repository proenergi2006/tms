<script setup>
  import { onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute, useRouter } from 'vue-router'
  import { ssoApi } from '@/api/auth'
  import logoProenergi from '@/assets/logo-proenergi.png'
  import { useAuthStore } from '@/stores/auth'

  // Halaman transisi SSO dari SYOP — dituju lewat link berisi ?token=...
  // (token AES-256-GCM terenkripsi, diverifikasi di backend SsoController,
  // bukan di sini). Pola redirect-dengan-query-error dipakai karena token
  // Pinia disimpan di memory (Architecture Document Bagian 3.3), jadi tidak
  // ada state untuk dibaca balik kalau langsung push ke /login begitu saja
  // setelah gagal — pesan error dikirim lewat query agar login.vue bisa
  // menampilkannya.
  const route = useRoute()
  const router = useRouter()
  const auth = useAuthStore()
  const { t } = useI18n()

  const statusText = ref(t('sso.verifying'))
  const hasError = ref(false)

  onMounted(async () => {
    const token = route.query.token

    if (!token) {
      hasError.value = true
      await router.replace({ path: '/login', query: { ssoError: t('sso.missingToken') } })

      return
    }

    try {
      const { data } = await ssoApi.login(token)
      auth.setSession(data.data.token, data.data.user)
      await router.replace('/')
    } catch (error) {
      hasError.value = true
      const message = error.response?.data?.message ?? t('sso.failed')
      await router.replace({ path: '/login', query: { ssoError: message } })
    }
  })
</script>

<template>
  <div class="sso-loading-screen d-flex flex-column align-center justify-center">
    <img alt="Pro Energi" class="sso-logo mb-6" :src="logoProenergi">
    <v-progress-circular v-if="!hasError" color="primary" indeterminate size="40" />
    <p class="text-body-2 text-medium-emphasis mt-4">{{ statusText }}</p>
  </div>
</template>

<style scoped>
.sso-loading-screen {
  min-height: 100vh;
}

.sso-logo {
  height: 48px;
}
</style>
