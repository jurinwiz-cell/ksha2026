import { useEffect, useState } from 'react'
import { useDocumentStore } from '../state/document-store'
import { renderPageToDataUrl } from '../core/pdf-renderer'

interface ThumbnailProps {
  pageIndex: number
}

function Thumbnail({ pageIndex }: ThumbnailProps) {
  const fileRef = useDocumentStore(s => s.fileRef)
  const selectedPageIndex = useDocumentStore(s => s.selectedPageIndex)
  const selectPage = useDocumentStore(s => s.selectPage)
  const deletePages = useDocumentStore(s => s.deletePages)
  const rotation = useDocumentStore(s => s.pages[pageIndex]?.rotation ?? 0)
  const [dataUrl, setDataUrl] = useState<string | null>(null)
  const [hovered, setHovered] = useState(false)
  const isSelected = selectedPageIndex === pageIndex

  useEffect(() => {
    if (!fileRef) return
    renderPageToDataUrl(fileRef, pageIndex).then(setDataUrl)
  }, [fileRef, pageIndex])

  return (
    <div
      onClick={() => selectPage(pageIndex)}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        position: 'relative',
        border: `2px solid ${isSelected ? '#0070f3' : '#ddd'}`,
        borderRadius: 4,
        padding: 4,
        cursor: 'pointer',
        background: isSelected ? '#e8f0fe' : 'white',
        marginBottom: 8
      }}
    >
      {dataUrl ? (
        <img
          src={dataUrl}
          alt={`페이지 ${pageIndex + 1}`}
          style={{ width: '100%', display: 'block', transform: `rotate(${rotation}deg)` }}
        />
      ) : (
        <div style={{ height: 80, background: '#f0f0f0', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          로딩...
        </div>
      )}
      <div style={{ fontSize: 11, textAlign: 'center', marginTop: 2 }}>
        {pageIndex + 1}
      </div>
      {hovered && (
        <button
          onClick={e => { e.stopPropagation(); deletePages([pageIndex]) }}
          style={{
            position: 'absolute',
            top: 4,
            right: 4,
            background: 'rgba(255,0,0,0.8)',
            color: 'white',
            border: 'none',
            borderRadius: '50%',
            width: 20,
            height: 20,
            cursor: 'pointer',
            fontSize: 12,
            lineHeight: '20px',
            textAlign: 'center'
          }}
        >
          ×
        </button>
      )}
    </div>
  )
}

export function ThumbnailSidebar() {
  const pages = useDocumentStore(s => s.pages)

  if (pages.length === 0) return null

  return (
    <div style={{ width: 140, overflowY: 'auto', borderRight: '1px solid #eee', padding: 8 }}>
      {pages.map((_, i) => (
        <Thumbnail key={i} pageIndex={i} />
      ))}
    </div>
  )
}
