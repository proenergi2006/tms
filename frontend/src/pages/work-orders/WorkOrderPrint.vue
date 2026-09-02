<script setup>
  import QRCode from 'qrcode'
  import { computed, onMounted, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute, useRouter } from 'vue-router'
  import { workOrdersApi } from '@/api/maintenance'
  import logoProenergi from '@/assets/logo-proenergi.png'
  import { useAuthStore } from '@/stores/auth'
  import { formatCurrency, formatDateTime } from '@/utils/format'

  // Cetak SPK/Work Order — dokumen fisik untuk dibawa mekanik/vendor ke
  // lapangan atau diarsipkan. Route standalone (bukan child DefaultLayout)
  // supaya tidak ada sidebar/topbar yang ikut tercetak; tetap dalam tab yang
  // sama (bukan tab baru) supaya sesi login (token di memory Pinia, lihat
  // stores/auth.js) tidak hilang.
  const route = useRoute()
  const router = useRouter()
  const auth = useAuthStore()
  const { t } = useI18n()

  const workOrder = ref(null)
  const loading = ref(true)
  const qrCodeDataUrl = ref(null)
  const printedAt = ref(new Date())

  function priorityColor (priority) {
    return { high: 'error', medium: 'warning', low: 'default' }[priority] ?? 'default'
  }

  function approvalStatusColor (status) {
    return { submitted: 'warning', completed: 'success', rejected: 'error' }[status] ?? 'default'
  }

  // Ringkasan sah/tidaknya dokumen — MENGGANTIKAN tabel log approval (nama
  // & role tiap approver) yang sebelumnya ikut tercetak. Diminta dihapus
  // dari cetakan fisik SPK (bukan dokumen audit) — cukup pernyataan status
  // akhir yang jelas dibaca mekanik/vendor di lapangan, riwayat lengkap
  // siapa-approve-kapan tetap ada di sistem (WorkOrderDetail.vue).
  const signoffTone = computed(() => ({
    submitted: 'warning',
    completed: 'success',
    rejected: 'error',
  }[workOrder.value?.approval_status] ?? 'neutral'))

  // Gabungan foto pengajuan (lapangan, dari SA) + foto pelaksanaan (WO) —
  // dua sumber attachment berbeda (lihat RequestController/WorkOrderController
  // ::storeAttachment()), disatukan di sini murni untuk tampilan cetak
  // supaya SPK fisik ikut membawa bukti foto, tidak cuma bisa dilihat online.
  const allPhotos = computed(() => {
    if (!workOrder.value) return []
    const fromRequest = (workOrder.value.request?.attachments ?? [])
      .map(att => ({ ...att, source: t('workOrderPrint.photoFromRequest') }))
    const fromExecution = (workOrder.value.attachments ?? [])
      .map(att => ({ ...att, source: t('workOrderPrint.photoFromExecution') }))

    return [...fromRequest, ...fromExecution]
  })

  function usedPartPhoto (item) {
    return item.attachments?.[0]?.url ?? null
  }

  async function load () {
    loading.value = true
    workOrder.value = null
    qrCodeDataUrl.value = null
    printedAt.value = new Date()

    const { data } = await workOrdersApi.get(route.params.id)
    workOrder.value = data.data
    loading.value = false

    // QR code ganti kolom tanda tangan fisik — hanya berarti begitu WO
    // sudah lolos SELURUH tahap approval (approval_status=completed), jadi
    // dipindai untuk membuka WO ini di sistem sebagai bukti sudah disetujui.
    if (workOrder.value.approval_status === 'completed') {
      qrCodeDataUrl.value = await QRCode.toDataURL(`${window.location.origin}/work-orders/${workOrder.value.id}`, {
        width: 130,
        margin: 1,
      })
    }

    setTimeout(() => window.print(), 300)
  }

  onMounted(load)

  // Vue Router menggunakan ulang instance komponen ini kalau berpindah
  // antar WO print secara langsung (hanya parameter :id yang berubah) —
  // onMounted tidak terpanggil lagi, jadi perlu watch param secara eksplisit
  // supaya tidak menampilkan data WO sebelumnya.
  watch(() => route.params.id, load)
</script>

