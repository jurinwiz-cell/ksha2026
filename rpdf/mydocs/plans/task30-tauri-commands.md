# Task #30 계획서: Tauri Commands 정의

**이슈**: #60  
**브랜치**: `local/task30`  
**마일스톤**: v0.5  
**선행 조건**: Task #29 (@rpdf/ui + packages/desktop 스캐폴딩)  
**다음 Task**: Task #34 (프론트엔드 bridge 분기)

---

## 목표

`packages/desktop/src-tauri`에 Rust native PDF 조작 Tauri commands를 구현한다.  
이번 Task에서는 Rust 백엔드만 구현하며, 프론트엔드 연동(bridge 분기)은 Task #34에서 처리한다.

**완료 기준**: `pnpm --filter @rpdf/desktop build` (cargo check 포함) 통과 + 단위 테스트 통과

---

## 환경

| 도구 | 버전 |
|------|------|
| cargo-tauri | 2.11.2 |
| rustc | 1.95.0 |
| tauri | 2.x |
| rpdf-edit | workspace |
| rpdf-serializer | workspace |
| rpdf-parser | workspace |

---

## 설계 결정

### 단일 문서 모델 (D1)

이번 Task에서는 **한 번에 하나의 문서**만 지원한다.  
PdfHandle 기반 멀티 문서는 Task #35(파일 시스템 연동) 이후 필요성이 확인되면 추가.

`AppState`는 `Mutex<Option<PdfState>>`로 현재 열린 문서 하나를 보관한다.

```rust
// state.rs
pub struct PdfState {
    pub doc: Document,
    pub sources: Vec<PageSource>,
    pub stack: CommandStack,
    pub sources_undo: Vec<Vec<PageSource>>,
    pub sources_redo: Vec<Vec<PageSource>>,
}

pub struct AppState {
    pub pdf: Mutex<Option<PdfState>>,
}
```

### bytes 기반 open (D2)

`open_pdf`는 파일 경로가 아닌 **바이트 배열**을 받는다.  
- 프론트엔드 드래그앤드롭(File → ArrayBuffer)과 호환  
- Task #35(네이티브 파일 다이얼로그)에서 경로 기반 `open_pdf_path` 추가 예정  
- WASM bridge와 동일한 시그니처 → Task #34 bridge 전환이 쉬움

### PageInfo 반환 (D3)

commands는 성공 시 현재 문서 상태(`Vec<PageInfo>`)를 반환한다.  
별도 `get_pages` command 없이 각 변경 command가 최신 상태를 함께 반환.  
undo/redo도 `UndoResult { pages, undo_len, redo_len }` 구조체 반환.

```rust
#[derive(serde::Serialize, Clone)]
pub struct PageInfo {
    pub index: usize,
    pub rotation: i32,
    pub media_box: Option<[f64; 4]>,
    pub crop_box: Option<[f64; 4]>,
}

#[derive(serde::Serialize)]
pub struct OpenResult {
    pub pages: Vec<PageInfo>,
    pub undo_len: usize,
    pub redo_len: usize,
}
```

### undo/redo sources 스택 동기화 (D4)

rpdf-wasm의 핵심 불변성을 그대로 따른다:  
**sources_undo는 CommandStack과 항상 동일 높이를 유지한다.**

```rust
fn execute_cmd(
    state: &mut PdfState,
    cmd: Box<dyn Command>,
    new_sources: Option<Vec<PageSource>>,
) -> Result<(), String> {
    state.stack.execute(cmd, &mut state.doc)
        .map_err(|e| e.to_string())?;
    state.sources_undo.push(state.sources.clone()); // 항상 push
    state.sources_redo.clear();
    if let Some(s) = new_sources {
        state.sources = s;
    }
    Ok(())
}
```

### 에러 처리 (D5)

모든 Tauri command는 `Result<T, String>`을 반환한다.  
에러는 `anyhow::Error` 또는 직접 `.to_string()`으로 변환.  
`unwrap()`·`expect()` 사용 금지 — Mutex 잠금 실패는 `.map_err(|e| e.to_string())?`로 전파.

---

## 파일 구조

```
packages/desktop/src-tauri/src/
├── main.rs         (변경 없음)
├── lib.rs          (command 등록 추가)
├── state.rs        (신규 — AppState, PdfState, 헬퍼)
└── commands.rs     (신규 — Tauri command 함수들)
```

---

## 작업 범위

### 체크포인트 A: Cargo.toml 의존성 추가

`packages/desktop/src-tauri/Cargo.toml`에 추가:

```toml
[dependencies]
tauri = { version = "2", features = [] }
tauri-plugin-shell = "2"
serde = { version = "1", features = ["derive"] }
serde_json = "1"
rpdf-parser = { path = "../../../../crates/rpdf-parser" }
rpdf-edit = { path = "../../../../crates/rpdf-edit" }
rpdf-serializer = { path = "../../../../crates/rpdf-serializer" }
rpdf-core = { path = "../../../../crates/rpdf-core" }
```

**완료 기준**: `cargo check -p rpdf-desktop` 통과

### 체크포인트 B: state.rs 구현

```rust
// packages/desktop/src-tauri/src/state.rs
use std::sync::Mutex;
use rpdf_core::types::document::Document;
use rpdf_edit::commands::{Command, CommandStack};
use rpdf_serializer::PageSource;

pub struct PdfState {
    pub doc: Document,
    pub sources: Vec<PageSource>,
    pub stack: CommandStack,
    pub sources_undo: Vec<Vec<PageSource>>,
    pub sources_redo: Vec<Vec<PageSource>>,
}

impl PdfState {
    pub fn new(doc: Document, sources: Vec<PageSource>) -> Self {
        Self {
            doc,
            sources,
            stack: CommandStack::new(),
            sources_undo: Vec::new(),
            sources_redo: Vec::new(),
        }
    }

    pub fn execute_cmd(
        &mut self,
        cmd: Box<dyn Command>,
        new_sources: Option<Vec<PageSource>>,
    ) -> Result<(), String> {
        self.stack
            .execute(cmd, &mut self.doc)
            .map_err(|e| e.to_string())?;
        self.sources_undo.push(self.sources.clone()); // 항상 push
        self.sources_redo.clear();
        if let Some(s) = new_sources {
            self.sources = s;
        }
        Ok(())
    }
}

#[derive(Default)]
pub struct AppState {
    pub pdf: Mutex<Option<PdfState>>,
}
```

**완료 기준**: `cargo check` 통과

### 체크포인트 C: commands.rs 구현

