<?php
/**
 * 쇼핑몰 - 관리자 뷰
 * @author 딱따고기 (https://ddgglabs.org)
 */
class ddggshopAdminView extends ddggshop
{
	public function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	/** 대시보드 → 신규 관리자 콘솔로 이동 (라이믹스 관리자 진입점) */
	public function dispDdggshopAdminDashboard()
	{
		$mout = executeQueryArray('module.getMidList', (object) array('module' => 'ddggshop'));
		$shop_mid = ($mout->toBool() && $mout->data) ? $mout->data[0]->mid : '';
		if ($shop_mid)
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $shop_mid, 'act', 'dispDdggshopConsole'));
			return new BaseObject();
		}
		// 인스턴스(mid) 미생성 → 옛 관리자 콘솔 대신 생성 안내 화면
		Context::set('sitemap_url', getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMenuAdminSiteMap'));
		Context::set('browser_title', '쇼핑몰');
		$this->setTemplateFile('admin_no_instance');
	}

	/** (구) 라이믹스 관리자 레이아웃 대시보드 — 콘솔 미연결 시 폴백 */
	public function dispDdggshopAdminDashboardLegacy()
	{
		$paid_statuses = array('paid', 'preparing', 'shipping', 'delivered');

		$all = ddggshopModel::getSalesSummary((object) array('status_list' => $paid_statuses));
		$today = ddggshopModel::getSalesSummary((object) array('status_list' => $paid_statuses, 'from_date' => date('Ymd000000')));
		$month = ddggshopModel::getSalesSummary((object) array('status_list' => $paid_statuses, 'from_date' => date('Ym') . '01000000'));

		$pending = ddggshopModel::getOrderList((object) array('status' => 'pending', 'list_count' => 1));
		$soldout = ddggshopModel::getItemList((object) array('status' => 'soldout', 'list_count' => 1));
		$items = ddggshopModel::getItemList((object) array('list_count' => 1));

		$recent = ddggshopModel::getOrderList((object) array('list_count' => 8));
		$recent_orders = $recent->toBool() ? ($recent->data ?: array()) : array();
		foreach ($recent_orders as $o) $o->status_label = ddggshopModel::statusLabel($o->status);

		Context::set('sum_all', $all);
		Context::set('sum_today', $today);
		Context::set('sum_month', $month);
		Context::set('cnt_pending', $pending->total_count);
		Context::set('cnt_soldout', $soldout->total_count);
		Context::set('cnt_items', $items->total_count);
		Context::set('recent_orders', $recent_orders);
		Context::set('admin_tab', 'dashboard');
		$this->setTemplateFile('admin_dashboard');
	}

	/** 상품 목록 */
	public function dispDdggshopAdminItemList()
	{
		$args = new stdClass;
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 20;
		$search = trim((string)Context::get('search_keyword'));
		if ($search !== '') $args->search_keyword = $search;
		$cat = (int)Context::get('category_srl');
		if ($cat) $args->category_srls = ddggshopModel::getDescendantCategorySrls($cat);
		$status = trim((string)Context::get('status'));
		if (in_array($status, array('sale', 'soldout', 'hidden'), true)) $args->status = $status;

		$output = ddggshopModel::getItemList($args);
		Context::set('items', $output->toBool() ? ($output->data ?: array()) : array());
		Context::set('total_count', $output->total_count);
		Context::set('cur_page', $output->page);
		Context::set('total_page', isset($output->total_page) ? $output->total_page : 1);
		Context::set('category_map', ddggshopModel::getCategoryMap());

		// 계단식 카테고리 필터용
		$cat_arr = array();
		foreach (ddggshopModel::getCategoryList() as $c)
		{
			if ($c->status === 'Y') $cat_arr[] = array('srl' => (int)$c->category_srl, 'parent' => (int)$c->parent_srl, 'name' => $c->category_name);
		}
		Context::set('categories_json', json_encode($cat_arr, JSON_UNESCAPED_UNICODE));
		Context::set('search_keyword', $search);
		Context::set('category_srl', $cat);
		Context::set('cur_status', $status);
		Context::set('admin_tab', 'item');
		$this->setTemplateFile('admin_item_list');
	}

	/** 상품 등록/수정 폼 */
	public function dispDdggshopAdminItemForm()
	{
		$item_srl = (int)Context::get('item_srl');
		$item = $item_srl ? ddggshopModel::getItem($item_srl) : null;
		$attr_list = array();
		if ($item && $item->images)
		{
			$decoded = @json_decode($item->images, true);
			$item->images_text = is_array($decoded) ? implode("\n", $decoded) : '';
		}
		if ($item && $item->attributes)
		{
			$decoded = @json_decode($item->attributes, true);
			if (is_array($decoded))
			{
				foreach ($decoded as $a) $attr_list[] = (object) $a;
			}
		}
		Context::set('attr_list', $attr_list);
		// 위지윅 에디터 (CKEditor) — 상세설명
		$oEditorModel = getModel('editor');
		$option = new stdClass;
		$option->editor_skin = 'ckeditor';
		$option->primary_key_name = 'item_srl';
		$option->content_key_name = 'content';
		$option->allow_fileupload = true;
		$option->enable_autosave = false;
		$option->enable_default_component = true;
		$option->enable_component = true;
		$option->resizable = true;
		$option->height = 420;
		$option->module_srl = $this->getShopModuleSrl();
		$upload_target_srl = ($item && $item->item_srl) ? (int)$item->item_srl : 0;
		Context::set('content', $item ? $item->content : '');
		Context::set('editor', $oEditorModel->getEditor($upload_target_srl, $option));

		Context::set('item', $item);
		// 계단식 카테고리 선택용 데이터
		$cat_arr = array();
		foreach (ddggshopModel::getCategoryList() as $c)
		{
			if ($c->status === 'Y') $cat_arr[] = array('srl' => (int)$c->category_srl, 'parent' => (int)$c->parent_srl, 'name' => $c->category_name);
		}
		Context::set('categories_json', json_encode($cat_arr, JSON_UNESCAPED_UNICODE));
		Context::set('item_category_srl', ($item && $item->category_srl) ? (int)$item->category_srl : 0);

		// 옵션 (그룹 정의 + 기존 조합)
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
		Context::set('admin_tab', 'item');
		$this->setTemplateFile('admin_item_form');
	}

	/** 카테고리 관리 */
	public function dispDdggshopAdminCategory()
	{
		$tree = ddggshopModel::getCategoryTreeOrdered();
		Context::set('categories', $tree);
		$parents = array();
		foreach ($tree as $c)
		{
			if ((int)$c->depth < 2) $parents[] = $c;
		}
		Context::set('parent_categories', $parents);
		Context::set('admin_tab', 'category');
		$this->setTemplateFile('admin_category');
	}

	/** 주문 목록 (기간/상태/결제수단/검색 필터) */
	public function dispDdggshopAdminOrderList()
	{
		$args = new stdClass;
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 20;

		$status = trim((string)Context::get('status'));
		if ($status !== '') $args->status = $status;
		$pay_method = trim((string)Context::get('pay_method'));
		if ($pay_method !== '') $args->pay_method = $pay_method;

		// 기간
		$period = (string)Context::get('period');
		$date_from = trim((string)Context::get('date_from'));
		$date_to = trim((string)Context::get('date_to'));
		list($from_ymd, $to_ymd) = $this->resolvePeriod($period, $date_from, $date_to);
		if ($from_ymd) $args->from_date = $from_ymd . '000000';
		if ($to_ymd) $args->to_date = $to_ymd . '235959';

		// 검색 (대상별)
		$search_target = (string)Context::get('search_target');
		if (!in_array($search_target, array('order_code', 'orderer_name', 'orderer_phone', 'orderer_email', 'item_name'), true)) $search_target = 'order_code';
		$search_keyword = trim((string)Context::get('search_keyword'));
		if ($search_keyword !== '')
		{
			if ($search_target === 'item_name')
			{
				$srls = ddggshopModel::getOrderItemSrlsByName($search_keyword);
				$args->order_srls = count($srls) ? $srls : array(0);
			}
			else
			{
				$args->{$search_target} = $search_keyword;
			}
		}

		$output = ddggshopModel::getOrderList($args);
		$orders = $output->toBool() ? ($output->data ?: array()) : array();
		foreach ($orders as $o)
		{
			$o->status_label = ddggshopModel::statusLabel($o->status);
			$o->pay_method_label = ddggshopModel::payMethodLabel($o->pay_method);
		}
		Context::set('orders', $orders);
		Context::set('total_count', $output->total_count);
		Context::set('cur_page', $output->page);
		Context::set('total_page', isset($output->total_page) ? $output->total_page : 1);
		Context::set('cur_status', $status);
		Context::set('cur_pay_method', $pay_method);
		Context::set('cur_period', $period);
		Context::set('date_from', $date_from);
		Context::set('date_to', $date_to);
		Context::set('search_target', $search_target);
		Context::set('search_keyword', $search_keyword);
		Context::set('status_options', $this->statusOptions());
		Context::set('admin_tab', 'order');
		$this->setTemplateFile('admin_order_list');
	}

	/** 기간 프리셋 → [from_ymd, to_ymd] (YYYYMMDD, 빈 문자열이면 제한없음) */
	protected function resolvePeriod($period, $date_from, $date_to)
	{
		$today = date('Ymd', RX_TIME);
		switch ($period)
		{
			case 'today':     return array($today, $today);
			case 'yesterday': $y = date('Ymd', RX_TIME - 86400); return array($y, $y);
			case '7':         return array(date('Ymd', RX_TIME - 86400 * 6), $today);
			case '15':        return array(date('Ymd', RX_TIME - 86400 * 14), $today);
			case '30':        return array(date('Ymd', RX_TIME - 86400 * 29), $today);
			case '90':        return array(date('Ymd', RX_TIME - 86400 * 89), $today);
			case 'custom':
				return array(
					$date_from !== '' ? str_replace('-', '', $date_from) : '',
					$date_to !== '' ? str_replace('-', '', $date_to) : '',
				);
			default:          return array('', '');
		}
	}

	/** 주문 상세 */
	public function dispDdggshopAdminOrderView()
	{
		$order_srl = (int)Context::get('order_srl');
		$order = ddggshopModel::getOrder($order_srl);
		if (!$order)
		{
			return new BaseObject(-1, '주문을 찾을 수 없습니다.');
		}
		$order->status_label = ddggshopModel::statusLabel($order->status);
		$order->pay_method_label = ddggshopModel::payMethodLabel($order->pay_method);

		Context::set('order', $order);
		Context::set('order_items', ddggshopModel::getOrderItemList($order_srl));
		Context::set('address', ddggshopModel::getOrderAddress($order_srl));
		Context::set('carriers', ddggshopModel::carrierList());
		Context::set('default_carrier', ddggshopModel::getConfig()->delivery_company);
		Context::set('tracking_url', ddggshopModel::trackingUrl($order->shipping_company, $order->shipping_invoice));
		$logs = ddggshopModel::getPaymentLogList((object) array('order_srl' => $order_srl, 'list_count' => 20));
		Context::set('logs', $logs->toBool() ? ($logs->data ?: array()) : array());
		Context::set('status_options', $this->statusOptions());
		Context::set('admin_tab', 'order');
		$this->setTemplateFile('admin_order_view');
	}

	/** 결제 로그 */
	public function dispDdggshopAdminPayment()
	{
		$args = new stdClass;
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 30;
		$output = ddggshopModel::getPaymentLogList($args);
		Context::set('logs', $output->toBool() ? ($output->data ?: array()) : array());
		Context::set('total_count', $output->total_count);
		Context::set('cur_page', $output->page);
		Context::set('total_page', isset($output->total_page) ? $output->total_page : 1);
		Context::set('admin_tab', 'order');
		$this->setTemplateFile('admin_payment');
	}

	/** 상품 문의 관리 */
	public function dispDdggshopAdminQna()
	{
		$args = new stdClass;
		$args->page = max(1, (int)Context::get('page'));
		$args->list_count = 20;
		$output = ddggshopModel::getQnaListAdmin($args);
		$qnas = $output->toBool() ? ($output->data ?: array()) : array();
		Context::set('qnas', $qnas);
		Context::set('total_count', $output->total_count);
		Context::set('cur_page', $output->page);
		Context::set('total_page', isset($output->total_page) ? $output->total_page : 1);
		Context::set('admin_tab', 'qna');
		$this->setTemplateFile('admin_qna');
	}

	/** 쇼핑몰 설정 (하위탭: 홈화면/결제/배송/기타) */
	public function dispDdggshopAdminConfig()
	{
		$config = ddggshopModel::getConfig();
		$sub = (string)Context::get('sub');
		if (!in_array($sub, array('home', 'payment', 'delivery', 'access', 'etc'), true)) $sub = 'home';

		Context::set('config', $config);
		Context::set('sub', $sub);
		Context::set('admin_tab', 'config');

		if ($sub === 'home')
		{
			Context::set('tags', ddggshopModel::getTagList());
			Context::set('banners', ddggshopModel::getBannerList());
		}
		elseif ($sub === 'delivery')
		{
			Context::set('editor_delivery', $this->buildConfigEditor('delivery_info', $config->delivery_info));
			Context::set('editor_return', $this->buildConfigEditor('exchange_return', $config->exchange_return));
			Context::set('delivery_companies', $this->deliveryCompanies());
		}
		elseif ($sub === 'access')
		{
			// 회원 그룹 목록
			$groups = array();
			foreach (MemberModel::getGroups() as $gsrl => $g)
			{
				$groups[] = (object) array('group_srl' => (int)$gsrl, 'title' => is_array($g) ? $g['title'] : $g->title);
			}
			Context::set('member_groups', $groups);
			Context::set('access_categories', ddggshopModel::getCategoryTreeOrdered());
		}
		$this->setTemplateFile('admin_config');
	}

	/** 설정 화면용 CKEditor 인스턴스 생성 */
	protected function buildConfigEditor($key, $content)
	{
		$oEditorModel = getModel('editor');
		$option = new stdClass;
		$option->editor_skin = 'ckeditor';
		$option->primary_key_name = 'cfg_' . $key;
		$option->content_key_name = $key;
		$option->allow_fileupload = true;
		$option->enable_autosave = false;
		$option->enable_default_component = true;
		$option->enable_component = true;
		$option->resizable = true;
		$option->height = 260;
		$option->module_srl = $this->getShopModuleSrl();
		Context::set($key, $content);
		return $oEditorModel->getEditor(0, $option);
	}

	/** ddggshop 모듈 인스턴스 module_srl (에디터 파일 업로드 컨텍스트용) */
	protected function getShopModuleSrl()
	{
		static $srl = null;
		if ($srl !== null) return $srl;
		$srl = 0;
		$out = executeQueryArray('module.getMidList', (object) array('module' => 'ddggshop'));
		if ($out->toBool() && $out->data) $srl = (int)$out->data[0]->module_srl;
		// ddggshop 인스턴스(mid)가 없으면 사이트 기본 모듈로 폴백.
		// 에디터 파일 업로드는 module_srl로부터 editor_mid를 만들어 그 mid로 업로드를 라우팅한다.
		// 0이면 editor_mid가 비어 업로드가 관리자 URL로 가서 'msg_module_is_not_exists'(요청한 페이지를 찾을 수 없습니다)가 발생.
		if (!$srl)
		{
			$default = ModuleModel::getDefaultMid();
			if ($default && !empty($default->module_srl)) $srl = (int)$default->module_srl;
		}
		return $srl;
	}

	/** 택배사 목록 */
	protected function deliveryCompanies()
	{
		return ddggshopModel::carrierList();
	}

	/** 주문 상태 옵션 */
	protected function statusOptions()
	{
		return array(
			'pending'   => '입금대기',
			'paid'      => '결제완료',
			'preparing' => '배송준비',
			'shipping'  => '배송중',
			'delivered' => '배송완료',
			'cancelled' => '취소',
			'refunded'  => '환불',
		);
	}
}
