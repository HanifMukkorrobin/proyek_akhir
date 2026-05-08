<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-emerald-950/40 backdrop-blur-sm" @click="handleClose" />

      <form class="relative w-full max-w-md overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl" @submit.prevent="resetPassword">
        <div class="p-6">
          <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-error-container text-on-error-container">
            <Icon icon="solar:lock-keyhole-bold-duotone" class="h-6 w-6" />
          </div>

          <h2 class="mb-2 text-title-lg font-bold text-on-surface">Reset Password User</h2>
          <p class="mb-6 text-body-sm text-on-surface-variant">
            Masukkan password baru untuk <span class="font-bold text-on-surface">{{ displayName }}</span>.
          </p>

          <div class="space-y-4">
            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Password Baru</span>
              <input
                v-model="password"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="password"
                autocomplete="new-password"
                required
              >
            </label>

            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Konfirmasi Password</span>
              <input
                v-model="confirmPassword"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="password"
                autocomplete="new-password"
                required
              >
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
              <input v-model="revokeTokens" class="mt-1 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary" type="checkbox">
              <span>
                <span class="block text-body-sm font-semibold text-on-surface">Cabut token aktif</span>
                <span class="block text-xs text-on-surface-variant">User perlu login ulang setelah password direset.</span>
              </span>
            </label>

            <div v-if="errorMessage" class="rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
              {{ errorMessage }}
            </div>

            <div v-if="successMessage" class="rounded-xl border border-outline-variant/60 bg-primary-fixed/15 px-4 py-3 text-body-sm text-primary">
              {{ successMessage }}
            </div>
          </div>

          <div class="mt-6 flex flex-col gap-3">
            <button
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-error py-3 text-body-sm font-bold text-on-error shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
              type="submit"
              :disabled="isSubmitting"
            >
              <Icon :icon="isSubmitting ? 'solar:refresh-bold-duotone' : 'solar:lock-keyhole-bold-duotone'" class="h-5 w-5" :class="{ 'animate-spin': isSubmitting }" />
              Reset Password
            </button>
            <button class="w-full rounded-xl bg-surface-container-high py-3 text-body-sm font-semibold text-on-surface transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isSubmitting" @click="handleClose">
              Batal
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2 border-t border-outline-variant bg-surface-container-low px-6 py-4">
          <Icon icon="solar:info-circle-bold-duotone" class="h-4 w-4 shrink-0 text-on-surface-variant" />
          <p class="text-[11px] leading-tight text-on-surface-variant">Aktivitas reset password dicatat oleh API untuk audit keamanan.</p>
        </div>
      </form>
    </div>
  </Teleport>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, ref } from 'vue'

const props = defineProps({
  user: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])
const { $api } = useNuxtApp()

const password = ref('')
const confirmPassword = ref('')
const revokeTokens = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const displayName = computed(() => props.user?.nama || props.user?.name || props.user?.username || 'User')

const validateForm = () => {
  if (password.value.length < 8) {
    return 'Password minimal 8 karakter.'
  }

  if (password.value !== confirmPassword.value) {
    return 'Konfirmasi password tidak sama.'
  }

  return ''
}

const resetPassword = async () => {
  const validationError = validateForm()

  if (validationError !== '') {
    errorMessage.value = validationError
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api.post(`/users/${props.user.user_id}/reset-password`, {
      password: password.value,
      revoke_tokens: revokeTokens.value
    })
    successMessage.value = 'Password user berhasil direset.'
    setTimeout(() => emit('saved'), 250)
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error?.message || 'Terjadi kesalahan saat reset password user.'
  } finally {
    isSubmitting.value = false
  }
}

const handleClose = () => {
  if (isSubmitting.value) {
    return
  }

  emit('close')
}
</script>
