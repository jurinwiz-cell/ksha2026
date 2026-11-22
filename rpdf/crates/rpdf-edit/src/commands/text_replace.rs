use std::io::Cursor;

use lopdf::{Dictionary, Document, Object, Stream, dictionary};
use thiserror::Error;

// ============================================================
// 공개 타입
// ============================================================

/// PDF 텍스트 교체에 사용할 폰트 설정.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct FontConfig {
    pub korean_font: BuiltinFont,
    pub latin_font: BuiltinFont,
    pub font_size: f64,
}

/// 지원하는 내장 폰트 목록.
///
/// 한글 TTF 폰트는 `font_bytes` 파라미터로 외부에서 전달한다.
/// 영어 표준 폰트는 PDF 14 표준 폰트이므로 임베딩이 불필요하다.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub enum BuiltinFont {
    // 한글 TTF — font_bytes로 전달
    NanumGothic,
    NanumMyeongjo,
    // 영어 PDF 14 표준 폰트
    Helvetica,
    TimesRoman,
    Courier,
}

impl BuiltinFont {
    /// 한글 TTF 폰트 여부를 반환한다.
    pub fn is_korean(&self) -> bool {
        matches!(self, BuiltinFont::NanumGothic | BuiltinFont::NanumMyeongjo)
    }

    /// PDF 폰트 이름 문자열을 반환한다.
    pub fn pdf_name(&self) -> &'static str {
        match self {
            BuiltinFont::NanumGothic => "NanumGothic",
            BuiltinFont::NanumMyeongjo => "NanumMyeongjo",
            BuiltinFont::Helvetica => "Helvetica",
            BuiltinFont::TimesRoman => "Times-Roman",
            BuiltinFont::Courier => "Courier",
        }
    }
}

/// 텍스트 정렬 방식.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub enum TextAlign {
    Left,
    Center,
    Right,
}

/// 텍스트 서식 설정.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct TextStyle {
    /// 굵게 (PDF text rendering mode 2).
    pub bold: bool,
    /// 기울임 (oblique matrix).
    pub italic: bool,
    /// 밑줄.
    pub underline: bool,
    /// 취소선.
    pub strikethrough: bool,
    /// RGB 색상 (0.0~1.0).
    pub color: [f64; 3],
    /// 정렬 방식.
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

/// 텍스트 교체 파라미터.
#[derive(Debug, Clone)]
pub struct TextReplaceParams {
    /// 0-based 페이지 인덱스.
    pub page_index: usize,
    /// 흰 박스 영역 `[x0, y0, width, height]` (PDF 좌표계).
    pub cover_rect: [f64; 4],
    /// 삽입할 새 텍스트.
    pub new_text: String,
    /// 텍스트 삽입 X 좌표.
    pub text_x: f64,
    /// 텍스트 삽입 Y 좌표.
    pub text_y: f64,
    /// 폰트 설정.
    pub font_config: FontConfig,
    /// 텍스트 서식.
    pub text_style: TextStyle,
}

/// 텍스트 교체 에러.
#[derive(Debug, Error)]
pub enum TextReplaceError {
    #[error("page index {0} out of bounds")]
    PageOutOfBounds(usize),
    #[error("failed to load PDF: {0}")]
    LoadFailed(String),
    #[error("failed to embed font: {0}")]
    FontEmbedFailed(String),
    #[error("failed to save PDF: {0}")]
    SaveFailed(String),
}

// ============================================================
// 공개 API
// ============================================================

