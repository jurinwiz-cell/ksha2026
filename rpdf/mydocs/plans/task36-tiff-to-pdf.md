# Task #36: TIFF → PDF 변환

**Issue**: #66
**브랜치**: `local/task36`
**마일스톤**: v0.5 (Tauri 데스크톱 앱)
**선행**: Task #35 완료 (네이티브 파일 다이얼로그 패턴 확립)

---

## 목표

여러 TIFF 파일을 선택해 하나의 PDF 문서로 변환하는 기능을 Tauri 데스크톱 앱에 추가한다.
각 TIFF 이미지 → PDF 한 페이지. 변환된 PDF는 즉시 앱 상태에 로드되어 편집할 수 있다.

---

## 범위

| 대상 | 작업 |
|------|------|
| `packages/desktop/src-tauri/Cargo.toml` | `image`, `lopdf` 의존성 추가 |
| `packages/desktop/src-tauri/src/commands.rs` | `tiff_to_pdf` Tauri command 추가 |
| `packages/desktop/src/core/tauri-dialog.ts` | `openTiffWithNativeDialog` 함수 추가 |
| `packages/desktop/src/App.tsx` | 'TIFF → PDF' 버튼 추가 |

**범위 외**: WASM 환경, wasm-client.ts, WasmApi 인터페이스 변경 없음 (Tauri 전용 기능)

---

## 구현 설계

### Rust 백엔드

#### 의존성 (실제 설치된 버전)

```toml
# packages/desktop/src-tauri/Cargo.toml
image = { version = "0.25", features = ["tiff", "jpeg"] }
lopdf = { version = "0.40", features = ["embed_image"] }
```

> `lopdf` workspace 선언(`lopdf = "0.40.0"`)에는 `embed_image` feature가 없으므로,
> `rpdf-desktop`의 Cargo.toml에서 features를 명시해 override한다.

#### `tiff_to_pdf` command 시그니처

```rust
#[tauri::command]
pub fn tiff_to_pdf(paths: Vec<String>) -> Result<Vec<u8>, String>
```

- **입력**: TIFF 파일 절대 경로 목록 (TypeScript에서 네이티브 다이얼로그로 수집)
- **출력**: PDF 바이트 (`Vec<u8>`) — frontend에서 `open_pdf` command로 앱 상태에 로드
- **State 없음**: 변환만 하는 순수 함수, `AppState` 불필요

#### 변환 로직 pseudo-code

```
fn tiff_to_pdf(paths: Vec<String>) -> Result<Vec<u8>, String>:
    if paths.is_empty():
        return Err("TIFF 경로가 없습니다")

    let mut doc = Document::with_version("1.5")
    let pages_id = doc.new_object_id()
    let mut page_ids: Vec<ObjectId> = vec![]

    for path in paths:
        let img = ImageReader::open(path)?.decode()?
        let width = img.width()
        let height = img.height()

        // RGB로 정규화 (TIFF는 RGBA, Grayscale 등 다양한 색상 공간 가능)
        let rgb = img.into_rgb8()

        // JPEG 인코딩 (품질 85 — 스캔 문서 화질 최소화)
        let mut jpeg = Vec::new()
        let mut encoder = JpegEncoder::new_with_quality(&mut Cursor::new(&mut jpeg), 85)
        encoder.encode_image(&rgb)?

        // A4(595×842pt) 안에 종횡비 유지하며 맞춤
        const A4_W: f64 = 595.0;
        const A4_H: f64 = 842.0;
        let scale = (A4_W / width as f64).min(A4_H / height as f64);
        let fit_w = width as f64 * scale;
        let fit_h = height as f64 * scale;
        let offset_x = (A4_W - fit_w) / 2.0;
        let offset_y = (A4_H - fit_h) / 2.0;

        // A4 페이지 생성 (항상 595×842pt)
        let page_id = doc.add_object(dictionary! {
            "Type" => "Page",
            "Parent" => pages_id,
            "MediaBox" => [0, 0, 595, 842],
            "Resources" => dictionary! {},
        })

        // 이미지 XObject 삽입 (종횡비 유지 fit 좌표 사용)
        let img_stream = xobject::image_from(jpeg)?
        doc.insert_image(page_id, img_stream, (offset_x, offset_y), (fit_w, fit_h))?

        page_ids.push(page_id)

    // Pages 루트 등록
    doc.objects.insert(pages_id, Object::Dictionary(dictionary! {
        "Type" => "Pages",
        "Kids" => page_ids.iter().map(|id| id.into()).collect::<Vec<_>>(),
        "Count" => page_ids.len() as i64,
    }))
    let catalog_id = doc.add_object(dictionary! {
        "Type" => "Catalog",
        "Pages" => pages_id,
    })
    doc.trailer.set("Root", catalog_id)

    // 바이트로 직렬화
    let mut out = Vec::new()
    doc.save_to(&mut out)?
    Ok(out)
```

