use std::io::Cursor;

use lopdf::{Dictionary, Document, Object, dictionary};
use thiserror::Error;

// ============================================================
// 공개 타입
// ============================================================

/// PDF 주석 종류.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize, PartialEq, Eq)]
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

/// PDF 주석 에러.
#[derive(Debug, Error)]
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

// ============================================================
// 공개 API
// ============================================================

/// PDF 바이트에 주석을 추가하고, 갱신된 PDF 바이트를 반환한다.
///
/// # Errors
///
/// - `LoadFailed`: lopdf 파싱 실패
/// - `PageIndexOutOfRange`: `page_index >= page_count`
/// - `SaveFailed`: lopdf 저장 실패
pub fn add_annotation(data: &[u8], params: &AnnotationParams) -> Result<Vec<u8>, AnnotationError> {
    let mut doc = Document::load_from(Cursor::new(data))
        .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;

    let page_ids: Vec<_> = doc.page_iter().collect();
    let page_id = page_ids
        .get(params.page_index)
        .copied()
        .ok_or(AnnotationError::PageIndexOutOfRange(params.page_index))?;

    let annot_dict = build_annot_dict(params);
    let annot_id = doc.add_object(Object::Dictionary(annot_dict));

    // 페이지 /Annots 배열에 참조 추가
    // [D-INDIRECT]: /Annots는 inline 배열 또는 간접 참조(Object::Reference) 중 하나
    insert_annot_ref(&mut doc, page_id, annot_id)?;

    let mut buf = Vec::new();
    doc.save_to(&mut buf)
        .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
    Ok(buf)
}

/// PDF 바이트에서 지정 페이지의 주석 목록을 읽는다.
///
/// /Annots 없는 페이지는 빈 Vec을 반환한다.
///
/// # Errors
///
/// - `LoadFailed`: lopdf 파싱 실패
/// - `PageIndexOutOfRange`: `page_index >= page_count`
pub fn get_annotations(
    data: &[u8],
    page_index: usize,
) -> Result<Vec<AnnotationInfo>, AnnotationError> {
    let doc = Document::load_from(Cursor::new(data))
        .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;

    let page_ids: Vec<_> = doc.page_iter().collect();
    let page_id = page_ids
        .get(page_index)
        .copied()
        .ok_or(AnnotationError::PageIndexOutOfRange(page_index))?;

    let page_obj = doc
        .get_object(page_id)
        .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
    let page_dict = page_obj
        .as_dict()
        .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;

    // /Annots 없는 페이지 → 빈 배열 반환 (AN-0)
    let annots_obj = match page_dict.get(b"Annots") {
        Err(_) => return Ok(vec![]),
        Ok(obj) => obj,
    };

    let annot_refs: Vec<Object> = match annots_obj {
        Object::Array(arr) => arr.clone(),
        Object::Reference(ref_id) => {
            let arr_obj = doc
                .get_object(*ref_id)
                .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
            arr_obj
                .as_array()
                .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?
                .clone()
        }
        _ => return Ok(vec![]),
    };

    let mut result = Vec::new();
    for annot_ref in &annot_refs {
        let annot_id_pair = match annot_ref.as_reference() {
            Ok(r) => r,
            Err(_) => continue,
        };
        let annot_obj = match doc.get_object(annot_id_pair) {
            Ok(o) => o,
            Err(_) => continue,
        };
        let annot_dict = match annot_obj.as_dict() {
            Ok(d) => d,
            Err(_) => continue,
        };

        // /Subtype 필터 — 알 수 없는 타입은 skip
        let subtype = match annot_dict.get(b"Subtype") {
            Ok(Object::Name(n)) => n.clone(),
            _ => continue,
        };
        let annot_type = match subtype.as_slice() {
            b"Highlight" => AnnotationType::Highlight,
            b"Underline" => AnnotationType::Underline,
            b"Text" => AnnotationType::StickyNote,
            _ => continue,
        };

        let rect = extract_rect(annot_dict);
        let color = extract_color(annot_dict);
        let content = extract_content(annot_dict);
        let opacity = extract_opacity(annot_dict);

        result.push(AnnotationInfo {
            annot_id: annot_id_pair.0,
            annot_type,
            rect,
            color,
            content,
            opacity,
        });
    }

    Ok(result)
}