<template>
  <div class="print-page">
    <div class="no-print d-flex ga-2 pa-4">
      <v-btn color="primary" prepend-icon="mdi-printer" variant="flat" @click="window.print()">{{ t('workOrder.print') }}</v-btn>
      <v-btn prepend-icon="mdi-arrow-left" variant="text" @click="router.back()">{{ t('common.back') }}</v-btn>
    </div>

    <div v-if="loading" class="no-print pa-4">{{ t('common.loading') }}</div>

    <div v-else-if="workOrder" class="print-content">
      <div class="doc-topbar" />

      <header class="doc-letterhead">
        <img alt="Pro Energi" class="doc-logo" :src="logoProenergi">

        <div class="doc-company">
          <div class="doc-company-name">PT Pro Energi</div>
          <div class="doc-company-tag">Transport Management System</div>
        </div>

        <div class="doc-doctitle">
          <div class="doc-doctitle-label">{{ t('workOrderPrint.docTitle') }}</div>
          <div class="doc-wo-no">{{ workOrder.wo_no }}</div>

          <div class="doc-status-badges">
            <span class="doc-badge" :class="`doc-badge--${approvalStatusColor(workOrder.approval_status)}`">
              {{ t(`enums.requestStatus.${workOrder.approval_status}`) }}
            </span>

            <span class="doc-badge doc-badge--neutral">{{ t(`enums.status.${workOrder.status}`) }}</span>
          </div>
        </div>
      </header>

      <div class="doc-rule" />

      <section class="doc-section">
        <h2 class="doc-section-title">
          <v-icon icon="mdi-clipboard-text-outline" size="16" />
          {{ t('workOrder.jobInfo') }}
        </h2>

        <table class="info-table mb-4">
          <tbody>
            <tr>
              <td class="label">{{ t('workOrder.requestNo') }}</td>
              <td>{{ workOrder.request?.request_no }}</td>
              <td class="label">{{ t('workOrder.type') }}</td>
              <td>{{ t(`enums.requestType.${workOrder.request?.type}`) }}</td>
            </tr>

            <tr>
              <td class="label">{{ t('requests.priority') }}</td>

              <td>
                <span class="doc-badge" :class="`doc-badge--${priorityColor(workOrder.request?.priority)}`">
                  {{ t(`enums.priority.${workOrder.request?.priority}`) }}
                </span>
              </td>

              <td class="label">{{ t('requests.tarNo') }}</td>
              <td>{{ workOrder.request?.tar_no ?? '-' }}</td>
            </tr>

            <tr v-if="workOrder.request?.maintenance_nature">
              <td class="label">{{ t('requests.maintenanceNature') }}</td>
              <td>{{ t(`enums.maintenanceNature.${workOrder.request.maintenance_nature}`) }}</td>
              <td class="label">{{ t('requests.estimatedDays') }}</td>
              <td>{{ workOrder.request?.estimated_days ? t('requests.estimatedDaysValue', { days: workOrder.request.estimated_days }) : '-' }}</td>
            </tr>

            <tr>
              <td class="label">{{ t('workOrder.fleet') }}</td>
              <td>{{ workOrder.request?.fleet?.plate_number ?? '-' }}</td>
              <td class="label">{{ t('workOrderPrint.printedAt') }}</td>
              <td>{{ formatDateTime(printedAt) }}</td>
            </tr>

            <tr>
              <td class="label">{{ t('workOrder.requestedBy') }}</td>
              <td>{{ workOrder.request?.requested_by_name ?? '-' }}</td>
              <td class="label">{{ t('workOrderPrint.createdAt') }}</td>
              <td>{{ formatDateTime(workOrder.created_at) }}</td>
            </tr>

            <tr>
              <td class="label">{{ t('workOrder.executor') }}</td>

              <td colspan="3">
                <template v-if="workOrder.execution_type">
                  {{ workOrder.execution_type === 'internal' ? workOrder.mechanic?.name : workOrder.vendor?.name }}
                  ({{ workOrder.execution_type }})
                </template>

                <template v-else>-</template>
              </td>
            </tr>

            <tr>
              <td class="label">{{ t('workOrder.description') }}</td>
              <td colspan="3">{{ workOrder.request?.description }}</td>
            </tr>

            <tr v-if="workOrder.request?.diagnosis">
              <td class="label">{{ t('workOrder.diagnosis') }}</td>
              <td colspan="3">{{ workOrder.request.diagnosis }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="doc-section">
        <h2 class="doc-section-title">
          <v-icon icon="mdi-format-list-checks" size="16" />
          {{ t('workOrder.costDetails') }}
        </h2>

        <table class="items-table mb-4">
          <thead>
            <tr>
              <th>{{ t('common.rowNo') }}</th>
              <th>{{ t('workOrder.description') }}</th>
              <th class="text-right">{{ t('workOrder.qty') }}</th>
              <th class="text-right">{{ t('workOrder.unitPrice') }}</th>
              <th class="text-right">{{ t('workOrder.total') }}</th>
              <th>{{ t('workOrder.usedPartPhoto') }}</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(item, index) in workOrder.items" :key="item.id" :class="{ 'row-alt': index % 2 === 1 }">
              <td>{{ index + 1 }}</td>
              <td>{{ item.description }}</td>
              <td class="text-right">{{ item.qty }}</td>
              <td class="text-right">{{ formatCurrency(item.unit_cost) }}</td>
              <td class="text-right">{{ formatCurrency(item.total_cost) }}</td>

              <td>
                <img v-if="usedPartPhoto(item)" alt="" class="item-photo" :src="usedPartPhoto(item)">
                <span v-else class="doc-muted">-</span>
              </td>
            </tr>

            <tr v-if="!workOrder.items?.length">
              <td class="text-medium-emphasis" colspan="6">{{ t('workOrder.noCostDetails') }}</td>
            </tr>
          </tbody>

          <tfoot>
            <tr>
              <td class="font-weight-bold" colspan="4">{{ t('workOrder.totalCost') }}</td>
              <td class="text-right font-weight-bold doc-total-amount">{{ formatCurrency(workOrder.total_cost) }}</td>
              <td />
            </tr>
          </tfoot>
        </table>
      </section>

      <section v-if="allPhotos.length > 0" class="doc-section">
        <h2 class="doc-section-title">
          <v-icon icon="mdi-camera-outline" size="16" />
          {{ t('workOrderPrint.photosTitle') }}
        </h2>

        <div class="photo-grid">
          <div v-for="(photo, index) in allPhotos" :key="index" class="photo-item">
            <img alt="" class="photo-img" :src="photo.url">
            <div class="text-caption doc-muted">{{ photo.caption || photo.source }}</div>
          </div>
        </div>
      </section>

      <section class="doc-signoff">
        <div class="doc-signoff-note" :class="`doc-signoff-note--${signoffTone}`">
          <v-icon
            :color="signoffTone === 'neutral' ? undefined : signoffTone"
            :icon="workOrder.approval_status === 'rejected' ? 'mdi-close-circle-outline' : (workOrder.approval_status === 'completed' ? 'mdi-shield-check-outline' : 'mdi-clock-outline')"
            size="20"
          />

          <p>{{ t(`workOrderPrint.signoff.${workOrder.approval_status}`) }}</p>
        </div>

        <div class="verification-box">
          <template v-if="qrCodeDataUrl">
            <img alt="QR verifikasi" class="verification-qr" :src="qrCodeDataUrl">
            <div class="text-caption font-weight-medium">{{ t('workOrderPrint.qrVerified') }}</div>
          </template>

          <div v-else class="text-caption text-medium-emphasis verification-placeholder">
            {{ t('workOrderPrint.qrNotYetVerified') }}
          </div>
        </div>
      </section>

      <footer class="doc-footer">
        <div class="text-caption doc-muted">{{ t('workOrderPrint.footerNote') }}</div>

        <div class="text-caption doc-muted doc-footer-meta">
          {{ t('workOrderPrint.printedBy', { name: auth.user?.name ?? '-' }) }} · {{ formatDateTime(printedAt) }}
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@page {
  size: A4;
  margin: 14mm 12mm;
}

.print-content {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 32px 48px;
  font-size: 13px;
  color: #1F2937;
  line-height: 1.5;
}

.doc-topbar {
  height: 7px;
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-primary-darken-1)) 100%);
  margin: 0 -32px 22px;
}

