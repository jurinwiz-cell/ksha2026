import type { DragEvent, ChangeEvent } from 'react'
import { useRef, useState } from 'react'
import { useDocumentStore } from '../state/document-store'

const ACCEPTED_MIME = 'application/pdf'

export function DropZone() {
  const openFile = useDocumentStore(s => s.openFile)
  const isLoading = useDocumentStore(s => s.isLoading)
  const [dragOver, setDragOver] = useState(false)
  const [dropError, setDropError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const handleFile = (file: File) => {
    if (file.type !== ACCEPTED_MIME) {
      setDropError('PDF 파일만 열 수 있습니다')
      return
    }
    setDropError(null)
    openFile(file)
  }

  const onDrop = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    setDragOver(false)
    const file = e.dataTransfer.files[0]
    if (file) handleFile(file)
  }

  const onDragOver = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    setDragOver(true)
  }

  const onDragLeave = () => setDragOver(false)

  const onInputChange = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) handleFile(file)
  }

  return (
    <div
      onDrop={onDrop}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
      style={{
        border: `2px dashed ${dragOver ? '#0070f3' : '#ccc'}`,
        borderRadius: 8,
        padding: 40,
        textAlign: 'center',
        cursor: 'pointer',
        background: dragOver ? '#f0f7ff' : 'transparent'
      }}
      onClick={() => inputRef.current?.click()}
    >
      <input
        ref={inputRef}
        type="file"
        accept={ACCEPTED_MIME}
        style={{ display: 'none' }}
        onChange={onInputChange}
      />
      {isLoading ? (
        <p>불러오는 중...</p>
      ) : (
        <>
          <p>PDF 파일을 여기에 드래그 드롭하거나 클릭하여 선택</p>
          {dropError && <p style={{ color: 'red' }}>{dropError}</p>}
        </>
      )}
    </div>
  )
}
