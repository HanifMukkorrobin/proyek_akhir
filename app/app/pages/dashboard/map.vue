<template>
  <div
    class="relative h-screen min-h-[680px] overflow-hidden bg-forest-950 font-sans text-on-surface"
  >
    <Head>
      <Title>3D Map Dashboard | GeoVisit PJJ IT</Title>
      <Link rel="stylesheet" href="/cesium/Widgets/widgets.css" />
    </Head>

    <div ref="cesiumContainer" class="absolute inset-0 z-0" />
    <div
      class="pointer-events-none absolute inset-0 z-10 bg-[radial-gradient(circle_at_50%_35%,rgba(20,184,116,0.08),transparent_34%),linear-gradient(90deg,rgba(6,20,11,0.48),rgba(6,20,11,0.12)_45%,rgba(6,20,11,0.26))]"
    />

    <header
      class="fixed left-4 right-4 top-4 z-50 flex min-h-16 items-center justify-between gap-4 rounded-2xl border border-white/15 bg-white/95 px-4 shadow-2xl shadow-emerald-950/15 backdrop-blur-md sm:left-6 sm:right-6 sm:top-6 sm:px-6"
    >
      <div class="flex min-w-0 items-center gap-3">
        <div class="flex min-w-0 items-center gap-3">
          <Icon
            icon="solar:map-point-wave-bold-duotone"
            class="h-7 w-7 shrink-0 text-primary"
          />
          <span
            class="truncate text-sm font-semibold text-emerald-900 sm:text-base"
            >GeoVisit PJJ IT</span
          >
        </div>
      </div>

      <div class="flex items-center gap-2 sm:gap-4">
        <!-- Simulasi Rute button (Dosen only) -->
        <button
          v-if="authStore.user?.role === 'dosen'"
          class="flex h-10 items-center gap-2 rounded-xl bg-primary/10 px-4 text-xs font-bold text-primary transition hover:bg-primary/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20"
          type="button"
          @click="isSimulasiOpen = !isSimulasiOpen"
        >
          <Icon icon="solar:routing-bold-duotone" class="h-4 w-4" />
          <span class="hidden sm:inline">Simulasi Rute</span>
        </button>

        <div class="hidden h-7 w-px bg-emerald-100 sm:block" />

        <button
          class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-emerald-50"
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
          class="flex h-10 w-10 items-center justify-center rounded-full bg-forest-950 text-emerald-100 shadow-lg shadow-emerald-950/20"
          type="button"
          :title="userName"
          @click="handleLogout"
        >
          {{ userInitial }}
        </button>
      </div>
    </header>

    <nav
      class="fixed bottom-5 left-4 top-24 z-40 flex w-[min(26rem,calc(100vw-2rem))] flex-col rounded-2xl border border-white/15 bg-white/95 p-5 shadow-2xl shadow-emerald-950/15 backdrop-blur-lg transition-transform duration-300 sm:left-6 sm:top-32 lg:bottom-6"
      :class="consoleOpen ? 'translate-x-0' : '-translate-x-[calc(100%+2rem)]'"
    >
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-base font-black text-emerald-950">3D Map Console</h1>
          <p class="mt-1 text-body-sm text-slate-500">
            {{ activeLevelLabel }} - {{ formatNumber(mapPoints.length) }} titik
          </p>
        </div>
        <button
          class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-emerald-50"
          type="button"
          @click="consoleOpen = false"
        >
          <Icon icon="solar:close-circle-bold-duotone" class="h-5 w-5" />
        </button>
      </div>
      <!-- 
      <div class="mt-6 grid grid-cols-2 gap-3">
        <button
          class="flex h-12 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-primary disabled:cursor-wait disabled:opacity-70"
          type="button"
          :disabled="isMapPointsLoading"
          @click="refreshMapPoints"
        >
          <Icon :icon="isMapPointsLoading ? 'solar:refresh-bold-duotone' : 'solar:refresh-linear'" class="h-5 w-5" :class="{ 'animate-spin': isMapPointsLoading }" />
          Refresh
        </button>

        <button
          class="flex h-12 items-center justify-center gap-2 rounded-xl border border-outline-variant/40 px-3 text-sm font-bold text-slate-700 transition hover:bg-emerald-50"
          type="button"
          @click="flyToIndonesia"
        >
          <Icon icon="solar:map-arrow-square-bold-duotone" class="h-5 w-5 text-primary" />
          Indonesia
        </button>
      </div>
 -->
      <div
        class="mt-6 rounded-xl border border-emerald-100 bg-surface-container-low p-4"
      >
        <p class="text-label-caps uppercase text-slate-500">Layer Aktif</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <span
            class="rounded-full bg-primary px-3 py-1 text-xs font-bold text-on-primary"
            >Wilayah</span
          >
          <span
            class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600"
            >Mahasiswa</span
          >
          <span
            class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600"
            >Arc Visitasi</span
          >
        </div>
        <p
          v-if="activeParentName"
          class="mt-3 truncate text-body-sm font-semibold text-emerald-900"
        >
          Filter: {{ activeParentName }}
        </p>
        <div v-if="navigationBreadcrumbs.length > 0" class="mt-4 space-y-3">
          <div class="flex flex-wrap gap-2">
            <button
              class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-primary transition hover:bg-emerald-50"
              type="button"
              @click="goBackLevel"
            >
              Kembali
            </button>
            <button
              v-for="(crumb, index) in navigationBreadcrumbs"
              :key="`${crumb.wilayahId}-${index}`"
              class="rounded-lg border border-emerald-100 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-emerald-50"
              type="button"
              @click="jumpToBreadcrumb(index)"
            >
              {{ crumb.name }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="selectedPoint"
        class="mt-4 flex min-h-0 flex-1 flex-col rounded-xl border border-emerald-100 bg-white p-4"
      >
        <div>
          <p class="text-label-caps uppercase text-slate-500">
            {{
              selectedPoint.type === "mahasiswa"
                ? "Selected Student"
                : "Selected Region"
            }}
          </p>
          <p class="mt-2 text-lg font-black text-emerald-950">
            {{ selectedPoint.name }}
          </p>
          <p
            v-if="selectedPoint.type === 'wilayah'"
            class="mt-1 text-body-sm text-slate-500"
          >
            {{ selectedPoint.levelLabel }} -
            {{ formatNumber(selectedPoint.count) }} mahasiswa
          </p>
          <p v-else class="mt-1 text-body-sm text-slate-500">
            {{ selectedPoint.regionName || "Wilayah belum tersedia" }}
          </p>

          <!-- <div
            v-if="selectedPoint.type === 'wilayah'"
            class="mt-4 flex flex-wrap gap-2"
          >
            <button
              v-if="selectedPoint.hasChild && selectedPoint.nextLevel"
              class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-on-primary transition hover:bg-emerald-700"
              type="button"
              @click="drillDownSelected"
            >
              Buka {{ levelLabel(selectedPoint.nextLevel) }}
            </button>
            <button
              class="rounded-lg border border-emerald-100 px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-emerald-50"
              type="button"
              @click="focusPoint(selectedPoint, { drillOnRegion: false })"
            >
              Fokus Titik
            </button>
          </div> -->

          <div
            v-if="selectedPoint.type === 'mahasiswa'"
            class="mt-4 rounded-lg bg-slate-50 p-3 text-body-sm text-slate-600"
          >
            {{ selectedPoint.address || "Alamat belum tersedia." }}
          </div>
        </div>

        <div
          v-if="selectedPoint.type === 'wilayah'"
          class="mt-4 flex min-h-0 flex-1 flex-col border-t border-emerald-100 pt-4"
        >
          <button
            class="mb-3 flex w-full items-center justify-between gap-3 text-left transition-opacity hover:opacity-70"
            type="button"
            @click="isStudentListOpen = !isStudentListOpen"
          >
            <p class="text-label-caps uppercase text-slate-500">Mahasiswa</p>
            <div class="flex items-center gap-2">
              <span
                v-if="studentPagination.total_data"
                class="text-xs font-bold text-primary"
                >{{ formatNumber(studentPagination.total_data) }}</span
              >
              <Icon
                :icon="
                  isStudentListOpen
                    ? 'solar:alt-arrow-up-linear'
                    : 'solar:alt-arrow-down-linear'
                "
                class="h-4 w-4 text-slate-400"
              />
            </div>
          </button>

          <div v-show="isStudentListOpen" class="flex min-h-0 flex-1 flex-col">
            <div
              v-if="isDetailLoading && selectedStudents.length === 0"
              class="space-y-2"
            >
              <div
                v-for="index in 3"
                :key="index"
                class="h-12 animate-pulse rounded-lg bg-slate-100"
              />
            </div>

            <div
              v-else-if="selectedStudents.length === 0"
              class="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-body-sm text-slate-500"
            >
              Data mahasiswa tidak ditemukan.
            </div>

            <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
              <button
                v-for="student in selectedStudents"
                :key="student.id"
                class="w-full rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-left transition hover:border-emerald-200 hover:bg-emerald-50"
                type="button"
                @click="focusPoint(student)"
              >
                <span class="block truncate text-sm font-bold text-slate-800">{{
                  student.name
                }}</span>
                <span class="mt-1 block truncate text-xs text-slate-500">{{
                  student.regionName || student.address
                }}</span>
              </button>
            </div>

            <button
              v-if="canLoadMoreStudents"
              class="mt-3 shrink-0 flex h-10 w-full items-center justify-center rounded-lg border border-emerald-100 text-sm font-bold text-primary transition hover:bg-emerald-50 disabled:cursor-wait disabled:opacity-60"
              type="button"
              :disabled="isDetailLoading"
              @click="loadNextStudentsPage"
            >
              {{ isDetailLoading ? "Memuat..." : "Muat berikutnya" }}
            </button>
          </div>
        </div>
      </div>

      <div
        class="mt-auto shrink-0 space-y-1 border-t border-emerald-100 pt-5 text-sm text-slate-500"
      >
        <p>Status: {{ sceneReady ? "System Online" : "Loading Cesium" }}</p>
        <p>Coordinate: {{ currentCoordinate }}</p>
      </div>
    </nav>

    <div class="fixed left-10 top-32 z-30 sm:top-36">
      <button
        class="flex size-14 shrink-0 items-center justify-center bg-white rounded-full text-primary transition hover:bg-emerald-50 sm:left-6 sm:top-32"
        type="button"
        aria-label="Toggle map console"
        @click="consoleOpen = !consoleOpen"
      >
        <Icon icon="solar:sidebar-code-bold-duotone" class="size-6" />
      </button>
    </div>

    <form
      class="fixed left-1/2 top-28 z-30 w-[min(32rem,calc(100vw-2rem))] -translate-x-1/2 sm:top-32"
      @submit.prevent="focusSearchResult"
    >
      <div
        class="flex h-14 items-center rounded-full border border-white/30 bg-white/80 px-5 shadow-xl shadow-emerald-950/10 backdrop-blur-md"
      >
        <Icon
          icon="solar:magnifer-linear"
          class="mr-3 h-6 w-6 shrink-0 text-slate-500"
        />
        <input
          v-model="searchQuery"
          class="min-w-0 flex-1 border-none bg-transparent text-body-md text-slate-800 outline-none placeholder:text-slate-500 focus:ring-0"
          type="search"
          placeholder="Cari Nama Mahasiswa..."
        />
        <Icon
          v-if="isSearching"
          icon="solar:refresh-bold-duotone"
          class="ml-3 h-5 w-5 animate-spin text-primary"
        />
      </div>

      <div
        v-if="searchQuery.trim().length >= 2"
        class="mt-2 overflow-hidden rounded-xl border border-white/30 bg-white/90 shadow-xl backdrop-blur-md"
      >
        <p
          v-if="searchError"
          class="px-4 py-3 text-sm font-semibold text-red-600"
        >
          {{ searchError }}
        </p>
        <p
          v-else-if="!isSearching && searchResults.length === 0"
          class="px-4 py-3 text-sm font-semibold text-slate-500"
        >
          Mahasiswa tidak ditemukan.
        </p>
        <button
          v-for="student in searchResults.slice(0, 5)"
          :key="student.id"
          class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left text-sm transition hover:bg-emerald-50"
          type="button"
          @click="focusPoint(student)"
        >
          <span class="min-w-0">
            <span class="block truncate font-semibold text-slate-800">{{
              student.name
            }}</span>
            <span class="block truncate text-xs text-slate-500">{{
              student.regionName || student.address
            }}</span>
          </span>
          <Icon
            icon="solar:map-point-bold-duotone"
            class="h-5 w-5 shrink-0 text-primary"
          />
        </button>
      </div>
    </form>

    <div class="fixed right-4 top-28 z-30 sm:right-7 sm:top-32">
      <div
        class="flex rounded-full border border-white/30 bg-white/80 p-1 shadow-xl shadow-emerald-950/10 backdrop-blur-md"
      >
        <NuxtLink
          class="rounded-full px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-white sm:px-5"
          to="/dashboard/chart"
        >
          Chart Mode
        </NuxtLink>
        <span
          class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-on-primary shadow-sm sm:px-5"
          >3D Map Mode</span
        >
      </div>
    </div>

    <div class="fixed bottom-6 right-4 z-30 flex flex-col gap-3 sm:right-7">
      <div class="mb-2 flex flex-col items-center gap-2">
        <div
          class="pointer-events-none rounded-full bg-white/80 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-900 shadow-sm backdrop-blur"
        >
          Kamera
        </div>
        <div
          ref="joystickArea"
          class="relative h-16 w-16 touch-none rounded-full border border-white/40 bg-white/40 shadow-xl backdrop-blur-md sm:h-20 sm:w-20"
          @mousedown="startJoystick"
          @touchstart.prevent="startJoystick"
        >
          <div
            class="absolute left-1/2 top-1/2 flex h-6 w-6 -translate-x-1/2 -translate-y-1/2 cursor-grab items-center justify-center rounded-full border border-white/50 bg-white shadow-md transition-transform duration-75 active:cursor-grabbing sm:h-8 sm:w-8"
            :style="joystickStyle"
          >
            <Icon
              icon="solar:gamepad-bold-duotone"
              class="h-4 w-4 text-emerald-700 sm:h-5 sm:w-5"
            />
          </div>
        </div>
      </div>
      <button
        class="flex h-12 w-12 items-center justify-center rounded-lg border border-white/30 bg-white/90 text-slate-900 shadow-xl shadow-emerald-950/10 backdrop-blur-md transition hover:bg-white"
        type="button"
        aria-label="Zoom in"
        @click="zoomIn"
      >
        <Icon icon="solar:add-circle-linear" class="h-6 w-6" />
      </button>
      <button
        class="flex h-12 w-12 items-center justify-center rounded-lg border border-white/30 bg-white/90 text-slate-900 shadow-xl shadow-emerald-950/10 backdrop-blur-md transition hover:bg-white"
        type="button"
        aria-label="Zoom out"
        @click="zoomOut"
      >
        <Icon icon="solar:minus-circle-linear" class="h-6 w-6" />
      </button>
    </div>

    <div
      v-if="mapError"
      class="fixed bottom-6 left-1/2 z-50 flex w-[min(36rem,calc(100vw-2rem))] -translate-x-1/2 items-start gap-3 rounded-xl border border-error/20 bg-error-container px-4 py-3 text-body-sm text-on-error-container shadow-xl"
    >
      <Icon
        icon="solar:danger-triangle-bold-duotone"
        class="h-5 w-5 shrink-0"
      />
      <span>{{ mapError }}</span>
    </div>

    <!-- Simulasi Rute Modal Panel -->
    <SimulasiModal
      :is-open="isSimulasiOpen"
      :click-coordinates="mapClickCoords"
      @close="isSimulasiOpen = false"
      @update-rute="handleSimulationRouteUpdate"
      @clear-rute="handleSimulationRouteClear"
      @set-capture-coordinates="handleSetCaptureCoordinates"
    />
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import polyline from "@mapbox/polyline";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: false,
  alias: ["/dashboard/map"],
});

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const config = useRuntimeConfig();
const { $api } = useNuxtApp();

