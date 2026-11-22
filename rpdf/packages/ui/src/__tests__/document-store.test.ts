import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useDocumentStore, initStore } from '../state/document-store'
import type { WasmApi, TextBlock, TextStyle, AnnotationInfo } from '../types/pdf'

const mockPages = [
  { index: 0, rotation: 0, media_box: [0, 0, 595, 842] as [number, number, number, number], crop_box: null },
  { index: 1, rotation: 0, media_box: [0, 0, 595, 842] as [number, number, number, number], crop_box: null }
]

const mockTextBlocks: TextBlock[] = [
  {
    text: 'Hello',
    x: 10,
    y: 700,
    width: 60,
    height: 12,
    font_size: 12,
    font_ref: 'F1',
    font_family: 'Helvetica',
  },
]

const mockAnnotations: AnnotationInfo[] = []

const mockApi: WasmApi & { open: (bytes: Uint8Array) => Promise<typeof mockPages> } = {
  init: vi.fn().mockResolvedValue(undefined),
  open: vi.fn().mockResolvedValue(mockPages),
  undoLen: vi.fn().mockResolvedValue(0),
  redoLen: vi.fn().mockResolvedValue(0),
  rotatePage: vi.fn().mockResolvedValue(mockPages),
  deletePages: vi.fn().mockResolvedValue(mockPages),
  undo: vi.fn().mockResolvedValue(mockPages),
  redo: vi.fn().mockResolvedValue(mockPages),
  save: vi.fn().mockResolvedValue(new Uint8Array()),
  getTextBlocks: vi.fn().mockResolvedValue(mockTextBlocks),
  replaceText: vi.fn().mockResolvedValue(mockPages),
  addAnnotation: vi.fn().mockResolvedValue(mockAnnotations),
  getAnnotations: vi.fn().mockResolvedValue(mockAnnotations),
  deleteAnnotation: vi.fn().mockResolvedValue(mockAnnotations),
} as WasmApi & { open: (bytes: Uint8Array) => Promise<typeof mockPages> }

const DEFAULT_TEXT_STYLE: TextStyle = {
  bold: false,
  italic: false,
  underline: false,
  strikethrough: false,
  color: [0, 0, 0],
  alignment: 'Left',
}

beforeEach(() => {
  initStore(mockApi)
  useDocumentStore.setState({
    fileName: null,
    fileRef: null,
    pages: [],
    selectedPageIndex: 0,
    undoLen: 0,
    redoLen: 0,
    isLoading: false,
    error: null,
    textBlocks: [],
    editingBlock: null,
    fontConfig: { koreanFont: 'NanumGothic', latinFont: 'Helvetica', fontSize: 12 },
    textStyle: DEFAULT_TEXT_STYLE,
    annotations: [],
    annotationMode: 'none',
  })
  vi.clearAllMocks()
  vi.mocked(mockApi.undoLen).mockResolvedValue(0)
  vi.mocked(mockApi.redoLen).mockResolvedValue(0)
  vi.mocked(mockApi.getTextBlocks).mockResolvedValue(mockTextBlocks)
  vi.mocked(mockApi.replaceText).mockResolvedValue(mockPages)
  vi.mocked(mockApi.getAnnotations).mockResolvedValue(mockAnnotations)
})

afterEach(() => {
  initStore(null)
})

describe('null guard', () => {
  it('initStore 호출 전 store 사용 시 에러 발생', async () => {
    initStore(null)
    const file = new File(['%PDF-1.4'], 'test.pdf', { type: 'application/pdf' })
    await useDocumentStore.getState().openFile(file)
    const state = useDocumentStore.getState()
    expect(state.error).toContain('initStore() must be called')
  })
})

describe('openFile action', () => {
  it('정상 PDF — pages와 fileName이 설정된다', async () => {
    vi.mocked(mockApi.open).mockResolvedValue(mockPages)

    const file = new File(['%PDF-1.4'], 'test.pdf', { type: 'application/pdf' })
    await useDocumentStore.getState().openFile(file)

    const state = useDocumentStore.getState()
    expect(state.pages).toHaveLength(2)
    expect(state.fileName).toBe('test.pdf')
    expect(state.isLoading).toBe(false)
    expect(state.error).toBeNull()
  })

  it('파싱 실패 — error가 설정되고 pages는 비어 있다', async () => {
    vi.mocked(mockApi.open).mockRejectedValue(new Error('invalid PDF'))

    const file = new File(['not-a-pdf'], 'bad.pdf', { type: 'application/pdf' })
    await useDocumentStore.getState().openFile(file)

    const state = useDocumentStore.getState()
    expect(state.pages).toHaveLength(0)
    expect(state.error).toContain('invalid PDF')
    expect(state.isLoading).toBe(false)
  })
})

describe('rotatePage action', () => {
  it('rotatePage 호출 시 pages가 업데이트된다', async () => {
    const rotatedPages = [
      { index: 0, rotation: 90, media_box: [0, 0, 595, 842] as [number, number, number, number], crop_box: null },
      { index: 1, rotation: 0, media_box: [0, 0, 595, 842] as [number, number, number, number], crop_box: null }
    ]
    useDocumentStore.setState({ pages: mockPages, fileName: 'test.pdf', fileRef: new File([''], 'test.pdf') })

    vi.mocked(mockApi.rotatePage).mockResolvedValue(rotatedPages)
    vi.mocked(mockApi.undoLen).mockResolvedValue(1)
    vi.mocked(mockApi.redoLen).mockResolvedValue(0)

    await useDocumentStore.getState().rotatePage(0, 90)

    const state = useDocumentStore.getState()
    expect(state.pages[0].rotation).toBe(90)
    expect(state.undoLen).toBe(1)
    expect(state.isLoading).toBe(false)
  })
})

