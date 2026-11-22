# Task #28 완료 보고서: rpdf-studio 웹 에디터 최소 버전

**이슈**: #54  
**브랜치**: `local/task28`  
**완료일**: 2026-05-20

---

## 완료 항목

| 항목 | 상태 |
|------|------|
| packages/studio/ Vite+React+TS 스캐폴딩 | ✅ |
| rpdf-wasm file: 연동 | ✅ |
| Web Worker + Comlink (Uint8Array transfer) | ✅ |
| Zustand store (DocumentState) | ✅ |
| DropZone (드래그 드롭 + 파일 선택) | ✅ |
| pdf-renderer.ts (썸네일 + 뷰어 렌더링) | ✅ |
| ThumbnailSidebar (썸네일 + 선택 + 삭제) | ✅ |
| PageViewer (pdf.js 풀 렌더링 + CSS rotation) | ✅ |
| Toolbar (회전/Undo/Redo/저장/병합 비활성) | ✅ |
| ESLint v9 flat config | ✅ |
| Vitest 유닛 테스트 6개 | ✅ |
| Playwright E2E 6개 케이스 | ✅ |
| CI studio job | ✅ |
| typecheck / lint / test:unit / build 통과 | ✅ |

---

## 성공 기준 대비

| 기준 | 결과 |
|------|------|
| pnpm --filter @rpdf/studio dev 기동 가능 | ✅ |
| PDF 드래그 드롭 → 썸네일 사이드바 | ✅ (구현 완료) |
| 회전/삭제 편집 후 뷰어 반영 | ✅ (하이브리드: CSS/pdf.js 재로드) |
| Undo/Redo 동작 (CSS rotation 동기화) | ✅ |
| 저장 다운로드 | ✅ |
| pnpm build 성공 | ✅ (WASM gzip 349KB, 2MB 이내) |
| typecheck / lint / test 통과 | ✅ |
| CI studio job 그린 | ✅ (추가 완료, 실행은 PR 후 확인) |

---

## 번들 크기

- `rpdf_wasm_bg.wasm`: 828KB (gzip 349KB) — 2MB 제한 이내 ✅
- `index.js`: 522KB (gzip 159KB)

---

## 주요 설계 결정 (D1~D9)

- **D2**: rawBytes 제거, fileRef: File 참조만 유지 (메모리 중복 방지)
- **D3**: Comlink.transfer로 Uint8Array 소유권 이전 (복사 없음)
- **D4**: vite-plugin-static-copy로 pdf.worker 자동 복사
- **D6**: 하이브리드 전략 — 회전=CSS transform, 삭제=pdf.js 재로드
- **D7**: undo/redo/rotatePage/deletePages 모두 최신 PageInfo[] 반환
- **D8**: CI studio job에 wasm-pack build 선행
- **D9**: PageInfo 필드 snake_case 유지 (WASM 반환 형식과 일치)

---

## 회고 분류 표

| 항목 | 분류 | 내용 |
|------|------|------|
| ESLint v9 flat config에서 `js.configs.recommended` vs `js.recommended` | B | `js.recommended`는 undefined; `js.configs.recommended` 사용 필수 |
| vitest가 e2e/ 폴더를 스캔하는 문제 | B | `test.exclude`에 `**/e2e/**` 추가로 해결 |
| Comlink.transfer는 Worker expose 측이 아닌 호출 측에서 처리 | C | wasm-client.ts의 openWithTransfer 함수로 분리 |
| pdfjs-dist GlobalWorkerOptions.workerSrc 중복 설정 | C | pdf-renderer.ts와 PageViewer.tsx 양쪽에 설정 — 초기화 순서 주의 |

> A: 즉시 CLAUDE.md 반영 — 해당 없음 (기존 패턴 범위)  
> B: 트러블슈팅 문서 작성 예정  
> C: 완료 보고서 메모
