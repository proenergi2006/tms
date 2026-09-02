import api from '@/plugins/axios'

// Audit Trail — Architecture Document Bagian 7.3, NFR-05 Auditability.
export const auditLogsApi = {
  list: params => api.get('/audit-logs', { params }),
}
