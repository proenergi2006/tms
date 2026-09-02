export function formatCurrency (value) {
  const number = Number(value ?? 0)

  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(number)
}

export function formatDate (value) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value))
}

export function formatDateTime (value) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(value))
}

// Dipakai untuk MTBF/MTTR & durasi downtime — nilainya bisa dari menit
// sampai ribuan jam, jadi ditampilkan ringkas (hari/jam/menit), bukan
// angka menit mentah yang sulit dibaca.
export function formatDuration (minutes) {
  if (minutes === null || minutes === undefined) {
    return '-'
  }

  const totalMinutes = Math.round(minutes)
  const days = Math.floor(totalMinutes / 1440)
  const hours = Math.floor((totalMinutes % 1440) / 60)
  const mins = totalMinutes % 60

  const parts = []
  if (days > 0) {
    parts.push(`${days}h`)
  }
  if (hours > 0) {
    parts.push(`${hours}j`)
  }
  if (mins > 0 || parts.length === 0) {
    parts.push(`${mins}m`)
  }

  return parts.join(' ')
}
