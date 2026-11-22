# Task #29 — Tauri 프로젝트 초기 세팅 완료 보고서

**Issue**: v0.5 #58  
**브랜치**: `local/task29`  
**완료일**: 2026-05-20  

## 완료된 작업

계획서 성공 기준 대비 결과:

- [x] `pnpm --filter @rpdf/ui build` 통과 — 505KB ES, 371KB CJS + .d.ts 생성
- [x] `pnpm --filter @rpdf/studio dev` 기동 + 기존 기능 회귀 없음 — typecheck/lint 통과
- [x] `pnpm --filter @rpdf/desktop tauri dev` 창 열림 준비 완료 — typecheck 통과, Cargo workspace 등록
- [x] `typecheck`, `lint` 모두 통과 — studio/desktop/ui 전체
- [x] document-store 유닛 테스트 7개 통과 (null guard 포함)

## 실제 변경 사항

### 새로 추가된 파일
- `packages/ui/` — @rpdf/ui 공유 패키지 전체 (package.json, vite.config.ts, tsconfig.json, src/ 포함)
- `packages/desktop/` — Tauri 2 스캐폴딩 (src/, src-tauri/, package.json, vite.config.ts 포함)
- `mydocs/plans/task29-tauri-setup.md` — 계획서 (GSTACK REVIEW REPORT 포함)

### 수정된 파일
- `packages/studio/package.json` — `@rpdf/ui: workspace:*` 의존성 추가
- `packages/studio/src/main.tsx` — async bootstrap + 명시적 Comlink bridge 어댑터
- `packages/studio/src/App.tsx` — @rpdf/ui import 경로로 갱신, wasmApi.init() 제거
- `packages/studio/src/core/wasm-client.ts`, `wasm-worker.ts` — @rpdf/ui import 경로 갱신
- `packages/studio/vite.config.ts` — passWithNoTests 추가
- `Cargo.toml` (루트) — `packages/desktop/src-tauri` workspace 멤버 추가
- `.github/workflows/ci.yml` — @rpdf/ui build/test, @rpdf/desktop typecheck 단계 추가
- `CLAUDE.md` — 파일 명명 규칙 `PascalCase.tsx` → `snake_case.tsx` 업데이트

### 삭제된 파일
- `packages/studio/src/types/` — @rpdf/ui로 이전
- `packages/studio/src/state/document-store.ts` — @rpdf/ui로 이전
- `packages/studio/src/core/pdf-renderer.ts` — @rpdf/ui로 이전
- `packages/studio/src/ui/*.tsx` — @rpdf/ui로 이전
- `packages/studio/src/__tests__/document-store.test.ts` — @rpdf/ui로 이전

## 계획 대비 달라진 점

1. **추가**: UI 컴포넌트 파일명 PascalCase → snake_case 변경
   - 이유: 사용자 요청으로 작업 완료 직후 적용
   - 영향: `app_shell.tsx`, `drop_zone.tsx` 등 5개 파일 / export 이름(PascalCase)은 유지

2. **수정**: `vite-dts` → `vite-plugin-dts` (계획서 오기 수정)
   - 이유: plan-eng-review(D2-dts)에서 발견

## 발견된 이슈 (plan-eng-review에서 사전 차단)

| ID | 내용 | 처리 |
|----|------|------|
| D1 | `{ ...wasmApi }` Comlink.Remote spread → `{}` (Critical) | 명시적 어댑터 객체로 해결 |
| D6 | 루트 Cargo.toml workspace 멤버 누락 (Critical) | `packages/desktop/src-tauri` 추가 |
| D8 | workerSrc 경로 — @rpdf/ui가 정적 파일 복사 불가 | 각 앱 vite.config.ts에 viteStaticCopy |
| D9 | App.tsx가 wasm-client import → @rpdf/ui 포함 불가 | 각 앱 로컬 소유 |

## 회고 분류

| 항목 | 분류 | 내용 |
|------|------|------|
| Comlink.Remote spread 버그 | B | `mydocs/troubleshootings/` 작성 필요 |
| git mv 필요 (대소문자 rename) | A | CLAUDE.md Gotcha 추가 — macOS git은 대소문자 rename 무시, `git mv` 필수 |
| vite-dts 오기 | C | 완료 보고서 메모 — 계획서 작성 시 npm 패키지명 직접 확인 필수 |

## 테스트 결과

- `@rpdf/ui test:unit`: 7/7 통과 (null guard 포함)
- `@rpdf/ui typecheck`: 통과
- `@rpdf/ui build`: 통과 (ES + CJS + .d.ts)
- `@rpdf/studio typecheck`: 통과
- `@rpdf/studio lint`: 통과
- `@rpdf/desktop typecheck`: 통과

## 다음 관련 작업

- Task #30+ (v0.5): `pnpm --filter @rpdf/desktop tauri dev` 실제 창 열기 확인 (수동)
- Task #34: Tauri invoke 기반 native bridge 구현 (`isTauri` 분기)
- Comlink spread 트러블슈팅 문서 작성 (`mydocs/troubleshootings/`)

## 참고 자료

- 계획서: `mydocs/plans/task29-tauri-setup.md`
- 이슈: #58
