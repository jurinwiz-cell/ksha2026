# Task #29 계획서: Tauri 프로젝트 초기 세팅

**이슈**: #58  
**브랜치**: `local/task29`  
**마일스톤**: v0.5  
**참고**: `mydocs/plans/v0.5-tauri-desktop.md` — 프론트엔드 재사용 전략 옵션 B 선택

---

## 목표

rpdf-studio에서 UI 컴포넌트를 `@rpdf/ui` 공유 패키지로 추출하고,  
Tauri 2 기반 `packages/desktop` 프로젝트를 생성해  
`pnpm --filter @rpdf/desktop tauri dev`로 창이 열리는 상태까지 만든다.

---

## 환경 (실제 설치 버전)

| 도구 | 버전 |
|------|------|
| cargo-tauri | 2.11.2 |
| rustc | 1.95.0 |
| Node | 22.22.0 |
| pnpm | 10.13.1 |

---

## 설계 결정

### @rpdf/ui 패키지 범위 (D1)

**포함**:
- `types/pdf.ts` — `PageInfo`, `WasmApi` 인터페이스
- `state/document-store.ts` — Zustand store (initStore 패턴으로 api 주입 받음)
- `ui/*.tsx` — 5개 UI 컴포넌트 (AppShell, DropZone, PageViewer, ThumbnailSidebar, Toolbar)
- `core/pdf-renderer.ts` — pdf.js 렌더링 유틸 (pdfjs-dist에 의존)

**미포함** (각 앱이 직접 소유):
- `core/wasm-client.ts`, `core/wasm-worker.ts` — WASM 구현체 (studio에 유지)
- `App.tsx` — 각 앱 로컬 소유 (WASM import 포함, D9 참조)
- Task #34에서 tauri-bridge로 대체 예정

### initStore 패턴 (D2)

document-store.ts는 WasmApi 구현체를 직접 import하지 않고, 모듈 수준 변수로 주입받는다.  
이렇게 하면 @rpdf/ui가 rpdf-wasm(WASM 바이너리)에 의존하지 않아도 된다.

```typescript
// @rpdf/ui/src/state/document-store.ts
let _api: WasmApi & { open: (bytes: Uint8Array) => Promise<PageInfo[]> } | null = null

export function initStore(api: typeof _api): void {
  _api = api
}

// store 사용 시
function getApi() {
  if (!_api) throw new Error('initStore() must be called before using the store')
  return _api
}
```

**D7 null guard**: initStore 호출 전 store 사용 시 명확한 에러 발생. 테스트 포함 필수.

studio와 desktop 각각 main.tsx에서 (**Comlink spread 버그 회피**):
```typescript
import { wasmApi, openWithTransfer } from './core/wasm-client'
import { initStore } from '@rpdf/ui'

// ❌ 잘못된 방식: { ...wasmApi }는 Comlink.Remote(Proxy)를 spread하면 {}가 됨
// initStore({ ...wasmApi, open: openWithTransfer })

// ✅ 올바른 방식: 명시적 어댑터 객체 생성
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

**D1 근거**: `Comlink.wrap<T>(worker)`의 반환값은 Proxy 객체다. `{ ...proxy }`는 own enumerable properties만 복사하며, Proxy-trapped 메서드는 포함되지 않는다. 결과적으로 spread 객체는 `{}`가 되어 메서드가 모두 유실된다.

### desktop에서의 WASM 사용 (D3)

Task #29에서는 desktop도 studio와 동일하게 WASM bridge를 사용한다.  
Task #34에서 `tauri invoke` 기반 native bridge로 교체 예정.  
따라서 desktop/src/core/ 에 wasm-client.ts, wasm-worker.ts를 복사한다.

### vite-plugin-dts 사용 (D2-dts)

패키지명: `vite-plugin-dts` (npm 패키지). 계획서의 `vite-dts`는 오기 — 수정.  
`packages/ui/package.json` devDependencies에 `"vite-plugin-dts": "^4.x"` 사용.

```typescript
// packages/ui/vite.config.ts
import dts from 'vite-plugin-dts'
export default defineConfig({
  plugins: [react(), dts({ include: ['src'] })],
  build: { lib: { entry: 'src/index.ts', formats: ['es', 'cjs'] } }
})
```

### workerSrc — 각 앱이 viteStaticCopy 직접 설정 (D8)

`pdf-renderer.ts`의 `GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs'`는 해당 경로에 파일이 있어야 한다.  
`@rpdf/ui`는 라이브러리이므로 정적 파일 복사를 직접 담당할 수 없다.  
**studio와 desktop 각각** `vite.config.ts`에 `viteStaticCopy` 설정 필수.

