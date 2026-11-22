# Task #45 완료 보고서 — 릴리즈 빌드 파이프라인

**완료일**: 2026-05-21  
**브랜치**: local/task45  
**Issue**: #86  
**계획서**: mydocs/plans/task45-release-pipeline.md

## 구현 범위

| 파일 | 내용 |
|------|------|
| `packages/desktop/src-tauri/icons/icon.icns` | 신규 생성 (`pnpm tauri icon`) |
| `packages/desktop/src-tauri/icons/icon.ico` | 신규 생성 (`pnpm tauri icon`) |
| `packages/desktop/src-tauri/icons/64x64.png`, `Square*.png`, `StoreLogo.png` | 신규 생성 (Appx/Windows 아이콘 세트) |
| `packages/desktop/src-tauri/tauri.conf.json` | version `0.5.0`, `bundle.icon` 배열 추가 |
| `packages/desktop/src-tauri/Cargo.toml` | version `0.5.0` |
| `packages/desktop/package.json` | version `0.5.0` |
| `.github/workflows/release.yml` | 신규: 릴리즈 빌드 파이프라인 |

**총 변경**: 7개 파일 신규/수정, 2개 커밋

## 체크포인트 결과

- [x] CP-1: 아이콘 생성 + `tauri.conf.json` 버전 `0.5.0` + icon 목록 추가 + `package.json`/`Cargo.toml` 버전 갱신
- [x] CP-2: `.github/workflows/release.yml` 작성 + `cargo build -p rpdf-desktop` 통과 + typecheck 통과 + `pnpm build` 통과 (로컬 `.dmg` 생성 확인)
- [ ] CP-3: `workflow_dispatch` 수동 트리거 → macOS .dmg + Windows .msi 확인 (CI 트리거로만 가능, 로컬 불가)

## 로컬 검증 결과

| 검증 | 결과 |
|------|------|
| `cargo build -p rpdf-desktop` | ✅ 통과 (12.19s) |
| `pnpm --filter @rpdf/desktop typecheck` | ✅ 통과 (오류 없음) |
| `pnpm --filter @rpdf/desktop build` | ✅ 통과 — `rpdf_0.5.0_aarch64.dmg` 로컬 생성 확인 |

## 주요 설계 결정

| 결정 | 이유 |
|------|------|
| `workflow_dispatch` — Release 미생성 | `github.ref_name`이 브랜치명이라 `tagName`으로 쓰면 잘못된 Release 생성. D1에서 확정. |
| `releaseDraft: true` | 자동 빌드 후 사람이 검토·배포. false면 즉시 공개됨. |
| `fail-fast: false` | macOS 실패해도 Windows 빌드 계속 진행. |
| 코드 서명 생략 | v0.5 미서명. v0.6 TODO. |
| `icon.icns` 소문자 | `pnpm tauri icon`이 `icon.icns`(소문자 i)로 생성. 계획서의 `Icon.icns`(대문자 I)와 달라 실제 생성 파일명 기준으로 등록. |

## 트러블슈팅 후보 분류

| 항목 | 분류 | 내용 |
|------|------|------|
| `pnpm tauri icon` 생성 파일명 대소문자 | C | 계획서에서는 `Icon.icns`(대문자)로 예상했으나 실제 생성 파일은 `icon.icns`(소문자). tauri 공식 명령이 소문자로 생성하므로 계획서의 오기. 실제 파일명 기준으로 tauri.conf.json에 등록. |
| Agy timeout (task45-review 외부 목소리) | C | plan-eng-review 외부 목소리 단계에서 agy 타임아웃. Claude subagent로 폴백해 동일 품질 리뷰 완료. |
| `workflow_dispatch` tagName 버그 | C | plan-eng-review D1에서 발견 및 해결. dispatch는 artifacts-only 분기 추가. |

## 다음 작업

Task #46 예정. v0.5 릴리즈 태그 push 및 CP-3 CI 검증 포함 가능.
