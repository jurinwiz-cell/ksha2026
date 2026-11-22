# Task #44 계획서 — 주석 저장 E2E 검증

**Issue**: #84  
**브랜치**: local/task44  
**마일스톤**: v0.5  
**작성일**: 2026-05-21

---

## 1. 배경 및 목표

Task #43에서 주석 도구(Highlight/Underline/StickyNote)를 구현했다.  
단위 테스트는 `add_annotation → get_annotations` 수준에서 통과하지만,  
`serialize_document`를 경유하는 **E2E 라운드트립**은 검증되지 않은 상태다.

**목표**: 주석이 PDF 저장·재로드 후에도 보존됨을 Rust 테스트로 확인하고, 발견된 버그를 수정한다.

---

## 2. 아키텍처 이해

```
addAnnotation (WASM)
  └─ add_annotation(current_bytes, params) -> new_bytes   [rpdf-edit]
       └─ lopdf: Annot 객체 추가, 페이지 /Annots 배열에 ref 삽입, save_to()
  └─ new_sources (all pages point to new_arc)
  └─ execute_cmd(NoOpCommand, Some(new_sources))   → undo 스택에 push

save (WASM)
  └─ serialize_document(&self.doc, &self.sources)  [rpdf-serializer]
       └─ serialize_single_source:
            1. sources[0].bytes 를 lopdf 재로드
            2. 불필요한 페이지 delete_pages()
            3. rotation 적용
            4. save_to_vec()
       └─ serialize_multi_source (merge 시나리오):
            1. 각 소스 lopdf 로드
            2. objects 수집 (Catalog/Pages 제외)
            3. 통합 Document 재구성 → save

get_annotations (WASM)
  └─ get_annotations(sources[0].bytes, page_index)  [rpdf-edit]
       └─ lopdf: 페이지 /Annots 읽기 → AnnotationInfo 변환
```

---

## 3. 검증 공백 분석

| 시나리오 | 현재 커버리지 | 위험 |
|----------|--------------|------|
| add_annotation → get_annotations (바이트 레벨) | ✅ an_1~an_5 | 없음 |
| add_annotation → **serialize_document** → reload → get_annotations | ❌ 없음 | serialize 시 annotation 손실 가능 |
| add_annotation → **delete_pages** → serialize → reload → annotation 보존 | ❌ 없음 | delete_pages가 관련 annotation 객체도 삭제할 가능성 |
| add_annotation → **undo** → save → reload → annotation 없음 | ❌ 없음 | undo 후에도 annotation이 남을 가능성 |
| WASM save() → open(saved_bytes) → getAnnotations | ❌ 없음 | WASM 레벨 통합 |

---

## 4. 구현 계획

### 4-1. `rpdf-serializer` 통합 테스트 (신규)

**위치**: `crates/rpdf-serializer/tests/annotation_roundtrip_tests.rs`

#### 헬퍼
```rust
fn make_two_page_pdf() -> Vec<u8>  // 2페이지짜리 최소 PDF (make_minimal_pdf 확장)
fn make_highlight_params(page_index: usize) -> AnnotationParams
fn serialize_from_bytes(data: &[u8]) -> Vec<u8>  // 단일 소스, 전 페이지 유지
```

`serialize_from_bytes`는 다음을 수행한다:
1. `load_document_tracked(data)` → `(doc, sources)`
2. `serialize_document(&doc, &sources)` → 직렬화

#### 테스트 목록

**픽스처**: `fixture("pdfjs-basicapi.pdf")` (3페이지, rpdf-parser + lopdf 호환 확인됨).
새 `make_minimal_pdf` 헬퍼 작성 금지 — rpdf-parser 호환성 미보장.

| ID | 이름 | 시나리오 |
|----|------|----------|
| RT-1 | `single_source_preserves_highlight` | add_highlight → serialize → reload → get_annotations: 1건 존재 (annot_type·rect·color 비교) |
| RT-2 | `single_source_preserves_underline` | add_underline → serialize → reload: annot_type 비교 |
| RT-3 | `single_source_preserves_sticky_note` | add_sticky_note(content="테스트") → serialize → reload: content 보존 확인 |
| RT-4 | `single_source_annotation_survives_delete_other_page` | 3-page PDF, page 0에 annotation, **page 1 삭제(DeletePagesCommand.execute(&mut doc) → sources.remove(1) 수동 동기화)** → serialize → reload page 0: annotation 보존 |
| RT-5 | `single_source_multiple_annotations_preserved` | 같은 페이지에 3개 annotation → serialize → reload: 3개 모두 존재 |
| RT-6 | `single_source_after_rotation_annotation_preserved` | add_highlight → RotatePageCommand.execute(&mut doc, 90) → serialize → reload: annotation 보존 |
| RT-M1 | `multi_source_annotation_preserved_after_merge` | **픽스처 A**: `pdfjs-basicapi.pdf`에 page 0 하이라이트 추가 → **픽스처 B**: `fw4-2024.pdf` 그대로 사용 → MergeCommand로 병합 → serialize_multi_source → reload page 0: annotation 보존 (annot_type·rect·color 비교) |

