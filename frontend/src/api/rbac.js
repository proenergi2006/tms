import api from '@/plugins/axios'

// Manajemen Role & Permission (RBAC) — PRD Bagian 4, khusus Admin Sistem
// (permission rbac.manage). Lihat RoleController/PermissionController (backend).
export const rolesApi = {
  list: () => api.get('/roles'),
  create: data => api.post('/roles', data),
  update: (id, data) => api.put(`/roles/${id}`, data),
  syncPermissions: (id, permissionIds) => api.put(`/roles/${id}/permissions`, { permission_ids: permissionIds }),
  remove: id => api.delete(`/roles/${id}`),
}

export const permissionsApi = {
  list: () => api.get('/permissions'),
}
