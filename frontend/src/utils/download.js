// Memicu unduhan file dari response blob axios lewat anchor sementara.
// Dipakai untuk endpoint file yang butuh header Authorization (Bearer token
// di memory), sehingga tidak bisa diakses langsung lewat window.open/anchor
// biasa — lihat api/fleetHistory.js.
export function downloadBlob (blob, filename) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.append(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
