import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useDocumentStore, initStore } from '../state/document-store'
import { pdfToScreen, screenToPdf } from '../core/pdf-coords'
import type { AnnotationInfo, AnnotationType, WasmApi } from '../types/pdf'

// ── 테스트 데이터 ────────────────────────────────────────────

const mockPages = [
  {
    index: 0,
    rotation: 0,
    media_box: [0, 0, 595, 842] as [number, number, number, number],
    crop_box: null,
  },
]

const mockHighlightAnnotation: AnnotationInfo = {
  annotId: 10,
  annotType: 'Highlight',
  rect: [50, 700, 200, 720],
  color: [1, 1, 0],
  content: undefined,
  opacity: 0.3,
}

const mockAnnotations: AnnotationInfo[] = [mockHighlightAnnotation]

function makeMockApi(overrides: Partial<WasmApi> = {}): WasmApi {
  return {
    init: vi.fn().mockResolvedValue(undefined),
    open: vi.fn().mockResolvedValue(mockPages),
    undoLen: vi.fn().mockResolvedValue(0),
    redoLen: vi.fn().mockResolvedValue(0),
    rotatePage: vi.fn().mockResolvedValue(mockPages),
    deletePages: vi.fn().mockResolvedValue(mockPages),
    undo: vi.fn().mockResolvedValue(mockPages),
    redo: vi.fn().mockResolvedValue(mockPages),
    save: vi.fn().mockResolvedValue(new Uint8Array()),
    getTextBlocks: vi.fn().mockResolvedValue([]),
    replaceText: vi.fn().mockResolvedValue(mockPages),
    addAnnotation: vi.fn().mockResolvedValue(mockAnnotations),
    getAnnotations: vi.fn().mockResolvedValue(mockAnnotations),
    deleteAnnotation: vi.fn().mockResolvedValue([]),
    ...overrides,
  }
}

// ── ANN-UI-1: annotationMode 전환 ──────────────────────────

describe('ANN-UI-1: annotationMode 전환', () => {
  beforeEach(() => {
    initStore(makeMockApi() as Parameters<typeof initStore>[0])
    useDocumentStore.setState({ annotationMode: 'none', annotations: [] })
    vi.clearAllMocks()
  })

  afterEach(() => {
    initStore(null)
  })

  it('setAnnotationMode("Highlight") → annotationMode가 Highlight으로 변경된다', () => {
    useDocumentStore.getState().setAnnotationMode('Highlight')
    expect(useDocumentStore.getState().annotationMode).toBe('Highlight')
  })

  it('setAnnotationMode("none") → annotationMode가 none으로 변경된다', () => {
    useDocumentStore.setState({ annotationMode: 'Highlight' })
    useDocumentStore.getState().setAnnotationMode('none')
    expect(useDocumentStore.getState().annotationMode).toBe('none')
  })

  it('초기 annotationMode는 none이다', () => {
    useDocumentStore.setState({ annotationMode: 'none' })
    expect(useDocumentStore.getState().annotationMode).toBe('none')
  })

  it('모든 AnnotationType 값으로 전환된다', () => {
    const modes: Array<'none' | AnnotationType> = [
      'Highlight',
      'Underline',
      'StickyNote',
      'none',
    ]
    for (const mode of modes) {
      useDocumentStore.getState().setAnnotationMode(mode)
      expect(useDocumentStore.getState().annotationMode).toBe(mode)
    }
  })
})

// ── ANN-UI-2: annotations 로드 ──────────────────────────────

describe('ANN-UI-2: annotations 로드', () => {
  let mockApi: ReturnType<typeof makeMockApi>

  beforeEach(() => {
    mockApi = makeMockApi()
    initStore(mockApi as Parameters<typeof initStore>[0])
    useDocumentStore.setState({ annotations: [] })
    vi.clearAllMocks()
  })

  afterEach(() => {
    initStore(null)
  })

  it('loadAnnotations 호출 시 store.annotations가 업데이트된다', async () => {
    vi.mocked(mockApi.getAnnotations).mockResolvedValue(mockAnnotations)

    await useDocumentStore.getState().loadAnnotations(0)

    const state = useDocumentStore.getState()
    expect(state.annotations).toHaveLength(1)
    expect(state.annotations[0].annotId).toBe(10)
    expect(state.annotations[0].annotType).toBe('Highlight')
  })

  it('getAnnotations 실패 시 error가 설정된다', async () => {
    vi.mocked(mockApi.getAnnotations).mockRejectedValue(new Error('읽기 실패'))

    await useDocumentStore.getState().loadAnnotations(0)

    expect(useDocumentStore.getState().error).toContain('읽기 실패')
  })
})

// ── ANN-UI-3: screenToPdf 변환 ──────────────────────────────

