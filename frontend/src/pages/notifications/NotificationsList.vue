<script setup>
  import { computed, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { notificationsApi } from '@/api/assets'
  import { formatDateTime } from '@/utils/format'

  // Riwayat Notifikasi — daftar lengkap (bell di topbar hanya menampilkan
  // 20 terbaru), FR-11/FR-17.
  const { t } = useI18n()
  const items = ref([])
  const totalItems = ref(0)
  const loading = ref(false)
  const page = ref(1)
  const itemsPerPage = ref(15)
  const filter = ref('all')

  const headers = computed(() => [
    { title: t('common.rowNo'), key: 'no', sortable: false, width: 56 },
    { title: '', key: 'is_read', sortable: false, width: 40 },
    { title: t('notifications.message'), key: 'message', sortable: false },
    { title: t('notifications.type'), key: 'type', sortable: false },
    { title: t('notifications.time'), key: 'created_at', sortable: false },
  ])

  async function load () {
    loading.value = true
    try {
      const params = { page: page.value, per_page: itemsPerPage.value }
      if (filter.value === 'unread') params.is_read = false
      if (filter.value === 'read') params.is_read = true

      const { data } = await notificationsApi.list(params)
      items.value = data.data
      totalItems.value = data.meta?.total ?? data.data.length
    } finally {
      loading.value = false
    }
  }

  function applyFilter () {
    page.value = 1
    load()
  }

  async function markRead (item) {
    if (item.is_read) return
    item.is_read = true
    await notificationsApi.markRead(item.id)
  }

  const markingAll = ref(false)
  async function markAllRead () {
    markingAll.value = true
    try {
      await notificationsApi.markAllRead()
      await load()
    } finally {
      markingAll.value = false
    }
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4 flex-wrap ga-2">
      <h1 class="text-h5">{{ t('notifications.title') }}</h1>
      <v-spacer />

      <v-btn :loading="markingAll" prepend-icon="mdi-check-all" variant="tonal" @click="markAllRead">
        {{ t('notifications.markAllRead') }}
      </v-btn>
    </div>

    <v-tabs v-model="filter" class="mb-4" @update:model-value="applyFilter">
      <v-tab value="all">{{ t('notifications.all') }}</v-tab>
      <v-tab value="unread">{{ t('notifications.unread') }}</v-tab>
      <v-tab value="read">{{ t('notifications.read') }}</v-tab>
    </v-tabs>

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

        <template #item.is_read="{ item }">
          <v-icon v-if="!item.is_read" color="primary" icon="mdi-circle-small" />
        </template>

        <template #item.message="{ item }">
          <span :class="item.is_read ? '' : 'font-weight-bold'" style="cursor:pointer" @click="markRead(item)">
            {{ item.message }}
          </span>
        </template>

        <template #item.type="{ item }">
          <v-chip size="small" variant="tonal">{{ item.type }}</v-chip>
        </template>

        <template #item.created_at="{ item }">
          {{ formatDateTime(item.created_at) }}
        </template>
      </v-data-table-server>
    </v-card>
  </div>
</template>
