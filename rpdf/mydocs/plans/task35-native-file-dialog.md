# Task #35 계획서: 네이티브 파일 다이얼로그

**이슈**: #64  
**브랜치**: `local/task35`  
**마일스톤**: v0.5  
**선행 조건**: Task #34 (isTauri bridge 분기)  
**다음 Task**: TBD

---

## 목표

Tauri 환경에서 OS 네이티브 파일 선택 다이얼로그를 통해 PDF를 열 수 있도록 한다.

**완료 기준**: Tauri 창 → "파일 열기" 버튼 클릭 → OS 파일 다이얼로그 → PDF 선택 → 썸네일 표시

---

## 환경

| 도구 | 버전 |
|------|------|
| tauri-plugin-dialog | 2.7.1 |
| tauri-plugin-fs | 2.5.1 |
| @tauri-apps/plugin-dialog | ^2 |
| @tauri-apps/plugin-fs | ^2 |

---

## 설계 결정

### 아키텍처 방향

다음 두 가지 접근법을 검토했다.

| 방식 | 설명 | 장점 | 단점 |
|------|------|------|------|
| A. readFile + 기존 open_pdf | dialog → readFile → File → store.openFile | Rust 변경 없음, fileRef 유지 → 썸네일 정상 | IPC 2회 (readFile + open_pdf) |
| B. open_pdf_path (신규 Rust command) | dialog → open_pdf_path → pages (Rust가 파일 읽음) | IPC 1회 | fileRef 없음 → 썸네일 별도 처리 필요 |

**선택: A (readFile + 기존 open_pdf)**  
이유: Rust 변경 없음, fileRef가 유지되어 썸네일 렌더링 그대로 동작, 범위 최소화(YAGNI).

### 컴포넌트 수정 범위

`@rpdf/ui`의 `DropZone`·`document-store`는 변경하지 않는다.  
`packages/desktop/src/App.tsx`에만 Tauri-specific 버튼을 추가한다.

---

## 파일 구조

```
packages/desktop/
├── src/
│   ├── core/
│   │   └── tauri-dialog.ts     ← 신규
│   └── App.tsx                 ← "파일 열기" 버튼 추가
├── src-tauri/
│   ├── Cargo.toml              ← tauri-plugin-dialog, tauri-plugin-fs 추가
│   ├── src/
│   │   └── lib.rs              ← 플러그인 등록
│   └── capabilities/
│       └── default.json        ← 신규 (권한 설정)
└── package.json                ← JS 패키지 추가
```

---

## 작업 범위

### 체크포인트 A: 의존성 설치 + 권한 설정

**A-1. Rust 의존성 추가**

```bash
cargo add tauri-plugin-dialog -p rpdf-desktop
cargo add tauri-plugin-fs -p rpdf-desktop
```

**A-2. JS 패키지 추가**

```bash
pnpm --filter @rpdf/desktop add @tauri-apps/plugin-dialog @tauri-apps/plugin-fs
```

**A-3. `src-tauri/src/lib.rs` 플러그인 등록**

```rust
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_dialog::init())   // 추가
        .plugin(tauri_plugin_fs::init())        // 추가
        .manage(AppState::default())
        .invoke_handler(tauri::generate_handler![...])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
```

**A-4. `src-tauri/capabilities/default.json` 신규 생성**

```json
{
  "$schema": "../gen/schemas/desktop-schema.json",
  "identifier": "default",
  "description": "Default capability for main window",
  "windows": ["main"],
  "permissions": [
    "core:default",
    "dialog:allow-open",
    "fs:allow-read-file",
    "fs:scope-home-recursive"
  ]
}
```

**완료 기준**: `cargo check -p rpdf-desktop` 통과

### 체크포인트 B: Frontend 구현

**B-1. `packages/desktop/src/core/tauri-dialog.ts` 신규 생성**

```typescript
import { open } from '@tauri-apps/plugin-dialog'
import { readFile } from '@tauri-apps/plugin-fs'
import { useDocumentStore } from '@rpdf/ui'

export async function openPdfWithNativeDialog(): Promise<void> {
  const path = await open({
    multiple: false,
    filters: [{ name: 'PDF', extensions: ['pdf'] }],
  })
  if (!path) return  // open({ multiple: false }) returns string | null

  const bytes = await readFile(path)
  const filename = path.split('/').pop() ?? 'document.pdf'
  const file = new File([bytes], filename, { type: 'application/pdf' })

  await useDocumentStore.getState().openFile(file)
}
```

**B-2. `packages/desktop/src/App.tsx` 수정**

