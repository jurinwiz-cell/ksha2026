# 트러블슈팅 — lopdf `/Annots` 간접 참조 (Object::Reference) 처리

**발생일**: 2026-05-21
**해결일**: 2026-05-21
**관련 Issue**: #82
**심각도**: 중간
**환경**: lopdf 0.40 / Rust 1.78

## 증상

PDF 페이지에 주석을 추가하는 코드에서 `/Annots` 배열을 inline `Object::Array`로만 처리하면,
실제 PDF 파일에서 `/Annots`가 간접 참조(`Object::Reference`)로 저장된 경우 동작하지 않는다.

- 증상: 주석이 저장은 됐지만 PDF 뷰어에서 표시되지 않음
- 증상: `get_annotations` 호출 시 추가한 주석이 조회되지 않음
- 에러 메시지 없음 — silent failure

## 재현 방법

`add_annotation`에서 `/Annots`를 아래처럼 inline Array만 처리할 경우:

```rust
// ❌ 잘못된 패턴 — inline Array만 처리
let annots_obj = page_dict.get_mut(b"Annots")?;
if let Object::Array(arr) = annots_obj {
    arr.push(Object::Reference(annot_id));
}
// Object::Reference인 경우 조용히 무시됨
```

간접 참조 방식으로 `/Annots`를 저장한 PDF에 주석을 추가하면 아무 효과 없이 종료된다.

## 원인 분석

### PDF 스펙
PDF ISO 32000에서 `/Annots` 값은 두 가지 형태가 허용된다:
1. **inline Array**: `[ ref1 ref2 ... ]` — `/Annots` 값이 배열 자체
2. **indirect reference**: `/Annots 42 0 R` — 별도 객체(배열)를 가리키는 참조

lopdf `Document`에서 `page_dict.get_mut(b"Annots")`는 딕셔너리의 raw 값을 반환한다.
값이 `Object::Reference`인 경우 자동 역참조가 일어나지 않으므로, 반드시 수동으로 분기 처리해야 한다.

### borrow checker 문제
`doc`의 불변 참조(`get`)와 가변 참조(`get_object_mut`)를 같은 scope에서 사용할 수 없다.
`Object::Reference` 분기에서 `ref_id`를 먼저 scope block으로 추출한 뒤, 불변 참조를 해제하고 가변 참조를 획득해야 한다.

## 해결책

### 적용한 수정

```rust
// ✅ 올바른 패턴 — inline Array / indirect Reference 모두 처리
fn insert_annot_ref(
    doc: &mut Document,
    page_dict: &mut lopdf::Dictionary,
    annot_id: ObjectId,
) -> Result<(), AnnotationError> {
    if page_dict.has(b"Annots") {
        // ref_id를 scope block으로 먼저 추출 (borrow checker)
        let indirect_ref_id: Option<ObjectId> = {
            match page_dict.get(b"Annots").ok() {
                Some(Object::Reference(r)) => Some(*r),
                _ => None,
            }
        };

        if let Some(ref_id) = indirect_ref_id {
            // indirect reference — 외부 배열 객체에 push
            let arr = doc
                .get_object_mut(ref_id)
                .map_err(|e| AnnotationError::LopdfError(e))?
                .as_array_mut()
                .map_err(|e| AnnotationError::LopdfError(e))?;
            arr.push(Object::Reference(annot_id));
        } else {
            // inline Array — 직접 push
            let arr = page_dict
                .get_mut(b"Annots")
                .map_err(|e| AnnotationError::LopdfError(e))?
                .as_array_mut()
                .map_err(|e| AnnotationError::LopdfError(e))?;
            arr.push(Object::Reference(annot_id));
        }
    } else {
        // /Annots 없음 — 새 inline Array 생성
        page_dict.set(b"Annots", Object::Array(vec![Object::Reference(annot_id)]));
    }
    Ok(())
}
```

`delete_annotation`의 `retain` 처리도 동일 패턴으로 작성한다.

### 테스트 추가

```rust
#[test]
fn an_e4_annots_as_indirect_reference() {
    // /Annots를 indirect Object::Reference로 저장한 PDF에서도 add/get 동작 확인
    let doc = make_doc_with_indirect_annots();
    let bytes = serialize_doc(doc);
    let params = AddAnnotationParams { /* ... */ };
    let (new_bytes, annots) = add_annotation(&bytes, params).unwrap();
    assert_eq!(annots.len(), 1);
}
```

## 재발 방지

- lopdf로 PDF 딕셔너리 값을 읽을 때, `/Annots`·`/Kids`·`/Resources` 등 배열일 수 있는 필드는 항상 `Object::Reference` 케이스를 고려한다
- 계획서 단계에서 "간접 참조 처리 시나리오"를 명시하면 구현 시 누락을 방지할 수 있다 (Task #43 D3 결정으로 계획서에 반영됨)

## 참고 자료

- ISO 32000-1:2008 §12.5 (Annotations)
- lopdf 0.40 `Document::get_object_mut` API
- 관련 PR: #83
