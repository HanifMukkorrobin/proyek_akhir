<template>
  <article class="rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-5 shadow-sm transition-transform duration-200 hover:-translate-y-1">
    <div class="mb-4 flex items-start justify-between">
      <div class="rounded-lg bg-primary-fixed/15 p-2 text-primary">
        <Icon :icon="icon" class="h-5 w-5" />
      </div>
      <span class="rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wide" :class="badgeClass">{{ badge }}</span>
    </div>

    <p class="text-label-caps uppercase text-on-surface-variant">{{ title }}</p>
    <p class="mt-1 text-4xl font-black leading-none text-on-surface">{{ value }}</p>
  </article>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed } from 'vue'

const props = defineProps({
  icon: {
    type: String,
    required: true
  },
  title: {
    type: String,
    required: true
  },
  value: {
    type: String,
    required: true
  },
  badge: {
    type: String,
    default: ''
  },
  badgeTone: {
    type: String,
    default: 'neutral'
  }
})

const badgeClass = computed(() => {
  if (props.badgeTone === 'positive') {
    return 'bg-primary-fixed-dim text-on-primary-fixed-variant'
  }

  if (props.badgeTone === 'warning') {
    return 'bg-error-container text-on-error-container'
  }

  return 'bg-primary-fixed/15 text-primary'
})
</script>
