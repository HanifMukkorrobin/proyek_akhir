<template>
  <div
    v-if="isOpen"
    class="fixed bottom-5 left-4 top-24 z-50 flex w-[min(26rem,calc(100vw-2rem))] flex-col rounded-2xl border border-outline-variant/60 bg-white p-5 shadow-2xl backdrop-blur-md transition-transform duration-300 dark:border-emerald-900/60 dark:bg-forest-950/95 sm:left-6 sm:top-32 lg:bottom-6 overflow-hidden text-on-surface dark:text-inverse-on-surface"
  >
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-outline-variant/60 dark:border-emerald-900/60 shrink-0">
      <div>
        <h2 class="text-base font-black text-primary dark:text-emerald-400">Simulasi Rute</h2>
        <p class="text-[11px] text-slate-500 dark:text-emerald-100/50">Rencanakan visitasi efisien ke rumah mahasiswa</p>
      </div>
      <button
        class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-forest-800"
        type="button"
        @click="closePanel"
      >
        <Icon icon="solar:close-circle-bold-duotone" class="h-5 w-5" />
      </button>
    </div>

    <!-- Tab Navigation -->
    <div class="flex border-b border-outline-variant/40 dark:border-emerald-900/40 shrink-0">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-bold transition"
        :class="activeTab === tab.key
          ? 'border-b-2 border-primary text-primary dark:text-emerald-400'
          : 'text-slate-500 hover:text-slate-700 dark:text-emerald-100/40 dark:hover:text-emerald-100/70'"
        type="button"
        @click="activeTab = tab.key"
      >
        <Icon :icon="tab.icon" class="h-4 w-4" />
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <!-- Scrollable Panel Body -->
    <div class="flex-1 overflow-y-auto py-4 min-h-0 pr-0.5">
      <!-- Tab: Rencana -->
      <div v-if="activeTab === 'rencana'" class="flex flex-col gap-4">
        <!-- MODE A: DAFTAR RENCANA -->
        <div v-if="!showFormRencana" class="flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-600 dark:text-emerald-100/70">Daftar Rencana</h3>
            <button
              class="flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-[11px] font-bold text-on-primary shadow-sm transition hover:opacity-90"
              type="button"
              @click="openUnifiedForm"
            >
              <Icon icon="solar:add-circle-bold-duotone" class="h-3.5 w-3.5" />
              Buat Baru
            </button>
          </div>

          <div v-if="rencanaLoading" class="space-y-3">
            <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-forest-900" />
          </div>

          <div
            v-else-if="rencanaList.length === 0"
            class="flex flex-col items-center rounded-xl border border-dashed border-outline-variant bg-slate-50 py-8 text-center dark:border-emerald-900/40 dark:bg-forest-900/50"
          >
            <Icon icon="solar:route-bold-duotone" class="h-8 w-8 text-slate-400 dark:text-emerald-100/20" />
            <p class="mt-2 text-xs font-bold text-slate-700 dark:text-emerald-100/70">Belum ada rencana</p>
            <p class="text-[10px] text-slate-500 dark:text-emerald-100/45">Klik tombol di atas untuk memulai.</p>
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="rencana in rencanaList"
              :key="rencana.visitasi_rencana_id"
              class="group cursor-pointer rounded-xl border p-3 transition"
              :class="selectedRencana?.visitasi_rencana_id === rencana.visitasi_rencana_id
                ? 'border-primary bg-primary/5 dark:border-emerald-500/30 dark:bg-emerald-950/40'
                : 'border-outline-variant/60 bg-white hover:bg-slate-50 dark:border-emerald-900/60 dark:bg-forest-900 dark:hover:bg-forest-800'"
              @click="selectRencana(rencana)"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-bold text-on-surface dark:text-white">{{ rencana.nama_rencana }}</p>
                <span
                  class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase"
                  :class="statusBadgeClass(rencana.status)"
                >{{ rencana.status }}</span>
              </div>
              <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500 dark:text-emerald-100/50">
                <span class="flex items-center gap-1">
                  <Icon :icon="rencana.jenis_kendaraan === 'mobil' ? 'solar:car-bold-duotone' : 'solar:scooter-bold-duotone'" class="h-3.5 w-3.5" />
                  {{ rencana.jenis_kendaraan }}
                  <span v-if="rencana.jenis_kendaraan === 'mobil' && rencana.lewat_tol" class="text-primary dark:text-emerald-400">+ tol</span>
                </span>
                <span>{{ rencana.jumlah_peserta ?? 0 }}/5 mahasiswa</span>
              </div>
              <div v-if="rencana.perkiraan_total_jarak_km" class="mt-2 flex items-center justify-between border-t border-dashed border-outline-variant/40 pt-2 text-[11px] font-bold text-primary dark:text-emerald-400">
                <span>{{ rencana.perkiraan_total_jarak_km }} km</span>
                <span>{{ rencana.perkiraan_total_menit }} menit</span>
              </div>
            </div>
          </div>
        </div>

        <!-- MODE B: UNIFIED CREATION FORM -->
        <div v-else class="flex flex-col gap-4">
          <div class="flex items-center justify-between border-b border-outline-variant/30 pb-2">
            <h3 class="text-xs font-bold text-slate-600 dark:text-emerald-100/70">Buat Rencana Baru</h3>
            <button
              class="text-[10px] font-bold text-slate-500 hover:text-slate-700 dark:text-emerald-100/40 dark:hover:text-emerald-100/70"
              type="button"
              @click="closeUnifiedForm"
            >
              Batal
            </button>
          </div>

          <div class="space-y-3 text-xs">
            <div>
              <label class="font-bold text-slate-600 dark:text-emerald-100/60">Nama Rencana *</label>
              <input
                v-model="unifiedForm.nama_rencana"
                class="mt-1 h-9 w-full rounded-xl border border-outline-variant bg-slate-50 px-3 text-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-emerald-800 dark:bg-forest-950 dark:text-white"
                type="text"
                placeholder="misal: Visitasi Surabaya Barat"
              >
            </div>
            <div>
              <label class="font-bold text-slate-600 dark:text-emerald-100/60">Deskripsi</label>
              <textarea
                v-model="unifiedForm.deskripsi"
                class="mt-1 w-full rounded-xl border border-outline-variant bg-slate-50 px-3 py-2 text-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-emerald-800 dark:bg-forest-950 dark:text-white"
                rows="2"
                placeholder="Catatan singkat..."
              />
            </div>

            <!-- Pilih Peserta (Maksimal 5) -->
            <div class="rounded-xl border border-outline-variant/60 bg-white p-3 dark:border-emerald-900/60 dark:bg-forest-900 relative">
              <label class="font-bold text-slate-600 dark:text-emerald-100/60">Pilih Peserta (Maksimal 5) *</label>
              
              <div class="relative mt-1">
                <Icon icon="solar:magnifer-linear" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="mahasiswaSearch"
                  class="h-8 w-full rounded-lg border border-outline-variant bg-slate-50 pl-9 pr-3 text-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-emerald-800 dark:bg-forest-950 dark:text-white"
                  type="search"
                  placeholder="Cari mahasiswa dengan lokasi valid..."
                  @input="debouncedSearchMahasiswa"
                >
              </div>

              <!-- Search results dropdown in unified form -->
              <div
                v-if="mahasiswaSearch && mahasiswaSearchResults.length > 0"
                class="absolute left-3 right-3 z-[70] max-h-40 overflow-y-auto rounded-lg border border-outline-variant bg-white shadow-lg dark:border-emerald-800 dark:bg-forest-900 mt-1"
              >
                <button
                  v-for="mhs in mahasiswaSearchResults"
                  :key="mhs.mahasiswa_id"
                  class="flex w-full items-center gap-2 px-3 py-2 text-left text-[11px] transition hover:bg-slate-50 dark:hover:bg-forest-850"
                  type="button"
                  :disabled="unifiedForm.mahasiswa_ids.includes(mhs.mahasiswa_id) || unifiedForm.mahasiswa_ids.length >= 5"
                  @click="addMahasiswaToUnified(mhs)"
                >
                  <Icon icon="solar:user-bold-duotone" class="h-3.5 w-3.5 shrink-0 text-primary" />
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold text-on-surface dark:text-white">{{ mhs.nama }}</p>
                    <p class="truncate text-[9px] text-slate-500 dark:text-emerald-100/40">{{ mhs.alamat }}</p>
                  </div>
                  <span v-if="unifiedForm.mahasiswa_ids.includes(mhs.mahasiswa_id)" class="ml-auto shrink-0 text-[10px] font-bold text-primary">✓</span>
                </button>
              </div>

              <!-- List of selected participants -->
              <div class="mt-2 space-y-1.5">
                <div
                  v-for="(mhs, idx) in selectedMahasiswaList"
                  :key="mhs.mahasiswa_id"
                  class="flex items-center justify-between rounded-lg bg-slate-50 p-2 dark:bg-forest-950"
                >
                  <div class="flex items-center gap-2 min-w-0">
                    <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-black text-primary">
                      {{ idx + 1 }}
                    </div>
                    <div class="min-w-0">
                      <p class="text-[11px] font-bold text-on-surface dark:text-white truncate">{{ mhs.nama }}</p>
                      <p class="text-[9px] text-slate-500 truncate dark:text-emerald-100/50">{{ mhs.alamat }}</p>
                    </div>
                  </div>
                  <button
                    class="text-slate-400 hover:text-error transition p-1"
                    type="button"
                    @click="removeMahasiswaFromUnified(mhs.mahasiswa_id)"
                  >
                    <Icon icon="solar:trash-bin-minimalistic-bold-duotone" class="h-3.5 w-3.5" />
                  </button>
                </div>
                <p v-if="selectedMahasiswaList.length === 0" class="text-[10px] text-slate-400 text-center py-2 dark:text-emerald-100/30">
                  Belum ada peserta terpilih.
                </p>
              </div>
            </div>

            <!-- Titik Awal Dosen -->
            <div class="rounded-xl border border-outline-variant/60 bg-white p-3 dark:border-emerald-900/60 dark:bg-forest-900">
              <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-slate-600 dark:text-emerald-100/60">Titik Awal Dosen *</span>
                <button
                  class="flex h-6 items-center gap-1 rounded bg-primary/10 px-2 text-[9px] font-bold text-primary dark:text-emerald-400"
                  :class="{ 'animate-pulse bg-emerald-500/20 text-emerald-600': isCaptureMode }"
                  type="button"
                  @click="toggleMapCaptureMode"
                >
                  <Icon icon="solar:cursor-bold-duotone" class="h-3 w-3" />
                  {{ isCaptureMode ? 'Pilih di Peta (Aktif)' : 'Klik Peta' }}
                </button>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="text-[9px] font-bold text-slate-500">Latitude</label>
                  <input
                    v-model="unifiedForm.titik_awal_latitude"
                    class="mt-1 h-8 w-full rounded-lg border border-outline-variant bg-slate-50 px-2 font-mono text-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-emerald-800 dark:bg-forest-950 dark:text-white"
                    type="number"
                    step="0.000001"
                    placeholder="-7.275612"
                  >
                </div>
                <div>
                  <label class="text-[9px] font-bold text-slate-500">Longitude</label>
                  <input
                    v-model="unifiedForm.titik_awal_longitude"
                    class="mt-1 h-8 w-full rounded-lg border border-outline-variant bg-slate-50 px-2 font-mono text-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-emerald-800 dark:bg-forest-950 dark:text-white"
                    type="number"
                    step="0.000001"
                    placeholder="112.793910"
                  >
                </div>
              </div>
              <input
                v-model="unifiedForm.titik_awal_label"
                class="mt-2 h-8 w-full rounded-lg border border-outline-variant bg-slate-50 px-2 text-[11px] outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-emerald-800 dark:bg-forest-950 dark:text-white"
                type="text"
                placeholder="Nama lokasi (cth: Rumah Dosen)..."
              >
            </div>

            <!-- Moda Kendaraan -->
            <div class="rounded-xl border border-outline-variant/60 bg-white p-3 dark:border-emerald-900/60 dark:bg-forest-900">
              <span class="font-bold text-slate-600 dark:text-emerald-100/60">Moda Kendaraan & Tol *</span>
              <div class="mt-1.5 grid grid-cols-2 gap-2">
                <button
                  v-for="kend in ['motor', 'mobil']"
                  :key="kend"
                  class="flex h-8 items-center justify-center gap-1.5 rounded-lg border text-xs font-bold transition"
                  :class="unifiedForm.jenis_kendaraan === kend
                    ? 'border-primary bg-primary/10 text-primary dark:border-emerald-500 dark:text-emerald-400'
                    : 'border-outline-variant bg-slate-50 text-slate-600 hover:border-primary/40 dark:border-emerald-800 dark:bg-forest-950 dark:text-emerald-100/60'"
                  type="button"
                  @click="unifiedForm.jenis_kendaraan = kend"
                >
                  <Icon :icon="kend === 'mobil' ? 'solar:car-bold-duotone' : 'solar:scooter-bold-duotone'" class="h-3.5 w-3.5" />
                  {{ kend.charAt(0).toUpperCase() + kend.slice(1) }}
                </button>
              </div>

              <label v-if="unifiedForm.jenis_kendaraan === 'mobil'" class="mt-3 flex cursor-pointer items-center gap-2">
                <div
                  class="relative h-5 w-9 shrink-0 rounded-full transition"
                  :class="unifiedForm.lewat_tol ? 'bg-primary' : 'bg-slate-300 dark:bg-forest-700'"
                  @click="unifiedForm.lewat_tol = !unifiedForm.lewat_tol"
                >
                  <div
                    class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all"
                    :class="unifiedForm.lewat_tol ? 'left-4' : 'left-0.5'"
                  />
                </div>
                <span class="text-[11px] font-bold text-on-surface dark:text-white">Gunakan Jalan Tol</span>
              </label>
            </div>

            <!-- Form Error -->
            <div v-if="rencanaFormError" class="rounded-xl border border-error/20 bg-error-container/30 px-3 py-1.5 text-[10px] text-error">
              {{ rencanaFormError }}
            </div>

            <!-- Submit Button -->
            <button
              class="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-bold text-on-primary shadow-md shadow-primary/20 transition hover:opacity-90 disabled:opacity-60"
              type="button"
              :disabled="savingRencana"
              @click="submitUnifiedForm"
            >
              <Icon :icon="savingRencana ? 'solar:refresh-bold-duotone' : 'solar:routing-bold-duotone'" class="h-4.5 w-4.5" :class="{ 'animate-spin': savingRencana }" />
              {{ savingRencana ? 'Menyimpan & Hitung...' : 'Simpan & Hitung Rute' }}
            </button>
          </div>
        </div>
      </div>


      <!-- Tab: Hasil -->
      <div v-else-if="activeTab === 'hasil'" class="flex flex-col gap-4">
        <div v-if="!selectedRencana" class="rounded-xl border border-dashed border-outline-variant bg-slate-50 py-8 text-center dark:border-emerald-900/40 dark:bg-forest-900/50">
          <p class="text-xs text-slate-500 dark:text-emerald-100/50">Pilih rencana terlebih dahulu.</p>
        </div>
        <template v-else>
          <!-- Action Button -->
          <button
            class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-primary text-xs font-bold text-on-primary shadow-md shadow-primary/20 transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60"
            type="button"
            :disabled="simulasiLoading || !selectedRencana?.titik_awal_latitude || pesertaList.length === 0"
            @click="runSimulasi"
          >
            <Icon :icon="simulasiLoading ? 'solar:refresh-bold-duotone' : 'solar:routing-bold-duotone'" class="h-4.5 w-4.5" :class="{ 'animate-spin': simulasiLoading }" />
            {{ simulasiLoading ? 'Menghitung Rute...' : 'Jalankan Simulasi' }}
          </button>

          <div v-if="simulasiError" class="rounded-xl border border-error/20 bg-error-container/30 px-3 py-2 text-[10px] text-error">
            {{ simulasiError }}
          </div>

          <!-- Hasil Rute -->
          <div v-if="hasilRute" class="space-y-3">
            <div class="grid grid-cols-2 gap-2">
              <div class="rounded-xl border border-primary/20 bg-primary/5 p-3 dark:border-emerald-700/30 dark:bg-emerald-950/30">
                <p class="text-[9px] uppercase tracking-wider text-primary/70 dark:text-emerald-400/70">Total Jarak</p>
                <p class="mt-0.5 text-lg font-black text-primary dark:text-emerald-400">{{ hasilRute.total_jarak_km }} km</p>
              </div>
              <div class="rounded-xl border border-outline-variant/60 bg-white p-3 dark:border-emerald-900/60 dark:bg-forest-900">
                <p class="text-[9px] uppercase tracking-wider text-slate-500 dark:text-emerald-100/50">Est. Waktu</p>
                <p class="mt-0.5 text-lg font-black text-on-surface dark:text-white">{{ hasilRute.total_estimasi_menit }} menit</p>
              </div>
            </div>

            <div class="flex items-center justify-between border-t border-outline-variant/30 pt-3">
              <p class="text-xs font-bold text-on-surface dark:text-white">Urutan Rute</p>
              <div class="flex gap-1.5">
                <button
                  class="flex h-7 items-center gap-1 rounded bg-slate-100 px-2.5 text-[10px] font-bold text-slate-700 hover:bg-slate-200 dark:bg-forest-900 dark:text-emerald-100/70"
                  type="button"
                  @click="openPrintPage"
                >
                  <Icon icon="solar:printer-bold-duotone" class="h-3 w-3" />
                  PDF
                </button>
                <button
                  class="flex h-7 items-center gap-1 rounded bg-slate-100 px-2.5 text-[10px] font-bold text-slate-700 hover:bg-slate-200 dark:bg-forest-900 dark:text-emerald-100/70"
                  type="button"
                  :disabled="exportingExcel"
                  @click="exportExcel"
                >
                  <Icon icon="solar:file-download-bold-duotone" class="h-3 w-3" />
                  Excel
                </button>
              </div>
            </div>

            <!-- Detail List -->
            <div class="max-h-[30vh] overflow-y-auto border border-outline-variant/40 rounded-xl">
              <div
                v-for="(titik, idx) in hasilRute.detail"
                :key="titik.visitasi_rute_detail_id"
                class="flex items-start gap-2 border-b border-outline-variant/30 px-3 py-2.5 last:border-0"
                :class="titik.tipe_titik === 'titik_awal' || titik.tipe_titik === 'kembali' ? 'bg-primary/5 dark:bg-emerald-950/20' : 'bg-white dark:bg-forest-900'"
              >
                <div
                  class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[9px] font-black"
                  :class="titik.tipe_titik === 'titik_awal' ? 'bg-blue-600 text-white' : titik.tipe_titik === 'kembali' ? 'bg-blue-600 text-white' : 'bg-amber-500 text-white'"
                >
                  {{ titik.tipe_titik === 'titik_awal' ? 'S' : titik.tipe_titik === 'kembali' ? 'E' : idx }}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-bold text-on-surface dark:text-white truncate">
                    {{ titik.mahasiswa?.nama || titik.label || '-' }}
                  </p>
                  <div v-if="idx > 0" class="mt-0.5 flex gap-2 text-[10px] text-primary dark:text-emerald-400">
                    <span>+{{ titik.jarak_dari_sebelumnya_km }} km</span>
                    <span>·</span>
                    <span>{{ titik.estimasi_ke_sini_menit }} menit</span>
                  </div>
                </div>
                <div class="shrink-0 text-right font-mono text-[10px] font-bold text-primary dark:text-emerald-400">
                  {{ titik.estimasi_kumulatif_menit }} min
                </div>
              </div>
            </div>

            <!-- Engine telemetry -->
            <p class="text-center text-[9px] text-slate-400 dark:text-emerald-100/30 font-medium">
              Engine: {{ hasilRute.engine === 'haversine_fallback' ? '⚠ Haversine fallback' : '✓ OSRM (jalan nyata)' }}
            </p>
          </div>

          <div
            v-else-if="!simulasiLoading"
            class="flex flex-col items-center rounded-xl border border-dashed border-outline-variant bg-slate-50 py-6 text-center dark:border-emerald-900/40 dark:bg-forest-900/50"
          >
            <Icon icon="solar:routing-bold-duotone" class="h-8 w-8 text-slate-300 dark:text-emerald-100/20" />
            <p class="mt-2 text-xs text-slate-500 dark:text-emerald-100/40">Belum dijalankan. Klik tombol di atas.</p>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, ref, watch, onMounted } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  clickCoordinates: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'update-rute', 'clear-rute', 'set-capture-coordinates'])

