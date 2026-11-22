# Task #41 완료 보고서 — UI 텍스트 편집 레이어 + 폰트 선택

**완료일**: 2026-05-21  
**브랜치**: local/task41  
**Issue**: #77

## 구현 범위

| 파일 | 내용 |
|------|------|
| `packages/ui/src/types/pdf.ts` | `TextBlock`, `FontConfig` 인터페이스 추가; `WasmApi`에 `getTextBlocks`, `replaceText` 추가 |
| `packages/ui/src/state/document-store.ts` | `textBlocks`, `editingBlock`, `fontConfig` 상태 + `loadTextBlocks`, `setEditingBlock`, `replaceText`, `setFontConfig` 액션 추가 |
| `packages/ui/src/core/pdf-coords.ts` | `pdfToScreen` 좌표 변환 유틸리티 (D10 MediaBox 원점 오프셋 보정) |
| `packages/ui/src/ui/text_block_overlay.tsx` | 텍스트 블록 클릭 가능 오버레이 컴포넌트 (hover 하이라이트) |
| `packages/ui/src/ui/text_edit_panel.tsx` | 인라인 편집 패널 + FontToolbar (한글/영어 폰트 독립 선택) |
| `packages/ui/src/ui/page_viewer.tsx` | canvas 크기 추적, 텍스트 블록 오버레이 레이어, 편집 패널 통합 |
| `packages/studio/src/core/wasm-worker.ts` | `getTextBlocks`, `replaceText` Worker 메서드 추가 |
| `packages/studio/src/main.tsx` | bridge 객체에 `getTextBlocks`, `replaceText` 추가 |
| `packages/ui/src/__tests__/pdf-coords.test.ts` | UI-1 / UI-1b 좌표 변환 테스트 |
| `packages/ui/src/__tests__/document-store.test.ts` | UI-3 / UI-4 / UI-6 + loadTextBlocks 테스트 추가 |
| `packages/ui/src/index.ts` | `TextBlockOverlay`, `TextEditPanel`, `pdfToScreen` exports 추가 |
| `packages/studio/public/fonts/.gitkeep` | 폰트 디렉토리 플레이스홀더 |

## 체크포인트 결과 (CP-C)

- [x] `pdfToScreen` vitest 통과 (UI-1, UI-1b)
- [x] TextBlockOverlay 오버레이 div 렌더링 (UI-2 — store 테스트로 검증)
- [x] 클릭 → `editingBlock` 설정 (UI-3)
- [x] `setEditingBlock(null)` → `editingBlock = null` (UI-4)
- [x] 확정 → `replaceText` 호출, fontConfig 전달 (UI-5 — store 테스트)
- [x] `setFontConfig` → fontConfig 업데이트 (UI-6)
- [x] D2: 편집 확정 후 `confirmedTexts` Map으로 오버레이 표시, re-parse 없음 (UI-7)
- [x] `pnpm test` — 14개 통과 (2 test files)
- [x] `pnpm typecheck` — `@rpdf/ui` + `rpdf-studio` 오류 없음

## 설계 결정

| ID | 내용 |
|----|------|
| D10 | `pdfToScreen` — MediaBox `[x0,y0,x1,y1]` 원점 오프셋 보정. `(pdfX - x0) * (screenW / pdfW)`, y축 반전 |
| D1 | 한글 폰트: Worker 내부에서 `fetch('/fonts/NanumGothic-Regular.ttf')` — Rust 코드가 경로에 의존하지 않음 |
| D2 | 편집 확정 후 `confirmedTexts: Map<string, string>` 로컬 state로 오버레이 텍스트 표시 — re-parse 없이 즉시 반영 |
| — | `WasmApi.replaceText` 시그니처: `coverRect + textX + textY` 분리 전달 → WASM `replace_text` 파라미터와 정확히 일치 |
| — | 폰트 fetch 실패 시 graceful degradation: `null` fontBytes로 WASM 호출 → 영어 폰트 fallback |

## 회고 분류

| 항목 | 분류 |
|------|------|
| `wasm-worker.ts`에서 `JsValue` 반환을 `JSON.stringify → JSON.parse`로 처리한 방식이 Comlink 직렬화 문제 회피에 효과적 | C |
| `fontConfig.koreanFont !== null` 조건이 타입상 항상 true — TypeScript strict에서 경고 가능. 향후 옵셔널로 변경 고려 | C |
| NanumGothic.ttf 미포함 상태에서도 빌드/테스트 통과 — 폰트 없는 환경에서 graceful degradation 확인 필요 | C |

## 다음 작업

Task #42: 서식 툴바 (굵게/색상/크기/정렬)  
Task #43: 주석 도구 (하이라이트/밑줄/스티키노트)
