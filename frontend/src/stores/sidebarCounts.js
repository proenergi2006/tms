import { defineStore } from 'pinia'
import { ref } from 'vue'
import { approvalsApi } from '@/api/approval'
import { requestsApi } from '@/api/maintenance'
import { systemLogsApi } from '@/api/systemLogs'
import { useAuthStore } from '@/stores/auth'

// Badge angka di sidebar (DefaultLayout.vue) — disimpan di store terpisah
// (bukan state lokal di layout) supaya halaman lain (approve/reject di
// ApprovalsQueue.vue & WorkOrderDetail.vue, submit/resubmit di
// RequestForm.vue) bisa memicu refresh SEGERA setelah aksinya berhasil,
// tanpa menunggu polling 60 detik. Sengaja BUKAN solusinya menyuruh
// pengguna me-refresh halaman browser — sesi login di app ini token-nya
// cuma disimpan in-memory (lihat stores/auth.js), jadi reload halaman
// malah selalu logout, bukan cara yang valid untuk "menyegarkan" apa pun
// di sini.
export const useSidebarCountsStore = defineStore('sidebarCounts', () => {
  const approvalPending = ref(0)
  const requestsSubmitted = ref(0)
  const systemLogErrors = ref(0)

  async function refreshApprovalPending () {
    const auth = useAuthStore()
    if (!auth.hasPermission('approval.view')) {
      return
    }
    try {
      const { data } = await approvalsApi.pending()
      approvalPending.value = data.data.length
    } catch {
      // diabaikan — badge bersifat opsional
    }
  }

  async function refreshRequestsSubmitted () {
    const auth = useAuthStore()
    if (!auth.hasPermission('request.view')) {
      return
    }
    try {
      const { data } = await requestsApi.list({ status: 'submitted', per_page: 1 })
      requestsSubmitted.value = data.meta?.total ?? 0
    } catch {
      // diabaikan — badge bersifat opsional
    }
  }

  async function refreshSystemLogErrors () {
    const auth = useAuthStore()
    if (!auth.hasPermission('system-log.view')) {
      return
    }
    try {
      const { data } = await systemLogsApi.summary()
      systemLogErrors.value = data.error_count_24h
    } catch {
      // diabaikan — badge bersifat opsional
    }
  }

  // Dipanggil DefaultLayout.vue saat mount & tiap 60 detik (jaring pengaman
  // kalau ada perubahan dari luar tab ini, mis. approver lain), DAN oleh
  // halaman aksi (approve/reject/submit/resubmit) segera setelah aksinya
  // sukses supaya badge tidak menunggu siklus polling berikutnya.
  function refreshAll () {
    refreshApprovalPending()
    refreshRequestsSubmitted()
    refreshSystemLogErrors()
  }

  return {
    approvalPending,
    requestsSubmitted,
    systemLogErrors,
    refreshApprovalPending,
    refreshRequestsSubmitted,
    refreshSystemLogErrors,
    refreshAll,
  }
})
