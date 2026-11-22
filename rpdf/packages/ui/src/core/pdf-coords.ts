/**
 * PDF 좌표계(좌하단 원점)를 화면 좌표계(좌상단 원점)로 변환한다.
 *
 * MediaBox 원점 오프셋(x0, y0)도 보정한다.
 */
export function pdfToScreen(
  pdfX: number,
  pdfY: number,
  mediaBox: [number, number, number, number],
  screenW: number,
  screenH: number
): { x: number; y: number } {
  const [x0, y0, x1, y1] = mediaBox
  const pdfW = x1 - x0
  const pdfH = y1 - y0
  return {
    x: (pdfX - x0) * (screenW / pdfW),
    y: (pdfH - (pdfY - y0)) * (screenH / pdfH),
  }
}

/**
 * 화면 좌표계(좌상단 원점)를 PDF 좌표계(좌하단 원점)로 변환한다.
 *
 * pdfToScreen의 수학적 역변환. MediaBox 원점 오프셋(x0, y0) 보정 포함.
 */
export function screenToPdf(
  screenX: number,
  screenY: number,
  mediaBox: [number, number, number, number],
  screenW: number,
  screenH: number
): { x: number; y: number } {
  const [x0, y0, x1, y1] = mediaBox
  const pdfW = x1 - x0
  const pdfH = y1 - y0
  return {
    x: screenX * (pdfW / screenW) + x0,
    y: pdfH - screenY * (pdfH / screenH) + y0,
  }
}
