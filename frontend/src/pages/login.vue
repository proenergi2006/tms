<script setup>
  import { onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute, useRouter } from 'vue-router'
  import { authApi, devAuthApi } from '@/api/auth'
  import tmsLogo from '@/assets/tms-icon.png'
  import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
  import { useAuthStore } from '@/stores/auth'

  // Login kredensial TMS (username+password) — jalur utama, dibutuhkan
  // karena tidak semua pengguna cabang punya akun SYOP untuk SSO
  // (Architecture Document Bagian 3.3 & 7.1). Username, bukan email — lihat
  // catatan di api/auth.js. Pemilih "pengguna contoh" di bawah murni
  // kemudahan pengembangan/demo — hanya muncul bila DevAuthController aktif
  // (local/testing), tidak pernah di production (dan tetap pakai email,
  // bukan username — jalur terpisah dari login kredensial ini).
  const auth = useAuthStore()
  const router = useRouter()
  const route = useRoute()
  const { t } = useI18n()

  const username = ref('')
  const password = ref('')
  const showPassword = ref(false)
  const loading = ref(false)
  // Diprefill dari ?ssoError=... kalau redirect balik dari Sso.vue gagal —
  // token Pinia disimpan di memory jadi tidak ada state lain untuk
  // mengoper pesan error itu selain lewat query string.
  const errorMessage = ref(typeof route.query.ssoError === 'string' ? route.query.ssoError : null)

  const devUsers = ref([])
  const selectedDevEmail = ref(null)
  const devLoading = ref(false)

  onMounted(async () => {
    if (route.query.ssoError) {
      router.replace({ path: '/login' })
    }

    try {
      const { data } = await devAuthApi.listUsers()
      devUsers.value = data.data
    } catch {
      // Diam-diam diabaikan — DevAuthController memang 404 di luar
      // local/testing, bukan kondisi error yang perlu ditampilkan ke
      // pengguna pada login kredensial biasa.
    }
  })

  // Seluruh role (sa, kepala_pool, tim_logistik, fleet_operations,
  // admin_it_ga, admin_sistem, manajemen) mendarat di dashboard admin
  // biasa — aplikasi lapangan sudah dihapus.
  function redirectAfterLogin () {
    router.push('/')
  }

  async function login () {
    if (!username.value || !password.value) return
    loading.value = true
    errorMessage.value = null
    try {
      const { data } = await authApi.login(username.value, password.value)
      auth.setSession(data.data.token, data.data.user)
      redirectAfterLogin()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('login.loginError')
    } finally {
      loading.value = false
    }
  }

  async function devLogin () {
    if (!selectedDevEmail.value) return
    devLoading.value = true
    errorMessage.value = null
    try {
      const { data } = await devAuthApi.login(selectedDevEmail.value)
      auth.setSession(data.data.token, data.data.user)
      redirectAfterLogin()
    } catch {
      errorMessage.value = t('login.loginError')
    } finally {
      devLoading.value = false
    }
  }
</script>

<template>
  <div class="login-page d-flex flex-column">
    <div class="d-flex justify-end pa-4">
      <LanguageSwitcher />
    </div>

    <v-container class="fill-height flex-grow-1" fluid>
      <v-row align="center" class="login-row" justify="center" no-gutters>
        <v-col class="d-flex align-center justify-center login-form-panel" cols="12">
          <v-col
            class="login-form-col"
            cols="12"
            lg="4"
            md="5"
            sm="7"
          >
            <v-card class="login-card" rounded="lg">
              <div class="login-card-accent" />

              <v-card-text class="pa-8">
                <div class="d-flex justify-center mb-6">
                  <img alt="TMS" class="login-logo" :src="tmsLogo">
                </div>

                <h2 class="text-h5 font-weight-bold mb-1">{{ t('login.title') }}</h2>
                <p class="text-body-2 text-medium-emphasis mb-6">{{ t('login.info') }}</p>

                <v-alert v-if="errorMessage" class="mb-4" type="error" variant="tonal">
                  {{ errorMessage }}
                </v-alert>

                <v-text-field
                  v-model="username"
                  autocomplete="username"
                  class="mb-2"
                  :label="t('login.username')"
                  prepend-inner-icon="mdi-account-outline"
                  rounded="lg"
                  variant="outlined"
                  @keyup.enter="login"
                />

                <v-text-field
                  v-model="password"
                  :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                  autocomplete="current-password"
                  :label="t('login.password')"
                  prepend-inner-icon="mdi-lock-outline"
                  rounded="lg"
                  :type="showPassword ? 'text' : 'password'"
                  variant="outlined"
                  @click:append-inner="showPassword = !showPassword"
                  @keyup.enter="login"
                />

                <v-btn
                  block
                  class="mt-2 login-submit-btn"
                  color="primary"
                  :disabled="!username || !password"
                  :loading="loading"
                  rounded="lg"
                  size="large"
                  @click="login"
                >
                  {{ t('login.submit') }}
                  <v-icon end icon="mdi-arrow-right" />
                </v-btn>

                <template v-if="devUsers.length > 0">
                  <v-divider class="my-6">
                    <span class="text-caption text-medium-emphasis px-2">{{ t('common.or') }}</span>
                  </v-divider>

                  <div class="login-dev-panel">
                    <div class="d-flex align-center mb-3">
                      <v-icon class="mr-2" color="primary" icon="mdi-flask-outline" size="18" />
                      <span class="text-caption font-weight-medium">{{ t('login.devLoginHint') }}</span>
                    </div>

                    <v-select
                      v-model="selectedDevEmail"
                      class="mb-3"
                      density="comfortable"
                      hide-details
                      :item-title="item => `${item.name} (${item.role})`"
                      item-value="email"
                      :items="devUsers"
                      :label="t('login.selectUser')"
                      rounded="lg"
                      variant="outlined"
                    />

                    <v-btn
                      block
                      :disabled="!selectedDevEmail"
                      :loading="devLoading"
                      rounded="lg"
                      variant="tonal"
                      @click="devLogin"
                    >
                      {{ t('login.devSubmit') }}
                    </v-btn>
                  </div>
                </template>
              </v-card-text>
            </v-card>
          </v-col>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100%;
  background:
    linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)),
    url('/bg-pro.jpeg') center / cover no-repeat fixed;
}

.login-row {
  min-height: 560px;
}

.login-logo {
  height: 72px;
}

.login-form-panel {
  animation: login-fade-in 0.6s ease-out 0.1s both;
}

.login-form-col {
  max-width: 460px;
}

.login-card {
  width: 100%;
  position: relative;
  overflow: hidden;
  border-radius: 20px !important;
  box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.18) !important;
}

.login-card-accent {
  height: 5px;
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-primary-darken-1)) 100%);
}

.login-submit-btn {
  letter-spacing: 0.02em;
}

.login-dev-panel {
  border: 1px dashed rgba(var(--v-theme-primary), 0.4);
  border-radius: 14px;
  background: rgba(var(--v-theme-primary), 0.04);
  padding: 16px;
}

@keyframes login-fade-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
