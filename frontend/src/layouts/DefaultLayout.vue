<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import tmsIcon from '@/assets/tms-icon.png'
  import AppFooter from '@/components/AppFooter.vue'
  import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
  import NotificationMenu from '@/components/NotificationMenu.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useSidebarCountsStore } from '@/stores/sidebarCounts'

  // Item menu sidebar — disaring sesuai permission pengguna (RBAC), lihat
  // Architecture Document Bagian 3.1 & 7.2, dan Wireframe Document Bagian 2.
  // Ini hanya gating tampilan; otorisasi sesungguhnya tetap di backend lewat
  // middleware permission:* (lihat RolePermissionSeeder).
  const auth = useAuthStore()
  const router = useRouter()
  const { t } = useI18n()

  async function logout () {
    await auth.logout()
    router.push('/login')
  }

  // Badge di menu Log Sistem/Antrian Approval/Pengajuan — state-nya di
  // stores/sidebarCounts.js (bukan ref lokal di sini) supaya halaman aksi
  // (approve/reject, submit/resubmit pengajuan) bisa memicu refresh segera
  // setelah aksinya sukses, tanpa menunggu polling 60 detik di bawah ini
  // atau (yang salah) menyuruh pengguna reload halaman — reload selalu
  // logout di app ini karena token cuma di memory (lihat stores/auth.js).
  const sidebarCounts = useSidebarCountsStore()

  let sidebarCountsPoll = null

  onMounted(() => {
    sidebarCounts.refreshAll()
    sidebarCountsPoll = setInterval(sidebarCounts.refreshAll, 60_000)
  })

  onBeforeUnmount(() => {
    if (sidebarCountsPoll) clearInterval(sidebarCountsPoll)
  })

  // Menu dikelompokkan berdasarkan korelasi fungsional (bukan alfabetis)
  // supaya sidebar tetap mudah dipindai walau daftar menu terus bertambah.
  // Grup tanpa heading (Dashboard, Notifikasi) adalah item utilitas yang
  // relevan untuk semua orang, jadi sengaja tidak dikelompokkan.
  const menuGroups = computed(() => [
    {
      items: [
        { title: t('nav.dashboard'), icon: 'mdi-view-dashboard', to: '/' },
      ],
    },
    {
      heading: t('nav.groupOperations'),
      items: [
        {
          title: t('nav.requests'),
          icon: 'mdi-file-document-outline',
          to: '/requests',
          permission: 'request.view',
          badge: sidebarCounts.requestsSubmitted > 0 ? sidebarCounts.requestsSubmitted : null,
        },
        {
          title: t('nav.approvals'),
          icon: 'mdi-check-decagram-outline',
          to: '/approvals',
          permission: 'approval.view',
          badge: sidebarCounts.approvalPending > 0 ? sidebarCounts.approvalPending : null,
        },
      ],
    },
    {
      heading: t('nav.groupFleet'),
      items: [
        { title: t('nav.fleets'), icon: 'mdi-truck-outline', to: '/fleets', permission: 'fleet.view' },
        { title: t('nav.masterData'), icon: 'mdi-database-outline', to: '/master-data', permission: 'master-data.view' },
        { title: t('nav.assets'), icon: 'mdi-desktop-classic', to: '/assets', permission: 'asset.view' },
      ],
    },
    {
      heading: t('nav.groupReports'),
      items: [
        { title: t('nav.reports'), icon: 'mdi-chart-line', to: '/reports/fleet-profitability', permission: 'report.view' },
      ],
    },
    {
      heading: t('nav.groupAdmin'),
      items: [
        { title: t('nav.rbac'), icon: 'mdi-shield-account-outline', to: '/rbac', permission: 'rbac.manage' },
        { title: t('nav.users'), icon: 'mdi-account-multiple-outline', to: '/users', permission: 'user.manage' },
        { title: t('nav.approvalSteps'), icon: 'mdi-sitemap-outline', to: '/approval-steps', permission: 'approval-step.manage' },
        { title: t('nav.auditLog'), icon: 'mdi-history', to: '/audit-logs', permission: 'audit-log.view' },
        {
          title: t('nav.systemLog'),
          icon: 'mdi-alert-octagon-outline',
          to: '/system-logs',
          permission: 'system-log.view',
          badge: sidebarCounts.systemLogErrors > 0 ? sidebarCounts.systemLogErrors : null,
        },
      ],
    },
    {
      items: [
        { title: t('nav.notifications'), icon: 'mdi-bell-outline', to: '/notifications' },
      ],
    },
  ])

  const visibleMenuGroups = computed(() => menuGroups.value
    .map(group => ({ ...group, items: group.items.filter(item => !item.permission || auth.hasPermission(item.permission)) }))
    .filter(group => group.items.length > 0))

  const drawer = ref(true)
</script>

<template>
  <v-app>
    <v-navigation-drawer v-model="drawer">
      <v-list-item :subtitle="t('nav.appSubtitle')" :title="t('nav.appTitle')">
        <template #prepend>
          <v-avatar rounded="0" size="36">
            <v-img alt="TMS" :src="tmsIcon" />
          </v-avatar>
        </template>
      </v-list-item>

      <v-divider />

      <v-list color="primary" density="compact" nav>
        <template v-for="(group, index) in visibleMenuGroups" :key="index">
          <v-list-subheader v-if="group.heading">{{ group.heading }}</v-list-subheader>

          <v-list-item
            v-for="item in group.items"
            :key="item.to"
            :prepend-icon="item.icon"
            :title="item.title"
            :to="item.to"
          >
            <template v-if="item.badge" #append>
              <v-chip color="error" size="x-small">{{ item.badge }}</v-chip>
            </template>
          </v-list-item>
        </template>
      </v-list>
    </v-navigation-drawer>

    <v-app-bar>
      <v-app-bar-nav-icon @click="drawer = !drawer" />
      <v-spacer />
      <LanguageSwitcher />
      <NotificationMenu />

      <v-menu>
        <template #activator="{ props }">
          <v-btn append-icon="mdi-chevron-down" :text="auth.user?.name ?? t('common.name')" v-bind="props" variant="text" />
        </template>

        <v-list>
          <v-list-item prepend-icon="mdi-logout" :title="t('nav.logout')" @click="logout" />
        </v-list>
      </v-menu>
    </v-app-bar>

    <v-main>
      <v-container fluid>
        <router-view />
      </v-container>

      <AppFooter />
    </v-main>
  </v-app>
</template>