/// PDF 바이트에서 주석을 삭제하고, 갱신된 PDF 바이트를 반환한다.
///
/// # Errors
///
/// - `LoadFailed`: lopdf 파싱 실패
/// - `PageIndexOutOfRange`: `page_index >= page_count`
/// - `AnnotationNotFound`: /Annots 없거나 해당 id 없음
/// - `SaveFailed`: lopdf 저장 실패
pub fn delete_annotation(
    data: &[u8],
    page_index: usize,
    annot_id: u32,
) -> Result<Vec<u8>, AnnotationError> {
    let mut doc = Document::load_from(Cursor::new(data))
        .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;

    let page_ids: Vec<_> = doc.page_iter().collect();
    let page_id = page_ids
        .get(page_index)
        .copied()
        .ok_or(AnnotationError::PageIndexOutOfRange(page_index))?;

    // [D-NOANNOTS]: /Annots 없는 페이지 early return
    let has_annots = {
        let page_obj = doc
            .get_object(page_id)
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        let page_dict = page_obj
            .as_dict()
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        page_dict.has(b"Annots")
    };
    if !has_annots {
        return Err(AnnotationError::AnnotationNotFound(annot_id));
    }

    // [D-INDIRECT]: /Annots 간접 참조 처리
    // borrow checker: page_id → annots indirect ref id 먼저 추출
    let indirect_ref_id: Option<(u32, u16)> = {
        let page_obj = doc
            .get_object(page_id)
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        let page_dict = page_obj
            .as_dict()
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        match page_dict.get(b"Annots") {
            Ok(Object::Reference(r)) => Some(*r),
            _ => None,
        }
    };

    let removed = if let Some(ref_id) = indirect_ref_id {
        let arr_obj = doc
            .get_object_mut(ref_id)
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        let arr = arr_obj
            .as_array_mut()
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        let original_len = arr.len();
        arr.retain(|o| {
            o.as_reference()
                .map(|(num, _)| num != annot_id)
                .unwrap_or(true)
        });
        arr.len() < original_len
    } else {
        // Inline 배열
        let page_obj = doc
            .get_object_mut(page_id)
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        let page_dict = page_obj
            .as_dict_mut()
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        let arr = page_dict
            .get_mut(b"Annots")
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?
            .as_array_mut()
            .map_err(|e| AnnotationError::LoadFailed(e.to_string()))?;
        let original_len = arr.len();
        arr.retain(|o| {
            o.as_reference()
                .map(|(num, _)| num != annot_id)
                .unwrap_or(true)
        });
        arr.len() < original_len
    };

    if !removed {
        return Err(AnnotationError::AnnotationNotFound(annot_id));
    }

    // annot 오브젝트 제거 (garbage 방지)
    // generation=0 고정 (일반 PDF에서 충분)
    doc.objects.remove(&(annot_id, 0));

    let mut buf = Vec::new();
    doc.save_to(&mut buf)
        .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
    Ok(buf)
}

// ============================================================
// 내부 헬퍼
// ============================================================

/// /Annot 딕셔너리를 생성한다.
///
/// 공통: /Type /Annot, /Subtype, /Rect, /C
/// Highlight 추가: /CA (opacity)
/// StickyNote 추가: /Contents, /Open false, /Name /Note
fn build_annot_dict(params: &AnnotationParams) -> Dictionary {
    let [x0, y0, x1, y1] = params.rect;
    let [r, g, b] = params.color;

    let subtype = match params.annot_type {
        AnnotationType::Highlight => b"Highlight".to_vec(),
        AnnotationType::Underline => b"Underline".to_vec(),
        AnnotationType::StickyNote => b"Text".to_vec(),
    };

    let mut dict = dictionary! {
        "Type" => Object::Name(b"Annot".to_vec()),
        "Subtype" => Object::Name(subtype),
        "Rect" => Object::Array(vec![
            Object::Real(x0 as f32),
            Object::Real(y0 as f32),
            Object::Real(x1 as f32),
            Object::Real(y1 as f32),
        ]),
        "C" => Object::Array(vec![
            Object::Real(r as f32),
            Object::Real(g as f32),
            Object::Real(b as f32),
        ]),
    };

    match params.annot_type {
        AnnotationType::Highlight => {
            dict.set("CA", Object::Real(params.opacity as f32));
        }
        AnnotationType::StickyNote => {
            if let Some(ref text) = params.content {
                dict.set("Contents", Object::string_literal(text.as_str()));
            }
            dict.set("Open", Object::Boolean(false));
            dict.set("Name", Object::Name(b"Note".to_vec()));
        }
        AnnotationType::Underline => {}
    }

    dict
}

