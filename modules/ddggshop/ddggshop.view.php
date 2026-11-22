<?php
/**
 * 쇼핑몰 - 프론트 뷰
 * @author 딱따고기 (https://ddgglabs.org)
 */
class ddggshopView extends ddggshop
{
	/** @var object 모듈 설정 */
	protected $shop_config;

	/** @var bool 콘솔 접근 권한 */
	protected $console_admin = false;

	public function init()
	{
		$this->shop_config = ddggshopModel::getConfig();

		// 관리자 콘솔(별도 레이아웃) 분기
		if (strpos((string)Context::get('act'), 'dispDdggshopConsole') === 0)
		{
			$this->initConsole();
			return;
		}

		// 레이아웃 모드 (관리자 설정 > 레이아웃 설정)
		//  - standalone(기본): 쇼핑몰이 자체 헤더/푸터로 전체 화면을 구성 → 사이트 레이아웃 강제 해제
		//  - embedded: 메뉴에 지정한 라이믹스 레이아웃 안에 포함 → 자체 헤더(로고/로그인)·푸터는 숨김
		$layout_mode = (($this->shop_config->layout_mode ?? 'standalone') === 'embedded') ? 'embedded' : 'standalone';
		if ($layout_mode !== 'embedded')
		{
			Context::set('layout', 'none');
		}
		Context::set('shop_embedded', $layout_mode === 'embedded' ? 1 : 0);

		// 스킨 경로
		$skin = $this->module_info->skin ?? '';
		if (!$skin || $skin === '/USE_DEFAULT/')
		{
			$skin = ModuleModel::getModuleDefaultSkin('ddggshop', 'P') ?: 'default';
		}
		$skin_path = sprintf('%sskins/%s/', $this->module_path, $skin);
		if (!is_dir($skin_path))
		{
			$skin_path = $this->module_path . 'skins/default/';
		}
		$this->setTemplatePath($skin_path);

		$this->assignCommon();
	}

	/* ============================================================
	 *  관리자 콘솔 (별도 레이아웃 — module=ddggshop 프론트 액션)
	 * ============================================================ */

