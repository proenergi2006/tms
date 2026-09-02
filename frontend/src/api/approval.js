import api from '@/plugins/axios'

// Endpoint Approval — Design Document Bagian 3.4.
export const approvalsApi = {
  pending: () => api.get('/approvals/pending'),
  history: params => api.get('/approvals/history', { params }),
  approve: (workOrderId, notes) => api.post(`/work-orders/${workOrderId}/approve`, { notes }),
  reject: (workOrderId, reason) => api.post(`/work-orders/${workOrderId}/reject`, { reason }),
}

// Tahapan Approval (dinamis) — admin_sistem saja (permission
// approval-step.manage). Tidak ada endpoint hapus, step hanya diubah/
// dinonaktifkan (lihat ApprovalStepController backend).
export const approvalStepsApi = {
  list: () => api.get('/approval-steps'),
  create: data => api.post('/approval-steps', data),
  update: (id, data) => api.patch(`/approval-steps/${id}`, data),
}
