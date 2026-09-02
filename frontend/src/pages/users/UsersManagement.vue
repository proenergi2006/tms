<script setup>
  import { computed, onMounted, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { branchesApi } from '@/api/masterData'
  import { rolesApi } from '@/api/rbac'
  import { usersApi } from '@/api/users'
  import ConfirmDialog from '@/components/ConfirmDialog.vue'
  import { useAuthStore } from '@/stores/auth'

  // Manajemen Pengguna — PRD Bagian 4, khusus Admin Sistem (permission
  // user.manage). Satu-satunya jalur untuk membuat akun TMS baru (email+
  // password, lihat AuthController) & menetapkan role/cabangnya.
  const { t, te } = useI18n()
  const auth = useAuthStore()

  const users = ref([])
  const roles = ref([])
  const branches = ref([])
  const loading = ref(false)

  const filters = ref({ search: '', role_id: null, branch_id: null, status: null })
  const page = ref(1)
  const itemsPerPage = ref(10)

  const filteredUsers = computed(() => users.value.filter(u => {
    if (filters.value.role_id && u.role?.id !== filters.value.role_id) return false
    if (filters.value.branch_id && u.branch?.id !== filters.value.branch_id) return false
    if (filters.value.status && u.status !== filters.value.status) return false
    if (filters.value.search) {
      const needle = filters.value.search.toLowerCase()
      if (!u.name.toLowerCase().includes(needle) && !u.email.toLowerCase().includes(needle)) return false
    }
    return true
  }))

  // Filter berubah bisa membuat halaman saat ini kosong (mis. lagi di
  // halaman 3 lalu hasil filter cuma 1 halaman) — kembali ke halaman 1.
  watch(filters, () => {
    page.value = 1
  }, { deep: true })

  const headers = computed(() => [
    { title: t('common.rowNo'), key: 'no', sortable: false, width: 56 },
    { title: t('users.name'), key: 'name' },
    { title: t('users.email'), key: 'email' },
    { title: t('rbac.roleName'), key: 'role' },
    { title: t('common.branch'), key: 'branch' },
    { title: t('common.status'), key: 'status' },
    { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  function roleLabel (name) {
    return te(`enums.role.${name}`) ? t(`enums.role.${name}`) : name
  }

  async function load () {
    loading.value = true
    try {
      const [usersRes, rolesRes, branchesRes] = await Promise.all([
        usersApi.list(),
        rolesApi.list(),
        branchesApi.list({ per_page: 100 }),
      ])
      users.value = usersRes.data.data
      roles.value = rolesRes.data.data
      branches.value = branchesRes.data.data
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  // -- Tambah / ubah user --
  const formDialog = ref(false)
  const formSaving = ref(false)
  const formIsEdit = ref(false)
  const formErrorMessage = ref(null)

  function emptyForm () {
    return { id: null, name: '', email: '', password: '', role_id: null, branch_id: null, status: 'aktif' }
  }
  const form = ref(emptyForm())

  function openCreate () {
    formIsEdit.value = false
    form.value = emptyForm()
    formErrorMessage.value = null
    formDialog.value = true
  }

  function openEdit (user) {
    formIsEdit.value = true
    form.value = {
      id: user.id,
      name: user.name,
      email: user.email,
      password: '',
      role_id: user.role?.id ?? null,
      branch_id: user.branch?.id ?? null,
      status: user.status,
    }
    formErrorMessage.value = null
    formDialog.value = true
  }

  async function submitForm () {
    formSaving.value = true
    formErrorMessage.value = null
    try {
      const payload = { ...form.value }
      if (formIsEdit.value && !payload.password) delete payload.password

      await (formIsEdit.value ? usersApi.update(form.value.id, payload) : usersApi.create(payload))
      formDialog.value = false
      await load()
    } catch (error) {
      formErrorMessage.value = error.response?.data?.message ?? t('users.saveFailed')
    } finally {
      formSaving.value = false
    }
  }

  // -- Hapus user --
  const deleteDialog = ref(false)
  const deleting = ref(false)
  const userToDelete = ref(null)
  const deleteErrorMessage = ref(null)

  function confirmDeleteUser (user) {
    userToDelete.value = user
    deleteErrorMessage.value = null
    deleteDialog.value = true
  }

  async function doDeleteUser () {
    deleting.value = true
    try {
      await usersApi.remove(userToDelete.value.id)
      deleteDialog.value = false
      await load()
    } catch (error) {
      deleteDialog.value = false
      deleteErrorMessage.value = error.response?.data?.message ?? t('users.deleteFailed')
    } finally {
      deleting.value = false
    }
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ t('users.title') }}</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('users.addUser') }}</v-btn>
    </div>

    <v-alert
      v-if="deleteErrorMessage"
      class="mb-4"
      closable
      type="error"
      variant="tonal"
      @click:close="deleteErrorMessage = null"
    >{{ deleteErrorMessage }}</v-alert>

    <v-card class="mb-4">
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.search"
              clearable
              density="compact"
              :label="t('users.search')"
              prepend-inner-icon="mdi-magnify"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="filters.role_id"
              clearable
              density="compact"
              item-title="name"
              item-value="id"
              :items="roles"
              :label="t('rbac.roleName')"
            >
              <template #item="{ item, props }">
                <v-list-item v-bind="props" :title="roleLabel(item.name)" />
              </template>

              <template #selection="{ item }">{{ roleLabel(item.name) }}</template>
            </v-select>
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="filters.branch_id"
              clearable
              density="compact"
              item-title="name"
              item-value="id"
              :items="branches"
              :label="t('common.branch')"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              clearable
              density="compact"
              :items="[{ title: t('enums.status.aktif'), value: 'aktif' }, { title: t('enums.status.nonaktif'), value: 'nonaktif' }]"
              :label="t('common.status')"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card :loading="loading">
      <v-data-table
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="filteredUsers"
        :items-per-page-options="[10, 20, 50, { value: -1, title: t('common.all') }]"
        :no-data-text="t('users.noUsers')"
      >
        <template #item.no="{ index }">{{ (page - 1) * itemsPerPage + index + 1 }}</template>

        <template #item.role="{ item }">
          {{ roleLabel(item.role?.name) }}
        </template>

        <template #item.branch="{ item }">{{ item.branch?.name ?? '-' }}</template>

        <template #item.status="{ item }">
          <v-chip :color="item.status === 'aktif' ? 'success' : 'default'" size="small" variant="tonal">
            {{ item.status === 'aktif' ? t('enums.status.aktif') : t('enums.status.nonaktif') }}
          </v-chip>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            size="small"
            :title="t('common.edit')"
            variant="text"
            @click="openEdit(item)"
          />

          <v-btn
            v-if="item.id !== auth.user?.id"
            icon="mdi-delete-outline"
            size="small"
            :title="t('common.delete')"
            variant="text"
            @click="confirmDeleteUser(item)"
          />
        </template>
      </v-data-table>
    </v-card>

    <!-- Dialog tambah/ubah user -->
    <v-dialog v-model="formDialog" max-width="480">
      <v-card>
        <v-card-title>{{ formIsEdit ? t('users.editTitle') : t('users.addUser') }}</v-card-title>

        <v-card-text>
          <v-alert v-if="formErrorMessage" class="mb-4" type="error" variant="tonal">{{ formErrorMessage }}</v-alert>

          <v-text-field v-model="form.name" :label="t('users.name')" />
          <v-text-field v-model="form.email" :label="t('users.email')" type="email" />

          <v-text-field
            v-model="form.password"
            :hint="formIsEdit ? t('users.passwordHintEdit') : t('users.passwordHintCreate')"
            :label="t('users.password')"
            persistent-hint
            type="password"
          />

          <v-select
            v-model="form.role_id"
            class="mt-2"
            item-title="name"
            item-value="id"
            :items="roles"
            :label="t('rbac.roleName')"
          >
            <template #item="{ item, props }">
              <v-list-item v-bind="props" :title="roleLabel(item.name)" />
            </template>

            <template #selection="{ item }">{{ roleLabel(item.name) }}</template>
          </v-select>

          <v-select
            v-model="form.branch_id"
            clearable
            item-title="name"
            item-value="id"
            :items="branches"
            :label="t('common.branch')"
          />

          <v-switch
            v-model="form.status"
            color="primary"
            false-value="nonaktif"
            :label="t('users.active')"
            true-value="aktif"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="formDialog = false">{{ t('common.cancel') }}</v-btn>
          <v-btn color="primary" :loading="formSaving" variant="flat" @click="submitForm">{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ConfirmDialog
      v-model="deleteDialog"
      confirm-color="error"
      :confirm-text="t('common.delete')"
      :loading="deleting"
      :message="t('users.confirmDelete', { name: userToDelete?.name })"
      :title="t('users.deleteTitle')"
      @confirm="doDeleteUser"
    />
  </div>
</template>
