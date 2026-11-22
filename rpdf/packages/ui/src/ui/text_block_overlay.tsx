import { useState } from 'react'
import type { TextBlock } from '../types/pdf'
import { pdfToScreen } from '../core/pdf-coords'

interface TextBlockOverlayProps {
  block: TextBlock
  mediaBox: [number, number, number, number]
  canvasW: number
  canvasH: number
  isEditing: boolean
  onClick: (block: TextBlock) => void
}

export function TextBlockOverlay({
  block,
  mediaBox,
  canvasW,
  canvasH,
  isEditing,
  onClick,
}: TextBlockOverlayProps) {
  const [hovered, setHovered] = useState(false)

  const topLeft = pdfToScreen(block.x, block.y + block.height, mediaBox, canvasW, canvasH)
  const bottomRight = pdfToScreen(block.x + block.width, block.y, mediaBox, canvasW, canvasH)

  const left = topLeft.x
  const top = topLeft.y
  const width = bottomRight.x - topLeft.x
  const height = bottomRight.y - topLeft.y

  const borderColor = isEditing ? '#2563eb' : hovered ? 'rgba(59, 130, 246, 0.7)' : 'transparent'
  const backgroundColor = isEditing
    ? 'rgba(37, 99, 235, 0.15)'
    : hovered
      ? 'rgba(59, 130, 246, 0.08)'
      : 'transparent'

  return (
    <div
      style={{
        position: 'absolute',
        left,
        top,
        width: Math.max(width, 4),
        height: Math.max(height, 4),
        border: `1px solid ${borderColor}`,
        backgroundColor,
        cursor: 'pointer',
        boxSizing: 'border-box',
        transition: 'border-color 0.1s, background-color 0.1s',
      }}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      onClick={() => onClick(block)}
    />
  )
}
