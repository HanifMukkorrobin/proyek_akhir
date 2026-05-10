<template>
  <div
    class="min-h-screen bg-background font-sans text-on-surface transition-colors duration-300 dark:bg-forest-950 dark:text-inverse-on-surface"
  >
    <Head>
      <Title>GeoVisit PJJ IT | Visualisasi 3D dan Optimasi Rute</Title>
    </Head>

    <header
      class="sticky top-0 z-50 border-b border-outline-variant/60 bg-white/95 shadow-[0_8px_30px_rgba(32,137,58,0.04)] backdrop-blur-md dark:border-emerald-900/50 dark:bg-forest-950/95"
    >
      <nav
        class="mx-auto flex w-full max-w-7xl items-center justify-between px-5 py-4 md:px-8"
      >
        <NuxtLink class="flex items-center gap-2" to="/">
          <Icon
            icon="solar:map-point-wave-bold-duotone"
            class="h-6 w-6 text-primary dark:text-primary"
          />
          <span class="text-sm font-extrabold text-primary sm:text-xl"
            >GeoVisit PJJ IT</span
          >
        </NuxtLink>

        <div class="hidden items-center gap-8 md:flex">
          <a
            class="border-b-2 border-primary pb-1 text-sm font-bold text-primary"
            href="#home"
            >Home</a
          >
          <a
            class="text-sm font-medium text-on-surface-variant transition hover:text-primary dark:text-emerald-100/70 dark:hover:text-primary"
            href="#features"
            >Features</a
          >
          <a
            class="text-sm font-medium text-on-surface-variant transition hover:text-primary dark:text-emerald-100/70 dark:hover:text-primary"
            href="#about"
            >About</a
          >
        </div>

        <div class="flex items-center gap-3">
          <button
            class="flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition hover:bg-surface-container-high dark:text-emerald-100"
            type="button"
            aria-label="Toggle theme"
            @click="toggleTheme"
          >
            <Icon
              v-if="theme === 'dark'"
              icon="solar:sun-2-linear"
              class="h-5 w-5"
            />
            <Icon v-else icon="solar:moon-linear" class="h-5 w-5" />
          </button>
          <NuxtLink
            class="rounded-full bg-primary px-5 py-2 text-sm font-semibold text-on-primary shadow-md shadow-emerald-900/10 transition hover:bg-primary-container"
            to="/auth/login"
          >
            Mulai
          </NuxtLink>
        </div>
      </nav>
    </header>

    <main id="home">
      <section class="relative isolate overflow-hidden">
        <div
          class="absolute inset-0 bg-[radial-gradient(circle_at_2px_2px,rgba(32,137,58,0.12)_1px,transparent_0)] bg-[length:40px_40px]"
        />
        <div
          class="absolute inset-0 bg-gradient-to-b from-transparent via-background/75 to-background dark:via-forest-950/85 dark:to-forest-950"
        />

        <div
          class="relative mx-auto grid min-h-[760px] w-full max-w-7xl items-center gap-12 px-5 py-16 md:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:py-20"
        >
          <div class="max-w-2xl space-y-8">
            <div
              class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1"
            >
              <Icon
                icon="solar:stars-bold-duotone"
                class="h-4 w-4 text-primary"
              />
              <span class="text-label-caps uppercase text-primary"
                >Geospatial Innovation 2026</span
              >
            </div>

            <div class="space-y-5">
              <h1
                class="max-w-xl text-4xl font-black leading-[1.05] text-on-surface dark:text-white sm:text-5xl lg:text-[56px]"
              >
                3D Data Visualization &amp;
                <span class="text-primary dark:text-primary"
                  >Route Optimization</span
                >
              </h1>
              <p
                class="max-w-lg text-body-md text-on-surface-variant dark:text-emerald-100/75"
              >
                Transformasikan data geografis mahasiswa PJJ IT menjadi wawasan
                visual yang mendalam. GeoVisit menyediakan simulasi rute presisi
                tinggi dan pemetaan 3D interaktif untuk visitasi akademik yang
                lebih efisien.
              </p>
            </div>

            <div class="flex flex-wrap gap-4 pt-2">
              <NuxtLink
                class="inline-flex items-center gap-2 rounded-full bg-primary px-7 py-4 text-title-lg text-on-primary shadow-lg shadow-primary/20 transition hover:bg-primary-container"
                to="/auth/login"
              >
                Mulai Eksplorasi
                <Icon icon="solar:arrow-right-linear" class="h-5 w-5" />
              </NuxtLink>
            </div>
          </div>

          <div class="relative">
            <div
              class="overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-lifted dark:border-emerald-900/70 dark:bg-forest-900"
            >
              <img
                class="aspect-[4/3] w-full object-cover"
                alt="3D geospatial topography visualization"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuB5Ta5WxyLICnjB_8ygf-kivhw_WGFPdG5Ukt88DtpQKKbx8C7zUWx8hbPMju3YiBhnVYyOIyJ8hVFNrs47MknXk5aqU-jPsJkTpJPfzD_BrDWiEmr73GkS_rKMTU4_KzCDrkkwqwVC57tkAdx7Zq-tEnNSZOUqPpZXZ6mcDgYr1oY6HTu9XpIC-gTij5vVcrCeBG0-bGCHesbVjRcMhAs4XruDEzCfKpUKFh_TbBPqnl2gt_d26Y7_rEwUumbIZKSQJwL973ADmNw"
              />

              <div
                class="absolute bottom-6 left-6 right-6 flex items-center justify-between gap-4 rounded-xl border border-white/30 bg-white/90 p-4 shadow-lg backdrop-blur-md dark:border-emerald-800/70 dark:bg-forest-950/85"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/15 text-primary"
                  >
                    <Icon icon="solar:map-point-bold" class="h-5 w-5" />
                  </div>
                  <div>
                    <p
                      class="text-label-caps uppercase text-on-surface-variant dark:text-emerald-100/65"
                    >
                      Coordinates
                    </p>
                    <p
                      class="font-mono text-xs text-on-surface dark:text-emerald-50"
                    >
                      6.2088&deg; S, 106.8456&deg; E
                    </p>
                  </div>
                </div>
                <div class="h-8 w-px bg-outline-variant" />
                <div class="text-right">
                  <p
                    class="text-label-caps uppercase text-on-surface-variant dark:text-emerald-100/65"
                  >
                    Altitude
                  </p>
                  <p class="font-mono text-xs font-bold text-primary">
                    124.5 m
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section
        id="features"
        class="bg-surface-container-low py-24 dark:bg-forest-900"
      >
        <div class="mx-auto max-w-7xl px-5 md:px-8">
          <div class="mx-auto mb-16 max-w-2xl space-y-4 text-center">
            <h2
              class="text-headline-md font-bold text-on-surface dark:text-white"
            >
              Fitur Unggulan GeoVisit
            </h2>
            <p
              class="text-body-md text-on-surface-variant dark:text-emerald-100/70"
            >
              Solusi geospatial terintegrasi untuk analisis data, visualisasi
              spasial, dan efisiensi logistik dalam satu platform.
            </p>
          </div>

          <div class="grid gap-8 md:grid-cols-3">
            <article
              v-for="feature in features"
              :key="feature.title"
              class="group rounded-2xl border border-outline-variant/40 bg-white p-8 shadow-[0_4px_20px_rgba(0,0,0,0.03)] transition hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/5 dark:border-emerald-800/70 dark:bg-forest-950"
            >
              <div
                class="mb-6 flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary transition group-hover:scale-105"
              >
                <Icon :icon="feature.icon" class="h-7 w-7" />
              </div>
              <h3 class="mb-3 text-title-lg text-on-surface dark:text-white">
                {{ feature.title }}
              </h3>
              <p
                class="text-body-sm text-on-surface-variant dark:text-emerald-100/70"
              >
                {{ feature.description }}
              </p>
              <div class="mt-8 border-t border-outline-variant/30 pt-6">
                <a
                  class="inline-flex items-center gap-1 text-label-caps uppercase text-primary transition hover:gap-2"
                  href="#about"
                >
                  Pelajari Lebih Lanjut
                  <Icon icon="solar:arrow-right-linear" class="h-4 w-4" />
                </a>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section id="about" class="px-5 py-24 md:px-8">
        <div
          class="relative mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-primary p-10 text-center shadow-2xl md:p-20"
        >
          <div
            class="absolute inset-0 bg-[linear-gradient(45deg,rgba(255,255,255,0.05)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.05)_50%,rgba(255,255,255,0.05)_75%,transparent_75%,transparent)] bg-[length:60px_60px] opacity-20"
          />
          <div class="relative z-10 space-y-8">
            <h2
              class="mx-auto max-w-3xl text-4xl font-black leading-tight text-on-primary md:text-5xl"
            >
              Siap untuk memvisualisasikan data Anda masa kini?
            </h2>
            <p class="mx-auto max-w-xl text-body-md text-on-primary/80">
              Buka panel GeoVisit untuk memantau persebaran mahasiswa, mengelola
              data, dan menyiapkan simulasi rute visitasi.
            </p>
            <div
              class="flex flex-col items-center justify-center gap-4 pt-2 sm:flex-row"
            >
              <NuxtLink
                class="rounded-full bg-white px-10 py-4 text-title-lg text-primary shadow-xl transition hover:bg-surface-bright"
                to="/auth/login"
              >
                Masuk ke Panel
              </NuxtLink>
              <button
                class="rounded-full border border-white/20 bg-primary-container px-10 py-4 text-title-lg text-white transition hover:bg-white/10"
                type="button"
                @click="handleDashboardClick"
              >
                Buka Dashboard
              </button>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer
      class="border-t border-outline-variant/40 bg-stone-50 dark:border-emerald-900 dark:bg-forest-950"
    >
      <div
        class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-8 px-5 py-12 md:flex-row md:px-8"
      >
        <div class="text-center md:text-left">
          <p class="mb-2 text-sm font-bold text-stone-900 dark:text-stone-100">
            GeoVisit PJJ IT
          </p>
          <p class="font-mono text-xs uppercase text-stone-500">
            2026 GeoVisit PJJ IT. Nature-Tech Geospatial Solutions.
          </p>
        </div>
        <div class="flex flex-wrap justify-center gap-6 md:gap-8">
          <NuxtLink
            v-for="link in footerLinks"
            :key="link.label"
            class="font-mono text-xs uppercase text-stone-500 transition hover:text-primary"
            :to="link.to"
          >
            {{ link.label }}
          </NuxtLink>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "~/stores/auth";

