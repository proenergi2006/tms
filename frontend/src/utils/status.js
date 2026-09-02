import i18n from '@/plugins/i18n'

// Pemetaan status -> warna, mengikuti panduan warna Wireframe Document
// Bagian 1.1 (hijau=positif, merah=negatif, kuning/oranye=menunggu). Label
// diterjemahkan lewat locale (lihat locales/id.js & en.js, key enums.status).
const STATUS_COLOR = {
  // approval_status (requests & work_orders) — satu tahap approval saja
  // sekarang (submitted -> completed/rejected).
  submitted: 'warning',
  completed: 'success',
  rejected: 'error',

  // status pelaksanaan work_order
  waiting: 'warning',
  on_progress: 'info',
  finished: 'success',

  // status master data umum
  aktif: 'success',
  nonaktif: 'default',
  maintenance: 'warning',
  rusak: 'error',
  dihapuskan: 'default',
}

export function statusColor (status) {
  return STATUS_COLOR[status] ?? 'default'
}

export function statusLabel (status) {
  const key = `enums.status.${status}`

  return i18n.global.te(key) ? i18n.global.t(key) : status
}