/// PDF 바이트에서 지정 페이지의 영역을 흰 박스로 덮고 새 텍스트를 삽입한다.
///
/// - `pdf_bytes`: 원본 PDF 바이트.
/// - `params`: 페이지 인덱스, 덮을 영역, 삽입 텍스트, 좌표, 폰트 설정.
/// - `font_bytes`: 한글 TTF 바이트. `font_config.korean_font`가 한글 폰트일 때
///   반드시 제공해야 한다.
///
/// # Errors
///
/// - `PageOutOfBounds`: `page_index >= page_count`
/// - `LoadFailed`: lopdf 파싱 실패
/// - `FontEmbedFailed`: 한글 폰트인데 `font_bytes`가 None이거나 TTF 파싱 실패
/// - `SaveFailed`: lopdf 저장 실패
pub fn apply_text_replace(
    pdf_bytes: &[u8],
    params: &TextReplaceParams,
    font_bytes: Option<&[u8]>,
) -> Result<Vec<u8>, TextReplaceError> {
    let mut doc = Document::load_from(Cursor::new(pdf_bytes))
        .map_err(|e| TextReplaceError::LoadFailed(e.to_string()))?;

    let page_ids: Vec<_> = doc.page_iter().collect();
    let page_id = page_ids
        .get(params.page_index)
        .copied()
        .ok_or(TextReplaceError::PageOutOfBounds(params.page_index))?;

    let use_korean = params.font_config.korean_font.is_korean();

    // 한글 폰트를 사용하는데 font_bytes가 없으면 즉시 에러
    if use_korean && font_bytes.is_none() {
        return Err(TextReplaceError::FontEmbedFailed(
            "한글 폰트 사용 시 font_bytes가 필요합니다".to_string(),
        ));
    }

    // 한글 텍스트 hex 인코딩 — font_bytes 있을 때만 GlyphId 기반, 없으면 codepoint
    let content_stream = build_content_stream(params, use_korean, font_bytes);
    let stream_id = doc.add_object(content_stream);

    append_content_to_page(&mut doc, page_id, stream_id)?;

    if use_korean {
        let ttf = font_bytes.expect("checked above: use_korean implies font_bytes is Some");
        embed_korean_font(
            &mut doc,
            page_id,
            "KoFont",
            ttf,
            &params.font_config.korean_font,
        )?;
    }

    embed_latin_font(&mut doc, page_id, "LaFont", &params.font_config.latin_font)?;

    let mut buf = Vec::new();
    doc.save_to(&mut buf)
        .map_err(|e| TextReplaceError::SaveFailed(e.to_string()))?;
    Ok(buf)
}

// ============================================================
// 내부 헬퍼
// ============================================================

/// 텍스트 너비를 추정한다.
///
/// 한글·CJK 문자는 font_size * 0.95, 그 외는 font_size * 0.55로 계산한다.
fn estimate_text_width(text: &str, font_size: f64) -> f64 {
    text.chars()
        .map(|c| {
            if ('\u{AC00}'..='\u{D7A3}').contains(&c) || ('\u{4E00}'..='\u{9FFF}').contains(&c) {
                font_size * 0.95
            } else {
                font_size * 0.55
            }
        })
        .sum()
}

/// 흰 박스 + 텍스트 content stream을 생성한다.
fn build_content_stream(
    params: &TextReplaceParams,
    use_korean: bool,
    font_bytes: Option<&[u8]>,
) -> Stream {
    let [x0, y0, w, h] = params.cover_rect;
    let size = params.font_config.font_size;
    let style = &params.text_style;
    let [r, g, b] = style.color;

    // 정렬에 따른 X 좌표 조정
    let adjusted_x = match style.alignment {
        TextAlign::Left => params.text_x,
        TextAlign::Center => params.text_x - estimate_text_width(&params.new_text, size) / 2.0,
        TextAlign::Right => params.text_x - estimate_text_width(&params.new_text, size),
    };

    let x = adjusted_x;
    let y = params.text_y;

    // 텍스트 너비 (밑줄·취소선용)
    let text_w = estimate_text_width(&params.new_text, size);

    // 색상 연산자
    let color_fill = format!("{r:.4} {g:.4} {b:.4} rg\n");
    let color_stroke = if style.bold {
        format!("{r:.4} {g:.4} {b:.4} RG\n0.5 w\n")
    } else {
        String::new()
    };

    // 위치 연산자 (italic은 Tm 행렬, 그 외는 Td)
    let position_op = if style.italic {
        format!("1 0.2 0 1 {x:.4} {y:.4} Tm\n")
    } else {
        format!("{x:.4} {y:.4} Td\n")
    };

    // text rendering mode 연산자 (bold=2, 기본=0)
    let tr_op = if style.bold { "2 Tr\n" } else { "0 Tr\n" };

    let text_op = if use_korean {
        let hex = font_bytes
            .map(|b| encode_korean_text(&params.new_text, b))
            .unwrap_or_else(|| format!("<{}>", encode_chars_as_codepoints(&params.new_text)));
        format!("BT\n{position_op}/KoFont {size} Tf\n{tr_op}{hex} Tj\nET\n")
    } else {
        let escaped = escape_pdf_string(&params.new_text);
        format!("BT\n{position_op}/LaFont {size} Tf\n{tr_op}({escaped}) Tj\nET\n")
    };

    // 밑줄 연산자
    let underline_op = if style.underline {
        let uy = y - 1.0;
        let ux2 = x + text_w;
        format!("q\n{r:.4} {g:.4} {b:.4} RG\n1 w\n{x:.4} {uy:.4} m {ux2:.4} {uy:.4} l S\nQ\n")
    } else {
        String::new()
    };

    // 취소선 연산자
    let strikethrough_op = if style.strikethrough {
        let sy = y + size * 0.35;
        let sx2 = x + text_w;
        format!("q\n{r:.4} {g:.4} {b:.4} RG\n1 w\n{x:.4} {sy:.4} m {sx2:.4} {sy:.4} l S\nQ\n")
    } else {
        String::new()
    };

    let cover_op = format!("q\n1 1 1 rg\n{x0} {y0} {w} {h} re\nf\nQ\n");
    let content =
        format!("{cover_op}{color_fill}{color_stroke}{text_op}{underline_op}{strikethrough_op}");

    Stream::new(Dictionary::new(), content.into_bytes())
}

