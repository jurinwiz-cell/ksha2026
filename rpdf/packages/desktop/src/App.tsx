import { isTauri } from '@tauri-apps/api/core'
import { useDocumentStore } from '@rpdf/ui'
import { DropZone } from '@rpdf/ui'
import { ThumbnailSidebar } from '@rpdf/ui'
import { Toolbar } from '@rpdf/ui'
import { PageViewer } from '@rpdf/ui'
import { openPdfWithNativeDialog, openTiffWithNativeDialog } from './core/tauri-dialog'
import { useDarkMode } from './core/use-dark-mode'
import { useKeyboardShortcuts } from './core/use-keyboard-shortcuts'

function App() {
  useDarkMode()
  const pages = useDocumentStore(s => s.pages)
  const error = useDocumentStore(s => s.error)
  const clearError = useDocumentStore(s => s.clearError)
  const selectedPageIndex = useDocumentStore(s => s.selectedPageIndex)
  const undoLen = useDocumentStore(s => s.undoLen)
  const redoLen = useDocumentStore(s => s.redoLen)
  const isLoading = useDocumentStore(s => s.isLoading)
  const deletePages = useDocumentStore(s => s.deletePages)
  const undo = useDocumentStore(s => s.undo)
  const redo = useDocumentStore(s => s.redo)
  const saveFile = useDocumentStore(s => s.saveFile)

  useKeyboardShortcuts({
    onOpen: () => { if (isTauri()) openPdfWithNativeDialog() },
    onSave: () => { if (pages.length > 0 && !isLoading) saveFile() },
    onDelete: () => { if (pages.length > 1 && !isLoading) deletePages([selectedPageIndex]) },
    onUndo: () => { if (undoLen > 0 && !isLoading) undo() },
    onRedo: () => { if (redoLen > 0 && !isLoading) redo() },
  })

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100vh', fontFamily: 'sans-serif' }}>
      {error && (
        <div
          role="alert"
          style={{ background: '#fee', color: '#c00', padding: '8px 16px', cursor: 'pointer' }}
          onClick={clearError}
        >
          {error} (클릭하여 닫기)
        </div>
      )}
      <Toolbar />
      {pages.length === 0 ? (
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column',
                      alignItems: 'center', justifyContent: 'center', padding: 40, gap: 16 }}>
          {isTauri() && (
            <div style={{ display: 'flex', gap: 12 }}>
              <button
                onClick={openPdfWithNativeDialog}
                style={{
                  padding: '10px 24px',
                  fontSize: 15,
                  cursor: 'pointer',
                  borderRadius: 6,
                  border: '1px solid #0070f3',
                  background: '#0070f3',
                  color: 'white',
                }}
              >
                파일 열기
              </button>
              <button
                onClick={openTiffWithNativeDialog}
                style={{
                  padding: '10px 24px',
                  fontSize: 15,
                  cursor: 'pointer',
                  borderRadius: 6,
                  border: '1px solid #6c757d',
                  background: '#6c757d',
                  color: 'white',
                }}
              >
                TIFF → PDF
              </button>
            </div>
          )}
          <DropZone />
        </div>
      ) : (
        <div style={{ display: 'flex', flex: 1, overflow: 'hidden' }}>
          <ThumbnailSidebar />
          <PageViewer />
        </div>
      )}
    </div>
  )
}

export default App
