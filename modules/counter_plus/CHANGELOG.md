# CHANGELOG

## v1.1.0 (2026-06-16)

### 새 기능
- **GA4 총 지표 요약 카드** — 요약 탭 상단에 사용자·세션·페이지뷰·평균 세션시간·이탈률 5개 카드 추가
- **일별 방문자 추이 차트** — 방문자 분석 탭에 Chart.js 기반 세션/사용자 라인 그래프 추가
- **GA4 인기 페이지 평균 체류시간** — 인기 페이지 테이블에 페이지별 평균 참여시간(`userEngagementDuration`) 열 추가
- **소스/매체 상세 TOP 10** — 방문자 분석 탭에 `sessionSourceMedium` 기반 유입 출처 상세 테이블 추가
- **소스/매체 한국어 레이블** — `(direct) / (none)` → `직접 접속` 등 주요 값 자동 변환

### 수정
- **GA4 배치 요청 분리** — GA4 `batchRunReports` API 1회 최대 5개 제한 대응, 배치 A(5개)·배치 B(2개)로 분리
- **GA4 실시간 오류 구분** — `runRealtimeReport` 반환값을 `['count', 'error']` 구조로 변경하여 API 오류와 실제 0명을 구분 표시
- **GA4 토큰 만료 전 읽기 버그 수정** — `procCounter_plusAdminConfig`에서 설정 덮어쓰기 전 이전 client_id를 먼저 읽도록 수정
- **실시간 페이지 `(not set)` 처리** — GA4 반환값 `(not set)`·빈 문자열을 `(알 수 없음)`으로 표시
- **cURL 오류 로깅** — `_curlPost`에서 cURL 실패 시 `error_log`에 URL 포함 기록

### 이식성 개선
- **SSL CA 번들 자동 탐지** — 하드코딩된 XAMPP 경로(`E:/xampp/...`) 제거, `openssl_get_cert_locations()` + Linux 표준 경로 + XAMPP 드라이브 자동 탐색으로 교체 (Windows XAMPP·Linux 서버 모두 동작)
- **트리거 자동 등록** — `conf/module.xml`에 `<eventHandler>` 추가, 모듈 설치 시 `document.updateReadedCount` 트리거 자동 등록

### 정리
- `counter_plus.controller.php`의 구버전 `procCounter_plusAdminConfig` (서비스 계정 방식) 제거

---

## v1.0.0 (2026-06-12)

### 최초 릴리즈

#### Rhymix 조회수 통계
- `document_readed_log` 기반 조회 이력 수집 트리거 (`document.updateReadedCount after`)
- 전체 요약: 오늘·7일·30일 조회수, 인기 게시물 TOP 5, 주간 바 차트, 시간대별 피크, 게시판·회원 요약
- 인기 게시물: 기간·게시판 필터, TOP 20 테이블
- 시간대별: 24시간 분포 막대 그래프
- 게시판별: 게시판 순위 + 인기글 TOP 5
- 회원별: 로그인/비로그인 비율, 회원 TOP 10

#### GA4 통계
- OAuth2 연동 (`authorization_code` 흐름, refresh_token 자동 갱신)
- GA4 인기 페이지 TOP 20 (`pagePath` → `document_srl` 매핑)
- 요약 탭: 실시간 접속자, 기기별, 신규 vs 재방문, 유입 경로 TOP 3
- 방문자 분석 탭: 국가별 TOP 10, 기기별 상세, 유입 경로 TOP 5
- 실시간 탭: 지금 접속자 수 + 현재 보고 있는 페이지 목록
- GA4 설정 페이지: 속성 ID·OAuth2 정보 입력, 구글 계정 연동/해제

#### 기술 기반
- Rhymix v1 템플릿 파서 호환 (`<!--@foreach-->`, `{@ echo }` 방식)
- Rhymix Unbuffered PDO 대응 (`fetchAll()` 사용)
- Rhymix `addPrefixes()` 호환 SQL (`AS` 키워드 명시)
- GA4 Data API v1beta: `runReport`, `batchRunReports`, `runRealtimeReport`
