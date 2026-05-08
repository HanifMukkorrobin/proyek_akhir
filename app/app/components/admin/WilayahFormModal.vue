<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-emerald-950/40 backdrop-blur-sm" @click="handleClose" />

      <form class="relative flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl" @submit.prevent="submitForm">
        <div class="flex items-start justify-between gap-4 border-b border-outline-variant px-6 py-5">
          <div>
            <h2 class="text-title-lg font-bold text-on-surface">{{ title }}</h2>
            <p class="mt-1 text-body-sm text-on-surface-variant">{{ description }}</p>
          </div>
          <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low" type="button" :disabled="isSubmitting" @click="handleClose">
            <Icon icon="solar:close-circle-bold-duotone" class="h-6 w-6" />
          </button>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
          <div v-if="errorMessage" class="rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
            {{ errorMessage }}
          </div>

          <div v-if="successMessage" class="rounded-xl border border-outline-variant/60 bg-primary-fixed/15 px-4 py-3 text-body-sm text-primary">
            {{ successMessage }}
          </div>
          
          <div v-if="parentWilayah && isCreate" class="rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3">
            <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Parent Wilayah</p>
            <p class="mt-1 text-body-md font-semibold text-on-surface">{{ parentWilayah.nama }} ({{ parentWilayah.wilayah_id }})</p>
          </div>

          <label class="block">
            <span class="text-label-caps uppercase text-on-surface-variant">Nama Wilayah</span>
            <input
              v-model="form.nama"
              class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="text"
              required
            >
          </label>

          <label class="block">
            <span class="text-label-caps uppercase text-on-surface-variant">Kode Dukcapil</span>
            <input
              v-model="form.kode_dukcapil"
              class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="text"
              placeholder="opsional"
            >
          </label>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Latitude</span>
              <input
                v-model="form.latitude"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="text"
                placeholder="opsional"
              >
            </label>

            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Longitude</span>
              <input
                v-model="form.longitude"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="text"
                placeholder="opsional"
              >
            </label>
          </div>

        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-outline-variant bg-surface-container-low px-6 py-4 sm:flex-row sm:justify-end">
          <button class="rounded-xl bg-surface-container-high px-5 py-3 text-body-sm font-semibold text-on-surface transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isSubmitting" @click="handleClose">
            Batal
          </button>
          <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-body-sm font-bold text-on-primary shadow-sm transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isSubmitting">
            <Icon :icon="isSubmitting ? 'solar:refresh-bold-duotone' : 'solar:diskette-bold-duotone'" class="h-5 w-5" :class="{ 'animate-spin': isSubmitting }" />
            {{ submitLabel }}
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  mode: {
    type: String,
    default: 'create'
  },
  wilayah: {
    type: Object,
    default: null
  },
  parentWilayah: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])
const { $api } = useNuxtApp()

const form = reactive({
  parent_id: '',
  nama: '',
  kode_dukcapil: '',
  latitude: '',
  longitude: ''
})

const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const isCreate = computed(() => props.mode === 'create')
const title = computed(() => isCreate.value ? 'Tambah Wilayah' : 'Edit Wilayah')
const description = computed(() => isCreate.value ? 'Tambahkan data master wilayah baru.' : 'Perbarui data wilayah yang sudah ada.')
const submitLabel = computed(() => isCreate.value ? 'Tambah Wilayah' : 'Simpan Perubahan')

const resetForm = () => {
  if (isCreate.value) {
    form.parent_id = props.parentWilayah?.wilayah_id || ''
    form.nama = ''
    form.kode_dukcapil = ''
    form.latitude = ''
    form.longitude = ''
  } else {
    form.parent_id = props.wilayah?.parent_wilayah_id || ''
    form.nama = props.wilayah?.nama || ''
    form.kode_dukcapil = props.wilayah?.kode_dukcapil || ''
    form.latitude = props.wilayah?.latitude || ''
    form.longitude = props.wilayah?.longitude || ''
  }
  
  errorMessage.value = ''
  successMessage.value = ''
}

const trimOrNull = (value) => {
  const normalized = String(value || '').trim()
  return normalized === '' ? null : normalized
}

const formatApiError = (error) => {
  const errors = error?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const firstValue = Object.values(errors)[0]
    if (Array.isArray(firstValue) && firstValue.length > 0) return firstValue[0]
    if (typeof firstValue === 'string') return firstValue
  }
  return error?.response?.data?.message || error?.message || 'Terjadi kesalahan saat menyimpan wilayah.'
}

const buildPayload = () => {
  return {
    parent_id: isCreate.value ? form.parent_id : undefined,
    nama: trimOrNull(form.nama),
    kode_dukcapil: trimOrNull(form.kode_dukcapil),
    latitude: trimOrNull(form.latitude),
    longitude: trimOrNull(form.longitude)
  }
}

const submitForm = async () => {
  if (trimOrNull(form.nama) === null) {
    errorMessage.value = 'Nama wilayah wajib diisi.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    if (isCreate.value) {
      await $api.post('/wilayah', buildPayload())
      successMessage.value = 'Wilayah berhasil ditambahkan.'
    } else {
      await $api.patch(`/wilayah/${props.wilayah.wilayah_id}`, buildPayload())
      successMessage.value = 'Wilayah berhasil diperbarui.'
    }

    setTimeout(() => emit('saved'), 250)
  } catch (error) {
    errorMessage.value = formatApiError(error)
  } finally {
    isSubmitting.value = false
  }
}

const handleClose = () => {
  if (isSubmitting.value) return
  emit('close')
}

watch(() => [props.wilayah, props.mode, props.parentWilayah], resetForm, { immediate: true })
</script>
