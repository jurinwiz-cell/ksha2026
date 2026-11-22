# Task #30 완료 보고서: Tauri Commands 정의

**이슈**: #60 (closes #60)  
**브랜치**: local/task30  
**완료일**: 2026-05-20  
**계획서**: mydocs/plans/task30-tauri-commands.md

---

## 완료된 작업

### 체크포인트 A: Cargo.toml 의존성 추가 ✅

`packages/desktop/src-tauri/Cargo.toml`에 rpdf-parser/edit/serializer/core 의존성 추가.  
경로: `../../../crates/<name>` (계획서의 `../../../../`는 오류였음 — 실제 디렉토리 구조 기준 수정)

### 체크포인트 B: state.rs 구현 ✅

- `PdfState` (doc + sources + stack + sources_undo + sources_redo)
- `AppState` (Mutex<Option<PdfState>>)
- `execute_cmd`: sources_undo 항상 push, CLAUDE.md 불변성 준수

### 체크포인트 C: commands.rs 구현 ✅

7개 Tauri command 구현: `open_pdf`, `save_pdf`, `rotate_page`, `delete_pages`, `undo`, `redo`, `get_pages`

계획서 대비 변경:
- `validate_degrees`: `0/90/180/270` 고정 → `degrees % 90 == 0` (RotatePageCommand가 상대값 받음)
- `delete_pages` 검증: `indices.len()` → `HashSet` unique count (plan-eng-review Outside Voice 발견 버그 수정)

### 체크포인트 D: lib.rs command 등록 ✅

7개 command 모두 `invoke_handler!`에 등록.

### 체크포인트 E: 단위 테스트 6개 ✅

| 테스트 | 검증 대상 |
|--------|---------|
| `open_pdf_returns_page_infos` | PDF 로드 → pages 반환 |
| `rotate_increases_undo_len` | rotate → undo_len 증가 |
| `undo_restores_original_rotation` | undo → 원래 rotation 복원 |
| `delete_pages_reduces_page_count` | delete → 페이지 수 감소 |
| `delete_all_pages_returns_error` | 전체 삭제 → 에러 조건 확인 |
| `sources_undo_height_invariant_after_rotate_delete_undo_undo` | sources_undo 불변성 (D3) |

---

## 품질 관문

- `cargo test -p rpdf-desktop`: 6/6 통과 ✅
- `cargo clippy -p rpdf-desktop -- -D warnings`: 경고 없음 ✅
- `cargo fmt --check -p rpdf-desktop`: 통과 ✅

---

## 계획 대비 달라진 점

1. **Cargo.toml 경로**: 계획서 `../../../../crates/` → 실제 `../../../crates/` (디렉토리 깊이 오류)
2. **validate_degrees**: 절대값 검증 → 상대값(90 배수) 검증으로 변경 — `RotatePageCommand` 실제 API 반영
3. **delete_pages 중복 인덱스 수정**: plan-eng-review Outside Voice(Gemini CLI)가 발견한 버그 — `indices.len()` 대신 HashSet unique count 사용

---

## 배운 점

- `CommandStack::new(max_depth)` — 인자 필요. 계획서에서 `CommandStack::new()`로 잘못 기재됨.
- `RotatePageCommand`는 절대값이 아닌 상대 회전값을 받는다 — 계획서 검증 로직 수정 필요.
- Cargo.toml 상대 경로는 `packages/desktop/src-tauri/` 기준이므로 `../../../crates/` (3단계 상위).

---

## 회고 분류 표

| # | 항목 | 카테고리 | 판단 근거 |
|---|------|---------|---------|
| 1 | Cargo.toml 상대 경로 오류 (계획서 4단계, 실제 3단계) | **C: 스킵** | 일회성, 코드로 해결 완료 |
| 2 | `CommandStack::new(max_depth)` 인자 필요 | **C: 스킵** | 크레이트별 API — 계획서 작성 시 소스 확인으로 예방 가능 |
| 3 | `RotatePageCommand` 상대값 방식 (계획서 절대값 가정) | **C: 스킵** | 사전 API 확인으로 방지 — 이미 CLAUDE.md에 "외부 크레이트 docs.rs 확인 완료" 명시 |
| 4 | delete_pages 중복 인덱스 버그 — plan-eng-review Outside Voice 발견 | **C: 스킵** | 검토 과정에서 수정 완료. Outside Voice 효과 확인. |