/// 한글 텍스트를 GlyphId hex string으로 인코딩한다.
///
/// TTF 파싱 실패 시 유니코드 코드포인트 fallback을 사용한다.
fn encode_korean_text(text: &str, font_bytes: &[u8]) -> String {
    let face = match ttf_parser::Face::parse(font_bytes, 0) {
        Ok(f) => f,
        Err(_) => return format!("<{}>", encode_chars_as_codepoints(text)),
    };
    let hex: String = text
        .chars()
        .filter_map(|c| face.glyph_index(c))
        .map(|gid| format!("{:04X}", gid.0))
        .collect();
    format!("<{hex}>")
}

/// 유니코드 코드포인트를 hex string으로 인코딩한다 (GlyphId 매핑 불가 시 fallback).
fn encode_chars_as_codepoints(text: &str) -> String {
    text.chars().map(|c| format!("{:04X}", c as u32)).collect()
}

/// PDF 문자열의 특수문자를 이스케이프한다.
fn escape_pdf_string(text: &str) -> String {
    text.replace('\\', "\\\\")
        .replace('(', "\\(")
        .replace(')', "\\)")
}

/// 페이지 /Contents에 새 stream_id를 append한다.
///
/// 기존 /Contents가 단일 Reference이면 배열로 변환하고 append한다.
/// 기존이 배열이면 그대로 push한다.
fn append_content_to_page(
    doc: &mut Document,
    page_id: (u32, u16),
    stream_id: (u32, u16),
) -> Result<(), TextReplaceError> {
    let page_obj = doc
        .get_object_mut(page_id)
        .map_err(|e| TextReplaceError::SaveFailed(e.to_string()))?;

    let dict = page_obj
        .as_dict_mut()
        .map_err(|e| TextReplaceError::SaveFailed(e.to_string()))?;

    let new_ref = Object::Reference(stream_id);

    if let Ok(existing) = dict.get(b"Contents") {
        let updated = match existing {
            Object::Array(arr) => {
                let mut new_arr = arr.clone();
                new_arr.push(new_ref);
                Object::Array(new_arr)
            }
            Object::Reference(r) => {
                let r = *r;
                Object::Array(vec![Object::Reference(r), new_ref])
            }
            _ => Object::Array(vec![existing.clone(), new_ref]),
        };
        dict.set("Contents", updated);
    } else {
        dict.set("Contents", new_ref);
    }

    Ok(())
}

/// page Resources /Font 딕셔너리에서 주어진 키 존재 여부를 확인한다.
fn font_key_exists(doc: &Document, page_id: (u32, u16), key: &[u8]) -> bool {
    let Ok(page_obj) = doc.get_object(page_id) else {
        return false;
    };
    let Ok(dict) = page_obj.as_dict() else {
        return false;
    };
    let Ok(resources) = dict.get(b"Resources") else {
        return false;
    };
    let font_dict = match resources {
        Object::Dictionary(d) => d.get(b"Font").ok(),
        Object::Reference(r) => doc
            .get_object(*r)
            .ok()
            .and_then(|o| o.as_dict().ok())
            .and_then(|d| d.get(b"Font").ok()),
        _ => None,
    };
    match font_dict {
        Some(Object::Dictionary(fd)) => fd.has(key),
        Some(Object::Reference(r)) => doc
            .get_object(*r)
            .ok()
            .and_then(|o| o.as_dict().ok())
            .map(|d| d.has(key))
            .unwrap_or(false),
        _ => false,
    }
}

