import api from '@/plugins/axios'

// Manajemen Pengguna — PRD Bagian 4, khusus Admin Sistem (permission
// user.manage). Lihat UserController (backend).
export const usersApi = {
  list: params => api.get('/users', { params }),
  create: data => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  remove: id => api.delete(`/users/${id}`),
}
