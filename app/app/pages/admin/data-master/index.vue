<template>
  <div class="space-y-8">
    <Head>
      <Title>Data Master | GeoVisit PJJ IT</Title>
    </Head>

    <section>
      <h1 class="text-headline-md font-black text-on-surface md:text-3xl">Data Master</h1>
      <p class="mt-2 text-body-sm text-on-surface-variant">Visual placeholder for regional source data and spatial taxonomy management.</p>
    </section>

    <section class="grid grid-cols-1 gap-6 md:grid-cols-3">
      <NuxtLink v-for="item in masterCards" :key="item.label" :to="item.to" class="group block rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-6 shadow-sm transition hover:border-primary hover:shadow-md">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-primary-fixed/15 text-primary transition group-hover:bg-primary group-hover:text-white">
          <Icon :icon="item.icon" class="h-6 w-6" />
        </div>
        <p class="text-label-caps uppercase text-on-surface-variant">{{ item.label }}</p>
        <p class="mt-1 text-3xl font-black text-on-surface">{{ item.value }}</p>
        <p class="mt-3 text-body-sm text-on-surface-variant">{{ item.description }}</p>
      </NuxtLink>
    </section>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'

definePageMeta({
  layout: 'admin'
})

const masterCards = [
  {
    label: 'Hierarki Wilayah',
    value: 'Wilayah',
    description: 'Data master untuk provinsi, kabupaten/kota, kecamatan, dan desa.',
    icon: 'solar:map-point-bold-duotone',
    to: '/admin/data-master/wilayah'
  }
]
</script>
