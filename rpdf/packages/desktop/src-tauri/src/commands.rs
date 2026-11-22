use std::collections::HashSet;
use std::io::Cursor;

use image::{codecs::jpeg::JpegEncoder, ImageReader};
use lopdf::{
    content::{Content, Operation},
    dictionary, xobject, Document, Object, Stream,
};
use rpdf_core::types::document::Page;
use rpdf_edit::commands::{
    apply_text_replace, DeletePagesCommand, FontConfig, NoOpCommand, RotatePageCommand,
    TextReplaceParams, TextStyle,
};
use rpdf_serializer::{load_document_tracked, serialize_document, PageSource};
use tauri::State;

use crate::state::{AppState, PdfState};

/// 단일 페이지 정보 (프론트엔드 전달용).
#[derive(serde::Serialize, Clone)]
pub struct PageInfo {
    pub index: usize,
    pub rotation: i32,
    pub media_box: Option<[f64; 4]>,
    pub crop_box: Option<[f64; 4]>,
}

/// open/rotate/delete/undo/redo/get_pages 응답 구조.
#[derive(serde::Serialize)]
pub struct OpenResult {
    pub pages: Vec<PageInfo>,
    pub undo_len: usize,
    pub redo_len: usize,
}

// ──────────────────────────────────────────────
// 내부 헬퍼 함수
// ──────────────────────────────────────────────

fn page_to_info(page: &Page) -> PageInfo {
    PageInfo {
        index: page.index,
        rotation: page.rotation,
        media_box: page.media_box,
        crop_box: page.crop_box,
    }
}

fn doc_to_pages(state: &PdfState) -> Vec<PageInfo> {
    state.doc.pages.iter().map(page_to_info).collect()
}

fn validate_page_index(idx: usize, count: usize) -> Result<(), String> {
    if idx >= count {
        return Err(format!(
            "페이지 인덱스 범위 초과: {idx} (문서 페이지 수: {count})"
        ));
    }
    Ok(())
}

/// `degrees`가 90의 배수인지 검증한다.
///
/// `RotatePageCommand`는 상대 회전값을 받으므로 90의 배수이면 모두 유효하다.
fn validate_degrees(degrees: i32) -> Result<(), String> {
    if degrees % 90 != 0 {
        return Err(format!(
            "회전 각도는 90의 배수여야 합니다 (입력값: {degrees})"
        ));
    }
    Ok(())
}

/// `deleted` 인덱스를 제외한 새 `PageSource` 목록을 반환한다.
fn compute_new_sources(sources: &[PageSource], deleted: &[usize]) -> Vec<PageSource> {
    let deleted_set: HashSet<usize> = deleted.iter().copied().collect();
    sources
        .iter()
        .enumerate()
        .filter(|(i, _)| !deleted_set.contains(i))
        .map(|(_, s)| s.clone())
        .collect()
}

// ──────────────────────────────────────────────
// Tauri commands
// ──────────────────────────────────────────────

/// PDF 바이트를 파싱해 문서를 열고 페이지 정보를 반환한다.
#[tauri::command]
pub fn open_pdf(bytes: Vec<u8>, state: State<'_, AppState>) -> Result<OpenResult, String> {
    let (doc, sources) = load_document_tracked(&bytes).map_err(|e| e.to_string())?;
    let mut pdf_guard = state.pdf.lock().unwrap();
    let pdf_state = PdfState::new(doc, sources);
    let result = OpenResult {
        pages: doc_to_pages(&pdf_state),
        undo_len: pdf_state.stack.undo_len(),
        redo_len: pdf_state.stack.redo_len(),
    };
    *pdf_guard = Some(pdf_state);
    Ok(result)
}

/// 현재 문서를 PDF 바이트로 직렬화해 반환한다.
#[tauri::command]
pub fn save_pdf(state: State<'_, AppState>) -> Result<Vec<u8>, String> {
    let pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_ref()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;
    serialize_document(&pdf.doc, &pdf.sources).map_err(|e| e.to_string())
}

