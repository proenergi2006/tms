<script setup>
  import { computed, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { reportsApi } from '@/api/fleetHistory'
  import { branchesApi } from '@/api/masterData'
  import { useAuthStore } from '@/stores/auth'
  import { downloadBlob } from '@/utils/download'
  import { formatCurrency } from '@/utils/format'

  // Laporan Profitabilitas Armada — Wireframe Document Bagian 2.7.
  const auth = useAuthStore()
  const { t } = useI18n()
  const branches = ref([])
  const rows = ref([])
  const loading = ref(false)
  const tab = ref('profitabilitas')

  const filters = ref({ branch_id: null, period_from: '', period_to: '' })

  async function load () {
    loading.value = true
    try {
      const { data } = await reportsApi.fleetProfitability({
        branch_id: filters.value.branch_id || undefined,
        period_from: filters.value.period_from || undefined,
        period_to: filters.value.period_to || undefined,
      })
      rows.value = data.data
    } finally {
      loading.value = false
    }
  }

  // -- Biaya Perawatan & Rekomendasi Ganti Unit --
  // Beda sengaja dari tab Profitabilitas: fokus murni biaya PERBAIKAN
  // (maintenance_history), bukan seluruh biaya operasional (GPS/Asuransi/
  // Cicilan ikut di tab Profitabilitas), supaya "unit paling boros" dan
  // rasio terhadap nilai unit tidak bias oleh biaya non-perbaikan.
  const maintenanceCostRows = ref([])
  const loadingMaintenanceCost = ref(false)

  async function loadMaintenanceCost () {
    loadingMaintenanceCost.value = true
    try {
      const { data } = await reportsApi.fleetMaintenanceCost({
        branch_id: filters.value.branch_id || undefined,
        period_from: filters.value.period_from || undefined,
        period_to: filters.value.period_to || undefined,
      })
      maintenanceCostRows.value = data.data
    } finally {
      loadingMaintenanceCost.value = false
    }
  }

  async function loadAll () {
    await Promise.all([load(), loadMaintenanceCost()])
  }

  onMounted(async () => {
    const { data } = await branchesApi.list({ per_page: 50 })
    branches.value = data.data
    await loadAll()
  })

  const summary = computed(() => ({
    totalCost: rows.value.reduce((sum, r) => sum + r.total_cost, 0),
    totalRevenue: rows.value.reduce((sum, r) => sum + r.total_revenue, 0),
    totalProfit: rows.value.reduce((sum, r) => sum + r.profit, 0),
  }))

  const chartOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: rows.value.map(r => r.plate_number) },
    colors: [({ value }) => (value >= 0 ? '#22C55E' : '#EF4444')],
    legend: { show: false },
  }))
  const chartSeries = computed(() => [{ name: t('reports.profitLossSeries'), data: rows.value.map(r => r.profit) }])

  const exporting = ref(false)

  async function exportExcel () {
    exporting.value = true
    try {
      const { data } = await reportsApi.fleetProfitabilityExport({
        branch_id: filters.value.branch_id || undefined,
        period_from: filters.value.period_from || undefined,
        period_to: filters.value.period_to || undefined,
      })
      downloadBlob(data, 'laporan-profitabilitas-armada.xlsx')
    } finally {
      exporting.value = false
    }
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4 flex-wrap ga-2">
      <h1 class="text-h5">{{ t('reports.title') }}</h1>
      <v-spacer />
      <v-btn :loading="exporting" prepend-icon="mdi-file-excel" variant="tonal" @click="exportExcel">{{ t('common.export') }}</v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-text>
        <v-row dense>
          <v-col v-if="!auth.isBranchScoped" cols="12" md="3">
            <v-select
              v-model="filters.branch_id"
              clearable
              density="compact"
              item-title="name"
              item-value="id"
              :items="branches"
              :label="t('common.branch')"
              @update:model-value="loadAll"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.period_from"
              density="compact"
              :label="t('reports.periodFrom')"
              placeholder="2026-01"
              @change="loadAll"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.period_to"
              density="compact"
              :label="t('reports.periodTo')"
              placeholder="2026-07"
              @change="loadAll"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="profitabilitas">{{ t('reports.tabProfitability') }}</v-tab>
      <v-tab value="biaya-perawatan">{{ t('reports.tabMaintenanceCost') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="profitabilitas">
        <v-row class="mb-2">
          <v-col cols="12" md="4">
            <v-card><v-card-text>
              <div class="text-caption text-medium-emphasis">{{ t('reports.totalCost') }}</div>
              <div class="text-h6">{{ formatCurrency(summary.totalCost) }}</div>
            </v-card-text></v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card><v-card-text>
              <div class="text-caption text-medium-emphasis">{{ t('reports.totalRevenue') }}</div>
              <div class="text-h6">{{ formatCurrency(summary.totalRevenue) }}</div>
            </v-card-text></v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card><v-card-text>
              <div class="text-caption text-medium-emphasis">{{ t('reports.totalProfit') }}</div>

              <div class="text-h6" :class="summary.totalProfit >= 0 ? 'text-success' : 'text-error'">
                {{ formatCurrency(summary.totalProfit) }}
              </div>
            </v-card-text></v-card>
          </v-col>
        </v-row>

        <v-card class="mb-4" :loading="loading">
          <v-card-text>
            <apexchart
              v-if="rows.length > 0"
              height="320"
              :options="chartOptions"
              :series="chartSeries"
              type="bar"
            />

            <v-alert v-else type="info" variant="tonal">{{ t('reports.noDataFilter') }}</v-alert>
          </v-card-text>
        </v-card>

        <v-card>
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th><th>{{ t('reports.fleet') }}</th><th>{{ t('common.branch') }}</th><th class="text-right">{{ t('reports.totalCost') }}</th><th class="text-right">{{ t('reports.totalRevenue') }}</th><th class="text-right">{{ t('fleets.profitLoss') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in rows" :key="row.fleet_id">
                <td>{{ index + 1 }}</td>
                <td>{{ row.plate_number }} ({{ row.fleet_type }})</td>
                <td>{{ row.branch }}</td>
                <td class="text-right">{{ formatCurrency(row.total_cost) }}</td>
                <td class="text-right">{{ formatCurrency(row.total_revenue) }}</td>
                <td class="text-right" :class="row.profit >= 0 ? 'text-success' : 'text-error'">{{ formatCurrency(row.profit) }}</td>
              </tr>

              <tr v-if="rows.length === 0"><td class="text-medium-emphasis" colspan="6">{{ t('reports.noData') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="biaya-perawatan">
        <v-alert class="mb-4" type="info" variant="tonal">{{ t('reports.maintenanceCostHint') }}</v-alert>

        <v-card :loading="loadingMaintenanceCost">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('reports.fleet') }}</th>
                <th>{{ t('common.branch') }}</th>
                <th class="text-right">{{ t('fleets.purchasePrice') }}</th>
                <th class="text-right">{{ t('reports.totalRepairCost') }}</th>
                <th class="text-right">{{ t('reports.repairCount') }}</th>
                <th class="text-right">{{ t('reports.costRatio') }}</th>
                <th />
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in maintenanceCostRows" :key="row.fleet_id">
                <td>{{ index + 1 }}</td>
                <td>{{ row.plate_number }} ({{ row.fleet_type }})</td>
                <td>{{ row.branch }}</td>
                <td class="text-right">{{ row.purchase_price ? formatCurrency(row.purchase_price) : '-' }}</td>
                <td class="text-right" :class="row.replace_recommended ? 'text-error font-weight-medium' : ''">{{ formatCurrency(row.total_repair_cost) }}</td>
                <td class="text-right">{{ row.repair_count }}</td>
                <td class="text-right">{{ row.cost_ratio !== null ? `${Math.round(row.cost_ratio * 100)}%` : '-' }}</td>

                <td>
                  <v-chip
                    v-if="row.replace_recommended"
                    color="error"
                    prepend-icon="mdi-alert-octagon-outline"
                    size="small"
                    variant="tonal"
                  >
                    {{ t('reports.replaceRecommended') }}
                  </v-chip>
                </td>
              </tr>

              <tr v-if="maintenanceCostRows.length === 0">
                <td class="text-medium-emphasis" colspan="8">{{ t('reports.noData') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>
    </v-window>
  </div>
</template>
