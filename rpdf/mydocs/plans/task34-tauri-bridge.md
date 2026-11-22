# Task #34 계획서: isTauri bridge 분기

**이슈**: #62  
**브랜치**: `local/task34`  
**마일스톤**: v0.5  
**선행 조건**: Task #29 (@rpdf/ui + packages/desktop 스캐폴딩), Task #30 (Tauri Commands)  
**다음 Task**: Task #35 (네이티브 파일 다이얼로그)

---

## 목표

`packages/desktop/src/`에서 런타임에 Tauri 환경을 감지하고,  
WASM worker 대신 Rust native Tauri commands를 사용하도록 전환한다.

**완료 기준**: `pnpm --filter @rpdf/desktop dev` → Tauri 창에서 PDF 드래그앤드롭 → 페이지 썸네일 표시

---

## 환경

| 도구 | 버전 |
|------|------|
| @tauri-apps/api | ^2 (설치됨) |
| TypeScript | ^5.6.3 |
| Vite | ^6 |

---

## 현황 분석

### 현재 흐름 (WASM)

```
main.tsx
  → wasm-client.ts     # Comlink.wrap<WasmApi>(worker)
  → wasm-worker.ts     # WASM 초기화
  → initStore(bridge)  # document-store에 WasmApi 주입
```

### 목표 흐름 (Tauri 환경)

```
main.tsx
  → isTauri() 감지
  → tauri-client.ts    # Tauri invoke wrapper → WasmApi 인터페이스 구현
  → initStore(bridge)  # 동일
```

### 핵심 불일치 — WasmApi vs Tauri commands

| WasmApi 메서드 | Tauri command | 반환 차이 |
|---------------|--------------|---------|
| `open(bytes)` | `open_pdf` | Tauri → `OpenResult { pages, undo_len, redo_len }` |
| `rotatePage(i, d)` | `rotate_page` | 동일 |
| `deletePages(ids)` | `delete_pages` | 동일 |
| `undo()` | `undo` | 동일 |
| `redo()` | `redo` | 동일 |
| `undoLen() → number` | (OpenResult에 포함) | 별도 호출 없음 |
| `redoLen() → number` | (OpenResult에 포함) | 별도 호출 없음 |
| `save() → Uint8Array` | `save_pdf → Vec<u8>` | number[] 변환 필요 |
| `init() → void` | (없음 — no-op) | — |

**해결책**: `tauri-client.ts`에서 `_lastResult` 캐시를 유지.  
`undo()/redo()` 등 호출 시 `OpenResult`를 `_lastResult`에 저장 → `undoLen()/redoLen()` 호출 시 캐시 반환.  
(document-store.ts는 mutation 직후 undoLen/redoLen을 동기적으로 호출하므로 순서 보장됨)

---

## 타입 수정 사항

### PageInfo.media_box nullable

Rust `commands.rs`의 `PageInfo.media_box: Option<[f64; 4]>` → JSON 직렬화 시 `null` 가능.  
현재 TS 타입은 non-nullable → 수정 필요.

```typescript
// packages/ui/src/types/pdf.ts — 수정 전
export interface PageInfo {
  media_box: [number, number, number, number]  // ❌ Rust Option → null 불가
}

// 수정 후
export interface PageInfo {
  media_box: [number, number, number, number] | null  // ✅
}
```

---

## 파일 구조

```
packages/desktop/src/
├── core/
│   ├── wasm-client.ts      (변경 없음)
│   ├── wasm-worker.ts      (변경 없음)
│   └── tauri-client.ts     ← 신규
└── main.tsx                ← isTauri 분기 추가
packages/ui/src/
└── types/pdf.ts            ← media_box nullable 수정
```

---

## 작업 범위

### 체크포인트 A: PageInfo 타입 + tauri-client.ts

**A-1. `packages/ui/src/types/pdf.ts` 수정**

`media_box` 타입을 `[number, number, number, number] | null`로 수정.

**A-2. `packages/desktop/src/core/tauri-client.ts` 신규 생성**

```typescript
import { invoke } from '@tauri-apps/api/core'
import type { PageInfo, WasmApi } from '@rpdf/ui'

interface OpenResult {
  pages: PageInfo[]
  undo_len: number
  redo_len: number
}

let _lastResult: OpenResult = { pages: [], undo_len: 0, redo_len: 0 }

function storeAndExtract(result: OpenResult): PageInfo[] {
  _lastResult = result
  return result.pages
}

export const tauriClient: WasmApi & { open: (bytes: Uint8Array) => Promise<PageInfo[]> } = {
  init: async () => {},

  open: async (bytes: Uint8Array): Promise<PageInfo[]> => {
    // Tauri 2 IPC: Uint8Array → Rust Vec<u8> (D2: 직접 전달, Array.from 변환 불필요)
    const result = await invoke<OpenResult>('open_pdf', { bytes })
    return storeAndExtract(result)
  },

  rotatePage: async (index: number, degrees: number): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('rotate_page', { index, degrees })
    return storeAndExtract(result)
  },

  deletePages: async (indices: number[]): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('delete_pages', { indices })
    return storeAndExtract(result)
  },

  undo: async (): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('undo')
    return storeAndExtract(result)
  },

  redo: async (): Promise<PageInfo[]> => {
    const result = await invoke<OpenResult>('redo')
    return storeAndExtract(result)
  },

  undoLen: async (): Promise<number> => _lastResult.undo_len,

  redoLen: async (): Promise<number> => _lastResult.redo_len,

  save: async (): Promise<Uint8Array> => {
    const bytes = await invoke<number[]>('save_pdf')
    return new Uint8Array(bytes)
  },
}
```

