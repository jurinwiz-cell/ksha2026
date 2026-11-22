# Task #44 완료 보고서 — 주석 저장 E2E 검증

**완료일**: 2026-05-21  
**브랜치**: local/task44  
**Issue**: #84  
**계획서**: mydocs/plans/task44-annotation-save-verification.md

## 구현 범위

| 파일 | 내용 |
|------|------|
| `crates/rpdf-serializer/tests/annotation_roundtrip_tests.rs` | 신규: RT-1~RT-6, RT-M1 (7개 통합 테스트) |
| `crates/rpdf-wasm/src/lib.rs` | UT-14 추가 (PdfDocument::save() 라운드트립) |
| `mydocs/plans/task44-annotation-save-verification.md` | 계획서 (plan-eng-review 승인 완료) |

**총 변경**: 3개 파일, +406줄

## 체크포인트 결과

- [x] CP-1: `cargo test -p rpdf-serializer` — 17 passed (RT-1~6+RT-M1 포함)
- [x] CP-2: `cargo test -p rpdf-wasm` — 19 passed (UT-14 포함)
- [x] CP-3: `cargo clippy -p rpdf-serializer -p rpdf-wasm -- -D warnings` — 경고 없음
- [x] CP-3: `cargo fmt --check` — 통과
- [x] CP-3: `cargo test` (전체) — 모든 크레이트 통과, FAILED 없음

## 테스트 커버리지

| ID | 시나리오 | 결과 |
|----|----------|------|
| RT-1 | add_highlight → serialize → reload → get_annotations (annot_type·rect·color 검증) | ✅ |
| RT-2 | add_underline → serialize → reload: annot_type 검증 | ✅ |
| RT-3 | add_sticky_note("테스트 메모") → serialize → reload: content 보존 | ✅ |
| RT-4 | 3-page PDF, page 0 annotation, page 1 삭제 → serialize → reload: annotation 보존 | ✅ |
| RT-5 | 같은 페이지에 3개 annotation → serialize → reload: 3개 모두 존재 | ✅ |
| RT-6 | add_highlight → rotation 90° → serialize → reload: annotation 보존 | ✅ |
| RT-M1 | pdfjs-basicapi+fw4-2024 merge → serialize_multi_source → reload: annotation 보존 | ✅ |
| UT-14 | add_annotation[Rust fn] → PdfDocument{doc, sources} → save() → get_annotations | ✅ |

## 설계 결정

- **픽스처**: `pdfjs-basicapi.pdf` (3페이지, rpdf-parser+lopdf 호환 확인됨) 사용. `make_minimal_pdf` 신규 작성 금지.
- **JsValue 우회**: WASM API `addAnnotation(JsValue, ...)` 는 native `cargo test`에서 JS 런타임 없이 동작 불가. Rust 함수 `add_annotation(bytes, &params)` 직접 호출로 UT-14 구현.
- **sources 수동 동기화**: `DeletePagesCommand.execute(&mut doc)` 는 `doc.pages`만 갱신. RT-4에서 `sources.remove(1)` 수동 동기화 필수 (`serialize_tests.rs:167` 동일 패턴).
- **multi-source renumber 검증**: RT-M1에서 `renumber_objects_with()` 후 `/Annots` ref 정합성을 `annot_type·color·rect` 단언으로 검증. 실제 통과 → serialize_multi_source 버그 없음 확인.

## 트러블슈팅 후보 분류

| 항목 | 분류 | 내용 |
|------|------|------|
| `cargo test \| tail -20` 결과 잘림 | C | tail -20으로 annotation_roundtrip_tests 바이너리 결과 누락. 전체 출력 확인 필요 시 `cargo test 2>&1 \| grep "test result"` 사용 권장 |
| Agy 타임아웃 → Claude subagent 폴백 | C | plan-eng-review outside voice 단계에서 agy -p 120초 타임아웃. Claude subagent로 폴백하여 동일 품질 외부 리뷰 완료 |
| RT-M1 renumber 후 ObjectId 불일치 우려 | C | 실제 테스트 통과로 serialize_multi_source의 `_` 분기 객체 수집이 annotation 포함 정상 동작 확인됨 |

## 다음 작업

Task #45 예정 (미확정). Issue 목록 확인 필요.
