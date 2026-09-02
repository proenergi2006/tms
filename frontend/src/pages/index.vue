<script setup>
  import { computed, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { approvalsApi } from '@/api/approval'
  import { reportsApi } from '@/api/fleetHistory'
  import { requestsApi, workOrdersApi } from '@/api/maintenance'
  import { fleetsApi } from '@/api/masterData'
  import StatusChip from '@/components/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'
  import { formatCurrency, formatDuration } from '@/utils/format'

  // Dashboard — ringkasan operasional & finansial (Wireframe Document 2.1).
  // Setiap seksi hanya diambil/ditampilkan bila pengguna punya permission
  // terkait (RBAC — Architecture Document Bagian 7.2), agar role yang tidak
  // terlibat data tersebut (mis. Admin IT/GA) tidak menerima 403 dari API
  // yang memang bukan untuk mereka.
  const router = useRouter()
  const auth = useAuthStore()
  const { t } = useI18n()
  const loading = ref(true)

  const totalRequests = ref(0)
  const pendingApprovalCount = ref(0)
  const legalWarnings = ref([])
  const profitability = ref([])
  const fleetStatus = ref({ ready: [], breakdown: [], service: [], nonaktif: [] })
  const reliability = ref(null)

  // -- Ringkasan khusus SA: pengajuan/WO cabangnya sendiri (branch-scoped
  // lewat WorkOrderController::index()) yang butuh tindak lanjut SA — mulai
  // dikerjakan (approval selesai tapi status masih waiting) atau realisasi
  // & selesaikan (status on_progress). Dihitung client-side dari satu
  // panggilan work-orders/list, bukan endpoint terpisah. --
  const myWorkOrders = ref([])

  const priorityColor = priority => ({ high: 'error', medium: 'warning', low: 'default' }[priority] ?? 'default')

  const waitingApprovalCount = computed(() => myWorkOrders.value.filter(wo => wo.approval_status === 'submitted').length)
  const needsAction = computed(() => myWorkOrders.value
    .filter(wo => wo.approval_status === 'completed' && wo.status !== 'finished')
    .toSorted((a, b) => (a.status === 'waiting' ? -1 : 1)))
  const finishedThisMonthCount = computed(() => {
    const ym = new Date().toISOString().slice(0, 7)
    return myWorkOrders.value.filter(wo => wo.status === 'finished' && wo.finished_at?.slice(0, 7) === ym).length
  })

  const currentMonthProfit = computed(() => profitability.value.reduce((sum, row) => sum + row.profit, 0))

  const chartSeries = computed(() => [
    { name: 'Pendapatan', data: profitability.value.map(row => row.total_revenue) },
    { name: 'Biaya', data: profitability.value.map(row => row.total_cost) },
  ])
  const chartOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: profitability.value.map(row => row.plate_number) },
    colors: ['#22C55E', '#EF4444'],
    legend: { position: 'top' },
  }))

  onMounted(async () => {
    try {
      const period = new Date().toISOString().slice(0, 7)

      await Promise.all([
        auth.hasPermission('request.view')
          ? requestsApi.list({ per_page: 1 }).then(({ data }) => {
            totalRequests.value = data.meta?.total ?? data.data.length
          })
          : null,
        auth.hasPermission('approval.view')
          ? approvalsApi.pending().then(({ data }) => {
            pendingApprovalCount.value = data.data.length
          })
          : null,
        auth.hasPermission('report.view')
          ? reportsApi.fleetProfitability({ period_from: period, period_to: period }).then(({ data }) => {
            profitability.value = data.data
          })
          : null,
        auth.hasPermission('fleet.view')
          ? fleetsApi.legalWarnings().then(({ data }) => {
            legalWarnings.value = data.data
          })
          : null,
        auth.hasPermission('fleet.view')
          ? fleetsApi.statusSummary().then(({ data }) => {
            fleetStatus.value = data.data
          })
          : null,
        auth.hasPermission('fleet.view')
          ? fleetsApi.reliabilitySummary().then(({ data }) => {
            reliability.value = data.data
          })
          : null,
        auth.role === 'sa'
          ? workOrdersApi.list({ per_page: 100 }).then(({ data }) => {
            myWorkOrders.value = data.data
          })
          : null,
      ])
    } finally {
      loading.value = false
    }
  })

  const kpis = computed(() => {
    const list = []
    // SA punya seksi ringkasan sendiri (lihat template) yang sudah
    // mencakup Total Pengajuan dengan tampilan lebih kaya — tidak perlu
    // duplikat di baris KPI generik ini.
    if (auth.hasPermission('request.view') && auth.role !== 'sa') {
      list.push({ label: t('dashboard.totalRequests'), value: totalRequests.value, icon: 'mdi-file-document-outline', to: '/requests' })
    }
    if (auth.hasPermission('approval.view')) {
      list.push({ label: t('dashboard.pendingApproval'), value: pendingApprovalCount.value, icon: 'mdi-check-decagram-outline', to: '/approvals' })
    }
    if (auth.hasPermission('fleet.view')) {
      list.push({ label: t('dashboard.legalExpiring'), value: legalWarnings.value.length, icon: 'mdi-alert-outline', to: '/fleets' })
    }
    if (auth.hasPermission('report.view')) {
      list.push({ label: t('dashboard.profitThisMonth'), value: formatCurrency(currentMonthProfit.value), icon: 'mdi-chart-line', to: '/reports/fleet-profitability' })
    }

    return list
  })
