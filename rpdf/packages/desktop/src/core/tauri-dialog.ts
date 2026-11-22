import { invoke } from '@tauri-apps/api/core'
import { open } from '@tauri-apps/plugin-dialog'
import { readFile } from '@tauri-apps/plugin-fs'
import { useDocumentStore } from '@rpdf/ui'

export async function openPdfWithNativeDialog(): Promise<void> {
  const path = await open({
    multiple: false,
    filters: [{ name: 'PDF', extensions: ['pdf'] }],
  })
  if (!path) return // open({ multiple: false }) returns string | null

  const bytes = await readFile(path)
  const filename = path.split('/').pop() ?? 'document.pdf'
  const file = new File([bytes], filename, { type: 'application/pdf' })

  await useDocumentStore.getState().openFile(file)
}

export async function openTiffWithNativeDialog(): Promise<void> {
  const paths = await open({
    multiple: true,
    filters: [{ name: 'TIFF', extensions: ['tif', 'tiff'] }],
  })
  if (!paths || paths.length === 0) return

  const bytes: number[] = await invoke('tiff_to_pdf', { paths })
  const uint8 = new Uint8Array(bytes)
  const file = new File([uint8], 'converted.pdf', { type: 'application/pdf' })
  await useDocumentStore.getState().openFile(file)
}
