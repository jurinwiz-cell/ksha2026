use rpdf_core::types::{ContentStreamOperator, Page, PdfObject, TextBlock};

/// 문자열 텍스트의 추정 너비를 계산한다.
///
/// - ASCII 범위 문자: `font_size * 0.55`
/// - CJK 및 한글 범위 문자: `font_size * 0.95`
/// - 혼합: 문자별 분류 후 합산
fn estimate_width(text: &str, font_size: f64) -> f64 {
    text.chars()
        .map(|c| {
            let cp = c as u32;
            // 한글 자모·한글 음절·CJK 통합 한자 등 광폭 문자
            let is_wide = matches!(cp,
                0x1100..=0x11FF   // 한글 자모
                | 0x2E80..=0x2EFF // CJK 부수 보충
                | 0x3000..=0x9FFF // CJK 통합 한자 (넓은 범위)
                | 0xA000..=0xA4CF // 彝 문자
                | 0xAC00..=0xD7AF // 한글 음절
                | 0xF900..=0xFAFF // CJK 호환 한자
                | 0xFE30..=0xFE4F // CJK 호환 형태
                | 0xFF00..=0xFFEF // 전각 형식
            );
            if is_wide {
                font_size * 0.95
            } else {
                font_size * 0.55
            }
        })
        .sum()
}

/// `/Resources` `/Font` 딕셔너리에서 `font_ref`에 해당하는 `/BaseFont` 이름을 읽는다.
///
/// 반환값이 `None`이면 Resources 정보가 없거나 해당 폰트 참조가 없음을 뜻한다.
fn lookup_font_family(page: &Page, font_ref: &str) -> Option<String> {
    let resources = page.resources.as_ref()?;
    let font_dict = resources.get(b"Font")?.as_dict()?;
    let font_entry = font_dict.get(font_ref.as_bytes())?.as_dict()?;
    let base_font = font_entry.get(b"BaseFont")?;
    let name_bytes = base_font.as_name_bytes()?;
    // BaseFont 이름은 bytes → UTF-8 lossy 변환
    Some(String::from_utf8_lossy(name_bytes).into_owned())
}

