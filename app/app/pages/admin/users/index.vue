<template>
  <div class="space-y-6">
    <Head>
      <Title>Manajemen User | GeoVisit PJJ IT</Title>
    </Head>

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
      <div>
        <h1 class="text-headline-md font-black text-on-surface md:text-3xl">Manajemen User</h1>
        <p class="mt-2 text-body-sm text-on-surface-variant">Kelola akun, usergroup, status akses, dan reset password pengguna.</p>
      </div>
      <button
        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-6 py-3 text-body-sm font-semibold text-on-primary shadow-lg shadow-primary/20 transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
        type="button"
        :disabled="isLoading"
        @click.stop.prevent="openCreateModal"
      >
        <Icon icon="solar:add-circle-bold-duotone" class="h-5 w-5" />
        Tambah User
      </button>
    </section>

    <section class="overflow-hidden rounded-2xl border border-outline-variant/35 bg-surface-container-lowest shadow-[0_4px_20px_-4px_rgba(32,137,58,0.05)]">
      <div class="space-y-4 border-b border-outline-variant/35 px-6 py-4">
        <div class="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_auto_auto] lg:items-center">
          <label class="relative block">
            <Icon icon="solar:magnifer-linear" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-on-surface-variant" />
            <input
              v-model="searchInput"
              class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest pl-12 pr-4 text-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="search"
              placeholder="Cari nama, username, email, atau usergroup..."
            >
          </label>

          <select
            v-model="statusFilter"
            class="h-12 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-medium text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
          >
            <option value="">Semua Status</option>
            <option value="true">Aktif</option>
            <option value="false">Nonaktif</option>
          </select>

          <button
            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm font-semibold text-on-surface-variant transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isLoading"
            @click="refreshUsers"
          >
            <Icon icon="solar:refresh-bold-duotone" class="h-5 w-5" :class="{ 'animate-spin': isLoading }" />
            Refresh
          </button>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="filter in roleFilters"
            :key="filter.value"
            class="rounded-lg px-4 py-2 text-body-sm transition"
            :class="roleFilter === filter.value ? 'bg-surface-container-high font-semibold text-on-surface' : 'text-on-surface-variant hover:bg-surface-container-low'"
            type="button"
            @click="setRoleFilter(filter.value)"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mx-6 mt-4 rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
        {{ errorMessage }}
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[920px] border-collapse text-left">
          <thead>
            <tr class="bg-surface-container-low">
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Nama</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Username</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Email</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Usergroup</th>
              <th class="px-6 py-4 text-label-caps uppercase text-on-surface-variant">Status</th>
              <th class="px-6 py-4 text-right text-label-caps uppercase text-on-surface-variant">Actions</th>
            </tr>
          </thead>
          <tbody v-if="isLoading" class="divide-y divide-outline-variant/20">
            <tr v-for="index in 5" :key="index">
              <td v-for="column in 6" :key="column" class="px-6 py-4">
                <div class="h-5 animate-pulse rounded bg-surface-container-high" :class="column === 6 ? 'ml-auto w-24' : 'w-32'" />
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="users.length > 0" class="divide-y divide-outline-variant/20">
            <tr v-for="user in users" :key="user.user_id" class="transition hover:bg-surface-container-low/60">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-fixed/20 text-sm font-bold text-primary">{{ user.initial }}</div>
                  <p class="text-body-sm font-semibold text-on-surface">{{ user.nama || '-' }}</p>
                </div>
              </td>
              <td class="px-6 py-4 text-body-sm font-semibold text-on-surface">{{ user.username || '-' }}</td>
              <td class="px-6 py-4 text-body-sm text-on-surface-variant">{{ user.email || '-' }}</td>
              <td class="px-6 py-4">
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase" :class="user.usergroupClass">{{ user.usergroupKode }}</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2" :class="user.statusClass">
                  <span class="h-2 w-2 rounded-full" :class="user.dotClass" />
                  <span class="text-xs font-bold uppercase">{{ user.statusLabel }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center justify-end gap-2">
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary" type="button" title="Edit user" aria-label="Edit user" @click.stop.prevent="openEditModal(user.raw)">
                    <Icon icon="solar:pen-bold-duotone" class="h-5 w-5" />
                  </button>
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-primary transition hover:bg-surface-container-low hover:text-primary-container" type="button" title="Reset password" aria-label="Reset password" @click.stop.prevent="selectedResetUser = user.raw">
                    <Icon icon="solar:lock-keyhole-bold-duotone" class="h-5 w-5" />
                  </button>
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-error transition hover:bg-error-container/50" type="button" title="Hapus user" aria-label="Hapus user" @click.stop.prevent="selectedDeleteUser = user.raw">
                    <Icon icon="solar:trash-bin-trash-bold-duotone" class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="px-6 py-14 text-center">
                <div class="mx-auto flex max-w-sm flex-col items-center">
                  <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                    <Icon icon="solar:user-cross-bold-duotone" class="h-6 w-6" />
                  </div>
                  <p class="text-body-md font-semibold text-on-surface">Tidak ada user ditemukan</p>
                  <p class="mt-1 text-body-sm text-on-surface-variant">Ubah filter atau tambahkan user baru.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col justify-between gap-4 border-t border-outline-variant/35 bg-surface-container-low px-6 py-4 md:flex-row md:items-center">
        <p class="text-body-sm text-on-surface-variant">{{ paginationLabel }}</p>
        <div class="flex items-center gap-2">
          <button
            class="flex h-9 w-9 items-center justify-center rounded border border-outline-variant text-on-surface-variant transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-40"
            type="button"
            :disabled="isLoading || pagination.halaman_sekarang <= 1"
            @click="goToPage(pagination.halaman_sekarang - 1)"
          >
            <Icon icon="solar:alt-arrow-left-linear" class="h-4 w-4" />
          </button>
          <span class="px-2 text-body-sm font-semibold text-on-surface">{{ pagination.halaman_sekarang }} / {{ pagination.total_halaman }}</span>
          <button
            class="flex h-9 w-9 items-center justify-center rounded border border-outline-variant text-on-surface-variant transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-40"
            type="button"
            :disabled="isLoading || pagination.halaman_sekarang >= pagination.total_halaman"
            @click="goToPage(pagination.halaman_sekarang + 1)"
          >
            <Icon icon="solar:alt-arrow-right-linear" class="h-4 w-4" />
          </button>
        </div>
      </div>
    </section>

    <button class="fixed bottom-8 right-8 z-20 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-on-primary shadow-2xl transition hover:scale-105" type="button">
      <Icon icon="solar:headphones-round-sound-bold-duotone" class="h-7 w-7" />
    </button>

    <AdminUserFormModal
      v-if="userFormMode"
      :mode="userFormMode"
      :user="selectedFormUser"
      @close="closeUserForm"
      @saved="handleUserSaved"
    />
    <AdminUserResetModal
      v-if="selectedResetUser"
      :user="selectedResetUser"
      @close="selectedResetUser = null"
      @saved="handleUserSaved"
    />
    <AdminUserDeleteModal
      v-if="selectedDeleteUser"
      :user="selectedDeleteUser"
      @close="selectedDeleteUser = null"
      @deleted="handleUserSaved"
    />
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, onMounted, ref, watch } from 'vue'
import AdminUserDeleteModal from '~/components/admin/UserDeleteModal.vue'
import AdminUserFormModal from '~/components/admin/UserFormModal.vue'
import AdminUserResetModal from '~/components/admin/UserResetModal.vue'

