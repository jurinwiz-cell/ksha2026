# 트러블슈팅 — window keydown 리스너가 INPUT 필드 입력을 간섭

**발생일**: 2026-05-20
**해결일**: 2026-05-20
**관련 Issue**: #71
**심각도**: 높음
**환경**: Tauri 2 / React / TypeScript

## 증상

- `window.addEventListener('keydown', handler)` 등록 후, 텍스트 입력 필드(`<input>`, `<textarea>`)에 포커스한 상태에서 `Delete` 키를 누르면 입력 문자가 지워지는 대신 앱의 PDF 페이지 삭제 단축키가 발동된다.
- `Ctrl+Z`도 텍스트 undo가 아니라 앱의 PDF undo 커맨드가 실행된다.
- 사용자가 텍스트를 편집하는 중에 키보드 단축키가 의도치 않게 발동한다.

## 재현 방법

```typescript
// 문제가 있는 코드
window.addEventListener('keydown', (e) => {
  if (e.key === 'Delete') {
    deletePage() // INPUT 포커스 중에도 발동됨
  }
})
```

텍스트 입력 필드에 포커스 후 Delete 키 입력 → 페이지 삭제 발동.

## 원인 분석

`window` 레벨에서 `keydown`을 수신하면 DOM 트리의 모든 요소에서 발생한 키 이벤트가 버블링(bubbling)으로 올라온다. `<input>` 안에서 발생한 이벤트도 예외 없이 window까지 전달된다. `e.target`을 확인하지 않으면 어떤 요소에서 발생했는지 구분할 수 없다.

## 해결책

### 적용한 수정

```typescript
const handleKeyDown = (e: KeyboardEvent) => {
  // INPUT/TEXTAREA/contentEditable에서는 앱 단축키 무시
  const target = e.target as HTMLElement
  if (
    ['INPUT', 'TEXTAREA'].includes(target.tagName) ||
    target.isContentEditable
  ) return

  // 이후 단축키 처리
  if (e.key === 'Delete') deletePage()
}

window.addEventListener('keydown', handleKeyDown)
```

### 테스트 추가

```typescript
it('INPUT 포커스 중 Delete → 단축키 핸들러 미호출', () => {
  renderHook(() => useKeyboardShortcuts({ onDelete, ... }))
  const input = document.createElement('input')
  document.body.appendChild(input)
  act(() => input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true })))
  document.body.removeChild(input)
  expect(onDelete).not.toHaveBeenCalled()
})
```

## 재발 방지

`window.addEventListener('keydown')` 구현 시 **반드시** handler 첫 줄에 input guard를 추가한다:

```typescript
const target = e.target as HTMLElement
if (['INPUT', 'TEXTAREA'].includes(target.tagName) || target.isContentEditable) return
```

## 배운 점

- `window` keydown은 DOM 전체 버블링을 받는다. 앱 단축키를 등록할 때 input 요소 예외 처리는 선택이 아니라 필수.
- 현재 UI에 텍스트 입력이 없더라도, 미래 확장을 위해 처음부터 guard를 추가해야 한다.