const { $api } = useNuxtApp()

// State
const activeTab = ref('rencana')
const rencanaList = ref([])
const rencanaLoading = ref(false)
const selectedRencana = ref(null)
const pesertaList = ref([])
const pesertaLoading = ref(false)
const removingPesertaId = ref(null)
const mahasiswaSearch = ref('')
const mahasiswaSearchResults = ref([])
const hasilRute = ref(null)
const simulasiLoading = ref(false)
const simulasiError = ref('')
const exportingExcel = ref(false)
const savingTitikAwal = ref(false)
const savingKendaraan = ref(false)
const showFormRencana = ref(false)
const editingRencana = ref(null)
const savingRencana = ref(false)
const rencanaFormError = ref('')
const isCaptureMode = ref(false)

const titikAwalForm = ref({ latitude: '', longitude: '', label: '' })
const kendaraanForm = ref({ jenis: 'motor', lewatTol: false })
const rencanaForm = ref({ nama_rencana: '', deskripsi: '', jenis_kendaraan: 'motor', lewat_tol: false })

const tabs = [
  { key: 'rencana', label: 'Rencana', icon: 'solar:document-text-bold-duotone' },
  { key: 'hasil', label: 'Hasil', icon: 'solar:route-bold-duotone' }
]

let debounceTimer = null

