<template>
  <div
    class="min-h-screen bg-white font-sans text-on-surface transition-colors duration-300 dark:bg-forest-950 dark:text-inverse-on-surface"
  >
    <Head>
      <Title>Chart Dashboard | GeoVisit PJJ IT</Title>
    </Head>

    <header
      class="sticky top-0 z-40 border-b border-outline-variant/60 bg-white/95 backdrop-blur-md dark:border-emerald-900/60 dark:bg-forest-950/95"
    >
      <div
        class="mx-auto flex h-16 w-full max-w-[1520px] items-center justify-between gap-4 px-5 sm:px-8 lg:px-10"
      >
        <NuxtLink class="flex min-w-0 items-center gap-3" to="/dashboard/chart">
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-on-primary shadow-md shadow-primary/20"
          >
            <Icon icon="solar:map-point-wave-bold-duotone" class="h-5 w-5" />
          </div>
          <span class="truncate text-lg font-black text-primary"
            >GeoVisit PJJ IT</span
          >
        </NuxtLink>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
          <button
            class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-surface-container-low dark:text-emerald-100/75 dark:hover:bg-forest-900"
            type="button"
            aria-label="Toggle theme"
            @click="toggleTheme"
          >
            <Icon
              v-if="theme === 'dark'"
              icon="solar:sun-2-linear"
              class="h-5 w-5"
            />
            <Icon v-else icon="solar:moon-linear" class="h-5 w-5" />
          </button>

          <button
            class="flex h-10 items-center gap-3 rounded-full border border-outline-variant bg-surface-container-highest pl-2 pr-3 text-left transition hover:bg-surface-container-low dark:border-emerald-800 dark:bg-forest-900 dark:hover:bg-forest-800"
            type="button"
            @click="handleLogout"
          >
            <span
              class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-container-high text-sm font-bold text-on-surface-variant dark:bg-forest-800 dark:text-emerald-100"
            >
              {{ userInitial }}
            </span>
            <span class="hidden min-w-0 sm:block">
              <span
                class="block max-w-40 truncate text-sm font-bold text-on-surface dark:text-emerald-50"
                >{{ userName }}</span
              >
              <span
                class="block text-xs text-on-surface-variant dark:text-emerald-100/65"
                >{{ userRoleLabel }}</span
              >
            </span>
          </button>
        </div>
      </div>
    </header>

    <main
      class="mx-auto w-full max-w-[1520px] px-5 py-8 sm:px-8 lg:px-10 lg:py-10"
    >
      <section
        class="mb-10 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
      >
        <div class="order-2 lg:order-1">
          <h1
            class="text-3xl font-black leading-tight text-slate-950 dark:text-white"
          >
            Overview
          </h1>
          <p class="mt-2 text-body-md text-slate-500 dark:text-emerald-100/70">
            Real-time geospatial analytics
          </p>
        </div>

        <div class="order-1 flex justify-start lg:order-2 lg:justify-end">
          <div
            class="flex items-center rounded-full border border-outline-variant bg-slate-50 p-1 shadow-panel dark:border-emerald-800 dark:bg-forest-900"
          >
            <button
              class="flex h-10 items-center gap-2 rounded-full bg-primary px-4 text-sm font-semibold text-on-primary shadow-sm sm:px-5"
              type="button"
            >
              <Icon icon="solar:chart-2-bold-duotone" class="h-5 w-5" />
              Chart Mode
            </button>
            <NuxtLink
              class="flex h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold text-slate-600 transition hover:bg-white sm:px-5 dark:text-emerald-100/70 dark:hover:bg-forest-800"
              to="/dashboard/map"
            >
              <Icon icon="solar:earth-bold-duotone" class="h-5 w-5" />
              3D Map Mode
            </NuxtLink>
          </div>
        </div>
      </section>

      <div
        v-if="errorMessage"
        class="mb-6 flex gap-3 rounded-xl border border-error/20 bg-error-container/50 px-4 py-3 text-body-sm text-on-error-container"
      >
        <Icon
          icon="solar:danger-triangle-bold-duotone"
          class="h-5 w-5 shrink-0"
        />
        <span>{{ errorMessage }}</span>
      </div>

      <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <article
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-panel dark:border-emerald-900/70 dark:bg-forest-900"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p
                class="text-label-caps uppercase text-slate-500 dark:text-emerald-100/60"
              >
                Total Mahasiswa PJJ 2023
              </p>
              <div
                v-if="isLoading"
                class="mt-4 h-12 w-36 animate-pulse rounded-lg bg-surface-container-high dark:bg-forest-800"
              />
              <p
                v-else
                class="mt-3 text-5xl font-black leading-none text-primary"
              >
                {{ formatNumber(summary.jumlah_mahasiswa) }}
              </p>
            </div>
            <div
              class="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary"
            >
              <Icon
                icon="solar:users-group-rounded-bold-duotone"
                class="h-7 w-7"
              />
            </div>
          </div>
        </article>

        <article
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-panel dark:border-emerald-900/70 dark:bg-forest-900"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p
                class="text-label-caps uppercase text-slate-500 dark:text-emerald-100/60"
              >
                Kabupaten/Kota Terjangkau
              </p>
              <div
                v-if="isLoading"
                class="mt-4 h-12 w-28 animate-pulse rounded-lg bg-surface-container-high dark:bg-forest-800"
              />
              <p
                v-else
                class="mt-3 text-5xl font-black leading-none text-slate-950 dark:text-white"
              >
                {{ formatNumber(kabupatenReachCount) }}
              </p>
            </div>
            <div
              class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-forest-800 dark:text-emerald-100/70"
            >
              <Icon icon="solar:map-bold-duotone" class="h-7 w-7" />
            </div>
          </div>
          <div class="mt-9 flex items-center gap-3">
            <div
              class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-forest-800"
            >
              <div
                class="h-full rounded-full bg-primary transition-all duration-500"
                :style="{ width: `${coveragePercent}%` }"
              />
            </div>
            <span
              class="whitespace-nowrap text-label-caps uppercase text-slate-500 dark:text-emerald-100/60"
              >{{ coveragePercent }}% Coverage</span
            >
          </div>
        </article>
      </section>

      <section
        class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(360px,0.95fr)]"
      >
        <article
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-panel dark:border-emerald-900/70 dark:bg-forest-900"
        >
          <div
            class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between dark:border-emerald-900/70"
          >
            <div>
              <h2 class="text-2xl font-black text-slate-950 dark:text-white">
                Persebaran Domisili
              </h2>
              <p
                class="mt-1 text-body-md text-slate-500 dark:text-emerald-100/65"
              >
                {{ chartSubtitle }}
              </p>
            </div>
            <div
              class="flex rounded-lg border border-outline-variant bg-slate-50 p-1 dark:border-emerald-800 dark:bg-forest-950"
            >
              <button
                v-for="option in chartGroupOptions"
                :key="option.value"
                class="h-9 rounded-md px-3 text-body-sm font-semibold transition"
                :class="
                  chartGroupBy === option.value
                    ? 'bg-white text-primary shadow-sm dark:bg-forest-800'
                    : 'text-slate-500 hover:text-primary dark:text-emerald-100/65'
                "
                type="button"
                @click="chartGroupBy = option.value"
              >
                {{ option.label }}
              </button>
            </div>
          </div>

          <div v-if="isLoading" class="space-y-8 py-16">
            <div v-for="index in 5" :key="index" class="space-y-3">
              <div class="flex justify-between">
                <div
                  class="h-4 w-32 animate-pulse rounded bg-surface-container-high dark:bg-forest-800"
                />
                <div
                  class="h-4 w-16 animate-pulse rounded bg-surface-container-high dark:bg-forest-800"
                />
              </div>
              <div
                class="h-4 animate-pulse rounded-full bg-surface-container-high dark:bg-forest-800"
              />
            </div>
          </div>

          <div
            v-else-if="activeChartRows.length === 0"
            class="flex min-h-[360px] flex-col items-center justify-center rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest text-center dark:border-emerald-800 dark:bg-forest-950"
          >
            <Icon
              icon="solar:chart-square-bold-duotone"
              class="h-10 w-10 text-slate-400"
            />
            <p
              class="mt-3 text-body-md font-semibold text-slate-900 dark:text-white"
            >
              Data chart belum tersedia
            </p>
            <p
              class="mt-1 text-body-sm text-slate-500 dark:text-emerald-100/65"
            >
              Endpoint dashboard belum mengembalikan data distribusi.
            </p>
          </div>

          <div v-else class="space-y-8 py-12">
            <div
              v-for="(row, index) in activeChartRows"
              :key="row.name"
              class="space-y-2"
            >
              <div class="flex items-end justify-between gap-4">
                <span
                  class="truncate text-body-md font-semibold text-slate-950 dark:text-white"
                  >{{ row.name }}</span
                >
                <span class="font-mono text-sm font-bold text-primary">{{
                  formatNumber(row.value)
                }}</span>
              </div>
              <div
                class="h-4 overflow-hidden rounded-full bg-slate-100 dark:bg-forest-800"
              >
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{
                    width: `${row.percent}%`,
                    backgroundColor: barColor(index),
                  }"
                />
              </div>
            </div>
          </div>
        </article>

        <aside
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-panel dark:border-emerald-900/70 dark:bg-forest-900"
        >
          <div class="mb-6">
            <h2 class="text-2xl font-black text-slate-950 dark:text-white">
              Hierarki Wilayah
            </h2>
            <p
              class="mt-1 text-body-md text-slate-500 dark:text-emerald-100/65"
            >
              Administrative drill-down
            </p>
          </div>

          <div class="relative mb-7">
            <Icon
              icon="solar:magnifer-linear"
              class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
            />
            <input
              v-model="regionSearch"
              class="h-12 w-full rounded-lg border border-outline-variant bg-slate-50 pl-11 pr-4 text-body-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-emerald-800 dark:bg-forest-950 dark:text-emerald-50"
              type="search"
              placeholder="Find region..."
            />
          </div>

          <div v-if="isLoading" class="space-y-3">
            <div
              v-for="index in 5"
              :key="index"
              class="h-11 animate-pulse rounded-lg bg-surface-container-high dark:bg-forest-800"
            />
          </div>

          <div
            v-else-if="filteredTreeRows.length === 0"
            class="rounded-xl border border-dashed border-outline-variant bg-slate-50 px-4 py-10 text-center dark:border-emerald-800 dark:bg-forest-950"
          >
            <Icon
              icon="solar:folder-open-bold-duotone"
              class="mx-auto h-9 w-9 text-slate-400"
            />
            <p
              class="mt-3 text-body-sm font-semibold text-slate-900 dark:text-white"
            >
              Wilayah tidak ditemukan
            </p>
          </div>

          <div v-else class="max-h-[480px] space-y-2 overflow-y-auto pr-1">
            <div
              v-for="row in filteredTreeRows"
              :key="`${row.wilayah_id}-${row.depth}`"
              class="flex min-h-11 items-center gap-2 rounded-lg px-2 py-1 transition hover:bg-slate-50 dark:hover:bg-forest-800"
              :style="{ paddingLeft: `${Math.min(row.depth, 4) * 18 + 4}px` }"
            >
              <button
                v-if="hasChild(row)"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:cursor-wait disabled:opacity-60 dark:text-emerald-100/65 dark:hover:bg-forest-950"
                type="button"
                :disabled="isNodeLoading(row.wilayah_id)"
                :aria-expanded="isExpanded(row.wilayah_id)"
                @click="toggleNode(row)"
              >
                <Icon
                  :icon="
                    isNodeLoading(row.wilayah_id)
                      ? 'solar:refresh-bold-duotone'
                      : 'solar:alt-arrow-right-linear'
                  "
                  class="h-5 w-5 transition"
                  :class="{
                    'rotate-90': isExpanded(row.wilayah_id),
                    'animate-spin': isNodeLoading(row.wilayah_id),
                  }"
                />
              </button>
              <span v-else class="h-8 w-8 shrink-0" />

              <Icon
                :icon="
                  row.depth === 0
                    ? 'solar:folder-with-files-bold-duotone'
                    : 'solar:folder-linear'
                "
                class="h-5 w-5 shrink-0 text-primary"
              />

              <div class="min-w-0 flex-1">
                <p
                  class="truncate text-body-sm font-semibold text-slate-900 dark:text-white"
                >
                  {{ row.nama || "-" }}
                </p>
              </div>

              <span
                class="shrink-0 rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-500 dark:bg-forest-800 dark:text-emerald-100/70"
              >
                {{ formatNumber(row.jumlah_mahasiswa) }}
              </span>
            </div>
          </div>
        </aside>
      </section>
    </main>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: false,
});

