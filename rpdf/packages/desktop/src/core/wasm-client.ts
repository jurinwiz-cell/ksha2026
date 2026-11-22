import * as Comlink from 'comlink'
import type { PageInfo, WasmApi } from '@rpdf/ui'

const worker = new Worker(new URL('./wasm-worker.ts', import.meta.url), { type: 'module' })
export const wasmApi = Comlink.wrap<WasmApi>(worker)

export async function openWithTransfer(bytes: Uint8Array): Promise<PageInfo[]> {
  return wasmApi.open(Comlink.transfer(bytes, [bytes.buffer]))
}