// Close panel emit
const closePanel = () => {
  isCaptureMode.value = false
  emit('set-capture-coordinates', false)
  emit('close')
}

// Toggle click-on-map coordinate mode
const toggleMapCaptureMode = () => {
  isCaptureMode.value = !isCaptureMode.value
  emit('set-capture-coordinates', isCaptureMode.value)
}

// Watch click coordinate changes from map
watch(() => props.clickCoordinates, (coords) => {
  if (coords && isCaptureMode.value) {
    const lat = coords.latitude.toFixed(6)
    const lng = coords.longitude.toFixed(6)
    
    if (showFormRencana.value) {
      unifiedForm.value.titik_awal_latitude = lat
      unifiedForm.value.titik_awal_longitude = lng
      unifiedForm.value.titik_awal_label = 'Titik Awal Terpilih'
    } else {
      titikAwalForm.value.latitude = lat
      titikAwalForm.value.longitude = lng
      titikAwalForm.value.label = 'Titik Awal Terpilih'
    }
    
    isCaptureMode.value = false
    emit('set-capture-coordinates', false)
  }
})

// Rencana CRUD
const fetchRencana = async () => {
  rencanaLoading.value = true
  try {
    const res = await $api.get('/visitasi')
    rencanaList.value = res.data?.data?.data || []
  } catch {
    rencanaList.value = []
  } finally {
    rencanaLoading.value = false
  }
}

