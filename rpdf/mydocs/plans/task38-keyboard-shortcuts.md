# Task #38 계획서: 키보드 단축키 바인딩

**Issue**: #71  
**브랜치**: `local/task38`  
**마일스톤**: v0.5 Tauri Desktop

---

## 목표

Tauri 데스크톱 앱에서 주요 작업을 키보드 단축키로 실행할 수 있게 한다.

---

## 단축키 목록

| 단축키 | 동작 | 조건 |
|--------|------|------|
| Ctrl/Cmd+O | PDF 파일 열기 | 항상 (isTauri()만 체크) |
| Ctrl/Cmd+S | 파일 저장 | PDF 열려 있을 때 |
| Delete | 선택 페이지 삭제 | PDF 열려 있고 페이지 수 > 1 |
| Ctrl/Cmd+Z | Undo | undoLen > 0 |
| Ctrl/Cmd+Y / Ctrl/Cmd+Shift+Z | Redo | redoLen > 0 |

---

## 설계

### 접근 방식: window keydown 리스너

- `@tauri-apps/plugin-global-shortcut` **제외** — 앱 unfocused 시에도 발동하는 전역 단축키는 이 앱에 불필요 (YAGNI)
- `window.addEventListener('keydown', handler)` 방식 — 앱 포커스 시에만 동작, 표준 데스크톱 앱 동작과 일치

### 새 훅: `use-keyboard-shortcuts.ts`

위치: `packages/desktop/src/core/use-keyboard-shortcuts.ts`

```typescript
export interface ShortcutHandlers {
  onOpen: () => void
  onSave: () => void
  onDelete: () => void
  onUndo: () => void
  onRedo: () => void
}

export function useKeyboardShortcuts(handlers: ShortcutHandlers): void
```

> **D2 결정**: `ShortcutHandlers`를 `export`해 App.tsx에서 명시적 타입 사용 보장.

- `useEffect` 내에서 `window.addEventListener('keydown', handleKeyDown)` 등록
- cleanup에서 `removeEventListener`
- `handlers`를 ref로 감싸서 매 render마다 listener 재등록 방지

#### 크로스 플랫폼 modifier 처리

```typescript
const isMod = (e: KeyboardEvent) => e.metaKey || e.ctrlKey
```

macOS: `Cmd` (metaKey), Windows/Linux: `Ctrl` (ctrlKey) 공통 처리

#### keydown 조건 분기 pseudo-code

```
handleKeyDown(e):
  // D4: INPUT/TEXTAREA/contentEditable에서는 단축키 무시
  const tag = (e.target as HTMLElement).tagName
  if (['INPUT', 'TEXTAREA'].includes(tag) || (e.target as HTMLElement).isContentEditable) return

  // D5: e.key.toLowerCase() 사용 — Shift/CapsLock 대소문자 독립
  const key = e.key.toLowerCase()

  if isMod(e) && key === 'o': e.preventDefault(); onOpen()
  if isMod(e) && key === 's': e.preventDefault(); onSave()
  // D6: Mac Backspace(⌫) = Delete 동일 처리
  if key === 'delete' || key === 'backspace': e.preventDefault(); onDelete()
  if isMod(e) && key === 'z' && !e.shiftKey: e.preventDefault(); onUndo()
  if isMod(e) && (key === 'y' || (key === 'z' && e.shiftKey)): e.preventDefault(); onRedo()
```

#### handlers ref 패턴 (listener 재등록 없이 최신 handler 보장)

```typescript
const handlersRef = useRef(handlers)
useEffect(() => { handlersRef.current = handlers })

useEffect(() => {
  const handler = (e: KeyboardEvent) => { /* handlersRef.current 사용 */ }
  window.addEventListener('keydown', handler)
  return () => window.removeEventListener('keydown', handler)
}, []) // 의존성 없음 — ref로 최신값 보장
```

### App.tsx 변경

`useKeyboardShortcuts` 호출 추가:

```typescript
const pages = useDocumentStore(s => s.pages)
const selectedPageIndex = useDocumentStore(s => s.selectedPageIndex)
const undoLen = useDocumentStore(s => s.undoLen)
const redoLen = useDocumentStore(s => s.redoLen)
const isLoading = useDocumentStore(s => s.isLoading)
const deletePages = useDocumentStore(s => s.deletePages)
const undo = useDocumentStore(s => s.undo)
const redo = useDocumentStore(s => s.redo)
const saveFile = useDocumentStore(s => s.saveFile)

useKeyboardShortcuts({
  onOpen: () => { if (isTauri()) openPdfWithNativeDialog() },
  onSave: () => { if (pages.length > 0 && !isLoading) saveFile() },
  onDelete: () => { if (pages.length > 1 && !isLoading) deletePages([selectedPageIndex]) },
  onUndo: () => { if (undoLen > 0 && !isLoading) undo() },
  onRedo: () => { if (redoLen > 0 && !isLoading) redo() },
})
```

---

## 테스트 전략

### 파일: `use-keyboard-shortcuts.test.ts`

`@testing-library/react`의 `renderHook` 사용. `KeyboardEvent`를 `window.dispatchEvent`로 발송.

#### 테스트 케이스 (10개)

| # | 설명 |
|---|------|
| 1 | Ctrl+O → onOpen 호출 |
| 2 | Meta+O → onOpen 호출 |
| 3 | Ctrl+S → onSave 호출 |
| 4 | Delete → onDelete 호출 |
| 5 | Backspace → onDelete 호출 (D6: Mac ⌫ 지원) |
| 6 | Ctrl+Z → onUndo 호출 |
| 7 | Ctrl+Y → onRedo 호출 |
| 8 | Ctrl+Shift+Z → onRedo 호출 (D5: lowercase 수정 검증) |
| 9 | INPUT 포커스 중 Delete → onDelete 미호출 (D4: input guard) |
| 10 | unmount 후 keydown → 핸들러 미호출 (cleanup 검증) |

---

## 파일 변경 목록

| 파일 | 변경 |
|------|------|
| `packages/desktop/src/core/use-keyboard-shortcuts.ts` | 신규 |
| `packages/desktop/src/core/use-keyboard-shortcuts.test.ts` | 신규 |
| `packages/desktop/src/App.tsx` | `useKeyboardShortcuts` 호출 추가 |

---

## 에러 처리 표

| 상황 | 처리 |
|------|------|
| `isLoading === true` 중 단축키 | handler 내부에서 조건 검사로 무시 |
| `pages.length === 0` 시 Ctrl+S | onSave 핸들러에서 조건 검사로 무시 |
| `pages.length <= 1` 시 Delete | onDelete 핸들러에서 조건 검사로 무시 |
| `undoLen === 0` 시 Ctrl+Z | onUndo 핸들러에서 조건 검사로 무시 |
| `redoLen === 0` 시 Ctrl+Y | onRedo 핸들러에서 조건 검사로 무시 |

---

## 검증 계획

```bash
pnpm --filter @rpdf/desktop typecheck
pnpm --filter @rpdf/desktop test
```

---

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | Scope & strategy | 0 | — | — |
| Codex Review | `/codex review` | Independent 2nd opinion | 0 | — | — |
| Eng Review | `/plan-eng-review` | Architecture & tests (required) | 1 | CLEAR (PLAN) | 6 issues, 0 critical gaps |
| Design Review | `/plan-design-review` | UI/UX gaps | 0 | — | — |
| DX Review | `/plan-devex-review` | Developer experience gaps | 0 | — | — |

- **CROSS-MODEL:** Gemini 외부 검토 실시. 6개 지적 중 3개 계획서에 반영 (input guard, e.key.toLowerCase(), Delete\|\|Backspace). 나머지 3개는 이 앱 컨텍스트에서 무시.
- **UNRESOLVED:** 0
- **VERDICT:** ENG CLEARED — 구현 시작 가능.