### App.tsx 각 앱 로컬 소유 (D9)

`App.tsx`는 `wasm-client.ts`를 import하므로 `@rpdf/ui`에 포함 불가.  
studio/desktop 각각 로컬 `App.tsx`를 소유한다.  
`wasmApi.init()` 호출은 App.tsx의 useEffect에서 **main.tsx의 async bootstrap**으로 이동:

```typescript
// main.tsx (studio/desktop 공통 패턴)
async function bootstrap() {
  await wasmApi.init()
  const bridge = { /* 명시적 어댑터 */ }
  initStore(bridge)
  createRoot(rootElement).render(<StrictMode><App /></StrictMode>)
}
bootstrap()
```

### Cargo workspace 멤버 추가 (D6)

루트 `Cargo.toml`의 `members` 배열에 `"packages/desktop/src-tauri"` 추가 필수.  
누락 시 `pnpm --filter @rpdf/desktop tauri dev`에서 cargo workspace 인식 실패.

### CSP 설정 (D5)

`tauri.conf.json`의 `security.csp: null` — WASM 사용을 위해 `wasm-unsafe-eval`이 필요하다.  
개발 단계에서는 null(비활성)로 시작하고, 이후 프로덕션 빌드 시 명시적 CSP로 강화한다.

---

## 작업 범위

### 체크포인트 A: packages/ui 초기화

**A-1**: `packages/ui/` 디렉토리 구조 생성
```
packages/ui/
├── package.json          # @rpdf/ui, private: false
├── tsconfig.json
├── vite.config.ts        # lib 모드 빌드
├── src/
│   ├── index.ts          # re-export
│   ├── types/
│   │   └── pdf.ts
│   ├── state/
│   │   └── document-store.ts  # initStore 포함
│   ├── core/
│   │   └── pdf-renderer.ts
│   └── ui/
│       ├── AppShell.tsx
│       ├── DropZone.tsx
│       ├── PageViewer.tsx
│       ├── ThumbnailSidebar.tsx
│       └── Toolbar.tsx
```

**A-2**: `packages/ui/package.json`
```json
{
  "name": "@rpdf/ui",
  "version": "0.1.0",
  "private": false,
  "type": "module",
  "main": "./dist/index.cjs",
  "module": "./dist/index.js",
  "types": "./dist/index.d.ts",
  "exports": {
    ".": {
      "import": "./dist/index.js",
      "require": "./dist/index.cjs",
      "types": "./dist/index.d.ts"
    }
  },
  "scripts": {
    "build": "vite build",
    "typecheck": "tsc --noEmit"
  },
  "dependencies": {
    "pdfjs-dist": "^4.10.38",
    "react": "^18.3.1",
    "react-dom": "^18.3.1",
    "zustand": "^5.0.13"
  },
  "devDependencies": {
    "@types/react": "^18.3.12",
    "@types/react-dom": "^18.3.1",
    "@vitejs/plugin-react": "^4.3.3",
    "typescript": "^5.6.3",
    "vite": "^6.4.2",
    "vite-plugin-dts": "^4.5.4"
  },
  "peerDependencies": {
    "react": "^18.0.0",
    "react-dom": "^18.0.0"
  }
}
```

**A-3**: studio의 파일을 @rpdf/ui로 이전 (복사 후 studio에서 삭제)
- `src/types/pdf.ts` → `@rpdf/ui/src/types/pdf.ts` (변경 없음)
- `src/state/document-store.ts` → `@rpdf/ui/src/state/document-store.ts` (initStore 추가)
- `src/core/pdf-renderer.ts` → `@rpdf/ui/src/core/pdf-renderer.ts` (변경 없음)
- `src/ui/*.tsx` 5개 → `@rpdf/ui/src/ui/*.tsx` (import 경로 수정)

**A-4**: `packages/ui/src/index.ts`
```typescript
export * from './types/pdf'
export * from './state/document-store'
export * from './core/pdf-renderer'
export { AppShell } from './ui/AppShell'
export { DropZone } from './ui/DropZone'
export { PageViewer } from './ui/PageViewer'
export { ThumbnailSidebar } from './ui/ThumbnailSidebar'
export { Toolbar } from './ui/Toolbar'
```

**체크포인트 A 완료 조건**: `pnpm --filter @rpdf/ui build` 통과

---

### 체크포인트 B: studio → @rpdf/ui 연결

**B-1**: `packages/studio/package.json`에 `"@rpdf/ui": "workspace:*"` 추가

