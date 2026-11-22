use std::path::Path;

use rpdf_edit::commands::{
    AnnotationParams, AnnotationType, Command, DeletePagesCommand, MergeCommand, RotatePageCommand,
    add_annotation, get_annotations,
};
use rpdf_serializer::{PageSource, load_document_tracked, serialize_document};

/// 테스트 픽스처 파일을 읽어 바이트로 반환한다.
fn fixture(name: &str) -> Vec<u8> {
    let path = Path::new(env!("CARGO_MANIFEST_DIR"))
        .join("../../examples")
        .join(name);
    std::fs::read(&path).unwrap_or_else(|e| panic!("fixture {} 읽기 실패: {}", name, e))
}

fn highlight_params(page_index: usize) -> AnnotationParams {
    AnnotationParams {
        page_index,
        annot_type: AnnotationType::Highlight,
        rect: [50.0, 700.0, 200.0, 720.0],
        color: [1.0, 1.0, 0.0],
        content: None,
        opacity: 0.3,
    }
}

fn underline_params(page_index: usize) -> AnnotationParams {
    AnnotationParams {
        page_index,
        annot_type: AnnotationType::Underline,
        rect: [50.0, 680.0, 200.0, 695.0],
        color: [0.0, 0.0, 0.8],
        content: None,
        opacity: 1.0,
    }
}

fn sticky_params(page_index: usize, text: &str) -> AnnotationParams {
    AnnotationParams {
        page_index,
        annot_type: AnnotationType::StickyNote,
        rect: [100.0, 500.0, 120.0, 520.0],
        color: [1.0, 1.0, 0.0],
        content: Some(text.to_string()),
        opacity: 1.0,
    }
}

/// bytes → serialize_document (전 페이지 유지) → 결과 bytes
fn serialize_all_pages(data: &[u8]) -> Vec<u8> {
    let (doc, sources) = load_document_tracked(data).unwrap();
    serialize_document(&doc, &sources).unwrap()
}

// ── RT-1: 단일 소스 — Highlight 보존 ──────────────────────────

#[test]
fn rt_1_single_source_preserves_highlight() {
    let bytes = fixture("pdfjs-basicapi.pdf");
    let new_bytes = add_annotation(&bytes, &highlight_params(0)).unwrap();
    let saved = serialize_all_pages(&new_bytes);
    let annots = get_annotations(&saved, 0).unwrap();

    assert_eq!(annots.len(), 1);
    assert_eq!(annots[0].annot_type, AnnotationType::Highlight);
    assert_eq!(annots[0].color, [1.0, 1.0, 0.0]);
    assert!((annots[0].rect[0] - 50.0).abs() < 0.5);
    assert!((annots[0].rect[1] - 700.0).abs() < 0.5);
    assert!((annots[0].rect[2] - 200.0).abs() < 0.5);
    assert!((annots[0].rect[3] - 720.0).abs() < 0.5);
}

// ── RT-2: 단일 소스 — Underline 보존 ─────────────────────────

#[test]
fn rt_2_single_source_preserves_underline() {
    let bytes = fixture("pdfjs-basicapi.pdf");
    let new_bytes = add_annotation(&bytes, &underline_params(0)).unwrap();
    let saved = serialize_all_pages(&new_bytes);
    let annots = get_annotations(&saved, 0).unwrap();

    assert_eq!(annots.len(), 1);
    assert_eq!(annots[0].annot_type, AnnotationType::Underline);
}

// ── RT-3: 단일 소스 — StickyNote 보존 ────────────────────────

#[test]
fn rt_3_single_source_preserves_sticky_note() {
    let bytes = fixture("pdfjs-basicapi.pdf");
    let new_bytes = add_annotation(&bytes, &sticky_params(0, "테스트 메모")).unwrap();
    let saved = serialize_all_pages(&new_bytes);
    let annots = get_annotations(&saved, 0).unwrap();

    assert_eq!(annots.len(), 1);
    assert_eq!(annots[0].annot_type, AnnotationType::StickyNote);
    assert_eq!(annots[0].content, Some("테스트 메모".to_string()));
}