describe('deletePages action', () => {
  it('페이지 삭제 후 pages 수 감소', async () => {
    useDocumentStore.setState({ pages: mockPages, fileName: 'test.pdf', fileRef: new File([''], 'test.pdf') })
    const remaining = [mockPages[1]]

    vi.mocked(mockApi.deletePages).mockResolvedValue(remaining)
    vi.mocked(mockApi.undoLen).mockResolvedValue(1)
    vi.mocked(mockApi.redoLen).mockResolvedValue(0)

    await useDocumentStore.getState().deletePages([0])

    expect(useDocumentStore.getState().pages).toHaveLength(1)
  })

  it('마지막 1페이지 삭제 시도 — error 설정, WASM 호출 없음', async () => {
    useDocumentStore.setState({ pages: [mockPages[0]] })

    await useDocumentStore.getState().deletePages([0])

    expect(vi.mocked(mockApi.deletePages)).not.toHaveBeenCalled()
    expect(useDocumentStore.getState().error).toContain('최소 1페이지')
  })
})

describe('undo / redo action', () => {
  it('undo 후 pages 복구', async () => {
    useDocumentStore.setState({ pages: [mockPages[1]], undoLen: 1 })

    vi.mocked(mockApi.undo).mockResolvedValue(mockPages)
    vi.mocked(mockApi.undoLen).mockResolvedValue(0)
    vi.mocked(mockApi.redoLen).mockResolvedValue(1)

    await useDocumentStore.getState().undo()

    const state = useDocumentStore.getState()
    expect(state.pages).toHaveLength(2)
    expect(state.undoLen).toBe(0)
    expect(state.redoLen).toBe(1)
  })
})

describe('loadTextBlocks action', () => {
  it('정상 호출 — textBlocks가 설정된다', async () => {
    await useDocumentStore.getState().loadTextBlocks(0)

    const state = useDocumentStore.getState()
    expect(state.textBlocks).toHaveLength(1)
    expect(state.textBlocks[0].text).toBe('Hello')
    expect(vi.mocked(mockApi.getTextBlocks)).toHaveBeenCalledWith(0)
  })

  it('실패 시 error가 설정된다', async () => {
    vi.mocked(mockApi.getTextBlocks).mockRejectedValue(new Error('extract failed'))

    await useDocumentStore.getState().loadTextBlocks(0)

    const state = useDocumentStore.getState()
    expect(state.error).toContain('extract failed')
  })
})

describe('setEditingBlock action', () => {
  it('UI-3: setEditingBlock → editingBlock이 설정된다', () => {
    const block = mockTextBlocks[0]
    useDocumentStore.getState().setEditingBlock(block)

    expect(useDocumentStore.getState().editingBlock).toEqual(block)
  })

  it('UI-4: setEditingBlock(null) → editingBlock이 null이 된다', () => {
    useDocumentStore.setState({ editingBlock: mockTextBlocks[0] })
    useDocumentStore.getState().setEditingBlock(null)

    expect(useDocumentStore.getState().editingBlock).toBeNull()
  })
})

describe('setFontConfig action', () => {
  it('UI-6: setFontConfig → fontConfig가 업데이트된다', () => {
    const newConfig = { koreanFont: 'NanumMyeongjo' as const, latinFont: 'TimesRoman' as const, fontSize: 18 }
    useDocumentStore.getState().setFontConfig(newConfig)

    const state = useDocumentStore.getState()
    expect(state.fontConfig.koreanFont).toBe('NanumMyeongjo')
    expect(state.fontConfig.latinFont).toBe('TimesRoman')
    expect(state.fontConfig.fontSize).toBe(18)
  })
})

describe('setTextStyle action', () => {
  it('UI-Style-1: setTextStyle({ bold: true }) → textStyle.bold === true', () => {
    useDocumentStore.getState().setTextStyle({ ...DEFAULT_TEXT_STYLE, bold: true })

    const state = useDocumentStore.getState()
    expect(state.textStyle.bold).toBe(true)
  })

  it('UI-Style-2: textStyle 초기값 — 모두 false, color 검정, Left', () => {
    const state = useDocumentStore.getState()
    expect(state.textStyle.bold).toBe(false)
    expect(state.textStyle.italic).toBe(false)
    expect(state.textStyle.underline).toBe(false)
    expect(state.textStyle.strikethrough).toBe(false)
    expect(state.textStyle.color).toEqual([0, 0, 0])
    expect(state.textStyle.alignment).toBe('Left')
  })

  it('UI-Style-3: replaceText 호출 시 textStyleJson 포함 확인 (mock 검증)', async () => {
    useDocumentStore.setState({
      pages: mockPages,
      textStyle: { ...DEFAULT_TEXT_STYLE, bold: true },
    })

    const block = mockTextBlocks[0]
    await useDocumentStore.getState().replaceText(0, block, 'New Text')

    expect(vi.mocked(mockApi.replaceText)).toHaveBeenCalledWith(
      0,
      [block.x, block.y, block.width, block.height],
      'New Text',
      block.x,
      block.y,
      expect.any(String),
      expect.stringContaining('"bold":true'),
      null
    )
  })
})
