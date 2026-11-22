# PDF 텍스트 편집 마일스톤 계획서 (Task #39–#45)
> **목표**: Acrobat 수준의 PDF 편집기. 흰박스 오버레이 방식으로 한글·영어 텍스트 편집, 주석, 이미지 삽입까지.
> **참조**: edwardkim/rhwp — 언어별 폰트 분리 설정, 폰트 세트 프리셋 UI 패턴 차용.

---

## 전체 기능 로드맵

| Phase | Tasks | 기능 |
|-------|-------|------|
| 1 | #39–#41 | 텍스트 블록 추출 + 텍스트 교체 + 기본 한글/영어 폰트 |
| 2 | #42–#43 | 서식 툴바(굵게/색상/크기/정렬) + 주석(하이라이트/밑줄/스티키노트/도형) |
| 3 | #44–#45 | 이미지 삽입/조작 + 텍스트 박스 삽입 + 페이지 재정렬(드래그&드롭) |

---

## 아키텍처 결정 (ADR)

### 왜 lopdf 직접 조작인가?

현재 serializer(`serialize_document`)는 IR(`Document.content`)을 PDF 바이트로 재인코딩하지 않는다.
`sources[i].bytes`를 lopdf로 로드한 뒤 구조적 변경만 적용한다.

모든 편집 기능은 동일 레이어에서 작동한다:

```
사용자 편집 확정
    ↓
apply_*(old_bytes, page_index, params)
    → lopdf 로드 → content stream / annotation 추가 → 저장
    ↓
new_bytes
    ↓
sources[page_index].bytes = new_bytes  ← 소스 바이트 교체
re-parse → SVG 재렌더링
```

**Undo**: 교체 전 `old_sources`를 `sources_undo`에 push → 기존 undo/redo 스택 재활용.

**D3 — Sources Arc 교체 정책**: `sources[page_index].bytes`만 교체하면 serializer의 단일소스 경로를 이탈한다.
텍스트 교체 후 **모든** `sources[i].bytes`를 동일한 새 Arc로 교체해야 serializer가 단일소스 경로를 유지한다.
```rust
let new_arc = Arc::new(new_pdf_bytes);
for src in sources.iter_mut() {
    src.bytes = Arc::clone(&new_arc);
}
```

**D2 — 클라이언트 오버레이 레이어**: CID 인코딩 텍스트는 re-parse 후 SVG에서 □으로 표시된다.
편집 확정 후 state에 `new_text`를 보존하고, SVG 위에 overlay div로 새 텍스트를 독립적으로 표시한다.
re-parse는 저장/내보내기 시에만 수행한다.

### 폰트 전략 (D1 결정: 정적 에셋 서빙 방식)

| 언어 | 기본 폰트 | 방식 |
|------|----------|------|
| 영어/숫자 | Helvetica (PDF 표준 내장) | 임베딩 불필요 |
| 한글 | NanumGothic-Regular.ttf | 정적 에셋 서빙 + runtime fetch, Type0/CIDFont |
| 추가 영어 폰트 | Noto Sans, Source Code Pro 등 | 사용자 선택 시 임베딩 |
| 추가 한글 폰트 | NanumMyeongjo, Spoqa Han Sans 등 | 사용자 선택 시 임베딩 |

**폰트 제공 방식 (D1)**: `include_bytes!` WASM 번들은 WASM 바이너리 크기를 ~8MB 이상 늘리므로 채택하지 않는다.
- **Web(WASM)**: `packages/studio/public/fonts/NanumGothic-Regular.ttf` 에 배치 → `fetch('/fonts/...')` runtime fetch
- **Tauri(Desktop)**: `tauri.conf.json`의 `bundle.resources` 에셋으로 내장 → 오프라인 지원, CDN 불필요
- `apply_text_replace` 호출 시 font TTF bytes를 파라미터로 전달하여 Rust 코드가 경로에 의존하지 않게 한다

**rhwp 패턴 차용**: 한글 폰트 / 영어 폰트를 독립적으로 선택할 수 있도록 UI를 구성한다.
새 텍스트 삽입 시 "한글 문자 → 한글 폰트로 렌더링, 나머지 → 영어 폰트로 렌더링" 하거나,
MVP에서는 단일 폰트(NanumGothic)로 한글·영어 모두 커버한다(NanumGothic은 라틴 글리프 포함).

