import { describe, it, expect } from 'vitest'
import { pdfToScreen } from '../core/pdf-coords'

describe('pdfToScreen', () => {
  it('UI-1: 표준 MediaBox [0,0,595,842] 변환 정확성', () => {
    const mediaBox: [number, number, number, number] = [0, 0, 595, 842]
    const screenW = 595
    const screenH = 842

    // 좌상단 (PDF 좌하단 기준)
    const topLeft = pdfToScreen(0, 842, mediaBox, screenW, screenH)
    expect(topLeft.x).toBeCloseTo(0)
    expect(topLeft.y).toBeCloseTo(0)

    // 우하단
    const bottomRight = pdfToScreen(595, 0, mediaBox, screenW, screenH)
    expect(bottomRight.x).toBeCloseTo(595)
    expect(bottomRight.y).toBeCloseTo(842)

    // 중앙
    const center = pdfToScreen(297.5, 421, mediaBox, screenW, screenH)
    expect(center.x).toBeCloseTo(297.5)
    expect(center.y).toBeCloseTo(421)
  })

  it('UI-1b: 비표준 MediaBox [100,100,500,800] 원점 오프셋 보정', () => {
    const mediaBox: [number, number, number, number] = [100, 100, 500, 800]
    const screenW = 400
    const screenH = 700

    // PDF x=100 → 화면 x=0 (원점)
    const origin = pdfToScreen(100, 800, mediaBox, screenW, screenH)
    expect(origin.x).toBeCloseTo(0)
    expect(origin.y).toBeCloseTo(0)

    // PDF x=500, y=100 → 화면 우하단
    const corner = pdfToScreen(500, 100, mediaBox, screenW, screenH)
    expect(corner.x).toBeCloseTo(400)
    expect(corner.y).toBeCloseTo(700)

    // 중앙점 검증
    const mid = pdfToScreen(300, 450, mediaBox, screenW, screenH)
    expect(mid.x).toBeCloseTo(200)
    expect(mid.y).toBeCloseTo(350)
  })
})
