<script setup>
  import { computed, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute, useRouter } from 'vue-router'
  import { approvalsApi } from '@/api/approval'
  import { workOrdersApi } from '@/api/maintenance'
  import { mechanicsApi, sparepartsApi, vendorsApi } from '@/api/masterData'
  import ConfirmDialog from '@/components/ConfirmDialog.vue'
  import StatusChip from '@/components/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useSidebarCountsStore } from '@/stores/sidebarCounts'
  import { canActOnApproval } from '@/utils/approvalStage'
  import { formatCurrency, formatDate, formatDateTime } from '@/utils/format'

  // Detail Work Order / SPK — Wireframe Document Bagian 2.3.
  const route = useRoute()
  const router = useRouter()
  const auth = useAuthStore()
  const sidebarCounts = useSidebarCountsStore()
  const { t } = useI18n()

  const workOrder = ref(null)
  const loading = ref(true)
  const errorMessage = ref(null)

  function priorityColor (priority) {
    return { high: 'error', medium: 'warning', low: 'default' }[priority] ?? 'default'
  }

  async function load () {
    loading.value = true
    try {
      const { data } = await workOrdersApi.get(route.params.id)
      workOrder.value = data.data
    } finally {
      loading.value = false
    }
  }

  const canApprove = computed(() => workOrder.value && canActOnApproval(workOrder.value.approval_status, auth.role))

  // Sejalan dengan guard backend (WorkOrderController::CLEARED_FOR_EXECUTION):
  // status pelaksanaan hanya boleh maju dari 'waiting' setelah approval_status
  // mencapai 'completed' (approval kini satu tahap saja).
  const clearedForExecution = computed(() => workOrder.value && workOrder.value.approval_status === 'completed')

  // -- Approve/Reject --
  const approveDialog = ref(false)
  const rejectDialog = ref(false)
  const approving = ref(false)
  const notes = ref('')
  const reason = ref('')

  async function doApprove () {
    approving.value = true
    errorMessage.value = null
    try {
      await approvalsApi.approve(workOrder.value.id, notes.value)
      approveDialog.value = false
      await load()
      sidebarCounts.refreshApprovalPending()
      sidebarCounts.refreshRequestsSubmitted()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('workOrder.approvalFailed')
    } finally {
      approving.value = false
    }
  }

  async function doReject () {
    approving.value = true
    errorMessage.value = null
    try {
      await approvalsApi.reject(workOrder.value.id, reason.value)
      rejectDialog.value = false
      await load()
      sidebarCounts.refreshApprovalPending()
      sidebarCounts.refreshRequestsSubmitted()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('workOrder.rejectFailed')
    } finally {
      approving.value = false
    }
  }

  // -- Assign pelaksana --
  const mechanics = ref([])
  const vendors = ref([])
  const assignDialog = ref(false)
  const assigning = ref(false)
  const assignForm = ref({ execution_type: 'internal', mechanic_id: null, vendor_id: null })

  function openAssignDialog () {
    assignForm.value = {
      execution_type: workOrder.value.execution_type ?? 'internal',
      mechanic_id: workOrder.value.mechanic_id,
      vendor_id: workOrder.value.vendor_id,
    }
    assignDialog.value = true
  }

  async function submitAssign () {
    assigning.value = true
    try {
      await workOrdersApi.assign({ request_id: workOrder.value.request_id, ...assignForm.value })
      assignDialog.value = false
      await load()
    } finally {
      assigning.value = false
    }
  }

  // -- Item biaya --
  const itemDialog = ref(false)
  const addingItem = ref(false)
  const itemError = ref(null)
  const spareparts = ref([])
  const newItem = ref({ sparepart_id: null, description: '', qty: 1, unit_cost: 0 })

  function openItemDialog () {
    newItem.value = { sparepart_id: null, description: '', qty: 1, unit_cost: 0 }
    itemError.value = null
    itemDialog.value = true
  }

  // Mengisi deskripsi & harga satuan otomatis dari data sparepart yang
  // dipilih (lihat masterData.unitCost) — pengguna tetap bisa mengubahnya,
  // mis. mencatat detail pemasangan atau harga berubah dari master data.
  function onSparepartSelect (sparepartId) {
    const sparepart = spareparts.value.find(s => s.id === sparepartId)
    if (sparepart) {
      newItem.value.description = sparepart.name
      newItem.value.unit_cost = Number(sparepart.unit_cost) || 0
    }
  }

  async function submitItem () {
    addingItem.value = true
    itemError.value = null
    try {
      await workOrdersApi.addItem(workOrder.value.id, newItem.value)
      itemDialog.value = false
      await load()
      const { data } = await sparepartsApi.list({ per_page: 100 })
      spareparts.value = data.data
    } catch (error) {
      itemError.value = error.response?.data?.message ?? t('workOrder.addItemFailed')
    } finally {
      addingItem.value = false
    }
  }

  // -- Lampiran --
  const uploadingAttachment = ref(false)
  const attachmentError = ref(null)
  const newAttachmentFiles = ref([])

  // Foto ditampilkan sebagai thumbnail; berkas lain (mis. PDF) sebagai ikon.
  function isImageAttachment (att) {
    return /\.(?:jpe?g|png|gif|webp)$/i.test(att.url ?? att.file_path ?? '')
  }

  function attachmentName (att) {
    return att.caption || att.file_path.split('/').pop()
  }

  async function submitAttachment () {
    if (newAttachmentFiles.value.length === 0) return
    uploadingAttachment.value = true
    attachmentError.value = null
    try {
      for (const file of newAttachmentFiles.value) {
        await workOrdersApi.uploadAttachment(workOrder.value.id, file)
      }
      newAttachmentFiles.value = []
      await load()
    } catch (error) {
      attachmentError.value = error.response?.data?.message ?? t('workOrder.attachmentUploadFailed')
    } finally {
      uploadingAttachment.value = false
    }
  }

  // -- Status pelaksanaan --
  const updatingStatus = ref(false)
  const nextStatusMap = { waiting: 'on_progress', on_progress: 'finished' }
  const nextStatusLabel = computed(() => ({
    on_progress: t('workOrder.startWork'),
    finished: t('workOrder.markFinished'),
  }))

  async function advanceStatus () {
    const next = nextStatusMap[workOrder.value.status]
    if (!next) return
    updatingStatus.value = true
    errorMessage.value = null
    try {
      await workOrdersApi.updateStatus(workOrder.value.id, next)
      await load()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('workOrder.statusUpdateFailed')
    } finally {
      updatingStatus.value = false
    }
  }

  // Klik tombol status "on_progress -> finished" tidak langsung memanggil
  // advanceStatus() — backend menolak (422) status finished bila
  // items_realized_at masih null, jadi SA wajib konfirmasi realisasi
  // sparepart (bisa daftar kosong) lewat dialog ini dulu. Transisi lain
  // (waiting -> on_progress) tetap langsung lewat advanceStatus().
  function onAdvanceClick () {
    if (nextStatusMap[workOrder.value.status] === 'finished') {
      openRealizeDialog()
    } else {
      advanceStatus()
    }
  }

  // -- Realisasi sparepart (sebelum WO ditandai finished) --
  const realizeDialog = ref(false)
  const realizing = ref(false)
  const realizeError = ref(null)
  const realizeItems = ref([])
  const realizeDiagnosis = ref('')

  function openRealizeDialog () {
    // Pra-isi dari daftar item WO saat ini (rencana/estimasi awal) — SA
    // menyesuaikannya jadi apa yang benar-benar terpakai. Diagnosa awal juga
    // bisa diperbarui di sini jadi temuan aktual setelah pekerjaan berjalan.
    const isEksternal = workOrder.value.execution_type === 'eksternal'
    realizeItems.value = (workOrder.value.items ?? []).map(item => ({
      sparepart_id: isEksternal ? null : (item.sparepart_id ?? item.sparepart?.id ?? null),
      description: item.description,
      qty: item.qty,
      unit_cost: item.unit_cost,
      // Foto sparepart bekas wajib diunggah ulang tiap kali realisasi
      // dikonfirmasi (item lama dihapus & dibuat ulang di backend — lihat
      // WorkOrderController::realizeItems() — jadi tidak ada foto lama untuk
      // di-prefill di sini). v-file-input non-multiple (Vuetify 4) mem-bind
      // File tunggal, BUKAN File[] — jangan diinisialisasi sebagai array.
      photo: null,
    }))
    realizeDiagnosis.value = workOrder.value.request?.diagnosis ?? ''
    realizeError.value = null
    realizeDialog.value = true
  }

  function addRealizeItemRow () {
    realizeItems.value.push({ sparepart_id: null, description: '', qty: 1, unit_cost: 0, photo: null })
  }

  function removeRealizeItemRow (index) {
    realizeItems.value.splice(index, 1)
  }

  function onRealizeItemSparepartSelect (row, sparepartId) {
    const sparepart = spareparts.value.find(s => s.id === sparepartId)
    if (sparepart) {
      row.description = sparepart.name
      row.unit_cost = Number(sparepart.unit_cost) || 0
    }
  }

  const realizeItemsTotal = computed(
    () => realizeItems.value.reduce((sum, item) => sum + (Number(item.qty) || 0) * (Number(item.unit_cost) || 0), 0),
  )

  // Bukan pengganti validasi backend (RealizeWorkOrderItemsRequest), hanya
  // supaya SA dapat pesan error langsung tanpa menunggu roundtrip 422 kalau
  // lupa lampirkan foto sparepart bekas.
  const missingPhotoRows = computed(
    () => realizeItems.value.some(row => row.sparepart_id && !row.photo),
  )

  async function confirmRealize () {
    if (missingPhotoRows.value) {
      realizeError.value = t('workOrder.usedPartPhotoRequired')
      return
    }

    realizing.value = true
    realizeError.value = null
    try {
      await workOrdersApi.realizeItems(workOrder.value.id, { items: realizeItems.value, diagnosis: realizeDiagnosis.value })
      await workOrdersApi.updateStatus(workOrder.value.id, 'finished')
      realizeDialog.value = false
      await load()
    } catch (error) {
      realizeError.value = error.response?.data?.message ?? t('workOrder.realizeFailed')
    } finally {
      realizing.value = false
    }
  }

  onMounted(async () => {
    await load()
    const [mechRes, vendorRes, sparepartRes] = await Promise.all([
      mechanicsApi.list({ per_page: 100 }),
      vendorsApi.list({ per_page: 100 }),
      sparepartsApi.list({ per_page: 100 }),
    ])
    mechanics.value = mechRes.data.data
    vendors.value = vendorRes.data.data
    spareparts.value = sparepartRes.data.data
  })
