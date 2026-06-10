<template>
  <div class="space-y-6">
    <Head>
      <Title>Direktori Mahasiswa | GeoVisit PJJ IT</Title>
    </Head>

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
      <div>
        <h1 class="text-headline-md font-black text-on-surface md:text-3xl">Direktori Mahasiswa</h1>
        <p class="mt-2 text-body-sm text-on-surface-variant">Kelola data mahasiswa, alamat domisili, hasil klasifikasi wilayah, dan koordinat geospasial.</p>
      </div>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <button
          class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 py-3 text-body-sm font-semibold text-on-surface-variant shadow-sm transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60"
          type="button"
          :disabled="isLoading"
          @click.stop.prevent="showImportModal = true"
        >
          <Icon icon="solar:upload-minimalistic-bold-duotone" class="h-5 w-5" />
          Import Data
        </button>
        <button
          class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-body-sm font-semibold text-on-primary shadow-md shadow-primary/20 transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
          type="button"
          :disabled="isLoading"
          @click.stop.prevent="openCreateModal"
        >
          <Icon icon="solar:add-circle-bold-duotone" class="h-5 w-5" />
          Tambah Mahasiswa
        </button>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-panel">
      <div class="space-y-4 border-b border-outline-variant px-6 py-4">
        <div class="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_auto] lg:items-center">
          <label class="relative block">
            <Icon icon="solar:magnifer-linear" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-on-surface-variant" />
            <input
              v-model="searchInput"
              class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest pl-12 pr-4 text-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="search"
              placeholder="Cari nama, alamat, atau ID mahasiswa..."
            >
          </label>

          <button
            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-semibold text-on-surface-variant transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isLoading"
            @click="refreshMahasiswa"
          >
            <Icon icon="solar:refresh-bold-duotone" class="h-5 w-5" :class="{ 'animate-spin': isLoading }" />
            Refresh
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mx-6 mt-4 rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
        {{ errorMessage }}
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[1260px] border-collapse text-left">
          <thead>
            <tr class="border-b border-outline-variant bg-surface-container-low text-on-surface-variant">
              <th class="px-6 py-4 text-label-caps uppercase">Mahasiswa</th>
              <th class="px-6 py-4 text-label-caps uppercase">Angkatan</th>
              <th class="px-6 py-4 text-label-caps uppercase">Alamat</th>
              <th class="px-6 py-4 text-label-caps uppercase">Wilayah</th>
              <th class="px-6 py-4 text-label-caps uppercase">Koordinat</th>
              <th class="px-6 py-4 text-label-caps uppercase">Diubah</th>
              <th class="px-6 py-4 text-right text-label-caps uppercase">Actions</th>
            </tr>
          </thead>
          <tbody v-if="isLoading" class="divide-y divide-outline-variant/20">
            <tr v-for="index in 5" :key="index">
              <td v-for="column in 7" :key="column" class="px-6 py-4">
                <div class="h-5 animate-pulse rounded bg-surface-container-high" :class="column === 3 ? 'w-80' : column === 7 ? 'ml-auto w-24' : 'w-36'" />
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="mahasiswaRows.length > 0" class="divide-y divide-outline-variant/20">
            <tr v-for="row in mahasiswaRows" :key="row.mahasiswa_id" class="transition hover:bg-surface-container-low/60">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-container-high font-bold text-primary">{{ row.initial }}</div>
                  <div class="min-w-0">
                    <p class="text-body-md font-semibold text-on-surface">{{ row.nama || '-' }}</p>
                    <p class="max-w-[260px] truncate font-mono text-[11px] text-on-surface-variant">{{ row.mahasiswa_id }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex rounded-full bg-primary-fixed/15 px-3 py-1 text-xs font-bold text-primary">
                  {{ row.angkatanLabel }}
                </span>
              </td>
              <td class="max-w-[360px] px-6 py-4 text-body-sm text-on-surface-variant">
                <p class="line-clamp-2">{{ row.alamat || '-' }}</p>
              </td>
              <td class="px-6 py-4">
                <p class="text-body-sm font-semibold text-on-surface">{{ row.wilayahLabel }}</p>
                <p class="mt-1 font-mono text-[11px] text-on-surface-variant">{{ row.wilayah_id || '-' }}</p>
              </td>
              <td class="px-6 py-4">
                <p class="font-mono text-xs text-on-surface">{{ row.coordinateLabel }}</p>
                <span class="mt-1 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold" :class="row.hasCoordinates ? 'bg-primary-fixed/15 text-primary' : 'bg-error-container text-on-error-container'">
                  {{ row.hasCoordinates ? 'Tersedia' : 'Belum ada' }}
                </span>
              </td>
              <td class="px-6 py-4 text-body-sm text-on-surface-variant">{{ row.updatedLabel }}</td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary" type="button" title="Edit mahasiswa" aria-label="Edit mahasiswa" @click.stop.prevent="openEditModal(row.raw)">
                    <Icon icon="solar:pen-bold-duotone" class="h-5 w-5" />
                  </button>
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-error transition hover:bg-error-container/50" type="button" title="Hapus mahasiswa" aria-label="Hapus mahasiswa" @click.stop.prevent="selectedDeleteMahasiswa = row.raw">
                    <Icon icon="solar:trash-bin-trash-bold-duotone" class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="7" class="px-6 py-14 text-center">
                <div class="mx-auto flex max-w-sm flex-col items-center">
                  <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                    <Icon icon="solar:user-cross-bold-duotone" class="h-6 w-6" />
                  </div>
                  <p class="text-body-md font-semibold text-on-surface">Tidak ada mahasiswa ditemukan</p>
                  <p class="mt-1 text-body-sm text-on-surface-variant">Ubah pencarian, tambah data baru, atau import CSV.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col justify-between gap-4 border-t border-outline-variant bg-surface-container-low px-6 py-4 md:flex-row md:items-center">
        <span class="text-body-sm text-on-surface-variant">{{ paginationLabel }}</span>
        <div class="flex items-center gap-2">
          <button
            class="flex h-10 w-10 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-50"
            type="button"
            :disabled="isLoading || pagination.halaman_sekarang <= 1"
            @click="goToPage(pagination.halaman_sekarang - 1)"
          >
            <Icon icon="solar:alt-arrow-left-linear" class="h-4 w-4" />
          </button>
          <span class="px-2 text-body-sm font-semibold text-on-surface">{{ pagination.halaman_sekarang }} / {{ pagination.total_halaman }}</span>
          <button
            class="flex h-10 w-10 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-50"
            type="button"
            :disabled="isLoading || pagination.halaman_sekarang >= pagination.total_halaman"
            @click="goToPage(pagination.halaman_sekarang + 1)"
          >
            <Icon icon="solar:alt-arrow-right-linear" class="h-4 w-4" />
          </button>
        </div>
      </div>
    </section>

    <AdminStudentImportModal
      v-if="showImportModal"
      @close="showImportModal = false"
      @imported="handleImported"
    />
    <AdminMahasiswaFormModal
      v-if="formMode"
      :mode="formMode"
      :mahasiswa="selectedFormMahasiswa"
      @close="closeFormModal"
      @saved="handleSaved"
    />
    <AdminMahasiswaDeleteModal
      v-if="selectedDeleteMahasiswa"
      :mahasiswa="selectedDeleteMahasiswa"
      @close="selectedDeleteMahasiswa = null"
      @deleted="handleSaved"
    />
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, onMounted, ref, watch } from 'vue'
import AdminMahasiswaDeleteModal from '~/components/admin/MahasiswaDeleteModal.vue'
import AdminMahasiswaFormModal from '~/components/admin/MahasiswaFormModal.vue'
import AdminStudentImportModal from '~/components/admin/StudentImportModal.vue'