const router = useRouter();
const authStore = useAuthStore();
const { $api } = useNuxtApp();

const THEME_KEY = "geovisit-theme-mode";
const theme = ref("light");
const isLoading = ref(false);
const errorMessage = ref("");
const chartGroupBy = ref("provinsi");
const regionSearch = ref("");

const summary = ref({
  jumlah_mahasiswa: 0,
  jumlah_user_terdaftar: 0,
  jumlah_provinsi_terjangkau: 0,
  jumlah_kabupaten_kota_terjangkau: 0,
  total_provinsi: 0,
  total_kabupaten_kota: 0,
  persentase_kabupaten_kota_terjangkau: 0,
  provinsi_teratas: null,
});

const chartByGroup = reactive({
  provinsi: {
    categories: [],
    data: [],
    rows: [],
  },
  kabupaten: {
    categories: [],
    data: [],
    rows: [],
  },
});

const rootWilayahRows = ref([]);
const childrenByParent = ref({});
const expandedNodeIds = ref(new Set());
const loadingNodeIds = ref(new Set());

const chartGroupOptions = [
  { label: "Provinsi", value: "provinsi" },
  { label: "Kab/Kota", value: "kabupaten" },
];

const userName = computed(
  () =>
    authStore.user?.nama ||
    authStore.user?.name ||
    authStore.user?.username ||
    "User",
);

