<script setup>
  import { statusColor, statusLabel } from '@/utils/status'

  defineProps({
    status: { type: String, required: true },
  })
</script>

<template>
  <v-chip :color="statusColor(status)" size="small" variant="tonal">
    {{ statusLabel(status) }}
  </v-chip>
</template>
