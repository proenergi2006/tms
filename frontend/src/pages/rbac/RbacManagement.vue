<script setup>
  import { computed, onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { permissionsApi, rolesApi } from '@/api/rbac'
  import ConfirmDialog from '@/components/ConfirmDialog.vue'

  // Manajemen Role & Permission (RBAC) — PRD Bagian 4, khusus Admin Sistem
  // (permission rbac.manage). Role bawaan sistem (is_system) tidak bisa
  // di-rename/dihapus karena namanya dirujuk langsung di kode (alur
  // approval, branch-scoping, dst) — permission-nya tetap bebas diubah,
  // itu justru inti fitur ini (lihat Role::SYSTEM_ROLES di backend).
  const { t } = useI18n()

  const roles = ref([])
  const permissions = ref([])
  const loading = ref(false)
  const page = ref(1)
  const itemsPerPage = ref(10)

  const headers = computed(() => [
    { title: t('common.rowNo'), key: 'no', sortable: false, width: 56 },
    { title: t('rbac.roleName'), key: 'name' },
    { title: t('rbac.type'), key: 'is_system' },
    { title: t('rbac.userCount'), key: 'user_count', align: 'end' },
    { title: t('rbac.permissionCount'), key: 'permission_count', align: 'end' },
    { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
  ])

  // Permission dikelompokkan berdasarkan prefix sebelum titik pertama
  // (mis. "fleet.view" & "fleet.manage" -> grup "fleet") supaya daftar
  // checkbox yang cukup panjang (17+ permission) tetap mudah dipindai.
  const permissionGroups = computed(() => {
    const groups = {}
    for (const permission of permissions.value) {
      const prefix = permission.name.split('.', 1)[0]
      if (!groups[prefix]) groups[prefix] = []
      groups[prefix].push(permission)
    }

    return groups
  })

  async function load () {
    loading.value = true
    try {
      const [rolesRes, permissionsRes] = await Promise.all([rolesApi.list(), permissionsApi.list()])
      roles.value = rolesRes.data.data
      permissions.value = permissionsRes.data.data
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  // -- Kelola permission per role --
  const permissionsDialog = ref(false)
  const permissionsSaving = ref(false)
  const activeRole = ref(null)
  const editingPermissionIds = ref([])

  function openPermissions (role) {
    activeRole.value = role
    editingPermissionIds.value = [...role.permissions]
    permissionsDialog.value = true
  }

  async function savePermissions () {
    permissionsSaving.value = true
    try {
      await rolesApi.syncPermissions(activeRole.value.id, editingPermissionIds.value)
      permissionsDialog.value = false
      await load()
    } finally {
      permissionsSaving.value = false
    }
  }

  // -- Tambah / rename role --
  const roleFormDialog = ref(false)
  const roleFormSaving = ref(false)
  const roleFormIsEdit = ref(false)
  const roleFormErrorMessage = ref(null)
  const roleForm = ref({ id: null, name: '' })

  function openCreateRole () {
    roleFormIsEdit.value = false
    roleForm.value = { id: null, name: '' }
    roleFormErrorMessage.value = null
    roleFormDialog.value = true
  }

  function openRenameRole (role) {
    roleFormIsEdit.value = true
    roleForm.value = { id: role.id, name: role.name }
    roleFormErrorMessage.value = null
    roleFormDialog.value = true
  }

  async function submitRoleForm () {
    roleFormSaving.value = true
    roleFormErrorMessage.value = null
    try {
      await (roleFormIsEdit.value
        ? rolesApi.update(roleForm.value.id, { name: roleForm.value.name })
        : rolesApi.create({ name: roleForm.value.name }))
      roleFormDialog.value = false
      await load()
    } catch (error) {
      roleFormErrorMessage.value = error.response?.data?.message ?? t('rbac.saveFailed')
    } finally {
      roleFormSaving.value = false
    }
  }

  // -- Hapus role --
  const deleteDialog = ref(false)
  const deleting = ref(false)
  const roleToDelete = ref(null)
  const deleteErrorMessage = ref(null)

  function confirmDeleteRole (role) {
    roleToDelete.value = role
    deleteErrorMessage.value = null
    deleteDialog.value = true
  }

  async function doDeleteRole () {
    deleting.value = true
    try {
      await rolesApi.remove(roleToDelete.value.id)
      deleteDialog.value = false
      await load()
    } catch (error) {
      deleteDialog.value = false
      deleteErrorMessage.value = error.response?.data?.message ?? t('rbac.deleteFailed')
    } finally {
      deleting.value = false
    }
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ t('rbac.title') }}</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateRole">{{ t('rbac.addRole') }}</v-btn>
    </div>

    <v-alert class="mb-4" type="info" variant="tonal">{{ t('rbac.info') }}</v-alert>

    <v-alert
      v-if="deleteErrorMessage"
      class="mb-4"
      closable
      type="error"
      variant="tonal"
      @click:close="deleteErrorMessage = null"
    >{{ deleteErrorMessage }}</v-alert>

    <v-card :loading="loading">
      <v-data-table
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="roles"
        :items-per-page-options="[10, 20, 50, { value: -1, title: t('common.all') }]"
      >
        <template #item.no="{ index }">{{ (page - 1) * itemsPerPage + index + 1 }}</template>

        <template #item.is_system="{ item }">
          <v-chip :color="item.is_system ? 'default' : 'primary'" size="small" variant="tonal">
            {{ item.is_system ? t('rbac.systemRole') : t('rbac.customRole') }}
          </v-chip>
        </template>

        <template #item.permission_count="{ item }">{{ item.permissions.length }}</template>

        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-shield-key-outline"
            size="small"
            :title="t('rbac.managePermissions')"
            variant="text"
            @click="openPermissions(item)"
          />

          <v-btn
            v-if="!item.is_system"
            icon="mdi-pencil-outline"
            size="small"
            :title="t('common.edit')"
            variant="text"
            @click="openRenameRole(item)"
          />

          <v-btn
            v-if="!item.is_system"
            icon="mdi-delete-outline"
            size="small"
            :title="t('common.delete')"
            variant="text"
            @click="confirmDeleteRole(item)"
          />
        </template>
      </v-data-table>
    </v-card>

    <!-- Dialog kelola permission -->
    <v-dialog v-model="permissionsDialog" max-width="640">
      <v-card>
        <v-card-title>{{ t('rbac.managePermissionsFor', { name: activeRole?.name }) }}</v-card-title>

        <v-card-text style="max-height: 60vh; overflow-y: auto;">
          <div v-for="(group, prefix) in permissionGroups" :key="prefix" class="mb-3">
            <div class="text-subtitle-2 text-medium-emphasis mb-1">{{ prefix }}</div>

            <v-checkbox
              v-for="permission in group"
              :key="permission.id"
              v-model="editingPermissionIds"
              density="compact"
              hide-details
              :label="permission.name"
              :value="permission.id"
            />
          </div>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="permissionsDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn color="primary" :loading="permissionsSaving" variant="flat" @click="savePermissions">
            {{ t('common.save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Dialog tambah/rename role -->
    <v-dialog v-model="roleFormDialog" max-width="420">
      <v-card>
        <v-card-title>{{ roleFormIsEdit ? t('rbac.renameRole') : t('rbac.addRole') }}</v-card-title>

        <v-card-text>
          <v-alert v-if="roleFormErrorMessage" class="mb-4" type="error" variant="tonal">{{ roleFormErrorMessage }}</v-alert>

          <v-text-field
            v-model="roleForm.name"
            :hint="t('rbac.roleNameHint')"
            :label="t('rbac.roleName')"
            persistent-hint
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="roleFormDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn color="primary" :loading="roleFormSaving" variant="flat" @click="submitRoleForm">
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
      :message="t('rbac.confirmDelete', { name: roleToDelete?.name })"
      :title="t('rbac.deleteTitle')"
      @confirm="doDeleteRole"
    />
  </div>
</template>
