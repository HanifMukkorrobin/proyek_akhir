<template>
  <div class="space-y-6">
    <Head>
      <Title>Log Simulasi Rute | GeoVisit PJJ IT</Title>
    </Head>

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
      <div>
        <h1 class="text-headline-md font-black text-on-surface md:text-3xl">Log Simulasi Rute</h1>
        <p class="mt-2 text-body-sm text-on-surface-variant">Pantau riwayat simulasi rute visitasi semua dosen.</p>
      </div>
      <button
        class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-body-sm font-semibold text-on-primary shadow-md shadow-primary/20 transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
        type="button"
        :disabled="isLoading"
        @click="fetchLogs"
      >
        <Icon icon="solar:refresh-bold-duotone" class="h-5 w-5" :class="{ 'animate-spin': isLoading }" />
        Refresh
      </button>
    </section>

    <!-- Summary Cards -->
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article
        v-for="card in summaryCards"
        :key="card.key"
        class="relative overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-5 shadow-sm"
      >
        <div class="mb-4 flex items-start justify-between">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg" :class="card.iconClass">
            <Icon :icon="card.icon" class="h-6 w-6" />
          </div>
          <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase" :class="card.badgeClass">{{ card.badge }}</span>
        </div>
        <p class="text-label-caps uppercase text-on-surface-variant">{{ card.label }}</p>
        <p class="mt-1 text-3xl font-black text-on-surface">{{ card.value }}</p>
        <p class="mt-2 text-body-sm text-on-surface-variant">{{ card.desc }}</p>
      </article>
    </section>

    <!-- Filter & Table -->
    <section class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm">
      <div class="space-y-3 border-b border-outline-variant px-6 py-4">
        <div class="grid gap-3 xl:grid-cols-[minmax(200px,1fr)_auto_auto_auto_auto] xl:items-center">
          <label class="relative block">
            <Icon icon="solar:magnifer-linear" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-on-surface-variant" />
            <input
              v-model="searchDosen"
              class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest pl-12 pr-4 text-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="search"
              placeholder="Cari nama dosen..."
              @input="debouncedFetch"
            >
          </label>

          <select
            v-model="statusFilter"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
            @change="fetchLogs"
          >
            <option value="">Semua Status</option>
            <option value="success">Berhasil</option>
            <option value="failed">Gagal</option>
            <option value="pending">Pending</option>
          </select>

          <input
            v-model="dateFrom"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
            type="date"
            aria-label="Tanggal mulai"
            @change="fetchLogs"
          >

          <input
            v-model="dateTo"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
            type="date"
            aria-label="Tanggal akhir"
            @change="fetchLogs"
          >

          <button
            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-semibold text-on-surface-variant transition hover:bg-surface-container-low"
            type="button"
            @click="clearFilters"
          >
            <Icon icon="solar:filter-bold-duotone" class="h-4 w-4" />
            Reset
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mx-6 mt-4 rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
        {{ errorMessage }}
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] border-collapse text-left">
          <thead>
            <tr class="bg-surface-container-low">
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Waktu</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Dosen</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Rencana</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Kendaraan</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Hasil</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Status</th>
            </tr>
          </thead>
          <tbody v-if="isLoading" class="divide-y divide-outline-variant/20">
            <tr v-for="i in 8" :key="i">
              <td v-for="c in 6" :key="c" class="px-6 py-4">
                <div class="h-5 animate-pulse rounded bg-surface-container-high" :class="c === 3 ? 'w-48' : 'w-28'" />
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="logs.length > 0" class="divide-y divide-outline-variant/20">
            <tr v-for="log in logs" :key="log.visitasi_rute_id" class="transition hover:bg-surface-container-low/60">
              <td class="whitespace-nowrap px-6 py-4">
                <p class="font-mono text-xs font-semibold text-on-surface">{{ formatDate(log.dibuat_pada) }}</p>
                <p class="mt-0.5 font-mono text-[11px] text-on-surface-variant">{{ formatTime(log.dibuat_pada) }}</p>
              </td>
              <td class="px-6 py-4">
                <p class="text-body-sm font-semibold text-on-surface">{{ log.dosen_nama || '-' }}</p>
                <p class="text-xs text-on-surface-variant">@{{ log.dosen_username || '-' }}</p>
              </td>
              <td class="px-6 py-4">
                <p class="max-w-[200px] truncate text-body-sm font-semibold text-on-surface">{{ log.nama_rencana || '-' }}</p>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-col gap-1">
                  <span class="inline-flex w-fit items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-1 text-[11px] font-bold uppercase text-on-surface-variant">
                    <Icon :icon="log.jenis_kendaraan === 'mobil' ? 'solar:car-bold-duotone' : 'solar:scooter-bold-duotone'" class="h-3.5 w-3.5" />
                    {{ log.jenis_kendaraan || '-' }}
                  </span>
                  <span v-if="log.jenis_kendaraan === 'mobil'" class="text-[11px] text-on-surface-variant">
                    {{ log.lewat_tol ? '✓ Via Tol' : '✗ Tanpa Tol' }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div v-if="log.status === 'success'" class="space-y-0.5">
                  <p class="font-mono text-xs font-bold text-primary">{{ log.total_jarak_km ? `${log.total_jarak_km} km` : '-' }}</p>
                  <p class="text-[11px] text-on-surface-variant">{{ log.total_estimasi_menit ? `${log.total_estimasi_menit} menit` : '-' }}</p>
                </div>
                <span v-else class="text-xs text-on-surface-variant">-</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2" :class="statusClass(log.status)">
                  <span class="h-2 w-2 rounded-full" :class="statusDotClass(log.status)" />
                  <span class="text-xs font-bold uppercase">{{ statusLabel(log.status) }}</span>
                </div>
                <p v-if="log.error_message" class="mt-1 max-w-[180px] truncate text-[11px] text-error" :title="log.error_message">
                  {{ log.error_message }}
                </p>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="px-6 py-14 text-center">
                <div class="mx-auto flex max-w-sm flex-col items-center">
                  <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                    <Icon icon="solar:route-bold-duotone" class="h-6 w-6" />
                  </div>
                  <p class="text-body-md font-semibold text-on-surface">Belum ada log simulasi</p>
                  <p class="mt-1 text-body-sm text-on-surface-variant">Log akan muncul setelah dosen menjalankan simulasi rute.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex flex-col justify-between gap-4 border-t border-outline-variant bg-surface-container-low px-6 py-4 md:flex-row md:items-center">
        <p class="text-body-sm text-on-surface-variant">{{ paginationLabel }}</p>
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
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, onMounted, ref } from 'vue'

