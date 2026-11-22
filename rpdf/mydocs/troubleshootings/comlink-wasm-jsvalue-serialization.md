# 트러블슈팅 — Comlink Worker에서 WASM JsValue 직렬화

**발생일**: 2026-05-21  
**해결일**: 2026-05-21  
**관련 Issue**: #77 (Task #41)  
**심각도**: 중간  
**환경**: wasm-pack 0.13 / Comlink 4.x / rpdf-wasm

## 증상

WASM 함수(`get_text_blocks`, `replace_text`)가 `JsValue`를 반환할 때,
Comlink Worker를 통해 메인 스레드로 전달하면 값이 비어 있거나 타입이 맞지 않는다.

```typescript
// wasm-worker.ts
const result = doc.get_text_blocks(pageIndex) // JsValue (Array)
return result // ← Comlink가 JsValue를 직렬화하지 못해 빈 객체 반환
```

## 원인 분석

Comlink는 `postMessage` 기반으로 동작하며, 전달 가능한 값은
[structured clone algorithm](https://developer.mozilla.org/en-US/docs/Web/API/Web_Workers_API/Structured_clone_algorithm)
을 따른다.

wasm-pack이 생성하는 `JsValue`는 JS 힙 포인터이며 structured clone이 불가능하다.
결과적으로 Worker → 메인 스레드 전송 시 값이 손실되거나 `undefined`로 도착한다.

## 해결책

WASM이 반환한 `JsValue`를 Worker 내부에서 즉시 `JSON.stringify → JSON.parse`로
일반 JS 값으로 변환한 뒤 Comlink로 반환한다.

```typescript
// 잘못된 패턴 — JsValue 그대로 반환
async getTextBlocks(pageIndex: number): Promise<TextBlock[]> {
  return doc.get_text_blocks(pageIndex) // JsValue, structured clone 불가
}

// 올바른 패턴 — JSON 왕복으로 일반 JS 값 변환
async getTextBlocks(pageIndex: number): Promise<TextBlock[]> {
  const result = doc.get_text_blocks(pageIndex)
  return JSON.parse(JSON.stringify(result)) as TextBlock[]
}
```

`replace_text`처럼 `PageInfo[]`를 반환하는 함수도 동일하게 처리한다.

```typescript
async replaceText(...): Promise<PageInfo[]> {
  const result = doc.replace_text(...)
  return JSON.parse(JSON.stringify(result)) as PageInfo[]
}
```

## 재발 방지

- WASM Worker에 새 메서드를 추가할 때 반환 타입이 `JsValue`이면 반드시 위 패턴 적용
- `JsValue`를 직접 반환하면 Comlink 경계에서 손실되므로 Worker 내부에서 변환 완료 후 반환
- WASM 함수 시그니처가 `-> Result<JsValue, JsValue>`이면 `.ok()` 처리 후 JSON 변환

## 배운 점

- wasm-pack `JsValue`는 JS 힙 레퍼런스이므로 Worker 스레드 경계를 넘을 수 없다
- `JSON.stringify → JSON.parse` 왕복이 비효율적으로 보이지만, Worker 내부라 메인 스레드 블로킹 없음
- 향후 wasm-pack이 `serde-wasm-bindgen` 기반으로 직접 typed 반환 시 이 패턴 불필요해질 수 있음

## 참고 자료

- Comlink 공식 문서: structured clone 제약 관련
- wasm-bindgen JsValue: https://rustwasm.github.io/wasm-bindgen/api/wasm_bindgen/struct.JsValue.html
- 관련 PR: Task #41 (local/task41)
