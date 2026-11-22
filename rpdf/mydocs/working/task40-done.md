# Task #40 완료 보고서 — TextReplace 백엔드 + 폰트 시스템

**완료일**: 2026-05-21  
**브랜치**: local/task40  
**Issue**: #75

## 구현 범위

| 파일 | 내용 |
|------|------|
| `crates/rpdf-edit/src/commands/text_replace.rs` | `apply_text_replace()` + 타입 + 11개 테스트 신규 |
| `crates/rpdf-edit/src/commands/noop.rs` | `NoOpCommand` 신규 (스택 동기화 sentinel) |
| `crates/rpdf-edit/src/commands/mod.rs` | text_replace, noop 모듈 등록 |
| `crates/rpdf-edit/Cargo.toml` | lopdf, ttf-parser, serde 추가 |
| `crates/rpdf-wasm/src/lib.rs` | `replace_text()` WASM 바인딩 추가 |
| `crates/rpdf-wasm/Cargo.toml` | serde_json 추가 |
| `packages/desktop/src-tauri/src/commands.rs` | `replace_text` Tauri command 추가 |
| `packages/desktop/src-tauri/src/lib.rs` | invoke_handler에 `replace_text` 등록 |
| `Cargo.toml` | `ttf-parser = "0.25"` workspace deps 추가 |

## 체크포인트 결과 (CP-B)

- [x] TR-1~TR-8 + 보조 3개 (11개 전체) 통과
- [x] `cargo test -p rpdf-wasm` 18개 통과
- [x] `cargo clippy --workspace -- -D warnings` 경고 없음
- [x] `cargo fmt --check` 통과

## evaluator 지적 및 처리

| 심각도 | 항목 | 처리 |
|--------|------|------|
| Critical | D3 Undo 탈동기화 — `replace_text`가 CommandStack 우회, sources_undo 높이 불일치 | `NoOpCommand` sentinel 패턴 도입, `execute_cmd` 경유로 항상 동기화 |
| Critical | Resources Reference orphan — inline dict로 교체 시 공유 리소스 손실 | two-step borrow 패턴으로 Reference deref 후 원본 수정 |
| Critical | inline Stream Contents 유실 — `_ =>` 분기에서 기존 내용 덮어쓰기 | `existing.clone()` 보존 후 append |
| Important | `undo_len()` 반환값 오류 | NoOpCommand 경유로 자동 해결 (stack height 포함) |

## 회고 분류

| 항목 | 분류 |
|------|------|
| CLAUDE.md "보조 스택 높이 일치" 원칙이 lopdf 직접 조작 경로에도 적용됨 — 계획서에서 미리 명시했어야 함 | A (CLAUDE.md 기존 항목이 이미 커버, 재확인 필요) |
| Resources Reference deref 패턴 — lopdf two-step borrow 필요 | B (트러블슈팅 문서 후보) |
| inline Stream → Array 변환 시 기존 내용 보존 | B |

## 다음 작업

Task #41: UI 텍스트 편집 레이어 + 폰트 선택
- `pdfToScreen` 좌표 변환 (D10 MediaBox 원점 보정 포함)
- TextBlockOverlay + TextEditPanel 컴포넌트
- FontFamilyPicker (한글/영어 독립 선택)
- document-store 확장 (textBlocks, editingBlock, fontConfig)