#### 에러 처리 표

| 상황 | 반환 |
|------|------|
| `paths` 빈 목록 | `Err("TIFF 경로가 없습니다")` |
| 파일 열기 실패 (경로 없음/권한) | `Err(io_error.to_string())` |
| 이미지 디코딩 실패 (지원 안 되는 형식) | `Err(image_error.to_string())` |
| JPEG 인코딩 실패 | `Err(image_error.to_string())` |
| lopdf XObject 생성 실패 | `Err(lopdf_error.to_string())` |
| PDF 직렬화 실패 | `Err(io_error.to_string())` |

### TypeScript 프론트엔드

#### `tauri-dialog.ts` 추가 함수

```typescript
export async function openTiffWithNativeDialog(): Promise<void> {
    const paths = await open({
        multiple: true,
        filters: [{ name: 'TIFF', extensions: ['tif', 'tiff'] }],
    })
    if (!paths || paths.length === 0) return

    const bytes: number[] = await invoke('tiff_to_pdf', { paths })
    const uint8 = new Uint8Array(bytes)
    const file = new File([uint8], 'converted.pdf', { type: 'application/pdf' })
    await useDocumentStore.getState().openFile(file)
}
```

> `tiff_to_pdf`는 `Vec<u8>` → Tauri IPC가 `number[]`로 직렬화.
> `open_pdf`와 달리 invoke 반환값을 직접 받아 `openFile`로 재활용.

#### `App.tsx` 변경

빈 화면(`pages.length === 0`)에 기존 'PDF 열기' 버튼 옆에 'TIFF → PDF' 버튼 추가:

```tsx
{isTauri() && (
    <>
        <button onClick={openPdfWithNativeDialog} style={btnStyle}>
            PDF 열기
        </button>
        <button onClick={openTiffWithNativeDialog} style={btnStyle}>
            TIFF → PDF
        </button>
    </>
)}
```

---

## 데이터 흐름

```
[User] TIFF 파일 선택 (다이얼로그, multiple=true)
    ↓  paths: string[]
[TypeScript] invoke('tiff_to_pdf', { paths })
    ↓  Vec<String>
[Rust] per-TIFF loop:
       image::ImageReader::open(path)?.decode()?
    ↓  into_rgb8()
       A4 fit 계산: scale = min(595/w, 842/h)
       JPEG quality=85 인코딩 → xobject::image_from()
       MediaBox=[0,0,595,842], insert_image(offset_x,y, fit_w,h)
[Rust] lopdf::Document → save_to(&mut Vec<u8>)
    ↓  Vec<u8> → IPC → number[]
[TypeScript] new Uint8Array(bytes) → File('converted.pdf')
    ↓  useDocumentStore.openFile(file)
       invoke('open_pdf', { bytes })
[AppState] PdfState 업데이트 → pages 반환
[UI] ThumbnailSidebar + PageViewer 렌더링
```

---

## 엣지 케이스