</script>

<template>
  <div>
    <h1 class="text-h5 mb-4">{{ t('dashboard.title') }}</h1>

    <template v-if="auth.role === 'sa'">
      <v-card class="mb-4 sa-hero" :loading="loading">
        <v-card-text class="d-flex flex-wrap align-center ga-4 py-6">
          <v-avatar color="white" size="56" variant="flat">
            <v-icon color="primary" icon="mdi-clipboard-text-outline" size="30" />
          </v-avatar>

          <div class="flex-grow-1">
            <div class="text-h6 text-white">{{ t('dashboard.saHeroTitle') }}</div>
            <div class="text-body-2 sa-hero-subtitle">{{ t('dashboard.saHeroSubtitle') }}</div>
          </div>

          <v-btn
            color="white"
            prepend-icon="mdi-plus"
            size="large"
            variant="flat"
            @click="router.push('/requests')"
          >
            {{ t('requests.create') }}
          </v-btn>
        </v-card-text>
      </v-card>

      <v-row>
        <v-col cols="6" md="3">
          <v-card class="pa-4" :loading="loading" :to="'/requests'" variant="tonal">
            <v-icon color="primary" icon="mdi-file-document-multiple-outline" size="28" />
            <div class="text-h5 mt-2">{{ myWorkOrders.length }}</div>
            <div class="text-caption text-medium-emphasis">{{ t('dashboard.totalRequests') }}</div>
          </v-card>
        </v-col>

        <v-col cols="6" md="3">
          <v-card
            class="pa-4"
            color="warning"
            :loading="loading"
            :to="'/requests'"
            variant="tonal"
          >
            <v-icon color="warning" icon="mdi-timer-sand" size="28" />
            <div class="text-h5 mt-2">{{ waitingApprovalCount }}</div>
            <div class="text-caption text-medium-emphasis">{{ t('dashboard.saWaitingApproval') }}</div>
          </v-card>
        </v-col>

        <v-col cols="6" md="3">
          <v-card
            class="pa-4"
            color="info"
            :loading="loading"
            :to="'/requests'"
            variant="tonal"
          >
            <v-icon color="info" icon="mdi-progress-wrench" size="28" />
            <div class="text-h5 mt-2">{{ needsAction.length }}</div>
            <div class="text-caption text-medium-emphasis">{{ t('dashboard.saNeedsAction') }}</div>
          </v-card>
        </v-col>

        <v-col cols="6" md="3">
          <v-card
            class="pa-4"
            color="success"
            :loading="loading"
            :to="'/requests'"
            variant="tonal"
          >
            <v-icon color="success" icon="mdi-check-circle-outline" size="28" />
            <div class="text-h5 mt-2">{{ finishedThisMonthCount }}</div>
            <div class="text-caption text-medium-emphasis">{{ t('dashboard.saFinishedThisMonth') }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-card class="mt-4" :loading="loading">
        <v-card-title>{{ t('dashboard.saNeedsActionTitle') }}</v-card-title>

        <v-list v-if="needsAction.length > 0" lines="two">
          <v-list-item
            v-for="wo in needsAction"
            :key="wo.id"
            :subtitle="wo.request?.description"
            :to="`/work-orders/${wo.id}`"
          >
            <template #prepend>
              <v-avatar :color="wo.status === 'waiting' ? 'info' : 'warning'" variant="tonal">
                <v-icon :icon="wo.status === 'waiting' ? 'mdi-play-circle-outline' : 'mdi-wrench-clock'" />
              </v-avatar>
            </template>

            <template #title>
              <span class="font-weight-medium">{{ wo.wo_no }}</span>

              <v-chip class="ml-2" :color="priorityColor(wo.request?.priority)" size="x-small" variant="tonal">
                {{ t(`enums.priority.${wo.request?.priority}`) }}
              </v-chip>
            </template>

            <template #append>
              <StatusChip :status="wo.status" />
            </template>
          </v-list-item>
        </v-list>

        <v-card-text v-else>
          <v-alert type="success" variant="tonal">{{ t('dashboard.saNoAction') }}</v-alert>
        </v-card-text>
      </v-card>
    </template>

    <v-row v-if="kpis.length > 0">
      <v-col
        v-for="kpi in kpis"
        :key="kpi.label"
        cols="12"
        md="3"
        sm="6"
      >
        <v-card :loading="loading" @click="router.push(kpi.to)">
          <v-card-text class="d-flex align-center">
            <v-avatar class="mr-4" color="primary" variant="tonal">
              <v-icon :icon="kpi.icon" />
            </v-avatar>

            <div>
              <div class="text-caption text-medium-emphasis">{{ kpi.label }}</div>
              <div class="text-h6">{{ kpi.value }}</div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-if="auth.hasPermission('fleet.view')" class="mt-2">
      <v-col cols="12">
        <v-card :loading="loading">
          <v-card-title>{{ t('dashboard.fleetStatus') }}</v-card-title>

          <v-card-text>
            <v-row dense>
              <v-col
                v-for="bucket in ['ready', 'breakdown', 'service', 'nonaktif']"
                :key="bucket"
                cols="6"
                md="3"
              >
                <v-card
                  class="pa-3"
                  :color="{ ready: 'success', breakdown: 'error', service: 'warning', nonaktif: undefined }[bucket]"
                  variant="tonal"
                >
                  <div class="text-caption">{{ t(`dashboard.fleetStatusBuckets.${bucket}`) }}</div>
                  <div class="text-h5 mb-2">{{ fleetStatus[bucket]?.length ?? 0 }}</div>

                  <div v-for="f in fleetStatus[bucket]?.slice(0, 5)" :key="f.id" class="text-caption">
                    {{ f.plate_number }} ({{ f.branch }})
                  </div>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-if="auth.hasPermission('fleet.view') && reliability" class="mt-2">
      <v-col cols="12">
        <v-card :loading="loading">
          <v-card-title>{{ t('dashboard.reliability') }}</v-card-title>

          <v-card-text>
            <v-row dense>
              <v-col cols="12" md="4">
                <v-card class="pa-3" variant="tonal">
                  <div class="text-caption">{{ t('dashboard.availabilityRate') }}</div>

                  <div class="text-h5" :class="reliability.aggregate.availability_rate >= 90 ? 'text-success' : 'text-error'">
                    {{ reliability.aggregate.availability_rate !== null ? `${reliability.aggregate.availability_rate}%` : '-' }}
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" md="4">
                <v-card class="pa-3" variant="tonal">
                  <div class="text-caption">{{ t('dashboard.mtbf') }}</div>
                  <div class="text-h5">{{ formatDuration(reliability.aggregate.mtbf_minutes) }}</div>
                </v-card>
              </v-col>

              <v-col cols="12" md="4">
                <v-card class="pa-3" variant="tonal">
                  <div class="text-caption">{{ t('dashboard.mttr') }}</div>
                  <div class="text-h5">{{ formatDuration(reliability.aggregate.mttr_minutes) }}</div>
                </v-card>
              </v-col>
            </v-row>

            <div v-if="reliability.fleets.some(f => f.availability_rate < 100)" class="mt-4">
              <div class="text-subtitle-2 mb-2">{{ t('dashboard.worstAvailability') }}</div>

              <v-list density="compact">
                <v-list-item
                  v-for="f in reliability.fleets.filter(f => f.availability_rate < 100).slice(0, 5)"
                  :key="f.fleet_id"
                  :subtitle="`${t('dashboard.availabilityRate')}: ${f.availability_rate}% — ${f.branch}`"
                  :title="f.plate_number"
                  :to="`/fleets/${f.fleet_id}`"
                />
              </v-list>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-if="auth.hasPermission('report.view') || auth.hasPermission('fleet.view')" class="mt-2">
      <v-col v-if="auth.hasPermission('report.view')" cols="12" md="8">
        <v-card :loading="loading">
          <v-card-title>{{ t('dashboard.profitLossChart') }}</v-card-title>

          <v-card-text>
            <apexchart
              v-if="profitability.length > 0"
              height="320"
              :options="chartOptions"
              :series="chartSeries"
              type="bar"
            />

            <v-alert v-else type="info" variant="tonal">{{ t('dashboard.noProfitData') }}</v-alert>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="auth.hasPermission('fleet.view')" cols="12" md="4">
        <v-card :loading="loading">
          <v-card-title>{{ t('dashboard.legalWarnings') }}</v-card-title>

          <v-list v-if="legalWarnings.length > 0" density="compact">
            <v-list-item
              v-for="doc in legalWarnings"
              :key="doc.id"
              :subtitle="t('dashboard.dueOn', { date: doc.expiry_date })"
              :title="`${doc.fleet_plate} — ${doc.doc_type}`"
              :to="'/fleets'"
            >
              <template #prepend>
                <v-icon color="warning" icon="mdi-alert-outline" />
              </template>
            </v-list-item>
          </v-list>

          <v-card-text v-else>
            <v-alert type="success" variant="tonal">{{ t('dashboard.noLegalWarnings') }}</v-alert>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-alert v-if="kpis.length === 0 && auth.role !== 'sa'" type="info" variant="tonal">
      {{ t('dashboard.noKpis') }}
    </v-alert>
  </div>
</template>

<style scoped>
.sa-hero {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-primary-darken-1)) 100%);
}

.sa-hero-subtitle {
  color: rgba(255, 255, 255, 0.85);
}
</style>
