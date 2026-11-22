# Task #28: rpdf-studio 웹 에디터 최소 버전

**이슈**: #54  
**브랜치**: `local/task28`  
**마일스톤**: M040 (v0.4 WASM + 웹 에디터)  
**선행 완료**: Task #25 (wasm_api), Task #26 (PDFium 대체), Task #27 (wasm-pack 파이프라인)

---

## 목표

`@rpdf/core` WASM 패키지와 pdf.js를 연동하는 React + Vite 기반 웹 에디터 최소 버전을 구현한다.  
`pnpm dev`로 로컬 기동하고, 파일 열기 → 썸네일 → 편집 → 저장 플로우가 동작하는 것을 목표로 한다.

---

## 현황 파악

| 항목 | 상태 |
|------|------|
| `npm/core/` 빌드 산출물 | ✅ `.wasm`, `.js`, `.d.ts` 존재 |
| `npm/core/package.json` | ❌ 없음 — CI에서 rename 스크립트로 생성 예정 |
| `crates/rpdf-wasm/pkg/` 로컬 빌드 | ✅ `rpdf_wasm.js`, `.wasm`, `.d.ts` 존재 |
| WASM API: `merge` 메서드 | ❌ 없음 — Task #28 범위 외, 후속 태스크에서 추가 |
| pnpm workspace 경로 | `packages/*`, `npm/*` |

### merge 기능 처리 결정

WASM API에 `merge(other: Uint8Array)` 메서드가 없으므로 **Task #28에서 병합 UI는 구현하지 않는다**.  
UI에는 "PDF 추가 병합" 버튼을 비활성화 상태로 표시하고 tooltip에 "준비 중"을 안내한다.  
WASM API 확장은 별도 이슈로 분리한다.

---

## 범위

### 포함

- `packages/studio/` React + Vite + TypeScript 프로젝트 생성
- `crates/rpdf-wasm/pkg/` 직접 참조 (`file:` 연동, 로컬 빌드 기준)
- pdf.js 렌더링 (썸네일 + 페이지 뷰어)
- Zustand 상태 관리
- Web Worker + Comlink (WASM 호출 오프로드, `Comlink.transfer()` 사용)
- 기능: 파일 열기(드래그 드롭 + 파일 선택), 썸네일 사이드바, 페이지 뷰어, 회전(0/90/180/270°), 페이지 삭제, 저장(다운로드), Undo/Redo
- 테스트: Vitest (store 유닛) + Playwright E2E 6개
- 품질: `pnpm typecheck`, `pnpm lint`, `pnpm test` 통과

### 제외

