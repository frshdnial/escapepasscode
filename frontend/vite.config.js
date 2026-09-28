import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// In dev, /api is proxied to the Slim server (composer start -> http://localhost:8000).
export default defineConfig({
  plugins: [vue()],
  server: { port: 5173, proxy: { '/api': 'http://localhost:8000' } },
})
