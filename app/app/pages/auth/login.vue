<template>
  <div class="relative min-h-screen overflow-hidden bg-background font-sans text-on-surface">
    <Head>
      <Title>Login | GeoVisit PJJ IT</Title>
    </Head>

    <div class="absolute inset-0 bg-[radial-gradient(circle_at_2px_2px,rgba(32,137,58,0.12)_1px,transparent_0)] bg-[length:40px_40px]" />
    <div class="absolute inset-0 bg-gradient-to-b from-white/70 via-background/90 to-background" />

    <div class="relative z-10 flex min-h-screen flex-col">
      <header class="flex items-center justify-between px-5 py-5 md:px-8">
        <NuxtLink class="flex items-center gap-3" to="/">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-on-primary shadow-lg shadow-primary/20">
            <Icon icon="solar:map-point-wave-bold-duotone" class="h-6 w-6" />
          </div>
          <div>
            <p class="text-lg font-black text-primary">GeoVisit PJJ IT</p>
            <p class="text-[10px] font-semibold uppercase text-on-surface-variant">Precision Admin</p>
          </div>
        </NuxtLink>

        <button
          class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition hover:bg-surface-container-high"
          type="button"
          aria-label="Toggle theme"
          @click="toggleTheme"
        >
          <Icon v-if="theme === 'dark'" icon="solar:sun-2-linear" class="h-5 w-5" />
          <Icon v-else icon="solar:moon-linear" class="h-5 w-5" />
        </button>
      </header>

      <main class="flex flex-1 items-center justify-center px-5 py-10">
        <div class="grid w-full max-w-6xl gap-8 lg:grid-cols-[1fr_440px] lg:items-center">
          <section class="hidden overflow-hidden rounded-2xl border border-outline-variant/60 bg-white shadow-lifted lg:block">
            <div class="relative min-h-[620px]">
              <img
                class="absolute inset-0 h-full w-full object-cover"
                alt="3D terrain preview"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuB5Ta5WxyLICnjB_8ygf-kivhw_WGFPdG5Ukt88DtpQKKbx8C7zUWx8hbPMju3YiBhnVYyOIyJ8hVFNrs47MknXk5aqU-jPsJkTpJPfzD_BrDWiEmr73GkS_rKMTU4_KzCDrkkwqwVC57tkAdx7Zq-tEnNSZOUqPpZXZ6mcDgYr1oY6HTu9XpIC-gTij5vVcrCeBG0-bGCHesbVjRcMhAs4XruDEzCfKpUKFh_TbBPqnl2gt_d26Y7_rEwUumbIZKSQJwL973ADmNw"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/80 via-emerald-950/20 to-transparent" />
              <div class="absolute bottom-8 left-8 right-8 rounded-2xl border border-white/20 bg-white/90 p-6 shadow-lg backdrop-blur-md">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-label-caps uppercase text-primary">
                  <Icon icon="solar:shield-check-bold-duotone" class="h-4 w-4" />
                  Secure Operations
                </div>
                <h1 class="text-3xl font-black text-on-surface">Pantau visitasi dan persebaran mahasiswa dari satu panel.</h1>
                <p class="mt-3 text-body-sm text-on-surface-variant">Masuk untuk mengelola data spasial, melihat log simulasi, dan menyiapkan workflow rute akademik.</p>
              </div>
            </div>
          </section>

          <section class="mx-auto w-full max-w-md rounded-2xl border border-emerald-100 bg-white p-8 shadow-lifted">
            <div class="mb-8 text-center">
              <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <Icon icon="solar:map-point-wave-bold-duotone" class="h-10 w-10" />
              </div>
              <h1 class="text-3xl font-black text-primary">GeoVisit PJJ IT</h1>
              <p class="mt-2 text-body-sm text-on-surface-variant">Masuk ke panel administrasi dan simulasi.</p>
            </div>

            <form class="space-y-5" @submit.prevent="handleLogin">
              <div v-if="authError" class="flex gap-3 rounded-xl bg-error-container px-4 py-3 text-sm text-on-error-container">
                <Icon icon="solar:danger-triangle-bold-duotone" class="h-5 w-5 shrink-0" />
                <span>{{ authError }}</span>
              </div>

              <div>
                <label class="mb-2 block text-sm font-semibold text-on-surface-variant" for="identifier">Username atau Email</label>
                <div class="relative">
                  <Icon icon="solar:user-rounded-bold-duotone" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-outline" />
                  <input
                    id="identifier"
                    v-model="form.identifier"
                    class="h-12 w-full rounded-lg border border-outline-variant bg-surface-container-lowest pl-10 pr-4 text-body-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    type="text"
                    placeholder="username atau email"
                    required
                  />
                </div>
              </div>

              <div>
                <label class="mb-2 block text-sm font-semibold text-on-surface-variant" for="password">Kata Sandi</label>
                <div class="relative">
                  <Icon icon="solar:lock-keyhole-bold-duotone" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-outline" />
                  <input
                    id="password"
                    v-model="form.password"
                    class="h-12 w-full rounded-lg border border-outline-variant bg-surface-container-lowest pl-10 pr-11 text-body-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="password"
                    required
                  />
                  <button
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant transition hover:text-primary"
                    type="button"
                    @click="showPassword = !showPassword"
                  >
                    <Icon :icon="showPassword ? 'solar:eye-bold-duotone' : 'solar:eye-closed-bold-duotone'" class="h-5 w-5" />
                  </button>
                </div>
              </div>

              <div class="flex items-center justify-between">
                <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-on-surface-variant">
                  <input v-model="form.rememberMe" class="checkbox checkbox-sm rounded border-outline-variant text-primary" type="checkbox" />
                  Ingat saya
                </label>
                <a class="text-xs font-semibold text-primary hover:underline" href="#">Lupa sandi?</a>
              </div>

              <button
                class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary font-semibold text-on-primary shadow-lg shadow-primary/20 transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-70"
                type="submit"
                :disabled="isLoading"
              >
                <Icon v-if="isLoading" icon="line-md:loading-loop" class="h-5 w-5" />
                <span>{{ isLoading ? 'Memproses...' : 'Masuk ke Dashboard' }}</span>
              </button>
            </form>

            <div class="mt-8 text-center">
              <NuxtLink class="inline-flex items-center gap-2 text-xs font-semibold text-on-surface-variant transition hover:text-primary" to="/">
                <Icon icon="solar:alt-arrow-left-linear" class="h-4 w-4" />
                Kembali ke Beranda
              </NuxtLink>
            </div>
          </section>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import { storeToRefs } from 'pinia'
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const { isLoading, error: authError } = storeToRefs(authStore)

