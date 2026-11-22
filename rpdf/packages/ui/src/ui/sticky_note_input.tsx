import { useEffect, useRef, useState } from 'react'

interface StickyNoteInputProps {
  screenX: number
  screenY: number
  onConfirm: (text: string) => void
  onCancel: () => void
}

/**
 * 스티키노트 텍스트 입력 팝업.
 *
 * - Escape: cancel
 * - Enter (Shift+Enter는 줄바꿈): confirm
 */
export function StickyNoteInput({
  screenX,
  screenY,
  onConfirm,
  onCancel,
}: StickyNoteInputProps) {
  const [text, setText] = useState('')
  const inputRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Escape') {
      e.preventDefault()
      onCancel()
    } else if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      if (text.trim()) {
        onConfirm(text.trim())
      }
    }
  }

  function handleConfirmClick() {
    if (text.trim()) {
      onConfirm(text.trim())
    }
  }

  return (
    <div
      style={{
        position: 'absolute',
        left: screenX,
        top: screenY,
        zIndex: 100,
        background: '#fffde7',
        border: '1px solid #f59e0b',
        borderRadius: 4,
        padding: 8,
        boxShadow: '0 2px 8px rgba(0,0,0,0.18)',
        minWidth: 180,
      }}
    >
      <textarea
        ref={inputRef}
        value={text}
        onChange={e => setText(e.target.value)}
        onKeyDown={handleKeyDown}
        placeholder="메모를 입력하세요 (Enter 확인, Shift+Enter 줄바꿈)"
        rows={3}
        style={{
          width: '100%',
          border: '1px solid #d1d5db',
          borderRadius: 3,
          padding: 4,
          fontSize: 13,
          resize: 'vertical',
          background: '#fffde7',
          boxSizing: 'border-box',
        }}
      />
      <div style={{ display: 'flex', gap: 4, marginTop: 6, justifyContent: 'flex-end' }}>
        <button
          onClick={onCancel}
          style={{
            padding: '3px 10px',
            fontSize: 12,
            border: '1px solid #d1d5db',
            borderRadius: 3,
            background: '#fff',
            cursor: 'pointer',
          }}
        >
          취소
        </button>
        <button
          onClick={handleConfirmClick}
          disabled={!text.trim()}
          style={{
            padding: '3px 10px',
            fontSize: 12,
            border: '1px solid #2563eb',
            borderRadius: 3,
            background: text.trim() ? '#2563eb' : '#93c5fd',
            color: '#fff',
            cursor: text.trim() ? 'pointer' : 'not-allowed',
          }}
        >
          확인
        </button>
      </div>
    </div>
  )
}
