import { pdfToScreen } from '../core/pdf-coords'
import type { AnnotationInfo } from '../types/pdf'

interface AnnotationLayerProps {
  annotations: AnnotationInfo[]
  mediaBox: [number, number, number, number]
  canvasW: number
  canvasH: number
  onDelete: (annotId: number) => void
}

/**
 * AnnotationInfo[] 목록을 PDF → 화면 좌표 변환 후 절대위치 div로 렌더링한다.
 *
 * - Highlight: 반투명 노란 div (opacity CA 값 적용)
 * - Underline: 해당 rect 하단에 파란 border-bottom 선
 * - StickyNote: 📝 아이콘, 클릭 시 내용 툴팁
 * - 오른쪽 클릭 또는 ×버튼 → onDelete 호출
 */
export function AnnotationLayer({
  annotations,
  mediaBox,
  canvasW,
  canvasH,
  onDelete,
}: AnnotationLayerProps) {
  if (annotations.length === 0) return null

  return (
    <>
      {annotations.map(annot => {
        const [rx0, ry0, rx1, ry1] = annot.rect
        const screenTL = pdfToScreen(rx0, ry1, mediaBox, canvasW, canvasH)
        const screenBR = pdfToScreen(rx1, ry0, mediaBox, canvasW, canvasH)

        const left = screenTL.x
        const top = screenTL.y
        const width = screenBR.x - screenTL.x
        const height = screenBR.y - screenTL.y

        function handleContextMenu(e: React.MouseEvent) {
          e.preventDefault()
          if (window.confirm('이 주석을 삭제하시겠습니까?')) {
            onDelete(annot.annotId)
          }
        }

        if (annot.annotType === 'Highlight') {
          const [r, g, b] = annot.color
          const alpha = annot.opacity
          return (
            <div
              key={annot.annotId}
              title="주석 (우클릭으로 삭제)"
              onContextMenu={handleContextMenu}
              style={{
                position: 'absolute',
                left,
                top,
                width: Math.max(width, 0),
                height: Math.max(height, 0),
                background: `rgba(${Math.round(r * 255)}, ${Math.round(g * 255)}, ${Math.round(b * 255)}, ${alpha})`,
                cursor: 'pointer',
                pointerEvents: 'auto',
              }}
            />
          )
        }

        if (annot.annotType === 'Underline') {
          const [r, g, b] = annot.color
          return (
            <div
              key={annot.annotId}
              title="밑줄 주석 (우클릭으로 삭제)"
              onContextMenu={handleContextMenu}
              style={{
                position: 'absolute',
                left,
                top,
                width: Math.max(width, 0),
                height: Math.max(height, 0),
                borderBottom: `2px solid rgb(${Math.round(r * 255)}, ${Math.round(g * 255)}, ${Math.round(b * 255)})`,
                cursor: 'pointer',
                pointerEvents: 'auto',
              }}
            />
          )
        }

        if (annot.annotType === 'StickyNote') {
          return (
            <div
              key={annot.annotId}
              title={annot.content ?? '(내용 없음)'}
              onContextMenu={handleContextMenu}
              style={{
                position: 'absolute',
                left,
                top,
                width: 20,
                height: 20,
                fontSize: 16,
                lineHeight: '20px',
                cursor: 'pointer',
                userSelect: 'none',
                pointerEvents: 'auto',
              }}
            >
              📝
            </div>
          )
        }

        return null
      })}
    </>
  )
}