const THEME_KEY = "geovisit-theme-mode";
const INDONESIA_CENTER = {
  lat: -2.5489,
  lon: 118.0149,
};
const VISITATION_HUB = {
  id: "pens-hub",
  name: "PENS Hub",
  lat: -7.2758,
  lon: 112.7947,
  count: 0,
};

const levelLabels = {
  provinsi: "Provinsi",
  kabupaten: "Kabupaten/Kota",
  kecamatan: "Kecamatan",
  desa: "Desa/Kelurahan",
};
const levelLengths = {
  provinsi: 6,
  kabupaten: 9,
  kecamatan: 12,
  desa: 15,
};
const levelFlyHeights = {
  provinsi: 2850000,
  kabupaten: 720000,
  kecamatan: 260000,
  desa: 90000,
};
const levelPerformance = {
  provinsi: {
    apiLimit: 80,
    renderLimit: 80,
    labelLimit: 50,
    arcLimit: 8,
  },
  kabupaten: {
    apiLimit: 140,
    renderLimit: 140,
    labelLimit: 28,
    arcLimit: 3,
  },
  kecamatan: {
    apiLimit: 120,
    renderLimit: 120,
    labelLimit: 18,
    arcLimit: 0,
  },
  desa: {
    apiLimit: 120,
    renderLimit: 120,
    labelLimit: 10,
    arcLimit: 0,
  },
};
const REGION_LABEL_VISIBILITY = {
  provinsi: {
    font: "800 14px Inter, sans-serif",
    maxDistance: 4200000,
    scaleNear: 1.12,
    scaleFar: 0.96,
    alphaNear: 1,
    alphaFar: 1,
    outlineWidth: 5,
    backgroundAlpha: 1,
    multiline: true,
  },
  kabupaten: {
    font: "800 14px Inter, sans-serif",
    maxDistance: 1800000,
    scaleNear: 0.95,
    scaleFar: 0.72,
    alphaNear: 1,
    alphaFar: 1,
    outlineWidth: 4,
    backgroundAlpha: 1,
    multiline: true,
  },
  kecamatan: {
    font: "800 14px Inter, sans-serif",
    maxDistance: 700000,
    scaleNear: 0.84,
    scaleFar: 0.62,
    alphaNear: 1,
    alphaFar: 1,
    outlineWidth: 4,
    backgroundAlpha: 1,
    multiline: true,
  },
  desa: {
    font: "800 14px Inter, sans-serif",
    maxDistance: 280000,
    scaleNear: 0.78,
    scaleFar: 0.56,
    alphaNear: 1,
    alphaFar: 1,
    outlineWidth: 4,
    backgroundAlpha: 1,
    multiline: true,
  },
};
const CAMERA_REFRESH_DEBOUNCE_MS = 650;
const SEARCH_RESULT_LIMIT = 10;
const TERRAIN_MARKER_OFFSET = {
  provinsi: 1200,
  kabupaten: 700,
  kecamatan: 220,
  desa: 80,
  mahasiswa: 35,
  hub: 180,
};
const POINT_FOCUS_HEIGHT = {
  provinsi: 1800000,
  kabupaten: 420000,
  kecamatan: 140000,
  desa: 55000,
  mahasiswa: 28000,
};
const fallbackCoordinates = [
  { key: "jawa barat", lat: -6.9175, lon: 107.6191 },
  { key: "jawa timur", lat: -7.2575, lon: 112.7521 },
  { key: "dki jakarta", lat: -6.2088, lon: 106.8456 },
  { key: "banten", lat: -6.4058, lon: 106.064 },
  { key: "jawa tengah", lat: -7.1509, lon: 110.1403 },
  { key: "bali", lat: -8.3405, lon: 115.092 },
  { key: "kalimantan timur", lat: 0.5387, lon: 116.4194 },
  { key: "sulawesi selatan", lat: -3.6688, lon: 119.9741 },
  { key: "sumatera utara", lat: 2.1154, lon: 99.5451 },
  { key: "papua", lat: -4.2699, lon: 138.0804 },
];

