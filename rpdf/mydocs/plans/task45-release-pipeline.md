# Task #45 계획서 — 릴리즈 빌드 파이프라인

**Issue**: #86  
**브랜치**: local/task45  
**마일스톤**: v0.5  
**작성일**: 2026-05-21

---

## 1. 배경 및 목표

v0.5 기능 구현이 완료됐다. 실사용자 배포를 위해 자동화된 GitHub Actions 릴리즈 파이프라인이 필요하다.

**목표**: 태그 push(`v*`) 시 GitHub Actions에서 macOS .dmg와 Windows .msi를 자동 빌드하고 GitHub Release에 첨부한다.

---

## 2. 현황 분석

| 항목 | 현재 상태 |
|------|-----------|
| `ci.yml` | Rust/Node/WASM/Studio 검증 — Ubuntu 전용, Tauri 빌드 없음 |
| `release.yml` | 없음 |
| `tauri.conf.json` version | `0.1.0` → `0.5.0`으로 갱신 필요 |
| `tauri.conf.json` icon | `[]` → 아이콘 파일 참조 추가 필요 |
| 아이콘 파일 | `src-tauri/icons/{32x32,128x128,128x128@2x,icon}.png` 존재, `.icns`/`.ico` 없음 |
| WASM 의존성 | `rpdf-wasm: file:../../crates/rpdf-wasm/pkg` — 빌드 전 `wasm-pack build` 필요 |
| 코드 서명 | v0.5는 미서명 배포 (v0.6 TODO) |

---

## 3. 구현 범위

### 3-1. 아이콘 생성 (로컬 1회)

```bash
cd packages/desktop
pnpm tauri icon src-tauri/icons/icon.png --output src-tauri/icons
```

생성 파일:
- `src-tauri/icons/Icon.icns` (macOS)
- `src-tauri/icons/icon.ico` (Windows)
- 기존 PNG 파일 갱신

### 3-2. `tauri.conf.json` 업데이트

**위치**: `packages/desktop/src-tauri/tauri.conf.json`

변경 항목:
```json
{
  "productName": "rpdf",
  "version": "0.5.0",
  ...
  "bundle": {
    "active": true,
    "targets": "all",
    "identifier": "io.github.kwoncheuljin.rpdf",
    "icon": [
      "icons/32x32.png",
      "icons/128x128.png",
      "icons/128x128@2x.png",
      "icons/icon.icns",
      "icons/icon.ico"
    ]
  }
}
```

### 3-3. `packages/desktop/package.json` 버전 갱신

```json
{
  "version": "0.5.0"
}
```

### 3-4. `.github/workflows/release.yml` 신규 생성

#### 트리거

```yaml
on:
  push:
    tags:
      - "v*"
  workflow_dispatch:
    inputs:
      tag:
        description: "릴리즈 태그 (예: v0.5.0)"
        required: false
        default: "v0.5.0-test"
```

#### 매트릭스

| Runner | 빌드 대상 | 아티팩트 |
|--------|---------|---------|
| `macos-latest` | arm64 | `.dmg` |
| `windows-latest` | x64 | `.msi`, `.exe` (setup) |

macOS x64 (Intel)은 v0.6에서 Universal Binary로 확장 예정.

#### 전체 워크플로우

```yaml
name: Release

on:
  push:
    tags:
      - "v*"
  workflow_dispatch:
    inputs:
      tag:
        description: "릴리즈 태그 (예: v0.5.0)"
        required: false
        default: "v0.5.0-test"

jobs:
  release:
    strategy:
      fail-fast: false
      matrix:
        include:
          - platform: macos-latest
            rust-targets: aarch64-apple-darwin
            args: "--target aarch64-apple-darwin"
          - platform: windows-latest
            rust-targets: x86_64-pc-windows-msvc
            args: ""

    runs-on: ${{ matrix.platform }}
    steps:
      - uses: actions/checkout@v4

      - uses: dtolnay/rust-toolchain@stable
        with:
          targets: ${{ matrix.rust-targets }}

      - uses: Swatinem/rust-cache@v2
        with:
          workspaces: . -> target

      - uses: taiki-e/install-action@v2
        with:
          tool: wasm-pack@0.15.0

      - name: Build WASM (desktop 의존성)
        run: wasm-pack build crates/rpdf-wasm --target web

      - uses: pnpm/action-setup@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 22
          cache: pnpm

      - run: pnpm install --frozen-lockfile

      - name: Build Tauri app (push — Release 생성)
        if: github.event_name == 'push'
        uses: tauri-apps/tauri-action@v0
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          tagName: ${{ github.ref_name }}
          releaseName: "rpdf ${{ github.ref_name }}"
          releaseBody: |
            ## 변경사항

            자세한 내용은 릴리즈 노트를 참조하세요.
          releaseDraft: true
          prerelease: false
          projectPath: packages/desktop
          args: ${{ matrix.args }}

      - name: Build Tauri app (workflow_dispatch — artifacts only)
        if: github.event_name == 'workflow_dispatch'
        uses: tauri-apps/tauri-action@v0
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          projectPath: packages/desktop
          args: ${{ matrix.args }}
```

