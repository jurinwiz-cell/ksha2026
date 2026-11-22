# Task #37 계획서: 창 상태 관리

**Issue**: #68
**브랜치**: `local/task37`
**마일스톤**: v0.5

---

## 목표

앱 재시작 후에도 창 크기·위치를 복원하고, OS 다크 모드를 감지해 React UI에 반영한다.

---

## 환경 (실제 설치 버전)

| 항목 | 버전 |
|------|------|
| tauri-plugin-window-state (Rust) | 2.4.1 |
| @tauri-apps/api | 2.11.0 |
| vitest (신규 추가) | 2.1.8 |
| jsdom (신규 추가) | 29 |
| tauri (Rust) | 2 |

---

## 범위

### In Scope

1. **창 상태 영속화**: `tauri-plugin-window-state` 플러그인으로 크기·위치 저장/복원 (Rust 전용)
2. **다크 모드 감지**: `@tauri-apps/api/window`의 `getCurrentWindow().theme()` + `onThemeChanged()` 이벤트
3. **CSS 변수**: `document.documentElement`에 `data-theme` attribute 설정, `:root` CSS 변수로 최소 다크/라이트 팔레트 제공
4. **React 훅** `useDarkMode`: 다크 모드 상태 구독 + 초기값 설정 + race condition 방지
5. **vitest 설정**: @rpdf/desktop에 테스트 환경 추가

### Out of Scope

- 멀티 창 지원
- 테마 수동 전환 UI (OS 감지 전용)
- 전체 UI 다크 모드 스타일링 (최소 CSS 변수만 제공)
- FOUC 방지 (theme IPC async 특성상 경미한 flash 허용 — 향후 TODO)
- `@tauri-apps/plugin-window-state` npm 패키지 (Rust 전용이므로 YAGNI)

---

## 데이터 흐름

```
OS 다크 모드 변경
  → Tauri onThemeChanged event
    → useDarkMode hook
      → document.documentElement.dataset.theme = 'dark' | 'light'
        → CSS :root var 적용
```

---

## 구현 명세

### 1. Rust: tauri-plugin-window-state 등록

**파일**: `packages/desktop/src-tauri/Cargo.toml`

```toml
tauri-plugin-window-state = "2.4.1"
```

**파일**: `packages/desktop/src-tauri/src/lib.rs`

```rust
use tauri_plugin_window_state::{StateFlags, WindowExt};

pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_fs::init())
        .plugin(
            tauri_plugin_window_state::Builder::default()
                .with_state_flags(StateFlags::SIZE | StateFlags::POSITION)
                .build()
        )
        .setup(|app| {
            // 창 시작 시 저장된 상태 복원 (첫 실행 시 silently no-ops)
            if let Some(w) = app.get_webview_window("main") {
                w.restore_state(StateFlags::SIZE | StateFlags::POSITION).ok();
            }
            Ok(())
        })
        .manage(AppState::default())
        .invoke_handler(tauri::generate_handler![...])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
```

> **핵심**: `WindowExt` trait import 필수. `.ok()`로 복원 실패를 의도적으로 무시 (첫 실행 등).

### 2. capabilities: 기존 권한만 유지

**파일**: `packages/desktop/src-tauri/capabilities/default.json`

window-state 권한은 **추가하지 않는다** — Rust 전용 복원이므로 JS IPC 불필요.

```json
{
  "permissions": [
    "core:default",
    "dialog:allow-open",
    "fs:allow-read-file",
    "fs:scope-home-recursive"
  ]
}
```

### 3. TypeScript 훅: `useDarkMode`

**파일**: `packages/desktop/src/core/use-dark-mode.ts`

```typescript
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
```

> **Race condition 방지**: `mounted` 플래그로 unmount 후 resolve 시 리스너 누수 방지.

### 4. CSS 변수: 최소 팔레트

**파일**: `packages/desktop/src/index.css` (신규)

```css
:root {
  --bg: #ffffff;
  --fg: #111111;
  --surface: #f5f5f5;
  --border: #dddddd;
  --primary: #0070f3;
}

[data-theme="dark"] {
  --bg: #1a1a1a;
  --fg: #eeeeee;
  --surface: #2a2a2a;
  --border: #444444;
  --primary: #3b9eff;
}

body {
  background: var(--bg);
  color: var(--fg);
  margin: 0;
}
```

`main.tsx` 상단에 `import './index.css'` 추가.

### 5. App.tsx: useDarkMode 연결

**파일**: `packages/desktop/src/App.tsx`

```typescript
import { useDarkMode } from './core/use-dark-mode'

function App() {
  useDarkMode()  // 사이드 이펙트: document.documentElement.dataset.theme 설정
  // ...기존 코드
}
```

### 6. vitest 설정 추가

**파일**: `packages/desktop/package.json` — devDependencies 추가:

```json
{
  "scripts": {
    "test": "vitest run"
  },
  "devDependencies": {
    "vitest": "^2.1.8",
    "jsdom": "^29.0.0",
    "@testing-library/react": "^16.0.0",
    "@testing-library/jest-dom": "^6.0.0"
  }
}
```

**파일**: `packages/desktop/vite.config.ts` — test 섹션 추가:

```typescript
export default defineConfig({
  // ...기존 설정
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test-setup.ts'],
  },
})
```

**파일**: `packages/desktop/src/test-setup.ts` (신규):

