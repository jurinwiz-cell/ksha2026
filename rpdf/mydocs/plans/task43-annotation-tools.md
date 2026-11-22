# Task #43 계획서 — 주석 도구 (하이라이트/밑줄/스티키노트)

**작성일**: 2026-05-21
**브랜치**: local/task43
**GitHub Issue**: #82
**선행 태스크**: Task #42 (서식 툴바) — PR #81 ✅

---

## 목표

PDF 페이지 위에 비파괴적 주석(annotation)을 추가·삭제·저장한다.

- **하이라이트**: 드래그로 영역 선택 → 반투명 노란 박스 오버레이 (PDF `/Highlight`)
- **밑줄**: 드래그로 영역 선택 → 하단 선 오버레이 (PDF `/Underline`)
- **스티키노트**: 클릭으로 위치 지정 + 텍스트 입력 팝업 (PDF `/Text`)
- Undo/Redo 지원 (Task #42의 NoOpCommand sentinel 패턴 재사용)
- 저장 시 PDF 파일에 annotation 포함

---

## 아키텍처 결정 (ADR)

| ID | 주제 | 결정 |
|----|------|------|
| D20 | 구현 레이어 | `apply_text_replace`와 동일한 순수 함수 패턴: `add_annotation(&[u8], params) → Vec<u8>`. Command trait 사용 안 함 (Document IR에 annotation 타입 없음) |
| D21 | PDF 주석 표현 | lopdf로 `/Annot` 딕셔너리 생성 → page `/Annots` 배열에 추가. QuadPoints 없이 `/Rect`만 사용 (구현 단순화) |
| D22 | Undo 동기화 | `execute_cmd(NoOpCommand, Some(new_sources))` — text_replace와 동일 패턴. Undo/Redo 후 `loadAnnotations` 재페치 필수 (→ D29) |
| D23 | 주석 ID | lopdf object_id `(u32, u16)` → `annot_id: u32` (generation 0 고정 가정)로 노출 |
| D24 | 하이라이트 색상 | 노란색 기본값 `[1.0, 1.0, 0.0]`, opacity 0.3 (`/CA 0.3`). 색상 선택 UI는 이번 범위 외 |
| D25 | 밑줄 색상 | 파란색 기본값 `[0.0, 0.0, 0.8]`. `/Underline`은 `/CA` 미지원 — opacity 생략 |
| D26 | 스티키노트 | `/Subtype /Text`, `/Rect [x y x+20 y+20]`, `/Contents "텍스트"`, `/Open false` |
| D27 | 좌표계 | 드래그 시작/끝을 screenToPdf 변환 후 Rust에 전달. `screenToPdf` 함수를 `pdf-coords.ts`에 추가 |
| D28 | `get_annotations` | 저장된 annotation을 읽어 UI 오버레이 복원 — 구현 범위 포함 |
| D29 | WASM 반환 타입 | `add_annotation`/`delete_annotation` → `AnnotationInfo[]` 반환 (PageInfo[] 불필요 — 페이지 구조 불변). Undo/Redo 액션도 `loadAnnotations` 호출. |
| D30 | 드래그 rect 정규화 | `annotation_drag.tsx`에서 mouseup 시 `[Math.min(x0,x1), Math.min(y0,y1), Math.max(x0,x1), Math.max(y0,y1)]`로 정규화. 방향 무관하게 올바른 /Rect 보장. |

---

## 데이터 모델

### Rust (`crates/rpdf-edit/src/commands/annotation.rs` 신규)

```rust
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub enum AnnotationType {
    Highlight,
    Underline,
    StickyNote,
}

/// PDF /Annot 생성 파라미터.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct AnnotationParams {
    /// 0-based 페이지 인덱스.
    pub page_index: usize,
    /// 주석 종류.
    pub annot_type: AnnotationType,
    /// Bounding rect [x0, y0, x1, y1] (PDF 좌표계, 좌하단 원점). 클라이언트가 정규화 후 전달.
    pub rect: [f64; 4],
    /// RGB 색상 0.0~1.0. 기본값: 하이라이트=[1,1,0], 밑줄=[0,0,0.8], 스티키노트=[1,1,0]
    pub color: [f64; 3],
    /// 스티키노트 텍스트. Highlight/Underline은 None.
    pub content: Option<String>,
    /// 하이라이트 불투명도 0.0~1.0. 기본값 0.3. Underline/StickyNote는 무시.
    pub opacity: f64,
}

/// PDF에서 읽어온 기존 주석 정보.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct AnnotationInfo {
    /// lopdf object_id.num (삭제 시 사용).
    pub annot_id: u32,
    pub annot_type: AnnotationType,
    pub rect: [f64; 4],
    pub color: [f64; 3],
    pub content: Option<String>,
    pub opacity: f64,
}

/// PDF 바이트에 주석을 추가한다.
pub fn add_annotation(data: &[u8], params: &AnnotationParams) -> Result<Vec<u8>, AnnotationError>

/// PDF 바이트에서 지정 페이지의 주석 목록을 읽는다.
pub fn get_annotations(data: &[u8], page_index: usize) -> Result<Vec<AnnotationInfo>, AnnotationError>

/// PDF 바이트에서 주석을 삭제한다.
pub fn delete_annotation(data: &[u8], page_index: usize, annot_id: u32) -> Result<Vec<u8>, AnnotationError>
```

### 에러 타입

```rust
#[derive(Debug, thiserror::Error)]
pub enum AnnotationError {
    #[error("페이지 인덱스 범위 초과: {0}")]
    PageIndexOutOfRange(usize),
    #[error("PDF 파싱 실패: {0}")]
    LoadFailed(String),
    #[error("PDF 저장 실패: {0}")]
    SaveFailed(String),
    #[error("주석을 찾을 수 없음: annot_id={0}")]
    AnnotationNotFound(u32),
}
```

### 에러 처리 표

| 에러 변형 | 발생 지점 | 테스트 |
|-----------|-----------|--------|
| `PageIndexOutOfRange` | page_ids[page_index] 접근 실패 | AN-E1 |
| `LoadFailed` | `lopdf::Document::load_mem` 실패 | AN-E2 |
| `SaveFailed` | `doc.save_to` 실패 | AN-E3 (단위 테스트 불가 — AN-1~5 성공 경로로 암묵적 커버) |
| `AnnotationNotFound` | `/Annots` 없거나 해당 id 없음 | AN-E4 |

---

## `add_annotation` 로직 (pseudo-code)

```
fn add_annotation(data, params):
  doc = lopdf::Document::load_mem(data)  // 실패 → LoadFailed
  page_ids = doc.page_iter().collect()
  page_id = page_ids[params.page_index]  // 범위 초과 → PageIndexOutOfRange

  annot_dict = build_annot_dict(params)  // /Type /Annot, /Subtype, /Rect, /C, /CA, /Contents
  annot_id = doc.add_object(annot_dict)  // ObjectId (u32, u16) 반환

  // 페이지 /Annots 배열에 참조 추가
  // [D-INDIRECT] /Annots는 inline 배열 또는 간접 참조(Object::Reference) 중 하나:
  page_obj = doc.get_object_mut(page_id)
  page_dict = page_obj.as_dict_mut()
  if page_dict.has(b"Annots"):
    annots_entry = page_dict.get_mut(b"Annots")
    match annots_entry:
      Object::Array(arr) → arr.push(Object::Reference(annot_id))
      Object::Reference(ref_id) →
        arr = doc.get_object_mut(ref_id).as_array_mut()
        arr.push(Object::Reference(annot_id))
  else:
    page_dict.set("Annots", Object::Array(vec![Object::Reference(annot_id)]))

  bytes = serialize(doc)  // 실패 → SaveFailed
  return bytes
```

**`build_annot_dict` 규칙:**
- 공통: `/Type /Annot`, `/Subtype <type>`, `/Rect [x0 y0 x1 y1]`, `/C [r g b]`
- Highlight 추가: `/CA <opacity>` (0.0~1.0)
- StickyNote 추가: `/Contents <string>`, `/Open false`, `/Name /Note`
- Underline: 추가 필드 없음

---

## `get_annotations` 로직

```
fn get_annotations(data, page_index):
  doc = lopdf::Document::load_mem(data)  // LoadFailed
  page_id = page_ids[page_index]          // PageIndexOutOfRange

  page_dict = doc.get_object(page_id).as_dict()
  // /Annots 없는 페이지는 빈 배열 반환 (AN-0 케이스)
  annots_arr = match page_dict.get(b"Annots"):
    None → return Ok(vec![])
    Some(Object::Array(arr)) → arr
    Some(Object::Reference(ref_id)) → doc.get_object(ref_id).as_array()

  result = []
  for annot_ref in annots_arr:
    annot_obj = doc.get_object(annot_ref).as_dict()
    subtype = annot_obj.get(b"Subtype") -> string
    annot_type = match subtype {
      "Highlight" → Highlight,
      "Underline" → Underline,
      "Text"      → StickyNote,
      _           → skip (알 수 없는 타입은 무시)
    }
    rect = annot_obj.get(b"Rect") -> [f64; 4]
    color = annot_obj.get(b"C") -> [f64; 3], default [0,0,0]
    content = annot_obj.get(b"Contents") -> Option<String>
    opacity = annot_obj.get(b"CA") -> f64, default 1.0
    result.push(AnnotationInfo { annot_id: annot_ref.num, ... })

  return result
```

---

## `delete_annotation` 로직

```
fn delete_annotation(data, page_index, annot_id):
  doc = lopdf::Document::load_mem(data)  // LoadFailed
  page_id = page_ids[page_index]          // PageIndexOutOfRange

  page_dict_mut = doc.get_object_mut(page_id).as_dict_mut()

  // [D-NOANNOTS] /Annots 없는 페이지 early return
  if !page_dict_mut.has(b"Annots"):
    return Err(AnnotationNotFound(annot_id))

  // [D-INDIRECT] /Annots 간접 참조 처리
  annots = match page_dict_mut.get_mut(b"Annots"):
    Object::Array(arr) → arr
    Object::Reference(ref_id) → doc.get_object_mut(ref_id).as_array_mut()

  original_len = annots.len()
  annots.retain(|o| o.as_reference().map(|(num, _)| num) != Ok(annot_id))
  if annots.len() == original_len → return Err(AnnotationNotFound(annot_id))

  // annot 오브젝트 제거 (garbage 방지)
  doc.objects.remove(&(annot_id, 0))

  bytes = serialize(doc)  // SaveFailed
  return bytes
```

---

## WASM API 추가

`crates/rpdf-wasm/src/lib.rs`에 3개 함수 추가:

```rust
/// 주석을 추가하고 해당 페이지의 갱신된 주석 목록을 반환한다.
/// (페이지 구조가 변하지 않으므로 PageInfo[]가 아닌 AnnotationInfo[] 반환)
pub fn add_annotation(&mut self, page_index: usize, params_json: String) -> Result<JsValue, JsValue>

/// 지정 페이지의 주석 목록을 반환한다.
pub fn get_annotations(&self, page_index: usize) -> Result<JsValue, JsValue>

/// 주석을 삭제하고 해당 페이지의 갱신된 주석 목록을 반환한다.
pub fn delete_annotation(&mut self, page_index: usize, annot_id: u32) -> Result<JsValue, JsValue>
```

**구현 세부:**
- `add_annotation` / `delete_annotation`: `execute_cmd(NoOpCommand, Some(new_sources))` 호출 후,
  새 bytes에 `get_annotations(new_bytes, page_index)`를 호출해 `AnnotationInfo[]` 반환.
- `get_annotations`: 읽기 전용, sources 변경 없음.

---

## TypeScript 변경

### `packages/ui/src/types/pdf.ts` 추가

```typescript
export type AnnotationType = 'Highlight' | 'Underline' | 'StickyNote'

export interface AnnotationParams {
  pageIndex: number
  annotType: AnnotationType
  rect: [number, number, number, number]  // [x0, y0, x1, y1] PDF coords (정규화 완료)
  color: [number, number, number]
  content?: string
  opacity: number
}

export interface AnnotationInfo {
  annotId: number
  annotType: AnnotationType
  rect: [number, number, number, number]
  color: [number, number, number]
  content?: string
  opacity: number
}

// WasmApi 확장
export interface WasmApi {
  // ... 기존 ...
  addAnnotation(pageIndex: number, paramsJson: string): Promise<AnnotationInfo[]>
  getAnnotations(pageIndex: number): Promise<AnnotationInfo[]>
  deleteAnnotation(pageIndex: number, annotId: number): Promise<AnnotationInfo[]>
}
```

### `packages/ui/src/core/pdf-coords.ts` 추가

```typescript
/**
 * 화면 좌표(좌상단 원점)를 PDF 좌표계(좌하단 원점)로 변환한다.
 * pdfToScreen의 수학적 역변환. MediaBox 원점 오프셋(x0, y0) 보정 포함.
 */
export function screenToPdf(
  screenX: number,
  screenY: number,
  mediaBox: [number, number, number, number],
  screenW: number,
  screenH: number
): { x: number; y: number }
```

### `packages/ui/src/state/document-store.ts` 추가

```typescript
annotationMode: 'none' | AnnotationType
annotations: AnnotationInfo[]  // 현재 페이지 주석 목록

setAnnotationMode: (mode: 'none' | AnnotationType) => void
loadAnnotations: (pageIndex: number) => Promise<void>
addAnnotation: (pageIndex: number, params: AnnotationParams) => Promise<void>
deleteAnnotation: (pageIndex: number, annotId: number) => Promise<void>
```

**⚠️ Undo/Redo 후 loadAnnotations 필수 (D29):**
```typescript
undo: async () => {
  // 기존 undo 로직
  await _api.undo()
  // annotations 재페치 — PDF bytes 복원 후 UI 동기화
  const annotations = await _api.getAnnotations(get().selectedPageIndex)
  set({ annotations })
}
// redo도 동일 패턴
```

### 신규 UI 컴포넌트

**`packages/ui/src/ui/annotation_toolbar.tsx`** (신규)
- 3개 버튼: 하이라이트 🟡 / 밑줄 U̲ / 스티키노트 📝
- 선택된 모드 강조. 다시 클릭 시 'none'으로 해제.

**`packages/ui/src/ui/annotation_layer.tsx`** (신규)
- `annotations` 목록을 PDF→화면 좌표 변환해 오버레이 렌더링
- Highlight: 반투명 노란 `div`
- Underline: 해당 rect 하단에 파란 선 (border-bottom)
- StickyNote: 📝 아이콘 클릭 시 내용 툴팁
- 오른쪽 클릭(또는 ×버튼) → `deleteAnnotation` 호출

**`packages/ui/src/ui/annotation_drag.tsx`** (신규)
- canvas 위에 포개진 투명 div로 마우스 이벤트 캡처
- `annotationMode !== 'none'`일 때만 활성화
- mousedown → mousemove → mouseup 으로 드래그 rect 계산
- **mouseup 시 rect 정규화 (D30)**: `[Math.min(x0,x1), Math.min(y0,y1), Math.max(x0,x1), Math.max(y0,y1)]`
- StickyNote는 클릭 지점에 입력창 팝업

### `page_viewer.tsx` 변경

- `AnnotationLayer`, `AnnotationDrag`, `AnnotationToolbar` 통합
- `loadAnnotations` 호출: 페이지 변경, 편집 후, Undo/Redo 후

---

## 구현 파일 목록

| 파일 | 변경 |
|------|------|
| `crates/rpdf-edit/src/commands/annotation.rs` | 신규 |
| `crates/rpdf-edit/src/commands/mod.rs` | annotation 모듈 등록 + re-export |
| `crates/rpdf-edit/src/commands/error.rs` | AnnotationError 추가 (또는 별도 error 선언) |
| `crates/rpdf-wasm/src/lib.rs` | add/get/delete_annotation 추가 |
| `packages/ui/src/types/pdf.ts` | AnnotationType, AnnotationParams, AnnotationInfo, WasmApi 확장 |
| `packages/ui/src/core/pdf-coords.ts` | screenToPdf 추가 |
| `packages/ui/src/state/document-store.ts` | annotation 상태 + 액션 추가, undo/redo에 loadAnnotations 추가 |
| `packages/ui/src/ui/annotation_toolbar.tsx` | 신규 |
| `packages/ui/src/ui/annotation_layer.tsx` | 신규 |
| `packages/ui/src/ui/annotation_drag.tsx` | 신규 (rect 정규화 포함) |
| `packages/ui/src/ui/page_viewer.tsx` | annotation 컴포넌트 통합 |
| `packages/studio/src/core/wasm-worker.ts` | addAnnotation/getAnnotations/deleteAnnotation 브릿지 |
| `packages/studio/src/main.tsx` | bridge 함수 추가 |
| `packages/desktop/src/core/wasm-worker.ts` | 동일 브릿지 동기화 |
| `packages/desktop/src/core/tauri-client.ts` | stub 추가 |
| `packages/desktop/src/main.tsx` | bridge 추가 |

> ⚠️ WasmApi breaking change: `desktop/src/core/wasm-worker.ts`, `desktop/src/core/tauri-client.ts`, `desktop/src/main.tsx` 모두 업데이트 필수 (Task #42 교훈).

---

## 테스트 전략

### Rust 단위 테스트 (`crates/rpdf-edit/src/commands/annotation.rs` 인라인)

| ID | 테스트 | 확인 내용 |
|----|--------|-----------|
| AN-0 | `get_annotations_empty_page` | /Annots 없는 페이지에서 빈 Vec 반환 확인 |
| AN-1 | `add_highlight_basic` | 하이라이트 추가 후 get_annotations로 1개 반환 |
| AN-2 | `add_underline_basic` | 밑줄 추가 후 type=Underline 확인 |
| AN-3 | `add_sticky_note` | StickyNote + content 추가 후 content 확인 |
| AN-4 | `delete_annotation` | 추가 → 삭제 → get_annotations 빈 배열 |
| AN-5 | `add_multiple_annotations` | 3개 추가 후 3개 반환 |
| AN-6 | `undo_add_annotation` | NoOpCommand 패턴으로 undo 시 주석 사라짐 (WASM 레벨 통합 테스트) |
| AN-E1 | `page_index_out_of_range` | PageIndexOutOfRange 반환 |
| AN-E2 | `invalid_pdf_bytes` | LoadFailed 반환 |
| AN-E4 | `delete_nonexistent` | AnnotationNotFound 반환 (including /Annots 없는 케이스) |

### TypeScript 단위 테스트 (`packages/ui/src/__tests__/`)

| ID | 테스트 | 확인 내용 |
|----|--------|-----------|
| ANN-UI-1 | `annotationMode 전환` | setAnnotationMode('Highlight') → state 반영 |
| ANN-UI-2 | `annotations 로드` | getAnnotations mock → store.annotations 업데이트 |
| ANN-UI-3 | `screenToPdf 변환` | 알려진 좌표 역변환 검증 |
| ANN-UI-4 | `screenToPdf-pdfToScreen 라운드트립` | pdfToScreen(screenToPdf(x, y)) ≈ (x, y) |
| ANN-UI-5 | `drag rect 정규화` | 우하→좌상 드래그 → [x_min, y_min, x_max, y_max] 보장 |

---

## 체크포인트

### CP-A: Rust annotation 함수 구현 + 단위 테스트
- `cargo test -p rpdf-edit` AN-0~AN-E4 통과
- `cargo clippy -- -D warnings` 경고 없음

### CP-B: WASM API 노출
- `cargo test -p rpdf-wasm` 통과
- `wasm-pack build crates/rpdf-wasm --target web` 성공

### CP-C: TypeScript 상태 + 좌표 변환
- `pnpm --filter @rpdf/ui test` ANN-UI-1~5 통과
- `pnpm typecheck` 통과 (studio + desktop + ui)

### CP-D: UI 컴포넌트 통합
- `pnpm typecheck` 전체 통과
- 브라우저에서 하이라이트/밑줄/스티키노트 추가·삭제 동작 확인
- 우하→좌상 드래그 시 정상 주석 생성 확인
- Undo/Redo 후 annotation overlay 동기화 확인

---

## 알려진 함정

1. **lopdf `/Annots` 간접 참조**: 페이지 dict의 /Annots가 `Object::Reference`인 경우 반드시 역참조 후 배열에 접근. pseudo-code에 명시됨.
2. **generation number**: lopdf object id는 `(num, gen)`. 삭제 시 `gen=0` 고정으로 처리 (일반 PDF에서 충분). retain에서 num만 비교.
3. **선택 rect 최소 크기**: 드래그 거리가 너무 짧으면 rect width/height가 0 — 클라이언트에서 최소 5pt 강제.
4. **Tauri desktop**: annotation stub 누락 시 CI 실패. 파일 목록에 명시 완료.
5. **Undo 후 스테일 annotations**: WASM undo는 PDF bytes만 복원 — `loadAnnotations` 재페치 필수. document-store undo/redo 액션에 명시.
6. **드래그 rect 정규화**: mouseup 시 Math.min/max로 [x_min, y_min, x_max, y_max] 보장. annotation_drag.tsx에 명시.

---

## NOT in scope

| 항목 | 이유 |
|------|------|
| 색상 선택 UI (color picker) | 기본값으로 충분. 추가 Task에서 구현 |
| 주석 텍스트 편집 (스티키노트 수정) | 첫 생성만 지원. edit은 후속 Task |
| 주석 이동/리사이즈 | 드래그 UX 복잡도. 후속 Task |
| 회전 페이지의 드래그 좌표 보정 | screenToPdf가 기본 회전 처리; 추가 보정은 후속 Task |
| Desktop(Tauri) 네이티브 annotation API | WASM 브릿지로 충분 |

---

## What already exists

| 기존 코드 | 재사용 여부 |
|-----------|------------|
| `apply_text_replace` 순수 함수 패턴 | ✅ 동일 패턴 — `add/delete_annotation`에 그대로 적용 |
| `NoOpCommand` sentinel | ✅ undo 동기화에 재사용 |
| `pdfToScreen` (pdf-coords.ts) | ✅ annotation_layer.tsx에서 오버레이 위치 계산에 재사용 |
| `TextBlockOverlay` 패턴 | ✅ annotation_layer.tsx 구현 참고 |
| `text_edit_panel.tsx` 툴바 구조 | ✅ annotation_toolbar.tsx 구조 참고 |
| studio/desktop 브릿지 패턴 | ✅ wasm-worker + main.tsx 패턴 동일하게 적용 |

---

## 외부 크레이트

- 새 크레이트 추가 없음. 기존 lopdf 0.40 사용.
- lopdf 관련 API (`page_iter`, `as_dict_mut`, `get_object_mut`, `objects: HashMap`)는 기존 `text_replace.rs`에서 이미 사용 중 — 호환성 확인 완료.

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | 범위 & 전략 | 0 | — | — |
| Outside Voice | `/plan-eng-review` 내장 | 독립 2차 검토 | 1 | issues_found | 9개 발견, 5개 반영 |
| Eng Review | `/plan-eng-review` | 아키텍처 & 테스트 (필수) | 1 | clean | 7개 이슈, 0개 미해결 |
| Design Review | `/plan-design-review` | UI/UX 갭 | 0 | — | — |
| DX Review | `/plan-devex-review` | DX 갭 | 0 | — | — |

- **OUTSIDE VOICE**: Claude subagent — 9개 발견, 주요 5개 (Undo 스테일, rect 정규화, /Annots 간접참조, 빈페이지 처리, roundtrip 테스트) 반영
- **CROSS-MODEL**: Undo 후 annotations 스테일 + drag rect 정규화 — 리뷰에서 누락됐으나 외부 의견으로 발견, 모두 계획서에 반영됨
- **UNRESOLVED**: 0개
- **VERDICT**: ENG REVIEW CLEARED — 구현 시작 가능
