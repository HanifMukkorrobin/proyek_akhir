<template>
  <div class="space-y-6">
    <Head>
      <Title>Log Aktivitas | GeoVisit PJJ IT</Title>
    </Head>

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
      <div>
        <h1 class="text-headline-md font-black text-on-surface md:text-3xl">Log Aktivitas</h1>
        <p class="mt-2 text-body-sm text-on-surface-variant">Pantau aktivitas API, perubahan data, status proses, dan audit akses pengguna.</p>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <button
          class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-semibold text-on-surface-variant shadow-sm transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60"
          type="button"
          :disabled="isLoading || logs.length === 0"
          @click="exportCurrentPage"
        >
          <Icon icon="solar:file-download-bold-duotone" class="h-5 w-5" />
          Export CSV
        </button>
        <button
          class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-body-sm font-semibold text-on-primary shadow-md shadow-primary/20 transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
          type="button"
          :disabled="isLoading || summaryLoading"
          @click="refreshAll"
        >
          <Icon icon="solar:refresh-bold-duotone" class="h-5 w-5" :class="{ 'animate-spin': isLoading || summaryLoading }" />
          Refresh
        </button>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article
        v-for="card in summaryCards"
        :key="card.key"
        class="relative overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-5 shadow-sm"
      >
        <div class="mb-4 flex items-start justify-between">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg text-primary" :class="card.iconClass">
            <Icon :icon="card.icon" class="h-6 w-6" />
          </div>
          <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase" :class="card.badgeClass">{{ card.badge }}</span>
        </div>
        <p class="text-label-caps uppercase text-on-surface-variant">{{ card.label }}</p>
        <div v-if="summaryLoading" class="mt-2 h-9 w-24 animate-pulse rounded bg-surface-container-high" />
        <p v-else class="mt-1 text-3xl font-black text-on-surface">{{ card.value }}</p>
        <p class="mt-2 text-body-sm text-on-surface-variant">{{ card.description }}</p>
      </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm">
      <div class="space-y-4 border-b border-outline-variant px-6 py-4">
        <div class="grid gap-3 xl:grid-cols-[minmax(260px,1fr)_auto_auto_auto_auto_auto] xl:items-center">
          <label class="relative block">
            <Icon icon="solar:magnifer-linear" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-on-surface-variant" />
            <input
              v-model="searchInput"
              class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest pl-12 pr-4 text-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="search"
              placeholder="Cari modul, aksi, user, path, target, atau pesan..."
            >
          </label>

          <select
            v-model="moduleFilter"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
          >
            <option v-for="option in moduleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>

          <select
            v-model="statusFilter"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
          >
            <option value="">Semua Status</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
          </select>

          <select
            v-model="methodFilter"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
          >
            <option value="">Semua Method</option>
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="PATCH">PATCH</option>
            <option value="DELETE">DELETE</option>
          </select>

          <input
            v-model="dateFrom"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
            type="date"
            aria-label="Tanggal mulai"
          >

          <input
            v-model="dateTo"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
            type="date"
            aria-label="Tanggal akhir"
          >
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="filter in quickStatusFilters"
              :key="filter.value"
              class="rounded-lg px-4 py-2 text-body-sm transition"
              :class="statusFilter === filter.value ? 'bg-surface-container-high font-semibold text-on-surface' : 'text-on-surface-variant hover:bg-surface-container-low'"
              type="button"
              @click="statusFilter = filter.value"
            >
              {{ filter.label }}
            </button>
          </div>

          <button
            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-semibold text-on-surface-variant transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isLoading"
            @click="clearFilters"
          >
            <Icon icon="solar:filter-bold-duotone" class="h-4 w-4" />
            Reset Filter
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mx-6 mt-4 rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
        {{ errorMessage }}
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[1220px] border-collapse text-left">
          <thead>
            <tr class="bg-surface-container-low">
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Waktu</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">User</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Aktivitas</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Endpoint</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Target</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Status</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Durasi</th>
              <th class="px-6 py-4 text-right text-label-caps uppercase text-on-surface-variant">Actions</th>
            </tr>
          </thead>
          <tbody v-if="isLoading" class="divide-y divide-outline-variant/20">
            <tr v-for="index in 6" :key="index">
              <td v-for="column in 8" :key="column" class="px-6 py-4">
                <div class="h-5 animate-pulse rounded bg-surface-container-high" :class="column === 8 ? 'ml-auto w-20' : column === 3 || column === 4 ? 'w-52' : 'w-28'" />
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="logs.length > 0" class="divide-y divide-outline-variant/20">
            <tr v-for="log in logs" :key="log.log_id" class="transition hover:bg-surface-container-low/60">
              <td class="whitespace-nowrap px-6 py-4">
                <p class="font-mono text-xs font-semibold text-on-surface">{{ log.dateLabel }}</p>
                <p class="mt-1 font-mono text-[11px] text-on-surface-variant">{{ log.timeLabel }}</p>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-fixed/20 text-sm font-bold text-primary">{{ log.initial }}</div>
                  <div class="min-w-0">
                    <p class="max-w-[180px] truncate text-body-sm font-semibold text-on-surface">{{ log.actorName }}</p>
                    <p class="max-w-[180px] truncate text-xs text-on-surface-variant">{{ log.actorMeta }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-col gap-2">
                  <div class="flex items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase" :class="moduleClass(log.modul)">{{ moduleLabel(log.modul) }}</span>
                    <span class="rounded-full bg-surface-container-high px-3 py-1 text-[11px] font-bold uppercase text-on-surface-variant">{{ actionLabel(log.aksi) }}</span>
                  </div>
                  <p class="max-w-[340px] truncate text-body-sm text-on-surface-variant">{{ log.deskripsi || log.response_message || '-' }}</p>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <span class="rounded-md px-2 py-1 font-mono text-[11px] font-bold" :class="methodClass(log.method)">{{ log.method || '-' }}</span>
                  <span class="max-w-[260px] truncate font-mono text-xs text-on-surface-variant">{{ log.path || '-' }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <p class="text-body-sm font-semibold text-on-surface">{{ log.target_tipe || '-' }}</p>
                <p class="mt-1 max-w-[180px] truncate font-mono text-[11px] text-on-surface-variant">{{ log.target_id || '-' }}</p>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2" :class="log.statusClass">
                  <span class="h-2 w-2 rounded-full" :class="log.dotClass" />
                  <span class="text-xs font-bold uppercase">{{ log.statusLabel }}</span>
                  <span class="rounded bg-surface-container-low px-2 py-0.5 font-mono text-[11px] text-on-surface-variant">{{ log.status_code || '-' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 font-mono text-xs text-on-surface-variant">{{ durationLabel(log.duration_ms) }}</td>
              <td class="px-6 py-4 text-right">
                <button
                  class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary disabled:cursor-not-allowed disabled:opacity-60"
                  type="button"
                  title="Detail log"
                  aria-label="Detail log"
                  :disabled="detailLoadingId === log.log_id"
                  @click.stop.prevent="openDetail(log.log_id)"
                >
                  <Icon :icon="detailLoadingId === log.log_id ? 'solar:refresh-bold-duotone' : 'solar:eye-bold-duotone'" class="h-5 w-5" :class="{ 'animate-spin': detailLoadingId === log.log_id }" />
                </button>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="8" class="px-6 py-14 text-center">
                <div class="mx-auto flex max-w-sm flex-col items-center">
                  <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                    <Icon icon="solar:document-add-bold-duotone" class="h-6 w-6" />
                  </div>
                  <p class="text-body-md font-semibold text-on-surface">Tidak ada log ditemukan</p>
                  <p class="mt-1 text-body-sm text-on-surface-variant">Ubah filter atau jalankan aktivitas API baru.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

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

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.8fr)]">
      <article class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm">
        <div class="border-b border-outline-variant px-6 py-5">
          <h2 class="text-title-lg font-bold text-on-surface">Aktivitas Terbaru</h2>
          <p class="mt-1 text-body-sm text-on-surface-variant">Lima event terbaru dari halaman aktif.</p>
        </div>
        <div class="space-y-4 p-6">
          <div v-if="logs.length === 0 && !isLoading" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest p-6 text-center text-body-sm text-on-surface-variant">
            Belum ada event untuk ditampilkan.
          </div>
          <div v-for="event in recentEvents" :key="event.log_id" class="flex gap-4 border-b border-dashed border-outline-variant/60 pb-4 last:border-0 last:pb-0">
            <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full" :class="event.dotClass" />
            <div class="min-w-0">
              <p class="text-body-sm font-bold text-on-surface">{{ moduleLabel(event.modul) }} - {{ actionLabel(event.aksi) }}</p>
              <p class="mt-1 line-clamp-2 text-xs text-on-surface-variant">{{ event.deskripsi || event.response_message || '-' }}</p>
              <p class="mt-2 font-mono text-[10px] text-primary">{{ event.relativeLabel }}</p>
            </div>
          </div>
        </div>
      </article>

      <article class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm">
        <div class="border-b border-outline-variant px-6 py-5">
          <h2 class="text-title-lg font-bold text-on-surface">Modul Teratas</h2>
          <p class="mt-1 text-body-sm text-on-surface-variant">Distribusi log berdasarkan modul.</p>
        </div>
        <div class="space-y-3 p-6">
          <div v-if="topModules.length === 0" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest p-6 text-center text-body-sm text-on-surface-variant">
            Belum ada ringkasan modul.
          </div>
          <div v-for="module in topModules" :key="module.modul" class="rounded-xl border border-outline-variant/35 bg-surface-container-lowest p-4">
            <div class="mb-2 flex items-center justify-between gap-3">
              <span class="text-body-sm font-bold text-on-surface">{{ moduleLabel(module.modul) }}</span>
              <span class="font-mono text-xs text-on-surface-variant">{{ formatNumber(module.total) }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface-container-high">
              <div class="h-full rounded-full bg-primary" :style="{ width: `${module.percent}%` }" />
            </div>
          </div>
        </div>
      </article>
    </section>

    <div v-if="selectedDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-emerald-950/45 p-4 backdrop-blur-sm" @click.self="closeDetail">
      <section class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-outline-variant px-6 py-5">
          <div>
            <p class="text-label-caps uppercase text-on-surface-variant">Detail Log</p>
            <h2 class="mt-1 text-title-lg font-bold text-on-surface">{{ moduleLabel(selectedDetail.modul) }} - {{ actionLabel(selectedDetail.aksi) }}</h2>
          </div>
          <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low" type="button" @click="closeDetail">
            <Icon icon="solar:close-circle-bold-duotone" class="h-6 w-6" />
          </button>
        </div>

        <div class="max-h-[calc(90vh-88px)] space-y-5 overflow-y-auto p-6">
          <div class="grid gap-4 md:grid-cols-2">
            <div v-for="item in detailFields" :key="item.label" class="rounded-xl border border-outline-variant/35 bg-surface-container-lowest p-4">
              <p class="text-label-caps uppercase text-on-surface-variant">{{ item.label }}</p>
              <p class="mt-2 break-words text-body-sm font-semibold text-on-surface">{{ item.value }}</p>
            </div>
          </div>

          <div class="rounded-xl border border-outline-variant/35 bg-surface-container-lowest p-4">
            <p class="text-label-caps uppercase text-on-surface-variant">Deskripsi</p>
            <p class="mt-2 text-body-sm text-on-surface">{{ selectedDetail.deskripsi || '-' }}</p>
          </div>

          <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-outline-variant/35 bg-surface-container-lowest p-4">
              <p class="mb-3 text-label-caps uppercase text-on-surface-variant">Request Payload</p>
              <pre class="max-h-72 overflow-auto rounded-lg bg-slate-950 p-4 text-xs text-primary-fixed">{{ formatJson(selectedDetail.request_payload) }}</pre>
            </div>
            <div class="rounded-xl border border-outline-variant/35 bg-surface-container-lowest p-4">
              <p class="mb-3 text-label-caps uppercase text-on-surface-variant">Metadata</p>
              <pre class="max-h-72 overflow-auto rounded-lg bg-slate-950 p-4 text-xs text-primary-fixed">{{ formatJson(selectedDetail.metadata) }}</pre>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, onMounted, ref, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const { $api } = useNuxtApp()

const logs = ref([])
const isLoading = ref(false)
const summaryLoading = ref(false)
const errorMessage = ref('')
const searchInput = ref('')
const searchQuery = ref('')
const moduleFilter = ref('')
const statusFilter = ref('')
const methodFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const detailLoadingId = ref('')
const selectedDetail = ref(null)
const summary = ref({
  total_log: 0,
  total_sukses: 0,
  total_gagal: 0,
  total_hari_ini: 0,
  modul_teratas: []
})
const pagination = ref({
  halaman_sekarang: 1,
  per_halaman: 10,
  total_data: 0,
  total_halaman: 1
})

let searchTimer = null

const moduleOptions = [
  { label: 'Semua Modul', value: '' },
  { label: 'Auth', value: 'auth' },
  { label: 'Users', value: 'users' },
  { label: 'Mahasiswa', value: 'mahasiswa' },
  { label: 'Import Mahasiswa', value: 'mahasiswa_import' },
  { label: 'Wilayah', value: 'wilayah' },
  { label: 'Dashboard', value: 'dashboard' },
  { label: 'Activity Logs', value: 'activity_logs' },
  { label: 'Public Wilayah', value: 'public_wilayah' },
  { label: 'Public Klasifikasi', value: 'public_klasifikasi_alamat' }
]

const quickStatusFilters = [
  { label: 'Semua', value: '' },
  { label: 'Success', value: 'success' },
  { label: 'Failed', value: 'failed' }
]

const summaryCards = computed(() => [
  {
    key: 'total',
    label: 'Total Log',
    value: formatNumber(summary.value.total_log),
    description: 'Seluruh aktivitas yang terekam.',
    badge: 'Audit',
    badgeClass: 'bg-surface-container-high text-on-surface-variant',
    iconClass: 'bg-primary-fixed/20',
    icon: 'solar:document-text-bold-duotone'
  },
  {
    key: 'success',
    label: 'Sukses',
    value: formatNumber(summary.value.total_sukses),
    description: 'Request dengan status 2xx/3xx.',
    badge: successRate.value,
    badgeClass: 'bg-primary-fixed-dim text-on-primary-fixed-variant',
    iconClass: 'bg-primary-fixed/20',
    icon: 'solar:check-circle-bold-duotone'
  },
  {
    key: 'failed',
    label: 'Gagal',
    value: formatNumber(summary.value.total_gagal),
    description: 'Request validasi/error/server gagal.',
    badge: 'Monitor',
    badgeClass: 'bg-error-container text-on-error-container',
    iconClass: 'bg-error-container/60',
    icon: 'solar:danger-triangle-bold-duotone'
  },
  {
    key: 'today',
    label: 'Hari Ini',
    value: formatNumber(summary.value.total_hari_ini),
    description: 'Aktivitas tercatat pada tanggal ini.',
    badge: 'Live',
    badgeClass: 'bg-primary-fixed/15 text-primary',
    iconClass: 'bg-surface-container-high',
    icon: 'solar:clock-circle-bold-duotone'
  }
])

const successRate = computed(() => {
  const total = Number(summary.value.total_log || 0)

  if (total === 0) {
    return '0%'
  }

  return `${Math.round((Number(summary.value.total_sukses || 0) / total) * 100)}%`
})

const paginationLabel = computed(() => {
  const total = pagination.value.total_data

  if (total === 0) {
    return 'Showing 0 logs'
  }

  const start = ((pagination.value.halaman_sekarang - 1) * pagination.value.per_halaman) + 1
  const end = Math.min(start + logs.value.length - 1, total)

  return `Showing ${start}-${end} of ${total} logs`
})

const recentEvents = computed(() => logs.value.slice(0, 5))

const topModules = computed(() => {
  const items = Array.isArray(summary.value.modul_teratas) ? summary.value.modul_teratas : []
  const max = Math.max(...items.map((item) => Number(item.total || 0)), 1)

  return items.map((item) => ({
    ...item,
    percent: Math.max(4, Math.round((Number(item.total || 0) / max) * 100))
  }))
})

const detailFields = computed(() => {
  if (!selectedDetail.value) {
    return []
  }

  return [
    { label: 'Waktu', value: formatDateTime(selectedDetail.value.dibuat_pada) },
    { label: 'User', value: selectedDetail.value.actorName || '-' },
    { label: 'Username', value: selectedDetail.value.username || '-' },
    { label: 'Usergroup', value: selectedDetail.value.usergroup_kode || '-' },
    { label: 'Method', value: selectedDetail.value.method || '-' },
    { label: 'Path', value: selectedDetail.value.path || '-' },
    { label: 'Target', value: `${selectedDetail.value.target_tipe || '-'} / ${selectedDetail.value.target_id || '-'}` },
    { label: 'Status', value: `${selectedDetail.value.status || '-'} (${selectedDetail.value.status_code || '-'})` },
    { label: 'IP Address', value: selectedDetail.value.ip_address || '-' },
    { label: 'Durasi', value: durationLabel(selectedDetail.value.duration_ms) }
  ]
})

const formatNumber = (value) => {
  return new Intl.NumberFormat('id-ID').format(Number(value || 0))
}

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
  const date = new Date(value)

  if (!value || Number.isNaN(date.getTime())) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  }).format(date)
}

