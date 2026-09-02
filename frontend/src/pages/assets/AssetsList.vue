<script setup>
  import { onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { assetsApi } from '@/api/assets'
  import ConfirmDialog from '@/components/ConfirmDialog.vue'
  import StatusChip from '@/components/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'

  // Asset Registry (IT & GA) — Wireframe Document Bagian 2.6.
  const auth = useAuthStore()
  const { t } = useI18n()
  const assets = ref([])
  const loading = ref(false)
  const category = ref(null)
  const search = ref('')

  async function load () {
    loading.value = true
    try {
      const { data } = await assetsApi.list({ category: category.value, per_page: 100 })
      assets.value = data.data
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  const dialog = ref(false)
  const saving = ref(false)
  const isEdit = ref(false)
  const form = ref({ id: null, asset_code: '', category: 'IT', name: '', location: '', purchase_date: '', status: 'aktif' })

  function openCreate () {
    isEdit.value = false
    form.value = { id: null, asset_code: '', category: 'IT', name: '', location: '', purchase_date: '', status: 'aktif' }
    dialog.value = true
  }

  function openEdit (asset) {
    isEdit.value = true
    form.value = { ...asset }
    dialog.value = true
  }

  async function submit () {
    saving.value = true
    try {
      await (isEdit.value ? assetsApi.update(form.value.id, form.value) : assetsApi.create(form.value))
      dialog.value = false
      await load()
    } finally {
      saving.value = false
    }
  }

  const categories = ['IT', 'GA']
  const statuses = ['aktif', 'rusak', 'dihapuskan']

  // -- Delete --
  const deleteDialog = ref(false)
  const deleting = ref(false)
  const assetToDelete = ref(null)

  function confirmDelete (asset) {
    assetToDelete.value = asset
    deleteDialog.value = true
  }

  async function doDelete () {
    deleting.value = true
    try {
      await assetsApi.remove(assetToDelete.value.id)
      deleteDialog.value = false
      await load()
    } finally {
      deleting.value = false
    }
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4 flex-wrap ga-2">
      <h1 class="text-h5">{{ t('assets.title') }}</h1>
      <v-spacer />

      <v-select
        v-model="category"
        clearable
        density="compact"
        hide-details
        :items="categories"
        :label="t('assets.category')"
        style="max-width:160px"
        @update:model-value="load"
      />

      <v-text-field
        v-model="search"
        density="compact"
        hide-details
        prepend-inner-icon="mdi-magnify"
        style="max-width:220px"
      />

      <v-btn
        v-if="auth.hasPermission('asset.manage')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        {{ t('assets.register') }}
      </v-btn>
    </div>

    <v-card :loading="loading">
      <v-table>
        <thead>
          <tr>
            <th>{{ t('common.rowNo') }}</th><th>{{ t('assets.assetCode') }}</th><th>{{ t('assets.category') }}</th><th>{{ t('assets.name') }}</th><th>{{ t('assets.location') }}</th><th>{{ t('common.status') }}</th>
            <th v-if="auth.hasPermission('asset.manage')" class="text-right">{{ t('common.actions') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(asset, index) in assets.filter(a => !search || a.name.toLowerCase().includes(search.toLowerCase()) || a.asset_code.toLowerCase().includes(search.toLowerCase()))"
            :key="asset.id"
            :style="auth.hasPermission('asset.manage') ? 'cursor:pointer' : ''"
            @click="auth.hasPermission('asset.manage') && openEdit(asset)"
          >
            <td>{{ index + 1 }}</td>
            <td>{{ asset.asset_code }}</td>
            <td>{{ asset.category }}</td>
            <td>{{ asset.name }}</td>
            <td>{{ asset.location ?? '-' }}</td>
            <td><StatusChip :status="asset.status" /></td>

            <td v-if="auth.hasPermission('asset.manage')" class="text-right">
              <v-btn icon="mdi-delete-outline" size="small" variant="text" @click.stop="confirmDelete(asset)" />
            </td>
          </tr>

          <tr v-if="assets.length === 0"><td class="text-medium-emphasis" colspan="7">{{ t('assets.noAssets') }}</td></tr>
        </tbody>
      </v-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="480">
      <v-card>
        <v-card-title>{{ isEdit ? t('assets.editAsset') : t('assets.register') }}</v-card-title>

        <v-card-text>
          <v-text-field v-model="form.asset_code" :label="t('assets.assetCode')" />
          <v-select v-model="form.category" :items="categories" :label="t('assets.category')" />
          <v-text-field v-model="form.name" :label="t('assets.assetName')" />
          <v-text-field v-model="form.location" :label="`${t('assets.location')} ${t('common.optional')}`" />
          <v-text-field v-model="form.purchase_date" :label="`${t('assets.purchaseDate')} ${t('common.optional')}`" type="date" />
          <v-select v-if="isEdit" v-model="form.status" :items="statuses.map(s => ({ title: t(`enums.status.${s}`), value: s }))" :label="t('common.status')" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn
            color="primary"
            :disabled="!form.asset_code || !form.name"
            :loading="saving"
            variant="flat"
            @click="submit"
          >
            {{ t('common.save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ConfirmDialog
      v-model="deleteDialog"
      confirm-color="error"
      :confirm-text="t('common.delete')"
      :loading="deleting"
      :message="t('assets.confirmDelete', { name: assetToDelete?.name, code: assetToDelete?.asset_code })"
      :title="t('assets.deleteTitle')"
      @confirm="doDelete"
    />
  </div>
</template>