// ── RT-4: 다른 페이지 삭제 후 Annotation 보존 ────────────────

#[test]
fn rt_4_single_source_annotation_survives_delete_other_page() {
    // pdfjs-basicapi.pdf: 3페이지
    let bytes = fixture("pdfjs-basicapi.pdf");
    let annotated_bytes = add_annotation(&bytes, &highlight_params(0)).unwrap();

    let (mut doc, mut sources) = load_document_tracked(&annotated_bytes).unwrap();

    // 0-based index 1 삭제
    let cmd = DeletePagesCommand::new(vec![1]);
    cmd.execute(&mut doc).unwrap();

    // sources 수동 동기화
    sources.remove(1);

    let saved = serialize_document(&doc, &sources).unwrap();
    let annots = get_annotations(&saved, 0).unwrap();

    assert_eq!(annots.len(), 1);
    assert_eq!(annots[0].annot_type, AnnotationType::Highlight);
}

// ── RT-5: 복수 Annotation 보존 ───────────────────────────────

#[test]
fn rt_5_single_source_multiple_annotations_preserved() {
    let bytes = fixture("pdfjs-basicapi.pdf");

    // page 0에 3개 순차 추가: highlight → underline → sticky
    let b1 = add_annotation(&bytes, &highlight_params(0)).unwrap();
    let b2 = add_annotation(&b1, &underline_params(0)).unwrap();
    let b3 = add_annotation(&b2, &sticky_params(0, "메모")).unwrap();

    let saved = serialize_all_pages(&b3);
    let annots = get_annotations(&saved, 0).unwrap();

    assert_eq!(annots.len(), 3);

    let types: Vec<&AnnotationType> = annots.iter().map(|a| &a.annot_type).collect();
    assert!(types.contains(&&AnnotationType::Highlight));
    assert!(types.contains(&&AnnotationType::Underline));
    assert!(types.contains(&&AnnotationType::StickyNote));
}

// ── RT-6: 회전 후 Annotation 보존 ────────────────────────────

#[test]
fn rt_6_single_source_after_rotation_annotation_preserved() {
    let bytes = fixture("pdfjs-basicapi.pdf");
    let annotated_bytes = add_annotation(&bytes, &highlight_params(0)).unwrap();

    let (mut doc, sources) = load_document_tracked(&annotated_bytes).unwrap();

    let cmd = RotatePageCommand::new(0, 90);
    cmd.execute(&mut doc).unwrap();

    let saved = serialize_document(&doc, &sources).unwrap();
    let annots = get_annotations(&saved, 0).unwrap();

    assert_eq!(annots.len(), 1);
    assert_eq!(annots[0].annot_type, AnnotationType::Highlight);
}

// ── RT-M1: 다중 소스 Merge 후 Annotation 보존 ────────────────

#[test]
fn rt_m1_multi_source_annotation_preserved_after_merge() {
    let bytes_a = fixture("pdfjs-basicapi.pdf");
    let annotated_a = add_annotation(&bytes_a, &highlight_params(0)).unwrap();
    let bytes_b = fixture("fw4-2024.pdf");

    let (mut doc_a, sources_a) = load_document_tracked(&annotated_a).unwrap();
    let (doc_b, sources_b) = load_document_tracked(&bytes_b).unwrap();

    // MergeCommand: sources Vec<Document>를 받음
    let cmd = MergeCommand::new(vec![doc_b]);
    cmd.execute(&mut doc_a).unwrap();

    // sources 병합: a_sources + b_sources
    let merged_sources: Vec<PageSource> = sources_a.into_iter().chain(sources_b).collect();

    let saved = serialize_document(&doc_a, &merged_sources).unwrap();
    let annots = get_annotations(&saved, 0).unwrap();

    assert_eq!(annots.len(), 1);
    assert_eq!(annots[0].annot_type, AnnotationType::Highlight);
}