const cesiumContainer = ref(null);
const theme = ref("dark");
const joystickArea = ref(null);
const joystickActive = ref(false);
const joystickPos = ref({ x: 0, y: 0 });
const consoleOpen = ref(false);
const isStudentListOpen = ref(true);
const sceneReady = ref(false);
const terrainReady = ref(false);
const mapError = ref("");
const searchQuery = ref("");
const searchResults = ref([]);
const searchError = ref("");
const isSearching = ref(false);
const isMapPointsLoading = ref(false);
const isDetailLoading = ref(false);
const mapPoints = ref([]);
const selectedPoint = ref(null);
const selectedStudents = ref([]);
const studentPagination = ref({
  halaman_sekarang: 1,
  per_halaman: 8,
  total_data: 0,
  total_halaman: 1,
});
const activeLevelKey = ref("provinsi");
const nextLevelKey = ref("kabupaten");
const activeParentId = ref(null);
const activeParentName = ref("");
const activeRegionSelection = ref(null);
const navigationStack = ref([]);
const currentCoordinate = ref(
  `${INDONESIA_CENTER.lat}, ${INDONESIA_CENTER.lon}`,
);

// Simulation State Variables
const isSimulasiOpen = ref(false);
const isCaptureCoordinatesMode = ref(false);
const mapClickCoords = ref(null);
const simulationRouteData = ref(null);

let Cesium = null;
let viewer = null;
let clickHandler = null;
let cameraMoveListener = null;
let searchDebounceTimer = null;
let pointFetchTimer = null;
let pointRequestSequence = 0;
let searchRequestSequence = 0;
let currentPointSignature = "";
let mapPointCache = new Map();
let regionPointCollection = null;
let regionLabelCollection = null;
let terrainProviderPromise = null;
let terrainSampleSequence = 0;
let terrainHeightCache = new Map();
let isCameraTransitioning = false;

const userName = computed(
  () =>
    authStore.user?.nama ||
    authStore.user?.name ||
    authStore.user?.username ||
    "User",
);

const userInitial = computed(() => {
  const name = userName.value.trim();
  return name ? name.charAt(0).toUpperCase() : "U";
});

const joystickStyle = computed(() => {
  return {
    transform: `translate(calc(-50% + ${joystickPos.value.x}px), calc(-50% + ${joystickPos.value.y}px))`,
  };
});

let joystickLoopId = null;

const startJoystick = (e) => {
  joystickActive.value = true;
  updateJoystickPos(e);
  window.addEventListener("mousemove", updateJoystickPos);
  window.addEventListener("mouseup", stopJoystick);
  window.addEventListener("touchmove", updateJoystickPos, { passive: false });
  window.addEventListener("touchend", stopJoystick);

  if (!joystickLoopId) {
    joystickLoopId = requestAnimationFrame(joystickLoop);
  }
};

const updateJoystickPos = (e) => {
  if (!joystickActive.value || !joystickArea.value) return;
  if (e.cancelable) e.preventDefault();

  const rect = joystickArea.value.getBoundingClientRect();
  const centerX = rect.left + rect.width / 2;
  const centerY = rect.top + rect.height / 2;

  let clientX = e.clientX;
  let clientY = e.clientY;

  if (e.touches && e.touches.length > 0) {
    clientX = e.touches[0].clientX;
    clientY = e.touches[0].clientY;
  }

  let dx = clientX - centerX;
  let dy = clientY - centerY;

  const maxDist = rect.width / 2 - 16;
  const dist = Math.sqrt(dx * dx + dy * dy);

  if (dist > maxDist) {
    dx = (dx / dist) * maxDist;
    dy = (dy / dist) * maxDist;
  }

  joystickPos.value = { x: dx, y: dy };
};

const stopJoystick = () => {
  joystickActive.value = false;
  joystickPos.value = { x: 0, y: 0 };
  window.removeEventListener("mousemove", updateJoystickPos);
  window.removeEventListener("mouseup", stopJoystick);
  window.removeEventListener("touchmove", updateJoystickPos);
  window.removeEventListener("touchend", stopJoystick);

  if (joystickLoopId) {
    cancelAnimationFrame(joystickLoopId);
    joystickLoopId = null;
  }
};

const joystickLoop = () => {
  if (!joystickActive.value || !viewer || !Cesium) {
    joystickLoopId = null;
    return;
  }

  const maxDist = 32;
  const normX = joystickPos.value.x / maxDist;
  const normY = joystickPos.value.y / maxDist;

  if (Math.abs(normX) > 0.05 || Math.abs(normY) > 0.05) {
    const camera = viewer.camera;
    const center = new Cesium.Cartesian2(
      viewer.canvas.clientWidth / 2,
      viewer.canvas.clientHeight / 2,
    );
    const target = viewer.scene.camera.pickEllipsoid(
      center,
      viewer.scene.globe.ellipsoid,
    );

    if (target) {
      const transform = Cesium.Transforms.eastNorthUpToFixedFrame(target);
      camera.lookAtTransform(transform);

      if (Math.abs(normX) > 0.05) {
        camera.rotateRight(normX * 0.03);
      }
      if (Math.abs(normY) > 0.05) {
        camera.rotateUp(-normY * 0.03);
      }

      camera.lookAtTransform(Cesium.Matrix4.IDENTITY);
      requestSceneRender();
    } else {
      if (Math.abs(normX) > 0.05) {
        camera.lookRight(normX * 0.03);
      }
      if (Math.abs(normY) > 0.05) {
        camera.lookUp(-normY * 0.03);
      }
      requestSceneRender();
    }
  }

  joystickLoopId = requestAnimationFrame(joystickLoop);
};

const activeLevelLabel = computed(() => levelLabel(activeLevelKey.value));

const navigationBreadcrumbs = computed(() => navigationStack.value);

const canLoadMoreStudents = computed(() => {
  const currentPage = Number(studentPagination.value.halaman_sekarang || 1);
  const totalPage = Number(studentPagination.value.total_halaman || 1);
  return selectedPoint.value?.type === "wilayah" && currentPage < totalPage;
});

watch(searchQuery, (value) => {
  if (!import.meta.client) {
    return;
  }

  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer);
  }

  const query = value.trim();
  searchError.value = "";

  if (query.length < 2) {
    searchResults.value = [];
    addMapEntities();
    requestSceneRender();
    return;
  }

  searchDebounceTimer = window.setTimeout(() => {
    fetchStudentSearch(query);
  }, 350);
});

const levelLabel = (levelKey) => levelLabels[levelKey] || "Wilayah";