```tsx
import { isTauri } from '@tauri-apps/api/core'
import { openPdfWithNativeDialog } from './core/tauri-dialog'

// pages.length === 0 분기에서:
{pages.length === 0 ? (
  <div style={{ flex: 1, display: 'flex', flexDirection: 'column',
                alignItems: 'center', justifyContent: 'center', padding: 40, gap: 16 }}>
    {isTauri() && (
      <button
        onClick={openPdfWithNativeDialog}
        style={{
          padding: '10px 24px',
          fontSize: 15,
          cursor: 'pointer',
          borderRadius: 6,
          border: '1px solid #0070f3',
          background: '#0070f3',
          color: 'white',
        }}
      >
        파일 열기
      </button>
    )}
    <DropZone />
  </div>
) : (...)}
```

**완료 기준**: `pnpm --filter @rpdf/desktop typecheck` 통과

### 체크포인트 C: 빌드 + 수동 검증

```bash
pnpm --filter @rpdf/desktop build
pnpm --filter @rpdf/desktop dev
```

수동 검증:
1. Tauri 창 → "파일 열기" 버튼 클릭 → OS 파일 다이얼로그 표시
2. PDF 선택 → 썸네일 표시
3. 드래그앤드롭 여전히 동작 확인 (회귀 없음)

---

## 에러 처리

- 다이얼로그에서 취소(null 반환) → 즉시 return, 에러 없음
- 파일 읽기 실패 → `readFile`이 reject → `openFile`의 catch → store error 표시
- 비PDF 파일 — dialog filters로 PDF만 선택 가능, DropZone의 MIME 검사가 fallback

---

## 공개 API 확인 완료

- `@tauri-apps/plugin-dialog`: `open({ multiple, filters })` → `string | string[] | null`
- `@tauri-apps/plugin-fs`: `readFile(path)` → `Uint8Array`
- 권한: `dialog:allow-open`, `fs:allow-read-file`, `fs:scope-home-recursive`

---

## 범위 외

- 최근 파일 목록(MRU)
- 드래그앤드롭 → Tauri native drag 이벤트 처리
- `@rpdf/studio`는 WASM 유지 (변경 없음)

---

## GSTACK REVIEW REPORT

**리뷰어**: plan-eng-review (Claude Sonnet 4.6) + Gemini 0.38.1 (외부 목소리)  
**리뷰 일시**: 2026-05-20  
**결론**: 수정 조건부 승인 ✅

### 확정된 사항

**D1 — 범위 결정 (테스트 완료)**  
`<input type="file">`이 Tauri WKWebView에서 동작하지 않음 (드롭존 클릭 테스트 직접 확인).  
→ `tauri-plugin-dialog` + `tauri-plugin-fs` 플러그인 추가가 필수. 원래 계획 그대로 진행.

### 수정 사항 (적용 완료)

**TS 타입 체크 단순화**  
`open({ multiple: false })` 반환 타입은 `string | null` (배열 없음).  
`!path || typeof path !== 'string'` → `if (!path) return` 으로 단순화. B-1에 반영됨.

### 발견된 이슈 (계획 범위 내 허용)

**Double IPC (Gemini 지적)**  
`readFile(path)` (Rust→JS) + `open_pdf(bytes)` (JS→Rust) = 2회 IPC, 메모리 피크 2×.  
v0.5 MVP 범위(일반 PDF <50MB)에서는 허용. 향후 `open_pdf_path` Rust command로 최적화 고려.

**`fs:scope-home-recursive` 보안 범위 (Gemini 지적)**  
홈 디렉토리 전체 읽기 권한 — 광범위하지만 로컬 PDF 뷰어로서 실용적.  
다음 조건에서 허용:
- dialog filter가 PDF만 선택 가능하도록 제한
- 네트워크 노출 없음 (로컬 앱 전용)
- v0.5 이후 더 좁은 scope(`fs:scope-download-recursive` 등) 검토

**`capabilities/default.json` — `$schema` 경로**  
`"../gen/schemas/desktop-schema.json"` 파일은 첫 `tauri build` 이후 생성됨.  
초기 설정 시 IDE 스키마 검증 불가 — 런타임에는 영향 없음. 허용.

### 구현 착수 조건

- [ ] Checkpoint A 완료 기준: `cargo check -p rpdf-desktop` 통과
- [ ] Checkpoint B 완료 기준: `pnpm --filter @rpdf/desktop typecheck` 통과
- [ ] Checkpoint C 완료 기준: 수동 검증 3단계 통과 (버튼 클릭 → 다이얼로그 → PDF 선택 → 썸네일)