/* -- Letterhead -- */
.doc-letterhead {
  display: flex;
  align-items: center;
  gap: 18px;
}

.doc-logo {
  width: 88px;
  height: auto;
  flex: 0 0 auto;
}

.doc-company {
  flex: 1 1 auto;
}

.doc-company-name {
  font-size: 19px;
  font-weight: 800;
  letter-spacing: 0.02em;
  color: #111827;
}

.doc-company-tag {
  font-size: 11px;
  color: rgba(0, 0, 0, 0.5);
  letter-spacing: 0.03em;
}

.doc-doctitle {
  flex: 0 0 auto;
  text-align: right;
}

.doc-doctitle-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: rgb(var(--v-theme-primary));
}

.doc-wo-no {
  font-size: 17px;
  font-weight: 700;
  color: #111827;
  margin: 1px 0 6px;
}

.doc-status-badges {
  display: flex;
  gap: 4px;
  justify-content: flex-end;
}

.doc-rule {
  height: 2px;
  background: linear-gradient(90deg, rgba(0, 0, 0, 0.12) 0%, rgba(0, 0, 0, 0.02) 100%);
  margin: 16px 0 20px;
}

/* -- Sections -- */
.doc-section {
  margin-bottom: 22px;
}

.doc-section-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgb(var(--v-theme-primary));
  margin: 0 0 8px;
}