| 케이스 | 처리 |
|--------|------|
| 빈 경로 목록 | Rust에서 조기 Err 반환 |
| TIFF 외 파일 혼입 | `image` 크레이트 디코딩 실패 → Err |
| 매우 큰 이미지 (4000px+) | JPEG 인코딩 품질 85 고정, 메모리 제한 없음 |
| 흑백(Grayscale) TIFF | `into_rgb8()` 자동 변환 |
| 16비트 TIFF | `image` 크레이트가 8비트로 다운샘플링 |
| RGBA TIFF (투명 채널) | `into_rgb8()` 에서 알파 채널 제거 (흰 배경 없음) |
| 다이얼로그 취소 | `paths` null/empty → 조기 return |
| 단일 파일 선택 | 정상 동작 (1페이지 PDF) |

### RGBA → 흰 배경 처리

`into_rgb8()`은 알파 채널을 단순 제거한다. 투명 배경이 검게 나올 수 있다.
→ 이 Task 범위에서는 흰 배경 합성을 생략한다. 실사용에서 문제가 되면 별도 Issue.

---

## 테스트 전략

### 단위 테스트 (Rust, `commands.rs` 내부 `#[cfg(test)]`)

테스트용 TIFF fixture를 `samples/` 에 추가한다.

1. **빈 경로 → 에러**: `tiff_to_pdf_with_empty_paths_returns_error`
2. **단일 TIFF → PDF 바이트**: `tiff_to_pdf_single_file_returns_valid_pdf`
   - 반환 바이트가 `%PDF-` 시그니처로 시작하는지 확인
   - `load_document_tracked`로 로드 성공하는지 확인
3. **멀티 TIFF → 페이지 수 일치**: `tiff_to_pdf_multiple_files_page_count_matches`
   - 2개 TIFF → 2페이지 PDF 검증
4. **파일 없음 → 에러**: `tiff_to_pdf_invalid_path_returns_error`
   - 존재하지 않는 경로 전달 → `Err` 반환 확인
5. **지원 불가 형식 → 에러**: `tiff_to_pdf_invalid_format_returns_error`
   - PNG 바이트를 TIFF 확장자 임시 파일로 저장 후 전달 → `Err` 반환 확인
   - (image 크레이트는 파일 내용을 확인해 형식 오류를 반환함)

### TIFF fixture 생성 (프로그래매틱, 외부 의존 없음)

테스트 코드 내에서 `image` 크레이트로 직접 생성한다. `samples/` 폴더에 TIFF를
바이너리 커밋하지 않는다.

```rust
fn make_tiff_bytes(width: u32, height: u32, r: u8, g: u8, b: u8) -> Vec<u8> {
    use image::{RgbImage, Rgb};
    let img = RgbImage::from_pixel(width, height, Rgb([r, g, b]));
    let mut buf = Vec::new();
    img.write_to(&mut std::io::Cursor::new(&mut buf), image::ImageFormat::Tiff)
        .expect("TIFF 인코딩 실패");
    buf
}
```

`tiff_to_pdf`는 경로를 받으므로 `tempfile`을 사용해 임시 파일로 저장 후 경로를 전달:

```rust
fn write_temp_tiff(bytes: &[u8]) -> tempfile::NamedTempFile {
    let mut f = tempfile::NamedTempFile::new().unwrap();
    f.write_all(bytes).unwrap();
    f
}
```

> `tempfile`은 이미 workspace dev-dependency에 있음 → 추가 불필요.

### TypeScript (수동 검증)

`pnpm tauri dev`로 앱 실행 후:
- TIFF → PDF 버튼 클릭 → 다이얼로그 열림
- TIFF 파일 1개 선택 → 1페이지 PDF 로드됨
- TIFF 파일 3개 선택 → 3페이지 PDF 로드됨
- 로드 후 rotate/delete/undo 정상 동작 확인

---

## 파일 화이트리스트

수정/추가 대상 파일만 나열:

```
packages/desktop/src-tauri/Cargo.toml        # image, lopdf 의존성 추가
packages/desktop/src-tauri/src/commands.rs   # tiff_to_pdf command + 5개 단위 테스트
packages/desktop/src-tauri/src/lib.rs        # tiff_to_pdf invoke_handler 등록
packages/desktop/src/core/tauri-dialog.ts    # openTiffWithNativeDialog 추가
packages/desktop/src/App.tsx                 # TIFF → PDF 버튼 추가
```