**B-2**: studio의 App.tsx, main.tsx import 경로 수정
- `'./types/pdf'` → `'@rpdf/ui'`
- `'./state/document-store'` → `'@rpdf/ui'`
- `'./ui/AppShell'` 등 → `'@rpdf/ui'`
- `'./core/pdf-renderer'` → `'@rpdf/ui'`
- 단, `'./core/wasm-client'`는 로컬 유지

**B-3**: studio/src/main.tsx — initStore 호출 추가 (Comlink spread 버그 회피, D1)
```typescript
import { initStore } from '@rpdf/ui'
import { wasmApi, openWithTransfer } from './core/wasm-client'

async function bootstrap() {
  await wasmApi.init()
  // ❌ { ...wasmApi } → Comlink.Remote(Proxy) spread 시 {} 반환, 메서드 유실
  // ✅ 명시적 어댑터 객체 사용
  const bridge = {
    init: () => wasmApi.init(),
    open: (bytes: Uint8Array) => openWithTransfer(bytes),
    rotatePage: (i: number, d: number) => wasmApi.rotatePage(i, d),
    deletePages: (indices: number[]) => wasmApi.deletePages(indices),
    undo: () => wasmApi.undo(),
    redo: () => wasmApi.redo(),
    undoLen: () => wasmApi.undoLen(),
    redoLen: () => wasmApi.redoLen(),
    save: () => wasmApi.save(),
  }
  initStore(bridge)
  const rootElement = document.getElementById('root')!
  createRoot(rootElement).render(<StrictMode><App /></StrictMode>)
}
bootstrap()
```

**B-4**: studio에서 이전한 파일 삭제 (types/, state/, ui/, core/pdf-renderer.ts)

**B-5**: App.tsx 유지 — studio/src/App.tsx는 @rpdf/ui로 이전하지 않음 (D9). 로컬 소유.

**체크포인트 B 완료 조건**: `pnpm --filter @rpdf/studio dev` 기존 기능 동작 확인, `typecheck` 통과

---

### 체크포인트 C: packages/desktop 스캐폴딩

**C-1**: `packages/desktop/` 디렉토리 구조

```
packages/desktop/
├── package.json          # @rpdf/desktop
├── tsconfig.json
├── vite.config.ts
├── index.html
├── src/
│   ├── main.tsx
│   ├── core/
│   │   ├── wasm-client.ts   # studio에서 복사 (Task #34에서 tauri-bridge로 교체)
│   │   └── wasm-worker.ts
│   └── App.tsx              # studio App.tsx와 동일 시작
└── src-tauri/
    ├── src/
    │   ├── main.rs
    │   └── lib.rs           # run() 함수 포함
    ├── Cargo.toml
    ├── build.rs
    └── tauri.conf.json
```

**C-2**: `packages/desktop/src-tauri/Cargo.toml`
```toml
[package]
name = "rpdf-desktop"
version = "0.1.0"
edition = "2021"

[lib]
name = "rpdf_desktop_lib"
crate-type = ["staticlib", "cdylib", "rlib"]

[[bin]]
name = "rpdf-desktop"
path = "src/main.rs"

[build-dependencies]
tauri-build = { version = "2", features = [] }

[dependencies]
tauri = { version = "2", features = [] }
tauri-plugin-shell = "2"
serde = { version = "1", features = ["derive"] }
serde_json = "1"
```

**C-3**: `packages/desktop/src-tauri/tauri.conf.json`
```json
{
  "productName": "rpdf",
  "version": "0.1.0",
  "identifier": "io.github.kwoncheuljin.rpdf",
  "build": {
    "frontendDist": "../dist",
    "devUrl": "http://localhost:5173"
  },
  "app": {
    "windows": [
      {
        "title": "rpdf",
        "width": 1200,
        "height": 800
      }
    ],
    "security": {
      "csp": null
    }
  },
  "bundle": {
    "active": true,
    "targets": "all",
    "icon": []
  }
}
```

**C-4**: `packages/desktop/src-tauri/src/lib.rs`
```rust
#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
```

**C-5**: `packages/desktop/src-tauri/src/main.rs`
```rust
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

fn main() {
    rpdf_desktop_lib::run()
}
```