const userRoleLabel = computed(() => {
  const role =
    authStore.user?.role || authStore.user?.usergroup?.kode || "user";
  return String(role).charAt(0).toUpperCase() + String(role).slice(1);
});

const userInitial = computed(() => {
  const name = userName.value.trim();
  return name ? name.charAt(0).toUpperCase() : "U";
});

const kabupatenReachCount = computed(() => {
  return Number(
    summary.value.jumlah_kabupaten_kota_terjangkau ||
      chartByGroup.kabupaten.categories.length ||
      0,
  );
});

const coveragePercent = computed(() => {
  const apiValue = Number(summary.value.persentase_kabupaten_kota_terjangkau);

  if (Number.isFinite(apiValue) && apiValue > 0) {
    return Math.min(100, Math.round(apiValue));
  }

  const totalKabupaten = Number(summary.value.total_kabupaten_kota || 0);

  if (kabupatenReachCount.value === 0 || totalKabupaten === 0) {
    return 0;
  }

  return Math.min(
    100,
    Math.round((kabupatenReachCount.value / totalKabupaten) * 100),
  );
});

const topProvinceShare = computed(() => {
  const apiValue = Number(summary.value.provinsi_teratas?.persentase);

  if (Number.isFinite(apiValue) && apiValue > 0) {
    return Math.round(apiValue);
  }

  const total = Number(summary.value.jumlah_mahasiswa || 0);
  const topValue = Number(chartByGroup.provinsi.data[0] || 0);

  if (total <= 0 || topValue <= 0) {
    return 0;
  }

  return Math.round((topValue / total) * 100);
});

