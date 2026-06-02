<template>
  <header class="sticky top-0 z-30 border-b border-outline-variant/60 bg-surface-container-lowest/95 shadow-panel backdrop-blur-sm">
    <div class="flex h-16 w-full items-center justify-between gap-4 px-4 md:px-6">
      <div class="flex min-w-0 flex-1 items-center gap-4 md:gap-6">
        <button
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-on-surface-variant transition hover:bg-surface-container-low md:hidden"
          type="button"
          @click="emit('toggle-sidebar')"
        >
          <Icon icon="solar:hamburger-menu-linear" class="h-5 w-5" />
        </button>
      </div>

      <div class="flex shrink-0 items-center gap-2 md:gap-4">
        <button
          class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition hover:bg-surface-container-low"
          type="button"
          aria-label="Toggle theme"
          @click="emit('toggle-theme')"
        >
          <Icon v-if="theme === 'dark'" icon="solar:sun-2-linear" class="h-5 w-5" />
          <Icon v-else icon="solar:moon-linear" class="h-5 w-5" />
        </button>

        <div class="hidden h-8 w-px bg-primary-fixed/20 md:block" />

        <div class="flex items-center gap-3 rounded-full p-1 pr-2 transition hover:bg-surface-container-low">
          <div class="hidden text-right md:block">
            <p class="text-sm font-bold leading-tight text-on-surface">{{ userName }}</p>
            <p class="text-xs text-on-surface-variant">{{ userRole }}</p>
          </div>
          <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-outline-variant/60 bg-primary-fixed/15 text-sm font-bold text-primary">
            {{ userInitial }}
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed } from 'vue'

const props = defineProps({
  theme: {
    type: String,
    default: 'light'
  },
  searchPlaceholder: {
    type: String,
    default: 'Global search spatial data...'
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

const emit = defineEmits(['toggle-sidebar', 'toggle-theme'])

const userInitial = computed(() => {
  const name = props.userName.trim()
  if (!name) {
    return 'A'
  }

  return name.charAt(0).toUpperCase()
})
</script>
