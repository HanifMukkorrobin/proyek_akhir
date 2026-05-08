<template>
  <div class="flex min-h-screen items-center justify-center bg-background text-on-surface">
    <div class="text-center">
      <span class="loading loading-ring loading-lg text-primary" />
      <p class="mt-4 text-body-sm font-semibold text-on-surface-variant">Loading GeoVisit PJJ IT admin...</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

onMounted(() => {
  authStore.initAuth()
  if (authStore.isAuthenticated) {
    router.replace('/admin/dashboard')
    return
  }

  router.replace('/auth/login')
})
</script>