---

## Phase 1 — Task #39: TextBlock 추출 API

**목표**: 페이지에서 클릭 가능한 텍스트 블록 목록(위치 + 내용)을 추출한다.

### 데이터 모델

```rust
// rpdf-core/src/types/text_block.rs (신규)
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub struct TextBlock {
    pub text: String,
    pub x: f64,        // 좌하단 x (PDF 좌표계, pt, y-axis up)
    pub y: f64,        // 좌하단 y
    pub width: f64,    // 추정 너비 (pt)
    pub height: f64,   // = font_size (pt)
    pub font_size: f64,
    pub font_ref: String,             // D4: 리소스 참조명 (예: "F1") — PDF 내부 키
    pub font_family: Option<String>,  // D4: 실제 폰트명 (예: "NanumGothic") — UI 표시용
}
```

> **D4 결정**: `font_name` 대신 `font_ref` + `font_family`로 분리. PDF 내부 참조명("F1")은 UI에 노출되지 않으며, Resources /Font 딕셔너리에서 /BaseFont 값을 읽어 `font_family`를 채운다.

### 구현

**위치**: `rpdf-svg/src/extract.rs` (신규)

```rust
pub fn extract_text_blocks(page: &Page) -> Vec<TextBlock>
```

Content stream 순회 규칙:

| 연산자 | 처리 |
|--------|------|
| `SetFont(name, size)` | `current_font_name`, `current_font_size` 갱신 |
| `SetTextMatrix(a,b,c,d,e,f)` | `tx = e`, `ty = f` |
| `MoveText(dx, dy)` | `tx += dx`, `ty += dy` |
| `MoveToNextLine` | `ty -= leading` |
| `ShowText(bytes)` | TextBlock 생성 |
| `ShowTextAdjusted(array)` | 문자열 요소마다 TextBlock 생성 |
| `BeginText` / `EndText` | TextState 초기화 / 리셋 |

**너비 추정** (폰트 메트릭 없이 근사):
- ASCII: `font_size × char_count × 0.55`
- CJK/한글: `font_size × char_count × 0.95`
- 혼합: 문자별 분류 후 합산

### API

**WASM**:
```rust
#[wasm_bindgen]
pub fn get_text_blocks(&self, page_index: usize) -> Result<JsValue, JsValue>
// → JSON: TextBlock[]
```

**Tauri**:
```rust
#[tauri::command]
pub fn get_text_blocks(page_index: usize, state: State<'_, AppState>) -> Result<Vec<TextBlock>, String>
```

### 테스트 전략

| ID | 내용 |
|----|------|
| TB-1 | `SetTextMatrix + ShowText` → 블록 1개, 위치 정확 |
| TB-2 | `MoveText` 누적 위치 |
| TB-3 | `ShowTextAdjusted` 문자열 요소 추출 |
| TB-4 | 한글 너비 > ASCII 너비 |
| TB-5 | 빈 페이지 → 빈 vec |
| TB-6 | `BeginText(BT)` 없이 `ShowText` 등장 → 방어적으로 블록 생성 (malformed PDF 대응) |

---

## Phase 1 — Task #40: TextReplace 백엔드 + 폰트 시스템

**목표**: 흰박스 오버레이 + 새 텍스트 삽입. 한글/영어 모두 지원하는 폰트 시스템.

### 공유 핵심 함수

```rust
// rpdf-edit/src/commands/text_replace.rs (신규)
pub struct TextReplaceParams {
    pub page_index: usize,
    pub cover_rect: [f64; 4],   // [x0, y0, width, height] PDF 좌표
    pub new_text: String,
    pub font_size: f64,
    pub text_x: f64,
    pub text_y: f64,
    pub font_config: FontConfig,
}

// D6: camelCase로 직렬화 — TypeScript FontConfig 필드명과 일치
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct FontConfig {
    pub korean_font: BuiltinFont,
    pub latin_font: BuiltinFont,
}

pub enum BuiltinFont {
    // 한글 폰트 (TTF 번들)
    NanumGothic,
    NanumMyeongjo,
    // 영어 폰트 (PDF 표준 내장, 임베딩 불필요)
    Helvetica,
    TimesRoman,
    Courier,
}

pub fn apply_text_replace(
    pdf_bytes: &[u8],
    params: &TextReplaceParams,
    font_bytes: Option<&[u8]>,  // D1: TTF 바이트를 외부에서 전달, Rust 코드가 경로에 의존하지 않음
) -> Result<Vec<u8>, TextReplaceError>
```

