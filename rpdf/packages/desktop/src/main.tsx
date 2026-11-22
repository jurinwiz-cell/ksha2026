import './index.css'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { isTauri } from '@tauri-apps/api/core'
import { initStore } from '@rpdf/ui'
import App from './App'

async function bootstrap() {
  let bridge: Parameters<typeof initStore>[0]

  if (isTauri()) {
    // D1: 동적 import — Tauri 환경에서 WASM Worker 미생성
    const { tauriClient } = await import('./core/tauri-client')
    await tauriClient.init()
    bridge = tauriClient
  } else {
    const { wasmApi, openWithTransfer } = await import('./core/wasm-client')
    await wasmApi.init()
    bridge = {
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
