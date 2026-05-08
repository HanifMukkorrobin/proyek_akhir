<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-emerald-950/40 backdrop-blur-sm" @click="handleClose" />

      <form class="relative flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl" @submit.prevent="submitForm">
        <div class="flex items-start justify-between gap-4 border-b border-outline-variant px-6 py-5">
          <div>
            <h2 class="text-title-lg font-bold text-on-surface">{{ title }}</h2>
            <p class="mt-1 text-body-sm text-on-surface-variant">{{ description }}</p>
          </div>
          <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low" type="button" :disabled="isSubmitting" @click="handleClose">
            <Icon icon="solar:close-circle-bold-duotone" class="h-6 w-6" />
          </button>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
          <div v-if="errorMessage" class="rounded-xl border border-error/20 bg-error-container/40 px-4 py-3 text-body-sm text-error">
            {{ errorMessage }}
          </div>

          <div v-if="successMessage" class="rounded-xl border border-outline-variant/60 bg-primary-fixed/15 px-4 py-3 text-body-sm text-primary">
            {{ successMessage }}
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Nama</span>
              <input
                v-model="form.nama"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="text"
                autocomplete="name"
                required
              >
            </label>

            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Username</span>
              <input
                v-model="form.username"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="text"
                autocomplete="username"
                required
              >
            </label>
          </div>

          <label class="block">
            <span class="text-label-caps uppercase text-on-surface-variant">Email</span>
            <input
              v-model="form.email"
              class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="email"
              autocomplete="email"
              placeholder="opsional"
            >
          </label>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Usergroup</span>
              <select
                v-model="form.usergroup_kode"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                required
              >
                <option v-for="option in usergroupOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </label>

            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Status</span>
              <select
                v-model="statusValue"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              >
                <option value="true">Aktif</option>
                <option value="false">Nonaktif</option>
              </select>
            </label>
          </div>

          <label v-if="form.usergroup_kode === 'mahasiswa'" class="block">
            <span class="text-label-caps uppercase text-on-surface-variant">Mahasiswa ID</span>
            <input
              v-model="form.mahasiswa_id"
              class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
              type="text"
              placeholder="opsional, isi jika user ditautkan ke mahasiswa"
            >
          </label>

          <div v-if="isCreate" class="grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Password</span>
              <input
                v-model="form.password"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="password"
                autocomplete="new-password"
                required
              >
            </label>

            <label class="block">
              <span class="text-label-caps uppercase text-on-surface-variant">Konfirmasi Password</span>
              <input
                v-model="form.confirm_password"
                class="mt-2 h-12 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                type="password"
                autocomplete="new-password"
                required
              >
            </label>
          </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-outline-variant bg-surface-container-low px-6 py-4 sm:flex-row sm:justify-end">
          <button class="rounded-xl bg-surface-container-high px-5 py-3 text-body-sm font-semibold text-on-surface transition hover:bg-surface-container-highest disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="isSubmitting" @click="handleClose">
            Batal
          </button>
          <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-body-sm font-bold text-on-primary shadow-sm transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isSubmitting">
            <Icon :icon="isSubmitting ? 'solar:refresh-bold-duotone' : 'solar:diskette-bold-duotone'" class="h-5 w-5" :class="{ 'animate-spin': isSubmitting }" />
            {{ submitLabel }}
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  mode: {
    type: String,
    default: 'create'
  },
  user: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])
const { $api } = useNuxtApp()

const usergroupOptions = [
  { label: 'Administrator', value: 'admin' },
  { label: 'Dosen', value: 'dosen' },
  { label: 'Mahasiswa', value: 'mahasiswa' }
]

const form = reactive({
  nama: '',
  username: '',
  email: '',
  usergroup_kode: 'admin',
  mahasiswa_id: '',
  password: '',
  confirm_password: ''
})

const statusValue = ref('true')
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const isCreate = computed(() => props.mode === 'create')
const title = computed(() => isCreate.value ? 'Tambah User' : 'Edit User')
const description = computed(() => isCreate.value ? 'Buat akun pengguna baru.' : 'Perbarui profil, usergroup, dan status akun.')
const submitLabel = computed(() => isCreate.value ? 'Tambah User' : 'Simpan Perubahan')

const resetForm = () => {
  form.nama = props.user?.nama || ''
  form.username = props.user?.username || ''
  form.email = props.user?.email || ''
  form.usergroup_kode = props.user?.usergroup?.kode || props.user?.role || 'admin'
  form.mahasiswa_id = props.user?.mahasiswa_id || ''
  form.password = ''
  form.confirm_password = ''
  statusValue.value = props.user?.status_aktif === false ? 'false' : 'true'
  errorMessage.value = ''
  successMessage.value = ''
}

const trimOrNull = (value) => {
  const normalized = String(value || '').trim()

  return normalized === '' ? null : normalized
}

const formatApiError = (error) => {
  const errors = error?.response?.data?.errors

  if (errors && typeof errors === 'object') {
    const firstValue = Object.values(errors)[0]

    if (Array.isArray(firstValue) && firstValue.length > 0) {
      return firstValue[0]
    }

    if (typeof firstValue === 'string') {
      return firstValue
    }
  }

  return error?.response?.data?.message || error?.message || 'Terjadi kesalahan saat menyimpan user.'
}

const validateForm = () => {
  if (trimOrNull(form.nama) === null) {
    return 'Nama wajib diisi.'
  }

  if (trimOrNull(form.username) === null) {
    return 'Username wajib diisi.'
  }

  if (trimOrNull(form.usergroup_kode) === null) {
    return 'Usergroup wajib dipilih.'
  }

  if (!isCreate.value) {
    return ''
  }

  if (form.password.length < 8) {
    return 'Password minimal 8 karakter.'
  }

  if (form.password !== form.confirm_password) {
    return 'Konfirmasi password tidak sama.'
  }

  return ''
}

const buildPayload = () => {
  const payload = {
    nama: trimOrNull(form.nama),
    username: trimOrNull(form.username),
    email: trimOrNull(form.email),
    usergroup_kode: form.usergroup_kode,
    mahasiswa_id: form.usergroup_kode === 'mahasiswa' ? trimOrNull(form.mahasiswa_id) : null,
    status_aktif: statusValue.value === 'true'
  }

  if (isCreate.value) {
    payload.password = form.password
  }

  return payload
}

const submitForm = async () => {
  const validationError = validateForm()

  if (validationError !== '') {
    errorMessage.value = validationError
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    if (isCreate.value) {
      await $api.post('/users', buildPayload())
      successMessage.value = 'User berhasil ditambahkan.'
    } else {
      await $api.patch(`/users/${props.user.user_id}`, buildPayload())
      successMessage.value = 'User berhasil diperbarui.'
    }

    setTimeout(() => emit('saved'), 250)
  } catch (error) {
    errorMessage.value = formatApiError(error)
  } finally {
    isSubmitting.value = false
  }
}

const handleClose = () => {
  if (isSubmitting.value) {
    return
  }

  emit('close')
}

watch(() => props.user, resetForm, { immediate: true })
</script>
