<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-emerald-950/40 backdrop-blur-sm" @click="handleClose" />

      <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl">
        <div class="p-6">
          <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-error-container text-on-error-container">
            <Icon icon="solar:trash-bin-trash-bold-duotone" class="h-6 w-6" />
          </div>

          <h2 class="mb-2 text-title-lg font-bold text-on-surface">Hapus Wilayah?</h2>
          <p class="text-body-sm text-on-surface-variant">
            Wilayah <span class="font-bold text-on-surface">{{ wilayah?.nama }} ({{ wilayah?.wilayah_id }})</span> akan dihapus dari sistem.
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
              @click="deleteWilayah"
            >
              <Icon :icon="isSubmitting ? 'solar:refresh-bold-duotone' : 'solar:trash-bin-trash-bold-duotone'" class="h-5 w-5" :class="{ 'animate-spin': isSubmitting }" />
              Hapus Wilayah
            </button>
            <button class="w-full rounded-xl bg-surface-container-high py-3 text-body-sm font-semibold text-on-surface transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isSubmitting" @click="handleClose">
              Batal
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { ref } from 'vue'

const props = defineProps({
  wilayah: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'deleted'])
const { $api } = useNuxtApp()

const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const deleteWilayah = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api.delete(`/wilayah/${props.wilayah.wilayah_id}`)
    successMessage.value = 'Wilayah berhasil dihapus.'
    setTimeout(() => emit('deleted'), 250)
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error?.message || 'Terjadi kesalahan saat menghapus wilayah.'
  } finally {
    isSubmitting.value = false
  }
}

const handleClose = () => {
  if (isSubmitting.value) return
  emit('close')
}
</script>
