/* ddggshop · skinGuide — 안내형 오버레이 로직 (순수 클라이언트, 스킨 동작에 영향 없음) */
(function () {
  'use strict';

  // 영역 → 라벨 매핑 (각 셀렉터의 "첫 번째" 요소에만 라벨을 단다)
  var REGIONS = [
    { sel: '.ds-head',     tone: 'head', n: '1',  file: '_head.html',  t: '헤더 · 로고/검색/장바구니', v: '$shop_name $shop_logo $cart_count $is_logged' },
    { sel: '.ds-nav',      tone: 'head', n: '1·', file: '_head.html',  t: '카테고리 GNB(메가메뉴)',   v: '$nav_menu→children' },
    { sel: '.ds-banners',  tone: 'home', n: '2',  file: 'index.html',  t: '메인 배너',                 v: '$banners(->image,->link)' },
    { sel: '.ds-hero',     tone: 'home', n: '2',  file: 'index.html',  t: '히어로(배너 없을 때)',      v: '$shop_name' },
    { sel: '.ds-home',     tone: 'home', n: '3',  file: 'index.html',  t: '홈 블록 영역',              v: '$home_blocks' },
    { sel: '.ds-section',  tone: 'home', n: '3·', file: 'index.html',  t: '상품 블록(블록 1개)',       v: '$blk->block / $blk->items' },
    { sel: '.ds-card',     tone: 'card', n: '4',  file: 'index·list.html', t: '상품 카드',             v: '$item(->item_name,->price,->thumb,->tags)' },
    { sel: 'footer',       tone: 'foot', n: '0',  file: '_foot.html',  t: '푸터(컨테이너 닫힘)',       v: '$shop_name' }
  ];

  // act → 페이지 콘텐츠 템플릿 (현재 페이지 .ds-page 래퍼에 동적 라벨)
  var PAGE_MAP = {
    dispDdggshopIndex:         { file: 'index.html',        t: '홈',           v: '$banners $home_blocks' },
    dispDdggshopProductList:   { file: 'list.html',         t: '상품 목록',     v: '$items $page_navigation $category $tags' },
    dispDdggshopProductDetail: { file: 'detail.html',       t: '상품 상세',     v: '$item $option_groups $reviews $qnas' },
    dispDdggshopCart:          { file: 'cart.html',         t: '장바구니',     v: '$cart_list $summary' },
    dispDdggshopOrder:         { file: 'order.html',        t: '주문서',       v: '$cart_list $addresses $client_key $use_point' },
    dispDdggshopOrderResult:   { file: 'order_result.html', t: '주문 완료',     v: '$order $order_items' },
    dispDdggshopOrderLookup:   { file: 'order_lookup.html', t: '비회원 주문조회', v: '$order' },
    dispDdggshopMyPage:        { file: 'my_page.html',      t: '마이페이지',    v: '$summary $recent_orders' },
    dispDdggshopMyOrders:      { file: 'my_orders.html',    t: '마이 · 주문내역', v: '$orders $page_navigation' },
    dispDdggshopMyAddresses:   { file: 'my_addresses.html', t: '마이 · 배송지',  v: '$addresses' },
    dispDdggshopMyCoupons:     { file: 'my_coupons.html',   t: '마이 · 쿠폰',   v: '$coupons' },
    dispDdggshopMyWishlist:    { file: 'my_wishlist.html',  t: '마이 · 위시리스트', v: '$items' }
  };

  function currentAct() {
    var a;
    try { a = new URLSearchParams(location.search).get('act'); } catch (e) {}
    if (a) return a;
    // 라우트 재작성(/item/123, /list/..) 대비: 경로로 추론
    var p = location.pathname;
    if (/\/item\//.test(p)) return 'dispDdggshopProductDetail';
    if (/\/list\//.test(p)) return 'dispDdggshopProductList';
    return 'dispDdggshopIndex'; // act 없으면 홈
  }

  function annotate() {
    // 현재 페이지 콘텐츠 래퍼 라벨 (.ds-page)
    var page = document.querySelector('.ds-page');
    var info = PAGE_MAP[currentAct()];
    if (page && info && !page.classList.contains('sg-region')) {
      page.classList.add('sg-region');
      page.setAttribute('data-sg-tone', 'page');
      var pt = document.createElement('span');
      pt.className = 'sg-tag sg-r';
      pt.innerHTML = '<span class="f">▣ ' + info.file + '</span> ' + esc(info.t) +
        '<span class="v">' + esc(info.v) + '</span>';
      page.insertBefore(pt, page.firstChild);
    }

    REGIONS.forEach(function (r) {
      var el = document.querySelector(r.sel);
      if (!el || el.classList.contains('sg-region')) return;
      el.classList.add('sg-region');
      el.setAttribute('data-sg-tone', r.tone);
      var tag = document.createElement('span');
      tag.className = 'sg-tag';
      tag.innerHTML = '<span class="f">' + r.n + ' · ' + r.file + '</span> ' +
        esc(r.t) + '<span class="v">' + esc(r.v) + '</span>';
      el.insertBefore(tag, el.firstChild);
    });
  }

  function esc(s) {
    return String(s).replace(/[&<>]/g, function (c) {
      return c === '&' ? '&amp;' : c === '<' ? '&lt;' : '&gt;';
    });
  }

  // 상단 안내 배너
  function banner() {
    var host = document.querySelector('.ddggshop');
    if (!host || host.querySelector('.sg-banner')) return;
    var b = document.createElement('div');
    b.className = 'sg-banner';
    b.innerHTML = '📘 <b>스킨 제작 가이드</b> — 점선 박스와 라벨이 각 영역을 그리는 ' +
      '<code>템플릿 파일 · 변수</code>를 알려줍니다. 새 스킨은 <code>skins/skinGuide</code>를 복사해 시작하세요. ' +
      '<button type="button" class="sg-banner-x" onclick="ddggSGToggle(0)">가이드 끄기</button>';
    host.insertBefore(b, host.firstChild);
  }

  // 우측 하단 토글 + 패널
  function controls() {
    if (document.querySelector('.sg-fab')) return;

    var fab = document.createElement('button');
    fab.type = 'button';
    fab.className = 'sg-fab';
    fab.innerHTML = '<span class="dot"></span> 스킨 가이드';
    fab.onclick = function () { document.querySelector('.sg-panel').classList.toggle('open'); };

    var panel = document.createElement('div');
    panel.className = 'sg-panel';
    panel.innerHTML = PANEL_HTML;
    document.body.appendChild(panel);
    document.body.appendChild(fab);
  }

  var PANEL_HTML =
    '<h3>📘 스킨 제작 가이드 <button type="button" class="sg-x" onclick="this.closest(\'.sg-panel\').classList.remove(\'open\')">×</button></h3>' +

    '<div class="sg-sec"><h4>이 스킨은?</h4>' +
    '<div class="sg-kv">기본 스킨과 <b>동일하게 동작</b>하는 안내형 스킨입니다. 화면 위 점선/라벨로 어느 ' +
    '<code>템플릿·변수</code>가 그 영역을 그리는지 보여줍니다. 가이드 표시는 우측 하단 버튼으로 끄고 켤 수 있어요(브라우저에 기억됨).</div></div>' +

    '<div class="sg-sec"><h4>새 스킨 만들기</h4><ol class="sg-steps">' +
    '<li><code>skins/skinGuide/</code> 폴더를 통째로 복사 → <code>skins/내스킨/</code></li>' +
    '<li><code>skin.xml</code>의 <code>&lt;title&gt;</code> 변경, 필요한 <code>&lt;extra_vars&gt;</code> 정의</li>' +
    '<li>가이드가 필요 없으면 <code>_head.html</code>에서 <code>guide.css·guide.js</code> load 두 줄 삭제</li>' +
    '<li>템플릿 / <code>css/shop.css</code> / <code>js/shop.js</code> 자유롭게 수정</li>' +
    '<li>관리자 → 쇼핑몰 모듈설정 → 스킨에서 <code>내스킨</code> 선택</li></ol></div>' +

    '<div class="sg-sec"><h4>템플릿 구성</h4>' +
    file('_head.html', '헤더 · GNB · 검색/드로어/로그인 모달 · 컨테이너 시작') +
    file('_foot.html', '푸터 · 컨테이너 닫힘 (모든 페이지 공통)') +
    file('index.html', '홈 — 배너 + 홈 블록 빌더($home_blocks)') +
    file('list.html', '상품 목록 — 카테고리/검색/페이징') +
    file('detail.html', '상품 상세 — 옵션·수량·리뷰·문의') +
    file('cart.html', '장바구니') +
    file('order.html', '주문서 — 배송지·결제(토스/무통장)') +
    file('order_result.html', '주문 완료') +
    file('order_lookup.html', '비회원 주문조회') +
    file('my_page.html', '마이페이지 대시보드') +
    file('my_orders · my_addresses · my_coupons · my_wishlist', '마이페이지 하위 탭') +
    file('_mypage_tabs.html', '마이페이지 탭 네비(조각)') +
    file('_home_editor.html', '관리자 전용 홈 라이브편집 오버레이') +
    '</div>' +

    '<div class="sg-sec"><h4>공통 변수 계약 (모든 페이지)</h4><div class="sg-kv">' +
    '<code>$shop_name</code> <code>$shop_logo</code> <code>$shop_mid</code> · ' +
    '<code>$primary_color</code> <code>$primary_color_dark</code>(강조색) · ' +
    '<code>$is_logged</code> <code>$logged_nick</code> <code>$is_site_admin</code> <code>$is_shop_admin</code> · ' +
    '<code>$cart_count</code> · <code>$nav_menu</code>(메가메뉴) <code>$cat_flat</code>(평면 카테고리) · ' +
    '<code>$grid_class</code>(목록 열수) <code>$price_hidden_text</code></div></div>' +

    '<div class="sg-sec"><h4>상품($item) 객체</h4><div class="sg-kv">' +
    '<code>item_srl</code> <code>item_name</code> <code>price</code> <code>thumb</code> ' +
    '<code>status</code>(sale/soldout) <code>use_stock</code>/<code>stock</code> ' +
    '<code>can_view_price</code>(회원공개가) <code>tags</code>(->title,->badge_color,->show_badge)</div></div>' +

    '<div class="sg-note">⚠️ 라이믹스 템플릿 규칙: <code>{@ }</code> 블록 안엔 중괄호 금지 → ' +
    '<code>&lt;!--@if--&gt;…&lt;!--@end--&gt;</code>, <code>loop=</code>, <code>cond=</code> 사용. ' +
    '인라인 <code>&lt;script&gt;</code>의 <code>src=</code> 문자열은 컴파일러가 경로를 재작성하니 ' +
    'base64 미리보기는 <code>createElement(\'img\')</code>로 처리하세요.</div>';

  function file(name, desc) {
    return '<div class="sg-file"><code>' + name + '</code><span class="d">' + desc + '</span></div>';
  }

  // 토글 (전역 노출 — 배너/버튼에서 호출)
  window.ddggSGToggle = function (on) {
    if (typeof on === 'undefined') on = !document.body.classList.contains('sg-on');
    document.body.classList.toggle('sg-on', !!on);
    try { localStorage.setItem('ddgg_sg', on ? '1' : '0'); } catch (e) {}
  };

  document.addEventListener('DOMContentLoaded', function () {
    annotate();
    banner();
    controls();
    var saved;
    try { saved = localStorage.getItem('ddgg_sg'); } catch (e) {}
    document.body.classList.toggle('sg-on', saved !== '0'); // 기본 ON
    document.querySelector('.sg-fab').onclick = function (ev) {
      // 클릭: 패널 토글 / Alt+클릭: 가이드 표시 토글
      if (ev.altKey) { window.ddggSGToggle(); return; }
      document.querySelector('.sg-panel').classList.toggle('open');
    };
  });
})();