const activeChartRows = computed(() =>
  buildChartRows(chartGroupBy.value).slice(0, 5),
);

const chartSubtitle = computed(() => {
  return chartGroupBy.value === "provinsi"
    ? "Top 5 provinces by student enrollment"
    : "Top 5 cities/regencies by student enrollment";
});

const visibleTreeRows = computed(() => flattenRows(rootWilayahRows.value, 0));

const filteredTreeRows = computed(() => {
  const query = regionSearch.value.trim().toLowerCase();

  if (!query) {
    return visibleTreeRows.value;
  }

  return visibleTreeRows.value.filter((row) => {
    return (
      String(row.nama || "")
        .toLowerCase()
        .includes(query) ||
      String(row.wilayah_id || "")
        .toLowerCase()
        .includes(query)
    );
  });
});

const applyTheme = (value) => {
  theme.value = value;

  if (!import.meta.client) {
    return;
  }

  document.documentElement.setAttribute("data-theme", value);
  document.documentElement.classList.toggle("dark", value === "dark");
  document.documentElement.style.colorScheme = value;
  localStorage.setItem(THEME_KEY, value);
};

const toggleTheme = () => {
  applyTheme(theme.value === "light" ? "dark" : "light");
};

const formatNumber = (value) => {
  return new Intl.NumberFormat("id-ID").format(Number(value || 0));
};

