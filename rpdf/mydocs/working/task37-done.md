# Task #37 완료 보고서: 창 상태 관리

**Issue**: #68 (closes)
**브랜치**: `local/task37`
**완료일**: 2026-05-20

---

## 구현 내용

### Rust 백엔드 (`lib.rs`, `Cargo.toml`)

`tauri-plugin-window-state = "2.4.1"` 추가.

- `use tauri_plugin_window_state::{StateFlags, WindowExt}` + `use tauri::Manager` import
- `Builder::default().with_state_flags(SIZE | POSITION).build()` 플러그인 등록
- `setup` hook: `if let Some(w) = app.get_webview_window("main") { w.restore_state(...).ok(); }` — 첫 실행 silently no-ops

### TypeScript (`use-dark-mode.ts`)

`useDarkMode()` 훅 신규:
- `getCurrentWindow().theme()` 초기값 + `onThemeChanged()` 이벤트 구독
- `mounted` 플래그로 async race condition 방지 (unmount 전 resolve 시 즉시 unlisten)
- `applyTheme(dark)` → `document.documentElement.dataset.theme = 'dark' | 'light'`

### CSS (`index.css`)

`:root` / `[data-theme="dark"]` CSS 변수 팔레트 (`--bg`, `--fg`, `--surface`, `--border`, `--primary`).
`main.tsx`에 `import './index.css'` 추가.

### App.tsx

`useDarkMode()` 호출 추가 (side-effect 전용).

### vitest 환경 구축

- devDependencies: `vitest ^2.1.8`, `jsdom ^29.0.0`, `@testing-library/react ^16.0.0`, `@testing-library/jest-dom ^6.0.0`
- `vite.config.ts`: `vitest/config` 기반으로 변경 + test 섹션 추가 (`environment: jsdom`, `globals: true`)
- `src/test-setup.ts` 신규: `@testing-library/jest-dom` import

---

## 검증 결과

| 항목 | 결과 |
|------|------|
| `cargo build -p rpdf-desktop` | Finished (0 errors) |
| `cargo clippy -p rpdf-desktop -- -D warnings` | 경고 없음 |
| `pnpm --filter @rpdf/desktop typecheck` | 통과 |
| `pnpm --filter @rpdf/desktop test` | 6 passed, 0 failed |

---

## 특이 사항 (트러블슈팅)

### A. `use tauri::Manager` 필수

- `app.get_webview_window("main")` 호출은 `tauri::Manager` trait scope 필요
- 계획서에 누락됐으나 컴파일 필수 → 추가
- **분류: C** (일회성, tauri 관련 이미 알려진 패턴)

### B. `vite.config.ts` → `vitest/config` 전환

- vitest test 섹션 타입 지원 위해 `vite`의 `defineConfig` 대신 `vitest/config`의 `defineConfig` 사용
- 기존 wasm, staticCopy 플러그인과 완전 호환
- **분류: B** (트러블슈팅 문서 후보)

### C. act() 경고 → 즉시 수정

- `onThemeChanged` 핸들러 직접 호출 시 act() 경고 발생
- `act(() => { handler!({...}) })` 로 수정 → 경고 제거
- **분류: C** (React Testing Library 기본 패턴)

---

## 회고 분류 표

| 항목 | 내용 | 분류 |
|------|------|------|
| `use tauri::Manager` 누락 | get_webview_window 사용 시 필수 | C |
| `vitest/config` vs `vite` defineConfig | vitest test 섹션 타입 지원 | B |
| act() 경고 패턴 | onThemeChanged 핸들러 호출 시 act() 필요 | C |

---

## 다음 단계

Task #38 — 단축키 바인딩 (Ctrl+O, Ctrl+S, Delete, Ctrl+Z 등)
