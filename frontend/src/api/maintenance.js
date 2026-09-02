import api from '@/plugins/axios'

// Endpoint Pengajuan & Work Order/SPK — Design Document Bagian 3.3.
export const requestsApi = {
  list: params => api.get('/requests', { params }),
  mine: params => api.get('/requests/mine', { params }),
  get: id => api.get(`/requests/${id}`),
  create: data => api.post('/requests', data),
  update: (id, data) => api.patch(`/requests/${id}`, data),
  resubmit: (id, data) => api.post(`/requests/${id}/resubmit`, data),
  uploadAttachment: (id, file, caption) => {
    const form = new FormData()
    form.append('file', file)
    if (caption) {
      form.append('caption', caption)
    }

    return api.post(`/requests/${id}/attachments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}

export const workOrdersApi = {
  list: params => api.get('/work-orders', { params }),
  get: id => api.get(`/work-orders/${id}`),
  assign: data => api.post('/work-orders', data),
  updateStatus: (id, status) => api.patch(`/work-orders/${id}/status`, { status }),
  addItem: (id, data) => api.post(`/work-orders/${id}/items`, data),
  // Multipart hanya dipakai kalau ada item (mendukung foto sparepart bekas
  // per item) — kasus "tidak ada sparepart terpakai" ([] items) tetap JSON
  // biasa karena array kosong tidak bisa direpresentasikan lewat FormData
  // (PHP tidak akan menerima key 'items' sama sekali kalau tidak ada field
  // items[...] yang dikirim, padahal backend mewajibkan 'items' selalu ada).
  realizeItems: (id, { items = [], diagnosis } = {}) => {
    if (items.length === 0) {
      return api.post(`/work-orders/${id}/realize-items`, { items: [], diagnosis })
    }

    const form = new FormData()
    if (diagnosis !== undefined && diagnosis !== null) {
      form.append('diagnosis', diagnosis)
    }

    for (const [index, item] of items.entries()) {
      if (item.sparepart_id) {
        form.append(`items[${index}][sparepart_id]`, item.sparepart_id)
      }

      form.append(`items[${index}][description]`, item.description)
      form.append(`items[${index}][qty]`, item.qty)
      form.append(`items[${index}][unit_cost]`, item.unit_cost)
      // Non-multiple v-file-input (Vuetify 4) binds a single File, not
      // File[] — normalize just in case a caller still passes an array.
      const photo = Array.isArray(item.photo) ? item.photo[0] : item.photo
      if (photo) {
        form.append(`items[${index}][photo]`, photo)
      }
    }

    return api.post(`/work-orders/${id}/realize-items`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  uploadAttachment: (id, file, caption) => {
    const form = new FormData()
    form.append('file', file)
    if (caption) {
      form.append('caption', caption)
    }

    return api.post(`/work-orders/${id}/attachments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
