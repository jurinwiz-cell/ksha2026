import { useDocumentStore } from '../state/document-store'
import type { AnnotationType } from '../types/pdf'

const ANNOTATION_MODES: Array<{ mode: AnnotationType; label: string; title: string }> = [
  { mode: 'Highlight', label: '하이라이트', title: '하이라이트 모드 (드래그로 영역 선택)' },
  { mode: 'Underline', label: '밑줄', title: '밑줄 모드 (드래그로 영역 선택)' },
  { mode: 'StickyNote', label: '스티키노트', title: '스티키노트 모드 (클릭으로 위치 지정)' },
]

export function AnnotationToolbar() {
  const annotationMode = useDocumentStore(s => s.annotationMode)
  const setAnnotationMode = useDocumentStore(s => s.setAnnotationMode)

  function handleModeClick(mode: AnnotationType) {
    setAnnotationMode(annotationMode === mode ? 'none' : mode)
  }

  return (
    <div
      style={{
        display: 'flex',
        gap: 4,
        alignItems: 'center',
        padding: '4px 8px',
        borderLeft: '1px solid #e5e7eb',
      }}
    >
      {ANNOTATION_MODES.map(({ mode, label, title }) => {
        const isActive = annotationMode === mode
        return (
          <button
            key={mode}
            title={title}
            onClick={() => handleModeClick(mode)}
            style={{
              padding: '4px 10px',
              fontSize: 13,
              borderRadius: 4,
              border: isActive ? '2px solid #2563eb' : '1px solid #d1d5db',
              background: isActive ? '#eff6ff' : '#fff',
              color: isActive ? '#2563eb' : '#374151',
              cursor: 'pointer',
              fontWeight: isActive ? 600 : 400,
              transition: 'all 0.15s',
            }}
          >
            {label}
          </button>
        )
      })}
    </div>
  )
}
