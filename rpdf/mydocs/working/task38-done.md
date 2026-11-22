# Task #38 완료 보고서: 키보드 단축키 바인딩

**Issue**: #71 (closes)
**브랜치**: `local/task38`
**완료일**: 2026-05-20

---

## 구현 내용

### 신규 훅: `use-keyboard-shortcuts.ts`

`useKeyboardShortcuts(handlers: ShortcutHandlers)` 훅:
- `window.addEventListener('keydown')` 등록, cleanup 시 `removeEventListener`
- `handlersRef` 패턴으로 stale closure 없이 최신 handler 참조
- `e.key.toLowerCase()` — Shift/CapsLock 대소문자 독립
- INPUT/TEXTAREA/contentEditable 포커스 시 단축키 무시 (input guard)
- `Delete || Backspace` — Mac ⌫ 키(Backspace) 동일 처리

### 단축키 목록

| 단축키 | 동작 |
|--------|------|
| Ctrl/Cmd+O | PDF 파일 열기 |
| Ctrl/Cmd+S | 파일 저장 |
| Delete / Backspace | 선택 페이지 삭제 |
| Ctrl/Cmd+Z | Undo |
| Ctrl/Cmd+Y / Ctrl/Cmd+Shift+Z | Redo |

### `App.tsx` 수정

`useKeyboardShortcuts` 호출 추가. 각 handler에 guard 조건:
- `onSave`: `pages.length > 0 && !isLoading`
- `onDelete`: `pages.length > 1 && !isLoading`
- `onUndo`: `undoLen > 0 && !isLoading`
- `onRedo`: `redoLen > 0 && !isLoading`

---

## 검증 결과

| 항목 | 결과 |
|------|------|
| `pnpm --filter @rpdf/desktop typecheck` | 통과 |
| `pnpm --filter @rpdf/desktop test` | 16 passed (10 신규 + 6 기존) |

---

## 특이 사항 (트러블슈팅)

### A. Gemini 외부 검토에서 3개 버그 발견 (plan-eng-review 단계)

계획서 작성 후 Gemini 외부 검토에서 다음 3개 실제 버그를 발견해 계획서에 반영:

1. **INPUT guard 누락** — window keydown에 target 체크 없어 text input 포커스 중 Delete가 페이지 삭제 트리거. `if (['INPUT','TEXTAREA'].includes(target.tagName) || target.isContentEditable) return` 추가로 해결.
2. **e.key 대소문자 버그** — Shift+Z 시 `e.key === 'Z'`(대문자)라 `Ctrl+Shift+Z` 조건 미발동. `e.key.toLowerCase()` 사용으로 해결.
3. **Mac Backspace = Delete** — macOS ⌫ 키는 `Backspace` 이벤트 발생. `Delete` 만 체크하면 Mac에서 동작 안 함. `key === 'delete' || key === 'backspace'` 조건으로 해결.

---

## 회고 분류 표

| 항목 | 내용 | 분류 |
|------|------|------|
| INPUT guard 패턴 | window keydown에 target check 필수 | B |
| Mac Delete vs Backspace | macOS ⌫는 Backspace 이벤트 | B |
| e.key.toLowerCase() | Shift/CapsLock 독립 키 비교 | C |

---

## 다음 단계

Task #39 — (미정)
