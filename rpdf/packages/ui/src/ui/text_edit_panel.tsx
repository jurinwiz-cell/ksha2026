import { useEffect, useRef, useState } from 'react'
import type { TextBlock, FontConfig, TextStyle } from '../types/pdf'
import { pdfToScreen } from '../core/pdf-coords'

const KOREAN_FONTS = ['NanumGothic', 'NanumMyeongjo'] as const
const LATIN_FONTS = ['Helvetica', 'TimesRoman', 'Courier'] as const
const FONT_SIZES = [8, 10, 12, 14, 18, 24, 36, 48, 72] as const

interface PaletteColor {
  label: string
  rgb: [number, number, number]
  hex: string
}

const COLOR_PALETTE: PaletteColor[] = [
  { label: '검정', rgb: [0, 0, 0], hex: '#000000' },
  { label: '빨강', rgb: [0.8, 0, 0], hex: '#cc0000' },
  { label: '파랑', rgb: [0, 0, 0.8], hex: '#0000cc' },
  { label: '초록', rgb: [0, 0.6, 0], hex: '#009900' },
  { label: '주황', rgb: [0.9, 0.5, 0], hex: '#e68000' },
  { label: '보라', rgb: [0.5, 0, 0.5], hex: '#800080' },
  { label: '분홍', rgb: [0.9, 0.4, 0.5], hex: '#e66680' },
  { label: '회색', rgb: [0.5, 0.5, 0.5], hex: '#808080' },
]