```rust
// packages/desktop/src-tauri/src/commands.rs
use std::collections::HashSet;
use rpdf_edit::commands::{DeletePagesCommand, RotatePageCommand};
use rpdf_serializer::{PageSource, load_document_tracked, serialize_document};
use rpdf_core::types::document::Page;
use tauri::State;
use crate::state::{AppState, PdfState};

#[derive(serde::Serialize, Clone)]
pub struct PageInfo {
    pub index: usize,
    pub rotation: i32,
    pub media_box: Option<[f64; 4]>,
    pub crop_box: Option<[f64; 4]>,
}

#[derive(serde::Serialize)]
pub struct OpenResult {
    pub pages: Vec<PageInfo>,
    pub undo_len: usize,
    pub redo_len: usize,
}

fn page_to_info(page: &Page) -> PageInfo {
    PageInfo {
        index: page.index,
        rotation: page.rotation,
        media_box: page.media_box.map(|b| [b[0], b[1], b[2], b[3]]),
        crop_box: page.crop_box.map(|b| [b[0], b[1], b[2], b[3]]),
    }
}

fn doc_to_pages(state: &PdfState) -> Vec<PageInfo> {
    state.doc.pages.iter().map(page_to_info).collect()
}

fn validate_page_index(idx: usize, count: usize) -> Result<(), String> {
    if idx >= count {
        return Err(format!("페이지 인덱스 범위 초과: {idx}"));
    }
    Ok(())
}

fn validate_degrees(degrees: i32) -> Result<(), String> {
    match degrees {
        0 | 90 | 180 | 270 => Ok(()),
        _ => Err(format!("유효하지 않은 회전각: {degrees} (0/90/180/270만 허용)")),
    }
}

fn compute_new_sources(sources: &[PageSource], deleted: &[usize]) -> Vec<PageSource> {
    let set: HashSet<usize> = deleted.iter().copied().collect();
    sources
        .iter()
        .enumerate()
        .filter(|(i, _)| !set.contains(i))
        .map(|(_, s)| PageSource { bytes: s.bytes.clone(), page_index: s.page_index })
        .collect()
}

// --- Commands ---

#[tauri::command]
pub fn open_pdf(bytes: Vec<u8>, state: State<'_, AppState>) -> Result<OpenResult, String> {
    let (doc, sources) = load_document_tracked(&bytes).map_err(|e| e.to_string())?;
    let mut guard = state.pdf.lock().map_err(|e| e.to_string())?;
    let pdf = PdfState::new(doc, sources);
    let result = OpenResult {
        pages: doc_to_pages(&pdf),
        undo_len: 0,
        redo_len: 0,
    };
    *guard = Some(pdf);
    Ok(result)
}

#[tauri::command]
pub fn save_pdf(state: State<'_, AppState>) -> Result<Vec<u8>, String> {
    let guard = state.pdf.lock().map_err(|e| e.to_string())?;
    let pdf = guard.as_ref().ok_or("열린 문서 없음")?;
    serialize_document(&pdf.doc, &pdf.sources).map_err(|e| e.to_string())
}

#[tauri::command]
pub fn rotate_page(
    index: usize,
    degrees: i32,
    state: State<'_, AppState>,
) -> Result<OpenResult, String> {
    let mut guard = state.pdf.lock().map_err(|e| e.to_string())?;
    let pdf = guard.as_mut().ok_or("열린 문서 없음")?;
    validate_page_index(index, pdf.doc.page_count())?;
    validate_degrees(degrees)?;
    let cmd = Box::new(RotatePageCommand::new(index, degrees));
    pdf.execute_cmd(cmd, None)?;
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

#[tauri::command]
pub fn delete_pages(
    indices: Vec<usize>,
    state: State<'_, AppState>,
) -> Result<OpenResult, String> {
    let mut guard = state.pdf.lock().map_err(|e| e.to_string())?;
    let pdf = guard.as_mut().ok_or("열린 문서 없음")?;
    if indices.is_empty() {
        return Err("삭제할 페이지 목록이 비어있습니다".to_string());
    }
    for &i in &indices {
        validate_page_index(i, pdf.doc.page_count())?;
    }
    let unique_count = indices.iter().copied().collect::<std::collections::HashSet<usize>>().len();
    if pdf.doc.page_count() <= unique_count {
        return Err("최소 1페이지 필요".to_string());
    }
    let new_sources = compute_new_sources(&pdf.sources, &indices);
    let cmd = Box::new(DeletePagesCommand::new(indices));
    pdf.execute_cmd(cmd, Some(new_sources))?;
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

#[tauri::command]
pub fn undo(state: State<'_, AppState>) -> Result<OpenResult, String> {
    let mut guard = state.pdf.lock().map_err(|e| e.to_string())?;
    let pdf = guard.as_mut().ok_or("열린 문서 없음")?;
    if pdf.stack.undo_len() == 0 {
        return Err("되돌릴 커맨드 없음".to_string());
    }
    pdf.stack.undo(&mut pdf.doc).map_err(|e| e.to_string())?;
    let prev = pdf.sources_undo.pop().expect("sources_undo 높이 불일치");
    let current = std::mem::replace(&mut pdf.sources, prev);
    pdf.sources_redo.push(current);
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

#[tauri::command]
pub fn redo(state: State<'_, AppState>) -> Result<OpenResult, String> {
    let mut guard = state.pdf.lock().map_err(|e| e.to_string())?;
    let pdf = guard.as_mut().ok_or("열린 문서 없음")?;
    if pdf.stack.redo_len() == 0 {
        return Err("다시 실행할 커맨드 없음".to_string());
    }
    pdf.stack.redo(&mut pdf.doc).map_err(|e| e.to_string())?;
    let next = pdf.sources_redo.pop().expect("sources_redo 높이 불일치");
    let current = std::mem::replace(&mut pdf.sources, next);
    pdf.sources_undo.push(current);
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

#[tauri::command]
pub fn get_pages(state: State<'_, AppState>) -> Result<OpenResult, String> {
    let guard = state.pdf.lock().map_err(|e| e.to_string())?;
    let pdf = guard.as_ref().ok_or("열린 문서 없음")?;
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}
```

**완료 기준**: `cargo check -p rpdf-desktop` 통과

### 체크포인트 D: lib.rs — command 등록

```rust
// packages/desktop/src-tauri/src/lib.rs
mod commands;
mod state;

use state::AppState;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .manage(AppState::default())
        .invoke_handler(tauri::generate_handler![
            commands::open_pdf,
            commands::save_pdf,
            commands::rotate_page,
            commands::delete_pages,
            commands::undo,
            commands::redo,
            commands::get_pages,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
```

**완료 기준**: `cargo check -p rpdf-desktop` 통과

### 체크포인트 E: 단위 테스트

`packages/desktop/src-tauri/src/commands.rs` 하단에 `#[cfg(test)]` 블록:

```rust
#[cfg(test)]
mod tests {
    // PdfState를 직접 생성해 execute_cmd 로직을 검증 (Tauri State 없이)
    // 테스트 헬퍼: load_document_tracked + PdfState::new
    // 1. open_pdf 바이트 로드 → pages 반환 검증
    // 2. rotate_page → pages 반환 + undo_len 증가
    // 3. undo → pages 원복 + undo_len 감소
    // 4. delete_pages → 페이지 수 감소
    // 5. 최소 1페이지 유지 에러
    // 6. initStore 미호출 시 에러 (Tauri는 직접 state 없이 단위 테스트 불가 → 로직 헬퍼 분리)
}
```