	/** 콘솔 공통 초기화: 독립 레이아웃 + 권한 + 사이드바 데이터 */
	protected function initConsole()
	{
		$this->setTemplatePath($this->module_path . 'tpl_console/');
		$this->setLayoutPath($this->module_path . 'tpl_console/');
		$this->setLayoutFile('console_layout');

		$mid = $this->module_info->mid;
		Context::set('shop_mid', $mid);
		$logged = Context::get('logged_info');
		$this->console_admin = ($logged && ((isset($logged->is_admin) && $logged->is_admin === 'Y') || !empty($this->grant->manager)));
		Context::set('is_site_admin', ($logged && isset($logged->is_admin) && $logged->is_admin === 'Y') ? 1 : 0);

		Context::set('shop_name', $this->shop_config->shop_name);
		Context::set('shop_logo', $this->shop_config->logo_image);
		$nick = $logged ? $logged->nick_name : '관리자';
		Context::set('console_nick', $nick);
		Context::set('console_email', $logged ? $logged->email_address : '');
		Context::set('console_ava', mb_substr($nick, 0, 1, 'UTF-8'));
		Context::set('console_logochar', mb_substr($this->shop_config->shop_name, 0, 1, 'UTF-8') ?: 'S');
		Context::set('shop_home_url', getUrl('', 'mid', $mid, 'act', 'dispDdggshopIndex'));
		Context::set('rx_admin_url', getUrl('', 'module', 'admin', 'act', 'dispAdminIndex'));
		Context::set('login_url', getUrl('act', 'dispMemberLoginForm'));

		// 사이드바 메뉴 URL
		Context::set('u_dashboard', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsole'));
		Context::set('u_orders', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleOrders'));
		Context::set('u_orderview', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleOrderView'));
		Context::set('u_stats', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleStats'));
		Context::set('u_qna', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleQna'));
		Context::set('u_category', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleCategory'));
		Context::set('u_items', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleItems'));
		Context::set('u_homeblocks', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleHomeBlocks'));
		Context::set('u_homeblockform', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleHomeBlockForm'));
		Context::set('u_badges', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleBadges'));
		Context::set('u_coupon', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleCoupon'));
		Context::set('u_config', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleConfig'));
		Context::set('u_itemform', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleItemForm'));
		Context::set('u_claims', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleClaims'));

		// 배지 카운트 (처리대기 주문 / 미답변 문의 / 교환·반품 신청)
		if ($this->console_admin)
		{
			$po = ddggshopModel::getOrderList((object) array('status' => 'pending', 'list_count' => 1));
			Context::set('cnt_pending', $po->total_count);
			$qo = ddggshopModel::getQnaListAdmin((object) array('answered_n' => 'Y', 'list_count' => 1));
			Context::set('cnt_qna', ($qo && isset($qo->total_count)) ? $qo->total_count : 0);
			Context::set('cnt_claims', ddggshopModel::countClaims('requested'));
		}

		Context::set('console_btn_label', '');
		Context::set('console_btn_url', '');
		Context::set('layout_info', null);
		Context::set('browser_title', '쇼핑몰 관리자 콘솔');
	}

	/** 콘솔 페이지 공통 헤더값 + 권한 가드. 권한 없으면 false */
	protected function consoleGuard($page, $crumb, $title)
	{
		// 렌더 직전 경로 재확정 (스토어프론트 init 등이 덮어쓰는 경우 대비)
		$this->setTemplatePath($this->module_path . 'tpl_console/');
		$this->setLayoutPath($this->module_path . 'tpl_console/');
		$this->setLayoutFile('console_layout');
		if (!$this->console_admin)
		{
			$this->setTemplateFile('console_denied');
			return false;
		}
		Context::set('console_page', $page);
		Context::set('console_crumb', $crumb);
		Context::set('console_title', $title);
		return true;
	}

	/** 콘솔 대시보드 */
	public function dispDdggshopConsole()
	{
		if (!$this->consoleGuard('dashboard', 'HOME', '대시보드')) return;

		$paid = array('paid', 'preparing', 'shipping', 'delivered');
		Context::set('sum_all', ddggshopModel::getSalesSummary((object) array('status_list' => $paid)));
		Context::set('sum_today', ddggshopModel::getSalesSummary((object) array('status_list' => $paid, 'from_date' => date('Ymd000000'))));
		Context::set('sum_month', ddggshopModel::getSalesSummary((object) array('status_list' => $paid, 'from_date' => date('Ym') . '01000000')));

		$pending = ddggshopModel::getOrderList((object) array('status' => 'pending', 'list_count' => 1));
		$ready = ddggshopModel::getOrderList((object) array('status' => 'paid', 'list_count' => 1));
		$soldout = ddggshopModel::getItemList((object) array('status' => 'soldout', 'list_count' => 1));
		$items = ddggshopModel::getItemList((object) array('list_count' => 1));
		Context::set('cnt_pending', $pending->total_count);
		Context::set('cnt_ready', $ready->total_count);
		Context::set('cnt_soldout', $soldout->total_count);
		Context::set('cnt_items', $items->total_count);

		$recent = ddggshopModel::getOrderList((object) array('list_count' => 8));
		$recent_orders = $recent->toBool() ? ($recent->data ?: array()) : array();
		foreach ($recent_orders as $o) $o->status_label = ddggshopModel::statusLabel($o->status);
		Context::set('recent_orders', $recent_orders);

		// 최근 7일 일별 매출 바차트
		$bars = array();
		$maxv = 1;
		for ($i = 6; $i >= 0; $i--)
		{
			$d = date('Ymd', time() - $i * 86400);
			$s = ddggshopModel::getSalesSummary((object) array('status_list' => $paid, 'from_date' => $d . '000000', 'to_date' => $d . '235959'));
			$amt = (int)$s->total_sales;
			if ($amt > $maxv) $maxv = $amt;
			$bars[] = (object) array('d' => (int)substr($d, 6, 2) . '일', 'amt' => $amt, 'man' => round($amt / 10000));
		}
		foreach ($bars as $b) $b->h = max(4, (int)round($b->amt / $maxv * 130));
		Context::set('bars', $bars);

		$this->setTemplateFile('console_dashboard');
	}

	/** 콘솔: 주문내역 */
	public function dispDdggshopConsoleOrders()
	{
		if (!$this->consoleGuard('orders', '주문 관리', '주문내역')) return;
		$args = new stdClass;
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 20;
		$status = trim((string)Context::get('status'));
		if (in_array($status, array('pending', 'paid', 'preparing', 'shipping', 'delivered', 'cancelled', 'refunded'), true)) $args->status = $status;
		$q = trim((string)Context::get('q'));
		$target = (string)Context::get('search_target') ?: 'orderer_name';
		if ($q !== '')
		{
			if ($target === 'order_code') $args->order_code = $q;
			else $args->orderer_name = $q;
		}
		$pay = (string)Context::get('pay_method');
		if (in_array($pay, array('toss', 'banktransfer'), true)) $args->pay_method = $pay;
		// 기간 (프리셋 또는 날짜범위)
		$period = (string)Context::get('period');
		$from = preg_replace('/[^0-9]/', '', (string)Context::get('from_date'));
		$to = preg_replace('/[^0-9]/', '', (string)Context::get('to_date'));
		if ($period && $from === '' && $to === '')
		{
			$map = array('today' => 0, '7d' => 6, '1m' => 29, '3m' => 89);
			if (isset($map[$period])) $from = date('Ymd', time() - $map[$period] * 86400);
		}
		if ($from !== '') $args->from_date = substr($from . '000000', 0, 14);
		if ($to !== '') $args->to_date = substr($to . '235959', 0, 14);

		$out = ddggshopModel::getOrderList($args);
		$orders = $out->toBool() ? ($out->data ?: array()) : array();
		foreach ($orders as $o) $o->status_label = ddggshopModel::statusLabel($o->status);
		Context::set('orders', $orders);
		Context::set('total_count', $out->total_count);
		Context::set('cur_page', $out->page);
		Context::set('total_page', $out->total_page ?? 1);
		Context::set('cur_status', $status);
		Context::set('q', $q);
		Context::set('search_target', $target);
		Context::set('cur_pay', $pay);
		Context::set('cur_period', $period);
		Context::set('cur_from', $from ? date('Y-m-d', strtotime($from)) : '');
		Context::set('cur_to', $to ? date('Y-m-d', strtotime($to)) : '');
		$this->setTemplateFile('console_orders');
	}

	/** 주문 목록 필터 → 쿼리 args (페이징 제외) */
	protected function buildConsoleOrderArgs()
	{
		$args = new stdClass;
		$status = trim((string)Context::get('status'));
		if (in_array($status, array('pending', 'paid', 'preparing', 'shipping', 'delivered', 'cancelled', 'refunded'), true)) $args->status = $status;
		$q = trim((string)Context::get('q'));
		$target = (string)Context::get('search_target') ?: 'orderer_name';
		if ($q !== '') { if ($target === 'order_code') $args->order_code = $q; else $args->orderer_name = $q; }
		$pay = (string)Context::get('pay_method');
		if (in_array($pay, array('toss', 'banktransfer'), true)) $args->pay_method = $pay;
		$period = (string)Context::get('period');
		$from = preg_replace('/[^0-9]/', '', (string)Context::get('from_date'));
		$to = preg_replace('/[^0-9]/', '', (string)Context::get('to_date'));
		if ($period && $from === '' && $to === '') { $map = array('today' => 0, '7d' => 6, '1m' => 29, '3m' => 89); if (isset($map[$period])) $from = date('Ymd', time() - $map[$period] * 86400); }
		if ($from !== '') $args->from_date = substr($from . '000000', 0, 14);
		if ($to !== '') $args->to_date = substr($to . '235959', 0, 14);
		return $args;
	}

	/** CSV 다운로드 출력 (UTF-8 BOM, Excel 호환) 후 종료 */
	protected function consoleCsv($filename, $rows)
	{
		if (!$this->console_admin) { header('HTTP/1.1 403 Forbidden'); exit('권한이 없습니다.'); }
		while (ob_get_level()) { @ob_end_clean(); }
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-store, no-cache');
		echo "\xEF\xBB\xBF";
		$out = fopen('php://output', 'w');
		foreach ($rows as $r) fputcsv($out, $r);
		fclose($out);
		exit();
	}

	/** 콘솔: 주문내역 엑셀(CSV) 다운로드 (현재 필터 적용) */
	public function dispDdggshopConsoleOrdersExcel()
	{
		if (!$this->console_admin) { header('HTTP/1.1 403 Forbidden'); exit('권한이 없습니다.'); }
		$args = $this->buildConsoleOrderArgs();
		$args->page = 1;
		$args->list_count = 50000;
		$out = ddggshopModel::getOrderList($args);
		$orders = $out->toBool() ? ($out->data ?: array()) : array();
		$rows = array(array('주문번호', '주문자', '연락처', '이메일', '상품금액', '배송비', '할인', '결제금액', '결제수단', '쿠폰', '상태', '주문일시'));
		foreach ($orders as $o)
		{
			$rows[] = array(
				$o->order_code, $o->orderer_name, $o->orderer_phone, $o->orderer_email,
				(int)$o->total_price, (int)$o->delivery_fee, (int)$o->discount_price, (int)$o->payment_price,
				($o->pay_method === 'toss' ? '카드(토스)' : '무통장'),
				isset($o->coupon_code) ? $o->coupon_code : '',
				ddggshopModel::statusLabel($o->status),
				date('Y-m-d H:i', strtotime(substr($o->regdate, 0, 14))),
			);
		}
		$this->consoleCsv('orders_' . date('Ymd_His') . '.csv', $rows);
	}

	/** 콘솔: 주문 통계 (월간/연간/기간별 + 인기상품) */
	public function dispDdggshopConsoleStats()
	{
		if (!$this->consoleGuard('stats', '통계', '주문 통계')) return;
		$paid = array('paid', 'preparing', 'shipping', 'delivered');
		$year = (int)Context::get('year');
		if ($year < 2000 || $year > 2100) $year = (int)date('Y');
		$from = preg_replace('/[^0-9]/', '', (string)Context::get('from_date'));
		$to = preg_replace('/[^0-9]/', '', (string)Context::get('to_date'));
		if ($from === '') $from = $year . '0101';
		if ($to === '') $to = $year . '1231';

		// 월별 (선택 연도 12개월)
		$months = array(); $maxv = 1;
		for ($m = 1; $m <= 12; $m++)
		{
			$last = (int)date('t', mktime(0, 0, 0, $m, 1, $year));
			$mfrom = sprintf('%04d%02d01000000', $year, $m);
			$mto = sprintf('%04d%02d%02d235959', $year, $m, $last);
			$s = ddggshopModel::getSalesSummary((object) array('status_list' => $paid, 'from_date' => $mfrom, 'to_date' => $mto));
			$mcost = ddggshopModel::getCostSum((object) array('status_list' => $paid, 'from_date' => $mfrom, 'to_date' => $mto));
			if ((int)$s->total_sales > $maxv) $maxv = (int)$s->total_sales;
			$months[] = (object) array('m' => $m, 'sales' => (int)$s->total_sales, 'cnt' => (int)$s->order_count, 'profit' => (int)$s->total_sales - (int)$mcost);
		}
		foreach ($months as $mm) $mm->h = max(2, (int)round($mm->sales / $maxv * 150));

		// 연도별 (최근 5년)
		$years = array();
		for ($y = $year - 4; $y <= $year; $y++)
		{
			$s = ddggshopModel::getSalesSummary((object) array('status_list' => $paid, 'from_date' => $y . '0101000000', 'to_date' => $y . '1231235959'));
			$ycost = ddggshopModel::getCostSum((object) array('status_list' => $paid, 'from_date' => $y . '0101000000', 'to_date' => $y . '1231235959'));
			$years[] = (object) array('y' => $y, 'sales' => (int)$s->total_sales, 'cnt' => (int)$s->order_count, 'profit' => (int)$s->total_sales - (int)$ycost);
		}

		// 기간 요약 + 인기상품
		$sum = ddggshopModel::getSalesSummary((object) array('status_list' => $paid, 'from_date' => $from . '000000', 'to_date' => $to . '235959'));
		$cancel = ddggshopModel::getSalesSummary((object) array('status_list' => array('cancelled', 'refunded'), 'from_date' => $from . '000000', 'to_date' => $to . '235959'));
		$period_cost = ddggshopModel::getCostSum((object) array('status_list' => $paid, 'from_date' => $from . '000000', 'to_date' => $to . '235959'));
		Context::set('period_sales', (int)$sum->total_sales);
		Context::set('period_count', (int)$sum->order_count);
		Context::set('period_avg', $sum->order_count ? (int)round($sum->total_sales / $sum->order_count) : 0);
		Context::set('period_cancel', (int)$cancel->order_count);
		Context::set('period_cost', (int)$period_cost);
		Context::set('period_profit', (int)$sum->total_sales - (int)$period_cost);
		$top = ddggshopModel::getTopItems($from . '000000', $to . '235959', 10);
		$n = 1;
		foreach ($top as $t) $t->no = $n++;
		Context::set('top_items', $top);

		Context::set('months', $months);
		Context::set('years', $years);
		Context::set('cur_year', $year);
		Context::set('cur_from', date('Y-m-d', strtotime($from)));
		Context::set('cur_to', date('Y-m-d', strtotime($to)));
		$yopts = array();
		for ($y = (int)date('Y'); $y >= (int)date('Y') - 6; $y--) $yopts[] = $y;
		Context::set('year_options', $yopts);
		$this->setTemplateFile('console_stats');
	}

	/** 콘솔: 통계 엑셀(CSV) — 선택 연도 월별 매출 */
	public function dispDdggshopConsoleStatsExcel()
	{
		if (!$this->console_admin) { header('HTTP/1.1 403 Forbidden'); exit('권한이 없습니다.'); }
		$paid = array('paid', 'preparing', 'shipping', 'delivered');
		$year = (int)Context::get('year');
		if ($year < 2000 || $year > 2100) $year = (int)date('Y');
		$rows = array(array($year . '년 월별 매출'), array('월', '매출', '주문수'));
		$total = 0; $tcnt = 0;
		for ($m = 1; $m <= 12; $m++)
		{
			$last = (int)date('t', mktime(0, 0, 0, $m, 1, $year));
			$s = ddggshopModel::getSalesSummary((object) array('status_list' => $paid, 'from_date' => sprintf('%04d%02d01000000', $year, $m), 'to_date' => sprintf('%04d%02d%02d235959', $year, $m, $last)));
			$rows[] = array($m . '월', (int)$s->total_sales, (int)$s->order_count);
			$total += (int)$s->total_sales; $tcnt += (int)$s->order_count;
		}
		$rows[] = array('합계', $total, $tcnt);
		$this->consoleCsv('stats_' . $year . '_' . date('Ymd_His') . '.csv', $rows);
	}

	/** 콘솔: 상품 엑셀(CSV) 내보내기 (현재 필터 적용) */
	public function dispDdggshopConsoleItemsExcel()
	{
		if (!$this->console_admin) { header('HTTP/1.1 403 Forbidden'); exit('권한이 없습니다.'); }
		$args = new stdClass;
		$args->page = 1; $args->list_count = 50000;
		$status = trim((string)Context::get('status'));
		if (in_array($status, array('sale', 'soldout', 'hidden'), true)) $args->status = $status;
		$q = trim((string)Context::get('q'));
		if ($q !== '') $args->search_keyword = $q;
		$cat = (int)Context::get('category_srl');
		if ($cat) $args->category_srls = ddggshopModel::getDescendantCategorySrls($cat);
		$tag = (int)Context::get('tag_srl');
		if ($tag) { $srls = ddggshopModel::getItemSrlsByTag($tag); $args->item_srls = count($srls) ? $srls : array(0); }

		$out = ddggshopModel::getItemList($args);
		$items = $out->toBool() ? ($out->data ?: array()) : array();
		$cmap = ddggshopModel::getCategoryMap();
		$rows = array(array('item_srl', '상품명', '상품코드', 'category_srl', '카테고리', '판매가', '원가', '재고', '재고관리(Y/N)', '상태(sale/soldout/hidden)', '브랜드', '제조사', '원산지', '배송비유형(default/free/fixed)', '배송비', '적립률'));
		foreach ($items as $it)
		{
			$cn = isset($cmap[$it->category_srl]) ? $cmap[$it->category_srl]->category_name : '';
			$rows[] = array(
				(int)$it->item_srl, $it->item_name, $it->item_code, (int)$it->category_srl, $cn,
				(int)$it->price, (int)$it->cost_price, (int)$it->stock, $it->use_stock, $it->status,
				$it->brand, $it->manufacturer, $it->origin,
				(isset($it->ship_fee_type) && $it->ship_fee_type) ? $it->ship_fee_type : 'default',
				(int)(isset($it->ship_fee) ? $it->ship_fee : 0), (int)$it->point_rate,
			);
		}
		$this->consoleCsv('products_' . date('Ymd_His') . '.csv', $rows);
	}

	/** 콘솔: 주문 상세 */
	public function dispDdggshopConsoleOrderView()
	{
		if (!$this->consoleGuard('orders', '주문 관리 / 주문내역', '주문 상세')) return;
		$order_srl = (int)Context::get('order_srl');
		$order = ddggshopModel::getOrder($order_srl);
		if ($order)
		{
			$order->status_label = ddggshopModel::statusLabel($order->status);
			$order->pay_label = ddggshopModel::payMethodLabel($order->pay_method);
			Context::set('order_items', ddggshopModel::getOrderItemList($order_srl));
			Context::set('order_address', ddggshopModel::getOrderAddress($order_srl));
			Context::set('status_options', array('pending' => '입금대기', 'paid' => '결제완료', 'preparing' => '배송준비', 'shipping' => '배송중', 'delivered' => '배송완료', 'cancelled' => '취소', 'refunded' => '환불'));
			Context::set('console_return', getUrl('', 'mid', $this->module_info->mid, 'act', 'dispDdggshopConsoleOrderView', 'order_srl', $order_srl));
			Context::set('tracking_url', ddggshopModel::trackingUrl($order->shipping_company, $order->shipping_invoice));
			Context::set('carriers', ddggshopModel::carrierList());
			Context::set('default_carrier', $this->shop_config->delivery_company);
			// 교환/반품 클레임 + 상태변경 이력(타임라인)
			$claims = ddggshopModel::getClaimList((object) array('order_srl' => $order_srl, 'list_count' => 50));
			$clist = $claims->toBool() ? ($claims->data ?: array()) : array();
			foreach ($clist as $c) { $c->type_label = ddggshopModel::claimTypeLabel($c->type); $c->status_label = ddggshopModel::claimStatusLabel($c->status); }
			Context::set('order_claims', $clist);
			$logs = ddggshopModel::getOrderLogs($order_srl);
			foreach ($logs as $lg) { $lg->action_label = ddggshopModel::orderLogActionLabel($lg->action); }
			Context::set('order_logs', $logs);
		}
		Context::set('order', $order);
		$this->setTemplateFile('console_order_view');
	}

	/** 콘솔: 주문서 출력 (인쇄용 단독 페이지) */
	public function dispDdggshopConsoleOrderPrint()
	{
		// 관리자 권한만 (콘솔 어드민)
		if (!$this->console_admin)
		{
			Context::set('layout', 'none');
			$this->setTemplatePath($this->module_path . 'tpl_console/');
			$this->setTemplateFile('console_denied');
			return;
		}
		Context::set('layout', 'none');
		$this->setTemplatePath($this->module_path . 'tpl_console/');

		$order_srl = (int)Context::get('order_srl');
		$order = ddggshopModel::getOrder($order_srl);
		if ($order)
		{
			$order->status_label = ddggshopModel::statusLabel($order->status);
			$order->pay_label = ddggshopModel::payMethodLabel($order->pay_method);
			Context::set('order_items', ddggshopModel::getOrderItemList($order_srl));
			Context::set('order_address', ddggshopModel::getOrderAddress($order_srl));
		}
		Context::set('order', $order);
		Context::set('shop_config', $this->shop_config);
		Context::set('shop_name', $this->shop_config->shop_name);
		$this->setTemplateFile('console_order_print');
	}

	/** 콘솔: 상품문의 */
	public function dispDdggshopConsoleQna()
	{
		if (!$this->consoleGuard('qna', '고객', '상품문의')) return;
		$args = new stdClass;
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 20;
		$q = trim((string)Context::get('q'));
		if ($q !== '') $args->search_keyword = $q;
		$ans = (string)Context::get('answered');
		if ($ans === 'answered') $args->answered_y = 'Y';
		elseif ($ans === 'unanswered') $args->answered_n = 'Y';
		$cat = (int)Context::get('category_srl');
		if ($cat) $args->category_srls = ddggshopModel::getDescendantCategorySrls($cat);
		$period = (string)Context::get('period');
		$from = preg_replace('/[^0-9]/', '', (string)Context::get('from_date'));
		$to = preg_replace('/[^0-9]/', '', (string)Context::get('to_date'));
		if ($period && $from === '' && $to === '')
		{
			$map = array('today' => 0, '7d' => 6, '1m' => 29, '3m' => 89);
			if (isset($map[$period])) $from = date('Ymd', time() - $map[$period] * 86400);
		}
		if ($from !== '') $args->reg_from = substr($from . '000000', 0, 14);
		if ($to !== '') $args->reg_to = substr($to . '235959', 0, 14);

		$out = ddggshopModel::getQnaListAdmin($args);
		$qnas = $out->toBool() ? ($out->data ?: array()) : array();
		Context::set('qnas', $qnas);
		Context::set('total_count', $out->total_count);
		Context::set('cur_page', $out->page);
		Context::set('total_page', $out->total_page ?? 1);
		Context::set('q', $q);
		Context::set('cur_answered', $ans);
		Context::set('cur_cat', $cat);
		Context::set('cur_period', $period);
		Context::set('cur_from', $from ? date('Y-m-d', strtotime($from)) : '');
		Context::set('cur_to', $to ? date('Y-m-d', strtotime($to)) : '');
		Context::set('filter_cats', ddggshopModel::getCategoryTreeOrdered());
		$this->setTemplateFile('console_qna');
	}

	/** 콘솔: 카테고리관리 */
	public function dispDdggshopConsoleCategory()
	{
		if (!$this->consoleGuard('category', '상품 분류', '카테고리관리')) return;
		$tree = ddggshopModel::getCategoryTreeOrdered();
		Context::set('categories', $tree);
		$parents = array();
		foreach ($tree as $c) { if ((int)$c->depth < 2) $parents[] = $c; }
		Context::set('parent_categories', $parents);
		Context::set('show_category_count', ($this->shop_config->show_category_count ?? 'N') === 'Y' ? 'Y' : 'N');
		// 카테고리명 다국어 입력: 다국어 사용 시 기본 언어를 제외한 활성 언어들
		$cat_langs = array();
		if (($this->shop_config->use_multilang ?? 'N') === 'Y')
		{
			$default_lang = ddggshopModel::getDefaultLang($this->shop_config);
			foreach (ddggshopModel::getEnabledLanguages($this->shop_config) as $code => $name)
			{
				if ($code === $default_lang) continue;
				$cat_langs[] = (object) array('code' => $code, 'name' => $name);
			}
		}
		Context::set('cat_langs', $cat_langs);
		$this->setTemplateFile('console_category');
	}

	/** 콘솔: 상품 뱃지 (태그) 관리 */
	public function dispDdggshopConsoleBadges()
	{
		if (!$this->consoleGuard('badges', '상품 뱃지', '상품 뱃지')) return;
		Context::set('tags', ddggshopModel::getTagList());
		$this->setTemplateFile('console_badges');
	}

	/** 콘솔: 교환/반품 신청 큐 */
	public function dispDdggshopConsoleClaims()
	{
		if (!$this->consoleGuard('claims', '주문 관리', '교환/반품')) return;
		$mid = $this->module_info->mid;
		$status = trim((string)Context::get('cstatus'));
		$args = new stdClass;
		if (in_array($status, array('requested', 'approved', 'rejected', 'completed'), true)) $args->status = $status;
		// 유형(교환/반품)
		$type = trim((string)Context::get('ctype'));
		if (in_array($type, array('exchange', 'return', 'cancel', 'refund'), true)) $args->type = $type;
		// 키워드(주문번호)
		$q = trim((string)Context::get('q'));
		if ($q !== '') $args->order_code = $q;
		// 기간(프리셋 또는 날짜범위)
		$period = (string)Context::get('period');
		$from = preg_replace('/[^0-9]/', '', (string)Context::get('from_date'));
		$to = preg_replace('/[^0-9]/', '', (string)Context::get('to_date'));
		if ($period && $from === '' && $to === '')
		{
			$map = array('today' => 0, '7d' => 6, '1m' => 29, '3m' => 89);
			if (isset($map[$period])) $from = date('Ymd', time() - $map[$period] * 86400);
		}
		if ($from !== '') $args->from_date = substr($from . '000000', 0, 14);
		if ($to !== '') $args->to_date = substr($to . '235959', 0, 14);
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 20;
		$out = ddggshopModel::getClaimList($args);
		$claims = $out->toBool() ? ($out->data ?: array()) : array();
		foreach ($claims as $c)
		{
			$c->type_label = ddggshopModel::claimTypeLabel($c->type);
			$c->status_label = ddggshopModel::claimStatusLabel($c->status);
			$c->order_url = getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleOrderView', 'order_srl', $c->order_srl);
		}
		Context::set('claims', $claims);
		Context::set('cstatus', $status);
		Context::set('cur_type', $type);
		Context::set('q', $q);
		Context::set('cur_period', $period);
		Context::set('cur_from', $from ? date('Y-m-d', strtotime($from)) : '');
		Context::set('cur_to', $to ? date('Y-m-d', strtotime($to)) : '');
		Context::set('total_count', isset($out->total_count) ? $out->total_count : count($claims));
		Context::set('cur_page', isset($out->page) ? $out->page : 1);
		Context::set('total_page', isset($out->total_page) ? $out->total_page : 1);
		Context::set('console_return', Context::get('u_claims'));
		$this->setTemplateFile('console_claims');
	}

	/** 콘솔: 홈 화면 구성 (블록 목록) */
	public function dispDdggshopConsoleHomeBlocks()
	{
		if (!$this->consoleGuard('homeblocks', '홈 화면 구성', '홈 화면 구성')) return;
		Context::set('console_btn_label', '블록 추가');
		Context::set('console_btn_url', Context::get('u_homeblockform'));
		$blocks = ddggshopModel::getHomeBlocks(false);
		foreach ($blocks as $b)
		{
			$b->type_label = ($b->block_type === 'promo') ? '프로모션 이미지' : '상품 블록';
			$b->source_label = ($b->block_type === 'promo') ? '' : ddggshopModel::blockSourceLabel($b->source_type);
		}
		Context::set('blocks', $blocks);
		$banners = ddggshopModel::getBannerList();
		Context::set('banners', $banners);
		Context::set('banners_json', json_encode($banners, JSON_UNESCAPED_UNICODE));
		Context::set('console_return', Context::get('u_homeblocks'));
		Context::set('shop_home_url', Context::get('shop_home_url'));
		$this->setTemplateFile('console_home');
	}

	/** 콘솔: 홈 블록 추가/수정 폼 */
	public function dispDdggshopConsoleHomeBlockForm()
	{
		if (!$this->consoleGuard('homeblocks', '홈 화면 구성', '블록 편집')) return;
		$block_srl = (int)Context::get('block_srl');
		$block = $block_srl ? ddggshopModel::getHomeBlock($block_srl) : null;
		Context::set('block', $block);
		Context::set('source_list', ddggshopModel::blockSourceList());
		Context::set('categories', ddggshopModel::getCategoryTreeOrdered());
		Context::set('tags', ddggshopModel::getTagList());
		// 직접 선택(manual) 블록 편집 시 현재 선택된 상품 표시
		$selected = array();
		if ($block && $block->source_type === 'manual')
		{
			$selected = ddggshopModel::getItemsBySrls(ddggshopModel::parseSrlList($block->source_value), '');
		}
		Context::set('selected_items', $selected);
		Context::set('console_return', Context::get('u_homeblocks'));
		$this->setTemplateFile('console_home_form');
	}

	/** 콘솔: 상품관리 (목록) */
	public function dispDdggshopConsoleItems()
	{
		if (!$this->consoleGuard('items', '상품 관리', '상품관리')) return;
		Context::set('console_btn_label', '상품등록');
		Context::set('console_btn_url', Context::get('u_itemform'));
		$args = new stdClass;
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 20;
		$status = trim((string)Context::get('status'));
		if (in_array($status, array('sale', 'soldout', 'hidden'), true)) $args->status = $status;
		$q = trim((string)Context::get('q'));
		if ($q !== '') $args->search_keyword = $q;
		$cat = (int)Context::get('category_srl');
		if ($cat) $args->category_srls = ddggshopModel::getDescendantCategorySrls($cat);
		$tag = (int)Context::get('tag_srl');
		if ($tag)
		{
			$srls = ddggshopModel::getItemSrlsByTag($tag);
			$args->item_srls = count($srls) ? $srls : array(0);
		}
		$dtarget = (Context::get('date_target') === 'upd') ? 'upd' : 'reg';
		$from = preg_replace('/[^0-9]/', '', (string)Context::get('from_date'));
		$to = preg_replace('/[^0-9]/', '', (string)Context::get('to_date'));
		if ($from !== '') { $k = ($dtarget === 'upd') ? 'upd_from' : 'reg_from'; $args->{$k} = substr($from . '000000', 0, 14); }
		if ($to !== '') { $k = ($dtarget === 'upd') ? 'upd_to' : 'reg_to'; $args->{$k} = substr($to . '235959', 0, 14); }

		$out = ddggshopModel::getItemList($args);
		$items = $out->toBool() ? ($out->data ?: array()) : array();
		$cmap = ddggshopModel::getCategoryMap();
		foreach ($items as $it)
		{
			$c = isset($cmap[$it->category_srl]) ? $cmap[$it->category_srl] : null;
			$it->cat_name = $c ? $c->category_name : '미분류';
			$it->status_label = ($it->status === 'sale') ? '판매중' : (($it->status === 'soldout') ? '품절' : '숨김');
		}
		Context::set('items', $items);
		Context::set('total_count', $out->total_count);
		Context::set('cur_page', $out->page);
		Context::set('total_page', $out->total_page ?? 1);
		Context::set('cur_status', $status);
		Context::set('q', $q);
		Context::set('item_edit_base', Context::get('u_itemform'));
		// 필터 UI 데이터
		$cat_arr = array();
		foreach (ddggshopModel::getCategoryList() as $c)
		{
			if ($c->status === 'Y') $cat_arr[] = array('srl' => (int)$c->category_srl, 'parent' => (int)$c->parent_srl, 'name' => $c->category_name);
		}
		Context::set('categories_json', json_encode($cat_arr, JSON_UNESCAPED_UNICODE));
		Context::set('filter_cats', ddggshopModel::getCategoryTreeOrdered());
		Context::set('cur_cat', $cat);
		$mtags = array();
		foreach (ddggshopModel::getTagList() as $t) { if ($t->auto_source === 'manual') $mtags[] = $t; }
		Context::set('filter_tags', $mtags);
		Context::set('cur_tag', $tag);
		Context::set('cur_date_target', $dtarget);
		Context::set('cur_from', $from ? date('Y-m-d', strtotime($from)) : '');
		Context::set('cur_to', $to ? date('Y-m-d', strtotime($to)) : '');
		$this->setTemplateFile('console_items');
	}

	/** 콘솔: 쿠폰 관리 */
	public function dispDdggshopConsoleCoupon()
	{
		if (!$this->consoleGuard('coupon', '마케팅', '쿠폰 관리')) return;
		$coupons = ddggshopModel::getCouponList();
		foreach ($coupons as $c)
		{
			$c->disc_label = ($c->discount_type === 'percent') ? ((int)$c->discount_value . '%') : (number_format($c->discount_value) . '원');
			$sd = substr((string)$c->start_date, 0, 8);
			$ed = substr((string)$c->end_date, 0, 8);
			$c->period_label = ($sd || $ed) ? (($sd ? date('y.m.d', strtotime($sd)) : '') . ' ~ ' . ($ed ? date('y.m.d', strtotime($ed)) : '')) : '기간 무제한';
			$c->limit_label = ((int)$c->usage_limit > 0) ? ($c->used_count . ' / ' . $c->usage_limit) : ($c->used_count . ' / 무제한');
		}
		Context::set('coupons', $coupons);
		// 편집용 원본 데이터(JS 폼 채우기)
		$coupon_edit = array();
		foreach ($coupons as $c)
		{
			$coupon_edit[(int)$c->coupon_srl] = array(
				'code' => $c->code,
				'title' => (string)$c->title,
				'discount_type' => $c->discount_type,
				'discount_value' => (int)$c->discount_value,
				'min_order' => (int)$c->min_order,
				'max_discount' => (int)$c->max_discount,
				'start_date' => $c->start_date ? date('Y-m-d', strtotime(substr((string)$c->start_date, 0, 8))) : '',
				'end_date' => $c->end_date ? date('Y-m-d', strtotime(substr((string)$c->end_date, 0, 8))) : '',
				'usage_limit' => (int)$c->usage_limit,
				'per_member' => (int)$c->per_member,
				'auto_signup' => $c->auto_signup,
			);
		}
		Context::set('coupons_json', json_encode($coupon_edit, JSON_UNESCAPED_UNICODE));
		$groups = array();
		foreach (MemberModel::getGroups() as $gsrl => $g)
		{
			$groups[] = (object) array('group_srl' => (int)$gsrl, 'title' => is_array($g) ? $g['title'] : $g->title);
		}
		Context::set('member_groups', $groups);
		Context::set('use_coupon', $this->shop_config->use_coupon ?? 'Y');
		Context::set('console_return', getUrl('', 'mid', $this->module_info->mid, 'act', 'dispDdggshopConsoleCoupon'));
		$this->setTemplateFile('console_coupon');
	}

	/** 콘솔: 쇼핑몰설정 (5개 탭) */
	public function dispDdggshopConsoleConfig()
	{
		if (!$this->consoleGuard('config', '쇼핑몰 설정', '쇼핑몰설정')) return;
		$config = $this->shop_config;
		$sub = (string)Context::get('sub');
		if (!in_array($sub, array('basic', 'payment', 'delivery', 'order', 'access', 'notify', 'layout', 'etc'), true)) $sub = 'basic';
		Context::set('config', $config);
		Context::set('csub', $sub);
		$mid = $this->module_info->mid;
		Context::set('console_return', getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleConfig', 'sub', $sub));

		if ($sub === 'basic')
		{
			$avail = array();
			foreach (ddggshopModel::getAvailableLanguages() as $code => $name)
			{
				$avail[] = (object) array('code' => $code, 'name' => $name);
			}
			Context::set('avail_langs', $avail);
			$en = (isset($config->enabled_langs) && is_array($config->enabled_langs)) ? $config->enabled_langs : array('ko', 'en');
			Context::set('enabled_langs_arr', $en);
		}
		elseif ($sub === 'payment')
		{
			Context::set('webhook_url', getNotEncodedFullUrl('', 'mid', $mid, 'act', 'procDdggshopPaymentWebhook'));
		}
		elseif ($sub === 'notify')
		{
			Context::set('notify_events_list', ddggshopModel::notifyEventList());
		}
		elseif ($sub === 'delivery')
		{
			$msrl = $this->consoleShopModuleSrl();
			Context::set('editor_delivery', $this->consoleEditor('delivery_info', $config->delivery_info, $msrl));
			Context::set('editor_return', $this->consoleEditor('exchange_return', $config->exchange_return, $msrl));
			Context::set('delivery_companies', ddggshopModel::carrierList());
			Context::set('ship_fees', ddggshopModel::getShipFeeList());
		}
		elseif ($sub === 'access')
		{
			$groups = array();
			foreach (MemberModel::getGroups() as $gsrl => $g)
			{
				$groups[] = (object) array('group_srl' => (int)$gsrl, 'title' => is_array($g) ? $g['title'] : $g->title);
			}
			Context::set('member_groups', $groups);
			Context::set('access_categories', ddggshopModel::getCategoryTreeOrdered());
		}
		elseif ($sub === 'etc')
		{
			Context::set('primary_color_dark', ddggshopModel::darkenColor($config->primary_color ?: '#ff4d2d'));
			$grades = (isset($config->grade_rates) && is_array($config->grade_rates)) ? $config->grade_rates : array();
			$gdiscs = (isset($config->grade_discounts) && is_array($config->grade_discounts)) ? $config->grade_discounts : array();
			$groups = array();
			foreach (MemberModel::getGroups() as $gsrl => $g)
			{
				$o = (object) array('group_srl' => (int)$gsrl, 'title' => is_array($g) ? $g['title'] : $g->title);
				$o->rate = isset($grades[(int)$gsrl]) ? (int)$grades[(int)$gsrl] : '';
				$gd = isset($gdiscs[(int)$gsrl]) ? $gdiscs[(int)$gsrl] : null;
				$o->disc_type = ($gd && ($gd['type'] ?? '') === 'amount') ? 'amount' : 'percent';
				$o->disc_value = ($gd && (int)($gd['value'] ?? 0) > 0) ? (int)$gd['value'] : '';
				$groups[] = $o;
			}
			Context::set('member_groups', $groups);
			$band_rows = array();
			foreach (ddggshopModel::getPriceBands($config) as $b)
			{
				$band_rows[] = (object) array('min' => $b['min'], 'max' => $b['max'], 'label' => $b['label']);
			}
			Context::set('price_band_rows', $band_rows);
		}
		$this->setTemplateFile('console_config');
	}

	/** 콘솔: ddggshop 인스턴스 module_srl (에디터 업로드용) */
	protected function consoleShopModuleSrl()
	{
		static $srl = null;
		if ($srl !== null) return $srl;
		$srl = 0;
		$out = executeQueryArray('module.getMidList', (object) array('module' => 'ddggshop'));
		if ($out->toBool() && $out->data) $srl = (int)$out->data[0]->module_srl;
		// ddggshop 인스턴스(mid)가 없으면 사이트 기본 모듈로 폴백 — 에디터 업로드 라우팅(editor_mid) 확보
		if (!$srl)
		{
			$default = ModuleModel::getDefaultMid();
			if ($default && !empty($default->module_srl)) $srl = (int)$default->module_srl;
		}
		return $srl;
	}

	/** 콘솔: 설정 화면용 에디터 */
	protected function consoleEditor($key, $content, $module_srl)
	{
		$opt = new stdClass;
		$opt->editor_skin = 'ckeditor';
		$opt->content_key_name = $key;
		$opt->allow_fileupload = true;
		$opt->enable_default_component = true;
		$opt->enable_component = true;
		$opt->resizable = true;
		$opt->height = 220;
		$opt->module_srl = $module_srl;
		Context::set($key, $content);
		return getModel('editor')->getEditor(0, $opt);
	}

	/** 콘솔: 상품 등록/수정 폼 */
	public function dispDdggshopConsoleItemForm()
	{
		if (!$this->consoleGuard('items', '상품 관리 / 상품관리', '상품 등록')) return;
		$item_srl = (int)Context::get('item_srl');
		$item = $item_srl ? ddggshopModel::getItem($item_srl) : null;
		if ($item) Context::set('console_title', '상품 수정');

		$item_images = array();
		if ($item && $item->images)
		{
			$d = @json_decode($item->images, true);
			if (is_array($d)) $item_images = $d;
		}
		Context::set('item_images', $item_images);
		$attr_list = array();
		if ($item && $item->attributes)
		{
			$d = @json_decode($item->attributes, true);
			if (is_array($d)) foreach ($d as $a) $attr_list[] = (object) $a;
		}
		Context::set('attr_list', $attr_list);

		$opt = new stdClass;
		$opt->editor_skin = 'ckeditor';
		$opt->primary_key_name = 'item_srl';
		$opt->content_key_name = 'content';
		$opt->allow_fileupload = true;
		$opt->enable_autosave = false;
		$opt->enable_default_component = true;
		$opt->enable_component = true;
		$opt->resizable = true;
		$opt->height = 360;
		$opt->module_srl = $this->consoleShopModuleSrl();
		$upload_target = ($item && $item->item_srl) ? (int)$item->item_srl : 0;
		Context::set('content', $item ? $item->content : '');
		Context::set('editor', getModel('editor')->getEditor($upload_target, $opt));

		Context::set('item', $item);
		Context::set('default_require_address', $this->shop_config->default_require_address ?? 'Y');
		// 구매 후 회원 그룹 변경: 그룹 목록 + 현재 선택값
		$mgroups = array();
		foreach (MemberModel::getGroups() as $gsrl => $g)
		{
			$mgroups[] = (object) array('group_srl' => (int)$gsrl, 'title' => is_array($g) ? $g['title'] : $g->title);
		}
		Context::set('member_groups', $mgroups);
		Context::set('grant_add_arr', ($item && isset($item->grant_add_groups)) ? ddggshopModel::parseGroupList($item->grant_add_groups) : array());
		Context::set('grant_del_arr', ($item && isset($item->grant_del_groups)) ? ddggshopModel::parseGroupList($item->grant_del_groups) : array());
		$cat_arr = array();
		foreach (ddggshopModel::getCategoryList() as $c)
		{
			if ($c->status === 'Y') $cat_arr[] = array('srl' => (int)$c->category_srl, 'parent' => (int)$c->parent_srl, 'name' => $c->category_name);
		}
		Context::set('categories_json', json_encode($cat_arr, JSON_UNESCAPED_UNICODE));
		Context::set('item_category_srl', ($item && $item->category_srl) ? (int)$item->category_srl : 0);
		Context::set('item_has_options', ($item && $item->has_options === 'Y') ? 'Y' : 'N');
		Context::set('opt_groups_json', json_encode(ddggshopModel::decodeOptionGroups($item), JSON_UNESCAPED_UNICODE));
		$opt_rows = array();
		if ($item && $item->item_srl)
		{
			foreach (ddggshopModel::getItemOptions($item->item_srl, false) as $o)
			{
				$opt_rows[] = array('label' => $o->option_label, 'price' => (int)$o->price_add, 'stock' => (int)$o->stock);
			}
		}
		Context::set('opt_rows_json', json_encode($opt_rows, JSON_UNESCAPED_UNICODE));
		Context::set('all_tags', ddggshopModel::getTagList());
		Context::set('item_tag_srls', ($item && $item->item_srl) ? ddggshopModel::getTagSrlsForItem($item->item_srl) : array());
		Context::set('console_return', getUrl('', 'mid', $this->module_info->mid, 'act', 'dispDdggshopConsoleItems'));
		$this->setTemplateFile('console_item_form');
	}

	/** 모든 화면 공통 변수 (헤더/카테고리/장바구니 수) */
	protected function assignCommon()
	{
		$key = ddggshopModel::getCartKey();
		$detail = ddggshopModel::getCartDetail($key);
		Context::set('shop_config', $this->shop_config);
		Context::set('shop_name', $this->shop_config->shop_name);
		Context::set('shop_logo', $this->shop_config->logo_image);
		// 푸터 사업자정보확인 링크 (직접 입력 우선, 없으면 사업자등록번호로 공정위 조회 링크 자동생성)
		$bizlink = trim((string)($this->shop_config->bizinfo_url ?? ''));
		if ($bizlink === '' && !empty($this->shop_config->biz_reg_no))
		{
			$digits = preg_replace('/[^0-9]/', '', $this->shop_config->biz_reg_no);
			if ($digits !== '') $bizlink = 'https://www.ftc.go.kr/bizCommPop.do?wrkr_no=' . $digits;
		}
		Context::set('biz_info_link', $bizlink);
		// 가격 비공개 안내: 비로그인=로그인 안내 / 로그인+권한부족=그룹 안내
		Context::set('price_hidden_text', ddggshopModel::getPriceLockText($this->shop_config));
		$pc = $this->shop_config->primary_color ?: '#ff4d2d';
		Context::set('primary_color', $pc);
		Context::set('primary_color_dark', ddggshopModel::darkenColor($pc));
		$slang_cat = ddggshopModel::getActiveLang($this->shop_config);
		$common_cats = ddggshopModel::getCategoryList();
		ddggshopModel::localizeCategoryNames($common_cats, $slang_cat, $this->shop_config);
		Context::set('categories', $common_cats);
		$nav_menu = ddggshopModel::getCategoryMenu();
		ddggshopModel::localizeCategoryNames($nav_menu, $slang_cat, $this->shop_config);
		Context::set('nav_menu', $nav_menu);
		$cat_flat = ddggshopModel::getCategoryTreeOrdered();
		ddggshopModel::localizeCategoryNames($cat_flat, $slang_cat, $this->shop_config);
		Context::set('cat_flat', $cat_flat);
		Context::set('cart_count', $detail->count);
		Context::set('use_coupon', $this->shop_config->use_coupon ?? 'Y');
		Context::set('shop_mid', $this->module_info->mid);
		Context::set('shop_url_base', RX_BASEURL); // 절대 경로 base (pretty URL에서 상대경로 깨짐 방지)
		// 다국어(i18n): 번역 사전 + 언어 선택기 데이터
		$Ldict = ddggshopModel::getLangDict($this->shop_config);
		Context::set('L', $Ldict);
		Context::set('L_json', json_encode($Ldict, JSON_UNESCAPED_UNICODE));
		Context::set('shop_lang', ddggshopModel::getActiveLang($this->shop_config));
		$use_ml = (($this->shop_config->use_multilang ?? 'N') === 'Y');
		$enabled_langs = ddggshopModel::getEnabledLanguages($this->shop_config);
		Context::set('use_multilang', ($use_ml && count($enabled_langs) > 1));
		$lang_options = array();
		foreach ($enabled_langs as $code => $name)
		{
			$lang_options[] = (object) array('code' => $code, 'name' => $name);
		}
		Context::set('lang_options', $lang_options);
		Context::set('is_logged', Context::get('is_logged'));
		$ll = Context::get('logged_info');
		Context::set('logged_nick', $ll ? $ll->nick_name : '');
		Context::set('is_site_admin', ($ll && isset($ll->is_admin) && $ll->is_admin === 'Y'));

		$cols = (int)($this->module_info->grid_columns ?? 0);
		if (!in_array($cols, array(3, 4, 5), true)) $cols = 4;
		Context::set('grid_class', 'ds-grid cols-' . $cols);

		// 최근 본 상품 플로팅 위젯 (레이아웃 설정 토글, 현재 보는 상품은 init 시점이라 자연 제외)
		$recent_widget = array();
		if (($this->shop_config->show_recent_widget ?? 'Y') === 'Y')
		{
			$recent_count = (int)($this->shop_config->recent_widget_count ?? 5);
			if ($recent_count < 1) $recent_count = 5;
			$recent_widget = ddggshopModel::getRecentItems(0, $recent_count);
			$this->decorateCards($recent_widget);
		}
		Context::set('recent_widget_items', $recent_widget);
	}

	/** 상품 목록에 태그(뱃지) 부착 */
	protected function attachTags(&$items)
	{
		$m = ddggshopModel::currentMemberSrl();
		foreach ($items as $it)
		{
			$it->tags = ddggshopModel::getTagsForItem($it->item_srl);
			$it->can_view_price = ddggshopModel::canViewPrice($it, $this->shop_config);
			$mp = ddggshopModel::discountedUnitPrice($it->price, $m, $this->shop_config);
			$it->member_price = $mp;
			$it->discounted = ($mp < (int)$it->price);
		}
	}

	/** 쇼핑몰 홈 (배너 + 홈 블록 빌더) */
	public function dispDdggshopIndex()
	{
		Context::set('banners', ddggshopModel::getBannerList('Y'));

		// 관리자(라이브 편집 권한) 판별 — 관리자는 숨김 블록도 받아 편집 가능
		$logged = Context::get('logged_info');
		$is_admin = ($logged && ((isset($logged->is_admin) && $logged->is_admin === 'Y') || !empty($this->grant->manager)));

		$blocks = ddggshopModel::getHomeBlocks(!$is_admin);
		$rendered = array();
		$editor_data = array();
		foreach ($blocks as $b)
		{
			$items = array();
			if ($b->block_type !== 'promo')
			{
				$items = ddggshopModel::resolveBlockItems($b);
				$this->attachTags($items);
			}
			// 일반 사용자에겐 빈/이미지없는 블록 숨김. 관리자는 편집을 위해 모두 노출.
			if (!$is_admin)
			{
				if ($b->block_type === 'promo' && !$b->image) continue;
				if ($b->block_type !== 'promo' && !count($items)) continue;
			}
			$rendered[] = (object) array(
				'type' => $b->block_type, 'block' => $b, 'items' => $items,
				'more_url' => ($b->block_type === 'promo') ? '' : $this->blockMoreUrl($b),
			);
			if ($is_admin) $editor_data[] = $this->blockEditorData($b);
		}

		// 블록을 하나도 만들지 않은 설치본은 기존 홈 노출 태그 방식으로 폴백
		if (!count($blocks))
		{
			foreach (ddggshopModel::getHomeTags() as $tag)
			{
				$items = ddggshopModel::getItemsForTag($tag);
				if (!count($items)) continue;
				$this->attachTags($items);
				$rendered[] = (object) array('type' => 'products', 'block' => (object) array('block_srl' => 0, 'title' => $tag->title, 'layout' => 'grid', 'status' => 'Y'), 'items' => $items, 'more_url' => '');
			}
		}

		Context::set('home_blocks', $rendered);
		Context::set('is_shop_admin', $is_admin ? 1 : 0);
		if ($is_admin)
		{
			Context::set('hb_editor_json', json_encode($editor_data));
			Context::set('source_list', ddggshopModel::blockSourceList());
			Context::set('hb_categories', ddggshopModel::getCategoryTreeOrdered());
			Context::set('hb_tags', ddggshopModel::getTagList());
		}
		Context::set('browser_title', $this->shop_config->shop_name);
		$this->setTemplateFile('index');
	}

	/** 라이브 편집용 블록 데이터(JSON 직렬화용) */
	protected function blockEditorData($b)
	{
		$d = array(
			'block_srl'    => (int)$b->block_srl,
			'block_type'   => $b->block_type,
			'title'        => (string)$b->title,
			'source_type'  => (string)$b->source_type,
			'source_value' => (string)$b->source_value,
			'list_count'   => (int)$b->list_count,
			'layout'       => $b->layout ?: 'grid',
			'link'         => (string)$b->link,
			'image'        => (string)$b->image,
			'status'       => $b->status,
			'items'        => array(),
		);
		if ($b->block_type !== 'promo' && $b->source_type === 'manual')
		{
			foreach (ddggshopModel::getItemsBySrls(ddggshopModel::parseSrlList($b->source_value), '') as $it)
			{
				$d['items'][] = array('item_srl' => (int)$it->item_srl, 'item_name' => $it->item_name, 'thumb' => (string)$it->thumb);
			}
		}
		return $d;
	}

	/** 홈 블록 '더보기' 링크 (관리자 지정 링크 우선, 카테고리 소스는 해당 목록으로) */
	protected function blockMoreUrl($b)
	{
		if (!empty($b->link)) return $b->link;
		if ($b->source_type === 'category' && (int)$b->source_value)
		{
			return getUrl('', 'mid', $this->module_info->mid, 'act', 'dispDdggshopProductList', 'category_srl', (int)$b->source_value);
		}
		return '';
	}

	/** 상품 목록 (카테고리/검색/페이징) */
	public function dispDdggshopProductList()
	{
		$args = new stdClass;
		$category_srl = (int)Context::get('category_srl');
		$search_keyword = trim((string)Context::get('search_keyword'));
		$sort = (string)Context::get('sort');

		$price_band = (string)Context::get('price_band');

		if ($category_srl) $args->category_srls = ddggshopModel::getDescendantCategorySrls($category_srl);
		if ($search_keyword !== '') $args->search_keyword = $search_keyword;
		$args->status = 'sale';
		$args->list_count = 12;
		$args->page = max(1, (int)Context::get('page'));

		// 가격대 필터 (관리자 설정 기반)
		$cfg_bands = ddggshopModel::getPriceBands($this->shop_config);
		$bands = array();
		$band_labels = array();
		foreach ($cfg_bands as $i => $b)
		{
			$code = 'b' . $i;
			$bands[$code] = array((int) $b['min'], (int) $b['max']);
			$band_labels[$code] = $b['label'];
		}
		// 직접 입력(최소~최대) 우선, 없으면 사전 정의 가격대
		$pmin = (int) preg_replace('/[^0-9]/', '', (string) Context::get('price_min'));
		$pmax = (int) preg_replace('/[^0-9]/', '', (string) Context::get('price_max'));
		if ($pmin > 0 && $pmax > 0 && $pmin > $pmax) { $t = $pmin; $pmin = $pmax; $pmax = $t; }
		if ($pmin > 0 || $pmax > 0)
		{
			$price_band = '';
			if ($pmin > 0) $args->price_min = $pmin;
			if ($pmax > 0) $args->price_max = $pmax;
		}
		elseif (isset($bands[$price_band]))
		{
			if ($bands[$price_band][0] > 0) $args->price_min = $bands[$price_band][0];
			if ($bands[$price_band][1] > 0) $args->price_max = $bands[$price_band][1];
		}

		if ($sort === 'price_asc') { $args->sort_index = 'price'; $args->order_type = 'asc'; }
		elseif ($sort === 'price_desc') { $args->sort_index = 'price'; $args->order_type = 'desc'; }
		elseif ($sort === 'popular') { $args->sort_index = 'buy_count'; $args->order_type = 'desc'; }
		else { $args->sort_index = 'list_order'; $args->order_type = 'desc'; }

		$output = ddggshopModel::getItemList($args);
		$items = $output->toBool() ? ($output->data ?: array()) : array();
		$this->attachTags($items);
		Context::set('items', $items);
		Context::set('total_count', $output->total_count);
		Context::set('cur_page', $output->page);
		Context::set('total_page', isset($output->total_page) ? $output->total_page : 1);
		Context::set('page_navigation', $output->page_navigation ?? null);

		$slang_cat = ddggshopModel::getActiveLang($this->shop_config);
		$cur_category = $category_srl ? ddggshopModel::getCategory($category_srl) : null;
		if ($cur_category) $cur_category->category_name = ddggshopModel::categoryDisplayName($cur_category, $slang_cat, $this->shop_config);
		Context::set('cur_category', $cur_category);
		$cat_path = $category_srl ? ddggshopModel::getCategoryPath($category_srl) : array();
		ddggshopModel::localizeCategoryNames($cat_path, $slang_cat, $this->shop_config);
		Context::set('category_path', $cat_path);
		Context::set('category_srl', $category_srl);
		Context::set('search_keyword', $search_keyword);
		Context::set('sort', $sort);
		Context::set('price_band', $price_band);
		Context::set('cur_price_min', $pmin > 0 ? $pmin : '');
		Context::set('cur_price_max', $pmax > 0 ? $pmax : '');
		$nav_categories = ddggshopModel::getCategoryTreeOrdered();
		ddggshopModel::localizeCategoryNames($nav_categories, $slang_cat, $this->shop_config);
		$show_cat_count = (($this->shop_config->show_category_count ?? 'N') === 'Y');
		if ($show_cat_count)
		{
			$cat_counts = ddggshopModel::getCategoryItemCounts();
			foreach ($nav_categories as $nc) $nc->item_count = isset($cat_counts[(int) $nc->category_srl]) ? $cat_counts[(int) $nc->category_srl] : 0;
		}
		Context::set('categories', $nav_categories);
		Context::set('show_category_count', $show_cat_count ? 'Y' : 'N');
		Context::set('price_bands', $band_labels);
		$L = ddggshopModel::getLangDict($this->shop_config);
		$title = $cur_category ? $cur_category->category_name : ($search_keyword !== '' ? str_replace('%s', $search_keyword, $L['list_title_search']) : $L['list_title_all']);
		Context::set('list_title', $title);
		Context::set('browser_title', $title . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('list');
	}

	/** 상품 상세 */
	public function dispDdggshopProductDetail()
	{
		$item_srl = (int)Context::get('item_srl');
		$item = ddggshopModel::getItem($item_srl);
		if (!$item)
		{
			return new BaseObject(-1, '상품을 찾을 수 없습니다.');
		}

		// 조회수 증가
		$u = new stdClass;
		$u->item_srl = $item_srl;
		$u->view_count = (int)$item->view_count + 1;
		executeQuery('ddggshop.updateItemView', $u);

		// 추가 이미지
		$images = array();
		if ($item->images)
		{
			$decoded = @json_decode($item->images, true);
			if (is_array($decoded)) $images = $decoded;
		}
		$item->available = ($item->status === 'sale' && ($item->use_stock !== 'Y' || $item->stock > 0));
		$item->tags = ddggshopModel::getTagsForItem($item_srl);

		// 가격·구매 노출 권한
		Context::set('can_view_price', ddggshopModel::canViewPrice($item, $this->shop_config));

		// 회원 그룹 가격 할인
		$disc_member = ddggshopModel::currentMemberSrl();
		$member_price = ddggshopModel::discountedUnitPrice($item->price, $disc_member, $this->shop_config);
		Context::set('member_price', $member_price);
		Context::set('has_member_discount', ($member_price < (int)$item->price));
		Context::set('member_disc_rules_json', json_encode(ddggshopModel::memberDiscountRules($disc_member, $this->shop_config)));
		Context::set('member_disc_badge', ddggshopModel::memberDiscountBadge($disc_member, $this->shop_config));

		// 옵션 (조합형)
		$opt_groups = array();
		$opts_js = array();
		if ($item->has_options === 'Y')
		{
			foreach (ddggshopModel::getItemOptions($item_srl) as $o)
			{
				$opts_js[] = array('srl' => (int)$o->option_srl, 'label' => $o->option_label, 'add' => (int)$o->price_add, 'stock' => (int)$o->stock);
			}
			$decoded = ddggshopModel::decodeOptionGroups($item);
			// 단일 그룹이면 옵션값 자체가 조합이므로 값별 추가금액·품절을 표시할 수 있다.
			$single = (count($decoded) === 1);
			$use_stock = ($item->use_stock === 'Y');
			$add_by_label = array();
			$stock_by_label = array();
			foreach ($opts_js as $oj) { $add_by_label[$oj['label']] = (int)$oj['add']; $stock_by_label[$oj['label']] = (int)$oj['stock']; }
			foreach ($decoded as $g)
			{
				if (empty($g['name'])) continue;
				$vals = array();
				foreach ((isset($g['values']) ? $g['values'] : array()) as $v)
				{
					$add = ($single && isset($add_by_label[$v])) ? (int)$add_by_label[$v] : 0;
					$soldout = ($single && $use_stock && isset($stock_by_label[$v]) && $stock_by_label[$v] <= 0) ? 1 : 0;
					$vals[] = (object) array('v' => $v, 'add' => $add, 'soldout' => $soldout);
				}
				$opt_groups[] = (object) array('name' => $g['name'], 'values' => $vals);
			}
		}
		Context::set('opt_groups', $opt_groups);
		Context::set('options_json', json_encode($opts_js, JSON_UNESCAPED_UNICODE));
		Context::set('item_use_stock', $item->use_stock);

		Context::set('item', $item);
		Context::set('images', $images);
		Context::set('category', $item->category_srl ? ddggshopModel::getCategory($item->category_srl) : null);
		$dpath = $item->category_srl ? ddggshopModel::getCategoryPath($item->category_srl) : array();
		ddggshopModel::localizeCategoryNames($dpath, null, $this->shop_config);
		Context::set('category_path', $dpath);
		Context::set('delivery_info', $this->shop_config->delivery_info);
		Context::set('exchange_return', $this->shop_config->exchange_return);
		Context::set('delivery_company', $this->shop_config->delivery_company);

		// 상세 표시 항목 (브랜드/제조사/원산지 + 커스텀 속성)
		$meta_rows = array();
		if (trim((string)$item->brand) !== '') $meta_rows[] = (object) array('name' => '브랜드', 'value' => $item->brand);
		if (trim((string)$item->manufacturer) !== '') $meta_rows[] = (object) array('name' => '제조사', 'value' => $item->manufacturer);
		if (trim((string)$item->origin) !== '') $meta_rows[] = (object) array('name' => '원산지', 'value' => $item->origin);
		if ($item->attributes)
		{
			$adec = @json_decode($item->attributes, true);
			if (is_array($adec))
			{
				foreach ($adec as $a)
				{
					if (!empty($a['name'])) $meta_rows[] = (object) array('name' => $a['name'], 'value' => isset($a['value']) ? $a['value'] : '');
				}
			}
		}
		Context::set('meta_rows', $meta_rows);

		// 후기
		$rev_out = ddggshopModel::getReviewList($item_srl, 1, 20);
		$reviews = $rev_out->toBool() ? ($rev_out->data ?: array()) : array();
		$is_admin = false;
		$my_srl = 0;
		if (Context::get('is_logged'))
		{
			$lg = Context::get('logged_info');
			$my_srl = (int)$lg->member_srl;
			$is_admin = (isset($lg->is_admin) && $lg->is_admin === 'Y');
		}
		foreach ($reviews as $r)
		{
			$r->can_delete = ($is_admin || (int)$r->member_srl === $my_srl);
			$r->star_html = $this->starHtml($r->rating);
			$r->image_list = ddggshopModel::parseReviewImages($r);
		}
		Context::set('reviews', $reviews);
		$stats = ddggshopModel::getReviewStats($item_srl);
		$stats->star_html = $this->starHtml($stats->avg);
		Context::set('review_stats', $stats);

		// 상품 문의
		$qnas = ddggshopModel::getQnaList($item_srl, 1, 30);
		foreach ($qnas as $q)
		{
			$q->can_delete = ($is_admin || (int)$q->member_srl === $my_srl);
			$q->can_view = ($q->is_secret !== 'Y' || $is_admin || (int)$q->member_srl === $my_srl);
			// 비밀글: 비인가 열람자에게는 질문·답변 내용을 서버단에서 제거(스킨 무관 강제)
			if (!$q->can_view)
			{
				$q->content = '';
				$q->answer = '';
			}
		}
		Context::set('qnas', $qnas);

		// 성장 기능: 찜 상태 / 리뷰적립 안내 / 연관·교차판매 / 최근 본 상품
		$cfg = $this->shop_config;
		Context::set('use_wishlist', $cfg->use_wishlist ?? 'Y');
		Context::set('use_review_point', $cfg->use_review_point ?? 'N');
		Context::set('review_point', (int)($cfg->review_point ?? 0));
		Context::set('photo_review_point', (int)($cfg->photo_review_point ?? 0));
		Context::set('is_wished', ($my_srl && ($cfg->use_wishlist ?? 'Y') === 'Y' && ddggshopModel::isWished($item_srl, $my_srl)) ? 1 : 0);

		// 작성 권한 (리뷰/문의)
		Context::set('can_write_review', ddggshopModel::canWriteReview($item_srl, $cfg) ? 1 : 0);
		Context::set('review_perm_msg', ddggshopModel::reviewPermMessage($cfg));
		Context::set('can_write_qna', ddggshopModel::canWriteQna($cfg) ? 1 : 0);
		Context::set('qna_perm_msg', ddggshopModel::qnaPermMessage($cfg));

		$related = array();
		$crosssell = array();
		if (($cfg->use_related ?? 'Y') === 'Y')
		{
			$related = ddggshopModel::getRelatedItems($item, 8);
			$crosssell = ddggshopModel::getCrossSellItems($item_srl, 8);
			$this->decorateCards($related);
			$this->decorateCards($crosssell);
		}
		Context::set('related_items', $related);
		Context::set('crosssell_items', $crosssell);

		// 최근 본 상품: 현재 상품 제외하고 읽은 뒤, 현재 상품을 쿠키에 추가
		$recent = array();
		if (($cfg->use_recent ?? 'Y') === 'Y')
		{
			$recent = ddggshopModel::getRecentItems($item_srl, 10);
			$this->decorateCards($recent);
		}
		Context::set('recent_items', $recent);
		ddggshopModel::pushRecentItem($item_srl);

		Context::set('browser_title', $item->item_name . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('detail');
	}

	/** 카드 렌더링용 상품 부가정보(가격노출·태그) 부여 — 연관/최근/찜 목록 공용 */
	protected function decorateCards(&$items)
	{
		$m = ddggshopModel::currentMemberSrl();
		$cfg = $this->shop_config;
		foreach ($items as $it)
		{
			$it->can_view_price = ddggshopModel::canViewPrice($it) ? 1 : 0;
			if (!isset($it->tags) || !is_array($it->tags)) $it->tags = array();
			$mp = ddggshopModel::discountedUnitPrice($it->price, $m, $cfg);
			$it->member_price = $mp;
			$it->discounted = ($mp < (int)$it->price);
		}
	}

	/** 별점 HTML (채워진 별 + 빈 별) */
	protected function starHtml($rating)
	{
		$n = max(0, min(5, (int)round($rating)));
		return '<span class="on">' . str_repeat('★', $n) . '</span>' . str_repeat('★', 5 - $n);
	}

	/** 장바구니 */
	public function dispDdggshopCart()
	{
		$key = ddggshopModel::getCartKey();
		$detail = ddggshopModel::getCartDetail($key);
		$cart_needs_address = ddggshopModel::orderNeedsAddress($detail->rows, $this->shop_config);
		$totals = ddggshopModel::calcOrderTotals($detail->item_total, $this->shop_config, $detail->rows, 0, $cart_needs_address);
		Context::set('cart', $detail);
		Context::set('totals', $totals);
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_cart'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('cart');
	}

	/** 주문서 (장바구니 또는 바로구매) */
	public function dispDdggshopOrder()
	{
		$buy_item_srl = (int)Context::get('buy_item_srl');
		$buy_qty = max(1, (int)Context::get('buy_qty'));

		if ($buy_item_srl)
		{
			$buy_option_srl = (int)Context::get('buy_option_srl');
			$item = ddggshopModel::getItem($buy_item_srl);
			$rows = array();
			$item_total = 0;
			if ($item && $item->status === 'sale')
			{
				$price = (int)$item->price;
				$option_label = '';
				$ok = true;
				if ($item->has_options === 'Y')
				{
					$opt = $buy_option_srl ? ddggshopModel::getItemOption($buy_option_srl) : null;
					if (!$opt || (int)$opt->item_srl !== $buy_item_srl || $opt->status !== 'Y' || ($item->use_stock === 'Y' && (int)$opt->stock < $buy_qty))
					{
						$ok = false;
					}
					else { $price += (int)$opt->price_add; $option_label = $opt->option_label; }
				}
				elseif ($item->use_stock === 'Y' && $item->stock < $buy_qty)
				{
					$ok = false;
				}
				if ($ok)
				{
					$orig_price = $price;
					$price = ddggshopModel::discountedUnitPrice($price, ddggshopModel::currentMemberSrl(), $this->shop_config);
					$sub = $price * $buy_qty;
					$rows[] = (object) array('item_srl' => $item->item_srl, 'item_name' => $item->item_name, 'option_label' => $option_label, 'thumb' => $item->thumb, 'price' => $price, 'orig_price' => $orig_price, 'discounted' => ($price < $orig_price), 'qty' => $buy_qty, 'subtotal' => $sub, 'available' => true, 'ship_fee_type' => (isset($item->ship_fee_type) && $item->ship_fee_type) ? $item->ship_fee_type : 'default', 'ship_fee' => (int)(isset($item->ship_fee) ? $item->ship_fee : 0), 'require_address' => (isset($item->require_address) && $item->require_address === 'N') ? 'N' : 'Y');
					$item_total = $sub;
				}
			}
			$detail = (object) array('rows' => $rows, 'item_total' => $item_total, 'count' => $buy_qty, 'has_unavailable' => !count($rows));
			Context::set('buy_mode', true);
			Context::set('buy_item_srl', $buy_item_srl);
			Context::set('buy_option_srl', $buy_option_srl);
			Context::set('buy_qty', $buy_qty);
		}
		else
		{
			$key = ddggshopModel::getCartKey();
			$detail = ddggshopModel::getCartDetail($key);
			Context::set('buy_mode', false);
		}
		// 배송 주소 수집 여부 (주문설정 정책 + 상품별 설정)
		$needs_address = ddggshopModel::orderNeedsAddress($detail->rows, $this->shop_config);
		$totals = ddggshopModel::calcOrderTotals($detail->item_total, $this->shop_config, $detail->rows, 0, $needs_address);
		Context::set('cart', $detail);
		Context::set('totals', $totals);
		Context::set('needs_address', $needs_address);
		Context::set('show_delivery_memo', (($this->shop_config->show_delivery_memo ?? 'Y') === 'Y'));
		Context::set('address_input_mode', (($this->shop_config->address_input_mode ?? 'search') === 'manual') ? 'manual' : 'search');
		$ship_rules = array();
		foreach (ddggshopModel::getShipFeeList(true) as $r)
		{
			$ship_rules[] = array('s' => preg_replace('/[^0-9]/', '', $r->zip_start), 'e' => preg_replace('/[^0-9]/', '', $r->zip_end), 'fee' => (int)$r->extra_fee, 'region' => $r->region);
		}
		Context::set('ship_rules_json', json_encode($ship_rules, JSON_UNESCAPED_UNICODE));
		// 우편번호 없는 지역명 규칙(해외 등) → 주문 시 배송지역 선택 드롭다운
		$region_rules = array();
		foreach (ddggshopModel::getRegionShipRules() as $r)
		{
			$region_rules[] = array('srl' => (int)$r->fee_srl, 'region' => $r->region, 'fee' => (int)$r->extra_fee);
		}
		Context::set('region_rules', $region_rules);
		Context::set('region_rules_json', json_encode($region_rules, JSON_UNESCAPED_UNICODE));

		// 적립금 사용
		$use_point = (($this->shop_config->use_point ?? 'N') === 'Y');
		$my_point = 0;
		if ($use_point && Context::get('is_logged') && class_exists('PointModel'))
		{
			$ll = Context::get('logged_info');
			$my_point = (int)abs(PointModel::getPoint($ll->member_srl));
		}
		Context::set('use_point', $use_point);
		Context::set('my_point', $my_point);
		Context::set('my_coupons', Context::get('is_logged') ? ddggshopModel::getMemberCoupons((int)Context::get('logged_info')->member_srl) : array());

		$slang = ddggshopModel::getActiveLang($this->shop_config);
		$pay_methods = array();
		foreach ($this->shop_config->pay_methods as $pm)
		{
			// 포인트 전액결제는 회원 전용 — 비로그인/비회원에게는 숨김
			if ($pm === 'point' && !Context::get('is_logged')) continue;
			$pay_methods[] = (object) array('code' => $pm, 'label' => ddggshopModel::payMethodLabel($pm, $slang));
		}
		Context::set('pay_methods', $pay_methods);
		Context::set('toss_client_key', $this->shop_config->toss_client_key);
		Context::set('bank_info', $this->shop_config->bank_info);

		// 프리필 + 회원 배송지 주소록
		$prefill = (object) array('name' => '', 'phone' => '', 'email' => '');
		$address_books = array();
		$default_addr = null;
		if (Context::get('is_logged'))
		{
			$logged = Context::get('logged_info');
			$prefill->name = $logged->nick_name;
			$prefill->email = $logged->email_address;
			$prefill->phone = isset($logged->phone_number) ? $logged->phone_number : '';
			$address_books = ddggshopModel::getAddressBookList($logged->member_srl);
			$default_addr = ddggshopModel::getDefaultAddressBook($logged->member_srl);
		}
		Context::set('prefill', $prefill);
		Context::set('address_books', $address_books);
		Context::set('default_addr', $default_addr);
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_order'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('order');
	}

	/** 주문 완료 / 토스 결제 승인 처리 */
	public function dispDdggshopOrderResult()
	{
		$order_code = trim((string)Context::get('order_code'));
		$order = ddggshopModel::getOrderByCode($order_code);
		if (!$order)
		{
			return new BaseObject(-1, '주문을 찾을 수 없습니다.');
		}

		$paymentKey = trim((string)Context::get('paymentKey'));
		$pay_fail = (string)Context::get('pay_fail');
		$approve_error = '';

		// 토스 결제 실패/취소 → 미결제(ready) 주문 정리 후 실패 화면
		if ($pay_fail === '1')
		{
			if ($this->canViewOrder($order))
			{
				getController('ddggshop')->deleteReadyOrder($order);
			}
			Context::set('order_code', $order_code);
			Context::set('pay_fail', true);
			Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_pay_fail'] . ' - ' . $this->shop_config->shop_name);
			$this->setTemplateFile('order_result');
			return;
		}

		// 토스 성공 리다이렉트 → 서버 승인
		if (in_array($order->status, array('ready', 'pending'), true) && $order->pay_method === 'toss' && $paymentKey !== '')
		{
			$amount = (int)Context::get('amount');
			$res = getController('ddggshop')->approveOrderPayment($order, $paymentKey, $amount);
			if (!$res->toBool()) $approve_error = $res->getMessage();
			$order = ddggshopModel::getOrderByCode($order_code); // 갱신
		}

		// 접근 권한 확인
		if (!$this->canViewOrder($order))
		{
			Context::set('lookup_required', true);
			Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_lookup'] . ' - ' . $this->shop_config->shop_name);
			$this->setTemplateFile('order_lookup');
			return;
		}

		Context::set('order', $order);
		Context::set('order_code', $order_code);
		Context::set('order_items', ddggshopModel::getOrderItemList($order->order_srl));
		Context::set('address', ddggshopModel::getOrderAddress($order->order_srl));
		Context::set('tracking_url', ddggshopModel::trackingUrl($order->shipping_company, $order->shipping_invoice));
		$slang = ddggshopModel::getActiveLang($this->shop_config);
		$Lr = ddggshopModel::getLangDict($this->shop_config);
		Context::set('status_label', ddggshopModel::statusLabel($order->status, $slang));
		Context::set('pay_method_label', ddggshopModel::payMethodLabel($order->pay_method, $slang));
		Context::set('bank_info', $this->shop_config->bank_info);
		Context::set('approve_error', $approve_error);
		Context::set('pay_fail', false);

		// 주문 단계 트래커
		$step_labels = array($Lr['step_received'], $Lr['step_paid'], $Lr['step_preparing'], $Lr['step_shipping'], $Lr['step_delivered']);
		$level_map = array('pending' => 0, 'paid' => 1, 'preparing' => 2, 'shipping' => 3, 'delivered' => 4);
		$level = isset($level_map[$order->status]) ? $level_map[$order->status] : -1;
		$steps = array();
		foreach ($step_labels as $i => $lb)
		{
			$done = ($i <= $level);
			$steps[] = (object) array('label' => $lb, 'done' => $done, 'icon' => $done ? '✓' : ($i + 1));
		}
		Context::set('order_steps', $steps);
		Context::set('is_cancelled', in_array($order->status, array('cancelled', 'refunded'), true));
		Context::set('cancellable', in_array($order->status, array('pending', 'paid'), true));

		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_order_result'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('order_result');
	}

	/** 비회원 주문조회 폼 */
	public function dispDdggshopOrderLookup()
	{
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_lookup'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('order_lookup');
	}

	/** 마이페이지 메인 (개요) */
	public function dispDdggshopMyPage()
	{
		if (!Context::get('is_logged'))
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}
		$logged = Context::get('logged_info');
		$member_srl = (int)$logged->member_srl;

		$output = ddggshopModel::getMemberOrders($member_srl, 1, 3);
		$orders = $output->toBool() ? ($output->data ?: array()) : array();
		$slang = ddggshopModel::getActiveLang($this->shop_config);
		foreach ($orders as $o)
		{
			$o->status_label = ddggshopModel::statusLabel($o->status, $slang);
			$o->items = ddggshopModel::getOrderItemList($o->order_srl);
		}
		$addresses = ddggshopModel::getAddressBookList($member_srl);

		$use_point = (($this->shop_config->use_point ?? 'N') === 'Y');
		Context::set('nick', $logged->nick_name);
		Context::set('use_point', $use_point);
		Context::set('my_point', $use_point ? (int)getModel('point')->getPoint($member_srl) : 0);
		Context::set('recent_orders', $orders);
		Context::set('order_total', (int)$output->total_count);
		Context::set('addr_count', count($addresses));
		Context::set('default_addr', ddggshopModel::getDefaultAddressBook($member_srl));
		Context::set('mypage_tab', 'home');
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_mypage'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('my_page');
	}

	/** 회원 주문 내역 */
	public function dispDdggshopMyOrders()
	{
		if (!Context::get('is_logged'))
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}
		$logged = Context::get('logged_info');
		$page = max(1, (int)Context::get('page'));
		$status = trim((string)Context::get('status'));
		$output = ddggshopModel::getMemberOrders($logged->member_srl, $page, 10, $status);

		$orders = $output->toBool() ? ($output->data ?: array()) : array();
		$slang = ddggshopModel::getActiveLang($this->shop_config);
		foreach ($orders as $o)
		{
			$o->status_label = ddggshopModel::statusLabel($o->status, $slang);
			$o->items = ddggshopModel::getOrderItemList($o->order_srl);
			$o->cancellable = in_array($o->status, array('pending', 'paid'), true);
			$o->tracking_url = ddggshopModel::trackingUrl($o->shipping_company, $o->shipping_invoice);
			// 교환/반품: 배송완료 + 진행중 신청 없을 때 신청 가능
			$open = ddggshopModel::getOpenClaim((int)$o->order_srl);
			$o->claim_label = $open ? (ddggshopModel::claimTypeLabel($open->type, $slang) . ' ' . ddggshopModel::claimStatusLabel($open->status, $slang)) : '';
			$o->can_claim = ($o->status === 'delivered' && !$open) ? 1 : 0;
		}
		Context::set('orders', $orders);
		Context::set('total_count', $output->total_count);
		Context::set('cur_page', $output->page);
		Context::set('total_page', isset($output->total_page) ? $output->total_page : 1);
		Context::set('cur_order_status', $status);
		Context::set('order_status_filters', array(
			'pending' => ddggshopModel::statusLabel('pending', $slang), 'paid' => ddggshopModel::statusLabel('paid', $slang), 'preparing' => ddggshopModel::statusLabel('preparing', $slang),
			'shipping' => ddggshopModel::statusLabel('shipping', $slang), 'delivered' => ddggshopModel::statusLabel('delivered', $slang), 'cancelled' => ddggshopModel::statusLabel('cancelled', $slang),
		));
		Context::set('mypage_tab', 'orders');
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_my_orders'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('my_orders');
	}

	/** 배송지 관리 */
	public function dispDdggshopMyAddresses()
	{
		if (!Context::get('is_logged'))
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}
		$logged = Context::get('logged_info');
		Context::set('addresses', ddggshopModel::getAddressBookList($logged->member_srl));
		Context::set('address_input_mode', ($this->shop_config->address_input_mode ?? 'search') === 'manual' ? 'manual' : 'search');
		Context::set('mypage_tab', 'addresses');
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_addresses'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('my_addresses');
	}

	/** 마이페이지: 쿠폰함 */
	public function dispDdggshopMyCoupons()
	{
		if (!Context::get('is_logged')) return new BaseObject(-1, '로그인이 필요합니다.');
		$logged = Context::get('logged_info');
		$coupons = ddggshopModel::getMemberCoupons((int)$logged->member_srl);
		foreach ($coupons as $c)
		{
			$sd = substr((string)$c->start_date, 0, 8);
			$ed = substr((string)$c->end_date, 0, 8);
			$c->period_label = ($sd || $ed) ? (($sd ? zdate($sd, 'Y.m.d') : '') . ' ~ ' . ($ed ? zdate($ed, 'Y.m.d') : '')) : '기간 제한 없음';
		}
		Context::set('coupons', $coupons);
		Context::set('mypage_tab', 'coupons');
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_coupons'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('my_coupons');
	}

	/** 마이페이지: 찜한 상품 */
	public function dispDdggshopMyWishlist()
	{
		if (!Context::get('is_logged')) return new BaseObject(-1, '로그인이 필요합니다.');
		$logged = Context::get('logged_info');
		$page = max(1, (int)Context::get('page'));
		$out = ddggshopModel::getWishlistItems((int)$logged->member_srl, $page, 24);
		$items = $out->toBool() ? ($out->data ?: array()) : array();
		$this->decorateCards($items);
		Context::set('items', $items);
		Context::set('total_count', isset($out->total_count) ? $out->total_count : count($items));
		Context::set('cur_page', isset($out->page) ? $out->page : 1);
		Context::set('total_page', isset($out->total_page) ? $out->total_page : 1);
		Context::set('mypage_tab', 'wishlist');
		Context::set('browser_title', ddggshopModel::getLangDict($this->shop_config)['title_wishlist'] . ' - ' . $this->shop_config->shop_name);
		$this->setTemplateFile('my_wishlist');
	}

	/** 주문 결과 열람 권한: 본인(회원) / 세션 권한 */
	protected function canViewOrder($order)
	{
		if (Context::get('is_logged') && $order->member_srl)
		{
			$logged = Context::get('logged_info');
			if ((int)$logged->member_srl === (int)$order->member_srl) return true;
		}
		if (isset($_SESSION['ddggshop_order_access'][(int)$order->order_srl])) return true;
		return false;
	}
}