const formatTime = (value) => {
  const date = new Date(value)

  if (!value || Number.isNaN(date.getTime())) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  }).format(date)
}

const formatDateTime = (value) => {
  const date = new Date(value)

  if (!value || Number.isNaN(date.getTime())) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  }).format(date)
}

const relativeTime = (value) => {
  const date = new Date(value)

  if (!value || Number.isNaN(date.getTime())) {
    return '-'
  }

  const diffSeconds = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000))

  if (diffSeconds < 60) {
    return `${diffSeconds}s ago`
  }

  const diffMinutes = Math.round(diffSeconds / 60)
  if (diffMinutes < 60) {
    return `${diffMinutes}m ago`
  }

  const diffHours = Math.round(diffMinutes / 60)
  if (diffHours < 24) {
    return `${diffHours}h ago`
  }

  const diffDays = Math.round(diffHours / 24)
  return `${diffDays}d ago`
}

const durationLabel = (duration) => {
  if (duration === null || duration === undefined || duration === '') {
    return '-'
  }

  const numeric = Number(duration)

  if (Number.isNaN(numeric)) {
    return '-'
  }

  return numeric >= 1000 ? `${(numeric / 1000).toFixed(2)}s` : `${numeric}ms`
}

const moduleLabel = (modul) => {
  const labels = {
    auth: 'Auth',
    users: 'Users',
    mahasiswa: 'Mahasiswa',
    mahasiswa_import: 'Import Mahasiswa',
    wilayah: 'Wilayah',
    dashboard: 'Dashboard',
    activity_logs: 'Activity Logs',
    public_wilayah: 'Public Wilayah',
    public_klasifikasi_alamat: 'Public Klasifikasi'
  }

  return labels[modul] || String(modul || '-').replaceAll('_', ' ')
}

