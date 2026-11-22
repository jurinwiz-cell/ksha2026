# Task #34 — isTauri bridge 분기 완료 보고서

**Issue**: v0.5 #62
**브랜치**: `local/task34`
**머지 커밋**: (PR 생성 후 기입)
**완료일**: 2026-05-20
**소요 시간**: 계획 1세션 / 실제 1세션

## 완료된 작업

- [x] `packages/ui/src/types/pdf.ts` — `media_box` → `| null` (Rust Option 호환)
- [x] `packages/desktop/src/core/tauri-client.ts` 신규 — Tauri invoke wrapper + `_lastResult` 캐시
- [x] `packages/desktop/src/main.tsx` — `isTauri()` 동적 import 분기
- [x] `pnpm --filter @rpdf/ui typecheck` 통과
- [x] `pnpm --filter @rpdf/desktop typecheck` 통과
- [x] Tauri 창 PDF 드래그앤드롭 → 페이지 썸네일 표시 확인 (수동)

## 실제 변경 사항

### 새로 추가된 파일
- `packages/desktop/src/core/tauri-client.ts` (54줄) — Tauri commands invoke wrapper

### 수정된 파일
- `packages/ui/src/types/pdf.ts` — `media_box: [...] | null` (1줄 수정)
- `packages/desktop/src/main.tsx` — 정적 import 제거 → `isTauri()` 분기 + 동적 import

### 삭제된 파일
- 없음

## 계획 대비 달라진 점

### D1: 동적 import (계획서 수정 반영)
계획서 초안은 정적 import였으나 리뷰(plan-eng-review)에서 결정:
- Tauri 환경에서 WASM Worker가 정적 import 시 불필요하게 생성됨
- 동적 import(`await import(...)`)로 변경해 Tauri 경로에서 WASM 미로드

### D2: Uint8Array 직접 전달 (계획서 수정 반영)
계획서 초안은 `Array.from(bytes)` MVP였으나:
- Tauri 2 IPC가 네이티브로 Uint8Array → Vec<u8> 변환 지원
- `Array.from` 불필요한 복사 제거

## 발견된 이슈

- **브라우저 WASM init 실패 (기존 버그)**: Vite dev 서버가 `rpdf_wasm_bg.wasm`을 HTML로 서빙 (`3c21646f` = `<!doctype`). Task #34 이전부터 존재, git stash 테스트로 확인. Tauri 경로는 WASM 미사용이므로 영향 없음. 별도 이슈 등록 검토.

## 배운 점

### 기술적
- Tauri 2 IPC는 `Uint8Array`를 직접 `Vec<u8>`로 직렬화 지원 — `Array.from()` 변환 불필요
- `isTauri()` from `@tauri-apps/api/core`가 브라우저에서 정상적으로 `false` 반환 (Tauri 2 표준)
- `Parameters<typeof initStore>[0]` 패턴으로 bridge 타입을 store에서 직접 추론 가능

### 프로세스
- plan-eng-review 단계에서 D1(동적 import)/D2(Uint8Array) 결정을 미리 확정해 구현 중 재결정 없음
- 브라우저 DevTools로 Tauri 웹뷰 직접 접근 불가 — 수동 테스트 필수 (Tauri 웹뷰는 네이티브 창)

## 테스트 결과

- `pnpm --filter @rpdf/ui typecheck`: 통과
- `pnpm --filter @rpdf/desktop typecheck`: 통과
- Tauri 창 수동 검증: PDF 드래그앤드롭 → 썸네일 표시 ✅

## 회고 분류 표

| 항목 | 분류 | 내용 |
|------|------|------|
| Tauri 2 IPC Uint8Array 직접 지원 | B | 트러블슈팅 문서 — `mydocs/troubleshootings/tauri-ipc-uint8array.md` 작성 검토 |
| 동적 import로 WASM Worker 미로드 패턴 | A | CLAUDE.md Tauri 개발 패턴에 추가 |
| Tauri 웹뷰 DevTools 직접 접근 불가 | B | 트러블슈팅 문서 — Tauri 수동 검증 방법 |
| 브라우저 WASM Vite 서빙 이슈 | C | 완료 보고서 메모 (별도 이슈) |

## 다음 관련 작업

- **Task #35**: 네이티브 파일 다이얼로그 (`open_pdf_path`)

## 참고 자료

- 계획서: `mydocs/plans/task34-tauri-bridge.md`
- PR: (생성 후 기입)
