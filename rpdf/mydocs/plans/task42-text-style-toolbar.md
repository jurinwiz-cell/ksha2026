# Task #42 계획서 — 서식 툴바 (텍스트 스타일)

**작성일**: 2026-05-21  
**브랜치**: local/task42  
**선행 태스크**: Task #40 (TextReplace 백엔드), Task #41 (UI 편집 레이어)

---

## 목표

텍스트 편집 시 굵게·기울임·밑줄·취소선·색상·정렬을 지정할 수 있다.  
추가 TTF 파일 없이 PDF content stream 연산자만으로 구현한다.

---

## 아키텍처 결정 (ADR)

| ID | 주제 | 결정 |
|----|------|------|
| D12 | Bold 구현 방식 | PDF text rendering mode 2 (fill+stroke). `2 Tr` 연산자 + `setLineWidth 0.5`. Bold TTF 추가 없음 |
| D13 | Italic 구현 방식 | `Tm` oblique matrix `[1 0.2 0 1 x y]`. 별도 Italic TTF 없음 |
| D14 | Color | `rg` 연산자 (PDF 좌표계 RGB 0.0~1.0). 기본값 `[0,0,0]` (검정) |
| D15 | Underline/Strikethrough | content stream에 선 그리기: `q {r} {g} {b} RG {x} {y} m {x+w} {y} l S Q` |
| D16 | Alignment 계산 위치 | **Rust 백엔드**에서 계산. Task #39 너비 추정 공식 재사용. frontend는 `alignment` 전달만 |
| D17 | TextStyle 위치 | `rpdf-edit`에 `TextStyle` + `TextAlign` 신규 타입. `TextReplaceParams`에 추가 |
| D18 | WASM API 변경 | `replace_text`에 `text_style_json: String` 추가 (breaking change, Task #41 이후) |
| D19 | 색상 UI | 8색 팔레트 (검정/빨강/파랑/초록/주황/보라/분홍/회색) + hex 직접 입력 |

---

## 데이터 모델

### Rust (`crates/rpdf-edit/src/commands/text_replace.rs` 추가)

```rust
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct TextStyle {
    pub bold: bool,
    pub italic: bool,
    pub underline: bool,
    pub strikethrough: bool,
    pub color: [f64; 3],          // RGB 0.0~1.0
    pub alignment: TextAlign,
}

impl Default for TextStyle {
    fn default() -> Self {
        Self {
            bold: false,
            italic: false,
            underline: false,
            strikethrough: false,
            color: [0.0, 0.0, 0.0],
            alignment: TextAlign::Left,
        }
    }
}

#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub enum TextAlign { Left, Center, Right }
```

`TextReplaceParams`에 `text_style: TextStyle` 필드 추가:
```rust
pub struct TextReplaceParams {
    // 기존 필드 유지
    pub page_index: usize,
    pub cover_rect: [f64; 4],
    pub new_text: String,
    pub text_x: f64,
    pub text_y: f64,
    pub font_config: FontConfig,
    // 신규
    pub text_style: TextStyle,
}
```

### TypeScript (`packages/ui/src/types/pdf.ts` 추가)

```typescript
export interface TextStyle {
  bold: boolean
  italic: boolean
  underline: boolean
  strikethrough: boolean
  color: [number, number, number]   // RGB 0.0~1.0
  alignment: 'Left' | 'Center' | 'Right'
}
```

`WasmApi.replaceText`에 `textStyleJson: string` 파라미터 추가:
```typescript
replaceText(
  pageIndex: number,
  coverRect: [number, number, number, number],
  newText: string,
  textX: number,
  textY: number,
  fontConfigJson: string,
  textStyleJson: string,    // ← 신규
  fontBytes: Uint8Array | null
): Promise<PageInfo[]>
```

`document-store`:
```typescript
interface DocumentState {
  // 기존 유지
  textStyle: TextStyle        // 신규
  setTextStyle: (style: TextStyle) => void  // 신규
}
```

---

## content stream 생성 로직 (`build_content_stream` 수정)

### 1. 텍스트 너비 추정 (D16 — Rust에서 계산)

```rust
fn estimate_text_width(text: &str, font_size: f64) -> f64 {
    text.chars().map(|c| {
        if ('\u{AC00}'..='\u{D7A3}').contains(&c) || ('\u{4E00}'..='\u{9FFF}').contains(&c) {
            font_size * 0.95
        } else {
            font_size * 0.55
        }
    }).sum()
}
```

### 2. 정렬 적용 (text_x 조정)

```rust
let adjusted_text_x = match params.text_style.alignment {
    TextAlign::Left   => params.text_x,
    TextAlign::Center => params.text_x - estimate_text_width(&params.new_text, size) / 2.0,
    TextAlign::Right  => params.text_x - estimate_text_width(&params.new_text, size),
};
```

### 3. content stream 구조

```
// 색상 (모든 경우)
{r} {g} {b} rg

// Bold용 선 색상 + 두께 (bold=true)
{r} {g} {b} RG
0.5 w

// BT 블록
BT
// Italic: Tm 행렬 사용 (italic=true)
1 0.2 0 1 {x} {y} Tm    ← italic
// 또는 일반 위치:
{x} {y} Td               ← non-italic

// 폰트 + Bold text rendering mode
/KoFont {size} Tf
2 Tr    ← bold=true (fill+stroke)
0 Tr    ← bold=false (fill only)

// 텍스트 출력
<hex...> Tj    ← 한글
({text}) Tj   ← 영어
ET

// Underline 선 (underline=true)
q
{r} {g} {b} RG
1 w
{x} {y-1} m  {x+w} {y-1} l  S
Q

// Strikethrough 선 (strikethrough=true)
q
{r} {g} {b} RG
1 w
{x} {y + size*0.35} m  {x+w} {y + size*0.35} l  S
Q
```

---

## WASM API 변경 (`crates/rpdf-wasm/src/lib.rs`)

```rust
pub fn replace_text(
    &mut self,
    page_index: usize,
    cover_rect: Vec<f64>,
    new_text: String,
    text_x: f64,
    text_y: f64,
    font_config_json: String,
    text_style_json: String,    // ← 신규
    font_bytes: Option<Vec<u8>>,
) -> Result<JsValue, JsValue>
```

`TextReplaceParams` 생성 시 `text_style` 파싱:
```rust
let text_style: TextStyle = serde_json::from_str(&text_style_json)
    .map_err(|e| JsValue::from(JsError::new(&e.to_string())))?;
```

---

## UI 변경

### `TextEditPanel` 서식 툴바 확장 (`packages/ui/src/ui/text_edit_panel.tsx`)

기존 `FontToolbar` (한글폰트/영어폰트/폰트크기) 아래에 추가:

```
── StyleButtons ──────────────────────────────
[B] [I] [U] [S]    ← 토글 버튼 (active시 배경색)

── ColorPicker ──────────────────────────────
● ● ● ● ● ● ● ●   ← 8색 팔레트
[#000000        ]   ← hex 입력 (직접 편집)

── AlignButtons ─────────────────────────────
[← Left] [↔ Center] [→ Right]
```

### `document-store` 확장

```typescript
// 초기값
textStyle: {
  bold: false,
  italic: false,
  underline: false,
  strikethrough: false,
  color: [0, 0, 0],
  alignment: 'Left'
}
```

`replaceText` 액션: `textStyle`를 JSON 직렬화해서 WASM에 전달

### WASM Worker (`packages/studio/src/core/wasm-worker.ts`)

```typescript
async replaceText(
  pageIndex: number,
  coverRect: [number, number, number, number],
  newText: string,
  textX: number,
  textY: number,
  fontConfigJson: string,
  textStyleJson: string,    // ← 신규
  fontBytes: Uint8Array | null
): Promise<PageInfo[]>
```

---

## 에러 표

| 에러 | 발생 조건 | 기존/신규 |
|------|----------|-----------|
| `PageOutOfBounds` | `page_index >= page_count` | 기존 |
| `LoadFailed` | lopdf 파싱 실패 | 기존 |
| `FontEmbedFailed` | 한글 폰트 font_bytes 없음 또는 파싱 실패 | 기존 |
| `SaveFailed` | lopdf 저장 실패 | 기존 |
| (신규 없음) | TextStyle 역직렬화 실패는 WASM 레이어에서 JsError로 처리 | — |

---

## 테스트 전략

### Rust 단위 테스트 (`crates/rpdf-edit/src/commands/text_replace.rs`)

| ID | 내용 |
|----|------|
| TR-Style-1 | `bold=true` → content stream에 `2 Tr` 포함 |
| TR-Style-2 | `italic=true` → content stream에 `Tm` 포함 (Td 미포함) |
| TR-Style-3 | `color=[1,0,0]` → `1 0 0 rg` 포함 |
| TR-Style-4 | `underline=true` → content stream에 `m` `l` `S` 포함 |
| TR-Style-5 | `strikethrough=true` → content stream에 `m` `l` `S` 포함 |
| TR-Style-6 | `alignment=Center` → text_x 조정됨 (adjusted_x < original_x) |
| TR-Style-7 | `alignment=Right` → adjusted_x < Center case |
| TR-Style-8 | 기존 TR-1~TR-8 회귀 없음 (TextStyle default 적용) |
| TR-Style-9 | `bold=true && italic=true` 동시 → content stream에 `Tm` + `2 Tr` 모두 포함 |

### TypeScript (vitest, `packages/ui/src/__tests__/`)

| ID | 내용 |
|----|------|
| UI-Style-1 | `setTextStyle({ bold: true })` → `textStyle.bold === true` |
| UI-Style-2 | `textStyle` 초기값 — 모두 false, color 검정, Left |
| UI-Style-3 | `replaceText` 호출 시 `textStyleJson` 포함 확인 (mock 검증) |

---

## 구현 순서

1. Rust `text_replace.rs` — `TextStyle`, `TextAlign` 추가 + `TextReplaceParams` 확장 + `build_content_stream` 수정
2. Rust 단위 테스트 (TR-Style-1~TR-Style-8)
3. `cargo test -p rpdf-edit` 통과
4. WASM `replace_text` 시그니처 업데이트
5. TypeScript 타입 (`TextStyle`, `WasmApi`) 업데이트
6. `document-store` `textStyle` 상태 + `setTextStyle` 추가
7. `TextEditPanel` 서식 툴바 UI 추가
8. WASM Worker + bridge 업데이트 (`packages/studio` **및** `packages/desktop` 양쪽 모두)
9. 기존 테스트 회귀 없음 확인
10. TypeScript 신규 테스트 (UI-Style-1~3)

---

## 체크포인트 (CP-D)

- [ ] TR-Style-1~8 단위 테스트 통과
- [ ] `cargo test`, `cargo clippy -- -D warnings` 통과
- [ ] B/I/U/S 토글 + 색상 팔레트 + 정렬 버튼 UI 렌더링
- [ ] `pnpm test`, `pnpm typecheck` 통과
- [ ] 기존 Task #41 기능 회귀 없음

---

## 알려진 한계

| 한계 | 후속 |
|------|------|
| Bold는 stroke 두께로 구현 — 진짜 Bold 글리프와 차이 | Phase 3: Bold TTF 추가 |
| Italic은 oblique 행렬 — 실제 Italic 글리프와 차이 | Phase 3: Italic TTF 추가 |
| 정렬 너비 추정 부정확 | Phase 3: ttf-parser 정밀 측정 |
| 색상 팔레트 8색 고정 | Phase 2+: 전체 color wheel |

---

## 관련 파일

- 백엔드: `crates/rpdf-edit/src/commands/text_replace.rs`
- WASM 바인딩: `crates/rpdf-wasm/src/lib.rs`
- UI 타입: `packages/ui/src/types/pdf.ts`
- store: `packages/ui/src/state/document-store.ts`
- UI 컴포넌트: `packages/ui/src/ui/text_edit_panel.tsx`
- WASM Worker (studio): `packages/studio/src/core/wasm-worker.ts`
- WASM Worker (desktop): `packages/desktop/src/core/wasm-worker.ts`  ← **breaking change 시 반드시 함께 업데이트**
- Tauri 클라이언트: `packages/desktop/src/core/tauri-client.ts`
