# 트러블슈팅 — lopdf `insert_image` API는 새 문서 생성에 사용 불가

**발생일**: 2026-05-20
**해결일**: 2026-05-20
**관련 Issue**: #66
**심각도**: 중간
**환경**: lopdf 0.40 / Rust 1.86 / rpdf-desktop

## 증상

계획서에서 `doc.insert_image(page_id, img_stream, position, size)`로 이미지를 삽입하려 했으나,
새 문서를 직접 구성하는 흐름에서 API가 기대대로 동작하지 않는다.

- `insert_image`는 이미 존재하는 페이지에 이미지를 삽입하는 전용 API
- 새 `Document`에 빈 페이지를 추가하고 `insert_image`를 호출하면 페이지 구조와 XObject 등록이 맞지 않아 유효하지 않은 PDF 생성 가능

## 재현 방법

```rust
// 잘못된 접근 — 새 문서에서 insert_image 시도
let mut doc = Document::with_version("1.5");
let pages_id = doc.new_object_id();
let page_id = doc.add_object(dictionary!{ "Type" => "Page", ... });
doc.insert_image(page_id, img_stream, (0.0, 0.0), (595.0, 842.0))?; // 문서 구조 불일치
```

## 원인 분석

### 최종 원인

`lopdf::Document::insert_image`는 **기존 완성된 PDF 문서에서 특정 페이지에 이미지를 추가**하는 API다.
내부적으로 페이지의 기존 Resources와 Contents를 수정하는 방식으로 동작하며,
새 문서를 빌드업하는 과정에서 호출하면 아직 구성이 완료되지 않은 구조와 충돌한다.

**관련 코드 위치**: lopdf 0.40 `document.rs` — `insert_image` 메서드

## 해결책

### 적용한 수정

`lopdf::Content` + `Operation`으로 `q/cm/Do/Q` 스트림을 직접 구성하고,
`xobject::image_from(jpeg_bytes)`로 XObject를 생성해 Resources에 수동 등록한다.

```rust
// 1. JPEG bytes → XObject Stream
let img_stream = xobject::image_from(jpeg_bytes).map_err(|e| e.to_string())?;
let img_id = doc.add_object(img_stream);
let img_name = format!("X{}", img_id.0);

// 2. Content stream 직접 구성 (q / cm / Do / Q)
let content = Content {
    operations: vec![
        Operation::new("q", vec![]),
        Operation::new("cm", vec![fit_w.into(), 0.into(), 0.into(), fit_h.into(), offset_x.into(), offset_y.into()]),
        Operation::new("Do", vec![Object::Name(img_name.as_bytes().to_vec())]),
        Operation::new("Q", vec![]),
    ],
};
let content_id = doc.add_object(Stream::new(dictionary!{}, content.encode()?));

// 3. 페이지에 Resources.XObject 등록
let page_id = doc.add_object(dictionary!{
    "Type" => "Page",
    "Parent" => pages_id,
    "MediaBox" => vec![0.into(), 0.into(), 595.into(), 842.into()],
    "Contents" => content_id,
    "Resources" => dictionary!{
        "XObject" => dictionary!{ img_name.as_bytes() => img_id }
    },
});
```

### 필요한 Cargo.toml feature

`xobject::image_from`은 `embed_image` feature 활성화 필요:

```toml
lopdf = { version = "0.40", features = ["embed_image"] }
```

workspace에 `lopdf = "0.40"`으로만 선언되어 있다면 멤버 크레이트에서 독립 선언한다
(→ `mydocs/troubleshootings/` 참조: Cargo workspace features 확장 패턴은 CLAUDE.md §도구활용원칙).

## 재발 방지

- lopdf로 **새 문서를 빌드**할 때는 `insert_image`가 아닌 Content stream 직접 구성을 사용한다.
- `insert_image`는 기존 PDF에 페이지를 수정(annotate)할 때만 사용한다.

## 배운 점

- `xobject::image_from(jpeg_bytes)` API는 JPEG bytes를 직접 받아 XObject Stream을 생성해주어 편리
- Content stream `q/cm/Do/Q` 패턴은 PDF 스펙 표준 방식 — lopdf 없이도 이해 가능
- lopdf `insert_image`의 사용 범위는 docs.rs 설명만으로는 명확하지 않아 실제 코드 레벨 확인 필요

## 참고 자료

- lopdf 0.40 docs.rs: `Document::insert_image`
- ISO 32000-1:2008 §8.8 (XObjects), §9.4.1 (Content Streams)
- 관련 PR: #67 (Task #36)