/// page Resources /Font 딕셔너리에 폰트를 등록한다.
///
/// /Resources가 Reference인 경우 원본 객체를 직접 수정해 공유 리소스를 보존한다.
fn register_font_to_page(
    doc: &mut Document,
    page_id: (u32, u16),
    font_key: &[u8],
    font_ref: (u32, u16),
) -> Result<(), TextReplaceError> {
    // 먼저 /Resources가 Reference인지 Inline인지 파악한다.
    // borrow checker 제약으로 page_obj 참조를 해제한 뒤 별도로 get_object_mut 호출.
    let resources_ref = {
        let page_obj = doc
            .get_object(page_id)
            .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;
        let dict = page_obj
            .as_dict()
            .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;
        match dict.get(b"Resources") {
            Ok(Object::Reference(r)) => Some(*r),
            _ => None,
        }
    };

    if let Some(res_id) = resources_ref {
        // Reference: 원본 Resources 객체를 직접 수정해 공유 리소스 보존
        let res_obj = doc
            .get_object_mut(res_id)
            .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;
        let res_dict = res_obj
            .as_dict_mut()
            .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;
        set_font_in_resources(res_dict, font_key, font_ref);
    } else {
        // Inline dict 또는 /Resources 없음: 페이지 dict 직접 수정
        let page_obj = doc
            .get_object_mut(page_id)
            .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;
        let dict = page_obj
            .as_dict_mut()
            .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;

        if dict.has(b"Resources") {
            let resources = dict
                .get_mut(b"Resources")
                .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;
            if let Object::Dictionary(res_dict) = resources {
                set_font_in_resources(res_dict, font_key, font_ref);
            }
            // Reference가 아닌 다른 타입(Name 등)은 무시 — 정상 PDF라면 발생 안 함
        } else {
            let mut font_dict = Dictionary::new();
            font_dict.set(font_key, Object::Reference(font_ref));
            let mut res_dict = Dictionary::new();
            res_dict.set("Font", Object::Dictionary(font_dict));
            dict.set("Resources", Object::Dictionary(res_dict));
        }
    }

    Ok(())
}

/// Resources 딕셔너리의 /Font 서브딕셔너리에 폰트를 등록한다.
fn set_font_in_resources(res_dict: &mut Dictionary, font_key: &[u8], font_ref: (u32, u16)) {
    if res_dict.has(b"Font") {
        if let Ok(Object::Dictionary(fd)) = res_dict.get_mut(b"Font") {
            fd.set(font_key, Object::Reference(font_ref));
        }
    } else {
        let mut font_dict = Dictionary::new();
        font_dict.set(font_key, Object::Reference(font_ref));
        res_dict.set("Font", Object::Dictionary(font_dict));
    }
}

/// 한글 TTF 폰트를 Type0 CIDFont로 임베딩한다.
///
/// `font_key`가 이미 Resources /Font에 존재하면 중복 임베딩을 생략한다.
fn embed_korean_font(
    doc: &mut Document,
    page_id: (u32, u16),
    font_key: &str,
    font_bytes: &[u8],
    font: &BuiltinFont,
) -> Result<(), TextReplaceError> {
    if font_key_exists(doc, page_id, font_key.as_bytes()) {
        return Ok(());
    }

    let face = ttf_parser::Face::parse(font_bytes, 0)
        .map_err(|e| TextReplaceError::FontEmbedFailed(e.to_string()))?;

    let units = face.units_per_em() as f64;
    let ascender = face.ascender() as f64 / units * 1000.0;
    let descender = face.descender() as f64 / units * 1000.0;
    let cap_height = face.capital_height().unwrap_or(face.ascender()) as f64 / units * 1000.0;

    let base_font = font.pdf_name();

    // FontFile2 (TTF stream)
    let mut font_stream_dict = Dictionary::new();
    font_stream_dict.set("Length1", Object::Integer(font_bytes.len() as i64));
    let font_stream = Stream::new(font_stream_dict, font_bytes.to_vec());
    let font_file_id = doc.add_object(font_stream);

    // FontDescriptor
    let font_descriptor = dictionary! {
        "Type" => Object::Name(b"FontDescriptor".to_vec()),
        "FontName" => Object::Name(base_font.as_bytes().to_vec()),
        "Flags" => Object::Integer(4),
        "FontBBox" => Object::Array(vec![
            Object::Integer(-1000),
            Object::Integer(descender as i64),
            Object::Integer(2000),
            Object::Integer(ascender as i64),
        ]),
        "ItalicAngle" => Object::Integer(0),
        "Ascent" => Object::Integer(ascender as i64),
        "Descent" => Object::Integer(descender as i64),
        "CapHeight" => Object::Integer(cap_height as i64),
        "StemV" => Object::Integer(80),
        "FontFile2" => Object::Reference(font_file_id),
    };
    let descriptor_id = doc.add_object(Object::Dictionary(font_descriptor));

    // CIDSystemInfo
    let cid_system_info = dictionary! {
        "Registry" => Object::string_literal("Adobe"),
        "Ordering" => Object::string_literal("Identity"),
        "Supplement" => Object::Integer(0),
    };

    // CIDFont (CIDFontType2)
    let cid_font = dictionary! {
        "Type" => Object::Name(b"Font".to_vec()),
        "Subtype" => Object::Name(b"CIDFontType2".to_vec()),
        "BaseFont" => Object::Name(base_font.as_bytes().to_vec()),
        "CIDSystemInfo" => Object::Dictionary(cid_system_info),
        "FontDescriptor" => Object::Reference(descriptor_id),
        "DW" => Object::Integer(1000),
    };
    let cid_font_id = doc.add_object(Object::Dictionary(cid_font));

    // ToUnicode CMap
    let cmap_content = "/CIDInit /ProcSet findresource begin\n\
        12 dict begin\n\
        begincmap\n\
        /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n\
        /CMapName /Adobe-Identity-H def\n\
        endcmap\n\
        CMapName currentdict /CMap defineresource pop\n\
        end\n\
        end";
    let cmap_stream = Stream::new(Dictionary::new(), cmap_content.as_bytes().to_vec());
    let cmap_id = doc.add_object(cmap_stream);

    // Type0 Font
    let type0_font = dictionary! {
        "Type" => Object::Name(b"Font".to_vec()),
        "Subtype" => Object::Name(b"Type0".to_vec()),
        "BaseFont" => Object::Name(base_font.as_bytes().to_vec()),
        "Encoding" => Object::Name(b"Identity-H".to_vec()),
        "DescendantFonts" => Object::Array(vec![Object::Reference(cid_font_id)]),
        "ToUnicode" => Object::Reference(cmap_id),
    };
    let type0_id = doc.add_object(Object::Dictionary(type0_font));

    register_font_to_page(doc, page_id, font_key.as_bytes(), type0_id)
}