/// 페이지 /Annots 배열에 annot_id 참조를 추가한다.
///
/// inline 배열과 간접 참조(Object::Reference) 모두 처리한다.
fn insert_annot_ref(
    doc: &mut Document,
    page_id: (u32, u16),
    annot_id: (u32, u16),
) -> Result<(), AnnotationError> {
    // borrow checker: indirect ref id 먼저 추출
    let indirect_ref_id: Option<(u32, u16)> = {
        let page_obj = doc
            .get_object(page_id)
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
        let page_dict = page_obj
            .as_dict()
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
        match page_dict.get(b"Annots") {
            Ok(Object::Reference(r)) => Some(*r),
            Ok(Object::Array(_)) => None,
            _ => {
                // /Annots 없음: inline 배열로 새로 생성
                return insert_annot_new_inline(doc, page_id, annot_id);
            }
        }
    };

    if let Some(ref_id) = indirect_ref_id {
        // 간접 참조: 원본 배열 객체에 직접 push
        let arr_obj = doc
            .get_object_mut(ref_id)
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
        let arr = arr_obj
            .as_array_mut()
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
        arr.push(Object::Reference(annot_id));
    } else {
        // Inline 배열: 페이지 dict의 /Annots에 직접 push
        let page_obj = doc
            .get_object_mut(page_id)
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
        let page_dict = page_obj
            .as_dict_mut()
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
        let arr = page_dict
            .get_mut(b"Annots")
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?
            .as_array_mut()
            .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
        arr.push(Object::Reference(annot_id));
    }

    Ok(())
}

/// /Annots가 없는 페이지에 새 inline 배열로 생성한다.
fn insert_annot_new_inline(
    doc: &mut Document,
    page_id: (u32, u16),
    annot_id: (u32, u16),
) -> Result<(), AnnotationError> {
    let page_obj = doc
        .get_object_mut(page_id)
        .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
    let page_dict = page_obj
        .as_dict_mut()
        .map_err(|e| AnnotationError::SaveFailed(e.to_string()))?;
    page_dict.set("Annots", Object::Array(vec![Object::Reference(annot_id)]));
    Ok(())
}

/// /Rect 배열을 [f64; 4]로 추출한다. 실패 시 [0,0,0,0] 반환.
fn extract_rect(dict: &Dictionary) -> [f64; 4] {
    let arr = match dict.get(b"Rect") {
        Ok(Object::Array(a)) => a,
        _ => return [0.0, 0.0, 0.0, 0.0],
    };
    if arr.len() < 4 {
        return [0.0, 0.0, 0.0, 0.0];
    }
    [
        obj_to_f64(&arr[0]),
        obj_to_f64(&arr[1]),
        obj_to_f64(&arr[2]),
        obj_to_f64(&arr[3]),
    ]
}

/// /C 배열을 [f64; 3]으로 추출한다. 실패 시 [0,0,0] 반환.
fn extract_color(dict: &Dictionary) -> [f64; 3] {
    let arr = match dict.get(b"C") {
        Ok(Object::Array(a)) => a,
        _ => return [0.0, 0.0, 0.0],
    };
    if arr.len() < 3 {
        return [0.0, 0.0, 0.0];
    }
    [
        obj_to_f64(&arr[0]),
        obj_to_f64(&arr[1]),
        obj_to_f64(&arr[2]),
    ]
}

/// /Contents 문자열을 Option<String>으로 추출한다.
fn extract_content(dict: &Dictionary) -> Option<String> {
    match dict.get(b"Contents") {
        Ok(Object::String(bytes, _)) => Some(String::from_utf8_lossy(bytes).into_owned()),
        _ => None,
    }
}

/// /CA 값을 f64로 추출한다. 없으면 1.0 반환.
fn extract_opacity(dict: &Dictionary) -> f64 {
    match dict.get(b"CA") {
        Ok(obj) => obj_to_f64(obj),
        Err(_) => 1.0,
    }
}