const extractErrorMessage = (error, fallback) => {
  return error?.response?.data?.message || error?.message || fallback;
};

const normalizeSummary = (payload) => ({
  jumlah_mahasiswa: Number(payload?.jumlah_mahasiswa || 0),
  jumlah_user_terdaftar: Number(payload?.jumlah_user_terdaftar || 0),
  jumlah_provinsi_terjangkau: Number(payload?.jumlah_provinsi_terjangkau || 0),
  jumlah_kabupaten_kota_terjangkau: Number(
    payload?.jumlah_kabupaten_kota_terjangkau || 0,
  ),
  total_provinsi: Number(payload?.total_provinsi || 0),
  total_kabupaten_kota: Number(payload?.total_kabupaten_kota || 0),
  persentase_kabupaten_kota_terjangkau: Number(
    payload?.persentase_kabupaten_kota_terjangkau || 0,
  ),
  provinsi_teratas: payload?.provinsi_teratas || null,
});

const normalizeChart = (payload) => {
  const categories = Array.isArray(payload?.categories)
    ? payload.categories
    : [];
  const firstSeries = Array.isArray(payload?.series) ? payload.series[0] : null;
  const data = Array.isArray(firstSeries?.data)
    ? firstSeries.data.map((value) => Number(value || 0))
    : [];
  const rows = Array.isArray(payload?.rows)
    ? payload.rows.map((row, index) => ({
        rank: Number(row?.rank || index + 1),
        name: row?.nama_wilayah || row?.nama || categories[index] || "-",
        value: Number(row?.jumlah || data[index] || 0),
        percent: Number(row?.bar_percent || 0),
        share: Number(row?.persentase || 0),
        wilayahId: row?.wilayah_id || row?.kode_wilayah || "",
      }))
    : [];

  return {
    categories,
    data,
    rows,
  };
};

const normalizeWilayahRows = (rows) => {
  if (!Array.isArray(rows)) {
    return [];
  }

  return rows.map((row) => ({
    ...row,
    wilayah_id: String(row.wilayah_id || ""),
    nama: row.nama || "",
    jumlah_mahasiswa: Number(row.jumlah_mahasiswa || 0),
    parent_wilayah_id: row.parent_wilayah_id || null,
    level: Number(row.level || 0),
    is_have_child: Number(row.is_have_child || 0),
  }));
};

const buildChartRows = (group) => {
  const chart = chartByGroup[group] || { categories: [], data: [] };
  const sourceRows =
    Array.isArray(chart.rows) && chart.rows.length > 0
      ? chart.rows
      : chart.categories.map((name, index) => ({
          name,
          value: Number(chart.data[index] || 0),
          percent: 0,
        }));
  const maxValue = Math.max(
    ...sourceRows.map((row) => Number(row.value || 0)),
    0,
  );

  return sourceRows.map((row) => {
    const value = Number(row.value || 0);
    const apiPercent = Number(row.percent || 0);

    return {
      name: row.name,
      value,
      percent:
        apiPercent > 0
          ? Math.max(4, Math.round(apiPercent))
          : maxValue > 0
            ? Math.max(4, Math.round((value / maxValue) * 100))
            : 0,
    };
  });
};

const barColor = (index) => {
  const opacity = Math.max(0.35, 1 - index * 0.14);
  return `rgba(0, 107, 38, ${opacity})`;
};

