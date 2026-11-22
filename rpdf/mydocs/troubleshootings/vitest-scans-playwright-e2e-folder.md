# 트러블슈팅 — Vitest가 Playwright e2e/ 폴더를 스캔하는 문제

**발생일**: 2026-05-20  
**해결일**: 2026-05-20  
**관련 Issue**: #54  
**심각도**: 낮음  
**환경**: Vitest 2.x / Playwright 1.x / Vite 6 / packages/studio/

## 증상

`packages/studio/e2e/` 폴더에 Playwright 테스트 파일(`*.spec.ts`)을 생성하면 Vitest가 이를 자신의 테스트 파일로 인식하여 실행을 시도한다.

```
Error: Cannot find module '@playwright/test'
    at vitest run
```

또는 Playwright 전용 API(`test`, `expect` from `@playwright/test`)가 Vitest 환경에서 동작하지 않아 실패.

## 재현 방법

1. `packages/studio/e2e/basic.spec.ts` 파일 생성 (Playwright 테스트 포함)
2. `pnpm --filter @rpdf/studio test:unit` 실행

```
FAIL  e2e/basic.spec.ts
Error: [ERR_MODULE_NOT_FOUND] Cannot find package '@playwright/test'
```

## 원인 분석

Vitest는 기본적으로 프로젝트 내 모든 `*.spec.ts`, `*.test.ts` 파일을 수집한다.  
`e2e/` 폴더를 명시적으로 제외하지 않으면 Playwright 파일도 Vitest 실행 대상에 포함된다.

## 해결책

`vite.config.ts`의 `test` 옵션에 `exclude` 추가:

```typescript
// vite.config.ts
export default defineConfig({
  // ...
  test: {
    environment: 'jsdom',
    globals: true,
    exclude: [
      '**/node_modules/**',
      '**/e2e/**'          // Playwright 폴더 제외
    ]
  }
})
```

## 재발 방지

- Vite + Vitest 프로젝트에서 Playwright E2E 폴더(`e2e/`)를 추가할 때 `vite.config.ts`의 `test.exclude`에 `**/e2e/**`를 함께 추가한다.
- `playwright.config.ts`의 `testDir`과 Vitest의 `exclude`가 서로 겹치지 않도록 설정한다.

## 참고 자료

- [Vitest — include/exclude 설정](https://vitest.dev/config/#exclude)
- [Playwright testDir 설정](https://playwright.dev/docs/test-configuration)