function rgbToHex(rgb: [number, number, number]): string {
  const [r, g, b] = rgb.map(v => Math.round(v * 255))
  return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`
}

function hexToRgb(hex: string): [number, number, number] | null {
  const cleaned = hex.replace('#', '')
  if (cleaned.length !== 6) return null
  const r = parseInt(cleaned.slice(0, 2), 16)
  const g = parseInt(cleaned.slice(2, 4), 16)
  const b = parseInt(cleaned.slice(4, 6), 16)
  if (isNaN(r) || isNaN(g) || isNaN(b)) return null
  return [r / 255, g / 255, b / 255]
}

interface TextEditPanelProps {
  block: TextBlock
  mediaBox: [number, number, number, number]
  canvasW: number
  canvasH: number
  fontConfig: FontConfig
  textStyle: TextStyle
  onConfirm: (newText: string) => void
  onCancel: () => void
  onFontConfigChange: (config: FontConfig) => void
  onTextStyleChange: (style: TextStyle) => void
}

export function TextEditPanel({
  block,
  mediaBox,
  canvasW,
  canvasH,
  fontConfig,
  textStyle,
  onConfirm,
  onCancel,
  onFontConfigChange,
  onTextStyleChange,
}: TextEditPanelProps) {
  const [text, setText] = useState(block.text)
  const [hexInput, setHexInput] = useState(rgbToHex(textStyle.color))
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    textareaRef.current?.focus()
  }, [])

  const bottomLeft = pdfToScreen(block.x, block.y, mediaBox, canvasW, canvasH)
  const panelLeft = Math.min(bottomLeft.x, canvasW - 290)
  const panelTop = bottomLeft.y + 4

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Escape') {
      e.preventDefault()
      onCancel()
    } else if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      onConfirm(text)
    }
  }

  function toggleStyle(key: keyof Pick<TextStyle, 'bold' | 'italic' | 'underline' | 'strikethrough'>) {
    onTextStyleChange({ ...textStyle, [key]: !textStyle[key] })
  }

  function setAlignment(alignment: TextStyle['alignment']) {
    onTextStyleChange({ ...textStyle, alignment })
  }

  function applyPaletteColor(rgb: [number, number, number]) {
    setHexInput(rgbToHex(rgb))
    onTextStyleChange({ ...textStyle, color: rgb })
  }

  function handleHexChange(value: string) {
    setHexInput(value)
    const rgb = hexToRgb(value)
    if (rgb) {
      onTextStyleChange({ ...textStyle, color: rgb })
    }
  }

  const styleButtonBase: React.CSSProperties = {
    width: 28,
    height: 26,
    border: '1px solid #d1d5db',
    borderRadius: 4,
    cursor: 'pointer',
    fontSize: 12,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  }

  function styleButtonStyle(active: boolean): React.CSSProperties {
    return {
      ...styleButtonBase,
      backgroundColor: active ? '#2563eb' : '#f9fafb',
      color: active ? '#fff' : '#374151',
    }
  }

  const alignButtonBase: React.CSSProperties = {
    flex: 1,
    height: 26,
    border: '1px solid #d1d5db',
    borderRadius: 4,
    cursor: 'pointer',
    fontSize: 11,
  }

  function alignButtonStyle(active: boolean): React.CSSProperties {
    return {
      ...alignButtonBase,
      backgroundColor: active ? '#2563eb' : '#f9fafb',
      color: active ? '#fff' : '#374151',
    }
  }

  return (
    <div
      style={{
        position: 'absolute',
        left: panelLeft,
        top: panelTop,
        width: 290,
        backgroundColor: '#fff',
        border: '1px solid #d1d5db',
        borderRadius: 6,
        boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
        padding: 10,
        zIndex: 100,
        display: 'flex',
        flexDirection: 'column',
        gap: 8,
      }}
    >
      <textarea
        ref={textareaRef}
        value={text}
        onChange={e => setText(e.target.value)}
        onKeyDown={handleKeyDown}
        rows={3}
        style={{
          width: '100%',
          resize: 'vertical',
          border: '1px solid #d1d5db',
          borderRadius: 4,
          padding: '4px 6px',
          fontSize: 13,
          fontFamily: 'inherit',
          boxSizing: 'border-box',
        }}
      />
      {/* 폰트 툴바 */}
      <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
        <select
          value={fontConfig.koreanFont}
          onChange={e =>
            onFontConfigChange({
              ...fontConfig,
              koreanFont: e.target.value as FontConfig['koreanFont'],
            })
          }
          style={{ flex: 1, fontSize: 12, padding: '2px 4px', minWidth: 0 }}
        >
          {KOREAN_FONTS.map(f => (
            <option key={f} value={f}>
              {f}
            </option>
          ))}
        </select>
        <select
          value={fontConfig.latinFont}
          onChange={e =>
            onFontConfigChange({
              ...fontConfig,
              latinFont: e.target.value as FontConfig['latinFont'],
            })
          }
          style={{ flex: 1, fontSize: 12, padding: '2px 4px', minWidth: 0 }}
        >
          {LATIN_FONTS.map(f => (
            <option key={f} value={f}>
              {f}
            </option>
          ))}
        </select>
        <select
          value={fontConfig.fontSize}
          onChange={e =>
            onFontConfigChange({ ...fontConfig, fontSize: Number(e.target.value) })
          }
          style={{ width: 56, fontSize: 12, padding: '2px 4px' }}
        >
          {FONT_SIZES.map(s => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </div>
      {/* 서식 버튼 (B/I/U/S) */}
      <div style={{ display: 'flex', gap: 4 }}>
        <button type="button" style={styleButtonStyle(textStyle.bold)} onClick={() => toggleStyle('bold')}>
          B
        </button>
        <button type="button" style={{ ...styleButtonStyle(textStyle.italic), fontStyle: 'italic' }} onClick={() => toggleStyle('italic')}>
          I
        </button>
        <button type="button" style={{ ...styleButtonStyle(textStyle.underline), textDecoration: 'underline' }} onClick={() => toggleStyle('underline')}>
          U
        </button>
        <button type="button" style={{ ...styleButtonStyle(textStyle.strikethrough), textDecoration: 'line-through' }} onClick={() => toggleStyle('strikethrough')}>
          S
        </button>
      </div>
      {/* 색상 팔레트 */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        <div style={{ display: 'flex', gap: 4 }}>
          {COLOR_PALETTE.map(c => (
            <button
              key={c.hex}
              type="button"
              title={c.label}
              onClick={() => applyPaletteColor(c.rgb)}
              style={{
                width: 20,
                height: 20,
                borderRadius: '50%',
                backgroundColor: c.hex,
                border: rgbToHex(textStyle.color) === c.hex ? '2px solid #2563eb' : '1px solid #d1d5db',
                cursor: 'pointer',
                padding: 0,
              }}
            />
          ))}
        </div>
        <input
          type="text"
          value={hexInput}
          onChange={e => handleHexChange(e.target.value)}
          placeholder="#000000"
          style={{
            width: '100%',
            fontSize: 12,
            padding: '2px 6px',
            border: '1px solid #d1d5db',
            borderRadius: 4,
            boxSizing: 'border-box',
          }}
        />
      </div>
      {/* 정렬 버튼 */}
      <div style={{ display: 'flex', gap: 4 }}>
        <button type="button" style={alignButtonStyle(textStyle.alignment === 'Left')} onClick={() => setAlignment('Left')}>
          Left
        </button>
        <button type="button" style={alignButtonStyle(textStyle.alignment === 'Center')} onClick={() => setAlignment('Center')}>
          Center
        </button>
        <button type="button" style={alignButtonStyle(textStyle.alignment === 'Right')} onClick={() => setAlignment('Right')}>
          Right
        </button>
      </div>
      {/* 확인/취소 버튼 */}
      <div style={{ display: 'flex', gap: 6, justifyContent: 'flex-end' }}>
        <button
          type="button"
          onClick={() => onConfirm(text)}
          style={{
            padding: '3px 10px',
            fontSize: 13,
            backgroundColor: '#2563eb',
            color: '#fff',
            border: 'none',
            borderRadius: 4,
            cursor: 'pointer',
          }}
        >
          ✓
        </button>
        <button
          type="button"
          onClick={onCancel}
          style={{
            padding: '3px 10px',
            fontSize: 13,
            backgroundColor: '#6b7280',
            color: '#fff',
            border: 'none',
            borderRadius: 4,
            cursor: 'pointer',
          }}
        >
          ✗
        </button>
      </div>
    </div>
  )
}
