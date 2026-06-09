<template>
  <div class="relative h-full w-full">
    <!-- Cesium container -->
    <div ref="cesiumContainer" class="h-full w-full bg-slate-100 dark:bg-forest-900" />

    <!-- Status loading overlays -->
    <div
      v-if="!sceneReady"
      class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/40 text-white backdrop-blur-sm"
    >
      <div class="h-10 w-10 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent" />
      <p class="mt-4 text-sm font-semibold tracking-wide">Memuat Peta 3D Cesium...</p>
    </div>

    <!-- Map console control instructions -->
    <div
      v-if="sceneReady"
      class="absolute bottom-4 left-4 z-10 max-w-xs rounded-xl bg-white/90 p-3 text-[11px] font-medium text-slate-800 shadow-md backdrop-blur-sm dark:bg-forest-950/90 dark:text-emerald-100"
    >
      <p class="font-bold text-primary dark:text-emerald-400">Peta Simulasi Rute 3D</p>
      <ul class="mt-1.5 list-disc pl-4 space-y-0.5">
        <li>Klik di mana saja di peta untuk menetapkan koordinat <strong>Titik Awal Dosen</strong>.</li>
        <li>Rute ditunjukkan oleh garis hijau dan marker berangka.</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

useHead({
  link: [
    { rel: 'stylesheet', href: '/cesium/Widgets/widgets.css' }
  ]
})

const props = defineProps({
  ruteDetail: {
    type: Array,
    default: () => []
  },
  titikAwal: {
    type: Object,
    default: null
  },
  peserta: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['map-click-coordinates'])

const config = useRuntimeConfig()
const cesiumContainer = ref(null)
const sceneReady = ref(false)

let viewer = null
let Cesium = null
let clickHandler = null

// INDONESIA Center Focus
const INDONESIA_CENTER = { lat: -2.5489, lon: 118.0149, height: 4200000.0 }
const SURABAYA_CENTER = { lat: -7.2575, lon: 112.7521, height: 35000.0 }

// SVG Marker Data URLs
const startMarkerUrl = ref('')
const studentMarkerUrl = ref('')

const createStartMarkerSvg = () => {
  if (startMarkerUrl.value) return startMarkerUrl.value
  startMarkerUrl.value = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
      <circle cx="21" cy="21" r="15" fill="#2563eb" stroke="#ffffff" stroke-width="4"/>
      <path d="M21 12l7 7h-4v9h-6v-9h-4z" fill="#ffffff"/>
    </svg>
  `)
  return startMarkerUrl.value
}

const createStudentMarkerSvg = (number) => {
  return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
      <circle cx="21" cy="21" r="15" fill="#f59e0b" stroke="#ffffff" stroke-width="4"/>
      <text x="21" y="26" font-family="Inter, sans-serif" font-weight="900" font-size="15px" fill="#ffffff" text-anchor="middle">${number}</text>
    </svg>
  `)
}

// Polyline Decoder Helper
const decodePolyline = (encoded) => {
  if (!encoded) return []
  const points = []
  let index = 0
  const len = encoded.length
  let lat = 0
  let lng = 0
  while (index < len) {
    let b
    let shift = 0
    let result = 0
    do {
      b = encoded.charCodeAt(index++) - 63
      result |= (b & 0x1f) << shift
      shift += 5
    } while (b >= 0x20)
    const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1))
    lat += dlat
    shift = 0
    result = 0
    do {
      b = encoded.charCodeAt(index++) - 63
      result |= (b & 0x1f) << shift
      shift += 5
    } while (b >= 0x20)
    const dlng = ((result & 1) ? ~(result >> 1) : (result >> 1))
    lng += dlng
    points.push({ latitude: lat / 1e5, longitude: lng / 1e5 })
  }
  return points
}

// Load Cesium script asynchronously
const loadCesiumGlobal = () => {
  if (window.Cesium) return Promise.resolve(window.Cesium)
  window.CESIUM_BASE_URL = '/cesium'
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = '/cesium/Cesium.js'
    script.async = true
    script.onload = () => {
      if (window.Cesium) resolve(window.Cesium)
      else reject(new Error('Cesium global not loaded.'))
    }
    script.onerror = () => reject(new Error('Failed to load Cesium.js.'))
    document.head.appendChild(script)
  })
}

