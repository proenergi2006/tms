<script setup>
  import { onBeforeUnmount, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { notificationsApi } from '@/api/assets'
  import { formatDateTime } from '@/utils/format'

  // Panel notifikasi di topbar — FR-11/FR-17 (approval tertunda, legalitas
  // jatuh tempo), lihat Wireframe Document Bagian 2.1 (ikon lonceng).
  const router = useRouter()
  const { t } = useI18n()
  const notifications = ref([])
  const loading = ref(false)
  const menu = ref(false)

  const unreadCount = () => notifications.value.filter(n => !n.is_read).length

  async function load () {
    loading.value = true
    try {
      const { data } = await notificationsApi.list({ per_page: 20 })
      notifications.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function markRead (notification) {
    if (notification.is_read) return
    notification.is_read = true
    await notificationsApi.markRead(notification.id)
  }

  function viewAll () {
    menu.value = false
    router.push('/notifications')
  }

  let poll = null

  onMounted(() => {
    load()
    // Polling sederhana agar badge tetap terkini tanpa perlu WebSocket —
    // cukup untuk skala data notifikasi in-app pada scaffold ini.
    poll = setInterval(load, 60_000)
  })

  onBeforeUnmount(() => {
    if (poll) clearInterval(poll)
  })
</script>

<template>
  <v-menu v-model="menu" :close-on-content-click="false" width="360">
    <template #activator="{ props: menuProps }">
      <v-btn icon="mdi-bell-outline" v-bind="menuProps">
        <v-badge v-if="unreadCount() > 0" color="error" :content="unreadCount()" floating>
          <v-icon icon="mdi-bell-outline" />
        </v-badge>

        <v-icon v-else icon="mdi-bell-outline" />
      </v-btn>
    </template>

    <v-card>
      <v-card-title class="text-subtitle-1">{{ t('notifications.title') }}</v-card-title>
      <v-divider />

      <v-list v-if="notifications.length > 0" density="compact" max-height="360" style="overflow-y:auto">
        <v-list-item
          v-for="notification in notifications"
          :key="notification.id"
          :class="notification.is_read ? '' : 'bg-blue-lighten-5'"
          :subtitle="formatDateTime(notification.created_at)"
          :title="notification.message"
          @click="markRead(notification)"
        >
          <template #prepend>
            <v-icon :color="notification.is_read ? 'grey' : 'primary'" icon="mdi-circle-small" />
          </template>
        </v-list-item>
      </v-list>

      <v-card-text v-else-if="!loading">
        {{ t('notifications.empty') }}
      </v-card-text>

      <v-divider />
      <v-list-item :title="t('notifications.seeAll')" @click="viewAll" />
    </v-card>
  </v-menu>
</template>
