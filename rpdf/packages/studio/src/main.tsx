import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { initStore } from '@rpdf/ui'
import { wasmApi, openWithTransfer } from './core/wasm-client'
import App from './App'

async function bootstrap() {
  await wasmApi.init()

  // D1: Comlink.Remote(Proxy)를 spread하면 {}가 됨 — 명시적 어댑터 객체 사용
  const bridge = {
    init: () => wasmApi.init(),
    open: (bytes: Uint8Array) => openWithTransfer(bytes),
    rotatePage: (i: number, d: number) => wasmApi.rotatePage(i, d),
    deletePages: (indices: number[]) => wasmApi.deletePages(indices),
    undo: () => wasmApi.undo(),
    redo: () => wasmApi.redo(),
    undoLen: () => wasmApi.undoLen(),
    redoLen: () => wasmApi.redoLen(),
    save: () => wasmApi.save(),
    getTextBlocks: (pageIndex: number) => wasmApi.getTextBlocks(pageIndex),
    replaceText: (
      pageIndex: number,
      coverRect: [number, number, number, number],
      newText: string,
      textX: number,
      textY: number,
      fontConfigJson: string,
      textStyleJson: string,
      fontBytes: Uint8Array | null
    ) => wasmApi.replaceText(pageIndex, coverRect, newText, textX, textY, fontConfigJson, textStyleJson, fontBytes),
    addAnnotation: (pageIndex: number, paramsJson: string) =>
      wasmApi.addAnnotation(pageIndex, paramsJson),
    getAnnotations: (pageIndex: number) => wasmApi.getAnnotations(pageIndex),
    deleteAnnotation: (pageIndex: number, annotId: number) =>
      wasmApi.deleteAnnotation(pageIndex, annotId),
  }
  initStore(bridge)

  const rootElement = document.getElementById('root')
  if (!rootElement) throw new Error('Root element not found')

  createRoot(rootElement).render(
    <StrictMode>
      <App />
    </StrictMode>
  )
}

bootstrap()