```typescript
import '@testing-library/jest-dom'
```

---

## 에러 처리 표

| 상황 | 처리 |
|------|------|
| `win.theme()` null 반환 (theme 감지 불가) | `isDark = false` (기본 라이트 모드) |
| `win.onThemeChanged()` 실패 (권한 없음 등) | console.error 로그, 훅은 초기값 유지 |
| 저장된 창 상태 없음 (첫 실행) | `restore_state().ok()` silently no-ops, `tauri.conf.json` 기본값 사용 |
| 창 상태 파일 손상 | 플러그인이 기본값으로 fallback (플러그인 자체 처리) |
| unmount 전 onThemeChanged resolve | mounted flag로 리스너 즉시 해제, 상태 업데이트 없음 |

---

## 테스트 전략

### 단위 테스트 (vitest, 6개)

**파일**: `packages/desktop/src/core/use-dark-mode.test.ts`

```typescript
import { renderHook, waitFor } from '@testing-library/react'
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
    mockOnThemeChanged.mockImplementation((cb) => { handler = cb; return Promise.resolve(mockUnlisten) })
    const { result } = renderHook(() => useDarkMode())
    await waitFor(() => handler !== undefined)
    handler!({ payload: 'dark' })
    await waitFor(() => expect(result.current).toBe(true))
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('onThemeChanged light 이벤트 → isDark=false', async () => {
    mockTheme.mockResolvedValue('dark')
    let handler: ((e: { payload: string }) => void) | undefined
    mockOnThemeChanged.mockImplementation((cb) => { handler = cb; return Promise.resolve(mockUnlisten) })
    const { result } = renderHook(() => useDarkMode())
    await waitFor(() => result.current === true)
    handler!({ payload: 'light' })
    await waitFor(() => expect(result.current).toBe(false))
  })

  it('unmount 시 unlisten 호출 (cleanup)', async () => {
    mockTheme.mockResolvedValue('dark')
    const { unmount } = renderHook(() => useDarkMode())
    await waitFor(() => expect(mockOnThemeChanged).toHaveBeenCalled())
    unmount()
    expect(mockUnlisten).toHaveBeenCalledTimes(1)
  })
})
```

### 런타임 검증 (수동, CI 불가)

창 상태 영속화는 실제 Tauri 런타임에서만 검증 가능:
- `pnpm tauri dev` 실행 → 창 크기 변경 → 앱 종료 → 재시작 → 크기 복원 확인

> **이유**: `tauri-plugin-window-state`는 실제 WebviewWindow를 통해 동작하며, 유닛 테스트로 커버 불가.

---

## 파일 변경 목록

| 파일 | 변경 |
|------|------|
| `packages/desktop/src-tauri/Cargo.toml` | `tauri-plugin-window-state = "2.4.1"` 추가 |
| `packages/desktop/src-tauri/src/lib.rs` | plugin 등록 + setup hook (`WindowExt` import + `if let` 패턴) |
| `packages/desktop/src-tauri/capabilities/default.json` | 변경 없음 (window-state JS 권한 불필요) |
| `packages/desktop/package.json` | vitest + jsdom + @testing-library/react 추가, test 스크립트 추가 |
| `packages/desktop/vite.config.ts` | test 섹션 추가 (jsdom, globals, setupFiles) |
| `packages/desktop/src/test-setup.ts` | 신규 (jest-dom import) |
| `packages/desktop/src/core/use-dark-mode.ts` | 신규 훅 (mounted flag 패턴) |
| `packages/desktop/src/core/use-dark-mode.test.ts` | vitest 테스트 6개 |
| `packages/desktop/src/index.css` | 신규 (CSS 변수 팔레트) |
| `packages/desktop/src/main.tsx` | `import './index.css'` 추가 |
| `packages/desktop/src/App.tsx` | `useDarkMode()` 호출 추가 |

---

## 체크포인트

1. `cargo build -p rpdf-desktop` 통과 (window-state 플러그인 컴파일)
2. `pnpm --filter @rpdf/desktop typecheck` 통과
3. `pnpm --filter @rpdf/desktop test` 통과 (useDarkMode 6개 테스트)
4. 런타임 수동 검증 (창 상태 복원, 다크 모드 전환)

---

## 공개 API 확인 완료

- `tauri-plugin-window-state` 2.4.1: `Builder::default().build()`, `StateFlags`, `WindowExt::restore_state()` 확인
- `@tauri-apps/api/window` 2.11.0: `getCurrentWindow().theme()`, `onThemeChanged()` 확인
- `core:default` permissions: window theme API 포함 확인

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | Scope & strategy | 0 | — | — |
| Codex Review | `/codex review` | Independent 2nd opinion | 1 | issues_found | Gemini outside voice: 5 findings, 4 accepted |
| Eng Review | `/plan-eng-review` | Architecture & tests (required) | 1 | CLEAR | 5 issues, 0 critical gaps |
| Design Review | `/plan-design-review` | UI/UX gaps | 0 | — | — |
| DX Review | `/plan-devex-review` | Developer experience gaps | 0 | — | — |

- **CROSS-MODEL:** Gemini와 이 리뷰 모두 WindowExt import 누락 지적. Race condition은 Gemini 독립 발견.
- **UNRESOLVED:** 0
- **VERDICT:** ENG CLEARED — 구현 시작 가능
