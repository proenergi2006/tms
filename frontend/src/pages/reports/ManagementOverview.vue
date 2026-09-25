<script setup>
  import { computed, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { approvalsApi } from '@/api/approval'
  import { reportsApi } from '@/api/fleetHistory'
  import { formatCurrency, formatDateTime, formatDuration } from '@/utils/format'

  // Ringkasan Manajemen — 5 kebutuhan yang sebelumnya tidak ada satu pun
  // tempatnya di TMS: (1) perbandingan lintas-cabang dalam satu layar,
  // (2) tren profit/cost bulanan seluruh perusahaan (bukan snapshot sesaat),
  // (3) visibilitas antrean approval READ-ONLY (Manajemen bukan approver,
  // tidak punya approval.act), (4) ringkasan insiden keselamatan/perilaku
  // driver lintas-cabang, (5) ringkasan belanja sparepart per kategori.
  // Semua tab digerbang permission report.view (sudah dimiliki Manajemen &
  // Operational Manager), sama seperti laporan lain — lihat ApprovalController
  // ::overview(), BranchComparisonReportController, DriverIncidentReportController,
  // SparepartSpendReportController di backend.
  const { t } = useI18n()
  const tab = ref('branch-comparison')
  const filters = ref({ period_from: '', period_to: '' })

  const branchRows = ref([])
  const loadingBranch = ref(false)
  async function loadBranchComparison () {
    loadingBranch.value = true
    try {
      const { data } = await reportsApi.branchComparison({
        period_from: filters.value.period_from || undefined,
        period_to: filters.value.period_to || undefined,
      })
      branchRows.value = data.data
    } finally {
      loadingBranch.value = false
    }
  }

  const trendRows = ref([])
  const loadingTrend = ref(false)
  async function loadTrend () {
    loadingTrend.value = true
    try {
      const { data } = await reportsApi.companyTrend({
        period_from: filters.value.period_from || undefined,
        period_to: filters.value.period_to || undefined,
      })
      trendRows.value = data.data
    } finally {
      loadingTrend.value = false
    }
  }

  const trendChartOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: trendRows.value.map(r => r.period) },
    colors: ['#22C55E', '#EF4444'],
    legend: { position: 'top' },
  }))
  const trendChartSeries = computed(() => [
    { name: t('reports.totalRevenue'), data: trendRows.value.map(r => r.total_revenue) },
    { name: t('reports.totalCost'), data: trendRows.value.map(r => r.total_cost) },
  ])

  const approvalRows = ref([])
  const loadingApproval = ref(false)
  async function loadApprovalOverview () {
    loadingApproval.value = true
    try {
      const { data } = await approvalsApi.overview()
      approvalRows.value = data.data
    } finally {
      loadingApproval.value = false
    }
  }

  // Ambang "sudah lama nunggu" murni sinyal visual (highlight merah di
  // tabel) — bukan SLA resmi yang didefinisikan di mana pun, karena approval
  // workflow saat ini memang tidak punya target waktu per tahap.
  const LONG_WAIT_MINUTES = 48 * 60

  const incidentTotals = ref({})
  const incidentByBranch = ref([])
  const loadingIncidents = ref(false)
  async function loadDriverIncidents () {
    loadingIncidents.value = true
    try {
      const { data } = await reportsApi.driverIncidents({
        date_from: filters.value.period_from ? `${filters.value.period_from}-01` : undefined,
        date_to: filters.value.period_to ? `${filters.value.period_to}-31` : undefined,
      })
      incidentTotals.value = data.data.totals
      incidentByBranch.value = data.data.by_branch
    } finally {
      loadingIncidents.value = false
    }
  }

  const incidentTypes = [
    { key: 'pod_sos_alert', label: 'reports.incidentSos', icon: 'mdi-alert-octagon-outline', color: 'error' },
    { key: 'pod_behavior_harsh', label: 'reports.incidentHarsh', icon: 'mdi-steering', color: 'warning' },
    { key: 'pod_behavior_fatigue', label: 'reports.incidentFatigue', icon: 'mdi-sleep', color: 'warning' },
    { key: 'pod_behavior_rest', label: 'reports.incidentRest', icon: 'mdi-bed-clock', color: 'warning' },
    { key: 'pod_photo_location', label: 'reports.incidentPhotoLocation', icon: 'mdi-map-marker-alert-outline', color: 'info' },
  ]

  const spendTotal = ref(0)
  const spendByCategory = ref([])
  const spendByBranch = ref([])
  const loadingSpend = ref(false)
  async function loadSparepartSpend () {
    loadingSpend.value = true
    try {
      const { data } = await reportsApi.sparepartSpend({
        period_from: filters.value.period_from || undefined,
        period_to: filters.value.period_to || undefined,
      })
      spendTotal.value = data.data.total_spend
      spendByCategory.value = data.data.by_category
      spendByBranch.value = data.data.by_branch
    } finally {
      loadingSpend.value = false
    }
  }

  // Approval Overview sengaja TIDAK ikut filter periode (selalu snapshot
  // "sekarang", bukan riwayat) — di-load sekali saja, bukan lewat loadAll().
  async function loadAll () {
    await Promise.all([loadBranchComparison(), loadTrend(), loadDriverIncidents(), loadSparepartSpend()])
  }

  onMounted(async () => {
    await Promise.all([loadAll(), loadApprovalOverview()])
  })
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4 flex-wrap ga-2">
      <h1 class="text-h5">{{ t('reports.managementOverviewTitle') }}</h1>
      <v-spacer />

      <v-text-field
        v-model="filters.period_from"
        density="compact"
        hide-details
        :label="t('reports.periodFrom')"
        max-width="160"
        placeholder="2026-01"
        @change="loadAll"
      />

      <v-text-field
        v-model="filters.period_to"
        density="compact"
        hide-details
        :label="t('reports.periodTo')"
        max-width="160"
        placeholder="2026-07"
        @change="loadAll"
      />
    </div>

    <div class="text-medium-emphasis mb-4">{{ t('reports.managementOverviewSubtitle') }}</div>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="branch-comparison">{{ t('reports.tabBranchComparison') }}</v-tab>
      <v-tab value="trend">{{ t('reports.tabTrend') }}</v-tab>
      <v-tab value="approval-overview">{{ t('reports.tabApprovalOverview') }}</v-tab>
      <v-tab value="incidents">{{ t('reports.tabIncidents') }}</v-tab>
      <v-tab value="sparepart-spend">{{ t('reports.tabSparepartSpend') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="branch-comparison">
        <v-alert class="mb-4" type="info" variant="tonal">{{ t('reports.branchComparisonHint') }}</v-alert>

        <v-card :loading="loadingBranch">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('common.branch') }}</th>
                <th class="text-right">{{ t('reports.totalCost') }}</th>
                <th class="text-right">{{ t('reports.totalRevenue') }}</th>
                <th class="text-right">{{ t('fleets.profitLoss') }}</th>
                <th class="text-right">{{ t('reports.legalExpired') }}</th>
                <th class="text-right">{{ t('reports.legalExpiring') }}</th>
                <th class="text-right">{{ t('reports.criticalStock') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in branchRows" :key="row.branch_id">
                <td>{{ index + 1 }}</td>
                <td>{{ row.branch }}</td>
                <td class="text-right">{{ formatCurrency(row.total_cost) }}</td>
                <td class="text-right">{{ formatCurrency(row.total_revenue) }}</td>
                <td class="text-right" :class="row.profit >= 0 ? 'text-success' : 'text-error'">{{ formatCurrency(row.profit) }}</td>

                <td class="text-right" :class="row.legal_expired_count > 0 ? 'text-error font-weight-medium' : ''">
                  {{ row.legal_expired_count }}
                </td>

                <td class="text-right" :class="row.legal_expiring_count > 0 ? 'text-warning font-weight-medium' : ''">
                  {{ row.legal_expiring_count }}
                </td>

                <td class="text-right" :class="row.critical_stock_count > 0 ? 'text-error font-weight-medium' : ''">
                  {{ row.critical_stock_count }}
                </td>
              </tr>

              <tr v-if="branchRows.length === 0 && !loadingBranch">
                <td class="text-medium-emphasis" colspan="8">{{ t('reports.noData') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="trend">
        <v-card :loading="loadingTrend">
          <v-card-text>
            <apexchart
              v-if="trendRows.length > 0"
              height="320"
              :options="trendChartOptions"
              :series="trendChartSeries"
              type="bar"
            />

            <v-alert v-else type="info" variant="tonal">{{ t('reports.noDataFilter') }}</v-alert>
          </v-card-text>
        </v-card>

        <v-card class="mt-4">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.period') }}</th>
                <th class="text-right">{{ t('reports.totalCost') }}</th>
                <th class="text-right">{{ t('reports.totalRevenue') }}</th>
                <th class="text-right">{{ t('fleets.profitLoss') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="row in trendRows" :key="row.period">
                <td>{{ row.period }}</td>
                <td class="text-right">{{ formatCurrency(row.total_cost) }}</td>
                <td class="text-right">{{ formatCurrency(row.total_revenue) }}</td>
                <td class="text-right" :class="row.profit >= 0 ? 'text-success' : 'text-error'">{{ formatCurrency(row.profit) }}</td>
              </tr>

              <tr v-if="trendRows.length === 0"><td class="text-medium-emphasis" colspan="4">{{ t('reports.noData') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="approval-overview">
        <v-alert class="mb-4" type="info" variant="tonal">{{ t('reports.approvalOverviewHint') }}</v-alert>

        <v-card :loading="loadingApproval">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('approvals.woNo') }}</th>
                <th>{{ t('fleets.plateNumber') }}</th>
                <th>{{ t('common.branch') }}</th>
                <th>{{ t('reports.approvalStep') }}</th>
                <th>{{ t('workOrder.requestedBy') }}</th>
                <th>{{ t('reports.waitingSince') }}</th>
                <th class="text-right">{{ t('reports.waitingDuration') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in approvalRows" :key="row.id">
                <td>{{ index + 1 }}</td>
                <td>{{ row.wo_no }}</td>
                <td>{{ row.plate_number ?? '-' }}</td>
                <td>{{ row.branch ?? '-' }}</td>
                <td>{{ row.approval_step ?? '-' }}</td>
                <td>{{ row.requested_by ?? '-' }}</td>
                <td>{{ formatDateTime(row.waiting_since) }}</td>

                <td class="text-right" :class="row.waiting_minutes >= LONG_WAIT_MINUTES ? 'text-error font-weight-medium' : ''">
                  {{ formatDuration(row.waiting_minutes) }}
                </td>
              </tr>

              <tr v-if="approvalRows.length === 0 && !loadingApproval">
                <td class="text-medium-emphasis" colspan="8">{{ t('reports.noPendingApprovals') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="incidents">
        <v-alert class="mb-4" type="info" variant="tonal">{{ t('reports.incidentsHint') }}</v-alert>

        <v-row class="mb-2">
          <v-col
            v-for="type in incidentTypes"
            :key="type.key"
            cols="6"
            md="2"
            sm="4"
          >
            <v-card :loading="loadingIncidents">
              <v-card-text>
                <v-icon :color="type.color" :icon="type.icon" size="small" start />
                <div class="text-caption text-medium-emphasis d-inline">{{ t(type.label) }}</div>
                <div class="text-h6">{{ incidentTotals[type.key] ?? 0 }}</div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-card :loading="loadingIncidents">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('common.branch') }}</th>
                <th v-for="type in incidentTypes" :key="type.key" class="text-right">{{ t(type.label) }}</th>
                <th class="text-right">{{ t('common.total') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in incidentByBranch" :key="row.branch_id">
                <td>{{ index + 1 }}</td>
                <td>{{ row.branch }}</td>
                <td v-for="type in incidentTypes" :key="type.key" class="text-right">{{ row[type.key] }}</td>
                <td class="text-right font-weight-medium">{{ row.total }}</td>
              </tr>

              <tr v-if="incidentByBranch.length === 0 && !loadingIncidents">
                <td class="text-medium-emphasis" :colspan="incidentTypes.length + 3">{{ t('reports.noData') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="sparepart-spend">
        <v-row class="mb-2">
          <v-col cols="12" md="4">
            <v-card><v-card-text>
              <div class="text-caption text-medium-emphasis">{{ t('reports.totalSparepartSpend') }}</div>
              <div class="text-h6">{{ formatCurrency(spendTotal) }}</div>
            </v-card-text></v-card>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" md="6">
            <v-card :loading="loadingSpend">
              <v-card-title>{{ t('reports.spendByCategory') }}</v-card-title>

              <v-table>
                <thead><tr><th>{{ t('masterData.category') }}</th><th class="text-right">{{ t('common.total') }}</th></tr></thead>

                <tbody>
                  <tr v-for="row in spendByCategory" :key="row.category">
                    <td>{{ row.category ? t(`enums.sparepartCategory.${row.category}`) : '-' }}</td>
                    <td class="text-right">{{ formatCurrency(row.total) }}</td>
                  </tr>

                  <tr v-if="spendByCategory.length === 0"><td class="text-medium-emphasis" colspan="2">{{ t('reports.noData') }}</td></tr>
                </tbody>
              </v-table>
            </v-card>
          </v-col>

          <v-col cols="12" md="6">
            <v-card :loading="loadingSpend">
              <v-card-title>{{ t('common.branch') }}</v-card-title>

              <v-table>
                <thead><tr><th>{{ t('common.branch') }}</th><th class="text-right">{{ t('common.total') }}</th></tr></thead>

                <tbody>
                  <tr v-for="row in spendByBranch" :key="row.branch_id">
                    <td>{{ row.branch }}</td>
                    <td class="text-right">{{ formatCurrency(row.total) }}</td>
                  </tr>

                  <tr v-if="spendByBranch.length === 0"><td class="text-medium-emphasis" colspan="2">{{ t('reports.noData') }}</td></tr>
                </tbody>
              </v-table>
            </v-card>
          </v-col>
        </v-row>
      </v-window-item>
    </v-window>
  </div>
</template>
