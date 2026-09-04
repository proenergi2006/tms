<script setup>
  import { computed, onMounted, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import {
    branchesApi, costTypesApi, driversApi, fleetsApi, jobTypesApi,
    mechanicsApi, sparepartsApi, vendorsApi, warehousesApi,
  } from '@/api/masterData'
  import ConfirmDialog from '@/components/ConfirmDialog.vue'
  import StatusChip from '@/components/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'
  import { formatCurrency } from '@/utils/format'

  // Master Data — CRUD referensi pendukung (cabang, driver, mekanik, vendor,
  // gudang, sparepart, jenis biaya, jenis pekerjaan). Armada punya halaman
  // tersendiri (Wireframe Document 2.5) karena riwayat/legalitas/dsb-nya
  // lebih kaya; entitas lain di sini karena tidak ada layar khusus di
  // Wireframe asli untuk mengelolanya, padahal backend sudah CRUD penuh.
  const auth = useAuthStore()
  const { t } = useI18n()
  // Cabang & Sparepart masing-masing dipisah dari permission
  // master-data.manage yang dipakai tab lain (driver/mekanik/vendor/dst) —
  // lihat RolePermissionSeeder & routes/modules/master-data.php backend.
  const MANAGE_PERMISSION_BY_TAB = {
    branches: 'branch.manage',
    spareparts: 'sparepart.manage',
  }
  const canManage = computed(() => (
    auth.hasPermission(MANAGE_PERMISSION_BY_TAB[tab.value] ?? 'master-data.manage')
  ))

  const refData = ref({ branches: [], warehouses: [] })

  const statusOptions = computed(() => ['aktif', 'nonaktif'].map(value => ({ title: t(`enums.status.${value}`), value })))
  const vendorTypeOptions = computed(() => ['bengkel', 'vendor_lain'].map(value => ({ title: t(`enums.vendorType.${value}`), value })))
  const sparepartCategoryOptions = computed(() => ['ban', 'oli_pelumas', 'aki_kelistrikan', 'rem', 'sparepart_mesin', 'sparepart_body', 'filter', 'lainnya']
    .map(value => ({ title: t(`enums.sparepartCategory.${value}`), value })))
  const sparepartUnitOptions = computed(() => ['pcs', 'set', 'unit', 'liter', 'box', 'meter']
    .map(value => ({ title: t(`enums.sparepartUnit.${value}`), value })))

  const tabs = computed(() => [
    {
      value: 'branches',
      label: t('masterData.tabBranches'),
      api: branchesApi,
      columns: [{ key: 'code', label: t('masterData.code') }, { key: 'name', label: t('common.name') }, { key: 'address', label: t('masterData.address') }],
      fields: [
        { key: 'code', label: t('masterData.branchCode'), type: 'text', required: true },
        { key: 'name', label: t('masterData.branchName'), type: 'text', required: true },
        { key: 'address', label: t('masterData.address'), type: 'text' },
      ],
    },
    {
      value: 'drivers',
      label: t('masterData.tabDrivers'),
      api: driversApi,
      columns: [{ key: 'name', label: t('common.name') }, { key: 'license_number', label: t('masterData.licenseNumber') }, { key: 'status', label: t('common.status') }],
      fields: [
        { key: 'name', label: t('common.name'), type: 'text', required: true },
        { key: 'license_number', label: t('masterData.licenseNumber'), type: 'text' },
        { key: 'license_expiry', label: t('masterData.licenseExpiry'), type: 'date' },
        { key: 'phone', label: t('common.phone'), type: 'text' },
        { key: 'branch_id', label: t('common.branch'), type: 'select', optionsSource: 'branches', required: true },
        { key: 'status', label: t('common.status'), type: 'select', options: statusOptions.value, editOnly: true },
      ],
    },
    {
      value: 'mechanics',
      label: t('masterData.tabMechanics'),
      api: mechanicsApi,
      columns: [{ key: 'name', label: t('common.name') }, { key: 'phone', label: t('common.phone') }, { key: 'status', label: t('common.status') }],
      fields: [
        { key: 'name', label: t('common.name'), type: 'text', required: true },
        { key: 'phone', label: t('common.phone'), type: 'text' },
        { key: 'branch_id', label: t('common.branch'), type: 'select', optionsSource: 'branches', required: true },
        { key: 'status', label: t('common.status'), type: 'select', options: statusOptions.value, editOnly: true },
      ],
    },
    {
      value: 'vendors',
      label: t('masterData.tabVendors'),
      api: vendorsApi,
      columns: [{ key: 'name', label: t('common.name') }, { key: 'type', label: t('masterData.type') }, { key: 'status', label: t('common.status') }],
      fields: [
        { key: 'name', label: t('common.name'), type: 'text', required: true },
        { key: 'type', label: t('masterData.type'), type: 'select', options: vendorTypeOptions.value },
        { key: 'contact_person', label: t('masterData.contactPerson'), type: 'text' },
        { key: 'phone', label: t('common.phone'), type: 'text' },
        { key: 'address', label: t('masterData.address'), type: 'text' },
        { key: 'status', label: t('common.status'), type: 'select', options: statusOptions.value, editOnly: true },
      ],
    },
    {
      value: 'warehouses',
      label: t('masterData.tabWarehouses'),
      api: warehousesApi,
      columns: [{ key: 'name', label: t('common.name') }, { key: 'address', label: t('masterData.address') }],
      fields: [
        { key: 'name', label: t('masterData.warehouseName'), type: 'text', required: true },
        { key: 'branch_id', label: t('common.branch'), type: 'select', optionsSource: 'branches', required: true },
        { key: 'address', label: t('masterData.address'), type: 'text' },
      ],
    },
    {
      value: 'spareparts',
      label: t('masterData.tabSpareparts'),
      api: sparepartsApi,
      columns: [
        { key: 'sku', label: t('masterData.sku') }, { key: 'name', label: t('common.name') },
        { key: 'unit_cost', label: t('masterData.unitCost') },
        { key: 'stock_qty', label: t('masterData.stock') }, { key: 'min_stock', label: t('masterData.minStock') },
      ],
      fields: [
        // SKU dibuat otomatis oleh backend saat create (lihat
        // SparepartController::store()) — hanya ditampilkan (read-only) saat
        // mengubah data yang sudah ada, sebagai referensi.
        { key: 'sku', label: t('masterData.sku'), type: 'text', hiddenOnCreate: true, readonly: true },
        { key: 'name', label: t('common.name'), type: 'text', required: true },
        { key: 'category', label: t('masterData.category'), type: 'select', options: sparepartCategoryOptions.value },
        { key: 'unit', label: t('masterData.unit'), type: 'select', options: sparepartUnitOptions.value },
        { key: 'unit_cost', label: t('masterData.unitCost'), type: 'number' },
        { key: 'warehouse_id', label: t('masterData.warehouse'), type: 'select', optionsSource: 'warehouses', required: true },
        { key: 'stock_qty', label: t('masterData.stock'), type: 'number' },
        { key: 'min_stock', label: t('masterData.minStock'), type: 'number' },
      ],
    },
    {
      value: 'cost-types',
      label: t('masterData.tabCostTypes'),
      api: costTypesApi,
      columns: [{ key: 'name', label: t('common.name') }, { key: 'category', label: t('masterData.category') }],
      fields: [
        { key: 'name', label: t('common.name'), type: 'text', required: true },
        { key: 'category', label: t('masterData.category'), type: 'text' },
      ],
    },
    {
      value: 'job-types',
      label: t('masterData.tabJobTypes'),
      api: jobTypesApi,
      columns: [{ key: 'name', label: t('common.name') }, { key: 'category', label: t('masterData.category') }],
      fields: [
        { key: 'name', label: t('common.name'), type: 'text', required: true },
        { key: 'category', label: t('masterData.category'), type: 'text' },
      ],
    },
  ])

  const tab = ref('branches')
  const rows = ref([])
  const loading = ref(false)

  const activeTab = () => tabs.value.find(item => item.value === tab.value)

  async function loadTab () {
    loading.value = true
    try {
      const { data } = await activeTab().api.list({ per_page: 100 })
      rows.value = data.data
    } finally {
      loading.value = false
    }
  }

  watch(tab, loadTab, { immediate: true })

  // -- Sinkron Driver dari SYOP --
  // Hanya driver dari transportir Pro Energi sendiri atau TDS yang ditarik
  // (lihat SyopNativeAdapter::getEligibleDrivers()) — bukan seluruh vendor
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
      const { data } = await driversApi.syncFromSyop(branchId ? { branch_id: branchId } : {})
      syncMessage.value = t('masterData.syncSuccess', { count: data.data.synced })
      await loadTab()
    } catch (error) {
      syncMessage.value = error.response?.data?.message ?? t('masterData.syncFailed')
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

  onMounted(async () => {
    const [branchesRes, fleetsRes, warehousesRes] = await Promise.all([
      branchesApi.list({ per_page: 100 }),
      fleetsApi.list({ per_page: 100 }),
      warehousesApi.list({ per_page: 100 }),
    ])
    refData.value = {
      branches: branchesRes.data.data,
      fleets: fleetsRes.data.data.map(f => ({ id: f.id, name: f.plate_number })),
      warehouses: warehousesRes.data.data,
    }
  })

  function optionsFor (field) {
    if (field.options) return field.options
    if (field.optionsSource) return refData.value[field.optionsSource] ?? []

    return []
  }

  // -- Create/Edit dialog --
  const dialog = ref(false)
  const isEdit = ref(false)
  const saving = ref(false)
  const errorMessage = ref(null)
  const form = ref({})

  const visibleFields = computed(() => activeTab().fields.filter(f => (isEdit.value || !f.editOnly) && (isEdit.value || !f.hiddenOnCreate)))

  // Role bercabang (SA, Fleet Operations, Kepala Pool, Tim Logistik) hanya
  // boleh mengelola data cabangnya sendiri — field cabang dikunci ke cabang
  // mereka di form ini. Backend tetap memvalidasi/memaksa ulang (lihat
  // User::isBranchScoped()), ini murni supaya UI tidak menyesatkan.
  const hasBranchField = computed(() => activeTab().fields.some(f => f.key === 'branch_id'))

  function openCreate () {
    isEdit.value = false
    form.value = hasBranchField.value && auth.isBranchScoped ? { branch_id: auth.branchId } : {}
    errorMessage.value = null
    dialog.value = true
  }

  function openEdit (row) {
    isEdit.value = true
    form.value = { ...row }
    if (hasBranchField.value && auth.isBranchScoped) {
      form.value.branch_id = auth.branchId
    }
    errorMessage.value = null
    dialog.value = true
  }

  async function submit () {
    saving.value = true
    errorMessage.value = null
    try {
      await (isEdit.value ? activeTab().api.update(form.value.id, form.value) : activeTab().api.create(form.value))
      dialog.value = false
      await loadTab()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('masterData.saveFailed')
    } finally {
      saving.value = false
    }
  }

  // -- Delete --
  const deleteDialog = ref(false)
  const deleting = ref(false)
  const rowToDelete = ref(null)

  function confirmDelete (row) {
    rowToDelete.value = row
    deleteDialog.value = true
  }

  async function doDelete () {
    deleting.value = true
    try {
      await activeTab().api.remove(rowToDelete.value.id)
      deleteDialog.value = false
      await loadTab()
    } finally {
      deleting.value = false
    }
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ t('masterData.title') }}</h1>
      <v-spacer />

      <v-btn
        v-if="canManage && tab === 'drivers'"
        class="mr-2"
        :loading="syncing"
        prepend-icon="mdi-sync"
        variant="tonal"
        @click="openSync"
      >{{ t('masterData.syncFromSyop') }}</v-btn>

      <v-btn v-if="canManage" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('masterData.add') }}</v-btn>
    </div>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab v-for="item in tabs" :key="item.value" :value="item.value">{{ item.label }}</v-tab>
    </v-tabs>

    <v-alert
      v-if="syncMessage && tab === 'drivers'"
      class="mb-4"
      closable
      :type="syncError ? 'error' : 'success'"
      variant="tonal"
      @click:close="syncMessage = null"
    >{{ syncMessage }}</v-alert>

    <v-card :loading="loading">
      <v-table>
        <thead>
          <tr>
            <th>{{ t('common.rowNo') }}</th>
            <th v-for="col in activeTab().columns" :key="col.key">{{ col.label }}</th>
            <th v-if="canManage" class="text-right">{{ t('common.actions') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(row, index) in rows" :key="row.id">
            <td>{{ index + 1 }}</td>

            <td v-for="col in activeTab().columns" :key="col.key">
              <StatusChip v-if="col.key === 'status'" :status="row[col.key]" />

              <span
                v-else-if="col.key === 'stock_qty' && row.is_below_minimum_stock"
                class="text-error font-weight-medium"
              >
                <v-icon icon="mdi-alert-circle-outline" size="small" start />{{ row[col.key] ?? '-' }}
              </span>

              <span v-else-if="col.key === 'unit_cost'">{{ formatCurrency(row[col.key]) }}</span>

              <span v-else>{{ row[col.key] ?? '-' }}</span>
            </td>

            <td v-if="canManage" class="text-right">
              <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(row)" />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" @click="confirmDelete(row)" />
            </td>
          </tr>

          <tr v-if="rows.length === 0">
            <td class="text-medium-emphasis" :colspan="activeTab().columns.length + 1 + (canManage ? 1 : 0)">{{ t('masterData.noData') }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="480">
      <v-card>
        <v-card-title>{{ isEdit ? `${t('masterData.edit')} ${activeTab().label}` : `${t('masterData.add')} ${activeTab().label}` }}</v-card-title>

        <v-card-text>
          <v-alert v-if="errorMessage" class="mb-4" type="error" variant="tonal">{{ errorMessage }}</v-alert>

          <template v-for="field in visibleFields" :key="field.key">
            <v-select
              v-if="field.type === 'select'"
              v-model="form[field.key]"
              :disabled="field.key === 'branch_id' && auth.isBranchScoped"
              :item-title="field.optionsSource ? 'name' : 'title'"
              :item-value="field.optionsSource ? 'id' : 'value'"
              :items="optionsFor(field)"
              :label="field.label"
              :required="field.required"
            />

            <v-text-field
              v-else
              v-model="form[field.key]"
              :label="field.label"
              :readonly="field.readonly"
              :required="field.required"
              :type="field.type === 'number' ? 'number' : (field.type === 'date' ? 'date' : 'text')"
            />
          </template>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">{{ t('common.cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" variant="flat" @click="submit">{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="syncBranchDialog" max-width="420">
      <v-card>
        <v-card-title>{{ t('masterData.syncFromSyop') }}</v-card-title>

        <v-card-text>
          <v-select
            v-model="syncBranchId"
            item-title="name"
            item-value="id"
            :items="refData.branches"
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

    <ConfirmDialog
      v-model="deleteDialog"
      confirm-color="error"
      :confirm-text="t('common.delete')"
      :loading="deleting"
      :message="t('masterData.confirmDelete', { label: activeTab().label.toLowerCase(), name: rowToDelete?.name ?? rowToDelete?.code })"
      :title="t('masterData.deleteTitle')"
      @confirm="doDelete"
    />
  </div>
</template>
