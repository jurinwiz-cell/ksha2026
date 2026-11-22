import { test, expect } from '@playwright/test'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const PDF_FIXTURE = path.resolve(__dirname, '../../../samples/multilang-korean-metadata.pdf')

test('PDF 드롭 후 썸네일 표시', async ({ page }) => {
  await page.goto('/')

  // 파일 input으로 PDF 업로드 (드래그 드롭 대신 input 직접 사용)
  const input = page.locator('input[type="file"]')
  await input.setInputFiles(PDF_FIXTURE)

  // 썸네일이 최소 1개 이상 표시될 때까지 대기
  await expect(page.locator('img[alt*="페이지"]').first()).toBeVisible({ timeout: 15000 })
})

test('페이지 회전 후 뷰어 반영', async ({ page }) => {
  await page.goto('/')

  const input = page.locator('input[type="file"]')
  await input.setInputFiles(PDF_FIXTURE)
  await expect(page.locator('img[alt*="페이지"]').first()).toBeVisible({ timeout: 15000 })

  // canvas가 표시될 때까지 대기 (PageViewer)
  await expect(page.locator('canvas')).toBeVisible({ timeout: 10000 })

  // 회전 버튼 클릭 (+90°)
  await page.getByRole('button', { name: /\+90/ }).click()

  // rotation 후 canvas가 여전히 존재 (CSS transform만 변경, reload 없음)
  await expect(page.locator('canvas')).toBeVisible({ timeout: 5000 })
})

test('페이지 삭제 후 썸네일 수 감소', async ({ page }) => {
  await page.goto('/')

  const input = page.locator('input[type="file"]')
  await input.setInputFiles(PDF_FIXTURE)
  await expect(page.locator('img[alt*="페이지"]').first()).toBeVisible({ timeout: 15000 })

  // 초기 썸네일 수 확인
  const initialCount = await page.locator('img[alt*="페이지"]').count()

  if (initialCount <= 1) {
    test.skip()
    return
  }

  // 첫 번째 썸네일 hover → × 버튼 클릭
  const firstThumbnail = page.locator('img[alt="페이지 1"]').locator('..')
  await firstThumbnail.hover()
  // × 버튼 (삭제) 클릭
  const deleteBtn = firstThumbnail.locator('button')
  await deleteBtn.click()

  // 썸네일 수 감소 확인
  await expect(page.locator('img[alt*="페이지"]')).toHaveCount(initialCount - 1, { timeout: 10000 })
})

test('Undo 후 삭제 복구', async ({ page }) => {
  await page.goto('/')

  const input = page.locator('input[type="file"]')
  await input.setInputFiles(PDF_FIXTURE)
  await expect(page.locator('img[alt*="페이지"]').first()).toBeVisible({ timeout: 15000 })

  const initialCount = await page.locator('img[alt*="페이지"]').count()

  if (initialCount <= 1) {
    // 1페이지 PDF면 삭제 불가 — 테스트 skip
    test.skip()
    return
  }

  // 첫 번째 썸네일 삭제
  const firstThumbnail = page.locator('img[alt="페이지 1"]').locator('..')
  await firstThumbnail.hover()
  await firstThumbnail.locator('button').click()
  await expect(page.locator('img[alt*="페이지"]')).toHaveCount(initialCount - 1, { timeout: 10000 })

  // Undo 클릭
  await page.getByRole('button', { name: /Undo/ }).click()

  // 썸네일 수 복구
  await expect(page.locator('img[alt*="페이지"]')).toHaveCount(initialCount, { timeout: 10000 })
})

test('저장 다운로드 파일 존재', async ({ page }) => {
  await page.goto('/')

  const input = page.locator('input[type="file"]')
  await input.setInputFiles(PDF_FIXTURE)
  await expect(page.locator('img[alt*="페이지"]').first()).toBeVisible({ timeout: 15000 })

  // 다운로드 이벤트 대기
  const downloadPromise = page.waitForEvent('download')
  await page.getByRole('button', { name: /저장/ }).click()
  const download = await downloadPromise

  // 다운로드된 파일명 확인
  expect(download.suggestedFilename()).toMatch(/\.pdf$/)
})

test('PDF 아닌 파일 드롭 → 오류 메시지', async ({ page }) => {
  await page.goto('/')

  // txt 파일 업로드 — DropZone의 MIME 검사에서 걸려 오류 메시지 표시
  const input = page.locator('input[type="file"]')
  const txtFile = {
    name: 'test.txt',
    mimeType: 'text/plain',
    buffer: Buffer.from('hello world')
  }
  await input.setInputFiles(txtFile)

  // 오류 메시지 표시 확인
  await expect(page.locator('text=PDF 파일만 열 수 있습니다')).toBeVisible({ timeout: 5000 })
})
