<script setup>
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()

  const model = defineModel({ type: Boolean, default: false })

  const props = defineProps({
    title: { type: String, default: null },
    message: { type: String, default: null },
    confirmText: { type: String, default: null },
    confirmColor: { type: String, default: 'primary' },
    requireReason: { type: Boolean, default: false },
    reasonLabel: { type: String, default: null },
    loading: { type: Boolean, default: false },
  })

  const emit = defineEmits(['confirm'])

  const reason = defineModel('reason', { type: String, default: '' })

  function confirm () {
    emit('confirm', props.requireReason ? reason.value : undefined)
  }
</script>

<template>
  <v-dialog v-model="model" max-width="480">
    <v-card>
      <v-card-title>{{ title ?? t('confirm.defaultTitle') }}</v-card-title>

      <v-card-text>
        <p>{{ message ?? t('confirm.defaultMessage') }}</p>

        <v-textarea
          v-if="requireReason"
          v-model="reason"
          class="mt-2"
          :label="reasonLabel ?? t('confirm.defaultReasonLabel')"
          rows="2"
        />
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">{{ t('common.cancel') }}</v-btn>

        <v-btn
          :color="confirmColor"
          :disabled="requireReason && !reason"
          :loading="loading"
          variant="flat"
          @click="confirm"
        >
          {{ confirmText ?? t('confirm.defaultConfirm') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