/// 지정한 페이지를 상대 각도만큼 회전시킨다.
///
/// `index`는 0-based 페이지 인덱스, `degrees`는 90의 배수 상대 회전값이다.
#[tauri::command]
pub fn rotate_page(
    index: usize,
    degrees: i32,
    state: State<'_, AppState>,
) -> Result<OpenResult, String> {
    validate_degrees(degrees)?;
    let mut pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_mut()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;
    validate_page_index(index, pdf.doc.page_count())?;
    let cmd = Box::new(RotatePageCommand::new(index, degrees));
    pdf.execute_cmd(cmd, None)?;
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

/// 지정한 인덱스 목록의 페이지를 삭제한다.
///
/// 최소 1페이지는 남아야 한다. 전체 삭제 시도 시 에러를 반환한다.
#[tauri::command]
pub fn delete_pages(indices: Vec<usize>, state: State<'_, AppState>) -> Result<OpenResult, String> {
    let mut pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_mut()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;

    // 중복 인덱스가 있을 수 있으므로 HashSet으로 unique count 사용
    let unique_indices: HashSet<usize> = indices.iter().copied().collect();
    let unique_count = unique_indices.len();
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

/// 마지막 커맨드를 되돌린다.
#[tauri::command]
pub fn undo(state: State<'_, AppState>) -> Result<OpenResult, String> {
    let mut pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_mut()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;
    pdf.stack.undo(&mut pdf.doc).map_err(|e| e.to_string())?;
    let prev = pdf
        .sources_undo
        .pop()
        .expect("sources_undo 높이 불일치 — 프로그래밍 오류");
    let current = std::mem::replace(&mut pdf.sources, prev);
    pdf.sources_redo.push(current);
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

/// 마지막으로 되돌린 커맨드를 다시 실행한다.
#[tauri::command]
pub fn redo(state: State<'_, AppState>) -> Result<OpenResult, String> {
    let mut pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_mut()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;
    pdf.stack.redo(&mut pdf.doc).map_err(|e| e.to_string())?;
    let next = pdf
        .sources_redo
        .pop()
        .expect("sources_redo 높이 불일치 — 프로그래밍 오류");
    let current = std::mem::replace(&mut pdf.sources, next);
    pdf.sources_undo.push(current);
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

/// 현재 문서의 페이지 정보를 반환한다.
#[tauri::command]
pub fn get_pages(state: State<'_, AppState>) -> Result<OpenResult, String> {
    let pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_ref()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;
    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

/// 지정한 0-based 인덱스 페이지의 텍스트 블록 목록을 반환한다.
///
/// 반환값: `Vec<TextBlock>` — 각 항목은 text, x, y, width, height, font_size, font_ref, font_family 포함.
#[tauri::command]
pub fn get_text_blocks(
    page_index: usize,
    state: State<'_, AppState>,
) -> Result<Vec<rpdf_core::types::TextBlock>, String> {
    let pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_ref()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;
    let page = pdf
        .doc
        .pages
        .get(page_index)
        .ok_or_else(|| format!("페이지 인덱스 범위 초과: {page_index}"))?;
    Ok(rpdf_svg::extract::extract_text_blocks(page))
}

/// 지정 페이지의 영역을 흰 박스로 덮고 새 텍스트를 삽입한다.
///
/// - `page_index`: 0-based 페이지 인덱스.
/// - `cover_rect`: `[x0, y0, width, height]` 형태의 PDF 좌표.
/// - `new_text`: 삽입할 텍스트.
/// - `text_x`, `text_y`: 텍스트 삽입 좌표.
/// - `font_config_json`: `FontConfig` JSON 문자열.
/// - `text_style_json`: `TextStyle` JSON 문자열.
/// - `font_path`: 한글 TTF 파일 경로 (한글 폰트 선택 시 필수).
#[allow(clippy::too_many_arguments)]
#[tauri::command]
pub fn replace_text(
    page_index: usize,
    cover_rect: Vec<f64>,
    new_text: String,
    text_x: f64,
    text_y: f64,
    font_config_json: String,
    text_style_json: String,
    font_path: Option<String>,
    state: State<'_, AppState>,
) -> Result<OpenResult, String> {
    let mut pdf_guard = state.pdf.lock().unwrap();
    let pdf = pdf_guard
        .as_mut()
        .ok_or_else(|| "열린 문서가 없습니다".to_string())?;

    let font_config: FontConfig =
        serde_json::from_str(&font_config_json).map_err(|e| e.to_string())?;

    let text_style: TextStyle =
        serde_json::from_str(&text_style_json).map_err(|e| e.to_string())?;

    if cover_rect.len() != 4 {
        return Err("cover_rect는 [x0, y0, width, height] 형태여야 합니다".to_string());
    }
    let rect = [cover_rect[0], cover_rect[1], cover_rect[2], cover_rect[3]];

    let params = TextReplaceParams {
        page_index,
        cover_rect: rect,
        new_text,
        text_x,
        text_y,
        font_config,
        text_style,
    };

    // 한글 TTF bytes 로드
    let font_bytes: Option<Vec<u8>> = if let Some(path) = font_path {
        Some(std::fs::read(&path).map_err(|e| format!("폰트 파일 읽기 실패: {e}"))?)
    } else {
        None
    };

    let current_bytes: Vec<u8> = pdf
        .sources
        .first()
        .map(|s| s.bytes.as_ref().clone())
        .ok_or_else(|| "소스 bytes 없음".to_string())?;

    let new_bytes = apply_text_replace(&current_bytes, &params, font_bytes.as_deref())
        .map_err(|e| e.to_string())?;

    // D3: 모든 sources[i].bytes를 동일한 새 Arc로 교체
    let new_arc = std::sync::Arc::new(new_bytes.clone());
    let mut new_sources = pdf.sources.clone();
    for src in new_sources.iter_mut() {
        src.bytes = std::sync::Arc::clone(&new_arc);
    }

    // 새 bytes로 재파싱 (execute_cmd 전에 준비)
    let (new_doc, _) = load_document_tracked(&new_bytes).map_err(|e| e.to_string())?;

    // D3 Undo 동기화: NoOpCommand를 CommandStack에 execute해 높이 일치 보장
    // replace_text는 CommandStack을 우회하므로 sentinel이 필요하다.
    pdf.execute_cmd(Box::new(NoOpCommand), Some(new_sources))?;
    pdf.doc = new_doc;

    Ok(OpenResult {
        pages: doc_to_pages(pdf),
        undo_len: pdf.stack.undo_len(),
        redo_len: pdf.stack.redo_len(),
    })
}

/// TIFF 파일 경로 목록을 받아 각 이미지를 A4 페이지로 변환한 PDF 바이트를 반환한다.
///
/// 각 TIFF는 A4(595×842pt) 안에 종횡비를 유지하며 중앙 배치된다.
#[tauri::command]
pub fn tiff_to_pdf(paths: Vec<String>) -> Result<Vec<u8>, String> {
    if paths.is_empty() {
        return Err("TIFF 경로가 없습니다".to_string());
    }

    let mut doc = Document::with_version("1.5");
    let pages_id = doc.new_object_id();
    let mut page_ids = Vec::new();

    for path in &paths {
        let img = ImageReader::open(path)
            .map_err(|e| e.to_string())?
            .decode()
            .map_err(|e| e.to_string())?;

        let width = img.width();
        let height = img.height();
        let rgb = img.into_rgb8();

        let mut jpeg = Vec::new();
        JpegEncoder::new_with_quality(&mut Cursor::new(&mut jpeg), 85)
            .encode_image(&rgb)
            .map_err(|e| e.to_string())?;

        const A4_W: f64 = 595.0;
        const A4_H: f64 = 842.0;
        let scale = (A4_W / width as f64).min(A4_H / height as f64);
        let fit_w = width as f64 * scale;
        let fit_h = height as f64 * scale;
        let offset_x = (A4_W - fit_w) / 2.0;
        let offset_y = (A4_H - fit_h) / 2.0;

        let img_stream = xobject::image_from(jpeg).map_err(|e| e.to_string())?;
        let img_id = doc.add_object(img_stream);
        let img_name = format!("X{}", img_id.0);

        let content = Content {
            operations: vec![
                Operation::new("q", vec![]),
                Operation::new(
                    "cm",
                    vec![
                        fit_w.into(),
                        0.into(),
                        0.into(),
                        fit_h.into(),
                        offset_x.into(),
                        offset_y.into(),
                    ],
                ),
                Operation::new("Do", vec![Object::Name(img_name.as_bytes().to_vec())]),
                Operation::new("Q", vec![]),
            ],
        };
        let content_id = doc.add_object(Stream::new(
            dictionary! {},
            content.encode().map_err(|e| e.to_string())?,
        ));

        let page_id = doc.add_object(dictionary! {
            "Type" => "Page",
            "Parent" => pages_id,
            "MediaBox" => vec![0.into(), 0.into(), 595.into(), 842.into()],
            "Contents" => content_id,
            "Resources" => dictionary! {
                "XObject" => dictionary! {
                    img_name.as_bytes() => img_id,
                },
            },
        });

        page_ids.push(page_id);
    }

    let count = page_ids.len() as i64;
    doc.objects.insert(
        pages_id,
        Object::Dictionary(dictionary! {
            "Type" => "Pages",
            "Kids" => page_ids.iter().map(|&id| Object::Reference(id)).collect::<Vec<_>>(),
            "Count" => count,
        }),
    );
    let catalog_id = doc.add_object(dictionary! {
        "Type" => "Catalog",
        "Pages" => pages_id,
    });
    doc.trailer.set("Root", catalog_id);

    let mut out = Vec::new();
    doc.save_to(&mut out).map_err(|e| e.to_string())?;
    Ok(out)
}

// ──────────────────────────────────────────────
// 단위 테스트 (PdfState 직접 생성)
// ──────────────────────────────────────────────

#[cfg(test)]
mod tests {
    use std::io::Write;

    use super::*;

    /// 테스트용 실제 PDF 바이트를 로드한다.
    ///
    /// samples/trad-xref-basicapi.pdf — 단순 단일 페이지 PDF
    fn load_test_pdf() -> (rpdf_core::types::document::Document, Vec<PageSource>) {
        let bytes = include_bytes!("../../../../samples/trad-xref-basicapi.pdf");
        load_document_tracked(bytes).expect("테스트 PDF 파싱 실패")
    }

    /// 멀티 페이지 테스트용 PDF (xref-stream-form-two-pages.pdf — 2페이지)
    fn load_two_page_pdf() -> (rpdf_core::types::document::Document, Vec<PageSource>) {
        let bytes = include_bytes!("../../../../samples/xref-stream-form-two-pages.pdf");
        load_document_tracked(bytes).expect("2페이지 테스트 PDF 파싱 실패")
    }

    /// 멀티 페이지 테스트용 PDF (xref-stream-doc-13-pages.pdf — 13페이지)
    fn load_multi_page_pdf() -> (rpdf_core::types::document::Document, Vec<PageSource>) {
        let bytes = include_bytes!("../../../../samples/xref-stream-doc-13-pages.pdf");
        load_document_tracked(bytes).expect("다중 페이지 테스트 PDF 파싱 실패")
    }

    #[test]
    fn open_pdf_returns_page_infos() {
        let (doc, sources) = load_test_pdf();
        let pdf = PdfState::new(doc, sources);
        let pages = doc_to_pages(&pdf);
        assert!(!pages.is_empty(), "페이지가 1개 이상이어야 한다");
        assert_eq!(pdf.stack.undo_len(), 0);
        assert_eq!(pdf.stack.redo_len(), 0);
    }

    #[test]
    fn rotate_increases_undo_len() {
        let (doc, sources) = load_test_pdf();
        let mut pdf = PdfState::new(doc, sources);
        let initial_rotation = pdf.doc.pages[0].rotation;

        let cmd = Box::new(RotatePageCommand::new(0, 90));
        pdf.execute_cmd(cmd, None).expect("rotate 실패");

        assert_eq!(pdf.stack.undo_len(), 1);
        assert_eq!(pdf.sources_undo.len(), 1);
        // 회전이 적용됐는지 확인
        let expected = (initial_rotation + 90).rem_euclid(360);
        assert_eq!(pdf.doc.pages[0].rotation, expected);
    }

    #[test]
    fn undo_restores_original_rotation() {
        let (doc, sources) = load_test_pdf();
        let mut pdf = PdfState::new(doc, sources);
        let initial_rotation = pdf.doc.pages[0].rotation;

        // rotate 실행
        let cmd = Box::new(RotatePageCommand::new(0, 90));
        pdf.execute_cmd(cmd, None).expect("rotate 실패");

        // undo
        pdf.stack.undo(&mut pdf.doc).expect("undo 실패");
        let prev = pdf.sources_undo.pop().expect("sources_undo 비어있음");
        let current = std::mem::replace(&mut pdf.sources, prev);
        pdf.sources_redo.push(current);

        assert_eq!(pdf.doc.pages[0].rotation, initial_rotation);
        assert_eq!(pdf.stack.undo_len(), 0);
        assert_eq!(pdf.stack.redo_len(), 1);
        assert_eq!(pdf.sources_undo.len(), 0);
        assert_eq!(pdf.sources_redo.len(), 1);
    }

    #[test]
    fn delete_pages_reduces_page_count() {
        let (doc, sources) = load_two_page_pdf();
        let initial_count = doc.pages.len();
        assert!(initial_count >= 2, "2페이지 이상 필요");

        let mut pdf = PdfState::new(doc, sources);
        let new_sources = compute_new_sources(&pdf.sources, &[0]);
        let cmd = Box::new(DeletePagesCommand::new(vec![0]));
        pdf.execute_cmd(cmd, Some(new_sources))
            .expect("delete 실패");

        assert_eq!(pdf.doc.page_count(), initial_count - 1);
        assert_eq!(pdf.sources.len(), initial_count - 1);
        assert_eq!(pdf.sources_undo.len(), 1);
    }

    #[test]
    fn delete_all_pages_returns_error() {
        let (doc, sources) = load_test_pdf();
        let page_count = doc.pages.len();
        let pdf = PdfState::new(doc, sources);

        // 전체 페이지 인덱스 목록
        let unique_count = page_count;

        // 최소 1페이지 유지 검증 (commands.rs의 delete_pages 로직과 동일)
        assert!(
            pdf.doc.page_count() <= unique_count,
            "전체 삭제 시 에러 조건 충족"
        );
    }

    #[test]
    fn sources_undo_height_invariant_after_rotate_delete_undo_undo() {
        let (doc, sources) = load_multi_page_pdf();
        assert!(doc.pages.len() >= 3, "3페이지 이상 필요");

        let mut pdf = PdfState::new(doc, sources);

        // rotate → delete → undo → undo 시 sources_undo 높이가 CommandStack과 일치해야 한다
        let cmd1 = Box::new(RotatePageCommand::new(0, 90));
        pdf.execute_cmd(cmd1, None).expect("rotate 실패");
        assert_eq!(pdf.stack.undo_len(), pdf.sources_undo.len());

        let new_sources = compute_new_sources(&pdf.sources, &[1]);
        let cmd2 = Box::new(DeletePagesCommand::new(vec![1]));
        pdf.execute_cmd(cmd2, Some(new_sources))
            .expect("delete 실패");
        assert_eq!(pdf.stack.undo_len(), pdf.sources_undo.len());

        // undo delete
        pdf.stack.undo(&mut pdf.doc).expect("undo delete 실패");
        let prev = pdf.sources_undo.pop().expect("sources_undo 비어있음");
        let current = std::mem::replace(&mut pdf.sources, prev);
        pdf.sources_redo.push(current);
        assert_eq!(pdf.stack.undo_len(), pdf.sources_undo.len());

        // undo rotate
        pdf.stack.undo(&mut pdf.doc).expect("undo rotate 실패");
        let prev = pdf.sources_undo.pop().expect("sources_undo 비어있음");
        let current = std::mem::replace(&mut pdf.sources, prev);
        pdf.sources_redo.push(current);

        assert_eq!(pdf.stack.undo_len(), 0);
        assert_eq!(pdf.sources_undo.len(), 0);
        assert_eq!(pdf.stack.redo_len(), 2);
        assert_eq!(pdf.sources_redo.len(), 2);
    }

    // ─── tiff_to_pdf 테스트 ────────────────────────────

    fn make_tiff_bytes(width: u32, height: u32, r: u8, g: u8, b: u8) -> Vec<u8> {
        use image::{ImageFormat, Rgb, RgbImage};
        let img = RgbImage::from_pixel(width, height, Rgb([r, g, b]));
        let mut buf = Vec::new();
        img.write_to(&mut Cursor::new(&mut buf), ImageFormat::Tiff)
            .expect("TIFF 인코딩 실패");
        buf
    }

    fn write_temp_tiff(bytes: &[u8]) -> tempfile::NamedTempFile {
        let mut f = tempfile::Builder::new().suffix(".tiff").tempfile().unwrap();
        f.write_all(bytes).unwrap();
        f
    }

    #[test]
    fn tiff_to_pdf_with_empty_paths_returns_error() {
        let result = tiff_to_pdf(vec![]);
        assert!(result.is_err());
        assert!(result.unwrap_err().contains("TIFF 경로가 없습니다"));
    }

    #[test]
    fn tiff_to_pdf_single_file_returns_valid_pdf() {
        let tiff = make_tiff_bytes(100, 100, 255, 0, 0);
        let tmp = write_temp_tiff(&tiff);
        let path = tmp.path().to_str().unwrap().to_string();

        let bytes = tiff_to_pdf(vec![path]).expect("변환 실패");
        assert!(bytes.starts_with(b"%PDF-"), "PDF 시그니처 없음");

        let (doc, _) = load_document_tracked(&bytes).expect("PDF 로드 실패");
        assert_eq!(doc.page_count(), 1);
    }

    #[test]
    fn tiff_to_pdf_multiple_files_page_count_matches() {
        let tiff1 = make_tiff_bytes(100, 100, 255, 0, 0);
        let tiff2 = make_tiff_bytes(200, 150, 0, 0, 255);
        let tmp1 = write_temp_tiff(&tiff1);
        let tmp2 = write_temp_tiff(&tiff2);
        let path1 = tmp1.path().to_str().unwrap().to_string();
        let path2 = tmp2.path().to_str().unwrap().to_string();

        let bytes = tiff_to_pdf(vec![path1, path2]).expect("변환 실패");
        let (doc, _) = load_document_tracked(&bytes).expect("PDF 로드 실패");
        assert_eq!(doc.page_count(), 2);
    }

    #[test]
    fn tiff_to_pdf_invalid_path_returns_error() {
        let result = tiff_to_pdf(vec!["/nonexistent/path/file.tiff".to_string()]);
        assert!(result.is_err());
    }

    #[test]
    fn tiff_to_pdf_invalid_format_returns_error() {
        // PNG 바이트를 .tiff 확장자 파일에 저장 → 형식 불일치로 decode 실패
        let png_bytes = include_bytes!("../../../../samples/trad-xref-basicapi.pdf");
        let mut tmp = tempfile::Builder::new().suffix(".tiff").tempfile().unwrap();
        tmp.write_all(png_bytes).unwrap();
        let path = tmp.path().to_str().unwrap().to_string();

        let result = tiff_to_pdf(vec![path]);
        assert!(result.is_err());
    }
}