**C-6**: `packages/desktop/package.json`
```json
{
  "name": "@rpdf/desktop",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "tauri dev",
    "build": "tauri build",
    "vite:build": "tsc -b && vite build",
    "typecheck": "tsc --noEmit"
  },
  "dependencies": {
    "@rpdf/ui": "workspace:*",
    "@tauri-apps/api": "^2",
    "@tauri-apps/plugin-shell": "^2",
    "comlink": "^4.4.2",
    "pdfjs-dist": "^4.10.38",
    "react": "^18.3.1",
    "react-dom": "^18.3.1",
    "rpdf-wasm": "file:../../crates/rpdf-wasm/pkg"
  },
  "devDependencies": {
    "@types/react": "^18.3.12",
    "@types/react-dom": "^18.3.1",
    "@vitejs/plugin-react": "^4.3.3",
    "typescript": "^5.6.3",
    "vite": "^6.4.2",
    "vite-plugin-wasm": "^3.6.0",
    "vite-plugin-static-copy": "^2.3.2"
  }
}
```

**C-7**: desktop의 vite.config.ts — studio vite.config.ts와 동일 시작 (D8: viteStaticCopy로 pdf.worker.min.mjs 복사 포함 필수)

**C-8**: `packages/desktop/src/main.tsx` — studio main.tsx와 동일 (initStore 포함, 명시적 bridge 어댑터 사용)

**체크포인트 C 완료 조건**: `pnpm --filter @rpdf/desktop tauri dev` 창 열림

---

### 체크포인트 D: pnpm workspace + 최종 검증

**D-1**: `pnpm-workspace.yaml` — 이미 `packages/*` 패턴으로 포함됨. 변경 불필요 (확인 완료).

**D-1b**: 루트 `Cargo.toml` members에 `"packages/desktop/src-tauri"` 추가 (D6 결정)

**D-2**: `pnpm install` — 전체 의존성 재설치

**D-3**: CI yml 업데이트
- `@rpdf/ui build` step 추가
- `@rpdf/desktop` lint/typecheck step 추가 (tauri build는 CI 제외 — 빌드 시간 과다)

**D-4**: 전체 품질 관문
```bash
pnpm --filter @rpdf/ui typecheck
pnpm --filter @rpdf/ui build
pnpm --filter @rpdf/studio typecheck
pnpm --filter @rpdf/studio lint
pnpm --filter @rpdf/studio test:unit
pnpm --filter @rpdf/desktop typecheck
```

---

## 에러 처리 표

| 상황 | 처리 | 에러 유형 |
|------|------|-----------|
| initStore 호출 전 store 사용 | `if (!_api) throw new Error('initStore not called')` | runtime |
| desktop에서 WASM 초기화 실패 | document-store.error 상태로 전파 (기존 동일) | UI 에러 메시지 |
| @rpdf/ui 빌드 실패 | studio/desktop 빌드도 실패 (의존 관계) | CI 단계 실패 |

---

## 테스트 전략

- **유닛 테스트**: document-store.test.ts를 @rpdf/ui/src/__tests__로 이전
  - `vi.mock('../core/wasm-client', ...)` 제거 (D3)
  - `beforeEach(() => initStore(mockApi))` 패턴으로 교체
  - `afterEach(() => initStore(null))` 또는 리셋 함수로 격리
  - **null guard 테스트 추가 (D7)**: initStore 호출 전 store 사용 시 에러 발생 확인
    ```typescript
    it('initStore 호출 전 store 사용 시 에러 발생', () => {
      // reset: initStore를 null로 호출
      initStore(null as any)
      expect(() => useDocumentStore.getState().someAction()).toThrow('initStore() must be called')
    })
    ```
- **studio E2E**: 기존 6개 케이스 회귀 없음 확인 (체크포인트 B 완료 후)
- **desktop 수동 테스트**: PDF 열기 → 회전 → 저장 골든 패스 (창이 열리는 수준)

---

## 공개 API 확인 완료

- `@tauri-apps/api` v2: `invoke`, `window`, `path` 등 — docs.rs + npm 확인
- `tauri-plugin-shell` v2: 기본 shell 플러그인 — tauri.app 확인
- `vite-plugin-dts` v4: lib 모드 .d.ts 생성 — npm 확인 완료 (계획서의 `vite-dts`는 오기, D2-dts에서 수정)

---

## 범위 외 (Task #34로 이연)

- Tauri `invoke` 기반 native bridge 구현
- `isTauri` 분기로 wasm-bridge vs tauri-bridge 선택
- PDF 파일 시스템 연동 (네이티브 파일 다이얼로그)

---

## 성공 기준

1. `pnpm --filter @rpdf/ui build` 통과
2. `pnpm --filter @rpdf/studio dev` 기동 + 기존 기능 회귀 없음
3. `pnpm --filter @rpdf/desktop tauri dev` 창 열림 + PDF 열기/편집/저장 동작
4. `typecheck`, `lint` 모두 통과
5. studio 기존 유닛 테스트 6개 통과

