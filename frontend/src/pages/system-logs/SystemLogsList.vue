<script setup>
  import { computed, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { systemLogsApi } from '@/api/systemLogs'
  import { formatDateTime } from '@/utils/format'

  // Log aplikasi (Laravel log) — supaya Admin Sistem tahu kalau ada error di
  // server tanpa akses SSH. Khusus permission system-log.view. Hanya baca,
  // diambil langsung dari file log server (lihat SystemLogReader backend).
  const { t } = useI18n()
  const items = ref([])
  const totalItems = ref(0)
  const loading = ref(false)
  const page = ref(1)
  const itemsPerPage = ref(20)

  const filters = ref({ level: null, search: '', date_from: '', date_to: '' })

  const levelOptions = computed(() => [
    'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
  ].map(level => ({ title: level.toUpperCase(), value: level })))

  const headers = computed(() => [
    { title: t('common.rowNo'), key: 'no', sortable: false, width: 56 },
    { title: t('systemLogs.time'), key: 'timestamp', sortable: false, width: 180 },
    { title: t('systemLogs.level'), key: 'level', sortable: false, width: 120 },
    { title: t('systemLogs.message'), key: 'message', sortable: false },
    { title: '', key: 'actions', sortable: false, width: 56 },
  ])

  function levelColor (level) {
    if (['emergency', 'alert', 'critical', 'error'].includes(level)) return 'error'
    if (level === 'warning') return 'warning'
    if (['notice', 'info'].includes(level)) return 'info'
    return 'grey'
  }

  async function load () {
    loading.value = true
    try {
      const { data } = await systemLogsApi.list({
        page: page.value,
        per_page: itemsPerPage.value,
        level: filters.value.level || undefined,
        search: filters.value.search || undefined,
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
      })
      items.value = data.data
      totalItems.value = data.meta?.total ?? data.data.length
    } finally {
      loading.value = false
    }
  }

  function applyFilters () {
    page.value = 1
    load()
  }

  const detailDialog = ref(false)
  const activeEntry = ref(null)

  function openDetail (entry) {
    activeEntry.value = entry
    detailDialog.value = true
  }
</script>

<template>
  <div>
    <h1 class="text-h5 mb-4">{{ t('systemLogs.title') }}</h1>

    <v-alert class="mb-4" type="info" variant="tonal">{{ t('systemLogs.info') }}</v-alert>

    <v-card class="mb-4">
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.level"
              clearable
              density="compact"
              :items="levelOptions"
              :label="t('systemLogs.level')"
              @update:model-value="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.search"
              clearable
              density="compact"
              :label="t('systemLogs.search')"
              prepend-inner-icon="mdi-magnify"
              @change="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.date_from"
              density="compact"
              :label="t('systemLogs.dateFrom')"
              type="date"
              @change="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.date_to"
              density="compact"
              :label="t('systemLogs.dateTo')"
              type="date"
              @change="applyFilters"
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
        :no-data-text="t('systemLogs.empty')"
        @update:options="load"
      >
        <template #item.no="{ index }">
          {{ (page - 1) * itemsPerPage + index + 1 }}
        </template>

        <template #item.timestamp="{ item }">{{ formatDateTime(item.timestamp) }}</template>

        <template #item.level="{ item }">
          <v-chip :color="levelColor(item.level)" size="small" variant="tonal">{{ item.level.toUpperCase() }}</v-chip>
        </template>

        <template #item.message="{ item }">
          <div class="text-truncate" style="max-width: 640px;">{{ item.message }}</div>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            v-if="item.detail"
            icon="mdi-text-box-search-outline"
            size="small"
            :title="t('systemLogs.detail')"
            variant="text"
            @click="openDetail(item)"
          />
        </template>
      </v-data-table-server>
    </v-card>

    <v-dialog v-model="detailDialog" max-width="900">
      <v-card>
        <v-card-title class="d-flex align-center">
          <v-chip
            v-if="activeEntry"
            class="mr-2"
            :color="levelColor(activeEntry.level)"
            size="small"
            variant="tonal"
          >{{ activeEntry.level.toUpperCase() }}</v-chip>

          <span>{{ activeEntry ? formatDateTime(activeEntry.timestamp) : '' }}</span>
        </v-card-title>

        <v-card-text>
          <p class="mb-3">{{ activeEntry?.message }}</p>

          <pre
            class="bg-grey-lighten-4 pa-3 rounded text-caption"
            style="white-space: pre-wrap; max-height: 50vh; overflow-y: auto;"
          >{{ activeEntry?.detail ?? t('systemLogs.noDetail') }}</pre>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="detailDialog = false">{{ t('common.close') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