definePageMeta({
  layout: 'admin'
})

const { $api } = useNuxtApp()

const roleFilters = [
  { label: 'Semua User', value: '' },
  { label: 'Administrator', value: 'admin' },
  { label: 'Dosen', value: 'dosen' },
  { label: 'Mahasiswa', value: 'mahasiswa' }
]

const users = ref([])
const isLoading = ref(false)
const errorMessage = ref('')
const searchInput = ref('')
const searchQuery = ref('')
const roleFilter = ref('')
const statusFilter = ref('')
const userFormMode = ref('')
const selectedFormUser = ref(null)
const selectedResetUser = ref(null)
const selectedDeleteUser = ref(null)
const pagination = ref({
  halaman_sekarang: 1,
  per_halaman: 10,
  total_data: 0,
  total_halaman: 1
})

let searchTimer = null

const paginationLabel = computed(() => {
  const total = pagination.value.total_data

  if (total === 0) {
    return 'Showing 0 users'
  }

  const start = ((pagination.value.halaman_sekarang - 1) * pagination.value.per_halaman) + 1
  const end = Math.min(start + users.value.length - 1, total)

  return `Showing ${start}-${end} of ${total} users`
})

const normalizeUser = (user) => {
  const kode = user.usergroup?.kode || user.role || '-'
  const isActive = Boolean(user.status_aktif)

  return {
    ...user,
    raw: user,
    initial: makeInitial(user.nama || user.username || '?'),
    usergroupKode: kode,
    usergroupClass: resolveUsergroupClass(kode),
    statusLabel: isActive ? 'Aktif' : 'Nonaktif',
    statusClass: isActive ? 'text-primary' : 'text-on-surface-variant',
    dotClass: isActive ? 'bg-primary' : 'bg-outline-variant'
  }
}