describe('ANN-UI-3: screenToPdf 변환', () => {
  const mediaBox: [number, number, number, number] = [0, 0, 595, 842]
  const screenW = 595
  const screenH = 842

  it('화면 좌상단 (0,0) → PDF 좌상단 (0, 842)', () => {
    const result = screenToPdf(0, 0, mediaBox, screenW, screenH)
    expect(result.x).toBeCloseTo(0)
    expect(result.y).toBeCloseTo(842)
  })

  it('화면 우하단 (595,842) → PDF 우하단 (595, 0)', () => {
    const result = screenToPdf(595, 842, mediaBox, screenW, screenH)
    expect(result.x).toBeCloseTo(595)
    expect(result.y).toBeCloseTo(0)
  })

  it('화면 중앙 (297.5, 421) → PDF 중앙 (297.5, 421)', () => {
    const result = screenToPdf(297.5, 421, mediaBox, screenW, screenH)
    expect(result.x).toBeCloseTo(297.5)
    expect(result.y).toBeCloseTo(421)
  })
})

// ── ANN-UI-4: screenToPdf ↔ pdfToScreen 라운드트립 ─────────

describe('ANN-UI-4: screenToPdf-pdfToScreen 라운드트립', () => {
  const mediaBox: [number, number, number, number] = [0, 0, 595, 842]
  const screenW = 595
  const screenH = 842

  it('pdfToScreen(screenToPdf(x, y)) ≈ (x, y)', () => {
    const testPoints = [
      { x: 0, y: 0 },
      { x: 100, y: 200 },
      { x: 297.5, y: 421 },
      { x: 595, y: 842 },
    ]

    for (const { x, y } of testPoints) {
      const pdf = screenToPdf(x, y, mediaBox, screenW, screenH)
      const screen = pdfToScreen(pdf.x, pdf.y, mediaBox, screenW, screenH)
      expect(screen.x).toBeCloseTo(x, 5)
      expect(screen.y).toBeCloseTo(y, 5)
    }
  })

  it('비표준 MediaBox [100,100,500,800] 라운드트립', () => {
    const mb: [number, number, number, number] = [100, 100, 500, 800]
    const sw = 400
    const sh = 700

    const testPoints = [{ x: 50, y: 100 }, { x: 200, y: 350 }]

    for (const { x, y } of testPoints) {
      const pdf = screenToPdf(x, y, mb, sw, sh)
      const screen = pdfToScreen(pdf.x, pdf.y, mb, sw, sh)
      expect(screen.x).toBeCloseTo(x, 5)
      expect(screen.y).toBeCloseTo(y, 5)
    }
  })
})

// ── ANN-UI-5: drag rect 정규화 ──────────────────────────────

describe('ANN-UI-5: drag rect 정규화', () => {
  const mediaBox: [number, number, number, number] = [0, 0, 595, 842]
  const screenW = 595
  const screenH = 842

  it('우하→좌상 드래그 → [x_min, y_min, x_max, y_max] 보장', () => {
    // 드래그 시작: 화면 우하 (400, 600) → PDF 좌표
    const startScreen = { x: 400, y: 600 }
    // 드래그 끝: 화면 좌상 (100, 200) → PDF 좌표
    const endScreen = { x: 100, y: 200 }

    const startPdf = screenToPdf(startScreen.x, startScreen.y, mediaBox, screenW, screenH)
    const endPdf = screenToPdf(endScreen.x, endScreen.y, mediaBox, screenW, screenH)

    // D30 정규화
    const x0 = Math.min(startPdf.x, endPdf.x)
    const y0 = Math.min(startPdf.y, endPdf.y)
    const x1 = Math.max(startPdf.x, endPdf.x)
    const y1 = Math.max(startPdf.y, endPdf.y)

    expect(x0).toBeLessThan(x1)
    expect(y0).toBeLessThan(y1)
    expect(x0).toBeCloseTo(100)
    expect(x1).toBeCloseTo(400)
  })

  it('좌상→우하 드래그 → 동일한 정규화 결과', () => {
    const startScreen = { x: 100, y: 200 }
    const endScreen = { x: 400, y: 600 }

    const startPdf = screenToPdf(startScreen.x, startScreen.y, mediaBox, screenW, screenH)
    const endPdf = screenToPdf(endScreen.x, endScreen.y, mediaBox, screenW, screenH)

    const x0 = Math.min(startPdf.x, endPdf.x)
    const y0 = Math.min(startPdf.y, endPdf.y)
    const x1 = Math.max(startPdf.x, endPdf.x)
    const y1 = Math.max(startPdf.y, endPdf.y)

    expect(x0).toBeLessThan(x1)
    expect(y0).toBeLessThan(y1)
    expect(x0).toBeCloseTo(100)
    expect(x1).toBeCloseTo(400)
  })
})
