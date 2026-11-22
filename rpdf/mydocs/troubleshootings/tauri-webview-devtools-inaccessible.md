# 트러블슈팅 — Tauri 웹뷰 Chrome DevTools 직접 접근 불가

**발생일**: 2026-05-20
**해결일**: 2026-05-20
**관련 Issue**: #62 (Task #34)
**심각도**: 낮음
**환경**: Tauri 2.x / macOS / Chrome DevTools MCP

## 증상

Chrome DevTools MCP(`list_pages`)로 페이지 목록을 조회하면 Tauri 웹뷰가 나타나지 않는다.

```
## Pages
1: about:blank [selected]
```

`navigate_page`로 `http://localhost:5173/`을 열면 일반 브라우저 탭이 열리지만, 이는 Tauri 웹뷰와 별개의 컨텍스트다. 이 탭에서 `isTauri()` → `false`이므로 WASM 경로로 분기되어 Tauri-specific 동작을 검증할 수 없다.

## 원인 분석

Tauri 2.x의 웹뷰는 macOS에서 WKWebView(WebKit 기반)를 사용한다. Chrome DevTools Protocol은 Chromium 기반 브라우저에 연결되므로, WKWebView는 CDP를 통한 원격 디버깅을 노출하지 않는다. Tauri dev 모드에서 Vite 서버(`localhost:5173`)를 실제로 띄우지만, Tauri 웹뷰 프로세스는 Chrome DevTools와 다른 디버깅 채널을 사용한다.

## 해결책 (검증 방법)

Tauri-specific 기능(invoke, isTauri 분기 등)은 자동화된 DevTools 접근 대신 **수동 검증**으로 확인한다.

### 수동 검증 절차

1. `pnpm --filter @rpdf/desktop dev` 실행
2. Tauri 창이 열리면 직접 PDF 드래그앤드롭
3. 썸네일 표시, 페이지 선택, 회전, Undo/Redo 동작 확인

### Tauri 웹뷰 내부 콘솔 접근 (macOS)

Tauri dev 모드에서는 마우스 오른쪽 클릭 → "요소 검사" 메뉴가 활성화되어 있다 (`devtools` 권한이 활성화된 경우). 이를 통해 웹뷰 내부 DevTools에 접근 가능하다.

### 간접 검증 — Tauri 로그

`tauri dev` 실행 로그에서 Rust 패닉/에러 없음을 확인한다.

```bash
# 배경 실행 후 로그 확인
pnpm --filter @rpdf/desktop dev 2>&1 | grep -E "error|Error|panic"
```

## 재발 방지

- Tauri 기능 검증이 필요한 Task의 체크포인트 C는 항상 **수동 검증** 항목으로 명시한다
- Chrome DevTools MCP는 Tauri 웹뷰 접근에 사용하지 않는다 (일반 브라우저 전용)
- Rust 백엔드 로그 + TypeScript typecheck 통과 + 수동 드래그앤드롭 테스트로 3단계 검증

## 참고 자료

- Tauri 2.x devtools 설정: `tauri.conf.json` → `app.windows[].devtools`
- 관련 Task: #34 (isTauri bridge 분기)
