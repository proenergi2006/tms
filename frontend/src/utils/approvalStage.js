// Tahapan approval WO kini sepenuhnya dinamis (lihat Tahapan Approval /
// approval-steps di admin) — backend yang menentukan urutan role & giliran
// siapa yang berhak bertindak (403 bila belum gilirannya). Frontend hanya
// perlu tahu role mana yang SECARA UMUM tergolong approver (fleet_operations
// atau kepala_pool) & WO masih di tahap 'submitted'.
export function canActOnApproval (approvalStatus, userRole) {
  return approvalStatus === 'submitted' && ['fleet_operations', 'kepala_pool'].includes(userRole)
}