/// `Page` content stream을 순회해 텍스트 블록 목록을 추출한다.
///
/// 각 `Tj` / `TJ` 연산자 하나당 `TextBlock` 하나(또는 여러 개)가 생성된다.
/// 좌표는 PDF 좌표계(좌하단 원점, pt 단위)를 사용한다.
///
/// # 방어적 동작
///
/// - `BT`(BeginText) 없이 `ShowText`가 등장해도 블록을 생성한다.
/// - `SetFont` 없이 `ShowText`가 등장하면 font_ref = "", font_size = 0.0 으로 기록한다.
pub fn extract_text_blocks(page: &Page) -> Vec<TextBlock> {
    let mut blocks: Vec<TextBlock> = Vec::new();

    // 텍스트 상태
    let mut tx: f64 = 0.0;
    let mut ty: f64 = 0.0;
    let mut current_font_ref: String = String::new();
    let mut current_font_size: f64 = 0.0;
    let mut leading: f64 = 0.0;

    for op in &page.content {
        match &op.operator {
            ContentStreamOperator::BeginText => {
                tx = 0.0;
                ty = 0.0;
            }
            ContentStreamOperator::EndText => {
                // 텍스트 객체 종료 — 특별 처리 없음
            }
            ContentStreamOperator::SetFont => {
                // 피연산자: [Name(font_ref), Real/Integer(size)]
                if let Some(name_obj) = op.operands.first()
                    && let Some(name_bytes) = name_obj.as_name_bytes()
                {
                    current_font_ref = String::from_utf8_lossy(name_bytes).into_owned();
                }
                if let Some(size_val) = extract_f64_from_operands(&op.operands, 1) {
                    current_font_size = size_val;
                    leading = size_val;
                }
            }
            ContentStreamOperator::SetLeading => {
                if let Some(val) = extract_f64_from_operands(&op.operands, 0) {
                    leading = val;
                }
            }
            ContentStreamOperator::SetTextMatrix => {
                // 피연산자: [a, b, c, d, e, f] — tx=e, ty=f
                if let (Some(e), Some(f)) = (
                    extract_f64_from_operands(&op.operands, 4),
                    extract_f64_from_operands(&op.operands, 5),
                ) {
                    tx = e;
                    ty = f;
                }
            }
            ContentStreamOperator::MoveText => {
                // Td: tx += dx, ty += dy
                if let (Some(dx), Some(dy)) = (
                    extract_f64_from_operands(&op.operands, 0),
                    extract_f64_from_operands(&op.operands, 1),
                ) {
                    tx += dx;
                    ty += dy;
                }
            }
            ContentStreamOperator::MoveTextSetLeading => {
                // TD: tx += dx, ty += dy (leading은 별도 처리)
                if let (Some(dx), Some(dy)) = (
                    extract_f64_from_operands(&op.operands, 0),
                    extract_f64_from_operands(&op.operands, 1),
                ) {
                    tx += dx;
                    ty += dy;
                }
            }
            ContentStreamOperator::MoveToNextLine => {
                // T*: ty -= leading
                ty -= if leading != 0.0 {
                    leading
                } else {
                    current_font_size
                };
            }
            ContentStreamOperator::ShowText => {
                // Tj: 피연산자 첫 번째가 문자열 bytes
                if let Some(bytes) = op.operands.first().and_then(|o| o.as_string_bytes()) {
                    let text = String::from_utf8_lossy(bytes).to_string();
                    let width = estimate_width(&text, current_font_size);
                    let font_family = lookup_font_family(page, &current_font_ref);
                    blocks.push(TextBlock {
                        text,
                        x: tx,
                        y: ty,
                        width,
                        height: current_font_size,
                        font_size: current_font_size,
                        font_ref: current_font_ref.clone(),
                        font_family,
                    });
                }
            }
            ContentStreamOperator::ShowTextAdjusted => {
                // TJ: 피연산자 첫 번째가 Array — 문자열 요소마다 TextBlock 생성
                if let Some(PdfObject::Array(items)) = op.operands.first() {
                    for item in items {
                        if let Some(bytes) = item.as_string_bytes() {
                            let text = String::from_utf8_lossy(bytes).to_string();
                            let width = estimate_width(&text, current_font_size);
                            let font_family = lookup_font_family(page, &current_font_ref);
                            blocks.push(TextBlock {
                                text,
                                x: tx,
                                y: ty,
                                width,
                                height: current_font_size,
                                font_size: current_font_size,
                                font_ref: current_font_ref.clone(),
                                font_family,
                            });
                        }
                        // Integer/Real 피연산자(커닝 값)는 위치 조정 없이 무시
                    }
                }
            }
            // 그 외 연산자는 텍스트 추출 목적상 무시
            _ => {}
        }
    }

    blocks
}

/// `PdfObject` 슬라이스에서 인덱스 위치의 f64 값을 추출한다.
///
/// `Integer` → f64 변환도 지원한다.
fn extract_f64_from_operands(operands: &[PdfObject], index: usize) -> Option<f64> {
    match operands.get(index)? {
        PdfObject::Real(v) => Some(*v),
        PdfObject::Integer(n) => Some(*n as f64),
        _ => None,
    }
}

#[cfg(test)]
mod internal_tests {
    use rpdf_core::types::{ContentStreamOperation, ContentStreamOperator, Page, PdfObject};

    use super::extract_text_blocks;

    /// 테스트용 최소 Page를 생성한다.
    fn make_page(content: Vec<ContentStreamOperation>) -> Page {
        Page {
            index: 0,
            content,
            resources: None,
            media_box: None,
            crop_box: None,
            rotation: 0,
        }
    }

    /// SetTextMatrix(1,0,0,1,e,f) 연산을 생성한다.
    fn set_text_matrix(e: f64, f: f64) -> ContentStreamOperation {
        ContentStreamOperation::new(
            ContentStreamOperator::SetTextMatrix,
            vec![
                PdfObject::Real(1.0),
                PdfObject::Real(0.0),
                PdfObject::Real(0.0),
                PdfObject::Real(1.0),
                PdfObject::Real(e),
                PdfObject::Real(f),
            ],
        )
    }

    /// SetFont(name, size) 연산을 생성한다.
    fn set_font(name: &str, size: f64) -> ContentStreamOperation {
        ContentStreamOperation::new(
            ContentStreamOperator::SetFont,
            vec![
                PdfObject::Name(name.as_bytes().to_vec()),
                PdfObject::Real(size),
            ],
        )
    }