const selectRencana = async (rencana) => {
  // Clear old state immediately to prevent displaying stale data on the map during loading
  hasilRute.value = null
  pesertaList.value = []
  emit('update-rute', { detail: [], peserta: [] })

  selectedRencana.value = rencana
  titikAwalForm.value = {
    latitude: rencana.titik_awal_latitude || '',
    longitude: rencana.titik_awal_longitude || '',
    label: rencana.titik_awal_label || ''
  }
  kendaraanForm.value = { jenis: rencana.jenis_kendaraan || 'motor', lewatTol: !!rencana.lewat_tol }
  
  await fetchPeserta()
  await fetchLatestRute()
  if (hasilRute.value) {
    activeTab.value = 'hasil'
  }
}

const unifiedForm = ref({
  nama_rencana: '',
  deskripsi: '',
  jenis_kendaraan: 'motor',
  lewat_tol: false,
  titik_awal_latitude: '',
  titik_awal_longitude: '',
  titik_awal_label: '',
  mahasiswa_ids: []
})
const selectedMahasiswaList = ref([])

const openUnifiedForm = () => {
  rencanaFormError.value = ''
  unifiedForm.value = {
    nama_rencana: '',
    deskripsi: '',
    jenis_kendaraan: 'motor',
    lewat_tol: false,
    titik_awal_latitude: '',
    titik_awal_longitude: '',
    titik_awal_label: '',
    mahasiswa_ids: []
  }
  selectedMahasiswaList.value = []
  mahasiswaSearch.value = ''
  mahasiswaSearchResults.value = []
  showFormRencana.value = true
}