- GitHub Pages 배포 (Task #29)
- merge 기능 (WASM API 미지원)
- 모바일 반응형 레이아웃

---

## 기술 스택 (실제 설치 버전 기준)

| 패키지 | 버전 | 이유 |
|--------|------|------|
| React | 19.x | 최신 안정 |
| Vite | 6.x | 빠른 HMR, WASM 지원 |
| TypeScript | 5.x (workspace 공유) | |
| pdf.js | 4.x (`pdfjs-dist`) | 썸네일·렌더링 |
| Zustand | 5.x | 경량 상태 관리 |
| Comlink | 4.x | Web Worker 래퍼 |
| vite-plugin-static-copy | latest | pdf.worker 복사 자동화 |
| Vitest | latest | 유닛 테스트 |
| Playwright | latest | E2E 테스트 |
| ESLint | 9.x | |

> ⚠️ 의존성 추가 전 `pnpm add` 후 실제 설치 버전을 이 표에 기재한다.

---

## 디렉토리 구조

```
packages/studio/
├── package.json
├── vite.config.ts
├── tsconfig.json
├── playwright.config.ts
├── index.html
├── public/
│   └── pdf.worker.min.mjs        # vite-plugin-static-copy로 자동 복사
└── src/
    ├── main.tsx
    ├── App.tsx
    ├── core/
    │   ├── wasm-worker.ts         # Comlink expose — WASM 호출
    │   ├── wasm-client.ts         # Comlink wrap — UI에서 호출
    │   └── pdf-renderer.ts        # pdf.js 렌더링 헬퍼
    ├── state/
    │   └── document-store.ts      # Zustand store
    ├── ui/
    │   ├── AppShell.tsx
    │   ├── Toolbar.tsx
    │   ├── ThumbnailSidebar.tsx
    │   ├── PageViewer.tsx
    │   └── DropZone.tsx
    └── types/
        └── pdf.ts                 # 공유 타입
```

---

## 데이터 모델

### Zustand store (`document-store.ts`)

```typescript
interface PageInfo {
  index: number;           // 0-based
  rotation: number;        // 0 | 90 | 180 | 270
  media_box: [number, number, number, number];   // WASM snake_case 그대로
  crop_box: [number, number, number, number] | null;
}

interface DocumentState {
  fileName: string | null;
  fileRef: File | null;    // rawBytes 대신 File 참조 보관 (pdf.js 첫 로드용)
  pages: PageInfo[];
  undoLen: number;
  redoLen: number;
  isLoading: boolean;
  error: string | null;

  // actions
  openFile: (file: File) => Promise<void>;
  rotatePage: (index: number, degrees: number) => Promise<void>;
  deletePages: (indices: number[]) => Promise<void>;
  undo: () => Promise<void>;
  redo: () => Promise<void>;
  saveFile: () => Promise<void>;
}
```

> **D2**: `rawBytes: Uint8Array` 제거 — Worker에서 `save()` 호출로 언제든 신선한 바이트 획득 가능.  
> `fileRef: File | null`로 pdf.js 최초 로드용 원본 파일 참조만 유지.
>
> **D9**: `PageInfo` 필드는 WASM `page_info()` 반환 형식(snake_case)을 그대로 사용.  
> CP-A에서 실제 반환 필드명을 samples/ PDF로 확인 후 확정.

### Web Worker API (`wasm-worker.ts`)

```typescript
// Comlink로 expose되는 객체
interface WasmApi {
  init(): Promise<void>;
  open(bytes: Uint8Array): Promise<PageInfo[]>;   // Comlink.transfer(bytes.buffer, [bytes.buffer])
  rotatePage(index: number, degrees: number): Promise<PageInfo[]>; // 회전 후 전체 pages 반환
  deletePages(indices: number[]): Promise<PageInfo[]>;
  undo(): Promise<PageInfo[]>;
  redo(): Promise<PageInfo[]>;
  undoLen(): Promise<number>;
  redoLen(): Promise<number>;
  save(): Promise<Uint8Array>;  // Comlink.transfer(result.buffer, [result.buffer])
}
```

> **D3**: `open()`, `save()`에서 Uint8Array 전달 시 `Comlink.transfer(bytes.buffer, [bytes.buffer])`로 소유권 이전.  
> transfer 후 호출자 컨텍스트에서 해당 buffer 접근 불가 — 재사용 시 `save()`로 새 bytes 획득.
>
> **D7**: `undo()`, `redo()`, `rotatePage()`, `deletePages()` 완료 시 최신 `PageInfo[]`를 반환해 store와 동기화.  
> 이렇게 하면 CSS transform과 WASM 내부 rotation 상태가 항상 일치.

Worker는 `PdfDocument` 인스턴스를 모듈 수준 변수로 유지한다.  
`open()` 호출 시 기존 인스턴스를 `free()`하고 새로 생성한다.

---

## 성능 전략 (하이브리드 — D6/D7 결정)

```
회전 액션
  ├── WASM rotatePage() → Worker가 새 PageInfo[] 반환
  ├── store.pages 갱신 → PageViewer가 CSS transform 즉각 반영
  └── pdf.js 재로드 없음 (빠른 응답)

삭제 액션
  ├── WASM deletePages() → Worker가 새 PageInfo[] 반환
  ├── store.pages 갱신
  └── pdf.js 전체 재로드 (save() → 새 bytes → pdf.js.getDocument())

Undo / Redo
  ├── WASM undo()/redo() → Worker가 새 PageInfo[] 반환
  ├── store.pages 갱신 (CSS rotation 동기화 포함)
  └── 삭제 Undo 시 pdf.js 재로드 필요
```

---

## 체크포인트

### CP-A: 프로젝트 스캐폴딩 + WASM 연동 확인

1. `packages/studio/` Vite + React + TypeScript + Vitest + Playwright 초기 세팅
2. `crates/rpdf-wasm/pkg/` 로컬 패키지 연동 (`"rpdf-wasm": "file:../../crates/rpdf-wasm/pkg"`)
3. `vite.config.ts`에 WASM 플러그인 + `vite-plugin-static-copy` 설정 추가
   ```typescript
   viteStaticCopy({ targets: [{ src: 'node_modules/pdfjs-dist/build/pdf.worker.min.mjs', dest: '' }] })
   ```
4. `wasm-worker.ts` 최소 구현: `init()` → wasm 로드 성공 확인
5. **`page_info()` 반환 필드 실제 확인**: `samples/` PDF 하나로 호출해 snake_case 필드명 목록 기록
6. `PageInfo` 인터페이스 확정 (실제 반환 필드 기준)
7. `App.tsx`에서 Worker를 통해 wasm init 후 콘솔 로그 확인
8. `pnpm --filter @rpdf/studio dev` 기동 확인

**완료 기준**: 브라우저 콘솔에 "WASM initialized" 출력, page_info() 반환 필드명 확인됨

---

### CP-B: 파일 열기 + 썸네일 사이드바

1. `DropZone.tsx`: 드래그 드롭 + 파일 선택 버튼 (PDF MIME 검사)
2. `openFile` action: File → ArrayBuffer → Uint8Array → `Comlink.transfer()` → Worker `open()` → store 갱신
3. `fileRef: File`을 store에 보관 (pdf.js 초기 로드용)
4. `pdf-renderer.ts`: pdfjs-dist로 썸네일 렌더링 (`renderPage` → canvas → dataURL)
5. `ThumbnailSidebar.tsx`: pages 배열 순회, 각 페이지 썸네일 표시
6. 선택된 페이지 하이라이트 상태 관리
7. Vitest: `openFile` action 유닛 테스트 (Worker mock, 정상 PDF + 파싱 실패)

**완료 기준**: PDF 드롭 → 썸네일 N개 사이드바 표시, 페이지 선택 시 하이라이트

---

### CP-C: 페이지 뷰어 + 편집 기능

1. `PageViewer.tsx`: 선택 페이지를 pdf.js로 전체 렌더링 (canvas), rotation 적용
2. `Toolbar.tsx`: 회전(+90°, -90°) 버튼 → `rotatePage` action
3. 페이지 삭제 버튼 (썸네일에 hover 시 × 버튼) → `deletePages` action
4. 편집 후 pages 갱신 → CSS transform / pdf.js 재로드 (하이브리드 전략)
5. Undo/Redo 버튼 → `undoLen`/`redoLen` 기반 비활성화 처리
6. `isLoading` 중 편집 버튼 비활성화
7. Vitest: rotatePage / deletePages / undo / redo action 유닛 테스트

**완료 기준**: 회전 → 뷰어 즉시 반영, 삭제 → 썸네일 수 감소, Undo로 복구

---

### CP-D: 저장 + 테스트 + 품질 관문

1. `saveFile` action: Worker `save()` → `Comlink.transfer()` → Uint8Array → Blob → `<a>` 클릭 다운로드
2. Playwright E2E 6개 작성:
   - PDF 드롭 → 썸네일 N개 표시
   - 페이지 회전 → 뷰어 반영
   - 페이지 삭제 → 썸네일 수 감소
   - Undo → 삭제 복구
   - 저장 → 다운로드 파일 존재
   - PDF 아닌 파일 드롭 → 오류 메시지
3. `pnpm --filter @rpdf/studio typecheck` 통과
4. `pnpm --filter @rpdf/studio lint` 통과
5. `pnpm --filter @rpdf/studio test` 통과 (Vitest + Playwright)
6. `pnpm --filter @rpdf/studio build` 성공, dist/ 번들 크기 확인

**완료 기준**: 모든 품질 관문 통과, 다운로드된 PDF 열기 검증

---

## CI 파이프라인 (D8)

`studio` CI job은 `wasm-pack build` 스텝이 선행되어야 한다.

```yaml
# .github/workflows/ci.yml 에 studio job 추가
studio:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: taiki-e/install-action@wasm-pack
    - run: wasm-pack build crates/rpdf-wasm --target web --out-name rpdf_wasm
    - uses: pnpm/action-setup@v4
    - uses: actions/setup-node@v4
      with:
        node-version: 22
        cache: pnpm
    - run: pnpm install --frozen-lockfile
    - run: pnpm --filter @rpdf/studio typecheck
    - run: pnpm --filter @rpdf/studio lint
    - run: pnpm --filter @rpdf/studio test:unit
    - run: pnpm --filter @rpdf/studio build
    - run: pnpm --filter @rpdf/studio test:e2e
```

---

## 에러 처리 표

| 케이스 | UI 처리 |
|--------|---------|
| 파일 드롭 — PDF가 아님 | DropZone 오류 메시지 표시 |
| WASM `open()` 실패 | 전체 화면 오류 배너 |
| `rotatePage` / `deletePages` 실패 | toast 알림 (store.error) |
| `save()` 실패 | toast 알림 |
| Undo 스택 비어있음 | 버튼 비활성화 (WASM 에러 도달 안 함) |
| 페이지 전체 삭제 시도 | toast "최소 1페이지 필요" (UI 레벨 사전 차단) |
| Worker 초기화 실패 | 전체 화면 오류 배너 "WASM 로드 실패" |

---

## pdf.js 연동 주의사항

- `vite-plugin-static-copy`로 `pdf.worker.min.mjs`를 `public/`에 자동 복사 (CP-A)
- `GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs'` 설정 필수
- Vite + pdf.js: `optimizeDeps.include: ['pdfjs-dist']` 또는 dynamic import 사용
- WASM 로딩과 pdf.js 워커 초기화는 독립적으로 진행 (`Promise.all`)

---

## WASM 로컬 연동 방식

CI에서는 `npm/core/` rename 결과물을 사용하지만,  
**Task #28에서는 로컬 빌드 산출물(`crates/rpdf-wasm/pkg/`)을 `file:` 참조**로 연동한다.

```json
// packages/studio/package.json
{
  "dependencies": {
    "rpdf-wasm": "file:../../crates/rpdf-wasm/pkg"
  }
}
```

---

## 완료 기준 (이슈 #54)

1. `pnpm --filter @rpdf/studio dev` 기동 성공
2. PDF 드래그 드롭 → 썸네일 사이드바 표시
3. 회전/삭제 편집 후 뷰어 반영 확인
4. Undo/Redo 동작 확인 (CSS rotation 동기화 포함)
5. 저장 다운로드 후 PDF 열기 검증
6. `pnpm --filter @rpdf/studio build` 성공
7. `typecheck`, `lint`, `test` (Vitest + Playwright) 통과
8. CI studio job 그린

---

## 위험 요소

| 위험 | 대응 |
|------|------|
| Vite + WASM 설정 복잡성 | `vite-plugin-wasm` 또는 `?url` import 패턴 |
| pdf.js Worker CORS 이슈 | `vite-plugin-static-copy`로 `public/`에 복사, 절대 경로 지정 |
| Web Worker WASM 공유 불가 | Worker 내에서 wasm 독립 초기화 |
| 편집 후 pdf.js 캐시 stale | 하이브리드 전략: 회전=CSS transform, 삭제/저장=재로드 |
| Undo 후 CSS/WASM 상태 불일치 | undo()/redo() Worker 반환 PageInfo[]로 store 동기화 |
| Playwright E2E CI에서 pkg/ 없음 | CI studio job 첫 단계에 wasm-pack build 추가 |
| page_info() any 타입 | CP-A에서 실제 반환 필드명 확인 후 PageInfo 확정 |

---

## NOT in scope

| 항목 | 이유 |
|------|------|
| GitHub Pages 배포 | Task #29 |
| merge 기능 | WASM API 미지원, 별도 이슈 필요 |
| 모바일 반응형 레이아웃 | v0.4 최소 범위 초과 |
| CI wasm 번들 크기 게이트 | Task #27에서 이미 처리 |
| @rpdf/core npm 배포 | CI rename 스크립트 완성 후 Task #29 |

## What already exists

| 기존 코드 | 활용 여부 |
|-----------|----------|
| `crates/rpdf-wasm/pkg/` WASM 빌드 산출물 | ✅ file: 참조로 직접 연동 |
| `pnpm-workspace.yaml` `packages/*` 설정 | ✅ 이미 포함, 추가 불필요 |
| Task #27 CI wasm-pack 빌드 스텝 패턴 | ✅ studio CI job에 재사용 |
| `samples/` PDF 파일 | ✅ CP-A page_info() 필드 확인용 |

---

## 참고

- 종합 계획서: `mydocs/plans/v0.4-wasm-bindings.md`
- WASM API 타입: `crates/rpdf-wasm/pkg/rpdf_wasm.d.ts`
- pnpm workspace: `pnpm-workspace.yaml`

---

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | 범위·전략 | 0 | — | — |
| Codex Review | `/codex review` | 독립 2차 의견 | 0 | — | Codex 인증 실패 (ChatGPT 계정 모델 미지원) |
| Eng Review | `/plan-eng-review` | 아키텍처·테스트 (필수) | 1 | CLEAR | 9개 이슈, 0개 critical gap |
| Design Review | `/plan-design-review` | UI/UX 갭 | 0 | — | — |
| DX Review | `/plan-devex-review` | 개발자 경험 갭 | 0 | — | — |

- **OUTSIDE VOICE**: Claude subagent (Codex 폴백) — CSS/WASM 동기화, CI WASM 빌드 누락, page_info() any 타입 발견 → D7/D8/D9로 반영
- **UNRESOLVED**: 0
- **VERDICT**: ENG CLEARED — 9개 이슈 모두 결정 완료, 구현 시작 가능
