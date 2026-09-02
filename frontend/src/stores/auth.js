import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/auth'

// Token disimpan di memory (state Pinia), bukan localStorage — mengurangi
// risiko XSS, sesuai Architecture Document Bagian 3.3.
export const useAuthStore = defineStore('auth', () => {
  const token = ref(null)
  const user = ref(null)

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
    }
  }

  return { token, user, isAuthenticated, role, permissions, branchId, isBranchScoped, setSession, hasPermission, logout }
})