const closeUnifiedForm = () => {
  showFormRencana.value = false
  isCaptureMode.value = false
  emit('set-capture-coordinates', false)
}

const addMahasiswaToUnified = (mhs) => {
  if (unifiedForm.value.mahasiswa_ids.length >= 5) return
  if (unifiedForm.value.mahasiswa_ids.includes(mhs.mahasiswa_id)) return
  
  unifiedForm.value.mahasiswa_ids.push(mhs.mahasiswa_id)
  selectedMahasiswaList.value.push(mhs)
  
  mahasiswaSearch.value = ''
  mahasiswaSearchResults.value = []
}

const removeMahasiswaFromUnified = (mhsId) => {
  unifiedForm.value.mahasiswa_ids = unifiedForm.value.mahasiswa_ids.filter(id => id !== mhsId)
  selectedMahasiswaList.value = selectedMahasiswaList.value.filter(mhs => mhs.mahasiswa_id !== mhsId)
}

const submitUnifiedForm = async () => {
  if (!unifiedForm.value.nama_rencana.trim()) {
    rencanaFormError.value = 'Nama rencana wajib diisi.'
    return
  }
  if (!unifiedForm.value.titik_awal_latitude || !unifiedForm.value.titik_awal_longitude) {
    rencanaFormError.value = 'Koordinat titik awal wajib ditentukan (bisa dengan klik di peta).'
    return
  }
  if (unifiedForm.value.mahasiswa_ids.length === 0) {
    rencanaFormError.value = 'Minimal pilih 1 mahasiswa peserta.'
    return
  }

  savingRencana.value = true
  rencanaFormError.value = ''
  try {
    const res = await $api.post('/visitasi/simultan', {
      nama_rencana: unifiedForm.value.nama_rencana,
      deskripsi: unifiedForm.value.deskripsi,
      jenis_kendaraan: unifiedForm.value.jenis_kendaraan,
      lewat_tol: unifiedForm.value.lewat_tol,
      titik_awal_latitude: parseFloat(unifiedForm.value.titik_awal_latitude),
      titik_awal_longitude: parseFloat(unifiedForm.value.titik_awal_longitude),
      titik_awal_label: unifiedForm.value.titik_awal_label || 'Titik Awal Terpilih',
      mahasiswa_ids: unifiedForm.value.mahasiswa_ids
    })

    const resultData = res.data?.data
    hasilRute.value = resultData || null
    
    await fetchRencana()
    
    if (resultData && resultData.visitasi_rencana_id) {
      const createdRencana = rencanaList.value.find(r => r.visitasi_rencana_id === resultData.visitasi_rencana_id)
      if (createdRencana) {
        selectedRencana.value = createdRencana
        await fetchPeserta()
      }
    }

    if (hasilRute.value) {
      emit('update-rute', { 
        detail: hasilRute.value.detail, 
        peserta: pesertaList.value, 
        hasil_osrm_raw: hasilRute.value.hasil_osrm_raw 
      })
      activeTab.value = 'hasil'
    }

    closeUnifiedForm()
  } catch (err) {
    rencanaFormError.value = err?.response?.data?.message || 'Gagal memproses rencana & rute.'
  } finally {
    savingRencana.value = false
  }
}

