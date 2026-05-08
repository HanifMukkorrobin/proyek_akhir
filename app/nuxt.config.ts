import { config as loadEnv } from 'dotenv'
import { copyFileSync, cpSync, existsSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'

const { parsed } = loadEnv()
const apiBase = parsed?.NUXT_PUBLIC_API_BASE || 'http://localhost:8080'
const cesiumIonToken = parsed?.NUXT_PUBLIC_CESIUM_ION_TOKEN || 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiIwOTAyMTkyYy0yZGVmLTQwMDEtODA1MC01OWNhZDM3ZDJjZmMiLCJpZCI6MjM0MTc1LCJpYXQiOjE3NTU1MTAyMjl9.T1wq47Z8irnzrB40nj7nsp56rg0-KfphxvnmLYDYNfQ'

const copyCesiumAssets = () => {
  const cesiumSource = join(process.cwd(), 'node_modules', 'cesium', 'Build', 'Cesium')
  const cesiumTarget = join(process.cwd(), 'public', 'cesium')

  if (!existsSync(cesiumSource)) {
    return
  }

  mkdirSync(cesiumTarget, { recursive: true })

  for (const directory of ['Assets', 'ThirdParty', 'Workers', 'Widgets']) {
    cpSync(join(cesiumSource, directory), join(cesiumTarget, directory), {
      recursive: true,
      force: true
    })
  }

  for (const file of ['Cesium.js', 'Cesium.js.map']) {
    const sourceFile = join(cesiumSource, file)
    if (existsSync(sourceFile)) {
      copyFileSync(sourceFile, join(cesiumTarget, file))
    }
  }
}

copyCesiumAssets()

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  pages: true,
  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],
  css: ['~/assets/css/tailwind.css'],
  postcss: {
    plugins: {
      cssnano: false
    }
  },
  runtimeConfig: {
    public: {
      apiBase,
      cesiumIonToken
    }
  }
})
