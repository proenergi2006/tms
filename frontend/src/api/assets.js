import api from '@/plugins/axios'

// Endpoint Asset Registry & Notifikasi — Design Document Bagian 3.6.
export const assetsApi = {
  list: params => api.get('/assets', { params }),
  create: data => api.post('/assets', data),
  update: (id, data) => api.put(`/assets/${id}`, data),
  remove: id => api.delete(`/assets/${id}`),
}

export const notificationsApi = {
  list: params => api.get('/notifications', { params }),
  markRead: id => api.patch(`/notifications/${id}/read`),
  markAllRead: () => api.patch('/notifications/read-all'),
}