const statusBadgeClass = (s) => ({
  draft: 'bg-slate-100 text-slate-600 dark:bg-forest-800 dark:text-emerald-100/75',
  siap: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
  selesai: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400'
}[s] || 'bg-slate-100 text-slate-600')

// Peserta
const fetchPeserta = async () => {
  if (!selectedRencana.value) return
  pesertaLoading.value = true
  try {
    const res = await $api.get(`/visitasi/${selectedRencana.value.visitasi_rencana_id}/peserta`)
    pesertaList.value = res.data?.data || []
    // Emisi perubahan peserta saat loading awal untuk rendering persiapan peta
    if (!hasilRute.value) {
      emit('update-rute', { detail: [], peserta: pesertaList.value })
    }
  } catch {
    pesertaList.value = []
  } finally {
    pesertaLoading.value = false
  }
}

const isAlreadyPeserta = (mahasiswaId) => pesertaList.value.some(p => p.mahasiswa_id === mahasiswaId)

const addPeserta = async (mhs) => {
  if (!selectedRencana.value || pesertaList.value.length >= 5) return
  try {
    await $api.post(`/visitasi/${selectedRencana.value.visitasi_rencana_id}/peserta`, {
      mahasiswa_id: mhs.mahasiswa_id
    })
    mahasiswaSearch.value = ''
    mahasiswaSearchResults.value = []
    await fetchPeserta()
    await fetchRencana()
  } catch (err) {
    alert(err?.response?.data?.message || 'Gagal menambahkan peserta.')
  }
}

