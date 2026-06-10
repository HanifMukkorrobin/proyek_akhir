<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[70] flex items-center justify-center bg-emerald-950/40 p-4 backdrop-blur-md md:p-6" role="dialog" aria-modal="true">
      <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-outline-variant bg-surface-container-low p-6">
          <div>
            <h2 class="text-headline-md font-bold text-on-surface">Import Data Mahasiswa</h2>
            <p class="mt-1 text-body-sm text-on-surface-variant">Upload Excel, cek hasil scan klasifikasi, lalu konfirmasi baris yang akan di-import.</p>
          </div>
          <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-on-surface-variant transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isBusy" @click="handleClose">
            <Icon icon="solar:close-circle-linear" class="h-6 w-6" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <div class="mb-6 grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border px-4 py-3" :class="phase === 'upload' ? 'border-primary bg-primary-container/10' : 'border-outline-variant bg-surface-container-lowest'">
              <p class="text-label-caps uppercase text-on-surface-variant">Fase 1</p>
              <p class="mt-1 text-body-sm font-bold text-on-surface">Upload & Scan</p>
            </div>
            <div class="rounded-xl border px-4 py-3" :class="phase === 'confirm' ? 'border-primary bg-primary-container/10' : 'border-outline-variant bg-surface-container-lowest'">
              <p class="text-label-caps uppercase text-on-surface-variant">Fase 2</p>
              <p class="mt-1 text-body-sm font-bold text-on-surface">Konfirmasi Hasil</p>
            </div>
            <div class="rounded-xl border px-4 py-3" :class="phase === 'done' ? 'border-primary bg-primary-container/10' : 'border-outline-variant bg-surface-container-lowest'">
              <p class="text-label-caps uppercase text-on-surface-variant">Selesai</p>
              <p class="mt-1 text-body-sm font-bold text-on-surface">Proses Import</p>
            </div>
          </div>

          <div class="mb-6 flex flex-col gap-6 lg:flex-row">
            <label
              class="group flex min-h-60 flex-1 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-outline-variant bg-surface-container-low p-8 text-center transition hover:border-primary"
              @dragover.prevent
              @drop.prevent="handleDrop"
            >
              <input ref="fileInput" class="hidden" type="file" accept=".xlsx,.xlsm,.xltx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" @change="handleFileChange">
              <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-container/10 text-primary transition group-hover:scale-105">
                <Icon icon="solar:cloud-upload-bold-duotone" class="h-8 w-8" />
              </div>
              <p class="mb-2 text-title-md font-bold text-on-surface">{{ selectedFile?.name || 'Drop file Excel di sini' }}</p>
              <p class="mb-5 text-body-sm text-on-surface-variant">atau klik area ini untuk memilih file</p>
              <span class="rounded-full bg-primary px-6 py-3 font-semibold text-on-primary shadow-md shadow-primary/20">Select File</span>
            </label>

            <div class="flex flex-col gap-5 lg:w-80">
              <div class="rounded-xl border border-outline-variant bg-surface-container p-5">
                <h3 class="mb-3 text-label-caps uppercase text-on-surface-variant">Format Excel</h3>
                <ul class="space-y-3 text-sm text-on-surface-variant">
                  <li v-for="item in guidelines" :key="item" class="flex gap-2">
                    <Icon icon="solar:check-circle-bold" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    <span>{{ item }}</span>
                  </li>
                </ul>
              </div>

              <label class="flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <input v-model="useExternalGeocoding" class="mt-1 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary" type="checkbox" :disabled="isBusy">
                <span>
                  <span class="block text-body-sm font-semibold text-on-surface">External geocoding</span>
                  <span class="block text-xs text-on-surface-variant">Aktifkan fallback Nominatim saat scan alamat.</span>
                </span>
              </label>

              <button class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-primary px-4 py-3 font-semibold text-primary transition hover:bg-primary-container/10 disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isBusy" @click="downloadTemplate">
                <Icon icon="solar:download-minimalistic-bold-duotone" class="h-5 w-5" />
                Download Template
              </button>
            </div>
          </div>

          <div v-if="errorMessage" class="mb-5 rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
            {{ errorMessage }}
          </div>

          <div v-if="scanResult" class="space-y-5">
            <div class="flex flex-wrap items-center gap-3">
              <h3 class="text-title-lg font-bold text-on-surface">Hasil Scan Import</h3>
              <span class="rounded-full bg-surface-container-high px-3 py-1 text-xs font-bold text-on-surface-variant">{{ scanResult.ringkasan.total_data }} total</span>
              <span class="rounded-full bg-primary-fixed/15 px-3 py-1 text-xs font-bold text-primary">{{ scanResult.ringkasan.dapat_import }} dapat import</span>
              <span class="rounded-full bg-error-container px-3 py-1 text-xs font-bold text-on-error-container">{{ scanResult.ringkasan.tidak_dapat_import }} perlu koreksi</span>
            </div>

            <div class="overflow-hidden rounded-xl border border-outline-variant shadow-sm">
              <div class="overflow-x-auto">
                <table class="w-full min-w-[1260px] border-collapse text-left">
                  <thead>
                    <tr class="bg-surface-container-high text-xs font-bold uppercase text-on-surface-variant">
                      <th class="px-4 py-3">Import</th>
                      <th class="px-4 py-3">Row</th>
                      <th class="px-4 py-3">Nama</th>
                      <th class="px-4 py-3">Angkatan</th>
                      <th class="px-4 py-3">Alamat</th>
                      <th class="px-4 py-3">Wilayah ID</th>
                      <th class="px-4 py-3">Confidence</th>
                      <th class="px-4 py-3">Status</th>
                      <th class="px-4 py-3">Alasan</th>
                    </tr>
                  </thead>
                  <tbody class="text-sm">
                    <tr v-for="row in previewRows" :key="row.baris" :class="row.isImportable && row.isValidAddress ? 'border-l-4 border-primary bg-primary-container/10' : 'border-l-4 border-error bg-error-container/20'">
                      <td class="px-4 py-3">
                        <input
                          v-model="selectedRows"
                          class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary disabled:cursor-not-allowed disabled:opacity-40"
                          type="checkbox"
                          :value="row.baris"
                          :disabled="!row.isImportable || phase === 'done' || isBusy"
                        >
                      </td>
                      <td class="px-4 py-3 font-semibold">{{ row.baris }}</td>
                      <td class="px-4 py-3" :class="row.isImportable ? 'text-on-surface' : 'text-error'">{{ row.nama || '-' }}</td>
                      <td class="px-4 py-3 font-mono text-xs">{{ row.angkatan || '-' }}</td>
                      <td class="max-w-sm px-4 py-3 text-on-surface-variant">
                        <p class="line-clamp-2">{{ row.alamat || '-' }}</p>
                      </td>
                      <td class="px-4 py-3 font-mono text-xs">{{ row.wilayahId || '-' }}</td>
                      <td class="px-4 py-3">{{ row.confidenceLabel }}</td>
                      <td class="px-4 py-3">
                        <div class="flex items-center gap-1 font-semibold" :class="row.isImportable ? 'text-primary' : 'text-error'">
                          <Icon :icon="row.isImportable ? 'solar:check-circle-bold' : 'solar:danger-circle-bold'" class="h-5 w-5" />
                          <span>{{ row.statusLabel }}</span>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-on-surface-variant">{{ row.reasonLabel }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="confirmResult" class="mt-5 rounded-xl border border-outline-variant/60 bg-primary-fixed/15 p-5">
            <h3 class="text-title-md font-bold text-primary">Import selesai</h3>
            <div class="mt-4 grid gap-3 md:grid-cols-4">
              <div v-for="item in confirmSummary" :key="item.label" class="rounded-lg bg-surface-container-lowest px-4 py-3">
                <p class="text-label-caps uppercase text-on-surface-variant">{{ item.label }}</p>
                <p class="mt-1 text-2xl font-black text-on-surface">{{ item.value }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col-reverse justify-end gap-3 border-t border-outline-variant bg-surface-container-low p-6 sm:flex-row">
          <button class="rounded-lg border border-outline-variant px-6 py-3 font-semibold text-on-surface-variant transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isBusy" @click="handleClose">
            {{ phase === 'done' ? 'Tutup' : 'Batal' }}
          </button>
          <button
            v-if="phase === 'confirm'"
            class="rounded-lg border border-outline-variant px-6 py-3 font-semibold text-on-surface-variant transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isBusy"
            @click="resetScan"
          >
            Scan Ulang
          </button>
          <button
            v-if="phase !== 'done'"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 font-semibold text-on-primary shadow-lg shadow-primary/20 transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="primaryDisabled"
            @click="phase === 'upload' ? scanFile() : confirmImport()"
          >
            <Icon :icon="isBusy ? 'solar:refresh-bold-duotone' : phase === 'upload' ? 'solar:scanner-bold-duotone' : 'solar:check-circle-bold-duotone'" class="h-5 w-5" :class="{ 'animate-spin': isBusy }" />
            {{ phase === 'upload' ? 'Scan File' : 'Proses Import' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, ref } from 'vue'

const emit = defineEmits(['close', 'imported'])
const { $api } = useNuxtApp()

const guidelines = ['Header wajib: nama, alamat, angkatan', 'Kolom angkatan berisi tahun 4 digit', 'File utama: XLSX', 'Baris ambigu harus dikoreksi sebelum import']

const fileInput = ref(null)
const selectedFile = ref(null)
const useExternalGeocoding = ref(false)
const isBusy = ref(false)
const errorMessage = ref('')
const phase = ref('upload')
const scanResult = ref(null)
const confirmResult = ref(null)
const selectedRows = ref([])

const previewRows = computed(() => {
  const rows = scanResult.value?.data || []

  return rows.map((row) => {
    const classification = row.hasil_klasifikasi || {}
    const isImportable = row.status_import === 'dapat_import'
    const score = classification.confidence_score

    return {
      ...row,
      isImportable,
      nama: row.input?.nama || '',
      alamat: row.input?.alamat || '',
      angkatan: row.input?.angkatan || '',
      wilayahId: classification.wilayah_id || '',
      isValidAddress: classification.is_valid_address ?? true,
      confidenceLabel: score === null || score === undefined ? '-' : `${Math.round(Number(score) * 100)}%`,
      statusLabel: isImportable ? 'Dapat import' : 'Tidak dapat import',
      reasonLabel: Array.isArray(row.alasan) && row.alasan.length > 0 ? row.alasan.join('; ') : (classification.geocoding_status || '-')
    }
  })
})

const confirmSummary = computed(() => {
  const summary = confirmResult.value?.ringkasan || {}

  return [
    { label: 'Terscan', value: summary.total_terscan || 0 },
    { label: 'Berhasil', value: summary.berhasil_import || 0 },
    { label: 'Gagal', value: summary.gagal_import || 0 },
    { label: 'Dilewati', value: summary.dilewati || 0 }
  ]
})

const primaryDisabled = computed(() => {
  if (isBusy.value) {
    return true
  }

  if (phase.value === 'upload') {
    return selectedFile.value === null
  }

  return selectedRows.value.length === 0
})

const formatApiError = (error, fallback) => {
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

  return error?.response?.data?.message || error?.message || fallback
}

const handleFileChange = (event) => {
  const file = event.target.files?.[0] || null
  setSelectedFile(file)
}

const handleDrop = (event) => {
  const file = event.dataTransfer?.files?.[0] || null
  setSelectedFile(file)
}

const setSelectedFile = (file) => {
  selectedFile.value = file
  errorMessage.value = ''
  scanResult.value = null
  confirmResult.value = null
  selectedRows.value = []
  phase.value = 'upload'
}

const scanFile = async () => {
  if (selectedFile.value === null) {
    errorMessage.value = 'Pilih file Excel terlebih dahulu.'
    return
  }

  isBusy.value = true
  errorMessage.value = ''
  scanResult.value = null
  confirmResult.value = null
  selectedRows.value = []

  const formData = new FormData()
  formData.append('file', selectedFile.value)
  formData.append('use_external_geocoding', useExternalGeocoding.value ? 'true' : 'false')

  try {
    const response = await $api.post('/mahasiswa/import/scan', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    const payload = response.data?.data || null

    scanResult.value = payload
    selectedRows.value = Array.isArray(payload?.data)
      ? payload.data.filter((row) => row.status_import === 'dapat_import').map((row) => Number(row.baris))
      : []
    phase.value = 'confirm'
  } catch (error) {
    errorMessage.value = formatApiError(error, 'Terjadi kesalahan saat scan import.')
  } finally {
    isBusy.value = false
  }
}

const confirmImport = async () => {
  if (!scanResult.value?.import_id) {
    errorMessage.value = 'Import ID tidak ditemukan. Lakukan scan ulang.'
    return
  }

  if (selectedRows.value.length === 0) {
    errorMessage.value = 'Pilih minimal satu baris yang dapat di-import.'
    return
  }

  isBusy.value = true
  errorMessage.value = ''

  try {
    const response = await $api.post('/mahasiswa/import/confirm', {
      import_id: scanResult.value.import_id,
      baris: selectedRows.value.map((row) => Number(row))
    })

    confirmResult.value = response.data?.data || null
    phase.value = 'done'
    emit('imported')
  } catch (error) {
    errorMessage.value = formatApiError(error, 'Terjadi kesalahan saat konfirmasi import.')
  } finally {
    isBusy.value = false
  }
}

const resetScan = () => {
  scanResult.value = null
  confirmResult.value = null
  selectedRows.value = []
  phase.value = 'upload'

  if (fileInput.value) {
    fileInput.value.value = ''
  }

  selectedFile.value = null
}

const downloadTemplate = async () => {
  isBusy.value = true
  errorMessage.value = ''

  try {
    const response = await $api.get('/mahasiswa/import/template', {
      responseType: 'blob'
    })
    const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url
    link.download = 'template-import-mahasiswa.xlsx'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (error) {
    errorMessage.value = formatApiError(error, 'Terjadi kesalahan saat download template.')
  } finally {
    isBusy.value = false
  }
}

const handleClose = () => {
  if (isBusy.value) {
    return
  }

  emit('close')
}
</script>