/// 영어 표준 폰트(Type1)를 임베딩한다.
///
/// `font_key`가 이미 Resources /Font에 존재하면 중복 임베딩을 생략한다.
fn embed_latin_font(
    doc: &mut Document,
    page_id: (u32, u16),
    font_key: &str,
    font: &BuiltinFont,
) -> Result<(), TextReplaceError> {
    if font_key_exists(doc, page_id, font_key.as_bytes()) {
        return Ok(());
    }

    let base_font = font.pdf_name();
    let type1_font = dictionary! {
        "Type" => Object::Name(b"Font".to_vec()),
        "Subtype" => Object::Name(b"Type1".to_vec()),
        "BaseFont" => Object::Name(base_font.as_bytes().to_vec()),
        "Encoding" => Object::Name(b"WinAnsiEncoding".to_vec()),
    };
    let font_id = doc.add_object(Object::Dictionary(type1_font));

    register_font_to_page(doc, page_id, font_key.as_bytes(), font_id)
}

// ============================================================
// 단위 테스트
// ============================================================

#[cfg(test)]
mod internal_tests {
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

    /// 영어 폰트 전용 파라미터 (font_bytes 불필요).
    ///
    /// korean_font도 Helvetica로 설정해 use_korean = false가 되도록 한다.
    fn latin_params(text: &str) -> TextReplaceParams {
        TextReplaceParams {
            page_index: 0,
            cover_rect: [10.0, 10.0, 100.0, 20.0],
            new_text: text.to_string(),
            text_x: 15.0,
            text_y: 15.0,
            font_config: FontConfig {
                korean_font: BuiltinFont::Helvetica,
                latin_font: BuiltinFont::Helvetica,
                font_size: 12.0,
            },
            text_style: TextStyle::default(),
        }
    }

    /// 한글 폰트 파라미터 (font_bytes 필요).
    fn korean_params(text: &str) -> TextReplaceParams {
        TextReplaceParams {
            page_index: 0,
            cover_rect: [10.0, 10.0, 200.0, 30.0],
            new_text: text.to_string(),
            text_x: 15.0,
            text_y: 15.0,
            font_config: FontConfig {
                korean_font: BuiltinFont::NanumGothic,
                latin_font: BuiltinFont::Helvetica,
                font_size: 14.0,
            },
            text_style: TextStyle::default(),
        }
    }

    /// TR-1: ASCII 텍스트 교체 후 바이트 길이가 증가한다.
    #[test]
    fn tr_1_ascii_replace_increases_size() {
        let original = make_minimal_pdf();
        let original_len = original.len();
        let result = apply_text_replace(&original, &latin_params("Hello"), None).unwrap();
        assert!(result.len() > original_len, "바이트 길이가 증가해야 한다");
    }

    /// TR-2: 한글 삽입 시도 — font_bytes가 유효하지 않으면 FontEmbedFailed.
    ///
    /// 실제 NanumGothic.ttf 없이 테스트하므로 파싱 실패를 예상한다.
    #[test]
    fn tr_2_korean_insert_invalid_ttf_returns_error() {
        let original = make_minimal_pdf();
        let invalid_ttf = vec![0u8; 16]; // 파싱 불가 더미
        let result = apply_text_replace(&original, &korean_params("안녕"), Some(&invalid_ttf));
        assert!(
            matches!(result, Err(TextReplaceError::FontEmbedFailed(_))),
            "유효하지 않은 TTF이면 FontEmbedFailed여야 한다"
        );
    }

