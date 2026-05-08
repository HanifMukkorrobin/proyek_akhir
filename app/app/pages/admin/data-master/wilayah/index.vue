<template>
  <div class="space-y-8">
    <Head>
      <Title>Manajemen Wilayah | GeoVisit PJJ IT</Title>
    </Head>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="flex items-center gap-3">
          <NuxtLink to="/admin/data-master" class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container-high text-on-surface-variant transition hover:bg-surface-container-highest">
            <Icon icon="solar:arrow-left-bold-duotone" class="h-5 w-5" />
          </NuxtLink>
          <h1 class="text-headline-md font-black text-on-surface md:text-3xl">Manajemen Wilayah</h1>
        </div>
        <p class="mt-2 text-body-sm text-on-surface-variant">Kelola data master hierarki wilayah (Provinsi, Kabupaten/Kota, Kecamatan, Desa).</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-label-lg font-bold text-on-primary shadow-sm transition hover:bg-primary-container"
          @click="openAddRootModal"
        >
          <Icon icon="solar:add-circle-bold-duotone" class="h-5 w-5" />
          Tambah Root Wilayah
        </button>
      </div>
    </div>

    <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm overflow-hidden">
      <div v-if="isLoadingRoot" class="flex items-center justify-center py-12">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
      
      <div v-else-if="rootNodes.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
          <Icon icon="solar:map-bold-duotone" class="h-8 w-8" />
        </div>
        <h3 class="text-title-md font-bold text-on-surface">Belum ada data wilayah</h3>
        <p class="mt-1 text-body-sm text-on-surface-variant">Silakan tambahkan root wilayah pertama.</p>
      </div>
      
      <div v-else class="space-y-1">
        <AdminWilayahTreeItem
          v-for="node in rootNodes"
          :key="node.wilayah_id"
          :wilayah="node"
          :refresh-trigger="refreshTrigger"
          @add="openAddChildModal"
          @edit="openEditModal"
          @delete="openDeleteModal"
        />
      </div>
    </div>

    <AdminWilayahFormModal
      v-if="showFormModal"
      :mode="formMode"
      :wilayah="selectedWilayah"
      :parent-wilayah="selectedParentWilayah"
      @close="closeModals"
      @saved="handleFormSaved"
    />

    <AdminWilayahDeleteModal
      v-if="showDeleteModal"
      :wilayah="selectedWilayah"
      @close="closeModals"
      @deleted="handleDeleted"
    />
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { onMounted, ref } from 'vue'

definePageMeta({
  layout: 'admin'
})

const { $api } = useNuxtApp()

const rootNodes = ref([])
const isLoadingRoot = ref(true)

const showFormModal = ref(false)
const showDeleteModal = ref(false)
const formMode = ref('create')
const selectedWilayah = ref(null)
const selectedParentWilayah = ref(null)

const refreshTrigger = ref(null)

const fetchRootNodes = async () => {
  isLoadingRoot.value = true
  try {
    const { data } = await $api.get('/wilayah')
    rootNodes.value = data.data
  } catch (error) {
    console.error('Failed to fetch root nodes', error)
  } finally {
    isLoadingRoot.value = false
  }
}

const openAddRootModal = () => {
  formMode.value = 'create'
  selectedWilayah.value = null
  selectedParentWilayah.value = null
  showFormModal.value = true
}

const openAddChildModal = (parentWilayah) => {
  formMode.value = 'create'
  selectedWilayah.value = null
  selectedParentWilayah.value = parentWilayah
  showFormModal.value = true
}

const openEditModal = (wilayah) => {
  formMode.value = 'edit'
  selectedWilayah.value = wilayah
  selectedParentWilayah.value = null
  showFormModal.value = true
}

const openDeleteModal = (wilayah) => {
  selectedWilayah.value = wilayah
  showDeleteModal.value = true
}

const closeModals = () => {
  showFormModal.value = false
  showDeleteModal.value = false
  setTimeout(() => {
    selectedWilayah.value = null
    selectedParentWilayah.value = null
  }, 200)
}

const triggerRefresh = (parentId) => {
  if (!parentId) {
    fetchRootNodes()
  } else {
    refreshTrigger.value = {
      action: 'refresh_node',
      wilayah_id: parentId,
      timestamp: Date.now()
    }
  }
}

const handleFormSaved = () => {
  if (formMode.value === 'create') {
    triggerRefresh(selectedParentWilayah.value?.wilayah_id)
  } else {
    triggerRefresh(selectedWilayah.value?.parent_wilayah_id)
  }
  closeModals()
}

const handleDeleted = () => {
  triggerRefresh(selectedWilayah.value?.parent_wilayah_id)
  closeModals()
}

onMounted(() => {
  fetchRootNodes()
})
</script>
