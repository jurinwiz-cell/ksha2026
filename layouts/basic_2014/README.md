# [XE-Basic](https://yjsoft.github.io/XEcontest2014-templateA-Basic/basic/basic.main.html)
XpressEngine에서 제공하는 템플릿A는 종이와 카드를 메타포로 한 기본형 템플릿입니다.

## XE Contest 2014
이 템플릿은 [XE Contest 2014](https://web.archive.org/web/20150220000037/http://contest.xpressengine.com/)를 위해 제작되었습니다.
디자인과 마크업까지 함께 제공되어 바로 레이아웃 또는 스킨 제작에 활용하여 출품할 수 있습니다.

## View page
미리보기 페이지를 참고하여 다운받기 전 사용해 볼 수 있습니다.
https://yjsoft.github.io/XEcontest2014-templateA-Basic/basic/basic.main.html


## License
제공된 웹 템플릿은 아래의 라이선스를 준수해야 합니다.
아래 라이선스는 템플릿 이용자의 저작물에 적용하여, 이용자의 권리 또한 보장합니다.

| 항목 | 라이선스 | 내용 |
| ------------ | ------------ | ----- |
| IMAGES & DESIGN | CCL          | 저작 출처(xpressengine.com) 표기, 수정가능, 영리 목적 이용 가능, 동일조건변경허락   |
|  HTML, CSS & JS    | LGPL         | 마크업 소스코드에는 오픈소스SW라이선스 LGPL를 적용합니다.   |
| FONT            | OFL          | 영리 목적이 아닌 한, 사용되는 폰트의 사용, 연구, 수정 및 재배포를 자유롭게 허락합니다.   |

## 라이믹스(Rhymix) 레이아웃 포팅본

이 저장소에는 위 원본 데모를 실제 동작하는 라이믹스 레이아웃/위젯 스킨으로
포팅한 결과물이 들어 있습니다.

포팅 과정에서 원본이 LGPL로 표기했던 코드 라이선스를, 라이믹스 배포 관례에
맞춰 **GPLv2**로 전환해 적용했습니다(그 외 이미지/폰트 라이선스는 원본과
동일하게 유지).

| 항목 | 대상 | 라이선스 |
| ------------ | ------------ | ------------ |
| 소스 코드 | HTML/Blade 템플릿(`layout.blade.php`, `parts/*.blade.php`, `login_form.blade.php`), CSS, JavaScript, `info.xml`/`skin.xml` | [GNU GPLv2](LICENSE) (원 저작권 NAVER 유지 + 수정자 YJSoft 표기, [COPYRIGHT](COPYRIGHT) 참고) |
| 디자인 에셋 | 이미지, 아이콘, 시각적 레이아웃 디자인 | [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/) (원본과 동일) |
| 웹 폰트 | `basic.layout.webfont.css`가 불러오는 나눔고딕, xeicon | [SIL Open Font License](https://scripts.sil.org/OFL) (원본과 동일) |

다음 두 파일은 원본 데모에 포함된 서드파티 라이브러리로, 위 GPLv2 전환
대상이 아니라 각자의 원 라이선스를 그대로 유지합니다(각 파일 상단 주석
참고).

- `layouts/basic/js/camera.js` — Camera slideshow v1.3.3, MIT License
- `layouts/basic/js/jquery.easing.1.3.js` — jQuery Easing v1.3, 자체 이용약관

## 설치

1. 현 디렉터리 전체를 라이믹스 설치 경로의 `layouts/basic_2014/`에 복사합니다.
2. 관리자 페이지 → **레이아웃 관리**에서 "Basic 2014" 레이아웃이 새로 인식되는지
   확인한 뒤, 원하는 모듈(도메인 전체 또는 특정 게시판/페이지 등)에 이
   레이아웃을 배정합니다.
3. 레이아웃 설정 화면에서 아래 표의 옵션들을 채웁니다. 특히 **메뉴**는
   관리자 → **메뉴 관리**에서 이 레이아웃이 요구하는 3개의 메뉴
   (`GNB`/`FOOTER_MENU`/`FOOTER_BOTTOM`)를 먼저 등록해 레이아웃 설정
   화면에서 연결해야 헤더/푸터 메뉴가 나타납니다.

## 옵션 안내

메뉴 (관리자 → 메뉴 관리에서 등록 후 레이아웃 설정에서 연결):

| 메뉴 | 최대 깊이 | 용도 |
| --- | --- | --- |
| `GNB` | 3단계 | 상단 전역 네비게이션. LNB/빵조각도 이 메뉴의 "현재 선택된 항목"을 기준으로 자동 계산되므로 별도 메뉴가 필요 없음 |
| `FOOTER_MENU` | 2단계 | 푸터 사이트맵(다단 목록) |
| `FOOTER_BOTTOM` | 1단계 | 푸터 최하단 한 줄 메뉴(이용약관/개인정보처리방침 등) |

레이아웃 확장변수(전부 관리자 → 레이아웃 관리 → 이 레이아웃의 설정 화면에서 입력):

| 옵션 | 설명 |
| --- | --- |
| `LOGO_IMG` / `LOGO_TEXT` | 헤더 로고. 이미지 → 문자 → 사이트 이름 순으로 폴백 |
| `LOGO_URL` | 헤더 로고(`#siteTitle`) 연결 주소. 비우면 사이트 루트(`getUrl('')`) |
| `COLUMN_TYPE` | `FULL`(1단) / `LEFT`(2단, 좌측 LNB) / `RIGHT`(2단, 우측 LNB) |
| `PAGE_TYPE` | `MAIN`(비주얼 슬라이더) / `SUB`(빵조각). 페이지별로 수동 지정 — 자동 감지는 하지 않음 |
| `VISUAL_IMAGE_1~4` / `VISUAL_TITLE_1~4` / `VISUAL_DESC_1~4` | `PAGE_TYPE=MAIN`일 때 슬라이더 4장의 이미지/제목/설명. 비워두면 데모 기본 이미지·문구로 폴백 |
| `SUB_VISUAL_BG_USE` / `SUB_VISUAL_BG_IMAGE` | `PAGE_TYPE=SUB`일 때 상단 빵조각 배경. 사용 안 함이거나 이미지 미지정 시 기본 배경으로 폴백 |
| `LOGIN_MODAL_USE` | `Y`면 하단에 로그인 모달(오버레이) 렌더링, `N`이면 헤더 로그인 링크가 별도 로그인 페이지로 이동(모달 자체는 렌더링 안 됨) |
| `LOGIN_MODAL_LOGO_IMG` | 로그인 모달 상단 로고(비우면 기본 XE 로고) |
| `LNB_TOP_IMAGE_USE` / `LNB_TOP_IMAGE` | `COLUMN_TYPE=LEFT/RIGHT`일 때 LNB 상단 썸네일 이미지 |
| `WEB_FONT` | `Y`면 구글 웹폰트(나눔고딕 얼리액세스 CDN) 적용 |
| `colorset` | 10종 컬러 테마(기본 Royal Classic 포함). 관리자가 지정하는 기본값 |
| `COLORSET_DEMO_USE` | `Y`면 GNB 마지막에 컬러테마 변경 메뉴 추가. 방문자가 고른 테마는 `rx_layout_colorset` 쿠키(1년)에 저장되어 페이지 이동 후에도 유지되며, `colorset` 관리자 기본값보다 우선 적용됨. 전환 시 `body`에 이미 붙어 있는 다른 클래스는 건드리지 않고 `color_*` 클래스만 교체 |
| `FOOTER_LOGO_IMG` / `FOOTER_LOGO_TEXT` | 푸터 로고. 헤더와 동일하게 이미지 → 문자 → 사이트 이름 순 폴백 |
| `FOOTER_LOGO_URL` | 푸터 로고(`.f_logo`) 연결 주소. 비우면 사이트 루트(`getUrl('')`) |
| `FOOTER_SITE_NAME` / `FOOTER_SITE_URL` | 푸터의 사이트명 문구 및 연결 링크 |
| `SNS_FACEBOOK_URL` / `SNS_TWITTER_URL` / `SNS_LINKEDIN_URL` / `SNS_GITHUB_URL` / `SNS_RSS_URL` / `SNS_YOUTUBE_URL` | 푸터 SNS 아이콘 6종. 각각 링크를 비우면 해당 아이콘 자체가 숨겨짐 |
| `FOOTER_COPYRIGHT` | 푸터 최하단 저작권 문구(HTML 허용, 링크 삽입 가능) |

## 로그인 모달

`LOGIN_MODAL_USE=Y`로 설정하면 헤더 로그인 링크가 `widgets/login_info/skins/basic/`
위젯 스킨을 오버레이 모달로 띄웁니다. 아래 두 모듈은 **설치돼 있지 않아도
동작**하며(가드 처리됨), 설치돼 있으면 해당 UI가 모달 안에 자동으로
추가됩니다.

- **sociallogin** 모듈 — 활성화된 소셜 로그인 서비스가 있으면 버튼 목록이 표시됨
- **spamfilter**(코어 번들) — 로그인 폼에 캡차 설정이 켜져 있으면 캡차가 표시됨