### lopdf Content Stream 조작

```
// 1. 흰 사각형 (원본 텍스트 가리기)
q
1 1 1 rg
{x0} {y0} {w} {h} re
f
Q

// 2. 새 텍스트 삽입 (한글 포함 시 Type0, 영어만 시 Type1)
BT
/KoFont {font_size} Tf
{text_x} {text_y} Td
<GID hex encoding> Tj    ← Type0 (한글/한자)
ET
```

### 한글 폰트 임베딩 (Type0/CIDFont)

번들 폰트 (D1 — 정적 에셋 서빙, include_bytes! 금지):
- `NanumGothic-Regular.ttf` (OFL, ~7.8MB) — `packages/studio/public/fonts/` + Tauri resources
- `NanumMyeongjo-Regular.ttf` (OFL, ~8.6MB) — `packages/studio/public/fonts/` + Tauri resources
- `apply_text_replace`는 TTF bytes를 파라미터로 받음 (`font_bytes: Option<&[u8]>`) — Rust가 경로에 의존하지 않음

lopdf 딕셔너리 구조:
```
Type0 Font ("KoFont" | "KoMyeongjo")
├── /Subtype /Type0
├── /Encoding /Identity-H
├── /DescendantFonts [CIDFont]
└── /ToUnicode <CMap stream>   ← 복사·검색 지원

CIDFont
├── /Subtype /CIDFontType2
├── /BaseFont /NanumGothic
├── /CIDSystemInfo <Adobe-Identity-0>
├── /FontDescriptor [FontDescriptor]
└── /W [width array]           ← ttf-parser로 추출

FontDescriptor
├── /FontBBox / /Flags / /ItalicAngle
├── /Ascent / /Descent / /CapHeight
└── /FontFile2 <TTF stream>
```

텍스트 인코딩:
- `ttf-parser`로 `char → GlyphId` 변환
- PDF hex string: `<GGGG GGGG ...>` (big-endian u16)

영어 표준 폰트:
- Helvetica, Times-Roman, Courier는 PDF 스펙 14 표준 폰트 — 임베딩 없이 /Type /Font /Subtype /Type1로 참조

**폰트 중복 임베딩 방지**: Resources `/Font` 딕셔너리에 키 존재 여부 확인 후 스킵.

### 의존성

```toml
# workspace Cargo.toml 추가
ttf-parser = "0.25"   # pure Rust, WASM 호환
```

### 에러 표

| 에러 | 발생 조건 |
|------|----------|
| `PageOutOfBounds` | `page_index >= page_count` |
| `LoadFailed(e)` | lopdf 로드 실패 |
| `FontEmbedFailed(e)` | 폰트 딕셔너리 구성 실패 |
| `GlyphNotFound(ch)` | 폰트에 글리프 없음 (fallback: 빈 칸) |
| `SaveFailed(e)` | lopdf 저장 실패 |

### WASM API

```rust
#[wasm_bindgen]
pub fn replace_text(
    &mut self,
    page_index: usize,
    block_json: &str,      // JSON.stringify(TextBlock)
    new_text: &str,
    font_config_json: &str, // JSON.stringify(FontConfig)
) -> Result<JsValue, JsValue>  // → PageInfo[]
```

Undo 통합: `sources_undo.push(old_sources)` → 기존 undo/redo 그대로.

### Tauri Command

```rust
#[tauri::command]
pub async fn replace_text(
    page_index: usize,
    block: TextBlock,
    new_text: String,
    font_config: FontConfig,
    state: State<'_, AppState>,
) -> Result<OpenResult, String>
```

### 테스트 전략

