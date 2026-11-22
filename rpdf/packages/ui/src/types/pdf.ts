// page_info() 반환 형식: { index, rotation, media_box, crop_box }
// rpdf_wasm.d.ts 주석 + crates/rpdf-wasm/src/ 소스 확인 결과: snake_case 필드
export interface PageInfo {
  index: number
  rotation: number
  media_box: [number, number, number, number] | null
  crop_box: [number, number, number, number] | null
}

// get_text_blocks() 반환 형식
export interface TextBlock {
  text: string
  x: number
  y: number
  width: number
  height: number
  font_size: number
  font_ref: string
  font_family: string | null
}

export interface FontConfig {
  koreanFont: 'NanumGothic' | 'NanumMyeongjo'
  latinFont: 'Helvetica' | 'TimesRoman' | 'Courier'
  fontSize: number
}

export interface TextStyle {
  bold: boolean
  italic: boolean
  underline: boolean
  strikethrough: boolean
  color: [number, number, number]
  alignment: 'Left' | 'Center' | 'Right'
}

export type AnnotationType = 'Highlight' | 'Underline' | 'StickyNote'

export interface AnnotationParams {
  pageIndex: number
  annotType: AnnotationType
  /** [x0, y0, x1, y1] PDF 좌표계 (정규화 완료) */
  rect: [number, number, number, number]
  /** RGB 0.0~1.0 */
  color: [number, number, number]
  content?: string
  opacity: number
}

export interface AnnotationInfo {
  annotId: number
  annotType: AnnotationType
  rect: [number, number, number, number]
  color: [number, number, number]
  content?: string
  opacity: number
}

export interface WasmApi {
  init(): Promise<void>
  open(bytes: Uint8Array): Promise<PageInfo[]>
  rotatePage(index: number, degrees: number): Promise<PageInfo[]>
  deletePages(indices: number[]): Promise<PageInfo[]>
  undo(): Promise<PageInfo[]>
  redo(): Promise<PageInfo[]>
  undoLen(): Promise<number>
  redoLen(): Promise<number>
  save(): Promise<Uint8Array>
  getTextBlocks(pageIndex: number): Promise<TextBlock[]>
  replaceText(
    pageIndex: number,
    coverRect: [number, number, number, number],
    newText: string,
    textX: number,
    textY: number,
    fontConfigJson: string,
    textStyleJson: string,
    fontBytes: Uint8Array | null
  ): Promise<PageInfo[]>
  addAnnotation(pageIndex: number, paramsJson: string): Promise<AnnotationInfo[]>
  getAnnotations(pageIndex: number): Promise<AnnotationInfo[]>
  deleteAnnotation(pageIndex: number, annotId: number): Promise<AnnotationInfo[]>
}
