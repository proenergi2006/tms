import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/auth'

// Token disimpan di sessionStorage (bukan localStorage) — bertahan lewat
// refresh halaman selama tab masih terbuka, otomatis hilang begitu tab/
// browser ditutup. Ini keputusan sadar mengubah dari in-memory murni
// (Architecture Document 3.3 awalnya) karena refresh=logout terlalu
// mengganggu di pemakaian nyata — tetap ada risiko XSS (skrip jahat bisa
// baca sessionStorage), tapi lebih kecil paparannya dibanding localStorage
// yang bertahan tanpa batas waktu lintas sesi browser.
const STORAGE_TOKEN_KEY = 'tms-auth-token'
const STORAGE_USER_KEY = 'tms-auth-user'

function readStoredSession () {
  try {
    const token = sessionStorage.getItem(STORAGE_TOKEN_KEY)
    const userJson = sessionStorage.getItem(STORAGE_USER_KEY)

    return {
      token: token || null,
      user: userJson ? JSON.parse(userJson) : null,
    }
  } catch {
    // Private browsing / storage dinonaktifkan — sesi tetap jalan di
    // memory untuk request saat ini, cuma tidak bertahan lewat refresh.
    return { token: null, user: null }
  }
}

function persistSession (token, user) {
  try {
    if (token) {
      sessionStorage.setItem(STORAGE_TOKEN_KEY, token)
      sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(user))
    } else {
      sessionStorage.removeItem(STORAGE_TOKEN_KEY)
      sessionStorage.removeItem(STORAGE_USER_KEY)
    }
  } catch {
    // diam-diam diabaikan, lihat catatan readStoredSession()
  }
}

export const useAuthStore = defineStore('auth', () => {
  const stored = readStoredSession()
  const token = ref(stored.token)
  const user = ref(stored.user)

  const isAuthenticated = computed(() => !!token.value)
  const role = computed(() => user.value?.role?.name ?? null)
  const permissions = computed(() => user.value?.permissions ?? [])
  const branchId = computed(() => user.value?.branch?.id ?? null)

  // Role Head Office (lintas-cabang) — cermin dari User::GLOBAL_ROLES di
  // backend. Hanya dipakai untuk default/kunci field cabang pada form;
  // backend tetap satu-satunya sumber otorisasi sesungguhnya. Daftar ini
  // sempat basi (masih ada 'finance' yang sudah dihapus, belum ada
  // 'logistik_ho') — akibatnya Logistik HO yang harusnya lintas cabang
  // malah ke-lock ke cabangnya sendiri di UI (fleet list, filter cabang
  // Laporan Profitabilitas, dll). Jaga tetap sama persis dengan
  // User::GLOBAL_ROLES di backend kalau daftar role berubah lagi.
  const GLOBAL_ROLES = new Set(['admin_it_ga', 'admin_sistem', 'manajemen', 'logistik_ho'])
  const isBranchScoped = computed(() => !!branchId.value && !GLOBAL_ROLES.has(role.value))

  function setSession (newToken, newUser) {
    token.value = newToken
    user.value = newUser
    persistSession(newToken, newUser)
  }

  function hasPermission (permission) {
    return permissions.value.includes(permission)
  }

  // Best-effort: revoke token di server (lihat AuthController::logout()) —
  // tetap bersihkan state lokal walau request gagal (mis. token sudah
  // kedaluwarsa/dicabut duluan), supaya pengguna selalu bisa keluar dari UI.
  async function logout () {
    try {
      if (token.value) {
        await authApi.logout()
      }
    } catch {
      // diabaikan — state lokal tetap dibersihkan di bawah
    } finally {
      token.value = null
      user.value = null
      persistSession(null, null)
    }
  }

  return { token, user, isAuthenticated, role, permissions, branchId, isBranchScoped, setSession, hasPermission, logout }
})
