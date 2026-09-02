<script setup>
  import { onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { branchesApi, fleetsApi } from '@/api/masterData'
  import StatusChip from '@/components/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'

  // Daftar Armada — pintu masuk ke Detail Armada (Wireframe Document 2.5).
  const router = useRouter()
  const auth = useAuthStore()
  const { t } = useI18n()
  const canManage = auth.hasPermission('master-data.manage')

  const fleets = ref([])
  const branches = ref([])
  const loading = ref(false)
  const search = ref('')

  async function load () {
    loading.value = true
    try {
      const { data } = await fleetsApi.list({ per_page: 50 })
      fleets.value = data.data
    } finally {
      loading.value = false
    }
  }

  onMounted(async () => {
    await load()
    if (canManage) {
      const { data } = await branchesApi.list({ per_page: 100 })
      branches.value = data.data
    }
  })

  // -- Buat Armada --
  const dialog = ref(false)
  const saving = ref(false)
  const errorMessage = ref(null)
  const form = ref({})

  const ownershipOptions = ['milik_sendiri', 'sewa', 'leasing']
  const mutationOptions = ['tidak_ada', 'pindah', 'jual', 'ganti_nopol']

  function openCreate () {
    form.value = {
      plate_number: '',
      fleet_type: '',
      brand: '',
      model: '',
      year: null,
      chassis_number: '',
      engine_number: '',
      keur_number: '',
      capacity: null,
      purchase_price: null,
      ownership: 'milik_sendiri',
      leasing_status: '',
      b3_dishub_number: '',
      mutation_status: 'tidak_ada',
      // Role bercabang (Tim Logistik) hanya bisa mendaftarkan armada untuk
      // cabangnya sendiri — backend memaksa ulang nilai ini juga.
      branch_id: auth.isBranchScoped ? auth.branchId : null,
    }
    errorMessage.value = null
    dialog.value = true
  }

  async function submit () {
    saving.value = true
    errorMessage.value = null
    try {
      await fleetsApi.create(form.value)
      dialog.value = false
      await load()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('fleets.saveFailed')
    } finally {
      saving.value = false
    }
  }

  // -- Sinkron Armada dari SYOP --
  // Hanya armada dari transportir Pro Energi sendiri atau TDS yang ditarik
  // (lihat SyopNativeAdapter::getEligibleFleets()) — bukan seluruh vendor
  // transportir pihak ketiga di SYOP.
  const syncing = ref(false)
  const syncMessage = ref(null)
  const syncError = ref(false)
  const syncBranchDialog = ref(false)
  const syncBranchId = ref(null)

  async function runSync (branchId) {
    syncing.value = true
    syncMessage.value = null
    syncError.value = false
    try {
      const { data } = await fleetsApi.syncFromSyop(branchId ? { branch_id: branchId } : {})
      syncMessage.value = data.data.skipped > 0
        ? t('fleets.syncSuccessWithSkipped', { synced: data.data.synced, skipped: data.data.skipped })
        : t('fleets.syncSuccess', { synced: data.data.synced })
      await load()
    } catch (error) {
      syncMessage.value = error.response?.data?.message ?? t('fleets.syncFailed')
      syncError.value = true
    } finally {
      syncing.value = false
    }
  }

  function openSync () {
    if (auth.isBranchScoped) {
      runSync()
    } else {
      syncBranchId.value = null
      syncBranchDialog.value = true
    }
  }

  async function confirmSyncBranch () {
    syncBranchDialog.value = false
    await runSync(syncBranchId.value)
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4 flex-wrap ga-2">
      <h1 class="text-h5">{{ t('fleets.title') }}</h1>
      <v-spacer />

      <v-text-field
        v-model="search"
        density="compact"
        hide-details
        max-width="280"
        prepend-inner-icon="mdi-magnify"
        single-line
      />

      <v-btn
        v-if="canManage"
        class="mr-2"
        :loading="syncing"
        prepend-icon="mdi-sync"
        variant="tonal"
        @click="openSync"
      >{{ t('fleets.syncFromSyop') }}</v-btn>

      <v-btn v-if="canManage" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('fleets.add') }}</v-btn>
    </div>

    <v-alert
      v-if="syncMessage"
      class="mb-4"
      closable
      :type="syncError ? 'error' : 'success'"
      variant="tonal"
      @click:close="syncMessage = null"
    >{{ syncMessage }}</v-alert>

    <v-row>
      <v-col
        v-for="fleet in fleets.filter(f => !search || f.plate_number.toLowerCase().includes(search.toLowerCase()))"
        :key="fleet.id"
        cols="12"
        md="4"
        sm="6"
      >
        <v-card :loading="loading" @click="router.push(`/fleets/${fleet.id}`)">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div class="text-h6">{{ fleet.plate_number }}</div>
              <StatusChip :status="fleet.status" />
            </div>

            <div class="text-medium-emphasis">{{ fleet.fleet_type }} — {{ fleet.brand }} {{ fleet.model }}</div>
            <div class="text-caption">{{ fleet.branch?.name }}</div>

            <v-chip
              v-if="fleet.inspection_due"
              class="mt-2 mr-1"
              color="warning"
              prepend-icon="mdi-alert-circle-outline"
              size="small"
              variant="tonal"
            >
              {{ t('fleets.inspectionDue') }}
            </v-chip>

            <v-chip
              v-if="fleet.service_due"
              class="mt-2"
              color="error"
              prepend-icon="mdi-wrench-clock-outline"
              size="small"
              variant="tonal"
            >
              {{ t('fleets.serviceDue') }}
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="dialog" max-width="560">
      <v-card>
        <v-card-title>{{ t('fleets.add') }}</v-card-title>

        <v-card-text>
          <v-alert v-if="errorMessage" class="mb-4" type="error" variant="tonal">{{ errorMessage }}</v-alert>

          <v-select
            v-model="form.branch_id"
            :disabled="auth.isBranchScoped"
            item-title="name"
            item-value="id"
            :items="branches"
            :label="t('common.branch')"
            required
          />

          <v-text-field v-model="form.plate_number" :label="t('fleets.plateNumber')" required />
          <v-text-field v-model="form.fleet_type" :label="t('fleets.fleetType')" required />
          <v-text-field v-model="form.brand" :label="`${t('fleets.brand')} ${t('common.optional')}`" />
          <v-text-field v-model="form.chassis_number" :label="`${t('fleets.chassisNumber')} ${t('common.optional')}`" />
          <v-text-field v-model="form.engine_number" :label="`${t('fleets.engineNumber')} ${t('common.optional')}`" />
          <v-text-field v-model.number="form.year" :label="`${t('fleets.year')} ${t('common.optional')}`" type="number" />
          <v-text-field v-model="form.model" :label="`${t('fleets.model')} ${t('common.optional')}`" />
          <v-text-field v-model="form.keur_number" :label="`${t('fleets.keurNumber')} ${t('common.optional')}`" />

          <v-select
            v-model="form.ownership"
            :items="ownershipOptions.map(o => ({ title: t(`enums.ownership.${o}`), value: o }))"
            :label="t('fleets.ownership')"
          />

          <v-text-field v-model="form.b3_dishub_number" :label="`${t('fleets.b3DishubNumber')} ${t('common.optional')}`" />

          <v-select
            v-model="form.mutation_status"
            :items="mutationOptions.map(o => ({ title: t(`enums.mutationStatus.${o}`), value: o }))"
            :label="t('fleets.mutationStatus')"
          />

          <v-text-field
            v-if="form.ownership === 'leasing'"
            v-model="form.leasing_status"
            :label="`${t('fleets.leasingStatus')} ${t('common.optional')}`"
          />

          <v-text-field v-model.number="form.capacity" :label="`${t('fleets.capacity')} ${t('common.optional')}`" type="number" />
          <v-text-field v-model.number="form.purchase_price" :label="`${t('fleets.purchasePrice')} ${t('common.optional')}`" type="number" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="!form.plate_number || !form.fleet_type || !form.branch_id"
            :loading="saving"
            variant="flat"
            @click="submit"
          >
            {{ t('common.save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="syncBranchDialog" max-width="420">
      <v-card>
        <v-card-title>{{ t('fleets.syncFromSyop') }}</v-card-title>

        <v-card-text>
          <v-select
            v-model="syncBranchId"
            item-title="name"
            item-value="id"
            :items="branches"
            :label="t('common.branch')"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="syncBranchDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="!syncBranchId"
            :loading="syncing"
            variant="flat"
            @click="confirmSyncBranch"
          >{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
