import { useEffect, useRef } from 'react'

export interface ShortcutHandlers {
  onOpen: () => void
  onSave: () => void
  onDelete: () => void
  onUndo: () => void
  onRedo: () => void
}

export function useKeyboardShortcuts(handlers: ShortcutHandlers): void {
  const handlersRef = useRef(handlers)
  useEffect(() => {
    handlersRef.current = handlers
  })

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement
      if (['INPUT', 'TEXTAREA'].includes(target.tagName) || target.isContentEditable) return

      const isMod = e.metaKey || e.ctrlKey
      const key = e.key.toLowerCase()

      if (isMod && key === 'o') {
        e.preventDefault()
        handlersRef.current.onOpen()
      } else if (isMod && key === 's') {
        e.preventDefault()
        handlersRef.current.onSave()
      } else if (key === 'delete' || key === 'backspace') {
        e.preventDefault()
        handlersRef.current.onDelete()
      } else if (isMod && key === 'z' && !e.shiftKey) {
        e.preventDefault()
        handlersRef.current.onUndo()
      } else if (isMod && (key === 'y' || (key === 'z' && e.shiftKey))) {
        e.preventDefault()
        handlersRef.current.onRedo()
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [])
}