const actionLabel = (aksi) => {
  const labels = {
    lihat: 'Lihat',
    tambah: 'Tambah',
    ubah: 'Ubah',
    hapus: 'Hapus',
    login: 'Login',
    proses: 'Proses',
    scan_import: 'Scan Import',
    confirm_import: 'Confirm Import',
    download_template: 'Download Template',
    reset_password: 'Reset Password',
    lihat_summary: 'Lihat Summary',
    lihat_chart: 'Lihat Chart',
    lihat_wilayah_tree: 'Lihat Wilayah Tree'
  }

  return labels[aksi] || String(aksi || '-').replaceAll('_', ' ')
}

const moduleClass = (modul) => {
  const normalized = String(modul || '')

  if (normalized === 'auth') {
    return 'bg-secondary-container text-on-secondary-container'
  }

  if (normalized.includes('mahasiswa')) {
    return 'bg-tertiary-fixed text-on-tertiary-fixed'
  }

  if (normalized === 'users') {
    return 'bg-primary-fixed/20 text-primary'
  }

  if (normalized === 'wilayah' || normalized === 'dashboard') {
    return 'bg-primary-fixed-dim text-on-primary-fixed-variant'
  }

  if (normalized === 'activity_logs') {
    return 'bg-surface-container-high text-on-surface-variant'
  }

  return 'bg-surface-container-low text-on-surface-variant'
}

