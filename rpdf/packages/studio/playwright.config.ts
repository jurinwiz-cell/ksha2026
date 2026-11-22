import { defineConfig, devices } from '@playwright/test'

// CI: vite preview(빌드 결과물) — WASM·pdf.js worker가 build 시 올바르게 번들됨
// 로컬: vite dev (HMR)
const BASE_URL = process.env.CI ? 'http://localhost:4173' : 'http://localhost:5173'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry'
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    }
  ],
  webServer: {
    command: process.env.CI ? 'pnpm preview' : 'pnpm dev',
    url: BASE_URL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000
  }
})
