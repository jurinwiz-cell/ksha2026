import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import wasm from 'vite-plugin-wasm'
import { viteStaticCopy } from 'vite-plugin-static-copy'

export default defineConfig({
  plugins: [
    react(),
    wasm(),
    viteStaticCopy({
      targets: [
        {
          src: 'node_modules/pdfjs-dist/build/pdf.worker.min.mjs',
          dest: ''
        }
      ]
    })
  ],
  worker: {
    format: 'es',
    plugins: () => [wasm()]
  },
  optimizeDeps: {
    include: ['pdfjs-dist']
  },
  // Tauri expects a specific port during dev
  server: {
    port: 5173,
    strictPort: true,
  },
  build: {
    // Tauri uses Chromium on macOS, so only target Chrome-compatible browsers
    target: ['es2021', 'chrome100', 'safari13'],
    // don't minify for debug builds
    minify: !process.env.TAURI_DEBUG ? 'esbuild' : false,
    // produce sourcemaps for debug builds
    sourcemap: !!process.env.TAURI_DEBUG,
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test-setup.ts'],
  },
})
