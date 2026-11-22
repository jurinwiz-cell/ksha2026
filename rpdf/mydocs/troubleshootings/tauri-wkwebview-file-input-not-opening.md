# 트러블슈팅 — Tauri WKWebView에서 `<input type="file">` 파일 선택 다이얼로그 미동작

**발생일**: 2026-05-20
**해결일**: 2026-05-20
**관련 Issue**: #64 (Task #35)
**심각도**: 중간
**환경**: Tauri 2.x / macOS / WKWebView

## 증상

Tauri 데스크톱 창에서 `<input type="file">`이 포함된 UI 요소(DropZone 등)를 클릭해도 OS 파일 선택 다이얼로그가 열리지 않는다.

- 브라우저(Chrome/Safari)에서는 동일한 UI가 정상 동작
- Tauri 창에서 클릭 시 아무 반응 없음 (에러 메시지 없음)
- `console.log`로 클릭 이벤트 자체는 발생함을 확인 가능

## 재현 방법

```bash
pnpm --filter @rpdf/desktop dev
```

Tauri 창이 열린 뒤 드래그앤드롭 영역(DropZone)을 클릭한다.

기대: OS 파일 선택 다이얼로그 오픈  
실제: 아무 반응 없음

## 원인 분석

### 최종 원인

macOS WKWebView는 보안 정책상 `<input type="file">`을 통한 시스템 파일 선택 대화상자를 허용하지 않는다. WKWebView가 렌더링하는 웹 컨텐츠에서 파일 선택 UI를 직접 열 수 없다.

**관련 코드 위치**: `packages/ui/src/components/DropZone.tsx` (또는 유사 경로)의 `<input type="file" ...>` 요소

**왜 이 문제가 발생하는가**:  
Tauri 2.x는 macOS에서 WKWebView를 사용한다. WKWebView의 파일 접근 제한으로 인해 웹 표준 `<input type="file">`이 파일 피커를 트리거하지 않는다. 브라우저(Chromium, WebKit)는 자체적으로 파일 피커를 처리하지만, WKWebView 임베딩 환경에서는 이 동작이 막혀 있다.

## 해결책

### Tauri 네이티브 파일 다이얼로그 플러그인 사용

`tauri-plugin-dialog`를 통해 OS 네이티브 파일 선택 다이얼로그를 직접 호출한다.

**Rust 의존성 추가** (`src-tauri/Cargo.toml`):
```toml
tauri-plugin-dialog = "2"
tauri-plugin-fs = "2"
```

**JS 패키지 추가**:
```bash
pnpm --filter @rpdf/desktop add @tauri-apps/plugin-dialog @tauri-apps/plugin-fs
```

**플러그인 등록** (`src-tauri/src/lib.rs`):
```rust
tauri::Builder::default()
    .plugin(tauri_plugin_dialog::init())
    .plugin(tauri_plugin_fs::init())
    // ...
```

**권한 설정** (`src-tauri/capabilities/default.json`):
```json
{
  "permissions": [
    "core:default",
    "dialog:allow-open",
    "fs:allow-read-file",
    "fs:scope-home-recursive"
  ]
}
```

**파일 열기 함수**:
```typescript
import { open } from '@tauri-apps/plugin-dialog'
import { readFile } from '@tauri-apps/plugin-fs'

export async function openPdfWithNativeDialog(): Promise<void> {
  const path = await open({
    multiple: false,
    filters: [{ name: 'PDF', extensions: ['pdf'] }],
  })
  if (!path) return  // 취소 시 null 반환

  const bytes = await readFile(path)
  const file = new File([bytes], path.split('/').pop() ?? 'document.pdf', { type: 'application/pdf' })
  await useDocumentStore.getState().openFile(file)
}
```

## 재발 방지

- CLAUDE.md §도구 활용 원칙에 추가됨: "Tauri WKWebView — `<input type="file">` 미지원"
- Tauri 앱에서 파일 열기 기능 구현 시 처음부터 `tauri-plugin-dialog` 사용
- plan-eng-review 단계에서 D1 scope test로 `<input type="file">` 동작 여부를 먼저 확인하면 범위 오설계를 방지할 수 있음

## 참고 자료

- [tauri-plugin-dialog 공식 문서](https://tauri.app/plugin/dialog/)
- 관련 Task: #35 (네이티브 파일 다이얼로그)
- 계획서: `mydocs/plans/task35-native-file-dialog.md`
