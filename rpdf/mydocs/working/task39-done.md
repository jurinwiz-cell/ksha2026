# Task #39 완료 보고서 — TextBlock 추출 API

**완료일**: 2026-05-21  
**브랜치**: local/task39  
**Issue**: #73

## 구현 범위

| 파일 | 내용 |
|------|------|
| `crates/rpdf-core/src/types/text_block.rs` | `TextBlock` 도메인 타입 신규 |
| `crates/rpdf-core/src/types/mod.rs` | `pub mod text_block` + re-export 추가 |
| `crates/rpdf-svg/src/extract.rs` | `extract_text_blocks()` + TB-1~TB-6 테스트 신규 |
| `crates/rpdf-svg/src/lib.rs` | `pub mod extract` 추가 |
| `crates/rpdf-wasm/Cargo.toml` | `rpdf-svg.workspace = true` 추가 |
| `crates/rpdf-wasm/src/lib.rs` | `get_text_blocks()` WASM 바인딩 추가 |
| `packages/desktop/src-tauri/Cargo.toml` | `rpdf-svg` 의존성 추가 |
| `packages/desktop/src-tauri/src/commands.rs` | `get_text_blocks` Tauri command 추가 |
| `packages/desktop/src-tauri/src/lib.rs` | invoke_handler에 `get_text_blocks` 등록 |

## 체크포인트 결과 (CP-A)

- [x] TB-1~TB-6 6개 단위 테스트 통과
- [x] WASM `get_text_blocks` 빌드 성공 (rpdf-wasm 18개 테스트 통과)
- [x] Tauri `get_text_blocks` command 등록 완료
- [x] `cargo clippy --workspace -- -D warnings` 경고 없음
- [x] `cargo fmt --check` 통과

## evaluator 지적 및 처리

| 심각도 | 항목 | 처리 |
|--------|------|------|
| Critical | TB-6 명세(fixture)와 구현(BT 없이 ShowText) 불일치 | 계획서 TB-6 설명 업데이트 (Option B) |
| Important | `MoveShowText`/`MoveSetShowText` 연산자 미처리 | 계획서 알려진 한계 테이블에 추가 |
| Important | `rpdf-core/CLAUDE.md` Copy 규칙 모호 | CLAUDE.md 불변 규칙 명확화 |
| Enhancement | TJ 커닝 값 무시 → x 위치 불정확 | 계획서 알려진 한계 테이블에 추가 |

## 회고 분류

| 항목 | 분류 |
|------|------|
| 계획서 TB-6이 "fixture 기반"이었으나 더 유용한 방어적 케이스로 구현됨 → 계획서 先 확정 중요 | C |
| rpdf-core/CLAUDE.md의 Copy 규칙이 String 포함 타입에 오해 유발 → evaluator가 지적 | B (문서 즉시 수정 완료) |
| MoveShowText(`'`)/`"` 연산자 처리 누락 — PDF spec coverage gap | C (Phase 2 예정) |

## 다음 작업

Task #40: TextReplace 백엔드 + 폰트 시스템
- `apply_text_replace()` — 흰박스 오버레이 + 새 텍스트 삽입
- NanumGothic Type0/CIDFont 임베딩
- WASM/Tauri `replace_text` 바인딩