/// lopdf Object를 f64로 변환한다.
fn obj_to_f64(obj: &Object) -> f64 {
    match obj {
        Object::Real(f) => f64::from(*f),
        Object::Integer(i) => *i as f64,
        _ => 0.0,
    }
}

// ============================================================
// 단위 테스트
// ============================================================

#[cfg(test)]
mod tests {
    use super::*;

    /// 최소 PDF를 lopdf로 생성해 반환한다.
    fn make_minimal_pdf() -> Vec<u8> {
        let mut doc = Document::with_version("1.5");
        let pages_id = doc.new_object_id();

        let page = dictionary! {
            "Type" => Object::Name(b"Page".to_vec()),
            "Parent" => Object::Reference(pages_id),
            "MediaBox" => Object::Array(vec![
                Object::Integer(0),
                Object::Integer(0),
                Object::Integer(595),
                Object::Integer(842),
            ]),
        };
        let page_id = doc.add_object(Object::Dictionary(page));

        let pages = dictionary! {
            "Type" => Object::Name(b"Pages".to_vec()),
            "Kids" => Object::Array(vec![Object::Reference(page_id)]),
            "Count" => Object::Integer(1),
        };
        doc.objects.insert(pages_id, Object::Dictionary(pages));

        let catalog_id = doc.add_object(dictionary! {
            "Type" => Object::Name(b"Catalog".to_vec()),
            "Pages" => Object::Reference(pages_id),
        });
        doc.trailer.set("Root", catalog_id);

        let mut buf = Vec::new();
        doc.save_to(&mut buf).unwrap();
        buf
    }

    fn highlight_params() -> AnnotationParams {
        AnnotationParams {
            page_index: 0,
            annot_type: AnnotationType::Highlight,
            rect: [50.0, 700.0, 200.0, 720.0],
            color: [1.0, 1.0, 0.0],
            content: None,
            opacity: 0.3,
        }
    }

    fn underline_params() -> AnnotationParams {
        AnnotationParams {
            page_index: 0,
            annot_type: AnnotationType::Underline,
            rect: [50.0, 680.0, 200.0, 695.0],
            color: [0.0, 0.0, 0.8],
            content: None,
            opacity: 1.0,
        }
    }

    fn sticky_params(text: &str) -> AnnotationParams {
        AnnotationParams {
            page_index: 0,
            annot_type: AnnotationType::StickyNote,
            rect: [100.0, 500.0, 120.0, 520.0],
            color: [1.0, 1.0, 0.0],
            content: Some(text.to_string()),
            opacity: 1.0,
        }
    }

    /// AN-0: /Annots 없는 페이지에서 빈 Vec 반환.
    #[test]
    fn an_0_get_annotations_empty_page() {
        let pdf = make_minimal_pdf();
        let result = get_annotations(&pdf, 0).unwrap();
        assert!(result.is_empty(), "주석 없는 페이지는 빈 Vec이어야 한다");
    }

    /// AN-1: 하이라이트 추가 후 get_annotations로 1개 반환.
    #[test]
    fn an_1_add_highlight_basic() {
        let pdf = make_minimal_pdf();
        let updated = add_annotation(&pdf, &highlight_params()).unwrap();
        let annots = get_annotations(&updated, 0).unwrap();
        assert_eq!(annots.len(), 1, "주석이 1개여야 한다");
        assert_eq!(annots[0].annot_type, AnnotationType::Highlight);
    }

    /// AN-2: 밑줄 추가 후 type=Underline 확인.
    #[test]
    fn an_2_add_underline_basic() {
        let pdf = make_minimal_pdf();
        let updated = add_annotation(&pdf, &underline_params()).unwrap();
        let annots = get_annotations(&updated, 0).unwrap();
        assert_eq!(annots.len(), 1);
        assert_eq!(annots[0].annot_type, AnnotationType::Underline);
    }

    /// AN-3: StickyNote + content 추가 후 content 확인.
    #[test]
    fn an_3_add_sticky_note() {
        let pdf = make_minimal_pdf();
        let params = sticky_params("메모입니다");
        let updated = add_annotation(&pdf, &params).unwrap();
        let annots = get_annotations(&updated, 0).unwrap();
        assert_eq!(annots.len(), 1);
        assert_eq!(annots[0].annot_type, AnnotationType::StickyNote);
        assert_eq!(annots[0].content.as_deref(), Some("메모입니다"));
    }

