import api from '@/plugins/axios'

// Login kredensial TMS (username+password) — jalur utama, dibutuhkan karena
// tidak semua pengguna punya akun SYOP untuk SSO. Username, BUKAN email —
// email tetap ada di akun tapi cuma dipakai SSO (lihat ssoApi di bawah),
// supaya orang yang perlu banyak akun (1 orang jadi Kepala Pool di beberapa
// cabang) tidak harus ketik email lengkap tiap akun. Lihat AuthController.
export const authApi = {
  login: (username, password) => api.post('/auth/login', { username, password }),
  logout: () => api.post('/auth/logout'),
}

// Login sementara sebelum SSO tersedia — hanya untuk kebutuhan
// pengembangan/demo (endpoint 404 di luar local/testing), lihat
// DevAuthController (backend) dan PRD Bagian 11.
export const devAuthApi = {
  listUsers: () => api.get('/auth/dev-users'),
  login: email => api.post('/auth/dev-login', { email }),
}

// SSO dari SYOP — token terenkripsi diverifikasi di backend (SsoController),
// lihat src/pages/Sso.vue.
export const ssoApi = {
  login: token => api.post('/auth/sso', { token }),
}
