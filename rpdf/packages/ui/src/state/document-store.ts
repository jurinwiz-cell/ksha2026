import { create } from 'zustand'
import type {
  AnnotationInfo,
  AnnotationParams,
  AnnotationType,
  FontConfig,
  PageInfo,
  TextBlock,
  TextStyle,
  WasmApi,
} from '../types/pdf'

type StoreApi = WasmApi & { open: (bytes: Uint8Array) => Promise<PageInfo[]> }

let _api: StoreApi | null = null

export function initStore(api: StoreApi | null): void {
  _api = api
}

function getApi(): StoreApi {
  if (!_api) throw new Error('initStore() must be called before using the store')
  return _api
}

const DEFAULT_FONT_CONFIG: FontConfig = {
  koreanFont: 'NanumGothic',
  latinFont: 'Helvetica',
  fontSize: 12,
}

const DEFAULT_TEXT_STYLE: TextStyle = {
  bold: false,
  italic: false,
  underline: false,
  strikethrough: false,
  color: [0, 0, 0],
  alignment: 'Left',
}

interface DocumentState {
  fileName: string | null
  fileRef: File | null
  pages: PageInfo[]
  selectedPageIndex: number
  undoLen: number
  redoLen: number
  isLoading: boolean
  error: string | null
  textBlocks: TextBlock[]
  editingBlock: TextBlock | null
  fontConfig: FontConfig
  textStyle: TextStyle
  annotations: AnnotationInfo[]
  annotationMode: 'none' | AnnotationType

  openFile: (file: File) => Promise<void>
  selectPage: (index: number) => void
  rotatePage: (index: number, degrees: number) => Promise<void>
  deletePages: (indices: number[]) => Promise<void>
  undo: () => Promise<void>
  redo: () => Promise<void>
  saveFile: () => Promise<void>
  clearError: () => void
  loadTextBlocks: (pageIndex: number) => Promise<void>
  setEditingBlock: (block: TextBlock | null) => void
  replaceText: (pageIndex: number, block: TextBlock, newText: string) => Promise<void>
  setFontConfig: (config: FontConfig) => void
  setTextStyle: (style: TextStyle) => void
  loadAnnotations: (pageIndex: number) => Promise<void>
  setAnnotationMode: (mode: 'none' | AnnotationType) => void
  addAnnotation: (pageIndex: number, params: AnnotationParams) => Promise<void>
  deleteAnnotation: (pageIndex: number, annotId: number) => Promise<void>
}

export const useDocumentStore = create<DocumentState>((set, get) => ({
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
  fontConfig: DEFAULT_FONT_CONFIG,
  textStyle: DEFAULT_TEXT_STYLE,
  annotations: [],
  annotationMode: 'none',

  openFile: async (file: File) => {
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const buffer = await file.arrayBuffer()
      const bytes = new Uint8Array(buffer)
      const pages = await api.open(bytes)
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({
        fileName: file.name,
        fileRef: file,
        pages,
        selectedPageIndex: 0,
        undoLen,
        redoLen,
        isLoading: false
      })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  selectPage: (index: number) => set({ selectedPageIndex: index }),

  rotatePage: async (index: number, degrees: number) => {
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const pages = await api.rotatePage(index, degrees)
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({ pages, undoLen, redoLen, isLoading: false })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  deletePages: async (indices: number[]) => {
    const { pages } = get()
    if (pages.length - indices.length < 1) {
      set({ error: '최소 1페이지 필요' })
      return
    }
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const newPages = await api.deletePages(indices)
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({ pages: newPages, undoLen, redoLen, isLoading: false, selectedPageIndex: 0 })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  undo: async () => {
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const pages = await api.undo()
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({ pages, undoLen, redoLen, isLoading: false })
      // D29: Undo 후 annotations 재페치 — PDF bytes 복원 후 UI 동기화
      const annotations = await api.getAnnotations(get().selectedPageIndex)
      set({ annotations })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  redo: async () => {
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const pages = await api.redo()
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({ pages, undoLen, redoLen, isLoading: false })
      // D29: Redo 후 annotations 재페치 — PDF bytes 복원 후 UI 동기화
      const annotations = await api.getAnnotations(get().selectedPageIndex)
      set({ annotations })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  saveFile: async () => {
    const { fileName } = get()
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const bytes = await api.save()
      const blob = new Blob([bytes.buffer as ArrayBuffer], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = fileName ?? 'edited.pdf'
      a.click()
      URL.revokeObjectURL(url)
      set({ isLoading: false })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  clearError: () => set({ error: null }),

  loadTextBlocks: async (pageIndex: number) => {
    try {
      const api = getApi()
      const blocks = await api.getTextBlocks(pageIndex)
      set({ textBlocks: blocks })
    } catch (e) {
      set({ error: String(e) })
    }
  },

  setEditingBlock: (block: TextBlock | null) => set({ editingBlock: block }),

  replaceText: async (pageIndex: number, block: TextBlock, newText: string) => {
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const { fontConfig, textStyle } = get()
      const fontConfigJson = JSON.stringify(fontConfig)
      const textStyleJson = JSON.stringify(textStyle)

      // 블록 좌표로 cover_rect 구성: [x, y, width, height]
      const coverRect: [number, number, number, number] = [block.x, block.y, block.width, block.height]
      const textX = block.x
      const textY = block.y

      const pages = await api.replaceText(
        pageIndex,
        coverRect,
        newText,
        textX,
        textY,
        fontConfigJson,
        textStyleJson,
        null
      )
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({ pages, undoLen, redoLen, isLoading: false, editingBlock: null })

      // 해당 페이지 텍스트 블록 재로드
      await get().loadTextBlocks(pageIndex)
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  setFontConfig: (config: FontConfig) => set({ fontConfig: config }),

  setTextStyle: (style: TextStyle) => set({ textStyle: style }),

  loadAnnotations: async (pageIndex: number) => {
    try {
      const api = getApi()
      const annotations = await api.getAnnotations(pageIndex)
      set({ annotations })
    } catch (e) {
      set({ error: String(e) })
    }
  },

  setAnnotationMode: (mode: 'none' | AnnotationType) => set({ annotationMode: mode }),

  addAnnotation: async (pageIndex: number, params: AnnotationParams) => {
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const paramsJson = JSON.stringify({
        pageIndex: params.pageIndex,
        annotType: params.annotType,
        rect: params.rect,
        color: params.color,
        content: params.content,
        opacity: params.opacity,
      })
      const annotations = await api.addAnnotation(pageIndex, paramsJson)
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({ annotations, undoLen, redoLen, isLoading: false })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },

  deleteAnnotation: async (pageIndex: number, annotId: number) => {
    set({ isLoading: true, error: null })
    try {
      const api = getApi()
      const annotations = await api.deleteAnnotation(pageIndex, annotId)
      const undoLen = await api.undoLen()
      const redoLen = await api.redoLen()
      set({ annotations, undoLen, redoLen, isLoading: false })
    } catch (e) {
      set({ isLoading: false, error: String(e) })
    }
  },
}))