| ID | 내용 |
|----|------|
| TR-1 | ASCII 교체 후 바이트 길이 증가 |
| TR-2 | 한글 삽입 후 재파싱 성공 |
| TR-3 | 흰 박스 content stream 요소 (`re f`) 존재 확인 |
| TR-4 | KoFont 리소스 등록 확인 |
| TR-5 | Undo 후 원본 바이트 복원 |
| TR-6 | 동일 페이지 2회 편집 → KoFont 중복 없음 |
| TR-7 | `page_index >= page_count` → PageOutOfBounds |
| TR-8 | 영어 폰트(Helvetica) 선택 시 FontFile2 없음 |

---

## Phase 1 — Task #41: UI 텍스트 편집 레이어 + 폰트 선택

**목표**: PageViewer에서 텍스트 클릭 → 인라인 에디터 → 폰트 선택 포함 편집 확정.

### 좌표 변환

```typescript
// PDF pt → 화면 px 변환
// D10: MediaBox 원점 오프셋 보정 — MediaBox = [x0, y0, x1, y1]
// x0/y0가 0이 아닌 PDF(예: [100 100 500 800])에서도 정확히 정렬되도록 한다.
function pdfToScreen(
  pdfX: number, pdfY: number,
  mediaBox: [number, number, number, number],  // [x0, y0, x1, y1]
  screenW: number, screenH: number
) {
  const [x0, y0, x1, y1] = mediaBox;
  const pdfW = x1 - x0;
  const pdfH = y1 - y0;
  return {
    x: (pdfX - x0) * (screenW / pdfW),
    y: (pdfH - (pdfY - y0)) * (screenH / pdfH),   // y축 반전 + 원점 보정
  }
}
```

### UI 컴포넌트 구조

```
PageViewer
├── <svg>  (기존 SVG 렌더링)
└── <div class="text-overlay-layer">  (SVG 위 absolute)
    ├── TextBlockOverlay[]  (hover 하이라이트, 클릭 → editingBlock 설정)
    └── TextEditPanel  (editingBlock !== null 일 때)
        ├── FontToolbar                  ← rhwp 패턴
        │   ├── FontFamilyPicker        (한글 폰트 / 영어 폰트 독립 선택)
        │   │   ├── 한글: NanumGothic | NanumMyeongjo
        │   │   └── 영어: Helvetica | TimesRoman | Courier
        │   ├── FontSizePicker          (8, 10, 12, 14, 18, 24, 36, 48, 72pt)
        │   ├── StyleButtons            (Bold / Italic — 향후 Phase 2)
        │   └── ColorPicker             (글자색 — 향후 Phase 2)
        ├── <textarea>                  (원본 텍스트 pre-fill, autoFocus)
        └── ActionButtons
            ├── 확정 (Enter / ✓ 버튼)
            └── 취소 (Escape / ✗ 버튼)
```

### FontFamilyPicker (rhwp 언어별 폰트 분리 패턴)

```tsx
<div class="font-picker-row">
  <label>한글 폰트</label>
  <select value={koreanFont} onChange={setKoreanFont}>
    <option value="NanumGothic">나눔고딕</option>
    <option value="NanumMyeongjo">나눔명조</option>
  </select>
</div>
<div class="font-picker-row">
  <label>영어 폰트</label>
  <select value={latinFont} onChange={setLatinFont}>
    <option value="Helvetica">Helvetica</option>
    <option value="TimesRoman">Times Roman</option>
    <option value="Courier">Courier</option>
  </select>
</div>
```

MVP에서는 단일 폰트(NanumGothic)로 한글·영어 모두 처리. Phase 2에서 Latin 전환 로직 추가.

### document-store 확장

```typescript
interface DocumentState {
  // ... 기존 필드 ...
  textBlocks: TextBlock[]
  editingBlock: TextBlock | null
  fontConfig: FontConfig  // 현재 선택된 폰트 설정 (세션 유지)

  loadTextBlocks: (pageIndex: number) => Promise<void>
  setEditingBlock: (block: TextBlock | null) => void
  replaceText: (block: TextBlock, newText: string) => Promise<void>
  setFontConfig: (config: FontConfig) => void
}

interface FontConfig {
  koreanFont: 'NanumGothic' | 'NanumMyeongjo'
  latinFont: 'Helvetica' | 'TimesRoman' | 'Courier'
  fontSize: number
}
```