definePageMeta({
  layout: 'admin'
})

const { $api } = useNuxtApp()

const mahasiswaRows = ref([])
const isLoading = ref(false)
const errorMessage = ref('')
const searchInput = ref('')
const searchQuery = ref('')
const showImportModal = ref(false)
const formMode = ref('')
const selectedFormMahasiswa = ref(null)
const selectedDeleteMahasiswa = ref(null)
const pagination = ref({
  halaman_sekarang: 1,
  per_halaman: 10,
  total_data: 0,
  total_halaman: 1
})

let searchTimer = null

const paginationLabel = computed(() => {
  const total = pagination.value.total_data

  if (total === 0) {
    return 'Showing 0 mahasiswa'
  }

  const start = ((pagination.value.halaman_sekarang - 1) * pagination.value.per_halaman) + 1
  const end = Math.min(start + mahasiswaRows.value.length - 1, total)

  return `Showing ${start}-${end} of ${total} mahasiswa`
})

const makeInitial = (value) => {
  const words = String(value || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)

  if (words.length === 0) {
    return '?'
  }

  return words.map((word) => word.charAt(0)).join('').toUpperCase()
}

const formatDate = (value) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

const normalizeMahasiswa = (mahasiswa) => {
  const wilayah = mahasiswa.wilayah && typeof mahasiswa.wilayah === 'object' ? mahasiswa.wilayah : {}
  const hasCoordinates = mahasiswa.latitude !== null && mahasiswa.latitude !== undefined && mahasiswa.longitude !== null && mahasiswa.longitude !== undefined

  return {
    ...mahasiswa,
    raw: mahasiswa,
    initial: makeInitial(mahasiswa.nama),
    wilayahLabel: wilayah.nama || '-',
    angkatanLabel: mahasiswa.angkatan || '-',
    coordinateLabel: hasCoordinates ? `${Number(mahasiswa.latitude).toFixed(6)}, ${Number(mahasiswa.longitude).toFixed(6)}` : '-',
    hasCoordinates,
    updatedLabel: formatDate(mahasiswa.diubah_pada || mahasiswa.dibuat_pada)
  }
}