const flattenRows = (rows, depth) => {
  return rows.flatMap((row) => {
    const current = { ...row, depth };

    if (!expandedNodeIds.value.has(row.wilayah_id)) {
      return [current];
    }

    return [
      current,
      ...flattenRows(childrenByParent.value[row.wilayah_id] || [], depth + 1),
    ];
  });
};

const hasChild = (row) => Number(row?.is_have_child || 0) === 1;

const isExpanded = (wilayahId) => expandedNodeIds.value.has(wilayahId);

const isNodeLoading = (wilayahId) => loadingNodeIds.value.has(wilayahId);

const setLoadingNode = (wilayahId, value) => {
  const next = new Set(loadingNodeIds.value);

  if (value) {
    next.add(wilayahId);
  } else {
    next.delete(wilayahId);
  }

  loadingNodeIds.value = next;
};

const fetchSummary = async () => {
  const response = await $api.get("/dashboard/summary");
  summary.value = normalizeSummary(response.data?.data);
};

const fetchChart = async (groupBy) => {
  const response = await $api.get("/dashboard/chart", {
    params: {
      group_by: groupBy,
      limit: 5,
    },
  });

  const normalized = normalizeChart(response.data?.data);
  chartByGroup[groupBy].categories = normalized.categories;
  chartByGroup[groupBy].data = normalized.data;
  chartByGroup[groupBy].rows = normalized.rows;
};

const fetchWilayahChildren = async (parentId) => {
  setLoadingNode(parentId, true);

  try {
    const response = await $api.get("/dashboard/wilayah-tree", {
      params: {
        parent_id: parentId,
      },
    });

    childrenByParent.value = {
      ...childrenByParent.value,
      [parentId]: normalizeWilayahRows(response.data?.data),
    };
  } finally {
    setLoadingNode(parentId, false);
  }
};

const fetchRootWilayah = async () => {
  const response = await $api.get("/dashboard/wilayah-tree", {
    params: {
      root_level: "provinsi",
    },
  });
  const rows = normalizeWilayahRows(response.data?.data);
  rootWilayahRows.value = rows;
  childrenByParent.value = {};
  expandedNodeIds.value = new Set();

  const firstExpandable = rows.find((row) => hasChild(row));

  if (firstExpandable) {
    expandedNodeIds.value = new Set([firstExpandable.wilayah_id]);
    await fetchWilayahChildren(firstExpandable.wilayah_id);
  }
};

const toggleNode = async (row) => {
  if (!hasChild(row)) {
    return;
  }

  const next = new Set(expandedNodeIds.value);

  if (next.has(row.wilayah_id)) {
    next.delete(row.wilayah_id);
    expandedNodeIds.value = next;
    return;
  }

  next.add(row.wilayah_id);
  expandedNodeIds.value = next;

  if (!childrenByParent.value[row.wilayah_id]) {
    try {
      await fetchWilayahChildren(row.wilayah_id);
    } catch (error) {
      errorMessage.value = extractErrorMessage(
        error,
        "Gagal memuat child wilayah.",
      );
    }
  }
};

const loadDashboard = async () => {
  isLoading.value = true;
  errorMessage.value = "";

  const results = await Promise.allSettled([
    fetchSummary(),
    fetchChart("provinsi"),
    fetchChart("kabupaten"),
    fetchRootWilayah(),
  ]);

  const failed = results.find((result) => result.status === "rejected");
  if (failed) {
    errorMessage.value = extractErrorMessage(
      failed.reason,
      "Gagal memuat dashboard.",
    );
  }

  isLoading.value = false;
};

const handleLogout = () => {
  authStore.logout();
  router.push("/auth/login");
};

onMounted(() => {
  const saved = localStorage.getItem(THEME_KEY);

  if (saved === "light" || saved === "dark") {
    applyTheme(saved);
  } else {
    const prefersDark = window.matchMedia(
      "(prefers-color-scheme: dark)",
    ).matches;
    applyTheme(prefersDark ? "dark" : "light");
  }

  authStore.initAuth();

  if (!authStore.isAuthenticated) {
    router.replace("/auth/login");
    return;
  }

  loadDashboard();
});
</script>
