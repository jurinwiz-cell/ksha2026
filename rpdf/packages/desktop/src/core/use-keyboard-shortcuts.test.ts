import { act, renderHook } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useKeyboardShortcuts } from './use-keyboard-shortcuts'

function fireKey(key: string, opts: Partial<KeyboardEventInit> = {}, target?: EventTarget) {
  const event = new KeyboardEvent('keydown', { key, bubbles: true, ...opts })
  const dispatchTarget = target ?? window
  dispatchTarget.dispatchEvent(event)
}

describe('useKeyboardShortcuts', () => {
  let onOpen: ReturnType<typeof vi.fn>
  let onSave: ReturnType<typeof vi.fn>
  let onDelete: ReturnType<typeof vi.fn>
  let onUndo: ReturnType<typeof vi.fn>
  let onRedo: ReturnType<typeof vi.fn>

  beforeEach(() => {
    onOpen = vi.fn()
    onSave = vi.fn()
    onDelete = vi.fn()
    onUndo = vi.fn()
    onRedo = vi.fn()
  })

  it('Ctrl+O → onOpen 호출', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    act(() => fireKey('o', { ctrlKey: true }))
    expect(onOpen).toHaveBeenCalledTimes(1)
  })

  it('Meta+O → onOpen 호출', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    act(() => fireKey('o', { metaKey: true }))
    expect(onOpen).toHaveBeenCalledTimes(1)
  })

  it('Ctrl+S → onSave 호출', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    act(() => fireKey('s', { ctrlKey: true }))
    expect(onSave).toHaveBeenCalledTimes(1)
  })

  it('Delete → onDelete 호출', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    act(() => fireKey('Delete'))
    expect(onDelete).toHaveBeenCalledTimes(1)
  })

  it('Backspace → onDelete 호출 (Mac ⌫ 지원)', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    act(() => fireKey('Backspace'))
    expect(onDelete).toHaveBeenCalledTimes(1)
  })

  it('Ctrl+Z → onUndo 호출', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    act(() => fireKey('z', { ctrlKey: true }))
    expect(onUndo).toHaveBeenCalledTimes(1)
  })

  it('Ctrl+Y → onRedo 호출', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    act(() => fireKey('y', { ctrlKey: true }))
    expect(onRedo).toHaveBeenCalledTimes(1)
  })

  it('Ctrl+Shift+Z → onRedo 호출 (lowercase 처리 검증)', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    // shiftKey=true이면 브라우저는 e.key='Z'(대문자) 반환 — toLowerCase() 검증
    act(() => fireKey('Z', { ctrlKey: true, shiftKey: true }))
    expect(onRedo).toHaveBeenCalledTimes(1)
  })

  it('INPUT 포커스 중 Delete → onDelete 미호출', () => {
    renderHook(() => useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo }))
    const input = document.createElement('input')
    document.body.appendChild(input)
    act(() => fireKey('Delete', {}, input))
    document.body.removeChild(input)
    expect(onDelete).not.toHaveBeenCalled()
  })

  it('unmount 후 keydown → 핸들러 미호출 (cleanup 검증)', () => {
    const { unmount } = renderHook(() =>
      useKeyboardShortcuts({ onOpen, onSave, onDelete, onUndo, onRedo })
    )
    unmount()
    act(() => fireKey('s', { ctrlKey: true }))
    expect(onSave).not.toHaveBeenCalled()
  })
})