const extractErrorMessage = (error, fallback = 'Terjadi kesalahan saat memuat data mahasiswa.') => {
  return error?.response?.data?.message || error?.message || fallback
}

const fetchMahasiswa = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const params = {
      page: pagination.value.halaman_sekarang,
      per_page: pagination.value.per_halaman
    }

    if (searchQuery.value.trim() !== '') {
      params.search = searchQuery.value.trim()
    }

    const response = await $api.get('/mahasiswa', { params })
    const payload = response.data?.data || {}

    mahasiswaRows.value = Array.isArray(payload.data) ? payload.data.map(normalizeMahasiswa) : []
    pagination.value = {
      halaman_sekarang: Number(payload.halaman_sekarang || 1),
      per_halaman: Number(payload.per_halaman || 10),
      total_data: Number(payload.total_data || 0),
      total_halaman: Number(payload.total_halaman || 1)
    }
  } catch (error) {
    mahasiswaRows.value = []
    errorMessage.value = extractErrorMessage(error)
  } finally {
    isLoading.value = false
  }
}

const refreshMahasiswa = () => {
  fetchMahasiswa()
}

const goToPage = (page) => {
  const safePage = Math.min(Math.max(1, page), pagination.value.total_halaman || 1)

  if (safePage === pagination.value.halaman_sekarang) {
    return
  }

  pagination.value.halaman_sekarang = safePage
  fetchMahasiswa()
}

const resetToFirstPageAndFetch = () => {
  pagination.value.halaman_sekarang = 1
  fetchMahasiswa()
}

const openCreateModal = () => {
  selectedFormMahasiswa.value = null
  formMode.value = 'create'
}

const openEditModal = (mahasiswa) => {
  selectedFormMahasiswa.value = mahasiswa
  formMode.value = 'edit'
}

const closeFormModal = () => {
  selectedFormMahasiswa.value = null
  formMode.value = ''
}

const handleSaved = () => {
  closeFormModal()
  selectedDeleteMahasiswa.value = null
  fetchMahasiswa()
}

const handleImported = () => {
  fetchMahasiswa()
}

watch(searchInput, (value) => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    searchQuery.value = value
  }, 350)
})

watch(searchQuery, resetToFirstPageAndFetch)

onMounted(() => {
  fetchMahasiswa()
})
</script>