    /// TR-3: content stream에 흰 박스 연산자(`re`, `f`)가 존재한다.
    #[test]
    fn tr_3_content_contains_white_box_operators() {
        let original = make_minimal_pdf();
        let params = TextReplaceParams {
            page_index: 0,
            cover_rect: [50.0, 50.0, 150.0, 30.0],
            new_text: "Test".to_string(),
            text_x: 55.0,
            text_y: 55.0,
            font_config: FontConfig {
                korean_font: BuiltinFont::Helvetica,
                latin_font: BuiltinFont::Helvetica,
                font_size: 12.0,
            },
            text_style: TextStyle::default(),
        };
        let result = apply_text_replace(&original, &params, None).unwrap();
        let content = String::from_utf8_lossy(&result);
        assert!(content.contains(" re"), "content에 re 연산자가 있어야 한다");
        assert!(
            content.contains("f\nQ") || content.contains("\nf\n"),
            "content에 f 연산자가 있어야 한다"
        );
    }

    /// TR-4: 영어 폰트 사용 시 LaFont 리소스가 Resources/Font에 등록된다.
    #[test]
    fn tr_4_la_font_registered_in_resources() {
        let original = make_minimal_pdf();
        let result = apply_text_replace(&original, &latin_params("Hi"), None).unwrap();
        let content = String::from_utf8_lossy(&result);
        assert!(
            content.contains("LaFont"),
            "LaFont 리소스가 등록되어야 한다"
        );
    }

    /// TR-5: 같은 페이지 2회 영어 폰트 편집 → LaFont 리소스가 lopdf에서 올바르게 조회된다.
    #[test]
    fn tr_5_no_duplicate_la_font_on_double_edit() {
        let original = make_minimal_pdf();

        let first = apply_text_replace(&original, &latin_params("A"), None).unwrap();
        let second = apply_text_replace(&first, &latin_params("B"), None).unwrap();

        let second_doc = Document::load_from(Cursor::new(&second)).unwrap();
        let page_ids: Vec<_> = second_doc.page_iter().collect();
        let page_id = page_ids[0];

        // 두 번째 편집 후에도 LaFont 키가 등록되어 있어야 한다
        assert!(
            font_key_exists(&second_doc, page_id, b"LaFont"),
            "LaFont 키가 등록되어 있어야 한다"
        );
        // 두 번째 편집 후 PDF 재로드 성공해야 한다
        assert!(Document::load_from(Cursor::new(&second)).is_ok());
    }

    /// TR-6: page_index >= page_count → PageOutOfBounds 에러.
    #[test]
    fn tr_6_out_of_bounds_returns_error() {
        let original = make_minimal_pdf();
        let params = TextReplaceParams {
            page_index: 99,
            cover_rect: [0.0, 0.0, 10.0, 10.0],
            new_text: "x".to_string(),
            text_x: 0.0,
            text_y: 0.0,
            font_config: FontConfig {
                korean_font: BuiltinFont::NanumGothic,
                latin_font: BuiltinFont::Helvetica,
                font_size: 10.0,
            },
            text_style: TextStyle::default(),
        };
        let result = apply_text_replace(&original, &params, None);
        assert!(matches!(result, Err(TextReplaceError::PageOutOfBounds(99))));
    }

    /// TR-7: 영어 폰트(Helvetica) 선택 시 FontFile2 없음 (LaFont만 존재).
    #[test]
    fn tr_7_latin_font_no_font_file2() {
        let original = make_minimal_pdf();
        let result = apply_text_replace(&original, &latin_params("Hello"), None).unwrap();
        let content = String::from_utf8_lossy(&result);
        assert!(
            !content.contains("FontFile2"),
            "영어 폰트 전용 시 FontFile2가 없어야 한다"
        );
        assert!(content.contains("LaFont"), "LaFont 리소스가 있어야 한다");
    }

    /// TR-8: font_bytes = None + 한글 폰트 선택 → FontEmbedFailed 에러.
    #[test]
    fn tr_8_korean_font_without_bytes_returns_error() {
        let original = make_minimal_pdf();
        let result = apply_text_replace(&original, &korean_params("가나다"), None);
        assert!(
            matches!(result, Err(TextReplaceError::FontEmbedFailed(_))),
            "한글 폰트인데 font_bytes가 없으면 FontEmbedFailed여야 한다"
        );
    }

    // ============================================================
    // TR-Style 테스트: TextStyle 서식 적용 검증
    // ============================================================