const methodClass = (method) => {
  const normalized = String(method || '').toUpperCase()

  if (normalized === 'GET') {
    return 'bg-secondary-container text-on-secondary-container'
  }

  if (normalized === 'POST') {
    return 'bg-primary-fixed/20 text-primary'
  }

  if (normalized === 'PUT' || normalized === 'PATCH') {
    return 'bg-tertiary-fixed/70 text-on-tertiary-fixed-variant'
  }

  if (normalized === 'DELETE') {
    return 'bg-error-container text-on-error-container'
  }

  return 'bg-surface-container-high text-on-surface-variant'
}

const normalizeLog = (log) => {
  const actorName = log.nama_user || log.user?.nama || log.username || 'System'
  const isSuccess = log.status === 'success'

  return {
    ...log,
    raw: log,
    actorName,
    actorMeta: log.username ? `@${log.username}` : log.usergroup_kode || '-',
    initial: makeInitial(actorName),
    dateLabel: formatDate(log.dibuat_pada),
    timeLabel: formatTime(log.dibuat_pada),
    relativeLabel: relativeTime(log.dibuat_pada),
    statusLabel: isSuccess ? 'Success' : 'Failed',
    statusClass: isSuccess ? 'text-primary' : 'text-error',
    dotClass: isSuccess ? 'bg-primary' : 'bg-error'
  }
}

