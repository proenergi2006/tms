<script setup>
  import { computed, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { requestsApi } from '@/api/maintenance'
  import { fleetsApi } from '@/api/masterData'
  import StatusChip from '@/components/StatusChip.vue'
  import { useAuthStore } from '@/stores/auth'
  import { formatDateTime } from '@/utils/format'

  // Daftar Pengajuan — Wireframe Document Bagian 2.2. Buat/Ubah Pengajuan
  // adalah HALAMAN PENUH tersendiri (lihat RequestForm.vue, route
  // /requests/new & /requests/:id/edit), bukan modal/dialog — supaya SA
  // punya ruang cukup mengisi laporan temuan lengkap tanpa modal bersarang.
  const router = useRouter()
  const auth = useAuthStore()
  const { t, tm } = useI18n()

  const items = ref([])
  const totalItems = ref(0)
  const loading = ref(false)
  const page = ref(1)
  const itemsPerPage = ref(15)

  const filters = ref({ status: null, fleet_id: null, type: null, priority: null })
  const fleets = ref([])

  // tm() (bukan t() dengan returnObjects) untuk mengambil objek mentah dari
  // locale — t() di Composition API vue-i18n selalu mengembalikan string,
  // returnObjects tidak berlaku di situ (silakan lihat memory/gotcha).
  const statusOptions = computed(() => Object.entries(tm('enums.requestStatus'))
    .map(([value, title]) => ({ title, value })))
  const typeOptions = computed(() => Object.entries(tm('enums.requestType'))
    .map(([value, title]) => ({ title, value })))
  const priorityOptions = computed(() => Object.entries(tm('enums.priority'))
    .map(([value, title]) => ({ title, value })))

  const headers = computed(() => [
    { title: t('common.rowNo'), key: 'no', sortable: false, width: 56 },
    { title: t('requests.requestNo'), key: 'request_no' },
    { title: t('requests.type'), key: 'type' },
    { title: t('requests.priority'), key: 'priority' },
    { title: t('requests.fleet'), key: 'fleet' },
    { title: t('common.status'), key: 'status' },
    { title: t('common.date'), key: 'created_at' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
  ])

  function priorityColor (priority) {
    return { high: 'error', medium: 'warning', low: 'default' }[priority] ?? 'default'
  }

  async function loadRequests () {
    loading.value = true
    try {
      const { data } = await requestsApi.list({
        page: page.value,
        per_page: itemsPerPage.value,
        ...filters.value,
      })
      items.value = data.data
      totalItems.value = data.meta?.total ?? data.data.length
    } finally {
      loading.value = false
    }
  }

  function applyFilters () {
    page.value = 1
    loadRequests()
  }

  function openWorkOrder (item) {
    if (item.work_order_id) {
      router.push(`/work-orders/${item.work_order_id}`)
    }
  }

  // SA hanya bisa mengajukan ulang pengajuan MILIK SENDIRI yang ditolak —
  // sejalan dengan otorisasi RequestController::resubmit() (permission
  // request.create + requested_by === user login, bukan request.edit
  // milik Fleet Operations).
  function canResubmit (item) {
    return item.status === 'rejected'
      && auth.hasPermission('request.create')
      && item.requested_by === auth.user?.id
  }

  onMounted(async () => {
    const { data } = await fleetsApi.list({ per_page: 100 })
    fleets.value = data.data
    await loadRequests()
  })
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ t('requests.title') }}</h1>
      <v-spacer />

      <v-btn
        v-if="auth.hasPermission('request.create')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="router.push('/requests/new')"
      >
        {{ t('requests.create') }}
      </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              clearable
              density="compact"
              :items="statusOptions"
              :label="t('common.status')"
              @update:model-value="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="filters.fleet_id"
              clearable
              density="compact"
              item-title="plate_number"
              item-value="id"
              :items="fleets"
              :label="t('requests.fleet')"
              @update:model-value="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="filters.type"
              clearable
              density="compact"
              :items="typeOptions"
              :label="t('requests.type')"
              @update:model-value="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="filters.priority"
              clearable
              density="compact"
              :items="priorityOptions"
              :label="t('requests.priority')"
              @update:model-value="applyFilters"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-data-table-server
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="items"
        :items-length="totalItems"
        :loading="loading"
        @update:options="loadRequests"
      >
        <template #item.no="{ index }">
          {{ (page - 1) * itemsPerPage + index + 1 }}
        </template>

        <template #item.fleet="{ item }">
          {{ item.fleet?.plate_number ?? '-' }}
        </template>

        <template #item.priority="{ item }">
          <v-chip :color="priorityColor(item.priority)" size="small" variant="tonal">{{ t(`enums.priority.${item.priority}`) }}</v-chip>
        </template>

        <template #item.status="{ item }">
          <StatusChip :status="item.status" />

          <div v-if="item.waiting_approval_label" class="text-caption text-medium-emphasis mt-1">
            {{ t('requests.waitingApproval', { role: item.waiting_approval_label }) }}
          </div>
        </template>

        <template #item.created_at="{ item }">
          {{ formatDateTime(item.created_at) }}
        </template>

        <template #item.actions="{ item }">
          <v-btn
            v-if="auth.hasPermission('request.edit')"
            size="small"
            variant="text"
            @click="router.push(`/requests/${item.id}/edit`)"
          >{{ t('common.edit') }}</v-btn>

          <v-btn
            v-if="canResubmit(item)"
            color="primary"
            size="small"
            variant="text"
            @click="router.push(`/requests/${item.id}/resubmit`)"
          >{{ t('requests.resubmit') }}</v-btn>

          <v-btn size="small" variant="text" @click="openWorkOrder(item)">{{ t('common.view') }}</v-btn>
        </template>
      </v-data-table-server>
    </v-card>
  </div>
</template>