// Initialize Cesium Viewer
const initializeCesium = async () => {
  if (!import.meta.client || !cesiumContainer.value) return
  try {
    Cesium = await loadCesiumGlobal()
    Cesium.Ion.defaultAccessToken = config.public.cesiumIonToken

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
          preserveDrawingBuffer: false
        }
      }
    })

    viewer.resolutionScale = window.devicePixelRatio > 1 ? 0.82 : 1
    if ('msaaSamples' in viewer.scene) {
      viewer.scene.msaaSamples = 1
    }

    viewer.scene.globe.enableLighting = false
    viewer.scene.globe.depthTestAgainstTerrain = false
    viewer.scene.fog.enabled = false
    viewer.scene.highDynamicRange = false

    if (viewer.scene.postProcessStages?.bloom) {
      viewer.scene.postProcessStages.bloom.enabled = false
    }

    installClickHandler()
    sceneReady.value = true

    // Draw initial view
    drawRouteAndPoints()

    // Trigger viewer resize after CSS loads and layout stabilizes
    setTimeout(() => {
      if (viewer) {
        viewer.resize()
      }
    }, 200)
  } catch (err) {
    console.error('Cesium initialization failed:', err)
  }
}

// Capture left click on terrain or ellipsoid to capture coordinates
const installClickHandler = () => {
  if (!viewer || !Cesium) return
  clickHandler = new Cesium.ScreenSpaceEventHandler(viewer.scene.canvas)
  clickHandler.setInputAction((movement) => {
    const cartesian = viewer.camera.pickEllipsoid(movement.position, viewer.scene.globe.ellipsoid)
    if (!cartesian) return

    const cartographic = Cesium.Cartographic.fromCartesian(cartesian)
    const latitude = Cesium.Math.toDegrees(cartographic.latitude)
    const longitude = Cesium.Math.toDegrees(cartographic.longitude)

    emit('map-click-coordinates', { latitude, longitude })

    // Add visual feedback marker
    addStartPointFeedback(longitude, latitude)
  }, Cesium.ScreenSpaceEventType.LEFT_CLICK)
}