> **RT-4 주의**: `DeletePagesCommand.execute()` 는 `doc.pages`만 갱신한다. `sources`는 `sources.remove(index)` 로 수동 동기화해야 한다 (`serialize_tests.rs:167` 참조).

> **RT-M1 주의**: renumber 후 page의 `/Annots` 배열이 가리키는 ObjectId가 바뀌므로, `get_annotations`가 올바른 ref를 따라가는지 `annot_type`·`rect`·`color` 단언으로 검증한다.

### 4-2. `rpdf-wasm` 통합 테스트 (신규, 재구성됨)

**위치**: `crates/rpdf-wasm/src/lib.rs` (기존 `#[cfg(test)]` 블록)

> **구현 주의**: native `cargo test`에서 `serde_wasm_bindgen::to_value()` / `from_value()`는 JavaScript 런타임이 없어 동작하지 않는다.
> UT-14는 `pdf.add_annotation(JsValue, ...)` WASM API를 **직접 호출하지 않는다**.
> 대신 Rust 함수 `add_annotation(bytes, &params)` + `load_document_tracked` + `PdfDocument { doc, sources }` 직접 구성 → `save()` 경로를 테스트한다.
> `cfg(not(target_arch = "wasm32"))` 가드는 기존 테스트와 동일하게 불필요.

| ID | 이름 | 시나리오 |
|----|------|----------|
| UT-14 | `ut_14_annotation_save_reload_roundtrip` | add_annotation(bytes, &params) [Rust fn] → load_document_tracked → PdfDocument{doc, sources} → save() → get_annotations: 1건 존재 |

### 4-3. 버그 수정 (필요 시)

테스트 실행 후 실패하면 근본 원인을 파악하고 수정한다.

예상 버그 후보:
- `serialize_single_source`: `delete_pages` 후 `/Annots` indirect ref 손상 → `get_annotations`에서 ref 팔로우 실패
- `serialize_multi_source`: annotation 객체가 `_` 분기로 수집되지만 page `/Annots` ref가 renumber 후 깨짐

---

## 5. 에러 처리 표

| 조건 | 기대 동작 |
|------|-----------|
| serialize 후 annotation 없음 | RT-* 테스트 실패 → 버그 수정 |
| delete_pages 후 /Annots ref 손상 | RT-4 실패 → insert_annot_ref 수정 또는 serialize 로직 수정 |
| renumber 후 ObjectId 불일치 | RT-* (multi-source) 실패 → serialize_multi_source 수정 |

---

## 6. 관련 파일

| 파일 | 변경 |
|------|------|
| `crates/rpdf-serializer/tests/annotation_roundtrip_tests.rs` | 신규: RT-1~RT-6, RT-M1 (7개) |
| `crates/rpdf-wasm/src/lib.rs` | UT-14 추가 (1개) |
| `crates/rpdf-edit/src/commands/annotation.rs` | 버그 수정 시 |
| `crates/rpdf-serializer/src/serialize.rs` | 버그 수정 시 |

---

## 7. 테스트 전략

- **F.I.R.S.T**: 모든 테스트는 실제 lopdf를 사용하되 인메모리 PDF로 I/O 없이 실행
- **헬퍼 공유**: `make_minimal_pdf`는 `rpdf-edit`의 기존 헬퍼를 참조 불가 (크레이트 경계). `rpdf-serializer/tests/`에 독립 헬퍼 작성.
- **검증 기준**: `annot_type`, `rect`, `color` 필드 일치. `annot_id`는 라운드트립 후 변경 가능하므로 검증 제외.

---

## 8. 체크포인트

- [ ] CP-1: `crates/rpdf-serializer/tests/` 테스트 RT-1~RT-6 + RT-M1 작성 + `cargo test -p rpdf-serializer` 통과
- [ ] CP-2: `crates/rpdf-wasm/src/lib.rs` UT-14 추가 + `cargo test -p rpdf-wasm` 통과
- [ ] CP-3: 버그 수정 (필요 시) + 전체 `cargo test` + `clippy` 통과
- [ ] CP-4: 완료 보고서 작성 + `/task-retro`

---

## 9. 공개 API 확인

- `rpdf-edit::add_annotation`, `get_annotations`: 기존 구현, docs 확인 완료
- `rpdf-serializer::serialize_document`, `load_document_tracked`: 기존 구현, 코드 확인 완료
- lopdf `Document::load_mem`, `delete_pages`: Task #43에서 사용, 동작 확인 완료

---

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | Scope & strategy | 0 | — | — |
| Codex Review | `/codex review` | Independent 2nd opinion | 0 | — | — |
| Eng Review | `/plan-eng-review` | Architecture & tests (required) | 1 | CLEAN | 4 issues, 2 critical gaps |
| Design Review | `/plan-design-review` | UI/UX gaps | 0 | — | — |
| DX Review | `/plan-devex-review` | Developer experience gaps | 0 | — | — |

- **CROSS-MODEL:** Claude subagent(outside voice) — RT-4 sources 수동 동기화 누락, RT-M1 픽스처 미명시 지적. 두 항목 계획서에 반영. cfg(wasm32) 가드 지적은 false positive.
- **UNRESOLVED:** 0
- **VERDICT:** ENG CLEARED — 리뷰 완료, 구현 시작 가능.
