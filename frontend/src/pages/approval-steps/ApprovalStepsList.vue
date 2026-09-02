<script setup>
  import { onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { approvalStepsApi } from '@/api/approval'
  import { rolesApi } from '@/api/rbac'

  // Tahapan Approval — admin_sistem mengatur urutan role approval WO secara
  // dinamis (permission approval-step.manage). Dulu urutan approval
  // hardcoded (fleet_operations lalu kepala_pool) di kode; sekarang backend
  // membaca urutan ini dari tabel approval_steps. Tidak ada endpoint hapus
  // — step hanya bisa diubah/dinonaktifkan (lihat ApprovalStepController).
  const { t, te } = useI18n()

  const steps = ref([])
  const roles = ref([])
  const loading = ref(false)

  function roleLabel (name) {
    return te(`enums.role.${name}`) ? t(`enums.role.${name}`) : name
  }

  async function load () {
    loading.value = true
    try {
      const [stepsRes, rolesRes] = await Promise.all([approvalStepsApi.list(), rolesApi.list()])
      steps.value = stepsRes.data.data
      roles.value = rolesRes.data.data
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  // -- Tambah / ubah step --
  const formDialog = ref(false)
  const formSaving = ref(false)
  const formIsEdit = ref(false)
  const formErrorMessage = ref(null)

  function emptyForm () {
    return { id: null, sequence_order: (steps.value.length + 1) * 10, role_name: null, label: '', is_active: true }
  }
  const form = ref(emptyForm())

  function openCreate () {
    formIsEdit.value = false
    form.value = emptyForm()
    formErrorMessage.value = null
    formDialog.value = true
  }

  function openEdit (step) {
    formIsEdit.value = true
    form.value = { ...step }
    formErrorMessage.value = null
    formDialog.value = true
  }

  async function submitForm () {
    formSaving.value = true
    formErrorMessage.value = null
    try {
      const payload = {
        sequence_order: form.value.sequence_order,
        role_name: form.value.role_name,
        label: form.value.label,
        is_active: form.value.is_active,
      }
      await (formIsEdit.value ? approvalStepsApi.update(form.value.id, payload) : approvalStepsApi.create(payload))
      formDialog.value = false
      await load()
    } catch (error) {
      formErrorMessage.value = error.response?.data?.message ?? t('approvalSteps.saveFailed')
    } finally {
      formSaving.value = false
    }
  }
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ t('approvalSteps.title') }}</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('approvalSteps.add') }}</v-btn>
    </div>

    <v-card :loading="loading">
      <v-table>
        <thead>
          <tr>
            <th>{{ t('approvalSteps.sequenceOrder') }}</th>
            <th>{{ t('approvalSteps.role') }}</th>
            <th>{{ t('approvalSteps.label') }}</th>
            <th>{{ t('approvalSteps.active') }}</th>
            <th class="text-right">{{ t('common.actions') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="step in steps" :key="step.id">
            <td>{{ step.sequence_order }}</td>
            <td>{{ roleLabel(step.role_name) }}</td>
            <td>{{ step.label }}</td>

            <td>
              <v-chip :color="step.is_active ? 'success' : 'default'" size="small" variant="tonal">
                {{ step.is_active ? t('common.yes') : t('common.no') }}
              </v-chip>
            </td>

            <td class="text-right">
              <v-btn
                icon="mdi-pencil-outline"
                size="small"
                :title="t('common.edit')"
                variant="text"
                @click="openEdit(step)"
              />
            </td>
          </tr>

          <tr v-if="steps.length === 0">
            <td class="text-medium-emphasis" colspan="5">{{ t('common.noData') }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <v-dialog v-model="formDialog" max-width="480">
      <v-card>
        <v-card-title>{{ formIsEdit ? t('approvalSteps.edit') : t('approvalSteps.add') }}</v-card-title>

        <v-card-text>
          <v-alert v-if="formErrorMessage" class="mb-4" type="error" variant="tonal">{{ formErrorMessage }}</v-alert>

          <v-text-field v-model.number="form.sequence_order" :label="t('approvalSteps.sequenceOrder')" type="number" />

          <v-select
            v-model="form.role_name"
            item-title="name"
            item-value="name"
            :items="roles"
            :label="t('approvalSteps.role')"
          >
            <template #item="{ item, props }">
              <v-list-item v-bind="props" :title="roleLabel(item.name)" />
            </template>

            <template #selection="{ item }">{{ roleLabel(item.name) }}</template>
          </v-select>

          <v-text-field v-model="form.label" :label="t('approvalSteps.label')" />

          <v-switch v-model="form.is_active" color="primary" :label="t('approvalSteps.active')" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="formDialog = false">{{ t('common.cancel') }}</v-btn>

          <v-btn color="primary" :loading="formSaving" variant="flat" @click="submitForm">
            {{ t('common.save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
