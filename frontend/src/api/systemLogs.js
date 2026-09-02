import api from '@/plugins/axios'

// Log aplikasi (Laravel log) — supaya Admin Sistem tahu kalau ada error di
// server tanpa akses SSH. Lihat SystemLogController (backend).
export const systemLogsApi = {
  list: params => api.get('/system-logs', { params }),
  summary: () => api.get('/system-logs-summary'),
}
