<template>
  <div class="min-h-screen bg-background font-sans text-on-background">
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-emerald-950/40 backdrop-blur-sm md:hidden"
      @click="sidebarOpen = false"
    />

    <AdminSidebarNav
      :items="navItems"
      :active-path="route.path"
      :open="sidebarOpen"
      :user-name="authStore.user?.nama || authStore.user?.name || 'Admin User'"
      :user-role="authStore.user?.role || 'System Manager'"
      @navigate="sidebarOpen = false"
      @logout="handleLogout"
    />

    <div class="flex min-h-screen flex-col md:ml-[360px]">
      <AdminTopbar
        :theme="theme"
        :search-placeholder="searchPlaceholder"
        :user-name="authStore.user?.nama || authStore.user?.name || 'Admin User'"
        :user-role="authStore.user?.role || 'System Manager'"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
        @toggle-theme="toggleTheme"
      />

      <main class="flex-1 px-5 py-6 md:px-8 md:py-8">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const THEME_KEY = 'geovisit-theme-mode'
const theme = ref('light')
const sidebarOpen = ref(false)

const navItems = [
  {
    label: 'Dashboard',
    to: '/admin/dashboard',
    icon: 'solar:widget-5-bold-duotone'
  },
  {
    label: 'Data Master',
    to: '/admin/data-master',
    icon: 'solar:database-bold-duotone'
  },
  {
    label: 'Mahasiswa',
    to: '/admin/mahasiswa',
    icon: 'solar:square-academic-cap-bold-duotone'
  },
  {
    label: 'Manajemen User',
    to: '/admin/users',
    icon: 'solar:user-id-bold-duotone'
  },
  {
    label: 'Logs',
    to: '/admin/log',
    icon: 'solar:history-bold-duotone'
  }
]

const searchPlaceholder = computed(() => {
  if (route.path.includes('/mahasiswa')) {
    return 'Search students...'
  }

  if (route.path.includes('/users')) {
    return 'Search system entities...'
  }

  if (route.path.includes('/log')) {
    return 'Search system events...'
  }

  return 'Global search spatial data...'
})

const applyTheme = (value) => {
  theme.value = value

  if (!import.meta.client) {
    return
  }

  document.documentElement.setAttribute('data-theme', value)
  document.documentElement.classList.toggle('dark', value === 'dark')
  document.documentElement.style.colorScheme = value
  localStorage.setItem(THEME_KEY, value)
}

const toggleTheme = () => {
  applyTheme(theme.value === 'light' ? 'dark' : 'light')
}

const handleLogout = () => {
  authStore.logout()
  router.push('/auth/login')
}

onMounted(() => {
  authStore.initAuth()

  if (!authStore.isAuthenticated) {
    router.replace('/auth/login')
    return
  }

  const saved = localStorage.getItem(THEME_KEY)

  if (saved === 'light' || saved === 'dark') {
    applyTheme(saved)
    return
  }

  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  applyTheme(prefersDark ? 'dark' : 'light')
})
</script>