const THEME_KEY = "geovisit-theme-mode";
const theme = ref("light");
const router = useRouter();
const authStore = useAuthStore();

const features = [
  {
    title: "Dashboard Persebaran Data",
    description:
      "Analisis persebaran titik data secara real-time dengan grafik intuitif dan filter parameter yang mendalam.",
    icon: "solar:chart-square-bold-duotone",
  },
  {
    title: "Peta 3D Interaktif",
    description:
      "Visualisasi terrain dan objek 3D dengan performa tinggi untuk perspektif spasial yang lebih akurat.",
    icon: "solar:earth-bold-duotone",
  },
  {
    title: "Simulasi Rute Efisien",
    description:
      "Optimasi rute visitasi dosen untuk menentukan jalur tercepat berdasarkan titik domisili mahasiswa.",
    icon: "solar:routing-2-bold-duotone",
  },
];

const footerLinks = [
  { label: "Dashboard", to: "/admin/dashboard" },
  { label: "3D Map", to: "/admin/dashboard" },
  { label: "Route Simulation", to: "/admin/log" },
  { label: "Privacy Policy", to: "/" },
];

const applyTheme = (value) => {
  theme.value = value;

  if (!import.meta.client) {
    return;
  }

  document.documentElement.setAttribute("data-theme", value);
  document.documentElement.classList.toggle("dark", value === "dark");
  document.documentElement.style.colorScheme = value;
  localStorage.setItem(THEME_KEY, value);
};

const toggleTheme = () => {
  applyTheme(theme.value === "light" ? "dark" : "light");
};

const handleDashboardClick = () => {
  authStore.initAuth();
  const role = authStore.user?.role;

  if (authStore.isAuthenticated && ["mahasiswa", "dosen"].includes(role)) {
    router.push("/admin/dashboard");
    return;
  }

  router.push("/auth/login");
};

onMounted(() => {
  const saved = localStorage.getItem(THEME_KEY);

  if (saved === "light" || saved === "dark") {
    applyTheme(saved);
    return;
  }

  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  applyTheme(prefersDark ? "dark" : "light");
});
</script>
