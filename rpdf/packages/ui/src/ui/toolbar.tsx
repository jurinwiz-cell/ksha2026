import { useDocumentStore } from '../state/document-store'

export function Toolbar() {
  const pages = useDocumentStore(s => s.pages)
  const selectedPageIndex = useDocumentStore(s => s.selectedPageIndex)
  const undoLen = useDocumentStore(s => s.undoLen)
  const redoLen = useDocumentStore(s => s.redoLen)
  const isLoading = useDocumentStore(s => s.isLoading)
  const rotatePage = useDocumentStore(s => s.rotatePage)
  const undo = useDocumentStore(s => s.undo)
  const redo = useDocumentStore(s => s.redo)
  const saveFile = useDocumentStore(s => s.saveFile)

  if (pages.length === 0) return null

  const disabled = isLoading

  return (
    <div
      style={{
        display: 'flex',
        gap: 8,
        padding: '8px 16px',
        borderBottom: '1px solid #eee',
        alignItems: 'center'
      }}
    >
      <button
        disabled={disabled}
        onClick={() => rotatePage(selectedPageIndex, -90)}
      >
        ↺ -90°
      </button>
      <button
        disabled={disabled}
        onClick={() => rotatePage(selectedPageIndex, 90)}
      >
        ↻ +90°
      </button>

      <span
        style={{
          width: 1,
          background: '#ddd',
          alignSelf: 'stretch',
          margin: '0 4px'
        }}
      />

      <button
        disabled={disabled || undoLen === 0}
        onClick={undo}
        title={`Undo (${undoLen})`}
      >
        ↩ Undo
      </button>
      <button
        disabled={disabled || redoLen === 0}
        onClick={redo}
        title={`Redo (${redoLen})`}
      >
        ↪ Redo
      </button>

      <span
        style={{
          width: 1,
          background: '#ddd',
          alignSelf: 'stretch',
          margin: '0 4px'
        }}
      />

      <button
        disabled={true}
        title="준비 중 — WASM merge API 미지원"
        style={{ opacity: 0.4, cursor: 'not-allowed' }}
      >
        + PDF 병합
      </button>

      <span style={{ flex: 1 }} />

      <button
        disabled={disabled}
        onClick={saveFile}
        style={{
          background: '#0070f3',
          color: 'white',
          border: 'none',
          borderRadius: 4,
          padding: '6px 16px',
          cursor: disabled ? 'not-allowed' : 'pointer'
        }}
      >
        저장 (다운로드)
      </button>
    </div>
  )
}
