import { useEffect, useState } from 'react'
import { getCurrentWindow } from '@tauri-apps/api/window'

export function useDarkMode(): boolean {
  const [isDark, setIsDark] = useState<boolean>(false)

  useEffect(() => {
    let mounted = true
    let unlisten: (() => void) | undefined

    async function init() {
      const win = getCurrentWindow()
      const theme = await win.theme()
      if (!mounted) return
      const dark = theme === 'dark'
      setIsDark(dark)
      applyTheme(dark)

      unlisten = await win.onThemeChanged(({ payload }) => {
        const dark = payload === 'dark'
        setIsDark(dark)
        applyTheme(dark)
      })
      // 이미 unmount됐다면 즉시 해제
      if (!mounted) unlisten()
    }

    init()
    return () => {
      mounted = false
      unlisten?.()
    }
  }, [])

  return isDark
}

function applyTheme(dark: boolean) {
  document.documentElement.dataset.theme = dark ? 'dark' : 'light'
}