const removePeserta = async (peserta) => {
  removingPesertaId.value = peserta.visitasi_peserta_id
  try {
    await $api.delete(`/visitasi/${selectedRencana.value.visitasi_rencana_id}/peserta/${peserta.visitasi_peserta_id}`)
    await fetchPeserta()
    await fetchRencana()
  } catch {
    // Silently fail
  } finally {
    removingPesertaId.value = null
  }
}

const debouncedSearchMahasiswa = () => {
  clearTimeout(debounceTimer)
  if (!mahasiswaSearch.value.trim()) {
    mahasiswaSearchResults.value = []
    return
  }
  debounceTimer = setTimeout(searchMahasiswa, 350)
}

const searchMahasiswa = async () => {
  try {
    const res = await $api.get('/dashboard/map/mahasiswa-search', {
      params: { q: mahasiswaSearch.value, exclude_invalid: true, limit: 10 }
    })
    mahasiswaSearchResults.value = res.data?.data?.results || []
  } catch {
    mahasiswaSearchResults.value = []
  }
}

// Titik Awal
const saveTitikAwal = async () => {
  if (!selectedRencana.value) return
  savingTitikAwal.value = true
  try {
    await $api.put(`/visitasi/${selectedRencana.value.visitasi_rencana_id}`, {
      titik_awal_latitude: parseFloat(titikAwalForm.value.latitude),
      titik_awal_longitude: parseFloat(titikAwalForm.value.longitude),
      titik_awal_label: titikAwalForm.value.label || null
    })
    await fetchRencana()
    const updated = rencanaList.value.find(r => r.visitasi_rencana_id === selectedRencana.value.visitasi_rencana_id)
    if (updated) {
      selectedRencana.value = updated
      emit('update-rute', { detail: hasilRute.value?.detail || [], peserta: pesertaList.value, hasil_osrm_raw: hasilRute.value?.hasil_osrm_raw })
    }
  } catch (err) {
    alert(err?.response?.data?.message || 'Gagal menyimpan titik awal.')
  } finally {
    savingTitikAwal.value = false
  }
}

