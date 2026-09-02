import api from '@/plugins/axios'

// Endpoint Master Data — Design Document Bagian 3.2.
export const branchesApi = {
  list: params => api.get('/branches', { params }),
  create: data => api.post('/branches', data),
  update: (id, data) => api.put(`/branches/${id}`, data),
  remove: id => api.delete(`/branches/${id}`),
}

export const fleetsApi = {
  list: params => api.get('/fleets', { params }),
  get: id => api.get(`/fleets/${id}`),
  create: data => api.post('/fleets', data),
  update: (id, data) => api.put(`/fleets/${id}`, data),
  remove: id => api.delete(`/fleets/${id}`),
  statusSummary: params => api.get('/fleets-status-summary', { params }),
  legalWarnings: params => api.get('/fleets-legal-warnings', { params }),
  reliabilitySummary: params => api.get('/fleets-reliability-summary', { params }),
  syncFromSyop: params => api.post('/fleets/sync-syop', null, { params }),
}

export const driversApi = {
  list: params => api.get('/drivers', { params }),
  create: data => api.post('/drivers', data),
  update: (id, data) => api.put(`/drivers/${id}`, data),
  remove: id => api.delete(`/drivers/${id}`),
  syncFromSyop: params => api.post('/drivers/sync-syop', null, { params }),
}

export const mechanicsApi = {
  list: params => api.get('/mechanics', { params }),
  create: data => api.post('/mechanics', data),
  update: (id, data) => api.put(`/mechanics/${id}`, data),
  remove: id => api.delete(`/mechanics/${id}`),
}

export const vendorsApi = {
  list: params => api.get('/vendors', { params }),
  create: data => api.post('/vendors', data),
  update: (id, data) => api.put(`/vendors/${id}`, data),
  remove: id => api.delete(`/vendors/${id}`),
}

export const warehousesApi = {
  list: params => api.get('/warehouses', { params }),
  create: data => api.post('/warehouses', data),
  update: (id, data) => api.put(`/warehouses/${id}`, data),
  remove: id => api.delete(`/warehouses/${id}`),
}

export const sparepartsApi = {
  list: params => api.get('/spareparts', { params }),
  create: data => api.post('/spareparts', data),
  update: (id, data) => api.put(`/spareparts/${id}`, data),
  remove: id => api.delete(`/spareparts/${id}`),
}

export const costTypesApi = {
  list: params => api.get('/cost-types', { params }),
  create: data => api.post('/cost-types', data),
  update: (id, data) => api.put(`/cost-types/${id}`, data),
  remove: id => api.delete(`/cost-types/${id}`),
}

export const jobTypesApi = {
  list: params => api.get('/job-types', { params }),
  create: data => api.post('/job-types', data),
  update: (id, data) => api.put(`/job-types/${id}`, data),
  remove: id => api.delete(`/job-types/${id}`),
}
