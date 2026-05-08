<template>
  <section class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm">
    <div class="border-b border-outline-variant/35 px-6 py-5">
      <h3 class="text-title-lg font-bold text-on-surface">Recent Activity</h3>
      <p class="text-xs text-on-surface-variant">Latest system actions and logs</p>
    </div>

    <div class="overflow-x-auto">
      <table class="table">
        <thead class="bg-surface-container-low text-[10px] uppercase text-on-surface-variant">
          <tr>
            <th>Time</th>
            <th>Action</th>
            <th>User</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="`${row.time}-${row.action}`" class="hover:bg-surface-container-low/60">
            <td class="text-sm font-medium">{{ row.time }}</td>
            <td>
              <span class="flex items-center gap-2 text-sm font-semibold text-primary">
                <Icon :icon="row.icon" class="h-4 w-4" />
                {{ row.action }}
              </span>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <div class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold" :class="userBadgeClass(row.userTone)">
                  {{ row.userInitial }}
                </div>
                <span class="text-sm font-medium">{{ row.user }}</span>
              </div>
            </td>
            <td>
              <span class="rounded px-2 py-1 text-[10px] font-bold uppercase" :class="statusClass(row.status)">
                {{ row.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { Icon } from '@iconify/vue'

defineProps({
  rows: {
    type: Array,
    default: () => []
  }
})

const statusClass = (status) => {
  if (status === 'SUCCESS') {
    return 'bg-primary-fixed-dim text-on-primary-fixed-variant'
  }

  if (status === 'PENDING') {
    return 'bg-error-container text-on-error-container'
  }

  return 'bg-surface-container text-on-surface-variant'
}

const userBadgeClass = (tone) => {
  if (tone === 'positive') {
    return 'bg-primary-fixed-dim text-on-primary-fixed-variant'
  }

  return 'bg-surface-container text-on-surface-variant'
}
</script>