    /// AN-4: 추가 → 삭제 → get_annotations 빈 배열.
    #[test]
    fn an_4_delete_annotation() {
        let pdf = make_minimal_pdf();
        let updated = add_annotation(&pdf, &highlight_params()).unwrap();
        let annots = get_annotations(&updated, 0).unwrap();
        assert_eq!(annots.len(), 1);

        let annot_id = annots[0].annot_id;
        let deleted = delete_annotation(&updated, 0, annot_id).unwrap();
        let after = get_annotations(&deleted, 0).unwrap();
        assert!(after.is_empty(), "삭제 후 빈 Vec이어야 한다");
    }

    /// AN-5: 3개 추가 후 3개 반환.
    #[test]
    fn an_5_add_multiple_annotations() {
        let pdf = make_minimal_pdf();
        let p1 = add_annotation(&pdf, &highlight_params()).unwrap();
        let p2 = add_annotation(&p1, &underline_params()).unwrap();
        let p3 = add_annotation(&p2, &sticky_params("세 번째")).unwrap();
        let annots = get_annotations(&p3, 0).unwrap();
        assert_eq!(annots.len(), 3, "주석이 3개여야 한다");
    }

    /// AN-E1: page_index 범위 초과 → PageIndexOutOfRange.
    #[test]
    fn an_e1_page_index_out_of_range() {
        let pdf = make_minimal_pdf();
        let params = AnnotationParams {
            page_index: 99,
            ..highlight_params()
        };
        let result = add_annotation(&pdf, &params);
        assert!(
            matches!(result, Err(AnnotationError::PageIndexOutOfRange(99))),
            "PageIndexOutOfRange(99)이어야 한다"
        );
    }

    /// AN-E2: 유효하지 않은 PDF 바이트 → LoadFailed.
    #[test]
    fn an_e2_invalid_pdf_bytes() {
        let invalid = b"not a pdf at all";
        let result = get_annotations(invalid, 0);
        assert!(
            matches!(result, Err(AnnotationError::LoadFailed(_))),
            "LoadFailed이어야 한다"
        );
    }

    /// AN-E4: 존재하지 않는 annot_id 삭제 → AnnotationNotFound.
    #[test]
    fn an_e4_delete_nonexistent() {
        let pdf = make_minimal_pdf();
        // /Annots 없는 페이지 → AnnotationNotFound
        let result = delete_annotation(&pdf, 0, 9999);
        assert!(
            matches!(result, Err(AnnotationError::AnnotationNotFound(9999))),
            "AnnotationNotFound(9999)이어야 한다"
        );
    }

    /// AN-E4b: /Annots 있지만 해당 id 없음 → AnnotationNotFound.
    #[test]
    fn an_e4b_delete_wrong_id() {
        let pdf = make_minimal_pdf();
        let updated = add_annotation(&pdf, &highlight_params()).unwrap();
        // 존재하지 않는 id로 삭제
        let result = delete_annotation(&updated, 0, 99999);
        assert!(
            matches!(result, Err(AnnotationError::AnnotationNotFound(99999))),
            "AnnotationNotFound(99999)이어야 한다"
        );
    }

    /// opacity 추출: 하이라이트의 /CA 값이 0.3으로 저장·조회된다.
    #[test]
    fn opacity_roundtrip_highlight() {
        let pdf = make_minimal_pdf();
        let updated = add_annotation(&pdf, &highlight_params()).unwrap();
        let annots = get_annotations(&updated, 0).unwrap();
        assert!(
            (annots[0].opacity - 0.3).abs() < 1e-6,
            "opacity가 0.3이어야 한다"
        );
    }

    /// rect 추출: 저장한 rect가 동일하게 조회된다.
    #[test]
    fn rect_roundtrip() {
        let pdf = make_minimal_pdf();
        let params = highlight_params();
        let updated = add_annotation(&pdf, &params).unwrap();
        let annots = get_annotations(&updated, 0).unwrap();
        let rect = annots[0].rect;
        assert!((rect[0] - 50.0).abs() < 1e-6);
        assert!((rect[1] - 700.0).abs() < 1e-6);
        assert!((rect[2] - 200.0).abs() < 1e-6);
        assert!((rect[3] - 720.0).abs() < 1e-6);
    }
}
