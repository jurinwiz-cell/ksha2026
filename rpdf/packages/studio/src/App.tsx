import { useDocumentStore } from '@rpdf/ui'
import { DropZone } from '@rpdf/ui'
import { ThumbnailSidebar } from '@rpdf/ui'
import { Toolbar } from '@rpdf/ui'
import { PageViewer } from '@rpdf/ui'

function App() {
  const pages = useDocumentStore(s => s.pages)
  const error = useDocumentStore(s => s.error)
  const clearError = useDocumentStore(s => s.clearError)

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
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 40 }}>
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