const THEME_KEY = 'geovisit-theme-mode'
const theme = ref('light')
const showPassword = ref(false)

const form = reactive({
  identifier: '',
  password: '',
  rememberMe: false
})

const applyTheme = (value) => {
  theme.value = value

  if (!import.meta.client) {
    return
  }

  document.documentElement.setAttribute('data-theme', value)
  document.documentElement.classList.toggle('dark', value === 'dark')
  document.documentElement.style.colorScheme = value
  localStorage.setItem(THEME_KEY, value)
}

const toggleTheme = () => {
  applyTheme(theme.value === 'light' ? 'dark' : 'light')
}

const handleLogin = async () => {
  authStore.error = ''

  try {
    const payload = await authStore.login(form.identifier, form.password, form.rememberMe)
    const role = payload?.user?.role || payload?.user?.usergroup?.kode
    router.push(role === 'admin' ? '/admin/dashboard' : '/dashboard/chart')
  } catch {
    // Error message is already set in auth store.
  }
}

onMounted(() => {
  const saved = localStorage.getItem(THEME_KEY)

  if (saved === 'light' || saved === 'dark') {
    applyTheme(saved)
  } else {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    applyTheme(prefersDark ? 'dark' : 'light')
  }

  authStore.initAuth()
  if (authStore.isAuthenticated) {
    const role = authStore.user?.role || authStore.user?.usergroup?.kode
    router.push(role === 'admin' ? '/admin/dashboard' : '/dashboard/chart')
  }
})
</script>
