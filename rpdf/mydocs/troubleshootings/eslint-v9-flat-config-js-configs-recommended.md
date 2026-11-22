# 트러블슈팅 — ESLint v9 flat config에서 js.recommended가 undefined

**발생일**: 2026-05-20  
**해결일**: 2026-05-20  
**관련 Issue**: #54  
**심각도**: 낮음  
**환경**: ESLint 9.x / @eslint/js 9.x / TypeScript / Vite 6

## 증상

ESLint v9 flat config(`eslint.config.mjs`)에서 `js.recommended`를 사용하면 설정이 조용히 무시되거나 런타임 오류가 발생한다.

```js
// 잘못된 코드
import js from '@eslint/js'
export default [js.recommended, ...]
```

- `pnpm lint` 실행 시 규칙이 전혀 적용되지 않거나 `TypeError: Cannot spread non-iterable` 오류 발생

## 재현 방법

```bash
# eslint.config.mjs 에 아래처럼 작성 후 실행
# import js from '@eslint/js'
# export default tseslint.config(js.recommended, ...)
pnpm eslint src
```

## 원인 분석

### 최종 원인

`@eslint/js` 패키지의 실제 export 구조가 `js.recommended`가 아닌 `js.configs.recommended`이다.

```js
// @eslint/js 실제 export
export default {
  configs: {
    recommended: { rules: { ... } },
    all: { rules: { ... } }
  }
}
```

`js.recommended`는 `undefined`이므로 `tseslint.config(undefined, ...)` 형태가 되어 설정이 무시된다.

## 해결책

```js
// eslint.config.mjs — 올바른 방법
import js from '@eslint/js'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  js.configs.recommended,   // ← recommended가 아닌 configs.recommended
  ...tseslint.configs.recommended,
  {
    rules: { 'no-console': 'warn' }
  }
)
```

## 재발 방지

- ESLint v9 flat config 설정 시 `@eslint/js` 공식 문서에서 `js.configs.recommended` 경로를 확인한다.
- `typescript-eslint`는 `tseslint.configs.recommended` (배열, 스프레드 필요), `@eslint/js`는 `js.configs.recommended` (객체) 형태.

## 참고 자료

- [@eslint/js npm 페이지](https://www.npmjs.com/package/@eslint/js)
- [ESLint v9 flat config 마이그레이션 가이드](https://eslint.org/docs/latest/use/configure/migration-guide)
