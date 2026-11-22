# 트러블슈팅 — macOS에서 ⌫(Delete) 키가 `Backspace` 이벤트를 발생시킴

**발생일**: 2026-05-20
**해결일**: 2026-05-20
**관련 Issue**: #71
**심각도**: 높음
**환경**: macOS / Tauri 2 / 브라우저 keydown 이벤트

## 증상

- macOS에서 `e.key === 'Delete'` 조건으로 단축키를 구현했는데 동작하지 않는다.
- Apple 키보드의 `⌫` 키를 눌러도 조건이 false로 평가된다.
- Windows에서는 정상 동작하지만 Mac에서만 단축키가 무반응이다.

## 원인 분석

macOS Apple 키보드의 키 이벤트 매핑:

| 키 | macOS `e.key` | Windows `e.key` |
|----|--------------|----------------|
| `⌫` (주 삭제 키) | `'Backspace'` | `'Backspace'` |
| `⌦` (Fn+⌫, 전진 삭제) | `'Delete'` | `'Delete'` |

macOS에서 `Delete` 이벤트는 **Fn+⌫** 조합으로만 발생한다. 사용자가 평소 누르는 `⌫` 키는 `Backspace`를 반환한다. 따라서 `e.key === 'Delete'` 만 체크하면 Mac 사용자는 Fn+⌫를 눌러야만 단축키가 발동된다.

## 해결책

### 적용한 수정

```typescript
// 잘못된 패턴 (Mac에서 ⌫ 미동작)
if (e.key === 'Delete') deletePage()

// 올바른 패턴 — Delete(PC/Fn+⌫) + Backspace(Mac ⌫) 모두 처리
if (e.key === 'Delete' || e.key === 'Backspace') deletePage()
```

또는 `e.key.toLowerCase()` 사용 시:

```typescript
const key = e.key.toLowerCase()
if (key === 'delete' || key === 'backspace') deletePage()
```

## 재발 방지

macOS를 지원하는 앱에서 "삭제" 단축키를 구현할 때는 `Delete`와 `Backspace` 모두 처리한다. Tauri 앱은 macOS가 주 플랫폼이므로 이 처리는 필수.

## 배운 점

- 웹/Electron/Tauri 앱에서 키 이벤트는 OS별로 동작이 다르다.
- Mac 키보드 레이블 "delete(⌫)"은 실제로 `Backspace` 이벤트 — 직관과 반대.
- 크로스 플랫폼 단축키 구현 시 Mac에서 직접 테스트하거나 `Backspace`를 함께 처리한다.
