<template>
  <div class="space-y-6">
    <Head>
      <Title>Dashboard | GeoVisit PJJ IT</Title>
    </Head>

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
      <div>
        <h1 class="text-headline-md font-black text-on-surface md:text-3xl">Dashboard</h1>
        <p class="mt-2 text-body-sm text-on-surface-variant">Pantau ringkasan mahasiswa, distribusi wilayah, dan struktur administrasi.</p>
      </div>

      <button
        class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-5 text-body-sm font-semibold text-on-surface-variant shadow-sm transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60"
        type="button"
        :disabled="isRefreshing"
        @click="refreshDashboard"
      >
        <Icon icon="solar:refresh-bold-duotone" class="h-5 w-5" :class="{ 'animate-spin': isRefreshing }" />
        Refresh
      </button>
    </section>

    <div v-if="errorMessage" class="rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
      {{ errorMessage }}
    </div>

    <section class="grid grid-cols-1 gap-5 md:grid-cols-2">
      <article
        v-for="card in summaryCards"
        :key="card.key"
        class="relative overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-6 shadow-sm"
      >
        <div class="mb-6 flex items-start justify-between">
          <div class="flex h-14 w-14 items-center justify-center rounded-xl text-primary" :class="card.iconClass">
            <Icon :icon="card.icon" class="h-7 w-7" />
          </div>
        </div>
        <p class="text-label-caps uppercase text-on-surface-variant">{{ card.label }}</p>
        <div v-if="summaryLoading" class="mt-2 h-10 w-32 animate-pulse rounded-lg bg-surface-container-high" />
        <p v-else class="mt-1 text-4xl font-black leading-none text-on-surface">{{ card.value }}</p>
        <p class="mt-3 text-body-sm text-on-surface-variant">{{ card.description }}</p>
        <div class="absolute -bottom-8 -right-10 h-28 w-40 rounded-tl-full bg-primary-fixed/15" />
      </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm">
      <div class="flex flex-col justify-between gap-4 border-b border-outline-variant px-6 py-5 lg:flex-row lg:items-center">
        <div>
          <h2 class="text-title-lg font-bold text-on-surface">Distribusi Mahasiswa</h2>
          <p class="mt-1 text-body-sm text-on-surface-variant">Grafik jumlah mahasiswa berdasarkan wilayah.</p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="option in chartGroupOptions"
            :key="option.value"
            class="rounded-lg px-4 py-2 text-body-sm transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="chartGroupBy === option.value ? 'bg-primary font-semibold text-on-primary shadow-sm shadow-primary/20' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'"
            type="button"
            :disabled="chartLoading"
            @click="setChartGroup(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
      </div>

      <div class="relative min-h-[420px] px-3 py-5 sm:px-6">
        <div v-if="chartLoading" class="flex h-[360px] items-center justify-center">
          <div class="flex items-center gap-3 rounded-xl bg-surface-container-low px-4 py-3 text-body-sm font-semibold text-on-surface-variant">
            <Icon icon="solar:refresh-bold-duotone" class="h-5 w-5 animate-spin" />
            Memuat chart...
          </div>
        </div>

        <div
          v-else-if="!hasChartData"
          class="flex h-[360px] flex-col items-center justify-center rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest text-center"
        >
          <Icon icon="solar:chart-square-bold-duotone" class="h-10 w-10 text-on-surface-variant" />
          <p class="mt-3 text-body-md font-semibold text-on-surface">Data chart belum tersedia</p>
          <p class="mt-1 text-body-sm text-on-surface-variant">API tidak mengembalikan kategori atau nilai distribusi.</p>
        </div>

        <div v-show="!chartLoading && hasChartData" ref="chartContainer" class="h-[360px] w-full" />
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm">
      <div class="flex flex-col justify-between gap-4 border-b border-outline-variant px-6 py-5 lg:flex-row lg:items-center">
        <div>
          <h2 class="text-title-lg font-bold text-on-surface">Wilayah Tree</h2>
          <p class="mt-1 text-body-sm text-on-surface-variant">Struktur wilayah dari API dashboard. Klik baris bertanda panah untuk expand atau collapse.</p>
        </div>
        <div class="flex items-center gap-2 rounded-full bg-surface-container-low px-4 py-2 text-body-sm font-semibold text-on-surface-variant">
          <Icon icon="solar:map-point-wave-bold-duotone" class="h-5 w-5 text-primary" />
          {{ visibleTreeRows.length }} baris tampil
        </div>
      </div>

      <div v-if="treeLoading" class="space-y-3 px-6 py-5">
        <div v-for="index in 5" :key="index" class="grid grid-cols-6 gap-4">
          <div class="col-span-2 h-5 animate-pulse rounded bg-surface-container-high" />
          <div class="h-5 animate-pulse rounded bg-surface-container-high" />
          <div class="h-5 animate-pulse rounded bg-surface-container-high" />
          <div class="h-5 animate-pulse rounded bg-surface-container-high" />
          <div class="h-5 animate-pulse rounded bg-surface-container-high" />
        </div>
      </div>

      <div v-else-if="rootWilayahRows.length === 0" class="px-6 py-14 text-center">
        <Icon icon="solar:map-arrow-square-bold-duotone" class="mx-auto h-12 w-12 text-on-surface-variant" />
        <p class="mt-4 text-body-md font-semibold text-on-surface">Data wilayah belum tersedia</p>
        <p class="mt-1 text-body-sm text-on-surface-variant">Endpoint wilayah-tree tidak mengembalikan data root.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[980px] border-collapse text-left">
          <thead>
            <tr class="border-b border-outline-variant bg-surface-container-low text-on-surface-variant">
              <th class="px-6 py-4 text-label-caps uppercase">Wilayah</th>
              <th class="px-6 py-4 text-label-caps uppercase">Kode Wilayah</th>
              <th class="px-6 py-4 text-label-caps uppercase">Mahasiswa</th>
              <th class="px-6 py-4 text-label-caps uppercase">Koordinat</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/20">
            <tr v-for="row in visibleTreeRows" :key="row.wilayah_id" class="transition hover:bg-surface-container-low/60">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3" :style="{ paddingLeft: `${Math.max(row.depth, 0) * 24}px` }">
                  <button
                    v-if="hasChild(row)"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface-variant transition hover:bg-surface-container-low disabled:cursor-wait disabled:opacity-60"
                    type="button"
                    :disabled="isNodeLoading(row.wilayah_id)"
                    :aria-expanded="isExpanded(row.wilayah_id)"
                    @click="toggleNode(row)"
                  >
                    <Icon
                      :icon="isNodeLoading(row.wilayah_id) ? 'solar:refresh-bold-duotone' : 'solar:alt-arrow-right-bold-duotone'"
                      class="h-4 w-4"
                      :class="{ 'rotate-90': isExpanded(row.wilayah_id), 'animate-spin': isNodeLoading(row.wilayah_id) }"
                    />
                  </button>
                  <span v-else class="h-8 w-8 shrink-0" />
                  <div class="min-w-0">
                    <p class="text-body-sm font-semibold text-on-surface">{{ row.nama || '-' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 font-mono text-xs text-on-surface">{{ row.wilayah_id }}</td>
              <td class="px-6 py-4 text-body-sm font-semibold text-on-surface">{{ formatNumber(row.jumlah_mahasiswa || 0) }}</td>
              <td class="px-6 py-4 font-mono text-xs text-on-surface-variant">{{ coordinateLabel(row) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

definePageMeta({
  layout: 'admin'
})

const { $api } = useNuxtApp()

const summary = ref({
  jumlah_mahasiswa: 0,
  jumlah_user_terdaftar: 0
})
const summaryLoading = ref(false)
const chartLoading = ref(false)
const treeLoading = ref(false)
const errorMessage = ref('')
const chartGroupBy = ref('provinsi')
const chartData = ref({
  categories: [],
  series: []
})
const chartContainer = ref(null)
const rootWilayahRows = ref([])
const childrenByParent = ref({})
const expandedNodeIds = ref(new Set())
const loadingNodeIds = ref(new Set())

let chartInstance = null
let highchartsModule = null
let themeObserver = null

const chartGroupOptions = [
  { label: 'Provinsi', value: 'provinsi' },
  { label: 'Kabupaten', value: 'kabupaten' }
]

const summaryCards = computed(() => [
  {
    key: 'mahasiswa',
    label: 'Total Mahasiswa',
    value: formatNumber(summary.value.jumlah_mahasiswa),
    description: 'Jumlah data mahasiswa yang tersimpan di sistem.',
    iconClass: 'bg-primary-fixed/20',
    icon: 'solar:square-academic-cap-bold-duotone'
  },
  {
    key: 'users',
    label: 'User Terdaftar',
    value: formatNumber(summary.value.jumlah_user_terdaftar),
    description: 'Jumlah akun yang tercatat pada manajemen user.',
    iconClass: 'bg-surface-container-high',
    icon: 'solar:users-group-two-rounded-bold-duotone'
  }
])

const isRefreshing = computed(() => summaryLoading.value || chartLoading.value || treeLoading.value)

const hasChartData = computed(() => {
  return chartData.value.categories.length > 0 && chartData.value.series.some((item) => Array.isArray(item.data) && item.data.length > 0)
})

const visibleTreeRows = computed(() => {
  return flattenRows(rootWilayahRows.value, 0)
})

const formatNumber = (value) => {
  return new Intl.NumberFormat('id-ID').format(Number(value || 0))
}

const extractErrorMessage = (error, fallback = 'Terjadi kesalahan saat memuat dashboard.') => {
  return error?.response?.data?.message || error?.message || fallback
}

const normalizeSummary = (payload) => {
  return {
    jumlah_mahasiswa: Number(payload?.jumlah_mahasiswa || 0),
    jumlah_user_terdaftar: Number(payload?.jumlah_user_terdaftar || 0)
  }
}

const normalizeChartData = (payload) => {
  return {
    categories: Array.isArray(payload?.categories) ? payload.categories : [],
    series: Array.isArray(payload?.series)
      ? payload.series.map((item) => ({
          name: item.name || 'Jumlah',
          data: Array.isArray(item.data) ? item.data.map((value) => Number(value || 0)) : []
        }))
      : []
  }
}

const normalizeWilayahRows = (rows) => {
  if (!Array.isArray(rows)) {
    return []
  }

  return rows.map((row) => ({
    ...row,
    wilayah_id: String(row.wilayah_id || ''),
    nama: row.nama || '',
    kode_dukcapil: row.kode_dukcapil || '',
    latitude: row.latitude,
    longitude: row.longitude,
    jumlah_mahasiswa: Number(row.jumlah_mahasiswa || 0),
    parent_wilayah_id: row.parent_wilayah_id || null,
    level: Number(row.level || 0),
    is_have_child: Number(row.is_have_child || 0)
  }))
}

const flattenRows = (rows, depth) => {
  return rows.flatMap((row) => {
    const current = { ...row, depth }

    if (!expandedNodeIds.value.has(row.wilayah_id)) {
      return [current]
    }

    const children = childrenByParent.value[row.wilayah_id] || []
    return [current, ...flattenRows(children, depth + 1)]
  })
}

const hasChild = (row) => {
  return Number(row?.is_have_child || 0) === 1
}

const isExpanded = (wilayahId) => {
  return expandedNodeIds.value.has(wilayahId)
}

const isNodeLoading = (wilayahId) => {
  return loadingNodeIds.value.has(wilayahId)
}

const levelLabel = (level) => {
  const labels = {
    1: 'Provinsi',
    2: 'Kab/Kota',
    3: 'Kecamatan',
    4: 'Kelurahan'
  }

  return labels[Number(level)] || `Level ${level || '-'}`
}

const coordinateLabel = (row) => {
  const latitude = row.latitude
  const longitude = row.longitude

  if (latitude === null || latitude === undefined || longitude === null || longitude === undefined) {
    return '-'
  }

  return `${Number(latitude).toFixed(6)}, ${Number(longitude).toFixed(6)}`
}

const setLoadingNode = (wilayahId, isLoading) => {
  const next = new Set(loadingNodeIds.value)

  if (isLoading) {
    next.add(wilayahId)
  } else {
    next.delete(wilayahId)
  }

  loadingNodeIds.value = next
}

const fetchSummary = async () => {
  summaryLoading.value = true

  try {
    const response = await $api.get('/dashboard/summary')
    summary.value = normalizeSummary(response.data?.data)
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Gagal memuat summary dashboard.')
  } finally {
    summaryLoading.value = false
  }
}

const fetchChart = async () => {
  chartLoading.value = true

  try {
    const response = await $api.get('/dashboard/chart', {
      params: {
        group_by: chartGroupBy.value
      }
    })

    chartData.value = normalizeChartData(response.data?.data)
  } catch (error) {
    chartData.value = {
      categories: [],
      series: []
    }
    errorMessage.value = extractErrorMessage(error, 'Gagal memuat chart dashboard.')
  } finally {
    chartLoading.value = false
    await renderChart()
  }
}

const fetchRootWilayah = async () => {
  treeLoading.value = true

  try {
    const response = await $api.get('/dashboard/wilayah-tree')
    rootWilayahRows.value = normalizeWilayahRows(response.data?.data)
    childrenByParent.value = {}
    expandedNodeIds.value = new Set()
  } catch (error) {
    rootWilayahRows.value = []
    errorMessage.value = extractErrorMessage(error, 'Gagal memuat wilayah tree.')
  } finally {
    treeLoading.value = false
  }
}

const fetchWilayahChildren = async (parentId) => {
  setLoadingNode(parentId, true)

  try {
    const response = await $api.get('/dashboard/wilayah-tree', {
      params: {
        parent_id: parentId
      }
    })

    childrenByParent.value = {
      ...childrenByParent.value,
      [parentId]: normalizeWilayahRows(response.data?.data)
    }
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Gagal memuat child wilayah.')
  } finally {
    setLoadingNode(parentId, false)
  }
}

const toggleNode = async (row) => {
  if (!hasChild(row)) {
    return
  }

  const next = new Set(expandedNodeIds.value)

  if (next.has(row.wilayah_id)) {
    next.delete(row.wilayah_id)
    expandedNodeIds.value = next
    return
  }

  next.add(row.wilayah_id)
  expandedNodeIds.value = next

  if (!childrenByParent.value[row.wilayah_id]) {
    await fetchWilayahChildren(row.wilayah_id)
  }
}

const setChartGroup = (value) => {
  if (chartGroupBy.value === value) {
    return
  }

  chartGroupBy.value = value
  fetchChart()
}

const refreshDashboard = async () => {
  errorMessage.value = ''
  await Promise.all([fetchSummary(), fetchChart(), fetchRootWilayah()])
}

const isDarkTheme = () => {
  if (!import.meta.client) {
    return false
  }

  return document.documentElement.getAttribute('data-theme') === 'dark'
}

const getChartTheme = () => {
  if (isDarkTheme()) {
    return {
      axis: '#9fc4a7',
      axisLine: '#31513b',
      grid: '#183122',
      text: '#edf3e8',
      tooltipBg: '#0b2a17',
      tooltipBorder: '#31513b',
      series: ['#78dc82', '#44b96d', '#2f8f52']
    }
  }

  return {
    axis: '#52635a',
    axisLine: '#d7e3d5',
    grid: '#eef5ed',
    text: '#1b1f1d',
    tooltipBg: '#ffffff',
    tooltipBorder: '#d7e3d5',
    series: ['#087c3d', '#22c55e', '#84cc16']
  }
}

const loadHighcharts = async () => {
  if (highchartsModule) {
    return highchartsModule
  }

  const module = await import('highcharts')
  highchartsModule = module.default || module
  return highchartsModule
}

const destroyChart = () => {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
}

const renderChart = async () => {
  await nextTick()

  if (!import.meta.client || !chartContainer.value || !hasChartData.value) {
    destroyChart()
    return
  }

  const Highcharts = await loadHighcharts()
  destroyChart()
  const chartTheme = getChartTheme()

  chartInstance = Highcharts.chart(chartContainer.value, {
    chart: {
      type: 'column',
      backgroundColor: 'transparent',
      height: 360,
      style: {
        fontFamily: 'Inter, sans-serif'
      }
    },
    title: {
      text: null
    },
    credits: {
      enabled: false
    },
    colors: chartTheme.series,
    xAxis: {
      categories: chartData.value.categories,
      lineColor: chartTheme.axisLine,
      tickColor: chartTheme.axisLine,
      labels: {
        style: {
          color: chartTheme.axis,
          fontSize: '12px'
        }
      }
    },
    yAxis: {
      allowDecimals: false,
      min: 0,
      gridLineColor: chartTheme.grid,
      title: {
        text: 'Jumlah Mahasiswa',
        style: {
          color: chartTheme.axis,
          fontWeight: '600'
        }
      },
      labels: {
        style: {
          color: chartTheme.axis
        }
      }
    },
    legend: {
      align: 'left',
      verticalAlign: 'top',
      itemStyle: {
        color: chartTheme.text,
        fontWeight: '700'
      }
    },
    tooltip: {
      borderRadius: 12,
      borderColor: chartTheme.tooltipBorder,
      backgroundColor: chartTheme.tooltipBg,
      style: {
        color: chartTheme.text
      },
      shadow: false,
      pointFormat: '<b>{point.y}</b> mahasiswa'
    },
    plotOptions: {
      column: {
        borderRadius: 8,
        borderWidth: 0,
        pointPadding: 0.12,
        groupPadding: 0.08
      },
      series: {
        animation: {
          duration: 240
        }
      }
    },
    series: chartData.value.series
  })
}

onMounted(() => {
  refreshDashboard()

  if (!import.meta.client) {
    return
  }

  themeObserver = new MutationObserver(() => {
    if (chartInstance) {
      void renderChart()
    }
  })

  themeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme']
  })
})

onBeforeUnmount(() => {
  destroyChart()
  themeObserver?.disconnect()
  themeObserver = null
})
</script>