    /// ShowText(text_bytes) 연산을 생성한다.
    fn show_text(text: &str) -> ContentStreamOperation {
        ContentStreamOperation::new(
            ContentStreamOperator::ShowText,
            vec![PdfObject::LiteralString(text.as_bytes().to_vec())],
        )
    }

    // TB-1: SetTextMatrix(1,0,0,1,50,100) + ShowText("Hi") → 블록 1개, x=50.0, y=100.0
    #[test]
    fn tb1_set_text_matrix_then_show_text() {
        let page = make_page(vec![
            set_font("F1", 12.0),
            set_text_matrix(50.0, 100.0),
            show_text("Hi"),
        ]);
        let blocks = extract_text_blocks(&page);
        assert_eq!(blocks.len(), 1);
        assert_eq!(blocks[0].x, 50.0);
        assert_eq!(blocks[0].y, 100.0);
        assert_eq!(blocks[0].text, "Hi");
    }

    // TB-2: SetTextMatrix + MoveText(5,-3) + ShowText → x=55, y=97
    #[test]
    fn tb2_move_text_accumulates() {
        let page = make_page(vec![
            set_font("F1", 12.0),
            set_text_matrix(50.0, 100.0),
            ContentStreamOperation::new(
                ContentStreamOperator::MoveText,
                vec![PdfObject::Real(5.0), PdfObject::Real(-3.0)],
            ),
            show_text("Hello"),
        ]);
        let blocks = extract_text_blocks(&page);
        assert_eq!(blocks.len(), 1);
        assert_eq!(blocks[0].x, 55.0);
        assert_eq!(blocks[0].y, 97.0);
    }

    // TB-3: ShowTextAdjusted([String("AB"), Integer(-50), String("CD")]) → 2개 블록
    #[test]
    fn tb3_show_text_adjusted_produces_multiple_blocks() {
        let page = make_page(vec![
            set_font("F1", 12.0),
            set_text_matrix(0.0, 0.0),
            ContentStreamOperation::new(
                ContentStreamOperator::ShowTextAdjusted,
                vec![PdfObject::Array(vec![
                    PdfObject::LiteralString(b"AB".to_vec()),
                    PdfObject::Integer(-50),
                    PdfObject::LiteralString(b"CD".to_vec()),
                ])],
            ),
        ]);
        let blocks = extract_text_blocks(&page);
        assert_eq!(blocks.len(), 2);
        assert_eq!(blocks[0].text, "AB");
        assert_eq!(blocks[1].text, "CD");
    }

    // TB-4: 한글 "안녕" width > ASCII "AB" width (같은 font_size)
    #[test]
    fn tb4_korean_width_wider_than_ascii() {
        let font_size = 12.0;
        let page_korean = make_page(vec![
            set_font("F1", font_size),
            set_text_matrix(0.0, 0.0),
            ContentStreamOperation::new(
                ContentStreamOperator::ShowText,
                vec![PdfObject::LiteralString("안녕".as_bytes().to_vec())],
            ),
        ]);
        let page_ascii = make_page(vec![
            set_font("F1", font_size),
            set_text_matrix(0.0, 0.0),
            show_text("AB"),
        ]);
        let blocks_korean = extract_text_blocks(&page_korean);
        let blocks_ascii = extract_text_blocks(&page_ascii);
        assert_eq!(blocks_korean.len(), 1);
        assert_eq!(blocks_ascii.len(), 1);
        assert!(
            blocks_korean[0].width > blocks_ascii[0].width,
            "한글 너비({}) > ASCII 너비({}) 기대",
            blocks_korean[0].width,
            blocks_ascii[0].width,
        );
    }

    // TB-5: content = [] → empty vec
    #[test]
    fn tb5_empty_content_returns_empty() {
        let page = make_page(vec![]);
        let blocks = extract_text_blocks(&page);
        assert!(blocks.is_empty());
    }

    // TB-6: BeginText 없이 ShowText → 블록 생성되어야 함 (방어적)
    #[test]
    fn tb6_show_text_without_begin_text_still_produces_block() {
        let page = make_page(vec![
            set_font("F1", 10.0),
            set_text_matrix(0.0, 0.0),
            show_text("test"),
        ]);
        let blocks = extract_text_blocks(&page);
        assert_eq!(blocks.len(), 1);
        assert_eq!(blocks[0].text, "test");
    }
}
