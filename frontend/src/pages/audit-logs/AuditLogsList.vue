<script setup>
  import { computed, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { auditLogsApi } from '@/api/audit'
  import { formatDateTime } from '@/utils/format'

  // Audit Log — Architecture Document Bagian 7.3, NFR-05 Auditability.
  // Khusus Admin Sistem (permission audit-log.view).
  const { t } = useI18n()
  const items = ref([])
  const totalItems = ref(0)
  const loading = ref(false)
  const page = ref(1)
  const itemsPerPage = ref(20)

  const filters = ref({ entity_type: '', action: '', date_from: '', date_to: '' })

  const headers = computed(() => [
    { title: t('common.rowNo'), key: 'no', sortable: false, width: 56 },
    { title: t('auditLog.time'), key: 'created_at', sortable: false },
    { title: t('auditLog.actor'), key: 'actor_name', sortable: false },
    { title: t('auditLog.action'), key: 'action', sortable: false },
    { title: t('auditLog.entity'), key: 'entity', sortable: false },
    { title: t('auditLog.changes'), key: 'diff', sortable: false },
  ])

  async function load () {
    loading.value = true
    try {
      const { data } = await auditLogsApi.list({
        page: page.value,
        per_page: itemsPerPage.value,
        entity_type: filters.value.entity_type || undefined,
        action: filters.value.action || undefined,
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

  // Meringkas before_data/after_data jadi daftar "field: sebelum -> sesudah"
  // untuk field yang nilainya benar-benar berubah.
  function diffEntries (log) {
    const before = log.before_data ?? {}
    const after = log.after_data ?? {}
    const keys = new Set([...Object.keys(before), ...Object.keys(after)])

    return [...keys]
      .filter(key => JSON.stringify(before[key]) !== JSON.stringify(after[key]))
      .map(key => ({ key, before: before[key] ?? '-', after: after[key] ?? '-' }))
  }
</script>

<template>
  <div>
    <h1 class="text-h5 mb-4">{{ t('auditLog.title') }}</h1>

    <v-card class="mb-4">
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.entity_type"
              density="compact"
              :label="t('auditLog.entityType')"
              placeholder="work_order"
              @change="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.action"
              density="compact"
              :label="t('auditLog.action')"
              placeholder="work_order.approve"
              @change="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.date_from"
              density="compact"
              :label="t('auditLog.dateFrom')"
              type="date"
              @change="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.date_to"
              density="compact"
              :label="t('auditLog.dateTo')"
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
        @update:options="load"
      >
        <template #item.no="{ index }">
          {{ (page - 1) * itemsPerPage + index + 1 }}
        </template>

        <template #item.actor_name="{ item }">
          {{ item.actor_name ?? t('auditLog.system') }}
        </template>

        <template #item.action="{ item }">
          <v-chip size="small" variant="tonal">{{ item.action }}</v-chip>
        </template>

        <template #item.entity="{ item }">
          {{ item.entity_type }} #{{ item.entity_id }}
        </template>

        <template #item.diff="{ item }">
          <div v-for="entry in diffEntries(item)" :key="entry.key" class="text-caption">
            <strong>{{ entry.key }}</strong>: {{ entry.before }} → {{ entry.after }}
          </div>

          <span v-if="diffEntries(item).length === 0" class="text-medium-emphasis text-caption">-</span>
        </template>

        <template #item.created_at="{ item }">
          {{ formatDateTime(item.created_at) }}
        </template>
      </v-data-table-server>
    </v-card>
  </div>
</template>
