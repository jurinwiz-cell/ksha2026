import { useRef, useState } from 'react'
import { screenToPdf } from '../core/pdf-coords'
import type { AnnotationType } from '../types/pdf'

const MIN_DRAG_SIZE_PT = 5

interface AnnotationDragProps {
  annotationMode: 'none' | AnnotationType
  mediaBox: [number, number, number, number]
  canvasW: number
  canvasH: number
  onDragComplete: (pdfRect: [number, number, number, number]) => void
  onClickPoint: (pdfX: number, pdfY: number) => void
}

interface DragState {
  startX: number
  startY: number
  currentX: number
  currentY: number
  active: boolean
}

/**
 * canvas 위에 포개진 투명 div로 마우스 이벤트를 캡처한다.
 *
 * annotationMode !== 'none'일 때만 활성화된다.
 * mousedown → mousemove → mouseup으로 드래그 rect를 계산하며,
 * mouseup 시 D30 정규화: [Math.min(x0,x1), Math.min(y0,y1), Math.max(x0,x1), Math.max(y0,y1)]
 * StickyNote는 클릭 지점의 onClickPoint 콜백을 호출한다.
 */
export function AnnotationDrag({
  annotationMode,
  mediaBox,
  canvasW,
  canvasH,
  onDragComplete,
  onClickPoint,
}: AnnotationDragProps) {
  const [drag, setDrag] = useState<DragState | null>(null)
  const containerRef = useRef<HTMLDivElement>(null)

  if (annotationMode === 'none') return null

  function getRelativePos(e: React.MouseEvent): { x: number; y: number } {
    const rect = containerRef.current?.getBoundingClientRect()
    if (!rect) return { x: 0, y: 0 }
    return {
      x: e.clientX - rect.left,
      y: e.clientY - rect.top,
    }
  }

  function handleMouseDown(e: React.MouseEvent) {
    e.preventDefault()
    const { x, y } = getRelativePos(e)
    setDrag({ startX: x, startY: y, currentX: x, currentY: y, active: true })
  }

  function handleMouseMove(e: React.MouseEvent) {
    if (!drag?.active) return
    const { x, y } = getRelativePos(e)
    setDrag(prev => prev ? { ...prev, currentX: x, currentY: y } : null)
  }

  function handleMouseUp(e: React.MouseEvent) {
    if (!drag?.active) return
    const { x, y } = getRelativePos(e)
    setDrag(null)

    if (annotationMode === 'StickyNote') {
      const pdf = screenToPdf(x, y, mediaBox, canvasW, canvasH)
      onClickPoint(pdf.x, pdf.y)
      return
    }

    const startPdf = screenToPdf(drag.startX, drag.startY, mediaBox, canvasW, canvasH)
    const endPdf = screenToPdf(x, y, mediaBox, canvasW, canvasH)

    // D30: rect 정규화 — 드래그 방향 무관하게 올바른 /Rect 보장
    const x0 = Math.min(startPdf.x, endPdf.x)
    const y0 = Math.min(startPdf.y, endPdf.y)
    const x1 = Math.max(startPdf.x, endPdf.x)
    const y1 = Math.max(startPdf.y, endPdf.y)

    // 최소 크기 검사 — 너무 짧은 드래그 무시
    if (x1 - x0 < MIN_DRAG_SIZE_PT || y1 - y0 < MIN_DRAG_SIZE_PT) return

    onDragComplete([x0, y0, x1, y1])
  }

  function handleMouseLeave() {
    if (drag?.active) setDrag(null)
  }

  // 드래그 중 선택 영역 시각적 표시
  const selectionBox =
    drag?.active && annotationMode !== 'StickyNote'
      ? {
          left: Math.min(drag.startX, drag.currentX),
          top: Math.min(drag.startY, drag.currentY),
          width: Math.abs(drag.currentX - drag.startX),
          height: Math.abs(drag.currentY - drag.startY),
        }
      : null

  return (
    <div
      ref={containerRef}
      onMouseDown={handleMouseDown}
      onMouseMove={handleMouseMove}
      onMouseUp={handleMouseUp}
      onMouseLeave={handleMouseLeave}
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        width: canvasW,
        height: canvasH,
        cursor: annotationMode === 'StickyNote' ? 'crosshair' : 'text',
        userSelect: 'none',
        zIndex: 10,
      }}
    >
      {selectionBox && (
        <div
          style={{
            position: 'absolute',
            left: selectionBox.left,
            top: selectionBox.top,
            width: selectionBox.width,
            height: selectionBox.height,
            background:
              annotationMode === 'Highlight'
                ? 'rgba(253, 224, 71, 0.35)'
                : 'rgba(59, 130, 246, 0.2)',
            border:
              annotationMode === 'Highlight'
                ? '1px dashed #ca8a04'
                : '1px dashed #2563eb',
            pointerEvents: 'none',
          }}
        />
      )}
    </div>
  )
}
