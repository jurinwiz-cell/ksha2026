# Task #42 완료 보고서 — 서식 툴바 (TextStyle)

**완료일**: 2026-05-21  
**브랜치**: local/task42  
**계획서**: mydocs/plans/task42-text-style-toolbar.md

## 구현 범위

| 파일 | 내용 |
|------|------|
| `crates/rpdf-edit/src/commands/text_replace.rs` | TextStyle/TextAlign 구조체, build_content_stream 수정, TR-Style-1~9 단위 테스트 |
| `crates/rpdf-edit/src/commands/mod.rs` | TextStyle, TextAlign re-export |
| `crates/rpdf-wasm/src/lib.rs` | replace_text에 text_style_json 파라미터 추가 |
| `packages/desktop/src-tauri/src/commands.rs` | Tauri replace_text 커맨드 text_style_json 파라미터 추가 |
| `packages/ui/src/types/pdf.ts` | TextStyle 인터페이스, WasmApi.replaceText textStyleJson 파라미터 추가 |
| `packages/ui/src/state/document-store.ts` | textStyle 상태 + setTextStyle 액션, replaceText에 textStyleJson 전달 |
| `packages/ui/src/ui/text_edit_panel.tsx` | StyleButtons(B/I/U/S), ColorPicker(8색 팔레트 + hex), AlignButtons |
| `packages/ui/src/ui/page_viewer.tsx` | textStyle, setTextStyle props 연결 |
| `packages/studio/src/core/wasm-worker.ts` | replaceText에 textStyleJson 파라미터 추가 |
| `packages/studio/src/main.tsx` | bridge replaceText에 textStyleJson 추가 |
| `packages/desktop/src/core/wasm-worker.ts` | replaceText breaking change 동기화 |
| `packages/desktop/src/core/tauri-client.ts` | stub에 textStyleJson 파라미터 추가 |
| `packages/desktop/src/main.tsx` | bridge replaceText에 textStyleJson 추가 |
| `packages/ui/src/__tests__/document-store.test.ts` | UI-Style-1~3 테스트 추가 |

## 체크포인트 결과 (CP-D)

- [x] TR-Style-1~9 단위 테스트 통과 (98 passed)
- [x] `cargo test -p rpdf-edit` 통과
- [x] `cargo clippy -- -D warnings` 통과
- [x] B/I/U/S 토글 + 8색 팔레트 + 정렬 버튼 UI 구현
- [x] `pnpm typecheck` 통과 (studio + desktop + ui 전체)
- [x] `packages/ui` vitest 17개 통과 (UI-Style-1~3 포함)
- [x] 기존 Task #41 기능 회귀 없음

## 설계 결정 반영 (ADR)

| ID | 내용 |
|----|------|
| D12 | Bold: `2 Tr` fill+stroke, `0.5 w` 선 두께 |
| D13 | Italic: `1 0.2 0 1 {x} {y} Tm` oblique 행렬 |
| D14 | Color: `rg` 연산자 (0.0~1.0) |
| D15 | Underline/Strikethrough: `q {r}{g}{b} RG 1 w {x} {y} m {x+w} {y} l S Q` |
| D16 | Alignment: Rust `estimate_text_width()` — 한글 0.95, 라틴 0.55 비율 |
| D17 | TextStyle: rpdf-edit에 독립 구조체, WasmApi JSON 직렬화 전달 |
| D18 | WASM API breaking change: text_style_json 파라미터 추가 — studio + desktop 양쪽 업데이트 |
| D19 | 8색 팔레트 + hex 입력 |

## 계획 대비 달라진 점

- plan-eng-review에서 발견한 이슈로 계획서 수정 후 진행:
  1. `packages/desktop/src/core/wasm-worker.ts` 관련 파일 목록 추가
  2. TR-Style-9 (bold+italic 동시 조합) 테스트 케이스 추가
- CI fix PR #80 생성: `packages/desktop` WasmApi 인터페이스 누락 별도 수정

## 회고 분류 표

| # | 항목 | 분류 | 판단 근거 |
|---|------|------|---------|
| 1 | plan-eng-review가 desktop 패키지 누락을 사전 발견 | C | 워크플로우 효과 확인, 이미 CLAUDE.md에 review 절차 있음 |
| 2 | WasmApi breaking change 시 모든 구현체(desktop 포함) 목록을 계획서에 명시해야 함 | **A: CLAUDE.md** | Task #41에서도 같은 패턴으로 CI 실패. 반복 방지 필요 |
| 3 | desktop wasm-worker.ts가 Task #41에서 미반영 → CI 실패 → 별도 PR(#80) 생성 | C | 이미 PR #80으로 수정, 개별 일회성 |

## 다음 작업

Task #43: 주석 도구 (하이라이트/밑줄/스티키노트)
