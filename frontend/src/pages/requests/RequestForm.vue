<script setup>
  import { computed, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute, useRouter } from 'vue-router'
  import { requestsApi } from '@/api/maintenance'
  import { fleetsApi, mechanicsApi, sparepartsApi, vendorsApi } from '@/api/masterData'
  import { useSidebarCountsStore } from '@/stores/sidebarCounts'
  import { formatCurrency } from '@/utils/format'

  // Halaman Buat/Ubah/Ajukan-Ulang Pengajuan — halaman penuh (pindah URL),
  // BUKAN modal. Dipakai untuk tiga alur: SA membuat pengajuan+WO baru
  // (POST /requests, route /requests/new), Fleet Operations mengubah
  // pengajuan yang sedang di tahapnya (PATCH /requests/{id}, permission
  // request.edit, route /requests/:id/edit), dan SA mengajukan ulang
  // pengajuan MILIK SENDIRI yang ditolak (POST /requests/{id}/resubmit,
  // route /requests/:id/resubmit — lihat RequestController::resubmit()) —
  // komponen yang sama, dibedakan lewat isEditMode/isResubmitMode
  // (dari path route, bukan cuma ada/tidaknya :id yang dipakai kedua mode
  // itu). FO SELENGKAP SA: rincian item & foto juga bisa dilihat/diedit/
  // ditambah saat mode edit/resubmit — lihat RequestController::
  // update()/resubmit()/storeAttachment() (backend).
  const route = useRoute()
  const router = useRouter()
  const sidebarCounts = useSidebarCountsStore()
  const { t, tm } = useI18n()

  const isResubmitMode = computed(() => route.path.endsWith('/resubmit'))
  const isEditMode = computed(() => !!route.params.id && !isResubmitMode.value)
  const hasExistingRequest = computed(() => !!route.params.id)

  const saving = ref(false)
  const loadingForm = ref(false)
  const errorMessage = ref(null)

  const fleets = ref([])
  const spareparts = ref([])
  const mechanics = ref([])
  const vendors = ref([])

  const typeOptions = computed(() => Object.entries(tm('enums.requestType'))
    .map(([value, title]) => ({ title, value })))
  const priorityOptions = computed(() => Object.entries(tm('enums.priority'))
    .map(([value, title]) => ({ title, value })))
  const maintenanceNatureOptions = computed(() => Object.entries(tm('enums.maintenanceNature'))
    .map(([value, title]) => ({ title, value })))

  function emptyForm () {
    return {
      type: null,
      maintenance_nature: null,
      priority: 'medium',
      fleet_id: null,
      diagnosis: '',
      description: '',
      estimated_days: null,
      odometer_km: null,
      trouble_date: null,
      suggestion: '',
      action_taken: '',
      execution_type: null,
      mechanic_id: null,
      vendor_id: null,
      items: [],
    }
  }
  const form = ref(emptyForm())

  // -- Kilometer: bukan input teks yang langsung terbuka, tapi baris "tap"
  // yang menampilkan nilai saat ini + tombol untuk membuka input set-nilai
  // secara inline (bukan dialog bersarang). --
  const odometerEditing = ref(false)
  function openOdometerInput () {
    odometerEditing.value = true
  }
  function confirmOdometer () {
    odometerEditing.value = false
  }

  // maintenance_nature, No. TAR, & Diagnosa hanya relevan (dan wajib) untuk
  // pengajuan perbaikan — lihat StoreRequestRequest (backend).
  const perbaikanRequired = computed(() => form.value.type === 'perbaikan')

  // Detail armada yang dipilih — ditampilkan langsung (model, tipe, merk, dst)
  // begitu plat dipilih, mirip kartu di master data, supaya SA yakin armada
  // yang benar sebelum submit.
  const selectedFleet = computed(() => fleets.value.find(f => f.id === form.value.fleet_id) ?? null)

  // -- Foto/lampiran (bisa lebih dari satu, dengan keterangan per foto) --
  // Tiap entri: { file, caption, previewUrl }. previewUrl dibuat via
  // URL.createObjectURL supaya foto tampil langsung sebelum diunggah, lalu
  // di-revoke saat dihapus/selesai submit agar tidak bocor memori.
  const photos = ref([])
  const photoInput = ref([])

  // Alasan penolakan tahap terakhir — cuma relevan & ditampilkan di mode
  // resubmit, supaya SA langsung tahu apa yang perlu diperbaiki tanpa
  // harus buka Detail Work Order dulu.
  const rejectionReason = ref(null)

  // Foto yang SUDAH diunggah sebelumnya (mis. oleh SA saat membuat) —
  // ditampilkan saat FO membuka mode edit, murni tampilan (tidak bisa
  // dihapus lewat halaman ini), supaya FO lihat konteks lengkap sebelum
  // menambah foto baru.
  const existingAttachments = ref([])

  function onPhotosSelected (files) {
    const list = Array.isArray(files) ? files : (files ? [files] : [])
    for (const file of list) {
      photos.value.push({ file, caption: '', previewUrl: URL.createObjectURL(file) })
    }
    photoInput.value = []
  }

  function removePhoto (index) {
    URL.revokeObjectURL(photos.value[index].previewUrl)
    photos.value.splice(index, 1)
  }

  // -- Item sparepart/biaya awal WO --
  function addItemRow () {
    form.value.items.push({ sparepart_id: null, description: '', qty: 1, unit_cost: 0 })
  }

  function removeItemRow (index) {
    form.value.items.splice(index, 1)
  }

  // Mengisi deskripsi & harga satuan otomatis dari sparepart yang dipilih —
  // pola sama seperti "+ Tambah Item" di WorkOrderDetail.vue.
  function onItemSparepartSelect (row, sparepartId) {
    const sparepart = spareparts.value.find(s => s.id === sparepartId)
    if (sparepart) {
      row.description = sparepart.name
      row.unit_cost = Number(sparepart.unit_cost) || 0
    }
  }

  // Vendor eksternal pakai sparepart sendiri — kalau SA beralih dari
  // internal ke eksternal setelah sempat memilih sparepart dari master
  // data, lepas tautannya supaya item jadi murni free-text (tidak nyangkut
  // ke stok gudang kita, lihat WorkOrderItemService).
  function onExecutionTypeChange (value) {
    if (value === 'eksternal') {
      for (const row of form.value.items) row.sparepart_id = null
    }
  }

  const itemsTotal = computed(
    () => form.value.items.reduce((sum, item) => sum + (Number(item.qty) || 0) * (Number(item.unit_cost) || 0), 0),
  )

  const canSubmit = computed(() => {
    if (!form.value.type || !form.value.description) return false
    if (perbaikanRequired.value) {
      if (!form.value.diagnosis || !form.value.maintenance_nature) return false
      if (form.value.estimated_days === null || form.value.estimated_days === '') return false
      if (form.value.odometer_km === null || form.value.odometer_km === '') return false
      if (!form.value.trouble_date || !form.value.suggestion || !form.value.action_taken) return false
      if (!form.value.execution_type) return false
      if (form.value.execution_type === 'internal' && !form.value.mechanic_id) return false
      if (form.value.execution_type === 'eksternal' && !form.value.vendor_id) return false
    }
    return true
  })

  function goBack () {
    router.push('/requests')
  }

  async function submit () {
    saving.value = true
    errorMessage.value = null
    try {
      // PATCH (edit), POST resubmit, & POST create sama-sama menerima items
      // sekarang — FO bisa mengoreksi rincian item sama seperti SA (lihat
      // RequestController::update()/resubmit()).
      let requestId = route.params.id
      if (isResubmitMode.value) {
        await requestsApi.resubmit(requestId, form.value)
      } else if (isEditMode.value) {
        await requestsApi.update(requestId, form.value)
      } else {
        const { data } = await requestsApi.create(form.value)
        requestId = data.data.id
      }

      // Unggah foto baru (beserta keterangan) — berlaku di kedua mode.
      for (const photo of photos.value) {
        await requestsApi.uploadAttachment(requestId, photo.file, photo.caption)
      }

      // Buat & ajukan-ulang menambah pengajuan submitted baru (FO edit
      // TIDAK mengubah status, jadi tidak perlu) — segarkan badge sidebar
      // supaya tidak menunggu polling 60 detik.
      if (!isEditMode.value) {
        sidebarCounts.refreshRequestsSubmitted()
        sidebarCounts.refreshApprovalPending()
      }

      goBack()
    } catch (error) {
      errorMessage.value = error.response?.data?.message ?? t('requests.saveFailed')
    } finally {
      saving.value = false
    }
  }

  onMounted(async () => {
    loadingForm.value = true
    try {
      const [fleetsRes, sparepartsRes, mechanicsRes, vendorsRes] = await Promise.all([
        fleetsApi.list({ per_page: 100 }),
        sparepartsApi.list({ per_page: 100 }),
        mechanicsApi.list({ per_page: 100 }),
        vendorsApi.list({ per_page: 100 }),
      ])
      fleets.value = fleetsRes.data.data
      spareparts.value = sparepartsRes.data.data
      mechanics.value = mechanicsRes.data.data
      vendors.value = vendorsRes.data.data

      if (hasExistingRequest.value) {
        const { data } = await requestsApi.get(route.params.id)
        const req = data.data
        form.value = {
          type: req.type,
          maintenance_nature: req.maintenance_nature,
          priority: req.priority,
          fleet_id: req.fleet_id ?? req.fleet?.id ?? null,
          diagnosis: req.diagnosis ?? '',
          description: req.description ?? '',
          estimated_days: req.estimated_days ?? null,
          odometer_km: req.odometer_km ?? null,
          trouble_date: req.trouble_date ?? null,
          suggestion: req.suggestion ?? '',
          action_taken: req.action_taken ?? '',
          execution_type: req.execution_type ?? null,
          mechanic_id: req.mechanic_id ?? null,
          vendor_id: req.vendor_id ?? null,
          items: (req.items ?? []).map(item => ({
            sparepart_id: item.sparepart_id,
            description: item.description,
            qty: item.qty,
            unit_cost: item.unit_cost,
          })),
        }
        existingAttachments.value = req.attachments ?? []
        rejectionReason.value = req.rejection_reason ?? null
      }
    } finally {
      loadingForm.value = false
    }
  })
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4 ga-2">
      <v-btn icon="mdi-arrow-left" variant="text" @click="goBack" />

      <h1 class="text-h5">
        {{ isResubmitMode ? t('requests.resubmitTitle') : (isEditMode ? t('requests.editTitle') : t('requests.create')) }}
      </h1>
    </div>

    <v-card :loading="loadingForm">
      <v-card-text class="pa-6">
        <v-alert v-if="isResubmitMode && rejectionReason" class="mb-4" type="warning" variant="tonal">
          <div class="font-weight-medium mb-1">{{ t('requests.rejectionReasonLabel') }}</div>
          {{ rejectionReason }}
        </v-alert>

        <v-alert v-if="errorMessage" class="mb-4" type="error" variant="tonal">{{ errorMessage }}</v-alert>

        <v-select v-model="form.type" :items="typeOptions" :label="t('requests.typeLabel')" />

        <v-select
          v-if="perbaikanRequired"
          v-model="form.maintenance_nature"
          :items="maintenanceNatureOptions"
          :label="t('requests.maintenanceNature')"
        />

        <v-select v-model="form.priority" :items="priorityOptions" :label="t('requests.priority')" />

        <v-select
          v-model="form.fleet_id"
          clearable
          item-title="plate_number"
          item-value="id"
          :items="fleets"
          :label="t('requests.fleetOptional')"
        />

        <v-card v-if="selectedFleet" class="mb-4" variant="tonal">
          <v-card-text class="py-3">
            <div class="text-subtitle-2 mb-2">
              <v-icon icon="mdi-truck-outline" size="small" start />{{ t('fleets.vehicleInfo') }}
            </div>

            <v-row dense>
              <v-col cols="6" sm="4">
                <div class="text-caption text-medium-emphasis">{{ t('fleets.plateNumber') }}</div>
                <div class="font-weight-medium">{{ selectedFleet.plate_number }}</div>
              </v-col>

              <v-col cols="6" sm="4">
                <div class="text-caption text-medium-emphasis">{{ t('fleets.fleetType') }}</div>
                <div class="font-weight-medium">{{ selectedFleet.fleet_type ?? '-' }}</div>
              </v-col>

              <v-col cols="6" sm="4">
                <div class="text-caption text-medium-emphasis">{{ t('fleets.brand') }}</div>
                <div class="font-weight-medium">{{ selectedFleet.brand ?? '-' }}</div>
              </v-col>

              <v-col cols="6" sm="4">
                <div class="text-caption text-medium-emphasis">{{ t('fleets.model') }}</div>
                <div class="font-weight-medium">{{ selectedFleet.model ?? '-' }}</div>
              </v-col>

              <v-col cols="6" sm="4">
                <div class="text-caption text-medium-emphasis">{{ t('fleets.year') }}</div>
                <div class="font-weight-medium">{{ selectedFleet.year ?? '-' }}</div>
              </v-col>

              <v-col cols="6" sm="4">
                <div class="text-caption text-medium-emphasis">{{ t('fleets.capacity') }}</div>
                <div class="font-weight-medium">{{ selectedFleet.capacity ?? '-' }}</div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-textarea
          v-if="perbaikanRequired"
          v-model="form.diagnosis"
          :label="t('requests.diagnosis')"
          rows="3"
        />

        <v-text-field
          v-if="perbaikanRequired"
          v-model.number="form.estimated_days"
          :label="t('requests.estimatedDays')"
          min="1"
          type="number"
        />

        <!-- Kilometer: baris "tap" (bukan input yang langsung terbuka),
             menampilkan nilai saat ini sampai diketuk — baru kemudian
             tampil input untuk Set Value, inline (bukan dialog bersarang). -->
        <div v-if="perbaikanRequired" class="mb-4">
          <div class="text-caption text-medium-emphasis mb-1">{{ t('requests.odometer') }}</div>

          <v-sheet
            v-if="!odometerEditing"
            class="d-flex align-center justify-space-between pa-3 cursor-pointer"
            rounded="lg"
            variant="tonal"
            @click="openOdometerInput"
          >
            <span v-if="form.odometer_km !== null && form.odometer_km !== ''" class="font-weight-medium">
              {{ form.odometer_km }} km
            </span>

            <span v-else class="text-medium-emphasis">{{ t('requests.odometerNotSet') }}</span>

            <v-btn size="small" variant="text" @click.stop="openOdometerInput">{{ t('requests.setValue') }}</v-btn>
          </v-sheet>

          <div v-else class="d-flex align-center ga-2">
            <v-text-field
              v-model.number="form.odometer_km"
              autofocus
              density="compact"
              hide-details
              :label="t('requests.odometer')"
              min="0"
              suffix="km"
              type="number"
              @keyup.enter="confirmOdometer"
            />

            <v-btn color="primary" size="small" variant="flat" @click="confirmOdometer">{{ t('requests.setValue') }}</v-btn>
          </div>
        </div>

        <v-text-field
          v-if="perbaikanRequired"
          v-model="form.trouble_date"
          :label="t('requests.troubleDate')"
          type="date"
        />

        <v-textarea
          v-if="perbaikanRequired"
          v-model="form.suggestion"
          :label="t('requests.suggestion')"
          rows="2"
        />

        <v-textarea
          v-if="perbaikanRequired"
          v-model="form.action_taken"
          :label="t('requests.actionTaken')"
          rows="2"
        />

        <v-radio-group
          v-if="perbaikanRequired"
          v-model="form.execution_type"
          inline
          :label="t('requests.executionType')"
          @update:model-value="onExecutionTypeChange"
        >
          <v-radio :label="t('workOrder.internal')" value="internal" />
          <v-radio :label="t('workOrder.external')" value="eksternal" />
        </v-radio-group>

        <v-select
          v-if="perbaikanRequired && form.execution_type === 'internal'"
          v-model="form.mechanic_id"
          item-title="name"
          item-value="id"
          :items="mechanics"
          :label="t('workOrder.mechanic')"
        />

        <v-select
          v-if="perbaikanRequired && form.execution_type === 'eksternal'"
          v-model="form.vendor_id"
          item-title="name"
          item-value="id"
          :items="vendors"
          :label="t('workOrder.vendor')"
        />

        <v-textarea v-model="form.description" :label="t('requests.description')" rows="3" />

        <!-- Rincian item & foto — FO bisa melihat/mengedit/menambah sama
             seperti SA (endpoint PATCH & upload lampiran sudah menerima
             keduanya, lihat RequestController::update()/storeAttachment()). -->
        <v-divider class="my-4" />

        <div class="d-flex align-center mb-2">
          <span class="text-subtitle-2">{{ t('workOrder.costDetails') }}</span>
          <v-spacer />
          <v-btn prepend-icon="mdi-plus" size="small" variant="text" @click="addItemRow">{{ t('workOrder.addItem') }}</v-btn>
        </div>

        <v-table v-if="form.items.length > 0" density="compact">
          <thead>
            <tr>
              <th v-if="form.execution_type !== 'eksternal'">{{ t('masterData.tabSpareparts') }}</th>
              <th>{{ t('workOrder.description') }}</th>
              <th class="text-right">{{ t('workOrder.qty') }}</th>
              <th class="text-right">{{ t('workOrder.unitPrice') }}</th>
              <th class="text-right">{{ t('workOrder.total') }}</th>
              <th />
            </tr>
          </thead>

          <tbody>
            <tr v-for="(row, index) in form.items" :key="index">
              <td v-if="form.execution_type !== 'eksternal'" style="min-width: 160px;">
                <v-select
                  v-model="row.sparepart_id"
                  clearable
                  density="compact"
                  hide-details
                  item-title="name"
                  item-value="id"
                  :items="spareparts"
                  variant="underlined"
                  @update:model-value="value => onItemSparepartSelect(row, value)"
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

              <td>
                <v-btn icon="mdi-close" size="x-small" variant="text" @click="removeItemRow(index)" />
              </td>
            </tr>
          </tbody>

          <tfoot>
            <tr>
              <td class="font-weight-bold" colspan="4">{{ t('workOrder.totalCost') }}</td>
              <td class="text-right font-weight-bold">{{ formatCurrency(itemsTotal) }}</td>
              <td />
            </tr>
          </tfoot>
        </v-table>

        <div v-else class="text-body-2 text-medium-emphasis">{{ t('workOrder.noCostDetails') }}</div>

        <v-divider class="my-4" />

        <div class="text-subtitle-2 mb-2">{{ t('requests.photos') }}</div>

        <v-row v-if="existingAttachments.length > 0" class="mb-2" dense>
          <v-col v-for="att in existingAttachments" :key="att.id" cols="6" sm="4">
            <v-card :href="att.url" target="_blank" variant="outlined">
              <v-img aspect-ratio="1.4" cover :src="att.url" />
              <v-card-text class="pa-2 text-caption text-truncate">{{ att.caption || t('requests.photos') }}</v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-file-input
          v-model="photoInput"
          accept="image/*"
          capture="environment"
          density="compact"
          hide-details
          :label="t('requests.addPhotos')"
          multiple
          prepend-icon="mdi-camera"
          show-size
          @update:model-value="onPhotosSelected"
        />

        <v-row v-if="photos.length > 0" class="mt-2" dense>
          <v-col v-for="(photo, index) in photos" :key="index" cols="12" sm="6">
            <v-card variant="outlined">
              <v-img aspect-ratio="1.6" cover :src="photo.previewUrl" />

              <v-card-text class="pa-2">
                <v-text-field
                  v-model="photo.caption"
                  density="compact"
                  hide-details
                  :label="t('requests.photoCaption')"
                  variant="underlined"
                />
              </v-card-text>

              <v-card-actions class="pt-0">
                <v-spacer />

                <v-btn
                  color="error"
                  prepend-icon="mdi-delete-outline"
                  size="small"
                  variant="text"
                  @click="removePhoto(index)"
                >
                  {{ t('common.delete') }}
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="goBack">{{ t('common.cancel') }}</v-btn>

        <v-btn
          color="primary"
          :disabled="!canSubmit"
          :loading="saving"
          variant="flat"
          @click="submit"
        >
          {{ isResubmitMode ? t('requests.resubmit') : (isEditMode ? t('common.save') : t('requests.submit')) }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>
