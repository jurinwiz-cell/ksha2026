# ddggshop 스토어프론트 스킨 제작 가이드 (skinGuide)

이 스킨은 **기본 스킨과 똑같이 동작**하면서, 화면 위에 "어느 템플릿·변수가 이 영역을 그리는가"를
점선 박스와 라벨로 보여주는 **안내형(reference) 스킨**입니다. 새 스킨은 이 폴더를 복사해 시작하세요.

## 새 스킨 만들기
1. `skins/skinGuide/` 폴더 전체 복사 → `skins/<내스킨>/`
2. `skin.xml`의 `<title>` 변경, 필요한 `<extra_vars>` 정의
3. 가이드가 필요 없으면 `_head.html`에서 아래 두 줄 삭제 → 평범한 스킨이 됨
   ```html
   <load target="css/guide.css" />
   <load target="js/guide.js" />
   ```
   (그리고 `css/guide.css`, `js/guide.js`, 이 문서 삭제)
4. 템플릿 / `css/shop.css` / `js/shop.js` 자유롭게 수정
5. 관리자 → 쇼핑몰 모듈설정 → **스킨**에서 `<내스킨>` 선택

가이드 표시는 화면 우측 하단 **「스킨 가이드」** 버튼으로 켜고 끌 수 있습니다(브라우저에 상태 저장).
패널 안 버튼은 클릭=패널 열기, **Alt+클릭**=가이드 점선/라벨 토글.

## 템플릿 구성
| 파일 | 역할 |
|------|------|
| `_head.html` | 헤더·GNB(메가메뉴)·검색/드로어/로그인 모달 · 컨테이너 시작(`.ddggshop`,`.ds-page`) |
| `_foot.html` | 푸터 · 컨테이너 닫힘 (모든 페이지 공통) |
| `index.html` | 홈 — 배너 + 홈 블록 빌더(`$home_blocks`) |
| `list.html` | 상품 목록 — 카테고리/검색/페이징 |
| `detail.html` | 상품 상세 — 옵션·수량·리뷰·문의 |
| `cart.html` | 장바구니 |
| `order.html` | 주문서 — 배송지·결제(토스/무통장) |
| `order_result.html` | 주문 완료 |
| `order_lookup.html` | 비회원 주문조회 |
| `my_page.html` | 마이페이지 대시보드 |
| `my_orders / my_addresses / my_coupons / my_wishlist .html` | 마이페이지 하위 탭 |
| `_mypage_tabs.html` | 마이페이지 탭 네비(조각) |
| `_home_editor.html` | 관리자 전용 홈 라이브편집 오버레이 |

## 공통 변수 계약 (모든 페이지)
- 상점: `$shop_name` `$shop_logo` `$shop_mid` `$price_hidden_text`
- 강조색: `$primary_color` `$primary_color_dark` (관리자 모듈설정의 값 — `_head`에서 `--ds-accent`로 주입)
- 로그인: `$is_logged` `$logged_nick` `$is_site_admin` `$is_shop_admin`
- 장바구니/메뉴: `$cart_count` · `$nav_menu`(메가메뉴 트리) · `$cat_flat`(평면 카테고리) · `$grid_class`(목록 열수)

## 상품(`$item`) 객체
`item_srl` `item_name` `price` `thumb` `status`(sale/soldout) `use_stock`/`stock`
`can_view_price`(회원공개가 노출 여부) `tags`(→`title`,`badge_color`,`show_badge`)

## 라이믹스 템플릿 규칙 (필수)
- `{@ }` 블록 안엔 **중괄호 금지** → `<!--@if-->…<!--@end-->`, `loop=`, `cond=` 사용
- 인라인 `<script>` 안의 `src="..."` / `href="..."` 문자열은 컴파일러가 경로를 재작성함 →
  base64 미리보기 등은 `createElement('img'); img.src=...` 로 처리 (문자열 `'<img src="'+...'` 금지)
- 변수 출력은 `{$var}`, URL은 `{getUrl('','mid',$shop_mid,'act','...')}`

> ⚠️ **데이터 바인딩 지점(변수명·폼 action·필드명)** 만 유지하면 마크업·CSS·구조는 마음대로 바꿔도 됩니다.