.doc-badge {
  display: inline-block;
  padding: 2px 11px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.doc-badge--success { background: #DCFCE7; color: #15803D; }
.doc-badge--warning { background: #FEF3C7; color: #B45309; }
.doc-badge--error { background: #FEE2E2; color: #B91C1C; }
.doc-badge--neutral { background: #E5E7EB; color: #374151; }
.doc-badge--default { background: #E5E7EB; color: #374151; }

/* -- Tables -- */
.info-table, .items-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid rgba(0, 0, 0, 0.1);
}

.info-table td {
  padding: 7px 10px;
  vertical-align: top;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.info-table tr:last-child td {
  border-bottom: none;
}

.info-table .label {
  width: 150px;
  font-weight: 600;
  color: rgba(0, 0, 0, 0.55);
  background: rgba(0, 0, 0, 0.015);
}

.items-table th {
  background: rgb(var(--v-theme-primary));
  color: white;
  text-align: left;
  padding: 8px 10px;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.items-table td {
  border: 1px solid rgba(0, 0, 0, 0.08);
  padding: 7px 10px;
  vertical-align: top;
}

.items-table tfoot td {
  border-top: 2px solid rgba(0, 0, 0, 0.25);
  background: rgba(0, 0, 0, 0.015);
}

.row-alt {
  background: rgba(0, 0, 0, 0.02);
}

.doc-total-amount {
  color: rgb(var(--v-theme-primary));
}

.doc-muted {
  color: rgba(0, 0, 0, 0.5);
}

.item-photo {
  width: 90px;
  height: 90px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid rgba(0, 0, 0, 0.15);
}

/* -- Photos -- */
.photo-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.photo-item {
  break-inside: avoid;
  text-align: center;
}

.photo-img {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid rgba(0, 0, 0, 0.15);
  margin-bottom: 3px;
}

/* -- Sign-off (menggantikan tabel log approval) -- */
.doc-signoff {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-top: 30px;
  break-inside: avoid;
}

.doc-signoff-note {
  flex: 1 1 auto;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid;
}

.doc-signoff-note p {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.5;
}

.doc-signoff-note--success { background: #F0FDF4; border-color: #BBF7D0; color: #166534; }
.doc-signoff-note--warning { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
.doc-signoff-note--error { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }
.doc-signoff-note--neutral { background: #F9FAFB; border-color: #E5E7EB; color: #374151; }

.verification-box {
  flex: 0 0 auto;
  text-align: center;
}

.verification-qr {
  width: 110px;
  height: 110px;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 8px;
  padding: 6px;
}

.verification-placeholder {
  width: 110px;
}

/* -- Footer -- */
.doc-footer {
  text-align: center;
  margin-top: 26px;
  border-top: 1px solid rgba(0, 0, 0, 0.08);
  padding-top: 10px;
}

.doc-footer-meta {
  margin-top: 3px;
}

@media print {
  .no-print {
    display: none !important;
  }

  .print-content {
    max-width: none;
    padding: 0;
  }

  .doc-topbar {
    margin: 0 0 22px;
  }
}
</style>
