<template>
  <div class="pl-5 relative before:absolute before:left-0 before:top-0 before:h-full before:w-[1px] before:bg-outline-variant">
    <div class="group relative flex items-center justify-between rounded-lg px-3 py-2 transition hover:bg-surface-container-low before:absolute before:left-[-20px] before:top-1/2 before:h-[1px] before:w-4 before:bg-outline-variant">
      <div class="flex items-center gap-3">
        <button 
          v-if="wilayah.is_have_child === 1" 
          class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-surface-container-high hover:bg-surface-container-highest transition-transform" 
          :class="{'rotate-90': isExpanded}"
          @click="toggleExpand"
        >
          <Icon icon="solar:alt-arrow-right-bold-duotone" class="h-4 w-4" />
        </button>
        <div v-else class="h-6 w-6 shrink-0"></div>
        <div>
          <span class="text-body-md font-semibold text-on-surface">{{ wilayah.nama }}</span>
          <span class="ml-2 text-label-sm text-on-surface-variant">({{ wilayah.wilayah_id }})</span>
        </div>
      </div>
      
      <div class="flex items-center gap-1 opacity-0 transition group-hover:opacity-100">
        <button class="flex h-8 w-8 items-center justify-center rounded-lg text-primary hover:bg-primary-container" title="Tambah Child" @click="$emit('add', wilayah)">
          <Icon icon="solar:add-circle-bold-duotone" class="h-5 w-5" />
        </button>
        <button class="flex h-8 w-8 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-high" title="Edit" @click="$emit('edit', wilayah)">
          <Icon icon="solar:pen-bold-duotone" class="h-4 w-4" />
        </button>
        <button class="flex h-8 w-8 items-center justify-center rounded-lg text-error hover:bg-error-container" title="Hapus" @click="$emit('delete', wilayah)">
          <Icon icon="solar:trash-bin-trash-bold-duotone" class="h-4 w-4" />
        </button>
      </div>
    </div>
    
    <div v-if="isExpanded && isLoading" class="pl-10 py-2">
      <div class="h-4 w-32 animate-pulse rounded bg-surface-container-high"></div>
    </div>
    
    <div v-if="isExpanded && !isLoading && children.length > 0" class="mt-1">
      <AdminWilayahTreeItem 
        v-for="child in children" 
        :key="child.wilayah_id" 
        :wilayah="child" 
        :refresh-trigger="refreshTrigger"
        @add="$emit('add', $event)" 
        @edit="$emit('edit', $event)" 
        @delete="$emit('delete', $event)" 
      />
    </div>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { ref, watch } from 'vue'

const props = defineProps({
  wilayah: {
    type: Object,
    required: true
  },
  refreshTrigger: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['add', 'edit', 'delete'])
const { $api } = useNuxtApp()

const isExpanded = ref(false)
const isLoading = ref(false)
const children = ref([])

const fetchChildren = async () => {
  isLoading.value = true
  try {
    const { data } = await $api.get(`/wilayah?parent_id=${props.wilayah.wilayah_id}`)
    children.value = data.data
  } catch (error) {
    console.error('Failed to fetch children', error)
  } finally {
    isLoading.value = false
  }
}

const toggleExpand = () => {
  isExpanded.value = !isExpanded.value
  if (isExpanded.value && children.value.length === 0) {
    fetchChildren()
  }
}

// Listen for refresh triggers from parent
watch(() => props.refreshTrigger, (trigger) => {
  if (!trigger) return
  
  if (trigger.action === 'refresh_node' && trigger.wilayah_id === props.wilayah.wilayah_id) {
    // If this node is expanded or we are told to refresh it, we refetch children
    if (isExpanded.value) {
      fetchChildren()
    } else {
      // Force expand and fetch
      isExpanded.value = true
      fetchChildren()
    }
  }
}, { deep: true })
</script>