### 타입 정의 (`@rpdf/ui/src/types/pdf.ts`)

```typescript
export interface TextBlock {
  text: string; x: number; y: number
  width: number; height: number
  font_size: number; font_name: string
}
export interface FontConfig {
  korean_font: string
  latin_font: string
  font_size: number
}
// WasmApi에 추가
getTextBlocks(pageIndex: number): Promise<TextBlock[]>
replaceText(pageIndex: number, blockJson: string, newText: string, fontConfigJson: string): Promise<PageInfo[]>
```

### 테스트 전략

| ID | 내용 |
|----|------|
| UI-1 | `pdfToScreen` — 표준 MediaBox [0,0,595,842] 변환 정확성 (vitest) |
| UI-1b | `pdfToScreen` — D10: 비표준 MediaBox [100,100,500,800] 원점 오프셋 보정 검증 |
| UI-2 | 텍스트 블록 로드 후 overlay div 수 = blocks.length |
| UI-3 | 클릭 → editingBlock 설정 |
| UI-4 | Escape → editingBlock = null |
| UI-5 | 확정 → replaceText 호출, fontConfig 전달 확인 |
| UI-6 | 폰트 변경 → 다음 편집에 반영 |
| UI-7 | D2: 편집 확정 후 overlay에 new_text가 표시되고 re-parse 없이도 UI 반영됨 |

---

## Phase 2 — Task #42: 서식 툴바 (텍스트 포맷팅)

**목표**: 텍스트 추가 시 굵게·기울임·색상·정렬을 지정할 수 있다.

### 기능 목록

| 기능 | PDF 구현 |
|------|----------|
| 굵게 (Bold) | Bold 변형 폰트 또는 `setLineWidth` + stroke 렌더링 |
| 기울임 (Italic) | Italic 변형 폰트 또는 `Tm` 행렬 기울이기 |
| 글자 색상 | `rg` / `RG` 연산자로 fill color 변경 |
| 정렬 (좌/중/우) | 텍스트 x 위치 + 너비 기준 계산 |
| 밑줄 | 텍스트 아래 `l` 선 그리기 |
| 취소선 | 텍스트 중간에 `l` 선 그리기 |

### 데이터 모델 확장

```rust
pub struct TextStyle {
    pub bold: bool,
    pub italic: bool,
    pub underline: bool,
    pub strikethrough: bool,
    pub color: [f64; 3],     // RGB 0.0~1.0
    pub alignment: TextAlign,
}

pub enum TextAlign { Left, Center, Right }
```

### UI (Toolbar 확장)

```
TextEditPanel
└── FontToolbar
    ├── [기존] FontFamilyPicker, FontSizePicker
    ├── [신규] B / I / U / S 버튼 (토글)
    ├── [신규] 글자색 ColorPicker (간단한 팔레트 + hex 입력)
    └── [신규] ← / ↔ / → 정렬 버튼
```

---

## Phase 2 — Task #43: 주석 도구 (Annotations)

**목표**: Acrobat 수준의 주석 기능을 구현한다.

### 주석 종류 및 PDF 구현

| 주석 | PDF 객체 | 설명 |
|------|----------|------|
| 하이라이트 | `/Highlight` Annotation | 선택 영역 노란 반투명 박스 |
| 밑줄 | `/Underline` Annotation | 텍스트 아래 선 |
| 취소선 | `/StrikeOut` Annotation | 텍스트 중간 선 |
| 스티키노트 | `/Text` Annotation | 말풍선 아이콘 + 팝업 텍스트 |
| 텍스트 박스 | `/FreeText` Annotation | 자유 위치 텍스트 (테두리 있음) |
| 자유 드로잉 | `/Ink` Annotation | 마우스 드래그 곡선 |
| 직선 | `/Line` Annotation | 시작-끝점 직선 |
| 화살표 | `/Line` + `/LE` | 화살표 머리 있는 직선 |
| 사각형 | `/Square` Annotation | 사각형 테두리 |
| 원 | `/Circle` Annotation | 원형 테두리 |