> TIFF fixture는 바이너리 커밋 없음. 테스트 코드 내에서 `image` + `tempfile`로 프로그래매틱 생성.

---

## 리스크

| 리스크 | 가능성 | 대응 |
|--------|--------|------|
| `lopdf embed_image` API가 workspace feature와 충돌 | 중 | rpdf-desktop Cargo.toml에 독립 선언 |
| `image` 크레이트 TIFF feature 빌드 오류 | 낮음 | features = ["tiff", "jpeg"] 명시 |
| JPEG 품질 손실이 과다 | 중 | 기본 품질(75)로 진행, 필요 시 90으로 조정 |
| lopdf `insert_image` 좌표 계산 오류 | 중 | 테스트로 PDF 로드 + 페이지 수 검증 |
| TIFF DPI 정보 미반영 → 페이지 크기 | 해결됨 | A4 핏으로 픽셀 크기 무관하게 A4 출력 |
| JPEG 스캔 문서 품질 열화 | 낮음 | 품질 85로 최소화. 실사용 피드백 후 별도 Issue |
| `Vec<u8>` IPC JSON 직렬화 크기 | 중 | 기존 save_pdf 동일 패턴 사용 중. 수십 페이지 대용량 시 별도 최적화 Issue |

---

## 체크포인트

| CP | 내용 | 완료 조건 |
|----|------|-----------|
| A | Rust: `tiff_to_pdf` command 구현 + 단위 테스트 | `cargo test` 통과, PDF 시그니처 검증 |
| B | TypeScript: 다이얼로그 + 버튼 연동 | 앱 실행 후 TIFF 선택 → 페이지 로드 확인 |
| C | 품질 관문 | `cargo clippy`, `pnpm typecheck`, `pnpm lint` 통과 |

---

## NOT in scope

| 항목 | 이유 |
|------|------|
| 1-bit 흑백 TIFF CCITT 인코딩 | 구현 복잡도 ↑. 실사용 피드백 후 별도 Issue |
| TIFF DPI 메타데이터 읽기 | A4 핏으로 해결. 추가 의존성 불필요 |
| 변환된 PDF 즉시 저장(파일로) | 기존 save 흐름 재사용으로 충분 |
| Tauri IPC 이진 최적화 | 기존 save_pdf와 동일 패턴. 대용량 시 별도 최적화 Issue |
| WASM/웹 환경 TIFF 지원 | Tauri 전용 기능으로 범위 한정 |

## What already exists

| 기존 코드 | 재사용 여부 |
|-----------|------------|
| `openPdfWithNativeDialog` (tauri-dialog.ts) | 동일 패턴으로 `openTiffWithNativeDialog` 구현 |
| `dialog:allow-open` 권한 (capabilities) | 그대로 재사용 |
| `open_pdf` command (commands.rs) | TypeScript에서 변환 후 재호출 |
| `AppState`, `PdfState` | 변환된 PDF 로드 후 그대로 사용 |
| `useDocumentStore.openFile` | TIFF→PDF 변환 후 동일 흐름으로 로드 |
| `tempfile` dev-dependency | fixture 생성에 재사용 |

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | Scope & strategy | 0 | — | — |
| Codex Review | `/codex review` | Independent 2nd opinion | 0 | — | — |
| Eng Review | `/plan-eng-review` | Architecture & tests (required) | 1 | CLEAN | 3 issues resolved, 0 critical gaps |
| Design Review | `/plan-design-review` | UI/UX gaps | 0 | — | — |
| DX Review | `/plan-devex-review` | Developer experience gaps | 0 | — | — |

- **OUTSIDE VOICE (Gemini):** 4개 지적 중 D1(페이지 크기) 계획서 반영, D2(JPEG 품질 85 설정) 반영, 나머지 2개(메모리 루프 오해, 아키텍처 위반) 기각
- **UNRESOLVED:** 0
- **VERDICT:** ENG CLEARED — 구현 시작 가능