> **설계 결정 (D1)**: `workflow_dispatch`에서 `github.ref_name`은 브랜치명이므로 `tagName`으로 사용하면 잘못된 Release가 생성된다.
> `push` 이벤트만 Release를 생성하고, `workflow_dispatch`는 빌드 아티팩트만 생산한다.

#### 주요 설계 결정

| 결정 | 이유 |
|------|------|
| `releaseDraft: true` | 자동 빌드 후 사람이 검토·배포. false면 즉시 공개. |
| `fail-fast: false` | macOS 실패해도 Windows 빌드 계속 진행. |
| `Swatinem/rust-cache` | Rust 빌드 캐시로 속도 향상 |
| 코드 서명 생략 | v0.5는 미서명. 사용자가 "개발자를 확인할 수 없습니다" 경고 수락 필요. |
| `wasm-pack@0.15.0` 고정 | 기존 CI와 버전 일치 |

---

## 4. 에러 처리 표

| 조건 | 기대 동작 |
|------|-----------|
| 아이콘 파일 누락 | `tauri build` 실패 → 아이콘 생성 후 재시도 |
| WASM 빌드 실패 | `pnpm install` 단계 실패 → WASM 선행 빌드 확인 |
| macOS 서명 경고 | 사용자가 "시스템 환경설정 > 보안" 에서 허용 |
| Windows Defender 경고 | 미서명 실행파일 — 사용자가 "추가 정보 > 실행" 선택 |

---

## 5. 관련 파일

| 파일 | 변경 |
|------|------|
| `packages/desktop/src-tauri/icons/Icon.icns` | 신규 생성 (tauri icon 명령) |
| `packages/desktop/src-tauri/icons/icon.ico` | 신규 생성 (tauri icon 명령) |
| `packages/desktop/src-tauri/tauri.conf.json` | version `0.5.0`, icon 목록 추가 |
| `packages/desktop/src-tauri/Cargo.toml` | version `0.5.0` (tauri.conf.json과 일관성) |
| `packages/desktop/package.json` | version `0.5.0` |
| `.github/workflows/release.yml` | 신규: 릴리즈 빌드 파이프라인 |

---

## 6. 테스트 전략

### 로컬 검증
- `cargo build -p rpdf-desktop` — Rust 컴파일 통과 확인
- `pnpm --filter @rpdf/desktop typecheck` — TypeScript 타입 검사
- `pnpm --filter @rpdf/desktop build` — Vite 빌드 (Tauri 없이 프론트엔드만)

### CI 검증
- `workflow_dispatch`로 수동 트리거 → macOS arm64 + Windows x64 빌드 결과 확인
- GitHub Release 초안(draft)에 `.dmg`, `.msi` 아티팩트 첨부 확인
- macOS: `.dmg` 마운트 후 `rpdf.app` 실행 — 앱이 정상 실행되고 PDF 열기 가능 확인
- Windows: `.msi` 설치 후 `rpdf.exe` 실행 — 앱이 정상 실행되고 PDF 열기 가능 확인

> **주의**: `tauri build`는 실제 빌드 환경(macOS, Windows) 없이는 로컬에서 cross-compile 불가. CI 트리거로만 검증.

---

## 7. 체크포인트

- [ ] CP-1: 아이콘 생성 + `tauri.conf.json` 업데이트 + 버전 갱신 커밋
- [ ] CP-2: `.github/workflows/release.yml` 작성 + `cargo build -p rpdf-desktop` + typecheck 통과
- [ ] CP-3: `workflow_dispatch` 수동 트리거 → macOS .dmg + Windows .msi 확인
- [ ] CP-4: 완료 보고서 + `/task-retro` + PR

---

## 8. 공개 API 확인

- `tauri-apps/tauri-action@v0`: [github.com/tauri-apps/tauri-action](https://github.com/tauri-apps/tauri-action) — 사용 확인
- `Swatinem/rust-cache@v2`: 기존 CI에서 사용 중
- `wasm-pack@0.15.0`: 기존 CI와 동일 버전 고정

---

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| Eng Review | `/plan-eng-review` | Architecture & CI design | 1 | CLEAN | D1 + 3 issues |

- **D1 (RESOLVED)**: `workflow_dispatch` 에서 `github.ref_name`은 브랜치명 → `tagName`으로 사용 시 잘못된 Release 생성. **결정: push만 Release 생성, dispatch는 artifacts-only.** 3-4 워크플로우에 조건 분기 추가.
- **Issue #2 (RESOLVED)**: `packages/desktop/src-tauri/Cargo.toml`의 `version = "0.1.0"` — tauri.conf.json 0.5.0 과 불일치. 파일 목록에 추가.
- **Issue #3 (RESOLVED — Claude subagent outside voice)**: 계획서에 dispatch 조건 분기 YAML이 명시되지 않음. 3-4 섹션에 구체적 YAML 추가.
- **Issue #5 (RESOLVED — Claude subagent outside voice)**: CP-3 검증 기준이 파일 존재만 확인. "실제 앱 실행 확인" 기준 추가.
- **UNRESOLVED**: 0
- **VERDICT**: ENG CLEARED — 리뷰 완료, 구현 시작 가능.
