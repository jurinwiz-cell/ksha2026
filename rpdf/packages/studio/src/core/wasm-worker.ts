import * as Comlink from 'comlink'
import init, { PdfDocument } from 'rpdf-wasm'
import type { AnnotationInfo, FontConfig, PageInfo, TextBlock } from '@rpdf/ui'

let doc: PdfDocument | null = null

function collectPages(document: PdfDocument): PageInfo[] {
  const count = document.page_count()
  const pages: PageInfo[] = []
  for (let i = 0; i < count; i++) {
    pages.push(document.page_info(i) as PageInfo)
  }
  return pages
}

const api = {
  async init(): Promise<void> {
    await init()
    // eslint-disable-next-line no-console
    console.log('WASM initialized')
  },

  async open(bytes: Uint8Array): Promise<PageInfo[]> {
    if (doc) doc.free()
    doc = new PdfDocument(bytes)
    return collectPages(doc)
  },

  async rotatePage(index: number, degrees: number): Promise<PageInfo[]> {
    if (!doc) throw new Error('No document loaded')
    doc.rotate_page(index, degrees)
    return collectPages(doc)
  },

  async deletePages(indices: number[]): Promise<PageInfo[]> {
    if (!doc) throw new Error('No document loaded')
    doc.delete_pages(new Uint32Array(indices))
    return collectPages(doc)
  },

  async undo(): Promise<PageInfo[]> {
    if (!doc) throw new Error('No document loaded')
    doc.undo()
    return collectPages(doc)
  },

  async redo(): Promise<PageInfo[]> {
    if (!doc) throw new Error('No document loaded')
    doc.redo()
    return collectPages(doc)
  },

  async undoLen(): Promise<number> {
    if (!doc) return 0
    return doc.undo_len()
  },

  async redoLen(): Promise<number> {
    if (!doc) return 0
    return doc.redo_len()
  },

  async save(): Promise<Uint8Array> {
    if (!doc) throw new Error('No document loaded')
    return doc.save()
  },

  async getTextBlocks(pageIndex: number): Promise<TextBlock[]> {
    if (!doc) throw new Error('No document loaded')
    const result = doc.get_text_blocks(pageIndex)
    // WASM이 JsValue(배열)를 반환하므로 JSON으로 직렬화 후 파싱
    const json = JSON.stringify(result)
    return JSON.parse(json) as TextBlock[]
  },

  async replaceText(
    pageIndex: number,
    coverRect: [number, number, number, number],
    newText: string,
    textX: number,
    textY: number,
    fontConfigJson: string,
    textStyleJson: string,
    fontBytes: Uint8Array | null
  ): Promise<PageInfo[]> {
    if (!doc) throw new Error('No document loaded')
    const fontConfig: FontConfig = JSON.parse(fontConfigJson)
    let resolvedFontBytes: Uint8Array | undefined

    if (fontConfig.koreanFont !== null) {
      const fontName =
        fontConfig.koreanFont === 'NanumMyeongjo'
          ? 'NanumMyeongjo-Regular.ttf'
          : 'NanumGothic-Regular.ttf'
      try {
        const resp = await fetch(`/fonts/${fontName}`)
        if (resp.ok) {
          resolvedFontBytes = new Uint8Array(await resp.arrayBuffer())
        }
      } catch {
        // 폰트 파일 없으면 fallback (graceful degradation)
      }
    }

    const effectiveFontBytes = fontBytes ?? resolvedFontBytes ?? null

    const result = doc.replace_text(
      pageIndex,
      new Float64Array(coverRect),
      newText,
      textX,
      textY,
      fontConfigJson,
      textStyleJson,
      effectiveFontBytes ?? null
    )
    // WASM replace_text는 PageInfo[] JsValue를 반환
    const json = JSON.stringify(result)
    return JSON.parse(json) as PageInfo[]
  },

  async addAnnotation(pageIndex: number, paramsJson: string): Promise<AnnotationInfo[]> {
    if (!doc) throw new Error('No document loaded')
    const result = doc.add_annotation(pageIndex, paramsJson)
    return JSON.parse(JSON.stringify(result)) as AnnotationInfo[]
  },

  async getAnnotations(pageIndex: number): Promise<AnnotationInfo[]> {
    if (!doc) throw new Error('No document loaded')
    const result = doc.get_annotations(pageIndex)
    return JSON.parse(JSON.stringify(result)) as AnnotationInfo[]
  },

  async deleteAnnotation(pageIndex: number, annotId: number): Promise<AnnotationInfo[]> {
    if (!doc) throw new Error('No document loaded')
    const result = doc.delete_annotation(pageIndex, annotId)
    return JSON.parse(JSON.stringify(result)) as AnnotationInfo[]
  },
}

Comlink.expose(api)