const normalizeSummary = (payload) => {
  return {
    total_log: Number(payload?.total_log || 0),
    total_sukses: Number(payload?.total_sukses || 0),
    total_gagal: Number(payload?.total_gagal || 0),
    total_hari_ini: Number(payload?.total_hari_ini || 0),
    modul_teratas: Array.isArray(payload?.modul_teratas) ? payload.modul_teratas : []
  }
}

const extractErrorMessage = (error, fallback = 'Terjadi kesalahan saat memuat log aktivitas.') => {
  return error?.response?.data?.message || error?.message || fallback
}

const fetchLogs = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const params = {
      page: pagination.value.halaman_sekarang,
      per_page: pagination.value.per_halaman,
      sort_by: 'dibuat_pada',
      sort_direction: 'desc'
    }

    if (searchQuery.value.trim() !== '') {
      params.search = searchQuery.value.trim()
    }

    if (moduleFilter.value !== '') {
      params.modul = moduleFilter.value
    }

    if (statusFilter.value !== '') {
      params.status = statusFilter.value
    }

    if (methodFilter.value !== '') {
      params.method = methodFilter.value
    }

    if (dateFrom.value !== '') {
      params.date_from = dateFrom.value
    }

    if (dateTo.value !== '') {
      params.date_to = dateTo.value
    }

    const response = await $api.get('/activity-logs', { params })
    const payload = response.data?.data || {}

    logs.value = Array.isArray(payload.data) ? payload.data.map(normalizeLog) : []
    pagination.value = {
      halaman_sekarang: Number(payload.halaman_sekarang || 1),
      per_halaman: Number(payload.per_halaman || 10),
      total_data: Number(payload.total_data || 0),
      total_halaman: Number(payload.total_halaman || 1)
    }
  } catch (error) {
    logs.value = []
    errorMessage.value = extractErrorMessage(error)
  } finally {
    isLoading.value = false
  }
}

