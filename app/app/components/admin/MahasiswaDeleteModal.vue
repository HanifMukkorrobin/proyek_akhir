<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-emerald-950/40 backdrop-blur-sm" @click="handleClose" />

      <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl">
        <div class="p-6">
          <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-error-container text-on-error-container">
            <Icon icon="solar:trash-bin-trash-bold-duotone" class="h-6 w-6" />
          </div>

          <h2 class="mb-2 text-title-lg font-bold text-on-surface">Hapus Mahasiswa?</h2>
          <p class="text-body-sm text-on-surface-variant">
            Data <span class="font-bold text-on-surface">{{ displayName }}</span> akan dihapus dari daftar aktif.
          </p>

          <div v-if="errorMessage" class="mt-5 rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
            {{ errorMessage }}
          </div>

          <div v-if="successMessage" class="mt-5 rounded-xl border border-outline-variant/60 bg-primary-fixed/15 px-4 py-3 text-body-sm text-primary">
            {{ successMessage }}
          </div>

          <div class="mt-6 flex flex-col gap-3">
            <button
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-error py-3 text-body-sm font-bold text-on-error shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
              type="button"
              :disabled="isSubmitting"
              @click="deleteMahasiswa"
            >
              <Icon :icon="isSubmitting ? 'solar:refresh-bold-duotone' : 'solar:trash-bin-trash-bold-duotone'" class="h-5 w-5" :class="{ 'animate-spin': isSubmitting }" />
              Hapus Mahasiswa
            </button>
            <button class="w-full rounded-xl bg-surface-container-high py-3 text-body-sm font-semibold text-on-surface transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isSubmitting" @click="handleClose">
              Batal
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2 border-t border-outline-variant bg-surface-container-low px-6 py-4">
          <Icon icon="solar:info-circle-bold-duotone" class="h-4 w-4 shrink-0 text-on-surface-variant" />
          <p class="text-[11px] leading-tight text-on-surface-variant">API menggunakan soft delete untuk menjaga histori data.</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, ref } from 'vue'

const props = defineProps({
  mahasiswa: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'deleted'])
const { $api } = useNuxtApp()

const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const displayName = computed(() => props.mahasiswa?.nama || 'Mahasiswa')

const deleteMahasiswa = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api.delete(`/mahasiswa/${props.mahasiswa.mahasiswa_id}`)
    successMessage.value = 'Mahasiswa berhasil dihapus.'
    setTimeout(() => emit('deleted'), 250)
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error?.message || 'Terjadi kesalahan saat menghapus mahasiswa.'
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
