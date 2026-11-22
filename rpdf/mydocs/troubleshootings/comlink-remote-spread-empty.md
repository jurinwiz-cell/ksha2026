# 트러블슈팅 — Comlink.Remote(Proxy) spread 시 빈 객체 반환

**발생일**: 2026-05-20  
**해결일**: 2026-05-20  
**관련 Issue**: #58 (Task #29)  
**심각도**: 높음 (silent fail — 런타임 에러 없이 메서드 호출 전체 유실)  
**환경**: TypeScript / Comlink 4.x / Vite

## 증상

`Comlink.wrap<T>(worker)`로 감싼 worker proxy를 spread해서 객체를 만들면,
모든 메서드가 사라진 빈 객체 `{}`가 반환된다.

```typescript
const wasmApi = Comlink.wrap<WasmApi>(worker)
const bridge = { ...wasmApi, open: customOpen }
// bridge === {} — 메서드 없음

bridge.init()  // TypeError: bridge.init is not a function
```

에러 메시지 없이 `undefined is not a function`으로 터지거나,
`initStore`에 넘긴 객체가 빈 객체라 store 내부에서 나중에야 실패한다.

## 재현 방법

```typescript
import * as Comlink from 'comlink'

const worker = new Worker(new URL('./worker.ts', import.meta.url), { type: 'module' })
const proxy = Comlink.wrap<{ foo(): void }>(worker)

const spread = { ...proxy }
console.log(Object.keys(spread))  // [] — 빈 배열
console.log(typeof spread.foo)    // undefined
```

## 원인 분석

### 최종 원인

`Comlink.wrap<T>(worker)`의 반환값은 ES6 `Proxy` 객체다.  
`{ ...proxy }` (object spread)는 `Object.assign({}, proxy)`와 동일하게 동작하며,
**own enumerable properties**(직접 소유한 열거 가능 속성)만 복사한다.

Proxy 객체는 `get` 트랩으로 메서드 접근을 가로채지만, own properties를 실제로 갖지 않는다.  
따라서 spread 결과는 항상 `{}`.

**왜 TypeScript가 에러를 잡지 못하는가**:  
`{ ...wasmApi, open: customOpen }` 의 타입은 `WasmApi & { open: ... }`로 추론된다.  
Proxy는 구조적으로 `WasmApi`를 만족하므로 타입 검사를 통과하지만,  
런타임에서 spread 복사는 실제 메서드를 담지 못한다.

## 해결책

### 명시적 어댑터 객체 사용

```typescript
// ❌ 잘못된 방식
const bridge = { ...wasmApi, open: openWithTransfer }

// ✅ 올바른 방식 — 각 메서드를 명시적으로 위임
const bridge: WasmApi & { open: (bytes: Uint8Array) => Promise<PageInfo[]> } = {
  init: () => wasmApi.init(),
  open: (bytes) => openWithTransfer(bytes),
  rotatePage: (i, d) => wasmApi.rotatePage(i, d),
  deletePages: (indices) => wasmApi.deletePages(indices),
  undo: () => wasmApi.undo(),
  redo: () => wasmApi.redo(),
  undoLen: () => wasmApi.undoLen(),
  redoLen: () => wasmApi.redoLen(),
  save: () => wasmApi.save(),
}
initStore(bridge)
```

## 재발 방지

- `Comlink.wrap()`의 반환값은 절대 spread(`{ ...proxy }`)하지 않는다.
- CLAUDE.md §도구 활용 원칙에 별도 항목 없음 — 이 문서가 참고 기준.
- 코드 리뷰 시 `{ ...someComlink }` 패턴이 보이면 즉시 지적.

## 배운 점

- ES6 Proxy는 own properties를 갖지 않으므로 spread 대상으로 사용 불가.
- TypeScript의 구조적 타입 시스템은 Proxy의 런타임 동작을 보장하지 않는다.
- Comlink의 공식 문서에는 spread 사용에 대한 경고가 없어 계획서 작성 단계에서 발견 어려움 → plan-eng-review Outside Voice(Claude subagent)가 사전 차단.

## 참고 자료

- Comlink GitHub: https://github.com/GoogleChromeLabs/comlink
- MDN Proxy: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Proxy
- 관련 PR: #59