definePageMeta({
  layout: 'admin'
})

const { $api } = useNuxtApp()

const logs = ref([])
const isLoading = ref(false)
const errorMessage = ref('')
const searchDosen = ref('')
const statusFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const pagination = ref({
  halaman_sekarang: 1,
  per_halaman: 15,
  total_data: 0,
  total_halaman: 1
})

const stats = ref({
  total: 0,
  berhasil: 0,
  gagal: 0,
  hari_ini: 0
})

let debounceTimer = null

const summaryCards = computed(() => [
  {
    key: 'total',
    label: 'Total Simulasi',
    value: formatNumber(stats.value.total),
    desc: 'Semua simulasi yang pernah dijalankan.',
    badge: 'All Time',
    badgeClass: 'bg-surface-container-high text-on-surface-variant',
    iconClass: 'bg-primary-fixed/20 text-primary',
    icon: 'solar:route-bold-duotone'
  },
  {
    key: 'berhasil',
    label: 'Berhasil',
    value: formatNumber(stats.value.berhasil),
    desc: 'Simulasi dengan status success.',
    badge: `${successRate.value}%`,
    badgeClass: 'bg-primary-fixed-dim text-on-primary-fixed-variant',
    iconClass: 'bg-primary-fixed/20 text-primary',
    icon: 'solar:check-circle-bold-duotone'
  },
  {
    key: 'gagal',
    label: 'Gagal',
    value: formatNumber(stats.value.gagal),
    desc: 'Simulasi dengan status failed.',
    badge: 'Error',
    badgeClass: 'bg-error-container text-on-error-container',
    iconClass: 'bg-error-container/60 text-error',
    icon: 'solar:danger-triangle-bold-duotone'
  },
  {
    key: 'hari_ini',
    label: 'Hari Ini',
    value: formatNumber(stats.value.hari_ini),
    desc: 'Simulasi yang dijalankan hari ini.',
    badge: 'Today',
    badgeClass: 'bg-primary-fixed/15 text-primary',
    iconClass: 'bg-surface-container-high text-on-surface-variant',
    icon: 'solar:clock-circle-bold-duotone'
  }
])

