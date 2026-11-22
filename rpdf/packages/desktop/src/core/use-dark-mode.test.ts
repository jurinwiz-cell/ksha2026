import { act, renderHook, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useDarkMode } from './use-dark-mode'

const mockUnlisten = vi.fn()
const mockTheme = vi.fn()
const mockOnThemeChanged = vi.fn()

vi.mock('@tauri-apps/api/window', () => ({
  getCurrentWindow: () => ({
    theme: mockTheme,
    onThemeChanged: mockOnThemeChanged,
  }),
}))

beforeEach(() => {
  vi.clearAllMocks()
  mockOnThemeChanged.mockResolvedValue(mockUnlisten)
  document.documentElement.dataset.theme = ''
})

describe('useDarkMode', () => {
  it('초기 테마가 dark이면 isDark=true, data-theme=dark', async () => {
    mockTheme.mockResolvedValue('dark')
    const { result } = renderHook(() => useDarkMode())
    await waitFor(() => expect(result.current).toBe(true))
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('초기 테마가 light이면 isDark=false, data-theme=light', async () => {
    mockTheme.mockResolvedValue('light')
    const { result } = renderHook(() => useDarkMode())
    await waitFor(() => expect(result.current).toBe(false))
    expect(document.documentElement.dataset.theme).toBe('light')
  })

  it('theme null이면 isDark=false (라이트 기본값)', async () => {
    mockTheme.mockResolvedValue(null)
    const { result } = renderHook(() => useDarkMode())
    await waitFor(() => expect(result.current).toBe(false))
    expect(document.documentElement.dataset.theme).toBe('light')
  })

  it('onThemeChanged dark 이벤트 → isDark=true', async () => {
    mockTheme.mockResolvedValue('light')
    let handler: ((e: { payload: string }) => void) | undefined
    mockOnThemeChanged.mockImplementation((cb: (e: { payload: string }) => void) => {
      handler = cb
      return Promise.resolve(mockUnlisten)
    })
    const { result } = renderHook(() => useDarkMode())
    await waitFor(() => handler !== undefined)
    act(() => { handler!({ payload: 'dark' }) })
    await waitFor(() => expect(result.current).toBe(true))
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('onThemeChanged light 이벤트 → isDark=false', async () => {
    mockTheme.mockResolvedValue('dark')
    let handler: ((e: { payload: string }) => void) | undefined
    mockOnThemeChanged.mockImplementation((cb: (e: { payload: string }) => void) => {
      handler = cb
      return Promise.resolve(mockUnlisten)
    })
    const { result } = renderHook(() => useDarkMode())
    await waitFor(() => result.current === true)
    act(() => { handler!({ payload: 'light' }) })
    await waitFor(() => expect(result.current).toBe(false))
    expect(document.documentElement.dataset.theme).toBe('light')
  })

  it('unmount 시 unlisten 호출 (cleanup)', async () => {
    mockTheme.mockResolvedValue('dark')
    const { unmount } = renderHook(() => useDarkMode())
    await waitFor(() => expect(mockOnThemeChanged).toHaveBeenCalled())
    unmount()
    expect(mockUnlisten).toHaveBeenCalledTimes(1)
  })
})