### lopdf Annotation 추가 패턴

> **D11 필수 요건**: Annotation 딕셔너리에 `/AP` (Appearance Stream) 없이 저장하면
> Acrobat/Chrome/Firefox/iOS 등 모든 주요 뷰어에서 annotation이 **표시되지 않는다**.
> 각 annotation 타입별 /AP XObject 스트림을 반드시 생성해야 한다.

```rust
// 하이라이트 예시 — /AP 포함 (D11)
let ap_stream = build_highlight_ap_stream(rect, quad_points, color, alpha);
let ap_ref = doc.add_object(ap_stream);

let annot = dictionary! {
    "Type" => "Annot",
    "Subtype" => "Highlight",
    "Rect" => Array(rect_array),
    "QuadPoints" => Array(quad_points),
    "C" => Array(vec![1.0.into(), 1.0.into(), 0.0.into()]),  // 노란색
    "CA" => 0.5_f64,  // 50% 투명도
    "AP" => dictionary! { "N" => ap_ref },  // D11: Normal appearance stream
};
```

**/AP 스트림 형식 (타입별)**:

| 주석 | /AP 스트림 내용 |
|------|----------------|
| Highlight | `q {alpha} g {rect} re f Q` — 반투명 색칠 사각형 |
| Underline | `{color} RG {x0} {y} m {x1} {y} l S` — 선 그리기 |
| StrikeOut | `{color} RG {x0} {ym} m {x1} {ym} l S` — 중간선 |
| Text (스티키) | 아이콘 XObject (최소: 노란 사각형 + 텍스트 팝업) |
| FreeText | `BT /F {size} Tf {x} {y} Td ({text}) Tj ET` |

### UI 구조

```
MainToolbar (상단 툴바 확장)
├── [기존] 파일 열기 / 저장 / 회전 / Undo / Redo
└── [신규] 모드 선택 버튼
    ├── 편집 모드 (E) — 텍스트 클릭 편집
    ├── 주석 모드 (A) — 주석 추가
    └── 보기 모드 (V) — 기본

주석 모드 선택 시 SubToolbar 표시:
├── 하이라이트 (H)
├── 밑줄 (U)
├── 스티키노트 (N)
├── 텍스트 박스 (T)
├── 자유 드로잉 (P)
├── 직선 (L)
├── 화살표 (→)
├── 사각형 (□)
└── 원 (○)
```

### 텍스트 선택 (하이라이트/밑줄 공통)

- PageViewer에 마우스 드래그 → 텍스트 블록 범위 선택 (SVG 좌표 → PDF 좌표 변환)
- 선택된 블록 목록 → QuadPoints 배열로 변환 → Annotation 생성

---

## Phase 3 — Task #44: 이미지 삽입/조작

**목표**: PDF 페이지에 이미지를 추가하고 크기/위치를 조정한다.

### 기능

| 기능 | 설명 |
|------|------|
| 이미지 삽입 | PNG/JPEG → XObject로 PDF에 추가 |
| 위치 조정 | 드래그&드롭으로 이동 |
| 크기 조정 | 핸들 드래그로 리사이즈 |
| 삭제 | 이미지 XObject 및 content stream 참조 제거 |

### lopdf 이미지 삽입

```rust
// Task #36 tiff_to_pdf에서 검증된 패턴 재활용
// XObject + content stream q/cm/Do/Q 패턴
```

---

## Phase 3 — Task #45: 페이지 재정렬 + 텍스트 박스 삽입

**목표**: 썸네일 드래그&드롭 페이지 순서 변경. 자유 위치 텍스트 박스 추가.

### 페이지 재정렬

- ThumbnailSidebar에 DnD 구현 (`@dnd-kit/core` 또는 `react-beautiful-dnd`)
- 순서 변경 → `reorder_pages` WASM/Tauri command
- 기존 CommandStack 통합 (Undo 지원)

### 자유 텍스트 박스

- 빈 공간 클릭 (편집 모드) → 텍스트 박스 생성
- `/FreeText` Annotation 방식
- 폰트 설정은 Task #41과 동일

---

