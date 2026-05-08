<template>
  <section class="rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-6 shadow-sm">
    <div class="mb-6 flex items-start justify-between">
      <div>
        <h3 class="text-title-lg font-bold text-on-surface">{{ title }}</h3>
        <p class="mt-1 text-xs text-on-surface-variant">{{ subtitle }}</p>
      </div>
      <Icon :icon="icon" class="h-5 w-5 text-on-surface-variant" />
    </div>

    <div class="h-64">
      <slot />
    </div>
  </section>
</template>

<script setup>
import { Icon } from '@iconify/vue'

defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: 'solar:chart-linear'
  }
})
</script>