const createEmptyStudentPagination = () => ({
  halaman_sekarang: 1,
  per_halaman: 8,
  total_data: 0,
  total_halaman: 1,
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

const formatNumber = (value) =>
  new Intl.NumberFormat("id-ID").format(Number(value || 0));

const unwrapData = (response) => response?.data?.data ?? response?.data ?? {};

const normalizeCoordinate = (value) => {
  const numberValue = Number(value);
  return Number.isFinite(numberValue) ? numberValue : null;
};

const normalizeRegionPoint = (row, index) => {
  const name = row?.nama || `Wilayah ${index + 1}`;
  const fallback = fallbackCoordinateFor(name, index);
  const latitude = normalizeCoordinate(row?.latitude);
  const longitude = normalizeCoordinate(row?.longitude);
  const hasCoordinate = latitude !== null && longitude !== null;

  return {
    id: String(row?.wilayah_id || `fallback-${index}`),
    type: "wilayah",
    wilayahId: String(row?.wilayah_id || `fallback-${index}`),
    name,
    lat: hasCoordinate ? latitude : fallback.lat,
    lon: hasCoordinate ? longitude : fallback.lon,
    count: Number(row?.jumlah_mahasiswa || 0),
    levelKey: row?.level_key || activeLevelKey.value,
    levelLabel:
      row?.level_label || levelLabel(row?.level_key || activeLevelKey.value),
    hasChild: Boolean(row?.has_child),
    nextLevel: row?.next_level || null,
    coordinateSource:
      row?.coordinate_source || (hasCoordinate ? "wilayah" : "estimated"),
    isEstimated: !hasCoordinate,
  };
};

const normalizeStudentPoint = (row, index) => {
  const fallback = fallbackCoordinateFor(
    row?.wilayah?.nama || row?.nama,
    index,
  );
  const latitude = normalizeCoordinate(row?.latitude);
  const longitude = normalizeCoordinate(row?.longitude);
  const wilayahLatitude = normalizeCoordinate(row?.wilayah?.latitude);
  const wilayahLongitude = normalizeCoordinate(row?.wilayah?.longitude);
  const lat = latitude ?? wilayahLatitude ?? fallback.lat;
  const lon = longitude ?? wilayahLongitude ?? fallback.lon;

  return {
    id: `student-${row?.mahasiswa_id || index}`,
    type: "mahasiswa",
    mahasiswaId: row?.mahasiswa_id || "",
    name: row?.nama || `Mahasiswa ${index + 1}`,
    address: row?.alamat || "",
    wilayahId: row?.wilayah_id || row?.wilayah?.wilayah_id || "",
    regionName: row?.wilayah?.nama || "",
    lat,
    lon,
    count: 1,
    coordinateSource:
      row?.coordinate_source ||
      (latitude !== null && longitude !== null ? "mahasiswa" : "wilayah"),
  };
};

const fallbackCoordinateFor = (name, index) => {
  const normalized = String(name || "").toLowerCase();
  const match = fallbackCoordinates.find((item) =>
    normalized.includes(item.key),
  );

  if (match) {
    return match;
  }

  const angle = (index / 12) * Math.PI * 2;
  return {
    lat: INDONESIA_CENTER.lat + Math.sin(angle) * 5,
    lon: INDONESIA_CENTER.lon + Math.cos(angle) * 12,
  };
};

const fetchMapPoints = async (
  levelKey = activeLevelKey.value,
  options = {},
) => {
  const level = levelLabels[levelKey] ? levelKey : "provinsi";
  const performance = getLevelPerformance(level);
  const parentId = getCompatibleParentId(
    level,
    options.parentId ?? activeParentId.value,
  );

  if (level !== "provinsi" && !parentId) {
    return;
  }

  const useBounds = Boolean(options.useBounds);
  const bounds = useBounds ? getVisibleBoundsParams() : {};
  const params = {
    level,
    limit: performance.apiLimit,
    ...bounds,
  };

  if (parentId) {
    params.parent_id = parentId;
  }

  const signature = buildPointSignature(level, parentId, bounds);
  if (!options.force && signature === currentPointSignature) {
    return;
  }

  if (!options.force && mapPointCache.has(signature)) {
    applyMapPointPayload(
      mapPointCache.get(signature),
      level,
      parentId,
      options.parentName,
      signature,
    );
    return;
  }

  pointRequestSequence += 1;
  const requestId = pointRequestSequence;
  isMapPointsLoading.value = true;

  try {
    const response = await $api.get("/dashboard/map/wilayah-points", {
      params,
    });
    const payload = unwrapData(response);
    const rows = Array.isArray(payload.points) ? payload.points : [];

    if (requestId !== pointRequestSequence) {
      return;
    }

    mapPointCache.set(signature, payload);
    applyMapPointPayload(
      payload,
      level,
      parentId,
      options.parentName,
      signature,
    );
  } catch (error) {
    if (requestId !== pointRequestSequence) {
      return;
    }

    mapError.value =
      error?.response?.data?.message ||
      error?.message ||
      "Gagal memuat titik wilayah untuk 3D map.";

    if (mapPoints.value.length === 0) {
      mapPoints.value = fallbackCoordinates.map((item, index) => ({
        id: `fallback-${index}`,
        type: "wilayah",
        wilayahId: `fallback-${index}`,
        name: item.key.replace(/\b\w/g, (character) => character.toUpperCase()),
        lat: item.lat,
        lon: item.lon,
        count: 0,
        levelKey: "provinsi",
        levelLabel: levelLabel("provinsi"),
        hasChild: false,
        nextLevel: null,
        coordinateSource: "fallback",
        isEstimated: true,
      }));
      activeLevelKey.value = "provinsi";
      nextLevelKey.value = "kabupaten";
      activeParentId.value = null;
      activeParentName.value = "";
      currentPointSignature = signature;
      addMapEntities();
      requestSceneRender();
    }
  } finally {
    if (requestId === pointRequestSequence) {
      isMapPointsLoading.value = false;
    }
  }
};

const fetchStudentsByRegion = async (point, page = 1) => {
  if (!point?.wilayahId || point.wilayahId.startsWith("fallback-")) {
    selectedStudents.value = [];
    return;
  }

  isDetailLoading.value = true;

  try {
    const response = await $api.get(
      `/dashboard/map/wilayah/${point.wilayahId}/mahasiswa`,
      {
        params: {
          page: studentPagination.value.halaman_sekarang,
          per_page: studentPagination.value.per_halaman,
          exclude_invalid: authStore.user?.role !== "admin",
        },
      },
    );
    const payload = unwrapData(response);
    const rows = Array.isArray(payload.mahasiswa) ? payload.mahasiswa : [];
    const students = rows
      .map(normalizeStudentPoint)
      .filter(
        (student) =>
          Number.isFinite(student.lat) && Number.isFinite(student.lon),
      );

    selectedStudents.value =
      page > 1 ? [...selectedStudents.value, ...students] : students;
    studentPagination.value = payload.pagination || {
      halaman_sekarang: page,
      per_halaman: 8,
      total_data: students.length,
      total_halaman: page,
    };
  } catch (error) {
    mapError.value =
      error?.response?.data?.message ||
      error?.message ||
      "Gagal memuat mahasiswa pada wilayah.";
    selectedStudents.value = page > 1 ? selectedStudents.value : [];
  } finally {
    isDetailLoading.value = false;
  }
};

const fetchStudentSearch = async (query) => {
  searchRequestSequence += 1;
  const requestId = searchRequestSequence;
  isSearching.value = true;
  searchError.value = "";

  try {
    const response = await $api.get("/dashboard/map/mahasiswa-search", {
      params: {
        q: query,
        limit: SEARCH_RESULT_LIMIT,
        wilayah_id: activeParentId.value,
        exclude_invalid: authStore.user?.role !== "admin",
      },
    });
    const payload = unwrapData(response);
    const rows = Array.isArray(payload.results) ? payload.results : [];

    if (requestId !== searchRequestSequence) {
      return;
    }

    searchResults.value = rows
      .map(normalizeStudentPoint)
      .filter(
        (point) => Number.isFinite(point.lat) && Number.isFinite(point.lon),
      );
    addMapEntities();
    void syncVisibleTerrainHeights();
    requestSceneRender();
  } catch (error) {
    if (requestId !== searchRequestSequence) {
      return;
    }

    searchError.value =
      error?.response?.data?.message ||
      error?.message ||
      "Gagal mencari mahasiswa.";
    searchResults.value = [];
    addMapEntities();
    requestSceneRender();
  } finally {
    if (requestId === searchRequestSequence) {
      isSearching.value = false;
    }
  }
};

const initializeCesium = async () => {
  if (!import.meta.client || !cesiumContainer.value) {
    return;
  }

  Cesium = await loadCesiumGlobal();
  Cesium.Ion.defaultAccessToken = config.public.cesiumIonToken;

  viewer = new Cesium.Viewer(cesiumContainer.value, {
    animation: false,
    baseLayerPicker: false,
    fullscreenButton: false,
    geocoder: false,
    homeButton: false,
    infoBox: false,
    navigationHelpButton: false,
    sceneModePicker: false,
    selectionIndicator: false,
    timeline: false,
    shouldAnimate: false,
    requestRenderMode: true,
    maximumRenderTimeChange: Infinity,
    useBrowserRecommendedResolution: true,
    contextOptions: {
      webgl: {
        antialias: false,
        preserveDrawingBuffer: false,
      },
    },
  });

  viewer.resolutionScale = window.devicePixelRatio > 1 ? 0.82 : 1;
  if ("msaaSamples" in viewer.scene) {
    viewer.scene.msaaSamples = 1;
  }
  viewer.scene.globe.enableLighting = false;
  viewer.scene.globe.depthTestAgainstTerrain = false;
  viewer.scene.fog.enabled = false;
  viewer.scene.highDynamicRange = false;

  if (viewer.scene.postProcessStages?.bloom) {
    viewer.scene.postProcessStages.bloom.enabled = false;
  }

  viewer.scene.screenSpaceCameraController.maximumZoomDistance = 4500000;
  viewer.scene.screenSpaceCameraController.minimumZoomDistance = 100;
  viewer.scene.preUpdate.addEventListener(enforceCameraBounds);

  initializePrimitiveCollections();
  installClickHandler();
  installCameraTracker();
  addMapEntities();
  void loadTerrainProvider();
  flyToIndonesia(0);
  sceneReady.value = true;
};

const loadCesiumGlobal = () => {
  if (window.Cesium) {
    return Promise.resolve(window.Cesium);
  }

  window.CESIUM_BASE_URL = "/cesium";

  return new Promise((resolve, reject) => {
    const existingScript = document.querySelector(
      'script[data-cesium-loader="true"]',
    );

    if (existingScript) {
      existingScript.addEventListener("load", () => resolve(window.Cesium), {
        once: true,
      });
      existingScript.addEventListener(
        "error",
        () => reject(new Error("Gagal memuat Cesium.js.")),
        { once: true },
      );
      return;
    }

    const script = document.createElement("script");
    script.src = "/cesium/Cesium.js";
    script.async = true;
    script.dataset.cesiumLoader = "true";
    script.onload = () => {
      if (window.Cesium) {
        resolve(window.Cesium);
      } else {
        reject(
          new Error("Cesium global tidak tersedia setelah script dimuat."),
        );
      }
    };
    script.onerror = () => reject(new Error("Gagal memuat /cesium/Cesium.js."));
    document.head.appendChild(script);
  });
};

const installClickHandler = () => {
  if (!viewer || !Cesium) {
    return;
  }

  clickHandler = new Cesium.ScreenSpaceEventHandler(viewer.scene.canvas);
  clickHandler.setInputAction((movement) => {
    // 1. Tangkap klik koordinat jika mode simulasi titik awal aktif
    if (isCaptureCoordinatesMode.value) {
      const cartesian = viewer.camera.pickEllipsoid(movement.position, viewer.scene.globe.ellipsoid);
      if (cartesian) {
        const cartographic = Cesium.Cartographic.fromCartesian(cartesian);
        const latitude = Cesium.Math.toDegrees(cartographic.latitude);
        const longitude = Cesium.Math.toDegrees(cartographic.longitude);
        
        mapClickCoords.value = { latitude, longitude };
        addSimulationClickFeedback(longitude, latitude);
      }
      return;
    }

    const picked = viewer.scene.pick(movement.position);
    const pointId = resolvePickedPointId(picked);

    if (!pointId) {
      return;
    }

    const point = findPointById(pointId);
    if (!point) {
      return;
    }

    if (point.type === "mahasiswa") {
      focusPoint(point, { keepSearchQuery: true });
      return;
    }

    if (!point.hasChild || !point.nextLevel) {
      focusPoint(point, { drillOnRegion: false });
      return;
    }

    void handlePointSelection(point, { drillOnRegion: true });
  }, Cesium.ScreenSpaceEventType.LEFT_CLICK);
};

const installCameraTracker = () => {
  if (!viewer || !Cesium) {
    return;
  }

  cameraMoveListener = () => {
    if (isCameraTransitioning) {
      return;
    }

    updateCurrentCoordinate();
    scheduleVisiblePointRefresh();
  };

  viewer.camera.moveEnd.addEventListener(cameraMoveListener);
};

const enforceCameraBounds = () => {
  if (!viewer || !Cesium || isCameraTransitioning) return;

  const camera = viewer.camera;
  const position = camera.positionCartographic;

  const minLon = Cesium.Math.toRadians(94.0);
  const maxLon = Cesium.Math.toRadians(142.0);
  const minLat = Cesium.Math.toRadians(-12.0);
  const maxLat = Cesium.Math.toRadians(7.0);

  let clampedLon = position.longitude;
  let clampedLat = position.latitude;
  let isOut = false;

  if (clampedLon < minLon) {
    clampedLon = minLon;
    isOut = true;
  } else if (clampedLon > maxLon) {
    clampedLon = maxLon;
    isOut = true;
  }

  if (clampedLat < minLat) {
    clampedLat = minLat;
    isOut = true;
  } else if (clampedLat > maxLat) {
    clampedLat = maxLat;
    isOut = true;
  }

  if (isOut) {
    camera.setView({
      destination: Cesium.Cartesian3.fromRadians(
        clampedLon,
        clampedLat,
        position.height,
      ),
      orientation: {
        heading: camera.heading,
        pitch: camera.pitch,
        roll: camera.roll,
      },
    });
  }
};

const updateCurrentCoordinate = () => {
  if (!viewer || !Cesium) {
    return;
  }

  const cartographic = Cesium.Cartographic.fromCartesian(
    viewer.camera.positionWC,
  );
  const latitude = Cesium.Math.toDegrees(cartographic.latitude);
  const longitude = Cesium.Math.toDegrees(cartographic.longitude);
  currentCoordinate.value = `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
};

const scheduleVisiblePointRefresh = () => {
  if (!viewer || !Cesium) {
    return;
  }

  if (pointFetchTimer) {
    clearTimeout(pointFetchTimer);
  }

  pointFetchTimer = window.setTimeout(() => {
    void syncVisibleTerrainHeights();
    requestSceneRender();
  }, CAMERA_REFRESH_DEBOUNCE_MS);
};

const addMapEntities = () => {
  if (!viewer || !Cesium) {
    return;
  }

  viewer.entities.suspendEvents();

  try {
    viewer.entities.removeAll();
    clearPrimitiveCollections();
    
    // Render rute simulasi jika panel simulasi sedang aktif
    if (isSimulasiOpen.value && simulationRouteData.value) {
      drawSimulationRouteOnMap();
    } else {
      addHubEntity();
      addRegionEntities();
      addSearchEntities();
    }
  } finally {
    viewer.entities.resumeEvents();
    requestSceneRender();
  }
};

const addHubEntity = () => {
  viewer.entities.add({
    id: VISITATION_HUB.id,
    name: VISITATION_HUB.name,
    position: Cesium.Cartesian3.fromDegrees(
      VISITATION_HUB.lon,
      VISITATION_HUB.lat,
      getHubHeight(),
    ),
    point: {
      pixelSize: 18,
      color: Cesium.Color.fromCssColorString("#93f99b"),
      outlineColor: Cesium.Color.WHITE.withAlpha(0.9),
      outlineWidth: 3,
    },
    label: {
      text: VISITATION_HUB.name,
      font: "700 13px Inter, sans-serif",
      fillColor: Cesium.Color.WHITE,
      outlineColor: Cesium.Color.fromCssColorString("#06140b"),
      outlineWidth: 4,
      style: Cesium.LabelStyle.FILL_AND_OUTLINE,
      pixelOffset: new Cesium.Cartesian2(0, -34),
    },
  });
};

const getRegionLabelVisibility = (levelKey) =>
  REGION_LABEL_VISIBILITY[levelKey] || REGION_LABEL_VISIBILITY.provinsi;

const buildRegionLabelText = (point) => {
  const visibility = getRegionLabelVisibility(point.levelKey);
  return visibility.multiline
    ? `${point.name}\nJumlah Mahasiswa: ${formatNumber(point.count)}`
    : point.name;
};

const addRegionEntities = () => {
  if (!regionPointCollection || !regionLabelCollection) {
    return;
  }

  const performance = getLevelPerformance(activeLevelKey.value);
  const visiblePoints = mapPoints.value.slice(0, performance.renderLimit);
  const topArcPoints = visiblePoints.slice(0, performance.arcLimit);
  const maxCount = Math.max(...visiblePoints.map((point) => point.count), 1);
  const labelVisibility = getRegionLabelVisibility(activeLevelKey.value);

  for (const [index, point] of visiblePoints.entries()) {
    const size = Math.max(9, Math.min(30, 9 + (point.count / maxCount) * 21));
    const color = Cesium.Color.fromCssColorString(
      index < 8 ? "#22c55e" : "#78dc82",
    ).withAlpha(0.94);
    const position = buildPointCartesian(point);

    regionPointCollection.add({
      id: point.id,
      position,
      pixelSize: size,
      color,
      outlineColor: Cesium.Color.WHITE.withAlpha(0.72),
      outlineWidth: 2,
      disableDepthTestDistance: Number.POSITIVE_INFINITY,
    });

    if (index < performance.labelLimit) {
      regionLabelCollection.add({
        id: point.id,
        position,
        text: buildRegionLabelText(point),
        font: labelVisibility.font,
        fillColor: Cesium.Color.WHITE,
        outlineColor: Cesium.Color.fromCssColorString("#06140b"),
        outlineWidth: labelVisibility.outlineWidth || 4,
        style: Cesium.LabelStyle.FILL_AND_OUTLINE,
        pixelOffset: new Cesium.Cartesian2(0, -34),
        showBackground: true,
        backgroundColor: Cesium.Color.fromCssColorString("#06140b").withAlpha(
          labelVisibility.backgroundAlpha || 0.48,
        ),
        backgroundPadding: new Cesium.Cartesian2(8, 5),
        disableDepthTestDistance: Number.POSITIVE_INFINITY,
        distanceDisplayCondition: new Cesium.DistanceDisplayCondition(
          0,
          labelVisibility.maxDistance,
        ),
        scaleByDistance: new Cesium.NearFarScalar(
          15000,
          labelVisibility.scaleNear,
          labelVisibility.maxDistance,
          labelVisibility.scaleFar,
        ),
        translucencyByDistance: new Cesium.NearFarScalar(
          15000,
          labelVisibility.alphaNear,
          labelVisibility.maxDistance,
          labelVisibility.alphaFar,
        ),
      });
    }
  }

  for (const [index, point] of topArcPoints.entries()) {
    viewer.entities.add({
      id: `arc-${point.id}`,
      polyline: {
        positions: createArcPositions(VISITATION_HUB, point),
        width: index < 8 ? 4 : 2,
        material: Cesium.Color.fromCssColorString("#22c55e").withAlpha(
          index < 8 ? 0.72 : 0.34,
        ),
      },
    });
  }
};

const addSearchEntities = () => {
  for (const [index, point] of searchResults.value
    .slice(0, SEARCH_RESULT_LIMIT)
    .entries()) {
    viewer.entities.add({
      id: `search-${point.id}`,
      name: point.name,
      position: buildPointCartesian(point),
      properties: {
        pointId: point.id,
      },
      billboard: {
        image: createStudentMarkerDataUrl(),
        width: 34,
        height: 34,
        verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
      },
      label: {
        text: point.name,
        font: "700 12px Inter, sans-serif",
        fillColor: Cesium.Color.WHITE,
        outlineColor: Cesium.Color.fromCssColorString("#78350f"),
        outlineWidth: 4,
        style: Cesium.LabelStyle.FILL_AND_OUTLINE,
        pixelOffset: new Cesium.Cartesian2(0, -46),
        showBackground: true,
        backgroundColor:
          Cesium.Color.fromCssColorString("#92400e").withAlpha(0.58),
        backgroundPadding: new Cesium.Cartesian2(8, 5),
      },
    });
  }
};

const studentMarkerDataUrl = ref("");

const createStudentMarkerDataUrl = () => {
  if (studentMarkerDataUrl.value) {
    return studentMarkerDataUrl.value;
  }

  studentMarkerDataUrl.value =
    "data:image/svg+xml;charset=UTF-8," +
    encodeURIComponent(`
    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
      <circle cx="21" cy="21" r="15" fill="#f59e0b" stroke="#ffffff" stroke-width="4"/>
      <circle cx="21" cy="17" r="4.5" fill="#78350f"/>
      <path d="M13.5 29c1.5-5 13.5-5 15 0" fill="none" stroke="#78350f" stroke-width="3" stroke-linecap="round"/>
    </svg>
  `);

  return studentMarkerDataUrl.value;
};

const createArcPositions = (from, to) => {
  const positions = [];
  const steps = 28;
  const distanceFactor = Math.max(
    Math.abs(from.lon - to.lon),
    Math.abs(from.lat - to.lat),
  );
  const arcHeight = Math.min(950000, Math.max(260000, distanceFactor * 52000));
  const startHeight =
    from.id === VISITATION_HUB.id ? getHubHeight() : getPointHeight(from);
  const endHeight = getPointHeight(to);

  for (let index = 0; index <= steps; index += 1) {
    const amount = index / steps;
    const lon = from.lon + (to.lon - from.lon) * amount;
    const lat = from.lat + (to.lat - from.lat) * amount;
    const baseHeight = startHeight + (endHeight - startHeight) * amount;
    const height = baseHeight + Math.sin(amount * Math.PI) * arcHeight;
    positions.push(Cesium.Cartesian3.fromDegrees(lon, lat, height));
  }

  return positions;
};

const findPointById = (pointId) => {
  return (
    searchResults.value.find((point) => point.id === pointId) ||
    mapPoints.value.find((point) => point.id === pointId)
  );
};

const handlePointSelection = async (point, options = {}) => {
  const drillOnRegion = options.drillOnRegion !== false;

  if (
    point?.type === "wilayah" &&
    drillOnRegion &&
    point.hasChild &&
    point.nextLevel
  ) {
    await drillIntoPoint(point, {
      focusCamera: options.focusCamera !== false,
    });
    return;
  }

  selectedPoint.value = point;
  currentCoordinate.value = `${point.lat.toFixed(4)}, ${point.lon.toFixed(4)}`;

  if (point.type === "wilayah") {
    activeRegionSelection.value = point;
    isStudentListOpen.value = true;
    await fetchStudentsByRegion(point);
  } else {
    selectedStudents.value = [point];
    studentPagination.value = {
      halaman_sekarang: 1,
      per_halaman: 1,
      total_data: 1,
      total_halaman: 1,
    };
  }

  requestSceneRender();
};

const loadNextStudentsPage = () => {
  if (!selectedPoint.value || selectedPoint.value.type !== "wilayah") {
    return;
  }

  const nextPage = Number(studentPagination.value.halaman_sekarang || 1) + 1;
  fetchStudentsByRegion(selectedPoint.value, nextPage);
};

const drillIntoPoint = async (point, options = {}) => {
  if (
    !point ||
    point.type !== "wilayah" ||
    !point.hasChild ||
    !point.nextLevel
  ) {
    return false;
  }

  const keepCount = getNavigationKeepCount(point.levelKey);
  navigationStack.value = [...navigationStack.value.slice(0, keepCount), point];
  selectedPoint.value = point;
  activeRegionSelection.value = point;
  activeParentId.value = point.wilayahId;
  activeParentName.value = point.name;
  selectedStudents.value = [];
  studentPagination.value = createEmptyStudentPagination();
  isStudentListOpen.value = true;
  void fetchStudentsByRegion(point);

  await fetchMapPoints(point.nextLevel, {
    parentId: point.wilayahId,
    parentName: point.name,
    force: true,
    useBounds: false,
  });

  if (options.focusCamera !== false) {
    flyCameraToPoint(point, {
      heightKey: point.nextLevel,
    });
  }

  return true;
};

const goBackLevel = async () => {
  if (navigationStack.value.length === 0) {
    flyToIndonesia();
    return;
  }

  const nextStack = navigationStack.value.slice(0, -1);
  navigationStack.value = nextStack;

  if (nextStack.length === 0) {
    flyToIndonesia(0.75);
    return;
  }

  const parent = nextStack[nextStack.length - 1];
  selectedPoint.value = parent;
  activeRegionSelection.value = parent;
  selectedStudents.value = [];
  studentPagination.value = createEmptyStudentPagination();
  isStudentListOpen.value = true;
  void fetchStudentsByRegion(parent);
  activeParentId.value = parent.wilayahId;
  activeParentName.value = parent.name;

  await fetchMapPoints(parent.nextLevel, {
    parentId: parent.wilayahId,
    parentName: parent.name,
    force: true,
    useBounds: false,
  });

  flyCameraToPoint(parent, {
    heightKey: parent.nextLevel || parent.levelKey,
  });
};

const jumpToBreadcrumb = async (index) => {
  if (index < 0 || index >= navigationStack.value.length) {
    return;
  }

  const nextStack = navigationStack.value.slice(0, index + 1);
  const target = nextStack[nextStack.length - 1];

  navigationStack.value = nextStack;
  selectedPoint.value = target;
  activeRegionSelection.value = target;
  selectedStudents.value = [];
  studentPagination.value = createEmptyStudentPagination();
  isStudentListOpen.value = true;
  void fetchStudentsByRegion(target);
  activeParentId.value = target.wilayahId;
  activeParentName.value = target.name;

  await fetchMapPoints(target.nextLevel, {
    parentId: target.wilayahId,
    parentName: target.name,
    force: true,
    useBounds: false,
  });

  flyCameraToPoint(target, {
    heightKey: target.nextLevel || target.levelKey,
  });
};

const drillDownSelected = async () => {
  const point = selectedPoint.value;
  if (!point || point.type !== "wilayah" || !point.nextLevel) {
    return;
  }

  await drillIntoPoint(point, {
    focusCamera: true,
  });
};

const focusPoint = (point, options = {}) => {
  if (!viewer || !Cesium || !point) {
    return;
  }

  void handlePointSelection(point, {
    drillOnRegion: options.drillOnRegion === true,
  });
  consoleOpen.value = false;

  if (point.type === "mahasiswa" && !options.keepSearchQuery) {
    searchQuery.value = point.name;
  }

  flyCameraToPoint(point, {
    height: options.height,
    heightKey: options.heightKey,
  });
};

const resolvePointFocusHeight = (point, options = {}) => {
  const requestedHeight = Number(options.height);
  if (Number.isFinite(requestedHeight) && requestedHeight > 0) {
    return requestedHeight;
  }

  const fallbackKey = point.type === "mahasiswa" ? "mahasiswa" : point.levelKey;
  const heightKey = options.heightKey || fallbackKey;
  return POINT_FOCUS_HEIGHT[heightKey] || POINT_FOCUS_HEIGHT.kecamatan;
};

const flyCameraToPoint = (point, options = {}) => {
  if (!viewer || !Cesium || !point) {
    return;
  }

  const focusHeight = resolvePointFocusHeight(point, options);
  const destinationHeight = Math.max(
    getPointTerrainHeight(point) + focusHeight,
    focusHeight,
  );

  viewer.camera.cancelFlight();
  isCameraTransitioning = true;

  viewer.camera.flyTo({
    destination: Cesium.Cartesian3.fromDegrees(
      point.lon,
      point.lat,
      destinationHeight,
    ),
    orientation: {
      heading: Cesium.Math.toRadians(0),
      pitch: Cesium.Math.toRadians(-89.5),
      roll: 0,
    },
    duration: 0.85,
    complete: () => {
      isCameraTransitioning = false;
      updateCurrentCoordinate();
      requestSceneRender();
    },
    cancel: () => {
      isCameraTransitioning = false;
      requestSceneRender();
    },
  });
};

const focusSearchResult = () => {
  const point = searchResults.value[0];

  if (point) {
    focusPoint(point);
  }
};

const refreshMapPoints = () => {
  fetchMapPoints(activeLevelKey.value, {
    parentId: activeParentId.value,
    parentName: activeParentName.value,
    force: true,
    useBounds: false,
  });
};

// =========================================================================
// Simulation Helpers & Event Handlers
// =========================================================================
const handleSimulationRouteUpdate = ({ detail, peserta, hasil_osrm_raw }) => {
  simulationRouteData.value = { detail, peserta, hasil_osrm_raw };
  addMapEntities();
};

const handleSimulationRouteClear = () => {
  simulationRouteData.value = null;
  const existingFeedback = viewer?.entities?.getById('sim-click-feedback');
  if (existingFeedback && viewer) {
    viewer.entities.remove(existingFeedback);
  }
  addMapEntities();
};

const handleSetCaptureCoordinates = (active) => {
  isCaptureCoordinatesMode.value = active;
};

// Add visual feedback marker when Dosen clicks map to select start point
const addSimulationClickFeedback = (lon, lat) => {
  if (!viewer || !Cesium) return;
  const existing = viewer.entities.getById('sim-click-feedback');
  if (existing) {
    viewer.entities.remove(existing);
  }

  viewer.entities.add({
    id: 'sim-click-feedback',
    name: 'Lokasi Titik Awal Terpilih',
    position: Cesium.Cartesian3.fromDegrees(lon, lat, 100),
    billboard: {
      image: createStartMarkerSvg(),
      width: 38,
      height: 38,
      verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
      disableDepthTestDistance: Number.POSITIVE_INFINITY
    },
    label: {
      text: 'Titik Awal Terpilih',
      font: '700 11px Inter, sans-serif',
      fillColor: Cesium.Color.WHITE,
      outlineColor: Cesium.Color.fromCssColorString('#1e3a8a'),
      outlineWidth: 3,
      style: Cesium.LabelStyle.FILL_AND_OUTLINE,
      pixelOffset: new Cesium.Cartesian2(0, -42),
      showBackground: true,
      backgroundColor: Cesium.Color.fromCssColorString('#1e40af').withAlpha(0.75),
      backgroundPadding: new Cesium.Cartesian2(6, 4)
    }
  });
  viewer.scene.requestRender();
};

// SVG Markers
const startMarkerSvgUrl = ref('');
const createStartMarkerSvg = () => {
  if (startMarkerSvgUrl.value) return startMarkerSvgUrl.value;
  startMarkerSvgUrl.value = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
      <circle cx="21" cy="21" r="15" fill="#2563eb" stroke="#ffffff" stroke-width="4"/>
      <path d="M21 12l7 7h-4v9h-6v-9h-4z" fill="#ffffff"/>
    </svg>
  `);
  return startMarkerSvgUrl.value;
};

const createSimulationStudentMarkerSvg = (number) => {
  return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
      <circle cx="21" cy="21" r="15" fill="#f59e0b" stroke="#ffffff" stroke-width="4"/>
      <text x="21" y="26" font-family="Inter, sans-serif" font-weight="900" font-size="15px" fill="#ffffff" text-anchor="middle">${number}</text>
    </svg>
  `);
};

// Polyline Decoder using @mapbox/polyline
const decodePolyline = (encoded) => {
  if (!encoded) return [];
  try {
    const decoded = polyline.decode(encoded);
    return decoded.map(coord => ({
      latitude: coord[0],
      longitude: coord[1]
    }));
  } catch (error) {
    console.error("Gagal men-decode polyline:", error);
    return [];
  }
};

// Render simulation route polyline and stop billboards in main Cesium
const drawSimulationRouteOnMap = () => {
  if (!viewer || !Cesium || !simulationRouteData.value) return;

  const { detail, peserta } = simulationRouteData.value;
  const positionsToFit = [];

  // 1. Render Titik Awal
  const startWp = detail.find(d => d.tipe_titik === 'titik_awal');
  if (startWp) {
    const lat = Number(startWp.latitude);
    const lon = Number(startWp.longitude);
    if (!isNaN(lat) && !isNaN(lon)) {
      const pos = Cesium.Cartesian3.fromDegrees(lon, lat, 100);
      positionsToFit.push(pos);

      viewer.entities.add({
        id: 'sim-start-point',
        name: startWp.label || 'Titik Awal Dosen',
        position: pos,
        billboard: {
          image: createStartMarkerSvg(),
          width: 40,
          height: 40,
          verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
          disableDepthTestDistance: Number.POSITIVE_INFINITY
        },
        label: {
          text: startWp.label || 'Titik Awal Dosen',
          font: '700 12px Inter, sans-serif',
          fillColor: Cesium.Color.WHITE,
          outlineColor: Cesium.Color.fromCssColorString('#1e3a8a'),
          outlineWidth: 4,
          style: Cesium.LabelStyle.FILL_AND_OUTLINE,
          pixelOffset: new Cesium.Cartesian2(0, -44),
          showBackground: true,
          backgroundColor: Cesium.Color.fromCssColorString('#1d4ed8').withAlpha(0.8)
        }
      });
    }
  }

  // 2. Render Rute & Detail Kunjungan jika ada hasil
  if (detail && detail.length > 0) {
    let flatPath = [];
    let hasHighPrecisionPath = false;

    // A. Render markers/billboards first
    detail.forEach((d, index) => {
      const lat = Number(d.latitude);
      const lon = Number(d.longitude);
      if (isNaN(lat) || isNaN(lon)) return;

      const pos = Cesium.Cartesian3.fromDegrees(lon, lat, 50);
      positionsToFit.push(pos);

      if (d.tipe_titik === 'mahasiswa') {
        const studentName = d.mahasiswa?.nama || d.label || `Tujuan ${index}`;
        viewer.entities.add({
          id: `sim-stop-${d.visitasi_rute_detail_id || index}`,
          name: studentName,
          position: pos,
          billboard: {
            image: createSimulationStudentMarkerSvg(d.urutan_kunjungan),
            width: 36,
            height: 36,
            verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
            disableDepthTestDistance: Number.POSITIVE_INFINITY
          },
          label: {
            text: `${d.urutan_kunjungan}. ${studentName}`,
            font: '700 11px Inter, sans-serif',
            fillColor: Cesium.Color.WHITE,
            outlineColor: Cesium.Color.fromCssColorString('#78350f'),
            outlineWidth: 4,
            style: Cesium.LabelStyle.FILL_AND_OUTLINE,
            pixelOffset: new Cesium.Cartesian2(0, -40),
            showBackground: true,
            backgroundColor: Cesium.Color.fromCssColorString('#92400e').withAlpha(0.8)
          }
        });
      }
    });

    // B. Build polyline/corridor coordinates from OSRM steps
    const raw = simulationRouteData.value.hasil_osrm_raw;
    if (raw && raw.trips && raw.trips[0] && raw.trips[0].legs) {
      const legs = raw.trips[0].legs;
      legs.forEach(leg => {
        if (leg.steps && leg.steps.length > 0) {
          leg.steps.forEach(step => {
            if (step.geometry) {
              const decoded = decodePolyline(step.geometry);
              decoded.forEach(p => {
                flatPath.push(p.longitude, p.latitude);
              });
              hasHighPrecisionPath = true;
            }
          });
        }
      });
    }

    if (!hasHighPrecisionPath) {
      // Fallback: decode geometries of each detail or straight line
      detail.forEach((d) => {
        const lat = Number(d.latitude);
        const lon = Number(d.longitude);
        if (isNaN(lat) || isNaN(lon)) return;

        if (d.geometri_polyline) {
          const decodedPoints = decodePolyline(d.geometri_polyline);
          decodedPoints.forEach(p => {
            flatPath.push(p.longitude, p.latitude);
          });
        } else {
          flatPath.push(lon, lat);
        }
      });
    }

    // C. Draw corridor (polygon) and polyline on map
    if (flatPath.length >= 4) {
      // 1. Polygon corridor (ribbon road shape)
      viewer.entities.add({
        id: 'sim-route-corridor',
        corridor: {
          positions: Cesium.Cartesian3.fromDegreesArray(flatPath),
          width: 25.0, // 25 meters wide road representation
          material: Cesium.Color.fromCssColorString('#10b981').withAlpha(0.35),
          cornerType: Cesium.CornerType.ROUNDED,
          height: 0,
          heightReference: Cesium.HeightReference.CLAMP_TO_GROUND
        }
      });

      // 2. Polyline center-line (glowing path overlay)
      viewer.entities.add({
        id: 'sim-route-polyline',
        polyline: {
          positions: Cesium.Cartesian3.fromDegreesArray(flatPath),
          width: 4,
          material: Cesium.Color.fromCssColorString('#34d399'),
          clampToGround: true
        }
      });
    }
  } else if (peserta && peserta.length > 0) {
    // 3. Jika belum ada rute, render marker peserta saat ini (tahap persiapan)
    peserta.forEach((pes, index) => {
      const mhs = pes.mahasiswa;
      if (!mhs) return;
      const lat = Number(mhs.latitude);
      const lon = Number(mhs.longitude);
      if (isNaN(lat) || isNaN(lon)) return;

      const pos = Cesium.Cartesian3.fromDegrees(lon, lat, 50);
      positionsToFit.push(pos);

      viewer.entities.add({
        id: `sim-peserta-prep-${pes.visitasi_peserta_id || index}`,
        name: mhs.nama,
        position: pos,
        billboard: {
          image: createSimulationStudentMarkerSvg(index + 1),
          width: 36,
          height: 36,
          verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
          disableDepthTestDistance: Number.POSITIVE_INFINITY
        },
        label: {
          text: mhs.nama,
          font: '700 11px Inter, sans-serif',
          fillColor: Cesium.Color.WHITE,
          outlineColor: Cesium.Color.fromCssColorString('#4b5563'),
          outlineWidth: 3,
          style: Cesium.LabelStyle.FILL_AND_OUTLINE,
          pixelOffset: new Cesium.Cartesian2(0, -40),
          showBackground: true,
          backgroundColor: Cesium.Color.fromCssColorString('#374151').withAlpha(0.75)
        }
      });
    });
  }

  // Focus camera
  if (positionsToFit.length > 0) {
    viewer.camera.flyToBoundingSphere(
      Cesium.BoundingSphere.fromPoints(positionsToFit),
      {
        duration: 1.5,
        offset: new Cesium.HeadingPitchRange(
          Cesium.Math.toRadians(0),
          Cesium.Math.toRadians(-65),
          null
        )
      }
    );
  }
};

watch(isSimulasiOpen, (isOpen) => {
  if (!isOpen) {
    simulationRouteData.value = null;
    isCaptureCoordinatesMode.value = false;
    const existing = viewer?.entities?.getById('sim-click-feedback');
    if (existing && viewer) {
      viewer.entities.remove(existing);
    }
  }
  addMapEntities();
});

const flyToIndonesia = (duration = 1.1) => {
  if (!viewer || !Cesium) {
    return;
  }

  const provinceSignature = buildPointSignature("provinsi", null, {});

  selectedPoint.value = null;
  selectedStudents.value = [];
  activeParentId.value = null;
  activeParentName.value = "";
  activeRegionSelection.value = null;
  navigationStack.value = [];
  studentPagination.value = createEmptyStudentPagination();
  isStudentListOpen.value = true;
  searchQuery.value = "";
  searchResults.value = [];
  searchError.value = "";
  pointRequestSequence += 1;
  terrainSampleSequence += 1;
  viewer.camera.cancelFlight();
  isCameraTransitioning = true;

  if (mapPointCache.has(provinceSignature)) {
    applyMapPointPayload(
      mapPointCache.get(provinceSignature),
      "provinsi",
      null,
      "",
      provinceSignature,
    );
  }

  viewer.camera.flyTo({
    destination: Cesium.Cartesian3.fromDegrees(
      INDONESIA_CENTER.lon,
      INDONESIA_CENTER.lat,
      levelFlyHeights.provinsi,
    ),
    orientation: {
      heading: Cesium.Math.toRadians(0),
      pitch: Cesium.Math.toRadians(-64),
      roll: 0,
    },
    duration: Math.min(duration, 0.75),
    complete: () => {
      isCameraTransitioning = false;
      void fetchMapPoints("provinsi", {
        force: !mapPointCache.has(provinceSignature),
        useBounds: false,
      });
    },
    cancel: () => {
      isCameraTransitioning = false;
      requestSceneRender();
    },
  });
};

const resetView = () => {
  flyToIndonesia();
};

const zoomIn = () => {
  if (viewer) {
    viewer.camera.zoomIn(350000);
    requestSceneRender();
  }
};

const zoomOut = () => {
  if (viewer) {
    viewer.camera.zoomOut(350000);
    requestSceneRender();
  }
};

const resolveLevelFromCamera = () => {
  if (!viewer || !Cesium) {
    return activeLevelKey.value;
  }

  const cartographic = Cesium.Cartographic.fromCartesian(
    viewer.camera.positionWC,
  );
  const height = Number(cartographic.height || 0);

  if (height > 2300000) {
    return "provinsi";
  }

  if (height > 800000) {
    return "kabupaten";
  }

  if (height > 240000) {
    return "kecamatan";
  }

  return "desa";
};

const getVisibleBoundsParams = () => {
  if (!viewer || !Cesium) {
    return {};
  }

  const rectangle = viewer.camera.computeViewRectangle(
    viewer.scene.globe.ellipsoid,
  );

  if (!rectangle) {
    return {};
  }

  const west = Cesium.Math.toDegrees(rectangle.west);
  const east = Cesium.Math.toDegrees(rectangle.east);
  const south = Cesium.Math.toDegrees(rectangle.south);
  const north = Cesium.Math.toDegrees(rectangle.north);

  if (![west, east, south, north].every(Number.isFinite) || west > east) {
    return {};
  }

  return {
    min_lat: clampCoordinate(south, -90, 90),
    max_lat: clampCoordinate(north, -90, 90),
    min_lng: clampCoordinate(west, -180, 180),
    max_lng: clampCoordinate(east, -180, 180),
  };
};

const clampCoordinate = (value, min, max) =>
  Math.max(min, Math.min(max, value));

const getCompatibleParentId = (levelKey, parentId) => {
  if (!parentId) {
    return null;
  }

  const targetLength = levelLengths[levelKey] || 0;
  return String(parentId).length < targetLength ? String(parentId) : null;
};

const buildPointSignature = (levelKey, parentId, bounds) => {
  const roundedBounds = Object.keys(bounds)
    .sort()
    .map((key) => `${key}:${Number(bounds[key]).toFixed(1)}`)
    .join(",");

  return `${levelKey}|${parentId || ""}|${roundedBounds}`;
};

const getLevelPerformance = (levelKey) =>
  levelPerformance[levelKey] || levelPerformance.provinsi;

const getNavigationKeepCount = (levelKey) =>
  ({
    provinsi: 0,
    kabupaten: 1,
    kecamatan: 2,
    desa: 3,
  })[levelKey] ?? 0;

const applyMapPointPayload = (
  payload,
  fallbackLevel,
  parentId,
  parentName,
  signature,
) => {
  const rows = Array.isArray(payload?.points) ? payload.points : [];

  mapPoints.value = rows
    .map(normalizeRegionPoint)
    .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lon))
    .sort((a, b) => b.count - a.count);

  activeLevelKey.value = payload?.level || fallbackLevel;
  nextLevelKey.value = payload?.next_level || null;
  activeParentId.value = parentId;
  activeParentName.value =
    parentName || (parentId ? activeParentName.value : "");
  currentPointSignature = signature;
  mapError.value = "";
  addMapEntities();
  void syncVisibleTerrainHeights();
  requestSceneRender();
};

const initializePrimitiveCollections = () => {
  if (!viewer || !Cesium) {
    return;
  }

  regionPointCollection = viewer.scene.primitives.add(
    new Cesium.PointPrimitiveCollection(),
  );
  regionLabelCollection = viewer.scene.primitives.add(
    new Cesium.LabelCollection(),
  );
};

const loadTerrainProvider = async () => {
  if (!viewer || !Cesium) {
    return null;
  }

  if (!terrainProviderPromise) {
    terrainProviderPromise = Cesium.createWorldTerrainAsync({
      requestVertexNormals: false,
      requestWaterMask: false,
    }).catch(() => null);
  }

  const provider = await terrainProviderPromise;

  if (!provider || !viewer) {
    return null;
  }

  viewer.terrainProvider = provider;
  terrainReady.value = true;

  try {
    const [hubCartographic] = await Cesium.sampleTerrainMostDetailed(provider, [
      Cesium.Cartographic.fromDegrees(VISITATION_HUB.lon, VISITATION_HUB.lat),
    ]);
    terrainHeightCache.set(
      "hub",
      Number.isFinite(hubCartographic?.height)
        ? Number(hubCartographic.height)
        : 0,
    );
  } catch {
    terrainHeightCache.set("hub", 0);
  }

  requestSceneRender();
  void syncVisibleTerrainHeights({ force: true });

  return provider;
};

const syncVisibleTerrainHeights = async (options = {}) => {
  if (!viewer || !Cesium || !terrainReady.value || !viewer.terrainProvider) {
    return;
  }

  const force = Boolean(options.force);
  const performance = getLevelPerformance(activeLevelKey.value);
  const regionTargets = mapPoints.value.slice(0, performance.renderLimit);
  const searchTargets = searchResults.value.slice(0, SEARCH_RESULT_LIMIT);
  const targets = [...regionTargets, ...searchTargets];
  const pendingTargets = targets.filter(
    (point) => force || !terrainHeightCache.has(getTerrainCacheKey(point)),
  );

  if (pendingTargets.length === 0) {
    return;
  }

  const requestId = ++terrainSampleSequence;
  const cartographics = pendingTargets.map((point) =>
    Cesium.Cartographic.fromDegrees(point.lon, point.lat),
  );

  try {
    const sampled = await Cesium.sampleTerrainMostDetailed(
      viewer.terrainProvider,
      cartographics,
    );

    if (requestId !== terrainSampleSequence) {
      return;
    }

    sampled.forEach((cartographic, index) => {
      const point = pendingTargets[index];
      const height = Number.isFinite(cartographic?.height)
        ? Number(cartographic.height)
        : 0;
      terrainHeightCache.set(getTerrainCacheKey(point), height);
    });

    addMapEntities();
    requestSceneRender();
  } catch {
    // Keep ellipsoid/fallback heights when terrain sampling fails.
  }
};

const clearPrimitiveCollections = () => {
  regionPointCollection?.removeAll();
  regionLabelCollection?.removeAll();
};

const getTerrainCacheKey = (point) =>
  `${point.id}|${point.lat.toFixed(6)}|${point.lon.toFixed(6)}`;

const getPointMarkerOffset = (point) => {
  if (point.type === "mahasiswa") {
    return TERRAIN_MARKER_OFFSET.mahasiswa;
  }

  return (
    TERRAIN_MARKER_OFFSET[point.levelKey] || TERRAIN_MARKER_OFFSET.kecamatan
  );
};

const getPointHeight = (point) => {
  const cachedHeight = terrainHeightCache.get(getTerrainCacheKey(point));
  const baseHeight = Number.isFinite(cachedHeight) ? cachedHeight : 0;
  return baseHeight + getPointMarkerOffset(point);
};

const getPointTerrainHeight = (point) => {
  const cachedHeight = terrainHeightCache.get(getTerrainCacheKey(point));
  return Number.isFinite(cachedHeight) ? cachedHeight : 0;
};

const getHubHeight = () => {
  const cachedHeight = terrainHeightCache.get("hub");
  const baseHeight = Number.isFinite(cachedHeight) ? cachedHeight : 0;
  return baseHeight + TERRAIN_MARKER_OFFSET.hub;
};

const buildPointCartesian = (point) =>
  Cesium.Cartesian3.fromDegrees(point.lon, point.lat, getPointHeight(point));

const resolvePickedPointId = (picked) => {
  const entityPointId = picked?.id?.properties?.pointId?.getValue?.();

  if (entityPointId) {
    return entityPointId;
  }

  if (typeof picked?.id === "string") {
    return picked.id;
  }

  if (typeof picked?.primitive?.id === "string") {
    return picked.primitive.id;
  }

  return null;
};

const requestSceneRender = () => {
  if (viewer?.scene?.requestRender) {
    viewer.scene.requestRender();
  }
};

const handleLogout = () => {
  authStore.logout();
  router.push("/auth/login");
};

onMounted(async () => {
  const saved = localStorage.getItem(THEME_KEY);
  applyTheme(saved === "light" || saved === "dark" ? saved : "dark");

  authStore.initAuth();

  if (!authStore.isAuthenticated) {
    router.replace("/auth/login");
    return;
  }

  // Cek query parameter untuk langsung membuka panel simulasi
  if (route.query.simulasi === "true" && authStore.user?.role === "dosen") {
    isSimulasiOpen.value = true;
  }

  await fetchMapPoints("provinsi", {
    force: true,
    useBounds: false,
  });

  try {
    await initializeCesium();
  } catch (error) {
    mapError.value = error?.message || "Gagal memuat Cesium 3D map.";
  }
});

onBeforeUnmount(() => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer);
  }

  if (pointFetchTimer) {
    clearTimeout(pointFetchTimer);
  }

  stopJoystick();

  if (viewer && cameraMoveListener) {
    viewer.camera.moveEnd.removeEventListener(cameraMoveListener);
  }

  if (viewer && viewer.scene) {
    viewer.scene.preUpdate.removeEventListener(enforceCameraBounds);
  }

  if (clickHandler) {
    clickHandler.destroy();
    clickHandler = null;
  }

  if (viewer) {
    regionPointCollection = null;
    regionLabelCollection = null;
    viewer.destroy();
    viewer = null;
  }
});
</script>

<style scoped>
:global(.cesium-widget),
:global(.cesium-widget canvas),
:global(.cesium-viewer),
:global(.cesium-viewer-cesiumWidgetContainer) {
  width: 100%;
  height: 100%;
}

:global(.cesium-viewer-bottom) {
  right: 1rem;
  bottom: 0.5rem;
  left: auto;
}

:global(.cesium-credit-lightbox-overlay) {
  z-index: 80;
}
</style>