</script>

<template>
  <div v-if="loading">
    <v-skeleton-loader type="article" />
  </div>

  <div v-else-if="workOrder">
    <div class="d-flex align-center mb-4 flex-wrap ga-2">
      <h1 class="text-h5">{{ workOrder.wo_no }}</h1>
      <StatusChip :status="workOrder.approval_status" />
      <StatusChip :status="workOrder.status" />

      <span v-if="workOrder.approval_status === 'submitted' && workOrder.approval_step" class="text-caption text-medium-emphasis">
        {{ t('requests.waitingApproval', { role: workOrder.approval_step.label }) }}
      </span>

      <v-spacer />

      <v-btn
        v-if="workOrder.approval_status === 'rejected' && auth.hasPermission('request.create') && workOrder.request?.requested_by === auth.user?.id"
        color="primary"
        prepend-icon="mdi-refresh"
        variant="tonal"
        @click="router.push(`/requests/${workOrder.request_id}/resubmit`)"
      >
        {{ t('requests.resubmit') }}
      </v-btn>

      <v-btn
        prepend-icon="mdi-printer"
        variant="text"
        @click="router.push(`/work-orders/${workOrder.id}/print`)"
      >
        {{ t('workOrder.print') }}
      </v-btn>

      <v-btn
        v-if="auth.hasPermission('work-order.update-status') && workOrder.status !== 'finished' && workOrder.approval_status !== 'rejected'"
        color="primary"
        :disabled="!nextStatusMap[workOrder.status] || !clearedForExecution"
        :loading="updatingStatus"
        variant="tonal"
        @click="onAdvanceClick"
      >
        {{ clearedForExecution ? (nextStatusLabel[nextStatusMap[workOrder.status]] ?? '-') : t('workOrder.waitingApproval') }}
      </v-btn>
    </div>

    <v-alert
      v-if="errorMessage"
      class="mb-4"
      closable
      type="error"
      variant="tonal"
      @click:close="errorMessage = null"
    >
      {{ errorMessage }}
    </v-alert>

    <v-row>
      <v-col cols="12" md="7">
        <v-card class="mb-4">
          <v-card-title>{{ t('workOrder.jobInfo') }}</v-card-title>

          <v-card-text>
            <v-row dense>
              <v-col cols="6"><strong>{{ t('workOrder.requestNo') }}</strong><div>{{ workOrder.request?.request_no }}</div></v-col>
              <v-col cols="6"><strong>{{ t('workOrder.type') }}</strong><div>{{ t(`enums.requestType.${workOrder.request?.type}`) }}</div></v-col>

              <v-col cols="6">
                <strong>{{ t('requests.priority') }}</strong>

                <div>
                  <v-chip :color="priorityColor(workOrder.request?.priority)" size="small" variant="tonal">
                    {{ t(`enums.priority.${workOrder.request?.priority}`) }}
                  </v-chip>
                </div>
              </v-col>

              <v-col cols="6">
                <strong>{{ t('requests.tarNo') }}</strong>
                <div>{{ workOrder.request?.tar_no ?? '-' }}</div>
              </v-col>

              <v-col v-if="workOrder.request?.maintenance_nature" cols="6">
                <strong>{{ t('requests.maintenanceNature') }}</strong>
                <div>{{ t(`enums.maintenanceNature.${workOrder.request.maintenance_nature}`) }}</div>
              </v-col>

              <v-col v-if="workOrder.request?.estimated_days" cols="6">
                <strong>{{ t('requests.estimatedDays') }}</strong>
                <div>{{ t('requests.estimatedDaysValue', { days: workOrder.request.estimated_days }) }}</div>
              </v-col>

              <v-col v-if="workOrder.request?.odometer_km !== null && workOrder.request?.odometer_km !== undefined" cols="6">
                <strong>{{ t('requests.odometer') }}</strong>
                <div>{{ workOrder.request.odometer_km }} km</div>
              </v-col>

              <v-col v-if="workOrder.request?.trouble_date" cols="6">
                <strong>{{ t('requests.troubleDate') }}</strong>
                <div>{{ formatDate(workOrder.request.trouble_date) }}</div>
              </v-col>

              <v-col cols="6"><strong>{{ t('workOrder.fleet') }}</strong><div>{{ workOrder.request?.fleet?.plate_number ?? '-' }}</div></v-col>
              <v-col cols="6"><strong>{{ t('workOrder.requestedBy') }}</strong><div>{{ workOrder.request?.requested_by_name ?? '-' }}</div></v-col>
              <v-col cols="12"><strong>{{ t('workOrder.description') }}</strong><div>{{ workOrder.request?.description }}</div></v-col>

              <v-col v-if="workOrder.request?.suggestion" cols="12">
                <strong>{{ t('requests.suggestion') }}</strong>
                <div>{{ workOrder.request.suggestion }}</div>
              </v-col>

              <v-col v-if="workOrder.request?.action_taken" cols="12">
                <strong>{{ t('requests.actionTaken') }}</strong>
                <div>{{ workOrder.request.action_taken }}</div>
              </v-col>

              <v-col cols="6">
                <strong>{{ t('workOrder.executor') }}</strong>

                <div v-if="workOrder.execution_type">
                  {{ workOrder.execution_type === 'internal' ? workOrder.mechanic?.name : workOrder.vendor?.name }}
                  ({{ workOrder.execution_type }})
                </div>

                <div v-else class="text-medium-emphasis">{{ t('workOrder.notAssigned') }}</div>
              </v-col>

              <v-col class="text-right" cols="6">
                <v-btn v-if="auth.hasPermission('work-order.manage')" size="small" variant="text" @click="openAssignDialog">{{ t('workOrder.assignExecutor') }}</v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title>{{ t('workOrder.diagnosis') }}</v-card-title>

          <v-card-text>
            <p v-if="workOrder.request?.diagnosis" class="mb-0" style="white-space: pre-wrap;">{{ workOrder.request.diagnosis }}</p>
            <div v-else class="text-medium-emphasis">{{ t('workOrder.noDiagnosis') }}</div>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="d-flex align-center">
            {{ t('workOrder.costDetails') }}
            <v-spacer />
            <v-btn v-if="auth.hasPermission('work-order.manage')" size="small" variant="text" @click="openItemDialog">{{ t('workOrder.addItem') }}</v-btn>
          </v-card-title>

          <v-table>
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
              <tr v-for="(item, index) in workOrder.items" :key="item.id">
                <td>{{ index + 1 }}</td>
                <td>{{ item.description }}</td>
                <td class="text-right">{{ item.qty }}</td>
                <td class="text-right">{{ formatCurrency(item.unit_cost) }}</td>
                <td class="text-right">{{ formatCurrency(item.total_cost) }}</td>

                <td>
                  <a
                    v-if="item.attachments?.length"
                    :href="item.attachments[0].url"
                    rel="noopener"
                    target="_blank"
                  >
                    <v-img
                      class="rounded"
                      cover
                      height="40"
                      :src="item.attachments[0].url"
                      width="40"
                    />
                  </a>

                  <span v-else-if="item.sparepart_id" class="text-caption text-medium-emphasis">
                    {{ t('workOrder.noUsedPartPhoto') }}
                  </span>
                </td>
              </tr>

              <tr v-if="!workOrder.items?.length">
                <td class="text-medium-emphasis" colspan="6">{{ t('workOrder.noCostDetails') }}</td>
              </tr>
            </tbody>

            <tfoot>
              <tr>
                <td class="font-weight-bold" colspan="4">{{ t('workOrder.totalCost') }}</td>
                <td class="text-right font-weight-bold">{{ formatCurrency(workOrder.total_cost) }}</td>
                <td />
              </tr>
            </tfoot>
          </v-table>
        </v-card>

        <v-card>
          <v-card-title>{{ t('workOrder.attachments') }}</v-card-title>

          <v-card-text>
            <v-alert v-if="attachmentError" class="mb-4" type="error" variant="tonal">{{ attachmentError }}</v-alert>

            <div class="text-caption text-medium-emphasis mb-1">{{ t('workOrder.fromRequest') }}</div>

            <v-row v-if="workOrder.request?.attachments?.length" class="mb-1" dense>
              <v-col v-for="att in workOrder.request.attachments" :key="att.id" cols="6" sm="4">
                <v-card :href="att.url" target="_blank" variant="outlined">
                  <v-img v-if="isImageAttachment(att)" aspect-ratio="1.4" cover :src="att.url" />

                  <div v-else class="d-flex align-center justify-center" style="height: 96px;">
                    <v-icon icon="mdi-file-document-outline" size="40" />
                  </div>

                  <v-card-text class="pa-2 text-caption text-truncate">{{ attachmentName(att) }}</v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <div v-else class="text-body-2 text-medium-emphasis mb-3">{{ t('workOrder.noRequestAttachments') }}</div>

            <v-divider class="my-3" />

            <div class="text-caption text-medium-emphasis mb-1">{{ t('workOrder.fromExecution') }}</div>

            <v-row v-if="workOrder.attachments?.length" class="mb-1" dense>
              <v-col v-for="att in workOrder.attachments" :key="att.id" cols="6" sm="4">
                <v-card :href="att.url" target="_blank" variant="outlined">
                  <v-img v-if="isImageAttachment(att)" aspect-ratio="1.4" cover :src="att.url" />

                  <div v-else class="d-flex align-center justify-center" style="height: 96px;">
                    <v-icon icon="mdi-file-document-outline" size="40" />
                  </div>

                  <v-card-text class="pa-2 text-caption text-truncate">{{ attachmentName(att) }}</v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <div v-else class="text-body-2 text-medium-emphasis">{{ t('workOrder.noExecutionAttachments') }}</div>

            <template v-if="auth.hasPermission('work-order.manage')">
              <v-file-input
                v-model="newAttachmentFiles"
                class="mt-3"
                density="compact"
                hide-details
                :label="t('workOrder.addAttachment')"
                multiple
                prepend-icon="mdi-paperclip"
                show-size
              />

              <v-btn
                class="mt-2"
                :disabled="newAttachmentFiles.length === 0"
                :loading="uploadingAttachment"
                size="small"
                variant="tonal"
                @click="submitAttachment"
              >
                {{ t('workOrder.upload') }}
              </v-btn>
            </template>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card>
          <v-card-title class="d-flex align-center">
            {{ t('workOrder.approvalTimeline') }}
            <v-spacer />

            <template v-if="canApprove">
              <v-btn color="success" size="small" variant="flat" @click="approveDialog = true">{{ t('workOrder.approve') }}</v-btn>

              <v-btn
                class="ml-2"
                color="error"
                size="small"
                variant="tonal"
                @click="rejectDialog = true"
              >{{ t('workOrder.reject') }}</v-btn>
            </template>
          </v-card-title>

          <v-card-text>
            <v-timeline density="compact" side="end">
              <v-timeline-item
                v-for="log in workOrder.approval_logs"
                :key="log.id"
                :dot-color="log.action === 'approve' ? 'success' : 'error'"
                size="small"
              >
                <div class="text-body-2">
                  <strong>{{ t(`enums.role.${log.approver_role}`) }}</strong> — {{ log.action === 'approve' ? t('workOrder.approving') : t('workOrder.rejecting') }}
                </div>

                <div class="text-caption text-medium-emphasis">{{ formatDateTime(log.approved_at) }}</div>
                <div v-if="log.notes" class="text-caption">"{{ log.notes }}"</div>
              </v-timeline-item>

              <v-timeline-item v-if="!workOrder.approval_logs?.length" dot-color="grey" size="small">
                <div class="text-body-2 text-medium-emphasis">{{ t('workOrder.noApprovalHistory') }}</div>
              </v-timeline-item>
            </v-timeline>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <ConfirmDialog
      v-model="approveDialog"
      v-model:reason="notes"
      confirm-color="success"
      :confirm-text="t('workOrder.approve')"
      :loading="approving"
      :message="t('workOrder.confirmApproveMessage')"
      :reason-label="t('workOrder.notesLabel')"
      :title="t('workOrder.confirmApproveTitle')"
      @confirm="doApprove"
    />

    <ConfirmDialog
      v-model="rejectDialog"
      v-model:reason="reason"
      confirm-color="error"
      :confirm-text="t('workOrder.reject')"
      :loading="approving"
      :message="t('workOrder.confirmRejectMessage')"
      :reason-label="t('workOrder.rejectReasonLabel')"
      require-reason
      :title="t('workOrder.confirmRejectTitle')"
      @confirm="doReject"
    />

    <v-dialog v-model="assignDialog" max-width="480">
      <v-card>
        <v-card-title>{{ t('workOrder.assignExecutor') }}</v-card-title>

        <v-card-text>
          <v-radio-group v-model="assignForm.execution_type" inline :label="t('workOrder.executorType')">
            <v-radio :label="t('workOrder.internal')" value="internal" />
            <v-radio :label="t('workOrder.external')" value="eksternal" />
          </v-radio-group>

          <v-select
            v-if="assignForm.execution_type === 'internal'"
            v-model="assignForm.mechanic_id"
            item-title="name"
            item-value="id"
            :items="mechanics"
            :label="t('workOrder.mechanic')"
          />

          <v-select
            v-else
            v-model="assignForm.vendor_id"
            item-title="name"
            item-value="id"
            :items="vendors"
            :label="t('workOrder.vendor')"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="assignDialog = false">{{ t('common.cancel') }}</v-btn>
          <v-btn color="primary" :loading="assigning" variant="flat" @click="submitAssign">{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="itemDialog" max-width="480">
      <v-card>
        <v-card-title>{{ t('workOrder.addCostItem') }}</v-card-title>

        <v-card-text>
          <v-alert v-if="itemError" class="mb-4" type="error" variant="tonal">{{ itemError }}</v-alert>

          <v-select
            v-if="workOrder.execution_type !== 'eksternal'"
            v-model="newItem.sparepart_id"
            clearable
            item-title="name"
            item-value="id"
            :items="spareparts"
            :label="`${t('masterData.tabSpareparts')} ${t('common.optional')}`"
            @update:model-value="onSparepartSelect"
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item v-bind="itemProps" :subtitle="`${t('masterData.stock')}: ${item.stock_qty}`" />
            </template>
          </v-select>

          <v-text-field v-model="newItem.description" :label="t('workOrder.description')" />
          <v-text-field v-model.number="newItem.qty" :label="t('workOrder.qty')" type="number" />
          <v-text-field v-model.number="newItem.unit_cost" :label="t('workOrder.unitPrice')" type="number" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="itemDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="!newItem.description"
            :loading="addingItem"
            variant="flat"
            @click="submitItem"
          >
            {{ t('common.save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="realizeDialog" max-width="720">
      <v-card>
        <v-card-title>{{ t('workOrder.realizeItems') }}</v-card-title>

        <v-card-text>
          <v-alert v-if="realizeError" class="mb-4" type="error" variant="tonal">{{ realizeError }}</v-alert>
          <p class="text-body-2 text-medium-emphasis mb-4">{{ t('workOrder.realizeItemsHint') }}</p>

          <v-textarea
            v-model="realizeDiagnosis"
            class="mb-2"
            :label="t('requests.diagnosis')"
            rows="3"
          />

          <div class="d-flex align-center mb-2">
            <span class="text-subtitle-2">{{ t('workOrder.costDetails') }}</span>
            <v-spacer />
            <v-btn prepend-icon="mdi-plus" size="small" variant="text" @click="addRealizeItemRow">{{ t('workOrder.addItem') }}</v-btn>
          </div>

          <v-table v-if="realizeItems.length > 0" density="compact">
            <thead>
              <tr>
                <th v-if="workOrder.execution_type !== 'eksternal'">{{ t('masterData.tabSpareparts') }}</th>
                <th>{{ t('workOrder.description') }}</th>
                <th class="text-right">{{ t('workOrder.qty') }}</th>
                <th class="text-right">{{ t('workOrder.unitPrice') }}</th>
                <th class="text-right">{{ t('workOrder.total') }}</th>
                <th v-if="workOrder.execution_type !== 'eksternal'">{{ t('workOrder.usedPartPhoto') }}</th>
                <th />
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in realizeItems" :key="index">
                <td v-if="workOrder.execution_type !== 'eksternal'" style="min-width: 160px;">
                  <v-select
                    v-model="row.sparepart_id"
                    clearable
                    density="compact"
                    hide-details
                    item-title="name"
                    item-value="id"
                    :items="spareparts"
                    variant="underlined"
                    @update:model-value="value => onRealizeItemSparepartSelect(row, value)"
                  />
                </td>

                <td style="min-width: 160px;">
                  <v-text-field v-model="row.description" density="compact" hide-details variant="underlined" />
                </td>

                <td style="width: 80px;">
                  <v-text-field
                    v-model.number="row.qty"
                    density="compact"
                    hide-details
                    type="number"
                    variant="underlined"
                  />
                </td>

                <td style="width: 120px;">
                  <v-text-field
                    v-model.number="row.unit_cost"
                    density="compact"
                    hide-details
                    type="number"
                    variant="underlined"
                  />
                </td>

                <td class="text-right">{{ formatCurrency((row.qty || 0) * (row.unit_cost || 0)) }}</td>

                <td v-if="workOrder.execution_type !== 'eksternal'" style="min-width: 180px;">
                  <v-file-input
                    v-model="row.photo"
                    accept="image/*"
                    density="compact"
                    :error="!!row.sparepart_id && !row.photo"
                    hide-details
                    :label="row.sparepart_id ? t('workOrder.usedPartPhotoRequired') : t('workOrder.usedPartPhotoOptional')"
                    prepend-icon=""
                    variant="underlined"
                  />
                </td>

                <td>
                  <v-btn icon="mdi-close" size="x-small" variant="text" @click="removeRealizeItemRow(index)" />
                </td>
              </tr>
            </tbody>

            <tfoot>
              <tr>
                <td class="font-weight-bold" colspan="4">{{ t('workOrder.totalCost') }}</td>
                <td class="text-right font-weight-bold">{{ formatCurrency(realizeItemsTotal) }}</td>
                <td v-if="workOrder.execution_type !== 'eksternal'" />
                <td />
              </tr>
            </tfoot>
          </v-table>

          <div v-else class="text-body-2 text-medium-emphasis">{{ t('workOrder.noCostDetails') }}</div>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="realizeDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="missingPhotoRows"
            :loading="realizing"
            variant="flat"
            @click="confirmRealize"
          >
            {{ t('workOrder.confirmRealize') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