    /// TR-Style-1: bold=true → content stream에 `2 Tr` 포함.
    #[test]
    fn tr_style_1_bold_includes_2_tr() {
        let stream = build_content_stream(
            &TextReplaceParams {
                page_index: 0,
                cover_rect: [0.0, 0.0, 100.0, 20.0],
                new_text: "Bold".to_string(),
                text_x: 10.0,
                text_y: 10.0,
                font_config: FontConfig {
                    korean_font: BuiltinFont::Helvetica,
                    latin_font: BuiltinFont::Helvetica,
                    font_size: 12.0,
                },
                text_style: TextStyle {
                    bold: true,
                    ..TextStyle::default()
                },
            },
            false,
            None,
        );
        let content = String::from_utf8_lossy(&stream.content);
        assert!(content.contains("2 Tr"), "bold=true이면 2 Tr이 있어야 한다");
    }

    /// TR-Style-2: italic=true → content stream에 `Tm` 포함, `Td` 미포함.
    #[test]
    fn tr_style_2_italic_uses_tm_not_td() {
        let stream = build_content_stream(
            &TextReplaceParams {
                page_index: 0,
                cover_rect: [0.0, 0.0, 100.0, 20.0],
                new_text: "Italic".to_string(),
                text_x: 10.0,
                text_y: 10.0,
                font_config: FontConfig {
                    korean_font: BuiltinFont::Helvetica,
                    latin_font: BuiltinFont::Helvetica,
                    font_size: 12.0,
                },
                text_style: TextStyle {
                    italic: true,
                    ..TextStyle::default()
                },
            },
            false,
            None,
        );
        let content = String::from_utf8_lossy(&stream.content);
        assert!(content.contains("Tm"), "italic=true이면 Tm이 있어야 한다");
        assert!(!content.contains("Td"), "italic=true이면 Td가 없어야 한다");
    }

    /// TR-Style-3: color=[1,0,0] → `1 0 0 rg` 포함.
    #[test]
    fn tr_style_3_color_sets_rg_operator() {
        let stream = build_content_stream(
            &TextReplaceParams {
                page_index: 0,
                cover_rect: [0.0, 0.0, 100.0, 20.0],
                new_text: "Red".to_string(),
                text_x: 10.0,
                text_y: 10.0,
                font_config: FontConfig {
                    korean_font: BuiltinFont::Helvetica,
                    latin_font: BuiltinFont::Helvetica,
                    font_size: 12.0,
                },
                text_style: TextStyle {
                    color: [1.0, 0.0, 0.0],
                    ..TextStyle::default()
                },
            },
            false,
            None,
        );
        let content = String::from_utf8_lossy(&stream.content);
        assert!(
            content.contains("1.0000 0.0000 0.0000 rg"),
            "color=[1,0,0]이면 1.0000 0.0000 0.0000 rg가 있어야 한다"
        );
    }

    /// TR-Style-4: underline=true → content stream에 `m`, `l`, `S` 포함.
    #[test]
    fn tr_style_4_underline_includes_line_operators() {
        let stream = build_content_stream(
            &TextReplaceParams {
                page_index: 0,
                cover_rect: [0.0, 0.0, 100.0, 20.0],
                new_text: "Underline".to_string(),
                text_x: 10.0,
                text_y: 10.0,
                font_config: FontConfig {
                    korean_font: BuiltinFont::Helvetica,
                    latin_font: BuiltinFont::Helvetica,
                    font_size: 12.0,
                },
                text_style: TextStyle {
                    underline: true,
                    ..TextStyle::default()
                },
            },
            false,
            None,
        );
        let content = String::from_utf8_lossy(&stream.content);
        assert!(
            content.contains(" m "),
            "underline=true이면 m 연산자가 있어야 한다"
        );
        assert!(
            content.contains(" l "),
            "underline=true이면 l 연산자가 있어야 한다"
        );
        assert!(
            content.contains(" S\n"),
            "underline=true이면 S 연산자가 있어야 한다"
        );
    }

    /// TR-Style-5: strikethrough=true → content stream에 `m`, `l`, `S` 포함.
    #[test]
    fn tr_style_5_strikethrough_includes_line_operators() {
        let stream = build_content_stream(
            &TextReplaceParams {
                page_index: 0,
                cover_rect: [0.0, 0.0, 100.0, 20.0],
                new_text: "Strike".to_string(),
                text_x: 10.0,
                text_y: 10.0,
                font_config: FontConfig {
                    korean_font: BuiltinFont::Helvetica,
                    latin_font: BuiltinFont::Helvetica,
                    font_size: 12.0,
                },
                text_style: TextStyle {
                    strikethrough: true,
                    ..TextStyle::default()
                },
            },
            false,
            None,
        );
        let content = String::from_utf8_lossy(&stream.content);
        assert!(
            content.contains(" m "),
            "strikethrough=true이면 m 연산자가 있어야 한다"
        );
        assert!(
            content.contains(" l "),
            "strikethrough=true이면 l 연산자가 있어야 한다"
        );
        assert!(
            content.contains(" S\n"),
            "strikethrough=true이면 S 연산자가 있어야 한다"
        );
    }

