/// PDF 페이지에서 추출한 텍스트 블록 (ISO 32000 §9 — Text).
///
/// content stream의 `Tj` / `TJ` 연산자 하나당 TextBlock 하나가 생성된다.
/// 위치 좌표는 PDF 좌표계(좌하단 원점, pt 단위)를 사용한다.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub struct TextBlock {
    /// 텍스트 내용 (UTF-8, lossy 변환).
    pub text: String,
    /// 좌하단 x 좌표 (PDF 좌표계, pt).
    pub x: f64,
    /// 좌하단 y 좌표 (PDF 좌표계, pt).
    pub y: f64,
    /// 추정 너비 (pt). 문자폭 계수(ASCII: 0.55, CJK: 0.95)로 근사.
    pub width: f64,
    /// 블록 높이 (pt). `font_size`와 동일.
    pub height: f64,
    /// 현재 폰트 크기 (pt).
    pub font_size: f64,
    /// PDF 내부 폰트 리소스 참조명 (예: `"F1"`).
    pub font_ref: String,
    /// 실제 폰트명 (예: `"NanumGothic"`). `/Resources` 딕셔너리에서 추출.
    /// 해당 정보가 없으면 `None`.
    pub font_family: Option<String>,
}
