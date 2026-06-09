<template>
  <div class="min-h-screen bg-white font-sans" id="print-page">
    <Head>
      <Title>Print Rute Visitasi | GeoVisit PJJ IT</Title>
    </Head>

    <!-- Print Controls (disembunyikan saat print) -->
    <div class="no-print sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3 shadow-sm">
      <button
        class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
        type="button"
        @click="$router.back()"
      >
        <Icon icon="solar:alt-arrow-left-linear" class="h-4 w-4" />
        Kembali
      </button>
      <div class="flex items-center gap-3">
        <p class="text-sm text-slate-500">Tekan Ctrl+P (atau ⌘P) untuk cetak / simpan PDF</p>
        <button
          class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
          type="button"
          @click="window.print()"
        >
          <Icon icon="solar:printer-bold-duotone" class="h-4 w-4" />
          Cetak / Simpan PDF
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex min-h-screen items-center justify-center">
      <div class="flex flex-col items-center gap-4">
        <Icon icon="solar:refresh-bold-duotone" class="h-10 w-10 animate-spin text-primary" />
        <p class="text-sm text-slate-500">Memuat data rute...</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="errorMessage" class="flex min-h-screen items-center justify-center p-8">
      <div class="text-center">
        <Icon icon="solar:danger-triangle-bold-duotone" class="mx-auto h-12 w-12 text-red-400" />
        <p class="mt-4 text-lg font-semibold text-slate-700">{{ errorMessage }}</p>
      </div>
    </div>

    <!-- Print Content -->
    <main v-else-if="printData" class="mx-auto max-w-4xl px-8 py-10">
      <!-- Header dokumen -->
      <div class="mb-8 flex items-start justify-between border-b-2 border-primary pb-6">
        <div>
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-white">
              <Icon icon="solar:route-bold-duotone" class="h-6 w-6" />
            </div>
            <div>
              <h1 class="text-xl font-black text-primary">Rute Visitasi Dosen</h1>
              <p class="text-xs text-slate-500">GeoVisit PJJ IT — Dokumen Simulasi Rute</p>
            </div>
          </div>
        </div>
        <div class="text-right">
          <p class="text-xs text-slate-500">Tanggal Cetak</p>
          <p class="text-sm font-semibold text-slate-700">{{ formatDateTime(new Date()) }}</p>
        </div>
      </div>

      <!-- Info Rencana -->
      <section class="mb-8 grid grid-cols-2 gap-6">
        <div class="space-y-3">
          <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Informasi Rencana</h2>
          <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
              <tr>
                <td class="py-1.5 pr-4 font-semibold text-slate-600">Nama Rencana</td>
                <td class="py-1.5 text-slate-800">{{ printData.rencana.nama_rencana }}</td>
              </tr>
              <tr>
                <td class="py-1.5 pr-4 font-semibold text-slate-600">Kendaraan</td>
                <td class="py-1.5 text-slate-800">
                  {{ printData.rencana.jenis_kendaraan?.charAt(0).toUpperCase() + printData.rencana.jenis_kendaraan?.slice(1) }}
                  <span v-if="printData.rencana.jenis_kendaraan === 'mobil' && printData.rencana.lewat_tol" class="ml-1 text-primary">(via tol)</span>
                  <span v-if="printData.rencana.jenis_kendaraan === 'mobil' && !printData.rencana.lewat_tol" class="ml-1 text-slate-500">(tanpa tol)</span>
                </td>
              </tr>
              <tr>
                <td class="py-1.5 pr-4 font-semibold text-slate-600">Titik Awal</td>
                <td class="py-1.5 font-mono text-xs text-slate-800">
                  {{ printData.rencana.titik_awal_label || 'Titik Awal Dosen' }}<br>
                  <span class="text-slate-500">{{ printData.rencana.titik_awal_latitude }}, {{ printData.rencana.titik_awal_longitude }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-3">
          <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Hasil Simulasi</h2>
          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-center">
              <p class="text-xs font-semibold uppercase text-primary/70">Total Jarak</p>
              <p class="mt-2 text-3xl font-black text-primary">{{ printData.rute.total_jarak_km }}</p>
              <p class="text-xs text-primary/60">km</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
              <p class="text-xs font-semibold uppercase text-slate-500">Est. Waktu</p>
              <p class="mt-2 text-3xl font-black text-slate-800">{{ printData.rute.total_estimasi_menit }}</p>
              <p class="text-xs text-slate-400">menit</p>
            </div>
          </div>
          <p class="text-xs text-slate-400">
            Simulasi: {{ formatDateTime(printData.rute.dibuat_pada) }}
          </p>
        </div>
      </section>

      <!-- Tabel Urutan Kunjungan -->
      <section class="mb-8">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">Urutan Kunjungan</h2>
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="border-b-2 border-slate-200 bg-slate-50">
              <th class="py-3 pl-3 pr-4 text-left text-xs font-bold uppercase text-slate-500">#</th>
              <th class="py-3 pr-4 text-left text-xs font-bold uppercase text-slate-500">Nama / Lokasi</th>
              <th class="py-3 pr-4 text-left text-xs font-bold uppercase text-slate-500">Alamat</th>
              <th class="py-3 pr-4 text-right text-xs font-bold uppercase text-slate-500">Jarak Dari Sblm</th>
              <th class="py-3 pr-4 text-right text-xs font-bold uppercase text-slate-500">Waktu ke Sini</th>
              <th class="py-3 pr-3 text-right text-xs font-bold uppercase text-slate-500">Total Menit</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="titik in printData.detail"
              :key="titik.visitasi_rute_detail_id"
              :class="titik.tipe_titik === 'titik_awal' || titik.tipe_titik === 'kembali' ? 'bg-primary/5' : ''"
            >
              <td class="py-3 pl-3 pr-4">
                <div
                  class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-black"
                  :class="titik.tipe_titik !== 'mahasiswa' ? 'bg-primary text-white' : 'bg-slate-200 text-slate-700'"
                >
                  {{ titik.tipe_titik === 'titik_awal' ? '★' : titik.tipe_titik === 'kembali' ? '↩' : titik.urutan_kunjungan }}
                </div>
              </td>
              <td class="py-3 pr-4">
                <p class="font-semibold text-slate-800">{{ titik.mahasiswa?.nama || titik.label || '-' }}</p>
                <p class="text-xs text-slate-500">
                  {{ titik.latitude?.toFixed(6) }}, {{ titik.longitude?.toFixed(6) }}
                </p>
              </td>
              <td class="py-3 pr-4">
                <p class="text-xs text-slate-600">{{ titik.mahasiswa?.alamat || '-' }}</p>
              </td>
              <td class="py-3 pr-4 text-right font-mono text-xs font-semibold text-slate-700">
                {{ titik.urutan_kunjungan > 0 ? `${titik.jarak_dari_sebelumnya_km} km` : '-' }}
              </td>
              <td class="py-3 pr-4 text-right font-mono text-xs font-semibold text-slate-700">
                {{ titik.urutan_kunjungan > 0 ? `${titik.estimasi_ke_sini_menit} mnt` : '-' }}
              </td>
              <td class="py-3 pr-3 text-right font-mono text-xs font-bold text-primary">
                {{ titik.estimasi_kumulatif_menit }} mnt
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-slate-200 bg-slate-50">
              <td colspan="3" class="py-3 pl-3 text-xs font-bold text-slate-600">TOTAL</td>
              <td class="py-3 pr-4 text-right font-mono text-sm font-black text-primary">
                {{ printData.rute.total_jarak_km }} km
              </td>
              <td colspan="2" class="py-3 pr-3 text-right font-mono text-sm font-black text-primary">
                {{ printData.rute.total_estimasi_menit }} menit
              </td>
            </tr>
          </tfoot>
        </table>
      </section>

      <!-- Footer dokumen -->
      <div class="mt-10 border-t border-slate-200 pt-6 text-center">
        <p class="text-xs text-slate-400">
          Dokumen ini digenerate otomatis oleh sistem GeoVisit PJJ IT.
          Data jarak dan waktu merupakan estimasi berdasarkan engine routing OSRM.
        </p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({ layout: false })

const route = useRoute()
const { $api } = useNuxtApp()

const isLoading = ref(true)
const errorMessage = ref('')
const printData = ref(null)

const rencanaId = route.params.rencanaId
const ruteId = route.params.ruteId

const formatDateTime = (v) => {
  const d = new Date(v)
  if (!v || isNaN(d)) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  }).format(d)
}

const fetchPrintData = async () => {
  try {
    const res = await $api.get(`/visitasi/${rencanaId}/rute/${ruteId}/print-data`)
    printData.value = res.data?.data || null
  } catch (err) {
    errorMessage.value = err?.response?.data?.message || 'Gagal memuat data rute.'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchPrintData)
</script>

<style>
@media print {
  .no-print {
    display: none !important;
  }

  body {
    font-size: 11pt;
  }

  @page {
    margin: 1.5cm;
    size: A4;
  }
}
</style>