**완료 기준**: `pnpm --filter @rpdf/desktop typecheck` 통과

### 체크포인트 B: main.tsx isTauri 분기

> **D1 결정**: 동적 import 사용 — Tauri 환경에서 WASM Worker가 생성되지 않도록 `wasm-client.ts`를 동적 import로 변경.

```typescript
import { isTauri } from '@tauri-apps/api/core'
import { initStore } from '@rpdf/ui'

async function bootstrap() {
  let bridge: Parameters<typeof initStore>[0]

  if (isTauri()) {
    // Tauri 환경: Rust native commands 사용 (WASM Worker 미생성)
    const { tauriClient } = await import('./core/tauri-client')
    await tauriClient.init()
    bridge = tauriClient
  } else {
    // 웹 환경: WASM worker 사용 (동적 import — Tauri에서는 이 경로 미실행)
    const { wasmApi, openWithTransfer } = await import('./core/wasm-client')
    await wasmApi.init()
    bridge = {
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
  }

  initStore(bridge)
  // ... render
}
```

**완료 기준**: `pnpm --filter @rpdf/desktop typecheck` + `pnpm --filter @rpdf/desktop build` 통과

### 체크포인트 C: 수동 검증

`pnpm --filter @rpdf/desktop dev` 실행 후 Tauri 창에서:
1. PDF 파일 드래그앤드롭 → 페이지 썸네일 표시 확인
2. 페이지 회전 → 썸네일 업데이트 확인
3. Undo → 회전 복원 확인

---

## 에러 처리

Tauri commands는 이미 `Result<T, String>` 반환 → `invoke`가 reject Promise로 변환.  
document-store.ts의 `catch (e) { set({ error: String(e) }) }` 패턴으로 자동 처리.

---

## 공개 API 확인 완료

- `@tauri-apps/api/core`: `invoke`, `isTauri` — Tauri 2.x 공식 API
- `packages/desktop/src-tauri/src/commands.rs`: Task #30에서 구현됨
- `WasmApi` 인터페이스: `packages/ui/src/types/pdf.ts`

---

## 범위 외 (이후 Task로 이연)

- Task #35: 네이티브 파일 다이얼로그 (`open_pdf_path`)
- 바이너리 IPC 최적화 (대용량 PDF — D2에서 Uint8Array 직접 전달로 이미 완화)
- `@rpdf/studio`에는 WASM 유지 (Tauri 미사용)

---

## GSTACK REVIEW REPORT

**작성일**: 2026-05-20  
**검토 모델**: Gemini CLI (outside voice) + plan-eng-review  
**상태**: ✅ APPROVED — 구현 진행 가능

### 결정 사항 (D1, D2)

| ID | 항목 | 결정 | 근거 |
|----|------|------|------|
| D1 | WASM Worker 로드 시점 | 동적 import 사용 | Tauri 환경에서 WASM Worker가 생성되는 불필요한 side effect 방지 |
| D2 | Tauri IPC bytes 직렬화 | Uint8Array 직접 전달 | Tauri 2 IPC가 네이티브로 Uint8Array → Vec<u8> 변환 지원; Array.from 불필요한 복사 제거 |

### 주요 검토 결과

- `_lastResult` 캐시 패턴: document-store가 mutation 직후 동기적으로 undoLen/redoLen을 호출하므로 순서 보장 — 유효
- `close()` 미구현: Rust 소유권으로 open_pdf 호출 시 이전 PdfState 자동 drop — 별도 구현 불필요
- 단일 문서 MVP: `AppState = Mutex<Option<PdfState>>` — 싱글턴 안전
- 에러 처리: Tauri `Result<T, String>` → invoke reject → document-store catch 패턴으로 자동 처리

### 구현 작업 목록

| ID | 파일 | 작업 | 체크포인트 |
|----|------|------|------------|
| T1 | `packages/ui/src/types/pdf.ts` | `media_box` → `\| null` 수정 | A |
| T2 | `packages/desktop/src/core/tauri-client.ts` | 신규 파일 생성 (Tauri invoke wrapper) | A |
| T3 | `packages/desktop/src/main.tsx` | 동적 import + isTauri() 분기 | B |