const fetchSummary = async () => {
  summaryLoading.value = true

  try {
    const params = {}

    if (dateFrom.value !== '') {
      params.date_from = dateFrom.value
    }

    if (dateTo.value !== '') {
      params.date_to = dateTo.value
    }

    const response = await $api.get('/activity-logs/summary', { params })
    summary.value = normalizeSummary(response.data?.data)
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Terjadi kesalahan saat memuat ringkasan log aktivitas.')
  } finally {
    summaryLoading.value = false
  }
}

const refreshAll = () => {
  fetchLogs()
  fetchSummary()
}

const goToPage = (page) => {
  const safePage = Math.min(Math.max(1, page), pagination.value.total_halaman || 1)

  if (safePage === pagination.value.halaman_sekarang) {
    return
  }

  pagination.value.halaman_sekarang = safePage
  fetchLogs()
}

const resetToFirstPageAndFetch = () => {
  pagination.value.halaman_sekarang = 1
  fetchLogs()
}

const clearFilters = () => {
  searchInput.value = ''
  searchQuery.value = ''
  moduleFilter.value = ''
  statusFilter.value = ''
  methodFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  pagination.value.halaman_sekarang = 1
  refreshAll()
}

const openDetail = async (logId) => {
  detailLoadingId.value = logId
  errorMessage.value = ''

  try {
    const response = await $api.get(`/activity-logs/${logId}`)
    selectedDetail.value = normalizeLog(response.data?.data || {})
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Terjadi kesalahan saat memuat detail log aktivitas.')
  } finally {
    detailLoadingId.value = ''
  }
}

const closeDetail = () => {
  selectedDetail.value = null
}

const formatJson = (value) => {
  if (!value || (typeof value === 'object' && Object.keys(value).length === 0)) {
    return '{}'
  }

  return JSON.stringify(value, null, 2)
}

const exportCurrentPage = () => {
  const headings = ['dibuat_pada', 'nama_user', 'username', 'modul', 'aksi', 'method', 'path', 'target_tipe', 'target_id', 'status', 'status_code', 'duration_ms', 'response_message']
  const rows = logs.value.map((log) => headings.map((heading) => csvCell(log[heading] ?? '')))
  const csv = [headings.join(','), ...rows.map((row) => row.join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `log-aktivitas-page-${pagination.value.halaman_sekarang}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

const csvCell = (value) => {
  const text = String(value ?? '')
  return `"${text.replaceAll('"', '""')}"`
}

watch(searchInput, (value) => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    searchQuery.value = value
  }, 350)
})

watch([searchQuery, moduleFilter, statusFilter, methodFilter], resetToFirstPageAndFetch)
watch([dateFrom, dateTo], () => {
  pagination.value.halaman_sekarang = 1
  refreshAll()
})

onMounted(() => {
  refreshAll()
})
</script>
