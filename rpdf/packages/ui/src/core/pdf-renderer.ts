import * as pdfjs from 'pdfjs-dist'

pdfjs.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs'

export async function renderPageToDataUrl(
  source: File | Uint8Array,
  pageIndex: number,
  scale = 0.3
): Promise<string> {
  let data: Uint8Array
  if (source instanceof File) {
    const buffer = await source.arrayBuffer()
    data = new Uint8Array(buffer)
  } else {
    data = source
  }

  const pdf = await pdfjs.getDocument({ data }).promise
  const page = await pdf.getPage(pageIndex + 1)

  const viewport = page.getViewport({ scale })
  const canvas = document.createElement('canvas')
  canvas.width = viewport.width
  canvas.height = viewport.height

  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('canvas 2d context 획득 실패')
  await page.render({ canvasContext: ctx, viewport }).promise

  return canvas.toDataURL()
}
