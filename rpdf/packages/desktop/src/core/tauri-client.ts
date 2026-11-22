import { invoke } from '@tauri-apps/api/core'
import type { AnnotationInfo, PageInfo, WasmApi, TextBlock } from '@rpdf/ui'

interface OpenResult {
  pages: PageInfo[]
  undo_len: number
  redo_len: number
}

let _lastResult: OpenResult = { pages: [], undo_len: 0, redo_len: 0 }

function storeAndExtract(result: OpenResult): PageInfo[] {
  _lastResult = result
  return result.pages
}

export const tauriClient: WasmApi = {
  init: async () => {},

  open: async (bytes: Uint8Array): Promise<PageInfo[]> => {
    // D2 결정: Uint8Array 직접 전달 (Tauri 2 IPC가 네이티브로 Vec<u8> 변환 지원)
    const result = await invoke<OpenResult>('open_pdf', { bytes })
    return storeAndExtract(result)
  },

  rotatePage: async (index: number, degrees: number): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('rotate_page', { index, degrees })
    return storeAndExtract(result)
  },

  deletePages: async (indices: number[]): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('delete_pages', { indices })
    return storeAndExtract(result)
  },

  undo: async (): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('undo')
    return storeAndExtract(result)
  },

  redo: async (): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('redo')
    return storeAndExtract(result)
  },

  undoLen: async (): Promise<number> => _lastResult.undo_len,

  redoLen: async (): Promise<number> => _lastResult.redo_len,

  save: async (): Promise<Uint8Array> => {
    const bytes = await invoke<number[]>('save_pdf')
    return new Uint8Array(bytes)
  },

  getTextBlocks: async (pageIndex: number): Promise<TextBlock[]> => {
    return await invoke<TextBlock[]>('get_text_blocks', { pageIndex })
  },

  replaceText: async (
    pageIndex: number,
    coverRect: [number, number, number, number],
    newText: string,
    textX: number,
    textY: number,
    fontConfigJson: string,
    textStyleJson: string,
    fontBytes: Uint8Array | null
  ): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('replace_text', {
      pageIndex,
      coverRect,
      newText,
      textX,
      textY,
      fontConfigJson,
      textStyleJson,
      fontBytes: fontBytes ? Array.from(fontBytes) : null,
    })
    return storeAndExtract(result)
  },

  addAnnotation: async (pageIndex: number, paramsJson: string): Promise<AnnotationInfo[]> => {
    return await invoke<AnnotationInfo[]>('add_annotation', { pageIndex, paramsJson })
  },

  getAnnotations: async (pageIndex: number): Promise<AnnotationInfo[]> => {
    return await invoke<AnnotationInfo[]>('get_annotations', { pageIndex })
  },

  deleteAnnotation: async (pageIndex: number, annotId: number): Promise<AnnotationInfo[]> => {
    return await invoke<AnnotationInfo[]>('delete_annotation', { pageIndex, annotId })
  },
}
