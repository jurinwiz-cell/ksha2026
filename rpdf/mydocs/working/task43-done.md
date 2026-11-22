# Task #43 완료 보고서 — 주석 도구 (Highlight/Underline/StickyNote)

**완료일**: 2026-05-21  
**브랜치**: local/task43  
**PR**: #83 (merged to devel)  
**Issue**: #82  
**계획서**: mydocs/plans/task43-annotation-tools.md

## 구현 범위

| 파일 | 내용 |
|------|------|
| `crates/rpdf-edit/src/commands/annotation.rs` | 신규: `add_annotation`/`delete_annotation`/`get_annotations` 순수 함수, `AnnotationParams`/`AnnotationInfo`/`AnnotationError` 타입, 12개 단위 테스트 |
| `crates/rpdf-edit/src/commands/mod.rs` | annotation 모듈 등록 및 pub use |
| `crates/rpdf-wasm/src/lib.rs` | `addAnnotation`/`deleteAnnotation`/`getAnnotations` WASM API (AnnotationInfo[] 반환, NoOpCommand sources 스택 동기화) |
| `packages/ui/src/types/pdf.ts` | `AnnotationType`, `AnnotationParams`, `AnnotationInfo` 추가, WasmApi 확장 |
| `packages/ui/src/core/pdf-coords.ts` | `screenToPdf` 역함수 추가 |
| `packages/ui/src/state/document-store.ts` | `annotations`, `annotationMode`, `annotationColor` 상태 추가, `loadAnnotations`/`setAnnotationMode`/`setAnnotationColor` 액션, undo/redo에 `loadAnnotations` 동기화 |
| `packages/ui/src/ui/annotation_toolbar.tsx` | 신규: 하이라이트/밑줄/스티키노트 모드 전환 + 색상 팔레트 3색 |
| `packages/ui/src/ui/annotation_drag.tsx` | 신규: 드래그 rect 정규화 포함, StickyNote 클릭 분기 |
| `packages/ui/src/ui/annotation_layer.tsx` | 신규: AnnotationInfo[] → screen coords 오버레이, 클릭 삭제 |
| `packages/ui/src/ui/sticky_note_input.tsx` | 신규: 스티키노트 텍스트 입력 팝업 |
| `packages/ui/src/ui/page_viewer.tsx` | annotation 컴포넌트 통합 |
| `packages/ui/src/index.ts` | 신규 컴포넌트 export |
| `packages/ui/src/__tests__/annotations.test.ts` | 신규: 13개 테스트 (screenToPdf 라운드트립, 드래그 정규화, undo/redo 동기화 등) |
| `packages/ui/src/__tests__/document-store.test.ts` | annotation 액션 테스트 추가 |
| `packages/studio/src/core/wasm-worker.ts` | annotation 브릿지 3개 추가 |
| `packages/studio/src/main.tsx` | bridge annotation 메서드 추가 |
| `packages/desktop/src/core/wasm-worker.ts` | annotation 브릿지 3개 추가 |
| `packages/desktop/src/core/tauri-client.ts` | annotation stub 3개 추가 |
| `packages/desktop/src/main.tsx` | bridge annotation 메서드 추가 |

**총 변경**: 20개 파일, +2,304줄 / -72줄

## 체크포인트 결과

- [x] `cargo test -p rpdf-edit` — 110 passed (annotation 12개 포함)
- [x] `cargo clippy -p rpdf-edit -p rpdf-wasm -- -D warnings` — 경고 없음
- [x] `cargo fmt --check` — 통과
- [x] `pnpm -C packages/ui typecheck` — 통과
- [x] `pnpm -C packages/studio typecheck` — 통과
- [x] `pnpm -C packages/desktop typecheck` — 통과
- [x] `pnpm --filter @rpdf/ui test:unit` — 30 passed (annotations 13 + coords 2 + store 15)
- [x] CI 전체 통과 (Rust / WASM / Node / Studio / Docs) → PR #83 auto-merge

## 설계 결정 반영 (ADR)

| ID | 내용 | 결과 |
|----|------|------|
| D20 | `apply_text_replace`와 동일한 순수 함수 패턴 | 적용 |
| D21 | lopdf `/Annot` 딕셔너리, `/Rect`만 사용 | 적용 |
| D22 | `execute_cmd(NoOpCommand, Some(new_sources))` undo 동기화 | 적용 |
| D23 | `annot_id: u32` (generation 0 고정) | 적용 |
| D24 | 하이라이트 노란색 `[1.0, 1.0, 0.0]`, `/CA 0.3` | 적용 |
| D25 | 밑줄 파란색 `[0.0, 0.0, 0.8]`, opacity 생략 | 적용 |
| D26 | 스티키노트 `/Subtype /Text`, `/Contents` | 적용 |
| D27 | `screenToPdf` 역함수 추가 | 적용 |
| D28 | `get_annotations` 구현 범위 포함 | 적용 |
| D29 | `AnnotationInfo[]` 반환, undo/redo → `loadAnnotations` | 적용 |
| D30 | 드래그 rect 정규화 `Math.min/max` | 적용 |

## 계획 대비 달라진 점

- 계획서에는 `annotation.rs`를 `src/commands/` 하위로 명시했으나, 실제로도 동일 경로에 생성 (계획과 일치)
- evaluator가 `handleClickPoint`에서 `pdfToScreen` 재사용 권고 (가독성 개선 여지) — 기능 영향 없어 이번 범위에서 미적용

## 트러블슈팅 후보 분류

| 항목 | 분류 | 내용 |
|------|------|------|
| lopdf `/Annots` 간접 참조 | B | Object::Reference vs inline Array — borrow checker scope block 패턴 |
| undo/redo 후 annotation 상태 stale | A (계획서 반영됨) | D29로 계획서에 명시, undo/redo 액션에 loadAnnotations 추가 |
| 드래그 rect 정규화 누락 | A (계획서 반영됨) | D30으로 계획서에 명시, annotation_drag.tsx에서 Math.min/max 처리 |
| task37-rebase conflict — 이미 devel 포함 | C | Task #38 squash-merge 시 #36/#37 코드가 함께 포함됨. PR #70 중복 close |

## 다음 작업

Task #44 예정 (미확정). 이전 계획서 또는 Issue 확인 필요.
