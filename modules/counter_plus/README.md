# counter_plus — 인기 게시물 통계 모듈

Rhymix(XE) 전용 게시물 조회 통계 모듈입니다.  
Rhymix 내장 조회 이력(`document_readed_log`)을 분석하는 **Rhymix 통계**와  
Google Analytics 4 Data API를 연동하는 **GA4 통계** 두 가지를 하나의 관리자 페이지에서 제공합니다.

---

## 주요 기능

### Rhymix 조회수 통계
| 서브탭 | 제공 정보 |
|--------|-----------|
| 전체 | 오늘 / 최근 7일 / 최근 30일 총 조회수, 인기 게시물 TOP 5, 주간 조회 추이 바 차트, 시간대별 피크, 게시판별 요약, 회원별 요약 |
| 인기 게시물 | 기간 선택(오늘·7일·30일·전체) + 게시판 필터, TOP 20 테이블 |
| 시간대별 | 24시간대 조회 분포 막대 그래프, 피크 시간대 / 피크 조회수 |
| 게시판별 | 게시판 순위 + 각 게시판 인기글 TOP 5 |
| 회원별 | 로그인/비로그인 비율, 회원 조회 TOP 10 |

### GA4 통계 (Google Analytics 4 연동)
| 서브탭 | 제공 정보 |
|--------|-----------|
| 요약 | 총 지표 카드(사용자·세션·페이지뷰·평균 세션시간·이탈률), 실시간 접속자, 기기별 비율, 신규 vs 재방문, 유입 경로 TOP 3 |
| 인기 페이지 | GA4 기반 인기 페이지 TOP 20, 각 페이지 평균 체류시간 |
| 방문자 분석 | 일별 방문자 추이 라인 차트, 국가별 TOP 10, 기기별 상세, 유입 경로 TOP 5, 소스/매체 상세 TOP 10 |
| 실시간 | 지금 접속 중인 사용자 수, 현재 보고 있는 페이지 목록 |

---

## 요구 사항

- **Rhymix** 2.x (XE 1.11 이상)
- **PHP** 7.2 이상
- **MySQL** 5.6 이상
- Rhymix `document` 모듈 활성화 (기본 제공 — `document_readed_log` 테이블 사용)
- GA4 통계 사용 시: Google Analytics 4 속성 + Google Cloud OAuth2 앱 (선택)

---

## 설치

1. `counter_plus` 폴더를 `modules/` 디렉토리에 복사
2. Rhymix 관리자 → **설치된 모듈** → `counter_plus` → **설치** 클릭
3. 트리거(`document.updateReadedCount`)가 자동 등록됩니다
4. 관리자 메뉴 → **counter_plus** → 통계 페이지에서 데이터 확인

> GA4 연동은 별도 설정이 필요합니다. `GA4_SETUP.md` 를 참고하세요.

---

## GA4 연동

`GA4_SETUP.md` 참고.  
Google Cloud Console에서 OAuth2 앱을 만들고, 관리자 설정 페이지에서 속성 ID / 클라이언트 ID / 클라이언트 Secret을 입력한 후 구글 계정을 연동합니다.

---

## 파일 구조

```
counter_plus/
├── conf/
│   ├── info.xml          모듈 메타 정보
│   └── module.xml        액션·권한·트리거 정의
├── lib/
│   └── GA4Client.php     GA4 Data API 클라이언트
├── tpl/
│   ├── admin_stats.html  통계 페이지 템플릿
│   └── admin_config.html GA4 설정 페이지 템플릿
├── lang/
│   └── ko.php            한국어 언어 파일
├── counter_plus.class.php
├── counter_plus.controller.php
├── counter_plus.admin.controller.php
├── counter_plus.admin.view.php
├── counter_plus.model.php
├── GA4_SETUP.md          GA4 연동 가이드
├── CHANGELOG.md          변경 이력
└── README.md             이 파일
```

---

##설치 순서

1. counter_plus 폴더 → modules/ 에 복사
2. Rhymix 관리자 → 설치된 모듈 → counter_plus 찾아서 설치 클릭
3. 끝

DB 테이블 생성 불필요한 이유
counter_plus는 자체 테이블을 만들지 않습니다. document_readed_log, documents, modules, member — 전부 Rhymix 코어 테이블이라 이미 존재합니다. 그래서 schemas 폴더가 비어 있는 것이고, 별도 DB 작업이 없습니다.

트리거는?
module.xml에 <eventHandler>를 추가해뒀기 때문에 2번 설치 클릭 시 Rhymix가 자동으로 document.updateReadedCount 트리거를 등록합니다.

---
단, GA4 통계는 설치 후 관리자 → counter_plus → GA4 설정에서 속성 ID와 OAuth2 계정 연동을 별도로 해야 뜹니다. Rhymix 조회수 통계는 설치 즉시 바로 사용 가능합니다.

## 라이선스

MIT License — 자유롭게 사용·수정·배포 가능합니다.

## 제작

**불패의초인 (BSplus)** — https://bsplus.net/
