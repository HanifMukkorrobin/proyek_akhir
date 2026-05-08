<template>
  <div class="flex min-h-[60vh] items-center justify-center">
    <span class="loading loading-ring loading-lg text-primary" />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

definePageMeta({
  layout: 'admin'
})

const router = useRouter()

onMounted(() => {
  router.replace('/admin/log')
})
</script>
