<template>
  <div ref="containerRef" class="h-full w-full" />
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  options: {
    type: Object,
    required: true
  },
  constructorType: {
    type: String,
    default: 'chart'
  }
})

const containerRef = ref(null)
let chartInstance
let highchartsLib

const createChart = async () => {
  if (!containerRef.value) {
    return
  }

  if (!highchartsLib) {
    const mod = await import('highcharts')
    highchartsLib = mod.default || mod
  }

  const constructor = highchartsLib[props.constructorType] || highchartsLib.chart

  if (chartInstance) {
    chartInstance.destroy()
  }

  chartInstance = constructor(containerRef.value, props.options)
}

onMounted(async () => {
  await createChart()
})

watch(
  () => props.options,
  async () => {
    if (chartInstance) {
      chartInstance.update(props.options, true, true)
      return
    }

    await createChart()
  },
  { deep: true }
)

onBeforeUnmount(() => {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
})
</script>