// Kendaraan
const saveKendaraan = async () => {
  if (!selectedRencana.value) return
  savingKendaraan.value = true
  try {
    await $api.put(`/visitasi/${selectedRencana.value.visitasi_rencana_id}`, {
      jenis_kendaraan: kendaraanForm.value.jenis,
      lewat_tol: kendaraanForm.value.lewatTol
    })
    await fetchRencana()
  } catch (err) {
    alert(err?.response?.data?.message || 'Gagal menyimpan pengaturan kendaraan.')
  } finally {
    savingKendaraan.value = false
  }
}

// Simulasi
const runSimulasi = async () => {
  if (!selectedRencana.value) return
  simulasiLoading.value = true
  simulasiError.value = ''
  try {
    const res = await $api.post(`/visitasi/${selectedRencana.value.visitasi_rencana_id}/simulasi`)
    hasilRute.value = res.data?.data || null
    await fetchRencana()
    const updated = rencanaList.value.find(r => r.visitasi_rencana_id === selectedRencana.value.visitasi_rencana_id)
    if (updated) selectedRencana.value = updated
    
    if (hasilRute.value) {
      emit('update-rute', { detail: hasilRute.value.detail, peserta: pesertaList.value, hasil_osrm_raw: hasilRute.value.hasil_osrm_raw })
      activeTab.value = 'hasil'
    }
  } catch (err) {
    simulasiError.value = err?.response?.data?.message || 'Simulasi gagal dijalankan.'
  } finally {
    simulasiLoading.value = false
  }
}

const fetchLatestRute = async () => {
  if (!selectedRencana.value) return
  try {
    const res = await $api.get(`/visitasi/${selectedRencana.value.visitasi_rencana_id}/rute`)
    hasilRute.value = res.data?.data || null
    if (hasilRute.value) {
      emit('update-rute', { detail: hasilRute.value.detail, peserta: pesertaList.value, hasil_osrm_raw: hasilRute.value.hasil_osrm_raw })
    } else {
      emit('update-rute', { detail: [], peserta: pesertaList.value })
    }
  } catch {
    hasilRute.value = null
    emit('update-rute', { detail: [], peserta: pesertaList.value })
  }
}

// Export
const openPrintPage = () => {
  if (!hasilRute.value) return
  const ruteId = hasilRute.value.visitasi_rute_id
  const rencanaId = selectedRencana.value.visitasi_rencana_id
  window.open(`/dashboard/visitasi/${rencanaId}/print/${ruteId}`, '_blank')
}

const exportExcel = async () => {
  exportingExcel.value = true
  try {
    const res = await $api.get('/visitasi/export-rekap-excel', { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `rekap-visitasi-${new Date().toISOString().slice(0, 10)}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    alert('Gagal mengunduh rekap Excel.')
  } finally {
    exportingExcel.value = false
  }
}

// Watch Open State
watch(() => props.isOpen, (open) => {
  if (open) {
    fetchRencana()
  } else {
    emit('clear-rute')
  }
})

onMounted(() => {
  if (props.isOpen) {
    fetchRencana()
  }
})
</script>
