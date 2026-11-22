import { useEffect, useRef, useState } from 'react'
import * as pdfjs from 'pdfjs-dist'
import { useDocumentStore } from '../state/document-store'
import type { AnnotationParams, TextBlock } from '../types/pdf'
import { AnnotationDrag } from './annotation_drag'
import { AnnotationLayer } from './annotation_layer'
import { AnnotationToolbar } from './annotation_toolbar'
import { StickyNoteInput } from './sticky_note_input'
import { TextBlockOverlay } from './text_block_overlay'
import { TextEditPanel } from './text_edit_panel'

pdfjs.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs'

interface StickyPending {
  pdfX: number
  pdfY: number
  screenX: number
  screenY: number
}

export function PageViewer() {
  const fileRef = useDocumentStore(s => s.fileRef)
  const pages = useDocumentStore(s => s.pages)
  const selectedPageIndex = useDocumentStore(s => s.selectedPageIndex)
  const textBlocks = useDocumentStore(s => s.textBlocks)
  const editingBlock = useDocumentStore(s => s.editingBlock)
  const fontConfig = useDocumentStore(s => s.fontConfig)
  const textStyle = useDocumentStore(s => s.textStyle)
  const annotations = useDocumentStore(s => s.annotations)
  const annotationMode = useDocumentStore(s => s.annotationMode)
  const loadTextBlocks = useDocumentStore(s => s.loadTextBlocks)
  const loadAnnotations = useDocumentStore(s => s.loadAnnotations)
  const setEditingBlock = useDocumentStore(s => s.setEditingBlock)
  const replaceText = useDocumentStore(s => s.replaceText)
  const setFontConfig = useDocumentStore(s => s.setFontConfig)
  const setTextStyle = useDocumentStore(s => s.setTextStyle)
  const addAnnotationAction = useDocumentStore(s => s.addAnnotation)
  const deleteAnnotationAction = useDocumentStore(s => s.deleteAnnotation)

  const canvasRef = useRef<HTMLCanvasElement>(null)
  const [pdfDoc, setPdfDoc] = useState<pdfjs.PDFDocumentProxy | null>(null)
  const [canvasW, setCanvasW] = useState(0)
  const [canvasH, setCanvasH] = useState(0)
  // D2: 편집 확정 후 로컬에서 새 텍스트를 오버레이로 표시
  const [confirmedTexts, setConfirmedTexts] = useState<Map<string, string>>(new Map())
  const [stickyPending, setStickyPending] = useState<StickyPending | null>(null)

  // PDF 문서 로드 (fileRef 변경 시 또는 pages.length 변경 시 재로드)
  useEffect(() => {
    if (!fileRef) return
    let cancelled = false
    fileRef.arrayBuffer().then(buf => {
      if (cancelled) return pdfjs.getDocument({ data: new Uint8Array(buf) }).promise
      return pdfjs.getDocument({ data: new Uint8Array(buf) }).promise
    }).then(doc => {
      if (cancelled) return
      setPdfDoc(doc)
    })
    return () => { cancelled = true }
  }, [fileRef, pages.length])

  // 선택 페이지 렌더링
  useEffect(() => {
    if (!pdfDoc || !canvasRef.current) return
    const pageNum = selectedPageIndex + 1 // pdf.js는 1-based
    if (pageNum < 1 || pageNum > pdfDoc.numPages) return

    let cancelled = false
    pdfDoc.getPage(pageNum).then(page => {
      if (cancelled || !canvasRef.current) return
      const scale = 1.5
      const viewport = page.getViewport({ scale })
      const canvas = canvasRef.current
      canvas.width = viewport.width
      canvas.height = viewport.height
      setCanvasW(viewport.width)
      setCanvasH(viewport.height)
      const ctx = canvas.getContext('2d')
      if (!ctx) return
      page.render({ canvasContext: ctx, viewport })
    })
    return () => { cancelled = true }
  }, [pdfDoc, selectedPageIndex])

  // selectedPageIndex 변경 시 텍스트 블록 및 주석 로드
  useEffect(() => {
    if (!fileRef || pages.length === 0) return
    loadTextBlocks(selectedPageIndex)
    loadAnnotations(selectedPageIndex)
  }, [selectedPageIndex, fileRef, pages.length]) // eslint-disable-line react-hooks/exhaustive-deps

  if (!fileRef || pages.length === 0) return null

  const currentPage = pages[selectedPageIndex]
  const rotation = currentPage?.rotation ?? 0
  const mediaBox = currentPage?.media_box ?? [0, 0, 595, 842]

  function makeBlockKey(block: TextBlock): string {
    return `${block.x}-${block.y}-${block.font_ref}`
  }

  function handleBlockClick(block: TextBlock) {
    setEditingBlock(block)
  }

  function handleConfirm(newText: string) {
    if (!editingBlock) return
    const key = makeBlockKey(editingBlock)
    setConfirmedTexts(prev => new Map(prev).set(key, newText))
    replaceText(selectedPageIndex, editingBlock, newText)
  }

  function handleCancel() {
    setEditingBlock(null)
  }

  function handleDragComplete(pdfRect: [number, number, number, number]) {
    const colorMap: Record<string, [number, number, number]> = {
      Highlight: [1.0, 1.0, 0.0],
      Underline: [0.0, 0.0, 0.8],
    }
    const color = colorMap[annotationMode] ?? [1.0, 1.0, 0.0]
    const opacity = annotationMode === 'Highlight' ? 0.3 : 1.0

    const params: AnnotationParams = {
      pageIndex: selectedPageIndex,
      annotType: annotationMode as 'Highlight' | 'Underline',
      rect: pdfRect,
      color,
      content: undefined,
      opacity,
    }
    addAnnotationAction(selectedPageIndex, params)
  }

  function handleClickPoint(pdfX: number, pdfY: number) {
    // StickyNote: 클릭 지점에서 입력 팝업 표시
    const screenCoord = {
      screenX: (pdfX - mediaBox[0]) / (mediaBox[2] - mediaBox[0]) * canvasW,
      screenY: (1 - (pdfY - mediaBox[1]) / (mediaBox[3] - mediaBox[1])) * canvasH,
    }
    setStickyPending({ pdfX, pdfY, screenX: screenCoord.screenX, screenY: screenCoord.screenY })
  }

  function handleStickyConfirm(text: string) {
    if (!stickyPending) return
    const { pdfX, pdfY } = stickyPending
    const noteSize = 20
    const params: AnnotationParams = {
      pageIndex: selectedPageIndex,
      annotType: 'StickyNote',
      rect: [pdfX, pdfY, pdfX + noteSize, pdfY + noteSize],
      color: [1.0, 1.0, 0.0],
      content: text,
      opacity: 1.0,
    }
    addAnnotationAction(selectedPageIndex, params)
    setStickyPending(null)
  }

  function handleStickyCancel() {
    setStickyPending(null)
  }

  function handleDeleteAnnotation(annotId: number) {
    deleteAnnotationAction(selectedPageIndex, annotId)
  }

  return (
    <div
      style={{
        flex: 1,
        overflow: 'auto',
        display: 'flex',
        flexDirection: 'column',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', padding: '4px 8px', borderBottom: '1px solid #e5e7eb', background: '#fafafa' }}>
        <AnnotationToolbar />
      </div>
      <div
        style={{
          flex: 1,
          overflow: 'auto',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: 20,
        }}
      >
        <div style={{ position: 'relative', display: 'inline-block' }}>
          <canvas
            ref={canvasRef}
            style={{
              transform: `rotate(${rotation}deg)`,
              maxWidth: '100%',
              boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
              display: 'block',
            }}
          />
          {canvasW > 0 && canvasH > 0 && (
            <div
              className="text-overlay-layer"
              style={{
                position: 'absolute',
                top: 0,
                left: 0,
                width: canvasW,
                height: canvasH,
                pointerEvents: annotationMode !== 'none' ? 'none' : 'auto',
              }}
            >
              <AnnotationLayer
                annotations={annotations}
                mediaBox={mediaBox}
                canvasW={canvasW}
                canvasH={canvasH}
                onDelete={handleDeleteAnnotation}
              />
              {textBlocks.map(block => {
                const key = makeBlockKey(block)
                const isEditing = editingBlock !== null && makeBlockKey(editingBlock) === key
                return (
                  <TextBlockOverlay
                    key={key}
                    block={block}
                    mediaBox={mediaBox}
                    canvasW={canvasW}
                    canvasH={canvasH}
                    isEditing={isEditing}
                    onClick={handleBlockClick}
                  />
                )
              })}
              {confirmedTexts.size > 0 &&
                textBlocks.map(block => {
                  const key = makeBlockKey(block)
                  const confirmedText = confirmedTexts.get(key)
                  if (!confirmedText) return null
                  return (
                    <div key={`confirmed-${key}`} style={{ position: 'absolute', fontSize: 10, color: '#6b7280', pointerEvents: 'none' }}>
                      {confirmedText}
                    </div>
                  )
                })}
              {editingBlock && (
                <TextEditPanel
                  block={editingBlock}
                  mediaBox={mediaBox}
                  canvasW={canvasW}
                  canvasH={canvasH}
                  fontConfig={fontConfig}
                  textStyle={textStyle}
                  onConfirm={handleConfirm}
                  onCancel={handleCancel}
                  onFontConfigChange={setFontConfig}
                  onTextStyleChange={setTextStyle}
                />
              )}
              {stickyPending && (
                <StickyNoteInput
                  screenX={stickyPending.screenX}
                  screenY={stickyPending.screenY}
                  onConfirm={handleStickyConfirm}
                  onCancel={handleStickyCancel}
                />
              )}
            </div>
          )}
          {canvasW > 0 && canvasH > 0 && (
            <AnnotationDrag
              annotationMode={annotationMode}
              mediaBox={mediaBox}
              canvasW={canvasW}
              canvasH={canvasH}
              onDragComplete={handleDragComplete}
              onClickPoint={handleClickPoint}
            />
          )}
        </div>
      </div>
    </div>
  )
}