const successRate = computed(() => {
  const total = stats.value.total || 0
  if (total === 0) return 0
  return Math.round((stats.value.berhasil / total) * 100)
})

const paginationLabel = computed(() => {
  const total = pagination.value.total_data
  if (total === 0) return 'Tidak ada data'
  const start = ((pagination.value.halaman_sekarang - 1) * pagination.value.per_halaman) + 1
  const end = Math.min(start + logs.value.length - 1, total)
  return `Showing ${start}–${end} of ${total} simulasi`
})

const formatNumber = (v) => new Intl.NumberFormat('id-ID').format(Number(v || 0))

const formatDate = (v) => {
  const d = new Date(v)
  if (!v || isNaN(d)) return '-'
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d)
}

const formatTime = (v) => {
  const d = new Date(v)
  if (!v || isNaN(d)) return '-'
  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(d)
}

const statusLabel = (s) => ({ success: 'Berhasil', failed: 'Gagal', pending: 'Pending' }[s] || s || '-')
const statusClass = (s) => ({ success: 'text-primary', failed: 'text-error', pending: 'text-on-surface-variant' }[s] || 'text-on-surface-variant')
const statusDotClass = (s) => ({ success: 'bg-primary', failed: 'bg-error', pending: 'bg-on-surface-variant' }[s] || 'bg-on-surface-variant')

const fetchLogs = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const params = {
      page: pagination.value.halaman_sekarang,
      per_page: pagination.value.per_halaman
    }
    if (statusFilter.value) params.status = statusFilter.value
    if (dateFrom.value) params.tanggal_mulai = dateFrom.value
    if (dateTo.value) params.tanggal_akhir = dateTo.value

    const res = await $api.get('/log-simulasi', { params })
    const payload = res.data?.data || {}

    logs.value = Array.isArray(payload.data) ? payload.data : []
    pagination.value = {
      halaman_sekarang: Number(payload.halaman_sekarang || 1),
      per_halaman: Number(payload.per_halaman || 15),
      total_data: Number(payload.total_data || 0),
      total_halaman: Number(payload.total_halaman || 1)
    }

    // Hitung stats dari data yang ada
    updateStats(payload)
  } catch (err) {
    errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal memuat log simulasi.'
    logs.value = []
  } finally {
    isLoading.value = false
  }
}

const updateStats = (payload) => {
  const data = Array.isArray(payload.data) ? payload.data : []
  const today = new Date().toDateString()

  stats.value = {
    total: Number(payload.total_data || 0),
    berhasil: data.filter(l => l.status === 'success').length,
    gagal: data.filter(l => l.status === 'failed').length,
    hari_ini: data.filter(l => new Date(l.dibuat_pada).toDateString() === today).length
  }
}

const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchLogs, 400)
}

const goToPage = (page) => {
  pagination.value.halaman_sekarang = Math.min(Math.max(1, page), pagination.value.total_halaman)
  fetchLogs()
}

const clearFilters = () => {
  searchDosen.value = ''
  statusFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  pagination.value.halaman_sekarang = 1
  fetchLogs()
}

onMounted(fetchLogs)
</script>
