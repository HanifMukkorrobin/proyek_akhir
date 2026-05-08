<template>
  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-[360px] max-w-[88vw] flex-col border-r border-outline-variant/60 bg-surface-container-lowest p-6 shadow-xl transition-transform duration-300 md:translate-x-0"
    :class="open ? 'translate-x-0' : '-translate-x-full'"
  >
    <div class="mb-10 flex items-center gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-on-primary shadow-lg shadow-primary/20">
        <Icon icon="solar:map-point-wave-bold-duotone" class="h-7 w-7" />
      </div>
      <div>
        <p class="text-2xl font-black text-on-surface">GeoVisit PJJ IT</p>
        <p class="text-xs font-semibold uppercase text-on-surface-variant">Precision Admin</p>
      </div>
    </div>

    <nav class="flex-1 space-y-2">
      <NuxtLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="group flex items-center gap-4 rounded-lg px-5 py-4 text-body-md transition-all duration-200"
        :class="isActive(item.to) ? 'border-l-4 border-primary bg-primary-fixed/15 font-semibold text-primary' : 'text-on-surface-variant hover:translate-x-1 hover:bg-surface-container-low/60 hover:text-primary'"
        @click="emit('navigate')"
      >
        <Icon :icon="item.icon" class="h-6 w-6 shrink-0" />
        <span>{{ item.label }}</span>
      </NuxtLink>
    </nav>

    <div class="mt-auto border-t border-outline-variant/60 pt-6">
      <div class="mb-5 flex items-center gap-3 rounded-xl bg-surface-container-low p-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">
          {{ userInitial }}
        </div>
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-on-surface">{{ userName }}</p>
          <p class="truncate text-xs text-on-surface-variant">{{ userRole }}</p>
        </div>
      </div>

      <button class="flex w-full items-center gap-4 rounded-lg px-5 py-4 text-left text-body-md text-on-surface-variant transition hover:translate-x-1 hover:bg-surface-container-low/60 hover:text-primary" type="button">
        <Icon icon="solar:settings-bold-duotone" class="h-6 w-6" />
        <span>Settings</span>
      </button>
      <button
        class="flex w-full items-center gap-4 rounded-lg px-5 py-4 text-left text-body-md text-error transition hover:translate-x-1 hover:bg-error-container/40"
        type="button"
        @click="emit('logout')"
      >
        <Icon icon="solar:logout-2-bold-duotone" class="h-6 w-6" />
        <span>Logout</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => []
  },
  activePath: {
    type: String,
    default: ''
  },
  open: {
    type: Boolean,
    default: false
  },
  userName: {
    type: String,
    default: 'Admin User'
  },
  userRole: {
    type: String,
    default: 'System Manager'
  }
})

const emit = defineEmits(['navigate', 'logout'])

const userInitial = computed(() => {
  const name = props.userName.trim()
  if (!name) {
    return 'A'
  }

  return name.charAt(0).toUpperCase()
})

const isActive = (to) => {
  if (to === '/admin/dashboard') {
    return props.activePath === to
  }

  if (to === '/admin/log') {
    return props.activePath.startsWith('/admin/log')
  }

  return props.activePath.startsWith(to)
}
</script>
