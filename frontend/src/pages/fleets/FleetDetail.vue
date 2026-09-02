<script setup>
  import { onMounted, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { fleetHistoryApi } from '@/api/fleetHistory'
  import { branchesApi, fleetsApi } from '@/api/masterData'
  import StatusChip from '@/components/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'
  import { formatCurrency, formatDate, formatDuration } from '@/utils/format'

  // Detail Armada — Wireframe Document Bagian 2.5 (tab Profit-Loss + tab lain).
  const route = useRoute()
  const auth = useAuthStore()
  const { t } = useI18n()
  const canManage = auth.hasPermission('master-data.manage')
  const fleetId = route.params.id

  const fleet = ref(null)
  const branches = ref([])
  const tab = ref('riwayat')

  const maintenanceHistory = ref([])
  const legalDocs = ref([])
  const fuelLogs = ref([])
  const operationalCosts = ref([])
  const revenues = ref([])
  const profitability = ref(null)
  const costPerKm = ref([])
  const downtimes = ref(null)
  const components = ref([])
  const loadingTab = ref(false)

  async function loadFleet () {
    const { data } = await fleetsApi.get(fleetId)
    fleet.value = data.data
  }

  async function loadTab (name) {
    loadingTab.value = true
    try {
      switch (name) {
        case 'riwayat': {
          const { data } = await fleetHistoryApi.maintenanceHistory(fleetId)
          maintenanceHistory.value = data.data

          break
        }
        case 'legalitas': {
          const { data } = await fleetHistoryApi.legalDocs(fleetId)
          legalDocs.value = data.data

          break
        }
        case 'fuel': {
          const { data } = await fleetHistoryApi.fuelLogs(fleetId)
          fuelLogs.value = data.data

          break
        }
        case 'biaya': {
          const { data } = await fleetHistoryApi.operationalCosts(fleetId)
          operationalCosts.value = data.data

          break
        }
        case 'pendapatan': {
          const { data } = await fleetHistoryApi.revenues(fleetId)
          revenues.value = data.data

          break
        }
        case 'profit-loss': {
          const { data } = await fleetHistoryApi.profitability(fleetId)
          profitability.value = data.data

          break
        }
        case 'cost-per-km': {
          const { data } = await fleetHistoryApi.costPerKm(fleetId)
          costPerKm.value = data.data.breakdown

          break
        }
        case 'downtime': {
          const { data } = await fleetHistoryApi.downtimes(fleetId)
          downtimes.value = data.data

          break
        }
        case 'komponen': {
          const { data } = await fleetHistoryApi.components(fleetId)
          components.value = data.data

          break
        }
      // No default
      }
    } finally {
      loadingTab.value = false
    }
  }

  watch(tab, name => loadTab(name))

  onMounted(async () => {
    await loadFleet()
    await loadTab(tab.value)
    if (canManage) {
      const { data } = await branchesApi.list({ per_page: 100 })
      branches.value = data.data
    }
  })

  // -- Ubah Armada --
  const editDialog = ref(false)
  const savingFleet = ref(false)
  const editError = ref(null)
  const editForm = ref({})
  const fleetStatuses = ['aktif', 'maintenance', 'nonaktif']
  const ownershipOptions = ['milik_sendiri', 'sewa', 'leasing']
  const mutationOptions = ['tidak_ada', 'pindah', 'jual', 'ganti_nopol']

  function openEditFleet () {
    editForm.value = {
      plate_number: fleet.value.plate_number,
      fleet_type: fleet.value.fleet_type,
      brand: fleet.value.brand,
      model: fleet.value.model,
      year: fleet.value.year,
      chassis_number: fleet.value.chassis_number,
      engine_number: fleet.value.engine_number,
      keur_number: fleet.value.keur_number,
      capacity: fleet.value.capacity,
      purchase_price: fleet.value.purchase_price,
      ownership: fleet.value.ownership,
      leasing_status: fleet.value.leasing_status,
      b3_dishub_number: fleet.value.b3_dishub_number,
      mutation_status: fleet.value.mutation_status,
      branch_id: fleet.value.branch_id,
      status: fleet.value.status,
      service_interval_km: fleet.value.service_interval_km,
      service_interval_engine_hours: fleet.value.service_interval_engine_hours,
      service_interval_months: fleet.value.service_interval_months,
    }
    editError.value = null
    editDialog.value = true
  }

  async function submitEditFleet () {
    savingFleet.value = true
    editError.value = null
    try {
      await fleetsApi.update(fleetId, editForm.value)
      editDialog.value = false
      await loadFleet()
    } catch (error) {
      editError.value = error.response?.data?.message ?? t('fleets.editSaveFailed')
    } finally {
      savingFleet.value = false
    }
  }

  // -- Legal doc dialog --
  const legalDialog = ref(false)
  const savingLegal = ref(false)
  const legalForm = ref({ doc_type: 'STNK', doc_number: '', expiry_date: '' })
  const docTypes = ['STNK', 'KIR', 'PAJAK', 'ASURANSI', 'IZIN']

  function openLegalDialog () {
    legalForm.value = { doc_type: 'STNK', doc_number: '', expiry_date: '' }
    legalDialog.value = true
  }

  async function submitLegal () {
    savingLegal.value = true
    try {
      await fleetHistoryApi.createLegalDoc(fleetId, legalForm.value)
      legalDialog.value = false
      await loadTab('legalitas')
    } finally {
      savingLegal.value = false
    }
  }

  // -- Fuel log dialog --
  const fuelDialog = ref(false)
  const savingFuel = ref(false)
  const fuelForm = ref({ log_date: '', liters: 0, cost: 0, odometer: null, engine_hours: null })

  function openFuelDialog () {
    fuelForm.value = { log_date: '', liters: 0, cost: 0, odometer: null, engine_hours: null }
    fuelDialog.value = true
  }

  async function submitFuel () {
    savingFuel.value = true
    try {
      await fleetHistoryApi.createFuelLog(fleetId, fuelForm.value)
      fuelDialog.value = false
      await loadTab('fuel')
    } finally {
      savingFuel.value = false
    }
  }

  // -- Revenue dialog --
  const revenueDialog = ref(false)
  const savingRevenue = ref(false)
  const revenueError = ref(null)
  const revenueForm = ref({ period: '', source_po_number: '', amount: 0 })

  function openRevenueDialog () {
    revenueForm.value = { period: '', source_po_number: '', amount: 0 }
    revenueError.value = null
    revenueDialog.value = true
  }

  async function submitRevenue () {
    savingRevenue.value = true
    revenueError.value = null
    try {
      await fleetHistoryApi.createRevenue(fleetId, revenueForm.value)
      revenueDialog.value = false
      await loadTab('pendapatan')
    } catch (error) {
      revenueError.value = error.response?.data?.message ?? t('fleets.saveRevenueFailed')
    } finally {
      savingRevenue.value = false
    }
  }

  const chartOptions = { chart: { toolbar: { show: false } }, colors: ['#22C55E', '#EF4444'], legend: { position: 'top' } }

  // -- Manajemen Komponen (ban/aki/oli/rem) --
  const componentLabels = { ban: 'fleets.componentBan', oli_pelumas: 'fleets.componentOli', aki_kelistrikan: 'fleets.componentAki', rem: 'fleets.componentRem' }

  const componentDialog = ref(false)
  const savingComponent = ref(false)
  const componentForm = ref({ component_type: null, interval_km: null, interval_months: null, notes: '' })

  function openComponentDialog (component) {
    componentForm.value = {
      component_type: component.component_type,
      interval_km: component.interval_km,
      interval_months: component.interval_months,
      notes: component.notes,
    }
    componentDialog.value = true
  }

  async function submitComponent () {
    savingComponent.value = true
    try {
      await fleetHistoryApi.updateComponent(fleetId, componentForm.value.component_type, {
        interval_km: componentForm.value.interval_km,
        interval_months: componentForm.value.interval_months,
        notes: componentForm.value.notes,
      })
      componentDialog.value = false
      await loadTab('komponen')
    } finally {
      savingComponent.value = false
    }
  }

  const markingReplacedType = ref(null)

  async function markComponentReplaced (componentType) {
    markingReplacedType.value = componentType
    try {
      await fleetHistoryApi.markComponentReplaced(fleetId, componentType)
      await loadTab('komponen')
    } finally {
      markingReplacedType.value = null
    }
  }
</script>

<template>
  <div v-if="fleet">
    <div class="d-flex align-center mb-4 flex-wrap ga-2">
      <h1 class="text-h5">{{ fleet.plate_number }}</h1>
      <StatusChip :status="fleet.status" />

      <v-chip
        v-if="fleet.inspection_due"
        color="warning"
        prepend-icon="mdi-alert-circle-outline"
        size="small"
        variant="tonal"
      >
        {{ t('fleets.inspectionDue') }}
      </v-chip>

      <v-chip
        v-if="fleet.service_due"
        color="error"
        prepend-icon="mdi-wrench-clock-outline"
        size="small"
        variant="tonal"
      >
        {{ t('fleets.serviceDue') }}
      </v-chip>

      <div class="text-medium-emphasis">{{ fleet.fleet_type }} — {{ fleet.brand }} {{ fleet.model }}</div>
      <v-spacer />
      <v-btn v-if="canManage" prepend-icon="mdi-pencil-outline" variant="tonal" @click="openEditFleet">{{ t('fleets.editFleet') }}</v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-title>{{ t('fleets.vehicleInfo') }}</v-card-title>

      <v-card-text>
        <v-row dense>
          <v-col cols="6" md="3"><strong>{{ t('common.branch') }}</strong><div>{{ fleet.branch?.name ?? '-' }}</div></v-col>
          <v-col cols="6" md="3"><strong>{{ t('fleets.chassisNumber') }}</strong><div>{{ fleet.chassis_number ?? '-' }}</div></v-col>
          <v-col cols="6" md="3"><strong>{{ t('fleets.engineNumber') }}</strong><div>{{ fleet.engine_number ?? '-' }}</div></v-col>
          <v-col cols="6" md="3"><strong>{{ t('fleets.keurNumber') }}</strong><div>{{ fleet.keur_number ?? '-' }}</div></v-col>
          <v-col cols="6" md="3"><strong>{{ t('fleets.ownership') }}</strong><div>{{ t(`enums.ownership.${fleet.ownership}`) }}</div></v-col>
          <v-col v-if="fleet.ownership === 'leasing'" cols="6" md="3"><strong>{{ t('fleets.leasingStatus') }}</strong><div>{{ fleet.leasing_status ?? '-' }}</div></v-col>
          <v-col cols="6" md="3"><strong>{{ t('fleets.b3DishubNumber') }}</strong><div>{{ fleet.b3_dishub_number ?? '-' }}</div></v-col>
          <v-col cols="6" md="3"><strong>{{ t('fleets.mutationStatus') }}</strong><div>{{ t(`enums.mutationStatus.${fleet.mutation_status}`) }}</div></v-col>
          <v-col cols="6" md="3"><strong>{{ t('fleets.purchasePrice') }}</strong><div>{{ fleet.purchase_price ? formatCurrency(fleet.purchase_price) : '-' }}</div></v-col>

          <v-col cols="6" md="3">
            <strong>{{ t('fleets.lastInspection') }}</strong>
            <div>{{ fleet.last_inspection_at ? formatDate(fleet.last_inspection_at) : t('fleets.neverInspected') }}</div>
          </v-col>

          <v-col cols="6" md="3">
            <strong>{{ t('fleets.nextInspectionDue') }}</strong>
            <div :class="fleet.inspection_due ? 'text-warning' : ''">{{ formatDate(fleet.inspection_due_date) }}</div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title class="d-flex align-center">
        {{ t('fleets.serviceSchedule') }}

        <v-chip
          v-if="fleet.service_due"
          class="ml-2"
          color="error"
          size="small"
          variant="tonal"
        >
          {{ t('fleets.serviceDue') }}
        </v-chip>

        <v-spacer />
        <v-btn v-if="canManage" size="small" variant="text" @click="openEditFleet">{{ t('fleets.configureService') }}</v-btn>
      </v-card-title>

      <v-card-text>
        <v-alert v-if="fleet.service_due" class="mb-4" type="error" variant="tonal">
          {{ t('fleets.serviceDueReasons', { reasons: fleet.service_due_reasons.map(r => t(`fleets.serviceDueReason.${r}`)).join(', ') }) }}
        </v-alert>

        <v-row dense>
          <v-col cols="6" md="3">
            <strong>{{ t('fleets.lastService') }}</strong>
            <div>{{ fleet.last_service_at ? formatDate(fleet.last_service_at) : t('fleets.neverServiced') }}</div>
          </v-col>

          <v-col cols="6" md="3">
            <strong>{{ t('fleets.serviceIntervalKm') }}</strong>
            <div>{{ fleet.service_interval_km ?? t('fleets.notConfigured') }}</div>
          </v-col>

          <v-col cols="6" md="3">
            <strong>{{ t('fleets.serviceIntervalEngineHours') }}</strong>
            <div>{{ fleet.service_interval_engine_hours ?? t('fleets.notConfigured') }}</div>
          </v-col>

          <v-col cols="6" md="3">
            <strong>{{ t('fleets.serviceIntervalMonths') }}</strong>
            <div>{{ fleet.service_interval_months ?? t('fleets.notConfigured') }}</div>
          </v-col>

          <v-col cols="6" md="3">
            <strong>{{ t('fleets.currentOdometer') }}</strong>
            <div>{{ fleet.current_odometer ?? '-' }}</div>
          </v-col>

          <v-col cols="6" md="3">
            <strong>{{ t('fleets.currentEngineHours') }}</strong>
            <div>{{ fleet.current_engine_hours ?? '-' }}</div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="riwayat">{{ t('fleets.tabHistory') }}</v-tab>
      <v-tab value="legalitas">{{ t('fleets.tabLegal') }}</v-tab>
      <v-tab value="fuel">{{ t('fleets.tabFuel') }}</v-tab>
      <v-tab value="biaya">{{ t('fleets.tabCosts') }}</v-tab>
      <v-tab value="pendapatan">{{ t('fleets.tabRevenue') }}</v-tab>
      <v-tab value="profit-loss">{{ t('fleets.tabProfitLoss') }}</v-tab>
      <v-tab value="cost-per-km">{{ t('fleets.tabCostPerKm') }}</v-tab>
      <v-tab value="downtime">{{ t('fleets.tabDowntime') }}</v-tab>
      <v-tab value="komponen">{{ t('fleets.tabComponents') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="riwayat">
        <v-card :loading="loadingTab">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('common.date') }}</th>
                <th>{{ t('workOrder.description') }}</th>
                <th>{{ t('fleets.itemsReplaced') }}</th>
                <th>{{ t('fleets.executor') }}</th>
                <th class="text-right">{{ t('fleets.cost') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in maintenanceHistory" :key="row.id">
                <td>{{ index + 1 }}</td>
                <td>{{ formatDate(row.performed_at) }}</td>
                <td>{{ row.description }}</td>

                <td>
                  <span v-if="row.items?.length">
                    {{ row.items.map(i => `${i.description} (${i.qty})`).join(', ') }}
                  </span>

                  <span v-else class="text-medium-emphasis">-</span>
                </td>

                <td>{{ row.executor ?? '-' }}</td>
                <td class="text-right">{{ formatCurrency(row.cost) }}</td>
              </tr>

              <tr v-if="maintenanceHistory.length === 0"><td class="text-medium-emphasis" colspan="6">{{ t('fleets.noHistory') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="legalitas">
        <v-card :loading="loadingTab">
          <v-card-title class="d-flex">
            {{ t('fleets.legalDocs') }}
            <v-spacer />
            <v-btn v-if="auth.hasPermission('fleet.manage')" size="small" variant="text" @click="openLegalDialog">{{ t('fleets.addDoc') }}</v-btn>
          </v-card-title>

          <v-table>
            <thead><tr><th>{{ t('common.rowNo') }}</th><th>{{ t('fleets.docType') }}</th><th>{{ t('fleets.docNumber') }}</th><th>{{ t('fleets.dueDate') }}</th><th /></tr></thead>

            <tbody>
              <tr v-for="(doc, index) in legalDocs" :key="doc.id">
                <td>{{ index + 1 }}</td>
                <td>{{ doc.doc_type }}</td>
                <td>{{ doc.doc_number ?? '-' }}</td>
                <td>{{ formatDate(doc.expiry_date) }}</td>
                <td><v-chip v-if="doc.is_expiring_soon" color="warning" size="small" variant="tonal">{{ t('fleets.expiringSoon') }}</v-chip></td>
              </tr>

              <tr v-if="legalDocs.length === 0"><td class="text-medium-emphasis" colspan="5">{{ t('fleets.noLegalDocs') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="fuel">
        <v-card :loading="loadingTab">
          <v-card-title class="d-flex">
            {{ t('fleets.fuelHistory') }}
            <v-spacer />
            <v-btn v-if="auth.hasPermission('fleet.manage')" size="small" variant="text" @click="openFuelDialog">{{ t('fleets.addRecord') }}</v-btn>
          </v-card-title>

          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('common.date') }}</th>
                <th class="text-right">{{ t('fleets.liters') }}</th>
                <th class="text-right">{{ t('fleets.cost') }}</th>
                <th class="text-right">{{ t('fleets.odometer') }}</th>
                <th class="text-right">{{ t('fleets.engineHours') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(log, index) in fuelLogs" :key="log.id">
                <td>{{ index + 1 }}</td>
                <td>{{ formatDate(log.log_date) }}</td>
                <td class="text-right">{{ log.liters }}</td>
                <td class="text-right">{{ formatCurrency(log.cost) }}</td>
                <td class="text-right">{{ log.odometer ?? '-' }}</td>
                <td class="text-right">{{ log.engine_hours ?? '-' }}</td>
              </tr>

              <tr v-if="fuelLogs.length === 0"><td class="text-medium-emphasis" colspan="6">{{ t('fleets.noFuelLogs') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="biaya">
        <v-card :loading="loadingTab">
          <v-table>
            <thead><tr><th>{{ t('common.rowNo') }}</th><th>{{ t('common.date') }}</th><th>{{ t('fleets.costType') }}</th><th>{{ t('fleets.source') }}</th><th class="text-right">{{ t('fleets.amount') }}</th></tr></thead>

            <tbody>
              <tr v-for="(cost, index) in operationalCosts" :key="cost.id">
                <td>{{ index + 1 }}</td>
                <td>{{ formatDate(cost.incurred_at) }}</td>
                <td>{{ cost.cost_type?.name }}</td>
                <td>{{ cost.source_type }}</td>
                <td class="text-right">{{ formatCurrency(cost.amount) }}</td>
              </tr>

              <tr v-if="operationalCosts.length === 0"><td class="text-medium-emphasis" colspan="5">{{ t('fleets.noCosts') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="pendapatan">
        <v-card :loading="loadingTab">
          <v-card-title class="d-flex">
            {{ t('fleets.revenueHistory') }}
            <v-spacer />
            <v-btn v-if="auth.hasPermission('fleet.manage')" size="small" variant="text" @click="openRevenueDialog">{{ t('fleets.addRevenue') }}</v-btn>
          </v-card-title>

          <v-table>
            <thead><tr><th>{{ t('common.rowNo') }}</th><th>{{ t('fleets.period') }}</th><th>{{ t('fleets.poNumber') }}</th><th class="text-right">{{ t('fleets.amount') }}</th></tr></thead>

            <tbody>
              <tr v-for="(rev, index) in revenues" :key="rev.id">
                <td>{{ index + 1 }}</td>
                <td>{{ rev.period }}</td>
                <td>{{ rev.source_po_number ?? '-' }}</td>
                <td class="text-right">{{ formatCurrency(rev.amount) }}</td>
              </tr>

              <tr v-if="revenues.length === 0"><td class="text-medium-emphasis" colspan="4">{{ t('fleets.noRevenue') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="profit-loss">
        <template v-if="profitability">
          <v-row class="mb-2">
            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('fleets.totalCost') }}</div>
                <div class="text-h6">{{ formatCurrency(profitability.summary.total_cost) }}</div>
              </v-card-text></v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('fleets.totalRevenue') }}</div>
                <div class="text-h6">{{ formatCurrency(profitability.summary.total_revenue) }}</div>
              </v-card-text></v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('fleets.profitLoss') }}</div>

                <div class="text-h6" :class="profitability.summary.profit >= 0 ? 'text-success' : 'text-error'">
                  {{ formatCurrency(profitability.summary.profit) }}
                </div>
              </v-card-text></v-card>
            </v-col>
          </v-row>

          <v-card>
            <v-card-text>
              <apexchart
                v-if="profitability.breakdown.length > 0"
                height="320"
                :options="{ ...chartOptions, xaxis: { categories: profitability.breakdown.map(b => b.period) } }"
                :series="[
                  { name: t('fleets.totalRevenue'), data: profitability.breakdown.map(b => b.total_revenue) },
                  { name: t('fleets.totalCost'), data: profitability.breakdown.map(b => b.total_cost) },
                ]"
                type="bar"
              />

              <v-alert v-else type="info" variant="tonal">{{ t('fleets.noProfitData') }}</v-alert>
            </v-card-text>
          </v-card>
        </template>
      </v-window-item>

      <v-window-item value="cost-per-km">
        <v-alert class="mb-4" type="info" variant="tonal">{{ t('fleets.costPerKmHint') }}</v-alert>

        <v-card :loading="loadingTab">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('fleets.period') }}</th>
                <th class="text-right">{{ t('fleets.repairCost') }}</th>
                <th class="text-right">{{ t('fleets.kmTotal') }}</th>
                <th class="text-right">{{ t('fleets.costPerKm') }}</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(row, index) in costPerKm" :key="row.period">
                <td>{{ index + 1 }}</td>
                <td>{{ row.period }}</td>
                <td class="text-right">{{ formatCurrency(row.repair_cost) }}</td>
                <td class="text-right">{{ row.km_total ?? '-' }}</td>
                <td class="text-right">{{ row.cost_per_km !== null ? formatCurrency(row.cost_per_km) : '-' }}</td>
              </tr>

              <tr v-if="costPerKm.length === 0">
                <td class="text-medium-emphasis" colspan="5">{{ t('fleets.noCostPerKmData') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <v-window-item value="downtime">
        <template v-if="downtimes">
          <v-row class="mb-2">
            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('fleets.currentlyDown') }}</div>

                <div class="text-h6" :class="downtimes.currently_down ? 'text-error' : 'text-success'">
                  {{ downtimes.currently_down ? t('fleets.yesDown') : t('fleets.noNotDown') }}
                </div>
              </v-card-text></v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('fleets.downtimeThisMonth') }}</div>
                <div class="text-h6">{{ downtimes.summary.total_downtime_minutes_this_month }} {{ t('fleets.minutes') }}</div>
              </v-card-text></v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('fleets.downtimeTotal') }}</div>
                <div class="text-h6">{{ downtimes.summary.total_downtime_minutes }} {{ t('fleets.minutes') }} ({{ downtimes.summary.count }}x)</div>
              </v-card-text></v-card>
            </v-col>
          </v-row>

          <v-row class="mb-2">
            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('dashboard.availabilityRate') }}</div>

                <div class="text-h6" :class="downtimes.summary.availability_rate >= 90 ? 'text-success' : 'text-error'">
                  {{ downtimes.summary.availability_rate }}%
                </div>
              </v-card-text></v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('dashboard.mtbf') }}</div>
                <div class="text-h6">{{ formatDuration(downtimes.summary.mtbf_minutes) }}</div>
              </v-card-text></v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card><v-card-text>
                <div class="text-caption text-medium-emphasis">{{ t('dashboard.mttr') }}</div>
                <div class="text-h6">{{ formatDuration(downtimes.summary.mttr_minutes) }}</div>
              </v-card-text></v-card>
            </v-col>
          </v-row>

          <v-card :loading="loadingTab">
            <v-table>
              <thead>
                <tr>
                  <th>{{ t('common.rowNo') }}</th>
                  <th>{{ t('fleets.downtimeStart') }}</th>
                  <th>{{ t('fleets.downtimeEnd') }}</th>
                  <th class="text-right">{{ t('fleets.duration') }}</th>
                  <th>{{ t('workOrder.description') }}</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="(row, index) in downtimes.records" :key="row.id">
                  <td>{{ index + 1 }}</td>
                  <td>{{ formatDate(row.started_at) }}</td>
                  <td>{{ row.ended_at ? formatDate(row.ended_at) : t('fleets.stillOngoing') }}</td>
                  <td class="text-right">{{ row.duration_minutes }} {{ t('fleets.minutes') }}</td>
                  <td>{{ row.description ?? '-' }}</td>
                </tr>

                <tr v-if="downtimes.records.length === 0">
                  <td class="text-medium-emphasis" colspan="5">{{ t('fleets.noDowntimeData') }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </template>
      </v-window-item>

      <v-window-item value="komponen">
        <v-alert class="mb-4" type="info" variant="tonal">{{ t('fleets.componentsHint') }}</v-alert>

        <v-card :loading="loadingTab">
          <v-table>
            <thead>
              <tr>
                <th>{{ t('common.rowNo') }}</th>
                <th>{{ t('fleets.component') }}</th>
                <th>{{ t('fleets.lastReplaced') }}</th>
                <th class="text-right">{{ t('fleets.lastReplacedOdometer') }}</th>
                <th class="text-right">{{ t('fleets.serviceIntervalKm') }}</th>
                <th class="text-right">{{ t('fleets.serviceIntervalMonths') }}</th>
                <th />
                <th v-if="auth.hasPermission('fleet.manage')" />
              </tr>
            </thead>

            <tbody>
              <tr v-for="(component, index) in components" :key="component.component_type">
                <td>{{ index + 1 }}</td>
                <td>{{ t(componentLabels[component.component_type]) }}</td>
                <td>{{ component.last_replaced_at ? formatDate(component.last_replaced_at) : t('fleets.neverServiced') }}</td>
                <td class="text-right">{{ component.last_replaced_odometer ?? '-' }}</td>
                <td class="text-right">{{ component.interval_km ?? t('fleets.notConfigured') }}</td>
                <td class="text-right">{{ component.interval_months ?? t('fleets.notConfigured') }}</td>

                <td>
                  <v-chip
                    v-if="component.due"
                    color="error"
                    prepend-icon="mdi-alert-circle-outline"
                    size="small"
                    variant="tonal"
                  >
                    {{ t('fleets.serviceDue') }}
                  </v-chip>
                </td>

                <td v-if="auth.hasPermission('fleet.manage')" class="text-right">
                  <v-btn size="small" variant="text" @click="openComponentDialog(component)">{{ t('fleets.configureInterval') }}</v-btn>

                  <v-btn
                    :loading="markingReplacedType === component.component_type"
                    size="small"
                    variant="text"
                    @click="markComponentReplaced(component.component_type)"
                  >{{ t('fleets.markReplaced') }}</v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>
    </v-window>

    <v-dialog v-model="componentDialog" max-width="420">
      <v-card>
        <v-card-title>{{ componentForm.component_type ? t(componentLabels[componentForm.component_type]) : '' }}</v-card-title>

        <v-card-text>
          <v-text-field
            v-model.number="componentForm.interval_km"
            :label="`${t('fleets.serviceIntervalKm')} ${t('common.optional')}`"
            type="number"
          />

          <v-text-field
            v-model.number="componentForm.interval_months"
            :label="`${t('fleets.serviceIntervalMonths')} ${t('common.optional')}`"
            type="number"
          />

          <v-text-field v-model="componentForm.notes" :label="`${t('common.notes')} ${t('common.optional')}`" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="componentDialog = false">{{ t('common.cancel') }}</v-btn>
          <v-btn color="primary" :loading="savingComponent" variant="flat" @click="submitComponent">{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="editDialog" max-width="560">
      <v-card>
        <v-card-title>{{ t('fleets.editFleet') }}</v-card-title>

        <v-card-text>
          <v-alert v-if="editError" class="mb-4" type="error" variant="tonal">{{ editError }}</v-alert>

          <v-select
            v-model="editForm.branch_id"
            :disabled="auth.isBranchScoped"
            item-title="name"
            item-value="id"
            :items="branches"
            :label="t('common.branch')"
          />

          <v-text-field v-model="editForm.plate_number" :label="t('fleets.plateNumber')" />
          <v-text-field v-model="editForm.fleet_type" :label="t('fleets.fleetType')" />
          <v-text-field v-model="editForm.brand" :label="`${t('fleets.brand')} ${t('common.optional')}`" />
          <v-text-field v-model="editForm.chassis_number" :label="`${t('fleets.chassisNumber')} ${t('common.optional')}`" />
          <v-text-field v-model="editForm.engine_number" :label="`${t('fleets.engineNumber')} ${t('common.optional')}`" />
          <v-text-field v-model.number="editForm.year" :label="`${t('fleets.year')} ${t('common.optional')}`" type="number" />
          <v-text-field v-model="editForm.model" :label="`${t('fleets.model')} ${t('common.optional')}`" />
          <v-text-field v-model="editForm.keur_number" :label="`${t('fleets.keurNumber')} ${t('common.optional')}`" />

          <v-select
            v-model="editForm.ownership"
            :items="ownershipOptions.map(o => ({ title: t(`enums.ownership.${o}`), value: o }))"
            :label="t('fleets.ownership')"
          />

          <v-text-field v-model="editForm.b3_dishub_number" :label="`${t('fleets.b3DishubNumber')} ${t('common.optional')}`" />

          <v-select
            v-model="editForm.mutation_status"
            :items="mutationOptions.map(o => ({ title: t(`enums.mutationStatus.${o}`), value: o }))"
            :label="t('fleets.mutationStatus')"
          />

          <v-text-field
            v-if="editForm.ownership === 'leasing'"
            v-model="editForm.leasing_status"
            :label="`${t('fleets.leasingStatus')} ${t('common.optional')}`"
          />

          <v-text-field v-model.number="editForm.capacity" :label="`${t('fleets.capacity')} ${t('common.optional')}`" type="number" />
          <v-text-field v-model.number="editForm.purchase_price" :label="`${t('fleets.purchasePrice')} ${t('common.optional')}`" type="number" />

          <v-select v-model="editForm.status" :items="fleetStatuses.map(s => ({ title: t(`enums.status.${s}`), value: s }))" :label="t('common.status')" />

          <v-divider class="my-4" />
          <div class="text-subtitle-2 mb-2">{{ t('fleets.serviceSchedule') }}</div>

          <v-text-field
            v-model.number="editForm.service_interval_km"
            :label="`${t('fleets.serviceIntervalKm')} ${t('common.optional')}`"
            type="number"
          />

          <v-text-field
            v-model.number="editForm.service_interval_engine_hours"
            :label="`${t('fleets.serviceIntervalEngineHours')} ${t('common.optional')}`"
            type="number"
          />

          <v-text-field
            v-model.number="editForm.service_interval_months"
            :label="`${t('fleets.serviceIntervalMonths')} ${t('common.optional')}`"
            type="number"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editDialog = false">{{ t('common.cancel') }}</v-btn>
          <v-btn color="primary" :loading="savingFleet" variant="flat" @click="submitEditFleet">{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="legalDialog" max-width="480">
      <v-card>
        <v-card-title>{{ t('fleets.addLegalDoc') }}</v-card-title>

        <v-card-text>
          <v-select v-model="legalForm.doc_type" :items="docTypes" :label="t('fleets.docType')" />
          <v-text-field v-model="legalForm.doc_number" :label="t('fleets.docNumberOptional')" />
          <v-text-field v-model="legalForm.expiry_date" :label="t('fleets.dueDateLabel')" type="date" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="legalDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="!legalForm.expiry_date"
            :loading="savingLegal"
            variant="flat"
            @click="submitLegal"
          >{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="fuelDialog" max-width="480">
      <v-card>
        <v-card-title>{{ t('fleets.addFuelRecord') }}</v-card-title>

        <v-card-text>
          <v-text-field v-model="fuelForm.log_date" :label="t('common.date')" type="date" />
          <v-text-field v-model.number="fuelForm.liters" :label="t('fleets.liters')" type="number" />
          <v-text-field v-model.number="fuelForm.cost" :label="t('fleets.cost')" type="number" />
          <v-text-field v-model.number="fuelForm.odometer" :label="`${t('fleets.odometer')} ${t('common.optional')}`" type="number" />
          <v-text-field v-model.number="fuelForm.engine_hours" :label="`${t('fleets.engineHours')} ${t('common.optional')}`" type="number" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="fuelDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="!fuelForm.log_date"
            :loading="savingFuel"
            variant="flat"
            @click="submitFuel"
          >{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="revenueDialog" max-width="480">
      <v-card>
        <v-card-title>{{ t('fleets.addRevenueTitle') }}</v-card-title>

        <v-card-text>
          <v-alert class="mb-4" type="info" variant="tonal">{{ t('fleets.revenueHint') }}</v-alert>
          <v-alert v-if="revenueError" class="mb-4" type="error" variant="tonal">{{ revenueError }}</v-alert>

          <v-text-field v-model="revenueForm.period" :label="t('fleets.periodFormat')" placeholder="2026-07" />
          <v-text-field v-model="revenueForm.source_po_number" :label="t('fleets.poNumberOptional')" />
          <v-text-field v-model.number="revenueForm.amount" :label="t('fleets.amount')" type="number" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="revenueDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="!revenueForm.period || !revenueForm.amount"
            :loading="savingRevenue"
            variant="flat"
            @click="submitRevenue"
          >{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