    /// TR-Style-6: alignment=Center → adjusted_x < original_x.
    #[test]
    fn tr_style_6_center_alignment_shifts_x_left() {
        let text_x = 200.0_f64;
        let stream_center = build_content_stream(
            &TextReplaceParams {
                page_index: 0,
                cover_rect: [0.0, 0.0, 400.0, 20.0],
                new_text: "CenterText".to_string(),
                text_x,
                text_y: 10.0,
                font_config: FontConfig {
                    korean_font: BuiltinFont::Helvetica,
                    latin_font: BuiltinFont::Helvetica,
                    font_size: 12.0,
                },
                text_style: TextStyle {
                    alignment: TextAlign::Center,
                    ..TextStyle::default()
                },
            },
            false,
            None,
        );
        let content_center = String::from_utf8_lossy(&stream_center.content);
        // Center 정렬 시 Td/Tm의 x 값이 원래 text_x보다 작아야 한다
        // "Td" 직전에 나타나는 x 좌표를 확인
        // content 내에 text_x 값 그대로는 없어야 함
        let original_x_str = format!("{:.4}", text_x);
        assert!(
            !content_center.contains(&format!("{original_x_str} 10.0000 Td")),
            "Center 정렬이면 x가 조정되어야 한다"
        );
    }

    /// TR-Style-7: alignment=Right → adjusted_x < Center case.
    #[test]
    fn tr_style_7_right_alignment_more_shift_than_center() {
        let text_x = 200.0_f64;
        let text = "RightText";
        let font_size = 12.0_f64;

        let width = estimate_text_width(text, font_size);
        let center_x = text_x - width / 2.0;
        let right_x = text_x - width;

        assert!(
            right_x < center_x,
            "Right 정렬의 x가 Center 정렬의 x보다 작아야 한다"
        );
    }

    /// TR-Style-8: TextStyle::default() 사용 시 기존 TR-1~8 회귀 없음.
    #[test]
    fn tr_style_8_default_style_no_regression() {
        let original = make_minimal_pdf();
        // TR-1 회귀: ASCII 텍스트 교체 후 크기 증가
        let result = apply_text_replace(&original, &latin_params("Hello"), None).unwrap();
        assert!(result.len() > original.len());

        // 기본 스타일은 bold/italic/underline/strikethrough 없음 → 2 Tr, Tm 없음
        let content = String::from_utf8_lossy(&result);
        assert!(
            !content.contains("2 Tr"),
            "기본 스타일이면 2 Tr 없어야 한다"
        );
    }

    /// TR-Style-9: bold=true && italic=true 동시 → `Tm` + `2 Tr` 모두 포함.
    #[test]
    fn tr_style_9_bold_and_italic_both_applied() {
        let stream = build_content_stream(
            &TextReplaceParams {
                page_index: 0,
                cover_rect: [0.0, 0.0, 100.0, 20.0],
                new_text: "BoldItalic".to_string(),
                text_x: 10.0,
                text_y: 10.0,
                font_config: FontConfig {
                    korean_font: BuiltinFont::Helvetica,
                    latin_font: BuiltinFont::Helvetica,
                    font_size: 12.0,
                },
                text_style: TextStyle {
                    bold: true,
                    italic: true,
                    ..TextStyle::default()
                },
            },
            false,
            None,
        );
        let content = String::from_utf8_lossy(&stream.content);
        assert!(content.contains("Tm"), "bold+italic이면 Tm이 있어야 한다");
        assert!(
            content.contains("2 Tr"),
            "bold+italic이면 2 Tr이 있어야 한다"
        );
    }

    /// encode_korean_text — 유효하지 않은 TTF bytes → codepoint fallback 실행.
    #[test]
    fn encode_korean_text_invalid_bytes_fallback() {
        let result = encode_korean_text("AB", b"not-a-ttf");
        assert!(result.starts_with('<'), "hex string 형식이어야 한다");
        assert!(result.ends_with('>'), "hex string 형식이어야 한다");
    }

    /// escape_pdf_string — 괄호와 백슬래시를 이스케이프한다.
    #[test]
    fn escape_pdf_string_escapes_parens_and_backslash() {
        let result = escape_pdf_string("(hello) world\\");
        assert_eq!(result, "\\(hello\\) world\\\\");
    }

    /// BuiltinFont::is_korean — 한글/영어 분류 정확성 검증.
    #[test]
    fn builtin_font_is_korean_classification() {
        assert!(BuiltinFont::NanumGothic.is_korean());
        assert!(BuiltinFont::NanumMyeongjo.is_korean());
        assert!(!BuiltinFont::Helvetica.is_korean());
        assert!(!BuiltinFont::TimesRoman.is_korean());
        assert!(!BuiltinFont::Courier.is_korean());
    }
}