## 의존성 요약

| 크레이트 | 버전 | 용도 | WASM |
|----------|------|------|------|
| `ttf-parser` | 0.25 | TrueType glyph 메트릭 | ✅ |
| (기존) `lopdf` | 0.40 | PDF 조작 | ✅ |

npm:
| 패키지 | 용도 |
|--------|------|
| `@dnd-kit/core` | 페이지 재정렬 DnD (Task #45) |

---

## 폰트 에셋 목록 (D1 — 정적 에셋 서빙)

| 파일 | 크기 | 라이선스 | 배치 위치 |
|------|------|----------|-----------|
| `NanumGothic-Regular.ttf` | ~7.8MB | OFL | `packages/studio/public/fonts/` + Tauri resources |
| `NanumMyeongjo-Regular.ttf` | ~8.6MB | OFL | `packages/studio/public/fonts/` + Tauri resources |

> **MVP**: NanumGothic만 포함. NanumMyeongjo는 Task #41에서 추가.
> **향후**: 폰트 서브셋으로 용량 90% 절감 가능 (v0.7+).
> **WASM 번들**: `include_bytes!` 방식 금지 — 번들 크기 ~8MB 이상 증가로 로딩 시간 급격히 증가.

---

## 구현 순서 (의존성 그래프)

```
Task #39 (TextBlock 추출)
    ↓
Task #40 (TextReplace 백엔드 + 폰트 시스템)
    ↓
Task #41 (UI 텍스트 편집 레이어 + 폰트 선택)
    ↓
Task #42 (서식 툴바)
    │
    ├── Task #43 (주석 도구)
    │
    └── Task #44 (이미지 삽입)
            ↓
        Task #45 (페이지 재정렬 + 텍스트 박스)
```

---

## 체크포인트

### CP-A (Task #39)
- [ ] `extract_text_blocks` 단위 테스트 통과
- [ ] WASM / Tauri `get_text_blocks` 빌드 성공
- [ ] `cargo test`, `cargo clippy` 통과

### CP-B (Task #40)
- [ ] `apply_text_replace` ASCII + 한글 단위 테스트 통과
- [ ] NanumGothic 임베딩 PDF → 한글 표시 확인 (브라우저/Acrobat)
- [ ] Undo 후 원본 복원
- [ ] `cargo test`, `cargo clippy` 통과

### CP-C (Task #41)
- [ ] 클릭 → 인라인 에디터 + 폰트 선택 UI 표시
- [ ] 한글/영어 입력 후 Enter → PDF에 반영
- [ ] 폰트 변경 적용 확인
- [ ] `pnpm test`, `pnpm typecheck` 통과

### CP-D (Task #42–#43)
- [ ] 굵게/색상 지정 후 PDF 저장 확인
- [ ] 하이라이트/스티키노트 주석 추가 후 Acrobat에서 확인

### CP-E (Task #44–#45)
- [ ] 이미지 삽입 → PDF 저장 확인
- [ ] 썸네일 드래그&드롭 → 순서 변경 + Undo 동작

---

## 알려진 한계 및 후속 작업

| 한계 | 후속 |
|------|------|
| 너비 추정 부정확 | v0.7: ttf-parser 정밀 측정 |
| 전체 폰트 임베딩 (~8MB 증가) | v0.7: 서브셋 임베딩 |
| **D9: CTM(cm 연산자) 추적 없음** — cm이 포함된 페이지 텍스트 편집 불가 | Phase 2: CTM 누적 행렬 추적 추가 |
| `MoveShowText`(`'`) / `MoveSetShowText`(`"`) 연산자 미처리 — 해당 연산자 사용 PDF에서 텍스트 누락 | Phase 2: 연산자 처리 추가 |
| `ShowTextAdjusted(TJ)` 배열 내 커닝 값 무시 — 연속 블록이 동일 x 위치 기록 | Phase 2: 커닝 누적 적용 |
| 암호화된 PDF 편집 불가 | 별도 Task |
| 모바일 터치 미지원 | 별도 마일스톤 |
| 찾기/바꾸기 | Phase 3+ |
| 머리말/꼬리말/페이지 번호 | Phase 3+ |

---

## 관련 문서

- 아키텍처: `mydocs/manual/architecture.md`
- 직렬화: `crates/rpdf-serializer/src/serialize.rs`
- Command 트레이트: `crates/rpdf-edit/src/commands/traits.rs`
- WASM 바인딩: `crates/rpdf-wasm/src/lib.rs`
- Tauri commands: `packages/desktop/src-tauri/src/commands.rs`
- 참조: edwardkim/rhwp 폰트 세트 UI (`font-set-dialog.ts`, `toolbar.ts`)

---

## GSTACK REVIEW REPORT

**리뷰 완료일**: 2026-05-20  
**리뷰어**: /plan-eng-review (Claude Sonnet 4.6) + Gemini 2.5 Pro (외부 목소리)  
**브랜치**: devel

### 결정 요약 (D1–D11)

| ID | 주제 | 결정 |
|----|------|------|
| D1 | 폰트 로딩 전략 | 정적 에셋 서빙 (`public/fonts/` + Tauri resources). `include_bytes!` 금지 |
| D2 | 편집 후 SVG 표시 | 클라이언트 오버레이 레이어. re-parse 없이 state에서 new_text 표시 |
| D3 | sources Arc 교체 | 편집 후 **모든** sources[i].bytes를 동일 Arc로 교체 (단일소스 직렬화 유지) |
| D4 | TextBlock 필드 | `font_ref` (PDF 내부 키) + `font_family` (UI 표시용 실제 폰트명) 분리 |
| D5 | Undo 통합 | 기존 `sources_undo` 스택 재활용. 텍스트 편집도 동일 스택으로 Undo |
| D6 | FontConfig 직렬화 | `#[serde(rename_all = "camelCase")]` — TypeScript camelCase 필드명 일치 |
| D7 | WASM API 시그니처 | `replace_text`에 `font_bytes: Option<&[u8]>` 추가 (D1 연동) |
| D8 | TTF font_bytes 전달 | WASM 호출 측에서 fetch 결과를 ArrayBuffer로 전달. Tauri는 `std::fs::read` |
| D9 | CTM(cm) 처리 | Phase 1: cm 연산자 포함 페이지 편집 제외 (알려진 한계). Phase 2에서 추적 |
| D10 | pdfToScreen 공식 | Phase 1에서 MediaBox x0/y0 오프셋 보정 즉시 적용. `(pdfX - x0)`, `(pdfH - (pdfY - y0))` |
| D11 | Annotation /AP | Task #43 필수 항목. /AP 없이는 주요 뷰어에서 annotation 불표시 |

### Gemini 외부 목소리 핵심 지적

1. **sources Arc 분기** — 단일 페이지만 교체 시 serializer single-source path 이탈. → D3 해결
2. **CID 텍스트 SVG 표시** — re-parse 후 □ 표시. → D2 클라이언트 오버레이로 해결
3. **CTM 누락** — cm 연산자 무시로 텍스트 좌표 오류. → D9 Phase 1 제외로 해결
4. **MediaBox 원점 오프셋** — `y: (pdfH - pdfY)` 공식 버그. → D10 Phase 1 수정으로 해결
5. **/AP Appearance Stream** — Annotation 저장 후 주요 뷰어 불표시. → D11 Task #43 필수 항목으로 해결

### 리뷰 준비도 (Review Readiness Dashboard)

```
Architecture   ████████████ 9/10  (sources Arc + 클라이언트 오버레이 설계 완성)
API Contract   ████████████ 9/10  (font_bytes 파라미터, serde camelCase 명시)
Test Coverage  ██████████░░ 8/10  (UI-1b/UI-7 추가, CTM 커버 미포함)
Error Handling ████████████ 9/10  (에러 표 완성)
Known Limits   ████████████ 10/10 (CTM/MediaBox/AP 모두 명시)
Font Strategy  ████████████ 10/10 (D1 정적 에셋, 오프라인 지원)
─────────────────────────────────
Overall        ██████████░░ 9.2/10  → 구현 진행 승인
```

**승인 조건**: 위 결정사항 반영 완료. 계획서 업데이트 완료. → generator subagent에 Task #39부터 위임 가능.