// Draw a temporary marker for coordinate feedback
const addStartPointFeedback = (lon, lat) => {
  if (!viewer || !Cesium) return
  
  // Remove existing click feedback if any
  const existingFeedback = viewer.entities.getById('click-feedback')
  if (existingFeedback) {
    viewer.entities.remove(existingFeedback)
  }

  viewer.entities.add({
    id: 'click-feedback',
    name: 'Lokasi Terpilih (Titik Awal)',
    position: Cesium.Cartesian3.fromDegrees(lon, lat, 100),
    billboard: {
      image: createStartMarkerSvg(),
      width: 38,
      height: 38,
      verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
      disableDepthTestDistance: Number.POSITIVE_INFINITY
    },
    label: {
      text: 'Titik Awal Baru',
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
  })

  viewer.scene.requestRender()
}

// Draw the simulation elements
const drawRouteAndPoints = () => {
  if (!viewer || !Cesium) return

  // Suspend changes to minimize updates
  viewer.entities.suspendEvents()
  viewer.entities.removeAll()

  const positionsToFit = []

  // 1. Render Titik Awal
  if (props.titikAwal && props.titikAwal.latitude && props.titikAwal.longitude) {
    const lat = Number(props.titikAwal.latitude)
    const lon = Number(props.titikAwal.longitude)
    const pos = Cesium.Cartesian3.fromDegrees(lon, lat, 100)
    positionsToFit.push(pos)

    viewer.entities.add({
      id: 'titik-awal-dosen',
      name: props.titikAwal.label || 'Titik Awal Dosen',
      position: pos,
      billboard: {
        image: createStartMarkerSvg(),
        width: 40,
        height: 40,
        verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
        disableDepthTestDistance: Number.POSITIVE_INFINITY
      },
      label: {
        text: props.titikAwal.label || 'Titik Awal Dosen',
        font: '700 12px Inter, sans-serif',
        fillColor: Cesium.Color.WHITE,
        outlineColor: Cesium.Color.fromCssColorString('#1e3a8a'),
        outlineWidth: 4,
        style: Cesium.LabelStyle.FILL_AND_OUTLINE,
        pixelOffset: new Cesium.Cartesian2(0, -44),
        showBackground: true,
        backgroundColor: Cesium.Color.fromCssColorString('#1d4ed8').withAlpha(0.8)
      }
    })
  }

  // 2. Render Rute & Detail Kunjungan jika ada hasil simulasi
  if (props.ruteDetail && props.ruteDetail.length > 0) {
    let flatPath = []

    props.ruteDetail.forEach((detail, index) => {
      const lat = Number(detail.latitude)
      const lon = Number(detail.longitude)
      if (isNaN(lat) || isNaN(lon)) return

      const pos = Cesium.Cartesian3.fromDegrees(lon, lat, 50)
      positionsToFit.push(pos)

      // Hanya render marker mahasiswa jika tipe_titik = 'mahasiswa'
      if (detail.tipe_titik === 'mahasiswa') {
        const studentName = detail.mahasiswa?.nama || detail.label || `Tujuan ${index}`
        viewer.entities.add({
          id: `visit-stop-${detail.visitasi_rute_detail_id || index}`,
          name: studentName,
          position: pos,
          billboard: {
            image: createStudentMarkerSvg(detail.urutan_kunjungan),
            width: 36,
            height: 36,
            verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
            disableDepthTestDistance: Number.POSITIVE_INFINITY
          },
          label: {
            text: `${detail.urutan_kunjungan}. ${studentName}`,
            font: '700 11px Inter, sans-serif',
            fillColor: Cesium.Color.WHITE,
            outlineColor: Cesium.Color.fromCssColorString('#78350f'),
            outlineWidth: 4,
            style: Cesium.LabelStyle.FILL_AND_OUTLINE,
            pixelOffset: new Cesium.Cartesian2(0, -40),
            showBackground: true,
            backgroundColor: Cesium.Color.fromCssColorString('#92400e').withAlpha(0.8)
          }
        })
      }

      // Decode geometri_polyline
      if (detail.geometri_polyline) {
        const decodedPoints = decodePolyline(detail.geometri_polyline)
        decodedPoints.forEach(p => {
          flatPath.push(p.longitude, p.latitude)
        })
      } else {
        // Fallback straight lines
        flatPath.push(lon, lat)
      }
    })

    // Render polyline
    if (flatPath.length >= 4) {
      viewer.entities.add({
        id: 'route-polyline',
        polyline: {
          positions: Cesium.Cartesian3.fromDegreesArray(flatPath),
          width: 5,
          material: Cesium.Color.fromCssColorString('#10b981'), // Emerald Green
          clampToGround: true
        }
      })
    }
  } else {
    // 3. Jika belum ada rute, render marker peserta saat ini (tahap persiapan)
    props.peserta.forEach((pes, index) => {
      const mhs = pes.mahasiswa
      if (!mhs) return
      const lat = Number(mhs.latitude)
      const lon = Number(mhs.longitude)
      if (isNaN(lat) || isNaN(lon)) return

      const pos = Cesium.Cartesian3.fromDegrees(lon, lat, 50)
      positionsToFit.push(pos)

      viewer.entities.add({
        id: `peserta-prep-${pes.visitasi_peserta_id || index}`,
        name: mhs.nama,
        position: pos,
        billboard: {
          image: createStudentMarkerSvg(index + 1),
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
      })
    })
  }

  // Resume events and fly to fit
  viewer.entities.resumeEvents()

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
    )
  } else {
    // Zoom to Surabaya / PENS by default
    viewer.camera.flyTo({
      destination: Cesium.Cartesian3.fromDegrees(
        SURABAYA_CENTER.lon,
        SURABAYA_CENTER.lat,
        SURABAYA_CENTER.height
      ),
      duration: 1.5
    })
  }

  viewer.scene.requestRender()
}

// Watchers for reactive updates
watch(() => props.titikAwal, () => {
  if (sceneReady.value) drawRouteAndPoints()
}, { deep: true })

watch(() => props.ruteDetail, () => {
  if (sceneReady.value) drawRouteAndPoints()
}, { deep: true })

watch(() => props.peserta, () => {
  if (sceneReady.value) drawRouteAndPoints()
}, { deep: true })

onMounted(() => {
  initializeCesium()
})

onBeforeUnmount(() => {
  if (clickHandler) {
    clickHandler.destroy()
  }
  if (viewer) {
    viewer.destroy()
  }
})
</script>