const makeInitial = (value) => {
  const words = String(value)
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)

  if (words.length === 0) {
    return '?'
  }

  return words.map((word) => word.charAt(0)).join('').toUpperCase()
}

const resolveUsergroupClass = (kode) => {
  const normalized = String(kode).toLowerCase()

  if (normalized === 'admin') {
    return 'bg-primary-fixed/20 text-primary'
  }

  if (normalized === 'dosen') {
    return 'bg-secondary-container text-on-secondary-container'
  }

  if (normalized === 'mahasiswa') {
    return 'bg-tertiary-fixed text-on-tertiary-fixed'
  }

  return 'bg-surface-container-high text-on-surface-variant'
}

const extractErrorMessage = (error, fallback = 'Terjadi kesalahan saat memuat data user.') => {
  return error?.response?.data?.message || error?.message || fallback
}

const fetchUsers = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const params = {
      page: pagination.value.halaman_sekarang,
      per_page: pagination.value.per_halaman
    }

    if (searchQuery.value.trim() !== '') {
      params.search = searchQuery.value.trim()
    }

    if (roleFilter.value !== '') {
      params.role = roleFilter.value
    }

    if (statusFilter.value !== '') {
      params.status_aktif = statusFilter.value
    }

    const response = await $api.get('/users', { params })
    const payload = response.data?.data || {}

    users.value = Array.isArray(payload.data) ? payload.data.map(normalizeUser) : []
    pagination.value = {
      halaman_sekarang: Number(payload.halaman_sekarang || 1),
      per_halaman: Number(payload.per_halaman || 10),
      total_data: Number(payload.total_data || 0),
      total_halaman: Number(payload.total_halaman || 1)
    }
  } catch (error) {
    users.value = []
    errorMessage.value = extractErrorMessage(error)
  } finally {
    isLoading.value = false
  }
}

const refreshUsers = () => {
  fetchUsers()
}

const goToPage = (page) => {
  const safePage = Math.min(Math.max(1, page), pagination.value.total_halaman || 1)

  if (safePage === pagination.value.halaman_sekarang) {
    return
  }

  pagination.value.halaman_sekarang = safePage
  fetchUsers()
}

const resetToFirstPageAndFetch = () => {
  pagination.value.halaman_sekarang = 1
  fetchUsers()
}

const setRoleFilter = (value) => {
  if (roleFilter.value === value) {
    return
  }

  roleFilter.value = value
}

const openCreateModal = () => {
  selectedFormUser.value = null
  userFormMode.value = 'create'
}

const openEditModal = (user) => {
  selectedFormUser.value = user
  userFormMode.value = 'edit'
}

const closeUserForm = () => {
  selectedFormUser.value = null
  userFormMode.value = ''
}

const handleUserSaved = () => {
  closeUserForm()
  selectedResetUser.value = null
  selectedDeleteUser.value = null
  fetchUsers()
}

watch(searchInput, (value) => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    searchQuery.value = value
  }, 350)
})

watch([searchQuery, roleFilter, statusFilter], resetToFirstPageAndFetch)

onMounted(() => {
  fetchUsers()
})
</script>
