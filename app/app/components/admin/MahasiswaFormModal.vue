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

          <label class="block">
            <span class="text-label-caps uppercase text-on-surface-variant">Nama Mahasiswa</span>
            <input
              v-model="form.nama"
              class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="text"
              autocomplete="name"
              required
            >
          </label>

          <label class="block">
            <span class="text-label-caps uppercase text-on-surface-variant">Angkatan</span>
            <select
              v-model.number="form.angkatan"
              class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              required
            >
              <option v-for="year in yearOptions" :key="year" :value="year">
                {{ year }}
              </option>
            </select>
          </label>

          <label class="block">
            <span class="text-label-caps uppercase text-on-surface-variant">Alamat Domisili</span>
            <textarea
              v-model="form.alamat"
              class="mt-2 min-h-36 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              required
            />
          </label>

          <label class="flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
            <input v-model="form.use_external_geocoding" class="mt-1 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary" type="checkbox">
            <span>
              <span class="block text-body-sm font-semibold text-on-surface">Gunakan external geocoding</span>
              <span class="block text-xs text-on-surface-variant">Jika aktif, API dapat memakai Nominatim sebagai fallback saat klasifikasi internal kurang yakin.</span>
            </span>
          </label>

          <div v-if="!isCreate && mahasiswa?.mahasiswa_id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
            <p class="text-label-caps uppercase text-on-surface-variant">Mahasiswa ID</p>
            <p class="mt-1 break-all font-mono text-xs text-on-surface">{{ mahasiswa.mahasiswa_id }}</p>
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
  mahasiswa: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])
const { $api } = useNuxtApp()

const form = reactive({
  nama: '',
  alamat: '',
  angkatan: new Date().getFullYear(),
  use_external_geocoding: false
})

const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const isCreate = computed(() => props.mode === 'create')
const title = computed(() => isCreate.value ? 'Tambah Mahasiswa' : 'Ubah Mahasiswa')
const description = computed(() => isCreate.value ? 'Simpan data mahasiswa dan klasifikasikan alamatnya.' : 'Perbarui nama atau alamat, lalu API akan menghitung ulang wilayah/koordinat.')
const submitLabel = computed(() => isCreate.value ? 'Tambah Mahasiswa' : 'Simpan Perubahan')
const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: currentYear - 1998 }, (_, index) => currentYear + 1 - index)

const resetForm = () => {
  form.nama = props.mahasiswa?.nama || ''
  form.alamat = props.mahasiswa?.alamat || ''
  form.angkatan = Number(props.mahasiswa?.angkatan || currentYear)
  form.use_external_geocoding = false
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

    if (Array.isArray(firstValue) && firstValue.length > 0) {
      return firstValue[0]
    }

    if (typeof firstValue === 'string') {
      return firstValue
    }
  }

  return error?.response?.data?.message || error?.message || 'Terjadi kesalahan saat menyimpan mahasiswa.'
}

const validateForm = () => {
  if (trimOrNull(form.nama) === null) {
    return 'Nama mahasiswa wajib diisi.'
  }

  if (trimOrNull(form.alamat) === null) {
    return 'Alamat domisili wajib diisi.'
  }

  if (!yearOptions.includes(Number(form.angkatan))) {
    return 'Angkatan wajib dipilih.'
  }

  return ''
}

const buildPayload = () => ({
  nama: trimOrNull(form.nama),
  alamat: trimOrNull(form.alamat),
  angkatan: Number(form.angkatan),
  use_external_geocoding: form.use_external_geocoding
})

const submitForm = async () => {
  const validationError = validateForm()

  if (validationError !== '') {
    errorMessage.value = validationError
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    if (isCreate.value) {
      await $api.post('/mahasiswa', buildPayload())
      successMessage.value = 'Mahasiswa berhasil ditambahkan.'
    } else {
      await $api.patch(`/mahasiswa/${props.mahasiswa.mahasiswa_id}`, buildPayload())
      successMessage.value = 'Mahasiswa berhasil diperbarui.'
    }

    setTimeout(() => emit('saved'), 250)
  } catch (error) {
    errorMessage.value = formatApiError(error)
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

watch(() => props.mahasiswa, resetForm, { immediate: true })
</script>
