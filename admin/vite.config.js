import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// El portal se sirve bajo /admin/ en produccion (junto a /api y /pwa).
export default defineConfig({
  plugins: [vue()],
  base: './',
  server: { port: 5173 }
})
