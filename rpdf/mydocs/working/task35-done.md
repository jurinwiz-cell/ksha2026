# Task #35 — 네이티브 파일 다이얼로그 완료 보고서

**Issue**: v0.5 #64
**브랜치**: `local/task35`
**머지 커밋**: (PR 생성 후 기입)
**완료일**: 2026-05-20
**소요 시간**: 계획 1세션 / 실제 1세션

## 완료된 작업

- [x] `tauri-plugin-dialog v2.7.1`, `tauri-plugin-fs v2.5.1` Rust/JS 의존성 추가
- [x] `packages/desktop/src-tauri/src/lib.rs` — 플러그인 등록
- [x] `packages/desktop/src-tauri/capabilities/default.json` 신규 생성 (권한 설정)
- [x] `packages/desktop/src/core/tauri-dialog.ts` 신규 생성
- [x] `packages/desktop/src/App.tsx` — "파일 열기" 버튼 추가
- [x] `cargo check -p rpdf-desktop` 통과
- [x] `pnpm --filter @rpdf/desktop typecheck` 통과

## 실제 변경 사항

### 새로 추가된 파일
- `packages/desktop/src/core/tauri-dialog.ts` — `openPdfWithNativeDialog()` 함수
- `packages/desktop/src-tauri/capabilities/default.json` — Tauri 2 권한 설정

### 수정된 파일
- `packages/desktop/src-tauri/Cargo.toml` — `tauri-plugin-dialog = "2.7.1"`, `tauri-plugin-fs = "2.5.1"` 추가
- `packages/desktop/src-tauri/src/lib.rs` — `.plugin(tauri_plugin_dialog::init())`, `.plugin(tauri_plugin_fs::init())` 추가
- `packages/desktop/src/App.tsx` — `isTauri() &&` 조건부 "파일 열기" 버튼 추가

### 삭제된 파일
- 없음

## 계획 대비 달라진 점

### D1: 범위 결정 — DropZone 클릭 불가 확인 (plan-eng-review 중 테스트)
Tauri WKWebView에서 `<input type="file">` (DropZone 클릭 핸들러)가 동작하지 않음을 직접 확인.
→ `tauri-plugin-dialog` 플러그인 필수. 원래 계획 그대로 진행.

### TypeScript 체크 단순화 (Gemini 외부 리뷰 반영)
계획서 초안: `!path || typeof path !== 'string'`
수정: `if (!path) return` — `open({ multiple: false })`의 정확한 반환 타입이 `string | null`이므로 단순화.

## 발견된 이슈

### Tauri WKWebView에서 `<input type="file">` 미동작
- 브라우저에서 작동하는 DropZone 클릭(파일 선택)이 WKWebView에서 열리지 않음
- 원인: WKWebView가 `<input type="file">`을 통한 시스템 파일 대화상자를 열지 않는 것으로 추정
- 해결: `tauri-plugin-dialog`의 OS 네이티브 다이얼로그 사용 (이번 Task의 목적)
- 트러블슈팅 문서 후보 (B)

### `readFile` 실패 시 unhandled rejection
- `openPdfWithNativeDialog` 내부의 `readFile` 실패가 store error 처리 이전에 발생하면 unhandled rejection이 됨
- 영향: console 에러 로그, UI 피드백 없음
- 현재 수준에서 허용 가능 (로컬 파일 읽기 실패 빈도 낮음), v0.5 이후 개선 검토

## 배운 점

### 기술적
- Tauri WKWebView는 `<input type="file">` 파일 선택 다이얼로그를 지원하지 않음 — 네이티브 다이얼로그 플러그인 필수
- Tauri 2 capabilities 시스템: `dialog:allow-open` + `fs:allow-read-file` + `fs:scope-home-recursive` 조합 필요
- `@tauri-apps/plugin-dialog`의 `open({ multiple: false })` 반환 타입은 `string | null` (배열 없음)
- `tauri-dialog.ts` 정적 import는 CLAUDE.md 동적 import 규칙 대상 아님 — 규칙의 핵심은 WASM Worker 생성 방지

### 프로세스
- plan-eng-review 중 D1 scope test로 `<input type="file">` 동작 여부를 먼저 확인 → 범위 과대/과소 설계 방지
- Gemini 외부 리뷰가 `typeof path !== 'string'` 불필요 조건을 잡아냄

## 테스트 결과

- `cargo check -p rpdf-desktop`: 통과
- `pnpm --filter @rpdf/desktop typecheck`: 통과
- Tauri 창 수동 검증: (체크포인트 C — PR 후 실행 필요)

## 회고 분류 표

| 항목 | 분류 | 내용 |
|------|------|------|
| WKWebView에서 `<input type="file">` 미동작 | **B** | 트러블슈팅 문서 — `tauri-wkwebview-file-input.md` |
| `open({ multiple: false })` 반환 타입 단순화 | **C** | 타입 정의에서 직접 확인 가능, 별도 문서 불필요 |
| `readFile` 실패 unhandled rejection | **C** | 완료 보고서 메모, 범용 패턴 아님 |
| Tauri 2 capabilities 권한 조합 패턴 | **A** | CLAUDE.md Tauri 개발 패턴에 추가 |

## 다음 관련 작업

- **체크포인트 C**: Tauri dev 서버에서 "파일 열기" 버튼 → 다이얼로그 → PDF 선택 → 썸네일 수동 확인
- **다음 Task**: TBD (v0.5 관련 이슈 목록 확인)

## 참고 자료

- 계획서: `mydocs/plans/task35-native-file-dialog.md`
- 선행 Task #34 완료 보고서: `mydocs/working/task34-done.md`
- PR: (생성 후 기입)