테스트는 `cargo test -p rpdf-desktop`으로 실행.  
단, Tauri command 함수 자체는 `State<'_>`를 받아 단위 테스트가 어려우므로,  
핵심 로직(`execute_cmd`, `validate_*`, `compute_new_sources`)을 헬퍼로 추출해 직접 테스트.

---

## 에러 처리 표

| 상황 | 에러 메시지 | 발생 위치 |
|------|-----------|---------|
| 열린 문서 없음 | `"열린 문서 없음"` | open 전 모든 command |
| 페이지 인덱스 초과 | `"페이지 인덱스 범위 초과: {idx}"` | rotate_page, delete_pages |
| 유효하지 않은 회전각 | `"유효하지 않은 회전각: {d}"` | rotate_page |
| 삭제 목록 비어있음 | `"삭제할 페이지 목록이 비어있습니다"` | delete_pages |
| 최소 1페이지 유지 | `"최소 1페이지 필요"` | delete_pages |
| undo 없음 | `"되돌릴 커맨드 없음"` | undo |
| redo 없음 | `"다시 실행할 커맨드 없음"` | redo |
| Mutex 잠금 실패 | `e.to_string()` | 모든 command |

---

## 테스트 전략

- **단위 테스트**: `PdfState::execute_cmd`, `validate_*`, `compute_new_sources` — 순수 Rust, fixture PDF 사용
- **통합**: `cargo check -p rpdf-desktop` — Tauri 빌드 전체 컴파일 확인
- **수동**: Task #34 이후 프론트엔드 연동 시 실제 창에서 확인

---

## 공개 API 확인 완료

- `rpdf-edit::commands`: `RotatePageCommand::new(index, degrees)`, `DeletePagesCommand::new(indices)`, `CommandStack::execute/undo/redo` — 소스 직접 확인
- `rpdf-serializer`: `load_document_tracked(&[u8]) -> Result<(Document, Vec<PageSource>)>`, `serialize_document` — 소스 직접 확인
- `tauri::State<'_, T>`: Tauri 2 공식 상태 관리 API — docs.rs 확인

---

## 범위 외 (이후 Task로 이연)

- Task #34: 프론트엔드에서 `isTauri` 감지 후 Tauri commands로 bridge 전환
- Task #35: `open_pdf_path(path: String)` — 네이티브 파일 다이얼로그 연동
- 멀티 문서 (PdfHandle) — 실사용 피드백 후 결정

---

## GSTACK REVIEW REPORT

**검토 일시**: 2026-05-20  
**브랜치**: local/task30  
**검토자**: plan-eng-review (Claude Sonnet 4.6) + Outside Voice (Gemini CLI)

### 결정 요약

| ID | 분야 | 결정 |
|----|------|------|
| D1 | 아키텍처 — DRY | PdfState 중복 허용. Task #34에서 통합 검토. |
| D2 | 코드 품질 — open 시 덮어쓰기 | 조용한 덮어쓰기 허용 (MVP). |
| D3 | 테스트 커버리지 | 핵심 경로 5개 + sources_undo 불변성 테스트 필수. |

### Outside Voice 발견 사항

- **수정 완료**: `delete_pages` 중복 인덱스 버그 — `indices.len()` 대신 `HashSet` 기반 unique count 사용으로 수정
- **MVP 허용**: `std::sync::Mutex` 사용 — async 차단 위험 있으나 단일 사용자 데스크톱 MVP에서 허용
- **MVP 허용**: `save_pdf` → `Vec<u8>` IPC — Task #35 native 파일 다이얼로그로 대체 예정

### 검토 판정

**APPROVED** — 아래 구현 조건 충족 시 진행 가능.

1. `delete_pages` 검증: `HashSet` unique count 기준 (계획서 수정 완료 ✅)
2. `undo` expect 패닉 허용 — 불변성 위반은 프로그래밍 오류, panic이 올바른 반응
3. 테스트 5개 경로 + 불변성 검증 필수 (D3)
4. 체크포인트 E 완료 전 `cargo test -p rpdf-desktop` 통과 확인

### 구현 태스크

| ID | 설명 | 우선순위 |
|----|------|---------|
| T1 | Cargo.toml 의존성 추가 + cargo check | CP-A |
| T2 | state.rs 구현 (PdfState, AppState) | CP-B |
| T3 | commands.rs 구현 (7개 command 함수) | CP-C |
| T4 | lib.rs command 등록 | CP-D |
| T5 | 단위 테스트 5개 + 불변성 1개 | CP-E |
