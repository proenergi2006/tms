<script setup>
  import { onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { approvalsApi } from '@/api/approval'
  import ConfirmDialog from '@/components/ConfirmDialog.vue'
  import StatusChip from '@/components/StatusChip.vue'
  import { useSidebarCountsStore } from '@/stores/sidebarCounts'
  import { formatCurrency, formatDateTime } from '@/utils/format'

  // Antrian Approval — Wireframe Document Bagian 2.4.
  const router = useRouter()
  const { t } = useI18n()
  const tab = ref('pending')
  const sidebarCounts = useSidebarCountsStore()

  const pending = ref([])
  const history = ref([])
  const loadingPending = ref(false)
  const loadingHistory = ref(false)

  async function loadPending () {
    loadingPending.value = true
    try {
      const { data } = await approvalsApi.pending()
      pending.value = data.data
    } finally {
      loadingPending.value = false
    }
  }

  async function loadHistory () {
    loadingHistory.value = true
    try {
      const { data } = await approvalsApi.history()
      history.value = data.data
    } finally {
      loadingHistory.value = false
    }
  }

  onMounted(() => {
    loadPending()
    loadHistory()
  })

  // -- Approve/Reject dari baris --
  const activeWorkOrder = ref(null)
  const approveDialog = ref(false)
  const rejectDialog = ref(false)
  const acting = ref(false)
  const notes = ref('')
  const reason = ref('')
  const errorMessage = ref(null)

  function openApprove (wo) {
    activeWorkOrder.value = wo
    notes.value = ''
    approveDialog.value = true
  }

  function openReject (wo) {
    activeWorkOrder.value = wo
    reason.value = ''
    rejectDialog.value = true
  }

  async function confirmApprove () {
    acting.value = true
    errorMessage.value = null
    try {
      await approvalsApi.approve(activeWorkOrder.value.id, notes.value)
      approveDialog.value = false
      await Promise.all([loadPending(), loadHistory()])
      sidebarCounts.refreshApprovalPending()
      sidebarCounts.refreshRequestsSubmitted()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('workOrder.approvalFailed')
    } finally {
      acting.value = false
    }
  }

  async function confirmReject () {
    acting.value = true
    errorMessage.value = null
    try {
      await approvalsApi.reject(activeWorkOrder.value.id, reason.value)
      rejectDialog.value = false
      await Promise.all([loadPending(), loadHistory()])
      sidebarCounts.refreshApprovalPending()
      sidebarCounts.refreshRequestsSubmitted()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('workOrder.rejectFailed')
    } finally {
      acting.value = false
    }
  }
</script>

<template>
  <div>
    <h1 class="text-h5 mb-4">{{ t('approvals.title') }}</h1>

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

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="pending">{{ t('approvals.myPending') }}</v-tab>
      <v-tab value="history">{{ t('approvals.history') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="pending">
        <v-card :loading="loadingPending">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('approvals.woNo') }}</th>
                <th>{{ t('approvals.fleet') }}</th>
                <th>{{ t('approvals.request') }}</th>
                <th class="text-right">{{ t('approvals.totalCost') }}</th>
                <th />
              </tr>
            </thead>

            <tbody>
              <tr v-for="(wo, index) in pending" :key="wo.id" style="cursor:pointer" @click="router.push(`/work-orders/${wo.id}`)">
                <td>{{ index + 1 }}</td>
                <td>{{ wo.wo_no }}</td>
                <td>{{ wo.request?.fleet?.plate_number ?? '-' }}</td>
                <td>{{ wo.request?.description }}</td>
                <td class="text-right">{{ formatCurrency(wo.total_cost) }}</td>

                <td class="text-right" @click.stop>
                  <v-btn color="success" size="small" variant="tonal" @click="openApprove(wo)">{{ t('approvals.approve') }}</v-btn>

                  <v-btn
                    class="ml-1"
                    color="error"
                    size="small"
                    variant="tonal"
                    @click="openReject(wo)"
                  >{{ t('approvals.reject') }}</v-btn>
                </td>
              </tr>

              <tr v-if="pending.length === 0">
                <td class="text-medium-emphasis" colspan="6">{{ t('approvals.noPending') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="history">
        <v-card :loading="loadingHistory">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('approvals.woNo') }}</th>
                <th>{{ t('approvals.fleet') }}</th>
                <th>{{ t('approvals.action') }}</th>
                <th>{{ t('approvals.time') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(log, index) in history"
                :key="log.id"
                style="cursor:pointer"
                @click="router.push(`/work-orders/${log.work_order_id}`)"
              >
                <td>{{ index + 1 }}</td>
                <td>{{ log.work_order?.wo_no }}</td>
                <td>{{ log.work_order?.fleet?.plate_number ?? '-' }}</td>
                <td><StatusChip :status="log.action === 'approve' ? 'completed' : 'rejected'" /></td>
                <td>{{ formatDateTime(log.approved_at) }}</td>
              </tr>

              <tr v-if="history.length === 0">
                <td class="text-medium-emphasis" colspan="5">{{ t('approvals.noHistory') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>
    </v-window>

    <ConfirmDialog
      v-model="approveDialog"
      v-model:reason="notes"
      confirm-color="success"
      :confirm-text="t('workOrder.approve')"
      :loading="acting"
      :message="t('approvals.confirmApproveMessage')"
      :reason-label="t('workOrder.notesLabel')"
      :title="t('workOrder.confirmApproveTitle')"
      @confirm="confirmApprove"
    />

    <ConfirmDialog
      v-model="rejectDialog"
      v-model:reason="reason"
      confirm-color="error"
      :confirm-text="t('workOrder.reject')"
      :loading="acting"
      :message="t('workOrder.confirmRejectMessage')"
      :reason-label="t('workOrder.rejectReasonLabel')"
      require-reason
      :title="t('workOrder.confirmRejectTitle')"
      @confirm="confirmReject"
    />
  </div>
</template>