---

## GSTACK REVIEW REPORT

**리뷰 일시**: 2026-05-20  
**리뷰어**: plan-eng-review (Eng Lead perspective + Claude outside voice)  
**결정 수**: 9개 (D1~D9)  
**상태**: ✅ 승인됨 — 구현 진행 가능

### 핵심 발견 및 수정사항

| ID | 심각도 | 발견 | 결정 |
|----|--------|------|------|
| D1 | 🔴 Critical | `{ ...wasmApi }` Comlink.Remote spread → `{}` (메서드 유실) | 명시적 어댑터 객체 사용 |
| D2-dts | 🟡 Minor | `vite-dts` 패키지명 오기 | `vite-plugin-dts` v4 로 수정 |
| D3 | 🟡 Minor | `vi.mock` 기반 테스트 → initStore 도입 후 구조 불일치 | `initStore(mockApi)` 패턴으로 재작성 |
| D4 | 🟢 Confirmed | `private: false` — 의도적 공개 (pnpm workspace 내 소비) | 유지 |
| D5 | 🟡 Minor | CSP null — WASM 개발 편의 | 개발 단계 null 허용, 프로덕션에서 강화 예정 |
| D6 | 🔴 Critical | 루트 Cargo.toml에 `packages/desktop/src-tauri` 멤버 누락 | 추가 필수 |
| D7 | 🟡 Minor | null guard 구현했으나 테스트 없음 | null guard 유닛 테스트 추가 |
| D8 | 🟡 Minor | `workerSrc` 경로 하드코딩 — @rpdf/ui가 정적 복사 불가 | 각 앱(studio/desktop)이 viteStaticCopy 직접 설정 |
| D9 | 🟡 Minor | App.tsx가 wasm-client import → @rpdf/ui 포함 불가 | 각 앱 로컬 소유, wasmApi.init()을 main.tsx bootstrap으로 이동 |

### 아키텍처 다이어그램 (검증 완료)

```
packages/
├── ui/               @rpdf/ui (라이브러리)
│   ├── types/pdf.ts          ← PageInfo, WasmApi 인터페이스
│   ├── state/document-store  ← initStore 패턴, null guard
│   ├── core/pdf-renderer     ← workerSrc 하드코딩 (각 앱이 파일 제공)
│   └── ui/*.tsx              ← 5개 컴포넌트
├── studio/           @rpdf/studio (웹)
│   ├── core/wasm-client.ts   ← Comlink 래퍼 (로컬)
│   ├── App.tsx               ← 로컬 소유 (D9)
│   ├── vite.config.ts        ← viteStaticCopy 포함 (D8)
│   └── main.tsx              ← bootstrap() + 명시적 bridge (D1)
└── desktop/          @rpdf/desktop (Tauri)
    ├── src/core/wasm-client.ts ← studio에서 복사 (Task #34에서 교체)
    ├── App.tsx               ← 로컬 소유 (D9)
    ├── vite.config.ts        ← viteStaticCopy 포함 (D8)
    ├── main.tsx              ← bootstrap() + 명시적 bridge (D1)
    └── src-tauri/            ← Cargo workspace 멤버 (D6)
```

### 구현 태스크 목록 (T1~T8)

| ID | 체크포인트 | 내용 |
|----|-----------|------|
| T1 | A | `packages/ui/` 디렉토리 구조 생성 + `vite-plugin-dts` 설정 |
| T2 | A | studio → @rpdf/ui 파일 이전 (types, state, core/pdf-renderer, ui/*.tsx) |
| T3 | A | document-store.ts에 `initStore` + `getApi` null guard 추가 |
| T4 | A | `@rpdf/ui/src/__tests__/document-store.test.ts` — vi.mock 제거, initStore 패턴 + null guard 테스트 |
| T5 | B | studio/package.json에 `@rpdf/ui: workspace:*` 추가, import 경로 수정 |
| T6 | B | studio/main.tsx → async bootstrap + 명시적 bridge 어댑터 |
| T7 | C | packages/desktop/ 스캐폴딩 (Cargo.toml workspace 멤버 추가 포함) |
| T8 | D | CI yml 업데이트 (@rpdf/ui build, @rpdf/desktop typecheck) |

### 리뷰 완료 선언

모든 Critical(D1, D6) 발견사항이 계획서에 반영됨.  
구현 진행 조건 충족: **generator subagent로 위임 가능**.
