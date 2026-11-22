<?php
/**
 * 쇼핑몰 - 모델 (조회 전용, static)
 * @author 딱따고기 (https://ddgglabs.org)
 */
class ddggshopModel extends ddggshop
{
	/** 모듈 설정 (기본값 병합) */
	public static function getConfig()
	{
		$config = ModuleModel::getModuleConfig('ddggshop');
		if (!is_object($config)) $config = new stdClass;

		$defaults = array(
			// 기타
			'shop_name'         => '쇼핑몰',
			'logo_image'        => '',
			'primary_color'     => '#ff4d2d',
			// 다국어(i18n)
			'use_multilang'     => 'N',                    // 스토어프론트 언어 선택기 사용
			'default_lang'      => 'ko',                   // 기본 언어 코드
			'enabled_langs'     => array('ko', 'en'),      // 노출 언어 코드 목록
			// 법적 표시사항 (전자상거래법 사업자 정보)
			'company_name'      => '',
			'ceo_name'          => '',
			'biz_address'       => '',
			'biz_phone'         => '',
			'biz_reg_no'        => '',
			'privacy_officer'   => '',
			'mailorder_no'      => '',
			'terms_url'         => '',
			'privacy_url'       => '',
			'bizinfo_url'       => '',
			'point_rate'        => 1,
			'grade_rates'       => array(),
			'grade_discounts'   => array(), // group_srl => array('type'=>'percent'|'amount','value'=>int)
			// 상품목록 가격대 필터(관리자 커스텀, max=0이면 상한 없음)
			'price_bands'       => array(
				array('min' => 0,      'max' => 20000,  'label' => '2만원 이하'),
				array('min' => 20000,  'max' => 50000,  'label' => '2만 ~ 5만원'),
				array('min' => 50000,  'max' => 100000, 'label' => '5만 ~ 10만원'),
				array('min' => 100000, 'max' => 0,      'label' => '10만원 이상'),
			),
			'use_point'         => 'N',
			'use_point_full'    => 'N', // 포인트 전액결제 허용(보유 적립금만으로 결제)
			'use_coupon'        => 'Y',
			'use_order_notify'  => 'Y',
			'notify_events'     => array(), // 이벤트별 on/off (비어있으면 전부 on)
			// 성장 기능
			'use_wishlist'        => 'Y',
			'use_recent'          => 'Y',
			'use_related'         => 'Y',
			'show_recent_widget'  => 'Y', // 최근 본 상품 플로팅 위젯
			'recent_widget_count' => 5,   // 최근 본 상품 위젯 노출 개수
			'show_category_count' => 'N', // 카테고리별 상품 개수 표시
			'use_review_point'    => 'N',
			'review_point'        => 100,
			'photo_review_point'  => 300,
			// 결제 - 토스
			'use_toss'          => 'Y',
			'toss_client_key'   => 'test_ck_D5GePWvyJnrK0W0k6q8gLzN97Eoq',
			'toss_secret_key'   => 'test_sk_zXLkKEypNArWmo50nX3lmeaxYG5R',
			// 결제 - 무통장
			'use_banktransfer'  => 'Y',
			'bank_info'         => '',
			// 배송
			'delivery_company'  => '',
			'delivery_fee_type' => 'paid',
			'base_delivery_fee' => 3000,
			'free_threshold'    => 50000,
			'island_fee'        => 0,
			'delivery_info'     => '',
			'exchange_return'   => '',
			// 판매 공개(회원/그룹) 제어
			'price_visibility'  => 'all',   // all | member | group
			'price_scope'       => 'whole', // whole | category
			'price_groups'      => array(), // group일 때 허용 그룹 srl
			'price_categories'  => array(), // scope=category 일 때 제한 카테고리 srl
			'price_hidden_text' => '로그인 후 가격 확인',
			'price_denied_text' => '', // 로그인했지만 권한 부족 시 문구(비우면 허용 그룹명으로 자동 생성)
			// 작성 권한
			'review_perm'       => 'member', // buyer | member | group
			'review_groups'     => array(),
			'qna_perm'          => 'member', // member | group
			'qna_groups'        => array(),
			// 알림 (알림센터 ncenterlite 사용)
			'notify_admin_qna'     => 'Y', // 상품문의 등록 시 관리자 알림
			'notify_member_answer' => 'Y', // 답변 등록 시 문의 회원 알림
			'notify_soldout'       => 'Y', // 상품/옵션 품절 시 관리자 알림
			// 주문설정 - 고객 입력 영역
			'address_policy'         => 'auto', // auto(상품설정 따름) | always(항상받기) | never(받지않기)
			'default_require_address' => 'Y',   // 신규 상품의 주소 수집 기본값(auto일 때)
			'show_delivery_memo'     => 'Y',    // 주문서 배송 요청사항 입력 표시
			'address_input_mode'     => 'search', // search(우편번호 검색) | manual(직접 입력·해외)
		);
		foreach ($defaults as $k => $v)
		{
			if (!isset($config->{$k}) || $config->{$k} === null)
			{
				$config->{$k} = $v;
			}
		}

		// 결제수단 목록은 사용여부 플래그로부터 구성
		$pay_methods = array();
		if ($config->use_toss === 'Y') $pay_methods[] = 'toss';
		if ($config->use_banktransfer === 'Y') $pay_methods[] = 'banktransfer';
		// 포인트 전액결제: 적립금 사용 + 전액결제 허용 시 결제수단으로 노출
		if (($config->use_point ?? 'N') === 'Y' && ($config->use_point_full ?? 'N') === 'Y') $pay_methods[] = 'point';
		$config->pay_methods = $pay_methods;
		return $config;
	}

	/** HEX 색상을 어둡게(hover/그라데이션용). 잘못된 값이면 기본 오렌지-다크 반환 */
	public static function darkenColor($hex, $factor = 0.86)
	{
		$hex = ltrim(trim((string)$hex), '#');
		if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '#e03e1f';
		$r = (int)round(hexdec(substr($hex, 0, 2)) * $factor);
		$g = (int)round(hexdec(substr($hex, 2, 2)) * $factor);
		$b = (int)round(hexdec(substr($hex, 4, 2)) * $factor);
		return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
	}

	/**
	 * 이 상품의 가격·구매버튼을 현재 사용자가 볼 수 있는가.
	 * 설정(price_visibility/scope/groups/categories)과 로그인 상태로 판단.
	 */
	public static function canViewPrice($item, $config = null)
	{
		if ($config === null) $config = self::getConfig();
		if ($config->price_visibility === 'all') return true;

		// 적용 범위가 '특정 카테고리'면 그 카테고리(하위 포함) 상품만 제한
		if ($config->price_scope === 'category')
		{
			static $restricted = null;
			if ($restricted === null)
			{
				$restricted = array();
				foreach ((array) $config->price_categories as $c)
				{
					foreach (self::getDescendantCategorySrls($c) as $d) $restricted[(int) $d] = true;
				}
			}
			if (empty($restricted[(int) $item->category_srl])) return true; // 제한 대상 아님 → 공개
		}

		// 제한 대상 → 로그인/그룹 확인
		$logged = Context::get('logged_info');
		if (!$logged) return false;
		if (isset($logged->is_admin) && $logged->is_admin === 'Y') return true;
		if ($config->price_visibility === 'member') return true;

		// 특정 그룹
		$allowed = array_map('intval', (array) $config->price_groups);
		if (!count($allowed)) return false;
		$mygroups = (isset($logged->group_list) && is_array($logged->group_list)) ? array_map('intval', array_keys($logged->group_list)) : array();
		return count(array_intersect($allowed, $mygroups)) > 0;
	}

	/** 상품 배열에 can_view_price 플래그 부여 */
	public static function attachPriceVisibility(&$items, $config = null)
	{
		if ($config === null) $config = self::getConfig();
		foreach ($items as $it)
		{
			if (is_object($it)) $it->can_view_price = self::canViewPrice($it, $config);
		}
	}

	/** 허용 그룹(price_groups)의 그룹명 배열 */
	public static function getAllowedGroupNames($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$allowed = array_map('intval', (array) $config->price_groups);
		if (!count($allowed)) return array();
		$names = array();
		foreach (MemberModel::getGroups() as $gsrl => $g)
		{
			if (in_array((int) $gsrl, $allowed, true))
			{
				$names[] = is_array($g) ? $g['title'] : $g->title;
			}
		}
		return $names;
	}

	/**
	 * 가격이 가려졌을 때 현재 사용자에게 보여줄 안내 문구.
	 * - 비로그인: 로그인 안내(price_hidden_text)
	 * - 로그인했지만 권한 부족: price_denied_text(없으면 허용 그룹명으로 자동 생성)
	 */
	public static function getPriceLockText($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$logged = Context::get('logged_info');
		if (!$logged) return $config->price_hidden_text;

		// 로그인 상태에서 가격이 가려진다면 그룹 권한 부족
		$denied = trim((string)($config->price_denied_text ?? ''));
		if ($denied !== '') return $denied;

		$names = self::getAllowedGroupNames($config);
		if (count($names)) return implode(', ', $names) . ' 회원만 가격을 확인할 수 있습니다.';
		return '구매 권한이 있는 회원만 가격을 확인할 수 있습니다.';
	}

	/* ===================== 작성 권한 (리뷰 / 문의) ===================== */

	/** 로그인 사용자가 허용 그룹 중 하나에 속하는지 */
	protected static function memberInGroups($logged, $groups)
	{
		$allowed = array_map('intval', (array) $groups);
		if (!count($allowed)) return false;
		$mygroups = (isset($logged->group_list) && is_array($logged->group_list)) ? array_map('intval', array_keys($logged->group_list)) : array();
		return count(array_intersect($allowed, $mygroups)) > 0;
	}

	/** 회원이 해당 상품을 구매(결제완료 이상)한 적 있는지 */
	public static function hasPurchased($item_srl, $member_srl)
	{
		if (!$member_srl) return false;
		$out = executeQuery('ddggshop.getMemberItemPurchaseCount', (object) array(
			'item_srl' => (int)$item_srl, 'member_srl' => (int)$member_srl,
			'status_list' => array('paid', 'preparing', 'shipping', 'delivered'),
		));
		return ($out->toBool() && $out->data) ? ((int)$out->data->count > 0) : false;
	}

	/** "1,2,3" → array(1,2,3) (정수 그룹 srl 목록) */
	public static function parseGroupList($csv)
	{
		if (!is_string($csv) || $csv === '') return array();
		return array_values(array_filter(array_map('intval', explode(',', $csv))));
	}

	/**
	 * 결제완료 후, 주문에 포함된 상품의 '구매 후 회원 그룹 변경' 설정을 적용.
	 * 상품별로 지정된 그룹을 회원에게서 제거하고 새 그룹을 추가(이동). 비회원은 대상 아님.
	 */
	public static function applyItemGroupGrants($order)
	{
		if (!$order || !(int)$order->member_srl || !class_exists('MemberController')) return;
		$member_srl = (int)$order->member_srl;
		$items = self::getOrderItemList($order->order_srl);
		$seen = array();
		foreach ($items as $oi)
		{
			$isrl = (int)$oi->item_srl;
			if (!$isrl || isset($seen[$isrl])) continue;
			$seen[$isrl] = true;
			$item = self::getItem($isrl);
			if (!$item || ($item->grant_group ?? 'N') !== 'Y') continue;
			try
			{
				foreach (self::parseGroupList($item->grant_del_groups ?? '') as $g)
				{
					\MemberController::removeMemberFromGroup($member_srl, $g);
				}
				foreach (self::parseGroupList($item->grant_add_groups ?? '') as $g)
				{
					\MemberController::addMemberToGroup($member_srl, $g);
				}
			}
			catch (\Throwable $e) { /* 그룹 변경 실패는 결제 완료를 막지 않음 */ }
		}
	}

	/** 회원이 해당 상품을 지금까지 구매한 총 수량 (취소·환불 제외) */
	public static function getPurchasedQty($item_srl, $member_srl)
	{
		if (!$member_srl) return 0;
		$out = executeQuery('ddggshop.getMemberItemPurchaseQty', (object) array(
			'item_srl' => (int)$item_srl, 'member_srl' => (int)$member_srl,
			'status_list' => array('pending', 'paid', 'preparing', 'shipping', 'delivered'),
		));
		return ($out->toBool() && $out->data && $out->data->qty) ? (int)$out->data->qty : 0;
	}

	/**
	 * 1인당 구매 한도 기준, 이 회원이 앞으로 더 살 수 있는 수량.
	 * buy_limit<=0 → 무제한(PHP_INT_MAX). 비회원(member_srl=0)은 과거구매 추적 불가 → 한도 그대로.
	 */
	public static function buyLimitRemaining($item, $member_srl)
	{
		$limit = (int)($item->buy_limit ?? 0);
		if ($limit <= 0) return PHP_INT_MAX;
		if (!$member_srl) return $limit; // 비회원: 이번 주문 내 수량만 제한
		return max(0, $limit - self::getPurchasedQty($item->item_srl, $member_srl));
	}

	/** 장바구니에 담긴 특정 상품의 총 수량 (옵션 합산) */
	public static function getCartItemQty($key, $item_srl)
	{
		$detail = self::getCartDetail($key);
		$sum = 0;
		foreach ($detail->rows as $r) { if ((int)$r->item_srl === (int)$item_srl) $sum += (int)$r->qty; }
		return $sum;
	}

	/** 리뷰 작성 가능 여부 (현재 로그인 사용자 기준) */
	public static function canWriteReview($item_srl, $config = null)
	{
		if ($config === null) $config = self::getConfig();
		$logged = Context::get('logged_info');
		if (!$logged) return false;
		if (isset($logged->is_admin) && $logged->is_admin === 'Y') return true;
		$perm = $config->review_perm ?? 'member';
		if ($perm === 'buyer') return self::hasPurchased($item_srl, (int)$logged->member_srl);
		if ($perm === 'group') return self::memberInGroups($logged, $config->review_groups ?? array());
		return true; // member
	}

	/** 문의 작성 가능 여부 (현재 로그인 사용자 기준) */
	public static function canWriteQna($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$logged = Context::get('logged_info');
		if (!$logged) return false;
		if (isset($logged->is_admin) && $logged->is_admin === 'Y') return true;
		$perm = $config->qna_perm ?? 'member';
		if ($perm === 'group') return self::memberInGroups($logged, $config->qna_groups ?? array());
		return true; // member
	}

	/** 권한 안내 문구 (작성폼 대체 표시용) */
	public static function reviewPermMessage($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$perm = $config->review_perm ?? 'member';
		if (!Context::get('is_logged')) return '후기는 <b>로그인</b> 후 작성할 수 있습니다.';
		if ($perm === 'buyer') return '이 상품을 <b>구매하신 회원</b>만 후기를 작성할 수 있습니다.';
		if ($perm === 'group') return '후기 작성 <b>권한이 있는 회원</b>만 작성할 수 있습니다.';
		return '';
	}

	public static function qnaPermMessage($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$perm = $config->qna_perm ?? 'member';
		if (!Context::get('is_logged')) return '문의는 <b>로그인</b> 후 작성할 수 있습니다.';
		if ($perm === 'group') return '문의 작성 <b>권한이 있는 회원</b>만 작성할 수 있습니다.';
		return '';
	}

	/* ===================== 상품 ===================== */

	/** 단일 상품 */
	public static function getItem($item_srl)
	{
		$args = new stdClass;
		$args->item_srl = (int)$item_srl;
		$output = executeQuery('ddggshop.getItem', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 특정 태그가 달린 상품 srl 배열 */
	public static function getItemSrlsByTag($tag_srl)
	{
		$output = executeQueryArray('ddggshop.getItemSrlsByTag', (object) array('tag_srl' => (int)$tag_srl));
		$out = array();
		if ($output->toBool() && $output->data) foreach ($output->data as $r) $out[] = (int)$r->item_srl;
		return $out;
	}

	/** 상품 옵션(조합) 목록 */
	public static function getItemOptions($item_srl, $only_active = true)
	{
		$args = new stdClass;
		$args->item_srl = (int)$item_srl;
		if ($only_active) $args->status = 'Y';
		$output = executeQueryArray('ddggshop.getItemOptions', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/** 단일 옵션 */
	public static function getItemOption($option_srl)
	{
		$args = new stdClass;
		$args->option_srl = (int)$option_srl;
		$output = executeQuery('ddggshop.getItemOption', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 상품 옵션 그룹 정의(JSON) 디코드 → [{name, values[]}] */
	public static function decodeOptionGroups($item)
	{
		if (!$item || $item->has_options !== 'Y' || !$item->option_groups) return array();
		$g = json_decode($item->option_groups, true);
		return is_array($g) ? $g : array();
	}

	/** 상품 목록 (전체 output: data/total_count/total_page/page) */
	public static function getItemList($args = null)
	{
		if (!is_object($args)) $args = new stdClass;
		if (!isset($args->list_count)) $args->list_count = 20;
		if (!isset($args->page)) $args->page = 1;
		if (!isset($args->sort_index)) $args->sort_index = 'list_order';
		if (!isset($args->order_type)) $args->order_type = 'desc';
		return executeQueryArray('ddggshop.getItemList', $args);
	}

	/** 상품 목록 (배열만) */
	public static function getItemListData($args = null)
	{
		$output = self::getItemList($args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/** 추천 상품 N개 */
	public static function getRecommendedItems($count = 8)
	{
		$args = new stdClass;
		$args->is_recommend = 'Y';
		$args->status = 'sale';
		$args->list_count = (int)$count;
		return self::getItemListData($args);
	}

	/** 신상품 N개 (등록 최신순) */
	public static function getNewItems($count = 8)
	{
		$args = new stdClass;
		$args->status = 'sale';
		$args->list_count = (int)$count;
		return self::getItemListData($args);
	}

	/* ===================== 카테고리 ===================== */

	/** 카테고리 목록 (배열) */
	public static function getCategoryList()
	{
		$output = executeQueryArray('ddggshop.getCategoryList');
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/** 카테고리 맵 (category_srl => obj) */
	public static function getCategoryMap()
	{
		$map = array();
		foreach (self::getCategoryList() as $c)
		{
			$map[$c->category_srl] = $c;
		}
		return $map;
	}

	/** 카테고리 트리(부모→자식 순서, 각 ->depth 보유) */
	public static function getCategoryTreeOrdered()
	{
		$cats = self::getCategoryList();
		$byParent = array();
		foreach ($cats as $c) $byParent[(int)$c->parent_srl][] = $c;
		$out = array();
		self::appendCategoryTree($byParent, 0, $out);
		return $out;
	}

	protected static function appendCategoryTree($byParent, $parent, &$out)
	{
		if (empty($byParent[$parent])) return;
		foreach ($byParent[$parent] as $c)
		{
			$out[] = $c;
			self::appendCategoryTree($byParent, (int)$c->category_srl, $out);
		}
	}

	/** 헤더 드롭다운용 계층 메뉴 (노출 카테고리만, 각 노드 ->children) */
	public static function getCategoryMenu()
	{
		$cats = self::getCategoryList();
		$byParent = array();
		foreach ($cats as $c)
		{
			if ($c->status === 'Y') $byParent[(int)$c->parent_srl][] = $c;
		}
		return self::buildMenuNodes($byParent, 0);
	}

	protected static function buildMenuNodes($byParent, $parent)
	{
		$list = array();
		if (!empty($byParent[$parent]))
		{
			foreach ($byParent[$parent] as $c)
			{
				$c->children = self::buildMenuNodes($byParent, (int)$c->category_srl);
				$list[] = $c;
			}
		}
		return $list;
	}

	/** 해당 카테고리 + 모든 하위 카테고리 srl 배열 */
	public static function getDescendantCategorySrls($category_srl)
	{
		$cats = self::getCategoryList();
		$byParent = array();
		foreach ($cats as $c) $byParent[(int)$c->parent_srl][] = (int)$c->category_srl;
		$result = array((int)$category_srl);
		$stack = array((int)$category_srl);
		while ($stack)
		{
			$p = array_pop($stack);
			if (!empty($byParent[$p]))
			{
				foreach ($byParent[$p] as $ch) { $result[] = $ch; $stack[] = $ch; }
			}
		}
		return $result;
	}

	/** 단일 카테고리 */
	public static function getCategory($category_srl)
	{
		$args = new stdClass;
		$args->category_srl = (int)$category_srl;
		$output = executeQuery('ddggshop.getCategory', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 루트 → 해당 카테고리까지의 경로(브레드크럼용) 배열 반환 */
	public static function getCategoryPath($category_srl)
	{
		$path = array();
		$srl = (int)$category_srl;
		$guard = 0;
		while ($srl && $guard++ < 20)
		{
			$cat = self::getCategory($srl);
			if (!$cat) break;
			array_unshift($path, $cat);
			$srl = (int)$cat->parent_srl;
		}
		return $path;
	}

	/* ===================== 태그 / 배너 ===================== */

	/** 태그 목록 (home_only=true 면 홈 노출+활성만) */
	public static function getTagList($home_only = false)
	{
		$args = new stdClass;
		if ($home_only)
		{
			$args->show_on_home = 'Y';
			$args->status = 'Y';
		}
		$output = executeQueryArray('ddggshop.getTagList', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/** 홈 노출 태그 */
	public static function getHomeTags()
	{
		return self::getTagList(true);
	}

	/** 단일 태그 */
	public static function getTag($tag_srl)
	{
		$args = new stdClass;
		$args->tag_srl = (int)$tag_srl;
		$output = executeQuery('ddggshop.getTag', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 태그에 해당하는 상품 목록 (manual=매핑, popular=판매순, new=최신순) */
	public static function getItemsForTag($tag, $count = null)
	{
		$count = $count ? (int)$count : (int)$tag->home_count;
		if ($count < 1) $count = 8;

		if ($tag->auto_source === 'popular')
		{
			return self::getItemListData((object) array('status' => 'sale', 'sort_index' => 'buy_count', 'list_count' => $count));
		}
		if ($tag->auto_source === 'new')
		{
			return self::getItemListData((object) array('status' => 'sale', 'sort_index' => 'list_order', 'list_count' => $count));
		}
		$args = new stdClass;
		$args->tag_srl = (int)$tag->tag_srl;
		$args->status = 'sale';
		$args->list_count = $count;
		$output = executeQueryArray('ddggshop.getItemsByTag', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/* ===================== 홈 화면 블록 빌더 ===================== */

	/** 상품 블록 소스 종류 (관리자 UI 라벨) */
	public static function blockSourceList()
	{
		return array(
			'manual'    => '직접 선택',
			'sorted'    => '상품 목록 순서 (관리자 정렬순)',
			'category'  => '카테고리',
			'new'       => '신상품',
			'best'      => '베스트셀러',
			'recommend' => '추천 상품',
			'tag'       => '태그',
		);
	}

	public static function blockSourceLabel($source_type)
	{
		$m = self::blockSourceList();
		return isset($m[$source_type]) ? $m[$source_type] : (string)$source_type;
	}

	/** 홈 블록 목록 (정렬순) */
	public static function getHomeBlocks($only_active = true)
	{
		$args = new stdClass;
		if ($only_active) $args->status = 'Y';
		$args->list_count = 100;
		$out = executeQueryArray('ddggshop.getHomeBlocks', $args);
		return $out->toBool() ? ($out->data ?: array()) : array();
	}

	public static function getHomeBlock($block_srl)
	{
		$out = executeQuery('ddggshop.getHomeBlock', (object) array('block_srl' => (int)$block_srl));
		return ($out->toBool() && $out->data) ? $out->data : null;
	}

	/** source_value(JSON 배열 또는 콤마 구분) → item_srl 정수 배열 */
	public static function parseSrlList($val)
	{
		if (is_array($val)) return array_values(array_filter(array_map('intval', $val)));
		$val = trim((string)$val);
		if ($val === '') return array();
		$arr = json_decode($val, true);
		if (!is_array($arr)) $arr = explode(',', $val);
		return array_values(array_filter(array_map('intval', $arr)));
	}

	/** 블록 소스 → 노출 상품 배열 (판매중) */
	public static function resolveBlockItems($block)
	{
		$count = max(1, (int)$block->list_count);
		$st = $block->source_type;
		$val = $block->source_value;

		if ($st === 'manual')
		{
			return self::getItemsBySrls(self::parseSrlList($val));
		}
		if ($st === 'category')
		{
			$cat = (int)$val;
			if (!$cat) return array();
			return self::getItemListData((object) array(
				'category_srls' => self::getDescendantCategorySrls($cat),
				'status' => 'sale', 'sort_index' => 'list_order', 'order_type' => 'desc', 'list_count' => $count,
			));
		}
		if ($st === 'sorted')
		{
			// 관리자 상품목록의 정렬순(list_order desc) — 전체상품 페이지 기본 정렬과 동일
			return self::getItemListData((object) array('status' => 'sale', 'sort_index' => 'list_order', 'order_type' => 'desc', 'list_count' => $count));
		}
		if ($st === 'new')
		{
			return self::getItemListData((object) array('status' => 'sale', 'sort_index' => 'regdate', 'order_type' => 'desc', 'list_count' => $count));
		}
		if ($st === 'best')
		{
			return self::getItemListData((object) array('status' => 'sale', 'sort_index' => 'buy_count', 'order_type' => 'desc', 'list_count' => $count));
		}
		if ($st === 'recommend')
		{
			return self::getItemListData((object) array('status' => 'sale', 'is_recommend' => 'Y', 'sort_index' => 'list_order', 'order_type' => 'desc', 'list_count' => $count));
		}
		if ($st === 'tag')
		{
			$tag_srl = (int)$val;
			if (!$tag_srl) return array();
			$out = executeQueryArray('ddggshop.getItemsByTag', (object) array('tag_srl' => $tag_srl, 'status' => 'sale', 'list_count' => $count));
			return $out->toBool() ? ($out->data ?: array()) : array();
		}
		return array();
	}

	/** 상품에 달린 태그 목록 (뱃지/표시용) */
	public static function getTagsForItem($item_srl)
	{
		$args = new stdClass;
		$args->item_srl = (int)$item_srl;
		$output = executeQueryArray('ddggshop.getItemTags', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/** 상품에 매핑된 tag_srl 배열 */
	public static function getTagSrlsForItem($item_srl)
	{
		$output = executeQueryArray('ddggshop.getItemSrlsByItem', (object) array('item_srl' => (int)$item_srl));
		$ids = array();
		if ($output->toBool() && is_array($output->data))
		{
			foreach ($output->data as $r) $ids[] = (int)$r->tag_srl;
		}
		return $ids;
	}

	/** 단일 배너 */
	public static function getBanner($banner_srl)
	{
		$args = new stdClass;
		$args->banner_srl = (int)$banner_srl;
		$output = executeQuery('ddggshop.getBanner', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 배너 목록 */
	public static function getBannerList($status = '')
	{
		$args = new stdClass;
		if ($status !== '') $args->status = $status;
		$output = executeQueryArray('ddggshop.getBannerList', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/* ===================== 배송지 주소록 ===================== */

	public static function getAddressBookList($member_srl)
	{
		if (!$member_srl) return array();
		$args = new stdClass;
		$args->member_srl = (int)$member_srl;
		$output = executeQueryArray('ddggshop.getAddressBookList', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	public static function getAddressBook($address_book_srl)
	{
		$args = new stdClass;
		$args->address_book_srl = (int)$address_book_srl;
		$output = executeQuery('ddggshop.getAddressBook', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 기본 배송지 (없으면 첫 번째) */
	public static function getDefaultAddressBook($member_srl)
	{
		$list = self::getAddressBookList($member_srl);
		if (!count($list)) return null;
		foreach ($list as $a)
		{
			if ($a->is_default === 'Y') return $a;
		}
		return $list[0];
	}

	/* ===================== 장바구니 ===================== */

	/**
	 * 현재 사용자의 장바구니 키.
	 * 회원: {member_srl}, 비회원: {session_key} (쿠키 기반).
	 */
	public static function getCartKey()
	{
		$key = new stdClass;
		$key->member_srl = null;
		$key->session_key = null;
		if (Context::get('is_logged'))
		{
			$logged = Context::get('logged_info');
			$key->member_srl = (int)$logged->member_srl;
		}
		else
		{
			$key->session_key = self::getGuestSessionKey();
		}
		return $key;
	}

	/** 비회원 장바구니용 세션키 (쿠키, 30일) */
	public static function getGuestSessionKey()
	{
		$ck = isset($_COOKIE['ddggshop_cart']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_COOKIE['ddggshop_cart']) : '';
		if (strlen($ck) < 16)
		{
			$ck = substr(md5(uniqid('ddggshop', true) . mt_rand()), 0, 32);
			@setcookie('ddggshop_cart', $ck, RX_TIME + 60 * 60 * 24 * 30, '/');
			$_COOKIE['ddggshop_cart'] = $ck;
		}
		return $ck;
	}

	/** 장바구니 키를 쿼리 args에 적용 (정확히 하나의 조건만 설정) */
	public static function applyCartKey($args, $key)
	{
		if ($key->member_srl)
		{
			$args->member_srl = (int)$key->member_srl;
		}
		else
		{
			$args->session_key = (string)$key->session_key;
		}
		return $args;
	}

	/** 장바구니 원본 행 */
	public static function getCartRows($key)
	{
		$args = self::applyCartKey(new stdClass, $key);
		$output = executeQueryArray('ddggshop.getCartRows', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/**
	 * 장바구니 상세 (상품정보 결합 + 합계 계산).
	 * 반환: stdClass { rows[], item_total, count, has_unavailable }
	 */
	public static function getCartDetail($key)
	{
		$rows = self::getCartRows($key);
		$member_srl = is_object($key) ? (int)($key->member_srl ?? 0) : 0;
		$cfg = self::getConfig();
		$result = new stdClass;
		$result->rows = array();
		$result->item_total = 0;
		$result->count = 0;
		$result->has_unavailable = false;

		foreach ($rows as $row)
		{
			$item = self::getItem($row->item_srl);
			$r = new stdClass;
			$r->cart_srl = $row->cart_srl;
			$r->item_srl = $row->item_srl;
			$r->option_srl = $row->option_srl;
			$r->qty = max(1, (int)$row->qty);
			if (!$item)
			{
				$r->item_name = '삭제된 상품';
				$r->thumb = '';
				$r->price = 0;
				$r->stock = 0;
				$r->available = false;
				$r->subtotal = 0;
				$result->has_unavailable = true;
				$result->rows[] = $r;
				continue;
			}
			$r->item_name = $item->item_name;
			$r->thumb = $item->thumb;
			$r->use_stock = $item->use_stock;
			$r->status = $item->status;
			$r->ship_fee_type = isset($item->ship_fee_type) && $item->ship_fee_type ? $item->ship_fee_type : 'default';
			$r->ship_fee = (int)(isset($item->ship_fee) ? $item->ship_fee : 0);
			$r->require_address = (isset($item->require_address) && $item->require_address === 'N') ? 'N' : 'Y';
			$r->option_label = '';

			if ((int)$row->option_srl)
			{
				$opt = self::getItemOption($row->option_srl);
				if (!$opt || $opt->status !== 'Y')
				{
					$r->price = (int)$item->price;
					$r->stock = 0;
					$r->available = false;
				}
				else
				{
					$r->option_label = $opt->option_label;
					$r->price = (int)$item->price + (int)$opt->price_add;
					$r->stock = (int)$opt->stock;
					$r->available = ($item->status === 'sale' && ($item->use_stock !== 'Y' || (int)$opt->stock >= $r->qty));
				}
			}
			else
			{
				$r->price = (int)$item->price;
				$r->stock = (int)$item->stock;
				$r->available = ($item->status === 'sale' && ($item->use_stock !== 'Y' || $item->stock >= $r->qty));
			}
			if (!$r->available) $result->has_unavailable = true;
			// 회원 그룹 가격 할인 적용
			$r->orig_price = (int)$r->price;
			$r->price = self::discountedUnitPrice($r->price, $member_srl, $cfg);
			$r->discounted = ($r->price < $r->orig_price);
			$r->subtotal = $r->price * $r->qty;
			$result->item_total += $r->subtotal;
			$result->count += $r->qty;
			$result->rows[] = $r;
		}
		return $result;
	}

	/**
	 * 주문 금액 계산 (배송비/적립 포함).
	 * 반환: stdClass { item_total, delivery_fee, payment_price, point_earn }
	 */
	/* ===================== 쿠폰 ===================== */

	public static function getCouponList($only_active = false)
	{
		$args = new stdClass;
		if ($only_active) $args->status = 'Y';
		$output = executeQueryArray('ddggshop.getCouponList', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	public static function getCouponByCode($code)
	{
		$output = executeQuery('ddggshop.getCouponByCode', (object) array('code' => trim((string)$code)));
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	public static function getCoupon($coupon_srl)
	{
		$output = executeQuery('ddggshop.getCoupon', (object) array('coupon_srl' => (int)$coupon_srl));
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	public static function getCouponMemberUseCount($coupon_srl, $member_srl)
	{
		$o = executeQuery('ddggshop.getCouponMemberUseCount', (object) array('coupon_srl' => (int)$coupon_srl, 'member_srl' => (int)$member_srl));
		return ($o->toBool() && $o->data) ? (int)$o->data->cnt : 0;
	}

	/** 쿠폰 검증 + 할인액 계산. 반환 {ok, message, coupon, discount} */
	public static function validateCoupon($code, $item_total, $member_srl = 0)
	{
		$r = new stdClass;
		$r->ok = false; $r->discount = 0; $r->coupon = null; $r->message = '';
		$code = trim((string)$code);
		if ($code === '') { $r->message = '쿠폰 코드를 입력해 주세요.'; return $r; }
		$c = self::getCouponByCode($code);
		if (!$c || $c->status !== 'Y') { $r->message = '유효하지 않은 쿠폰입니다.'; return $r; }
		$today = date('Ymd');
		if ($c->start_date && substr($c->start_date, 0, 8) > $today) { $r->message = '아직 사용 기간이 아닙니다.'; return $r; }
		if ($c->end_date && substr($c->end_date, 0, 8) < $today) { $r->message = '사용 기간이 만료된 쿠폰입니다.'; return $r; }
		if ((int)$c->min_order > 0 && (int)$item_total < (int)$c->min_order) { $r->message = number_format($c->min_order) . '원 이상 구매 시 사용 가능합니다.'; return $r; }
		if ((int)$c->usage_limit > 0 && (int)$c->used_count >= (int)$c->usage_limit) { $r->message = '쿠폰이 모두 소진되었습니다.'; return $r; }
		if ((int)$c->per_member > 0)
		{
			if (!$member_srl) { $r->message = '로그인 후 사용할 수 있는 쿠폰입니다.'; return $r; }
			if (self::getCouponMemberUseCount($c->coupon_srl, $member_srl) >= (int)$c->per_member) { $r->message = '이미 사용한 쿠폰입니다.'; return $r; }
		}
		if ($c->discount_type === 'percent')
		{
			$d = (int)floor((int)$item_total * (int)$c->discount_value / 100);
			if ((int)$c->max_discount > 0 && $d > (int)$c->max_discount) $d = (int)$c->max_discount;
		}
		else
		{
			$d = (int)$c->discount_value;
		}
		if ($d > (int)$item_total) $d = (int)$item_total;
		if ($d <= 0) { $r->message = '할인 금액이 없습니다.'; return $r; }
		$r->ok = true; $r->coupon = $c; $r->discount = $d; $r->message = '쿠폰이 적용되었습니다.';
		return $r;
	}

	/** 회원에게 쿠폰 발급 (미사용 보유분 있으면 스킵). 반환: 발급 여부 */
	public static function issueCoupon($coupon_srl, $member_srl)
	{
		$coupon_srl = (int)$coupon_srl; $member_srl = (int)$member_srl;
		if (!$coupon_srl || !$member_srl) return false;
		$c = executeQuery('ddggshop.getCouponIssueCount', (object) array('coupon_srl' => $coupon_srl, 'member_srl' => $member_srl, 'used' => 'N'));
		if ($c->toBool() && $c->data && (int)$c->data->cnt > 0) return false;
		$a = new stdClass;
		$a->issue_srl = getNextSequence();
		$a->coupon_srl = $coupon_srl;
		$a->member_srl = $member_srl;
		$a->used = 'N';
		executeQuery('ddggshop.insertCouponIssue', $a);
		return true;
	}

	/**
	 * 식별자(회원번호/아이디/이메일/닉네임)를 member_srl로 변환
	 * @return int 0이면 회원 없음
	 */
	public static function resolveMemberSrl($identifier)
	{
		$identifier = trim((string)$identifier);
		if ($identifier === '') return 0;

		// 숫자만 → 회원번호로 간주(존재 확인)
		if (ctype_digit($identifier))
		{
			$info = \MemberModel::getMemberInfoByMemberSrl((int)$identifier);
			if ($info && $info->member_srl) return (int)$info->member_srl;
		}
		// 이메일
		if (strpos($identifier, '@') !== false)
		{
			$info = \MemberModel::getMemberInfoByEmailAddress($identifier);
			if ($info && $info->member_srl) return (int)$info->member_srl;
			return 0;
		}
		// 아이디
		$srl = \MemberModel::getMemberSrlByUserID($identifier);
		if ($srl) return (int)$srl;
		// 닉네임
		$srl = \MemberModel::getMemberSrlByNickName($identifier);
		if ($srl) return (int)$srl;
		return 0;
	}

	/** 회원 검색 (아이디·닉네임·이메일 부분일치) — 쿠폰 개별발급 등 */
	public static function searchMembers($keyword, $limit = 20)
	{
		$keyword = trim((string)$keyword);
		if ($keyword === '') return array();
		$args = new stdClass;
		$args->s_user_id = $keyword;
		$args->s_nick_name = $keyword;
		$args->s_email_address = $keyword;
		$args->list_count = (int)$limit ?: 20;
		$args->sort_index = 'member_srl';
		$args->sort_order = 'desc';
		$out = executeQueryArray('member.getMemberList', $args);
		$rows = ($out->toBool() && $out->data) ? $out->data : array();
		$res = array();
		foreach ($rows as $m)
		{
			$res[] = (object) array(
				'member_srl' => (int)$m->member_srl,
				'user_id'    => $m->user_id,
				'nick_name'  => $m->nick_name,
				'email'      => $m->email_address,
			);
		}
		return $res;
	}

	/** 회원 보유 쿠폰함 (미사용·유효) */
	public static function getMemberCoupons($member_srl)
	{
		if (!$member_srl) return array();
		$out = executeQueryArray('ddggshop.getMemberCoupons', (object) array('member_srl' => (int)$member_srl, 'used' => 'N', 'status' => 'Y'));
		$list = $out->toBool() ? ($out->data ?: array()) : array();
		$today = date('Ymd'); $res = array();
		foreach ($list as $c)
		{
			if ($c->end_date && substr($c->end_date, 0, 8) < $today) continue;
			$c->disc_label = ($c->discount_type === 'percent') ? ((int)$c->discount_value . '%') : (number_format($c->discount_value) . '원');
			$res[] = $c;
		}
		return $res;
	}

	public static function markCouponUsedByMember($coupon_srl, $member_srl, $order_srl)
	{
		executeQuery('ddggshop.markCouponIssueUsed', (object) array('coupon_srl' => (int)$coupon_srl, 'member_srl' => (int)$member_srl, 'order_srl' => (int)$order_srl, 'used_date' => date('YmdHis')));
	}

	public static function getAutoSignupCouponSrls()
	{
		$out = executeQueryArray('ddggshop.getAutoSignupCoupons', (object) array('auto_signup' => 'Y', 'status' => 'Y'));
		$srls = array();
		if ($out->toBool() && $out->data) foreach ($out->data as $r) $srls[] = (int)$r->coupon_srl;
		return $srls;
	}

	public static function recordCouponUse($coupon, $member_srl, $order_srl, $discount)
	{
		$log = new stdClass;
		$log->log_srl = getNextSequence();
		$log->coupon_srl = (int)$coupon->coupon_srl;
		$log->member_srl = (int)$member_srl;
		$log->order_srl = (int)$order_srl;
		$log->discount = (int)$discount;
		executeQuery('ddggshop.insertCouponLog', $log);
		executeQuery('ddggshop.updateCouponUsed', (object) array('coupon_srl' => (int)$coupon->coupon_srl, 'used_count' => (int)$coupon->used_count + 1));
	}

	/* ===================== 주문 취소/환불 중앙 처리 (정합성) ===================== */

	/**
	 * 주문 취소·환불을 한 곳에서 처리: PG 취소(환불) → (트랜잭션) 상태변경·재고복원·적립금 회수/환불·쿠폰 복원.
	 * 고객 취소와 관리자 상태변경(cancelled/refunded)이 모두 이 메서드를 거쳐 정합성을 보장한다.
	 * idempotent: 이미 cancelled/refunded인 주문은 부작용 없이 성공(0) 반환.
	 *
	 * @param object $order   대상 주문(현재 상태 스냅샷)
	 * @param string $to_status 'cancelled' | 'refunded'
	 * @param string $reason  취소 사유(PG 취소·로그용)
	 * @return BaseObject
	 */
	public static function cancelOrder($order, $to_status, $reason = '')
	{
		if (!$order) return new BaseObject(-1, '주문을 찾을 수 없습니다.');
		if (!in_array($to_status, array('cancelled', 'refunded'), true)) $to_status = 'cancelled';
		// 이미 취소/환불된 주문 → 중복 처리 방지
		if (in_array($order->status, array('cancelled', 'refunded'), true))
		{
			return new BaseObject(0, '이미 취소/환불된 주문입니다.');
		}

		// 토스 결제완료건은 PG 취소(환불) — 네트워크 호출이므로 트랜잭션 밖에서 먼저 수행
		if ($order->status === 'paid' && $order->pay_method === 'toss' && $order->pg_tid)
		{
			$config = self::getConfig();
			$gw = self::getGateway('toss', $config);
			$res = $gw->cancel($order, $reason ?: '주문취소');
			self::logPayment($order, 'cancel', array('reason' => $reason), $res->raw, $res->success ? 'S' : 'F', $order->pg_tid);
			if (!$res->success) return new BaseObject(-1, '결제 취소(환불)에 실패했습니다. ' . $res->message);
		}

		$oDB = DB::getInstance();
		$oDB->begin();
		try
		{
			// 상태 변경 (가드: 이미 cancelled/refunded면 0건 → 동시 취소 방지)
			$u = new stdClass;
			$u->order_srl = (int)$order->order_srl;
			$u->status = $to_status;
			$u->admin_memo = isset($order->admin_memo) ? $order->admin_memo : '';
			$u->exclude_status = array('cancelled', 'refunded');
			$o1 = executeQuery('ddggshop.updateOrderStatusGuarded', $u);
			if (!$o1->toBool()) throw new Exception();
			if ($oDB->getAffectedRows() < 1)
			{
				$oDB->rollback();
				return new BaseObject(0, '이미 취소/환불 처리된 주문입니다.');
			}

			// 재고 복원
			self::restoreOrderStock((int)$order->order_srl);

			// 결제완료였던 회원 주문은 적립 포인트 회수
			if ($order->status === 'paid' && $order->member_srl)
			{
				$items = self::getOrderItemList((int)$order->order_srl);
				$pt = 0;
				foreach ($items as $oi) $pt += (int)$oi->point_amount;
				if ($pt > 0) getController('point')->setPoint((int)$order->member_srl, $pt, 'minus', 'ddggshop_order_cancel');
			}
			// 사용한 적립금 환불
			if ($order->member_srl && (int)$order->used_point > 0)
			{
				getController('point')->setPoint((int)$order->member_srl, (int)$order->used_point, 'plus', 'ddggshop_use_refund');
			}
			// 쿠폰 사용 복원
			self::restoreOrderCoupon($order);

			$oDB->commit();
		}
		catch (Exception $e)
		{
			$oDB->rollback();
			return new BaseObject(-1, '주문 취소 처리 중 오류가 발생했습니다.');
		}
		self::logOrder((int)$order->order_srl, ($to_status === 'refunded' ? 'refund' : 'cancel'), $order->status, $to_status, $reason);
		return new BaseObject(0, '주문이 취소되었습니다.');
	}

	/** 옵션 사용 상품의 본체 재고를 옵션 재고 합계로 동기화. 반환: 합계 */
	public static function syncItemStockFromOptions($item_srl)
	{
		$opts = self::getItemOptions($item_srl, false);
		$sum = 0;
		foreach ($opts as $o) $sum += max(0, (int) $o->stock);
		executeQuery('ddggshop.setItemStock', (object) array('item_srl' => (int) $item_srl, 'stock' => $sum));
		return $sum;
	}

	/** 주문 상품 재고 복원 (원자적: stock += qty, buy_count -= qty) */
	public static function restoreOrderStock($order_srl)
	{
		$items = self::getOrderItemList($order_srl);
		foreach ($items as $oi)
		{
			if (!$oi->item_srl) continue;
			$item = self::getItem($oi->item_srl);
			if (!$item) continue;
			$qty = (int)$oi->qty;
			if ($item->use_stock === 'Y' && (int)$oi->option_srl)
			{
				executeQuery('ddggshop.addItemOptionStock', (object) array('option_srl' => (int)$oi->option_srl, 'stock_delta' => $qty));
				executeQuery('ddggshop.addItemStock', (object) array('item_srl' => (int)$oi->item_srl, 'stock_delta' => 0, 'buy_count_delta' => -$qty));
				self::syncItemStockFromOptions((int)$oi->item_srl);
			}
			elseif ($item->use_stock === 'Y')
			{
				executeQuery('ddggshop.addItemStock', (object) array('item_srl' => (int)$oi->item_srl, 'stock_delta' => $qty, 'buy_count_delta' => -$qty));
			}
			else
			{
				executeQuery('ddggshop.addItemStock', (object) array('item_srl' => (int)$oi->item_srl, 'stock_delta' => 0, 'buy_count_delta' => -$qty));
			}
		}
	}

	/** 주문에 사용된 쿠폰 복원 (used_count 감소, 사용로그 삭제, 회원 보유쿠폰 미사용 복원) */
	public static function restoreOrderCoupon($order)
	{
		if (empty($order->coupon_code)) return;
		$coupon = self::getCouponByCode($order->coupon_code);
		if ($coupon)
		{
			executeQuery('ddggshop.decreaseCouponUsed', (object) array('coupon_srl' => (int)$coupon->coupon_srl, 'one' => 1, 'zero' => 0));
		}
		executeQuery('ddggshop.deleteCouponLogByOrder', (object) array('order_srl' => (int)$order->order_srl));
		if ($order->member_srl && $coupon)
		{
			executeQuery('ddggshop.restoreCouponIssue', (object) array('coupon_srl' => (int)$coupon->coupon_srl, 'member_srl' => (int)$order->member_srl, 'order_srl' => (int)$order->order_srl));
		}
	}

	/** 결제 로그 기록 (model 정적 컨텍스트용 — controller logPay와 동일 스키마) */
	public static function logPayment($order, $action, $req, $res, $result, $tid)
	{
		$a = new stdClass;
		$a->log_srl = getNextSequence();
		$a->order_srl = $order ? (int)$order->order_srl : 0;
		$a->order_code = $order ? $order->order_code : '';
		$a->gateway = $order ? $order->pay_method : '';
		$a->action = $action;
		$a->request_data = is_string($req) ? $req : json_encode($req);
		$a->response_data = is_string($res) ? $res : json_encode($res);
		$a->result = $result;
		$a->pg_tid = (string)$tid;
		$a->amount = $order ? (int)$order->payment_price : 0;
		$a->regdate = date('YmdHis');
		executeQuery('ddggshop.insertPaymentLog', $a);
	}

	/** 우편번호 구간 추가배송비 규칙 목록 */
	public static function getShipFeeList($only_active = false)
	{
		$args = new stdClass;
		if ($only_active) $args->status = 'Y';
		$output = executeQueryArray('ddggshop.getShipFees', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/** 우편번호가 속한 추가배송비 (첫 매칭). 신우편번호 5자리 기준 */
	public static function matchShipFee($zipcode)
	{
		$z = preg_replace('/[^0-9]/', '', (string)$zipcode);
		if ($z === '') return 0;
		$z = substr(str_pad($z, 5, '0', STR_PAD_LEFT), 0, 5);
		foreach (self::getShipFeeList(true) as $r)
		{
			$zs = preg_replace('/[^0-9]/', '', (string)$r->zip_start);
			$ze = preg_replace('/[^0-9]/', '', (string)$r->zip_end);
			if ($zs === '' || $ze === '') continue; // 지역명 규칙(우편번호 없음)은 자동매칭 제외 → 주문 시 선택
			$s = substr(str_pad($zs, 5, '0', STR_PAD_LEFT), 0, 5);
			$e = substr(str_pad($ze, 5, '0', STR_PAD_LEFT), 0, 5);
			if ($z >= $s && $z <= $e) return (int)$r->extra_fee;
		}
		return 0;
	}

	/** 우편번호 없이 지역명으로 등록된 추가배송비 규칙(해외/지역 선택용) */
	public static function getRegionShipRules()
	{
		$out = array();
		foreach (self::getShipFeeList(true) as $r)
		{
			$zs = preg_replace('/[^0-9]/', '', (string)$r->zip_start);
			$ze = preg_replace('/[^0-9]/', '', (string)$r->zip_end);
			if (($zs === '' || $ze === '') && trim((string)$r->region) !== '')
			{
				$out[] = $r;
			}
		}
		return $out;
	}

	/** 선택된 지역 규칙(fee_srl)의 추가배송비 — 지역명 규칙(우편번호 없음)만 인정 */
	public static function regionExtraFee($fee_srl)
	{
		$fee_srl = (int)$fee_srl;
		if (!$fee_srl) return 0;
		foreach (self::getRegionShipRules() as $r)
		{
			if ((int)$r->fee_srl === $fee_srl) return max(0, (int)$r->extra_fee);
		}
		return 0;
	}

	/** 알림센터(ncenterlite)로 회원에게 알림 발송 (설치/미설치 안전) */
	public static function notifyMember($member_srl, $message, $url = '', $type = '')
	{
		$member_srl = (int)$member_srl;
		if (!$member_srl) return;
		$config = self::getConfig();
		if (($config->use_order_notify ?? 'Y') !== 'Y') return;
		// 이벤트별 on/off (설정에 없으면 기본 on)
		if ($type !== '' && is_array($config->notify_events ?? null) && isset($config->notify_events[$type]) && $config->notify_events[$type] !== 'Y') return;
		try
		{
			$oController = getController('ncenterlite');
			if ($oController && method_exists($oController, 'sendNotification'))
			{
				$oController->sendNotification(0, $member_srl, $message, $url);
			}
		}
		catch (\Throwable $e) { }
	}

	/** 알림 이벤트 정의 (관리자 설정 UI·게이트 공용) */
	public static function notifyEventList()
	{
		return array(
			'admin_neworder' => '관리자 · 신규 주문 접수',
			'order_received' => '회원 · 주문 접수(무통장 입금대기)',
			'paid'           => '회원 · 결제완료 / 입금확인',
			'shipping'       => '회원 · 상품 발송(송장 등록)',
			'status'         => '회원 · 주문 상태 변경',
			'cancelled'      => '회원 · 주문 취소',
			'coupon'         => '회원 · 쿠폰 발급',
		);
	}

	/* ===================== 교환/반품 클레임 + 주문 이력 ===================== */

	public static function claimTypeLabel($type, $lang = 'ko')
	{
		$m = array('exchange' => '교환', 'return' => '반품', 'cancel' => '취소', 'refund' => '환불');
		$fallback = isset($m[$type]) ? $m[$type] : '반품';
		return self::i18nLabel('claim_' . (isset($m[$type]) ? $type : 'return'), $fallback, $lang);
	}

	/**
	 * 관리자 직접 취소/환불 시 교환/반품 내역에도 기록(type=cancel|refund, status=completed).
	 * 이미 자동 기록이 있으면 갱신(취소↔환불 전환 시 타입/금액 동기화).
	 */
	public static function recordCancelClaim($order, $status, $memo = '')
	{
		if (!$order) return;
		$type = ($status === 'refunded') ? 'refund' : 'cancel';
		$refund = ($status === 'refunded') ? (int)$order->payment_price : 0;

		$list = self::getClaimList((object) array('order_srl' => (int)$order->order_srl, 'list_count' => 50));
		$existing = null;
		if ($list->toBool() && $list->data)
		{
			foreach ($list->data as $c)
			{
				if (in_array($c->type, array('cancel', 'refund'), true)) { $existing = $c; break; }
			}
		}
		if ($existing)
		{
			executeQuery('ddggshop.updateClaimResolution', (object) array(
				'claim_srl' => (int)$existing->claim_srl, 'type' => $type, 'status' => 'completed',
				'refund_amount' => $refund, 'admin_memo' => $memo, 'processed_date' => date('YmdHis')));
			return;
		}
		$args = new stdClass;
		$args->claim_srl = getNextSequence();
		$args->order_srl = (int)$order->order_srl;
		$args->order_code = $order->order_code;
		$args->member_srl = (int)$order->member_srl;
		$args->type = $type;
		$args->reason = '관리자 ' . self::statusLabel($status) . ' 처리';
		$args->items = '';
		$args->status = 'completed';
		$args->refund_amount = $refund;
		$args->admin_memo = $memo;
		executeQuery('ddggshop.insertClaim', $args);
	}

	public static function claimStatusLabel($status, $lang = 'ko')
	{
		$m = array('requested' => '신청', 'approved' => '승인', 'rejected' => '반려', 'completed' => '완료');
		return self::i18nLabel('claimst_' . $status, isset($m[$status]) ? $m[$status] : $status, $lang);
	}

	public static function getClaim($claim_srl)
	{
		$out = executeQuery('ddggshop.getClaim', (object) array('claim_srl' => (int)$claim_srl));
		return ($out->toBool() && $out->data) ? $out->data : null;
	}

	public static function getClaimList($args = null)
	{
		if (!is_object($args)) $args = new stdClass;
		if (!isset($args->list_count)) $args->list_count = 20;
		if (!isset($args->page)) $args->page = 1;
		return executeQueryArray('ddggshop.getClaimList', $args);
	}

	public static function getMemberClaims($member_srl)
	{
		$out = self::getClaimList((object) array('member_srl' => (int)$member_srl, 'list_count' => 100));
		return $out->toBool() ? ($out->data ?: array()) : array();
	}

	/** 주문에 진행중(신청/승인)인 클레임이 있으면 반환 */
	public static function getOpenClaim($order_srl)
	{
		$out = executeQueryArray('ddggshop.getOpenClaim', (object) array('order_srl' => (int)$order_srl, 'exclude_status' => array('completed', 'rejected'), 'list_count' => 1));
		$rows = $out->toBool() ? ($out->data ?: array()) : array();
		return count($rows) ? $rows[0] : null;
	}

	public static function countClaims($status = '')
	{
		$args = new stdClass;
		if ($status !== '') $args->status = $status;
		$out = executeQuery('ddggshop.getClaimCount', $args);
		return ($out->toBool() && $out->data) ? (int)$out->data->count : 0;
	}

	/** 주문 상태/처리 이력 1줄 기록 (audit) */
	public static function logOrder($order_srl, $action, $from = '', $to = '', $memo = '')
	{
		$order_srl = (int)$order_srl;
		if (!$order_srl) return;
		$logged = Context::get('logged_info');
		$a = new stdClass;
		$a->log_srl = getNextSequence();
		$a->order_srl = $order_srl;
		$a->actor_srl = $logged ? (int)$logged->member_srl : 0;
		$a->actor_name = $logged ? (string)$logged->nick_name : '시스템';
		$a->action = (string)$action;
		$a->from_status = (string)$from;
		$a->to_status = (string)$to;
		$a->memo = (string)$memo;
		$a->regdate = date('YmdHis');
		executeQuery('ddggshop.insertOrderLog', $a);
	}

	public static function getOrderLogs($order_srl)
	{
		$out = executeQueryArray('ddggshop.getOrderLogs', (object) array('order_srl' => (int)$order_srl, 'list_count' => 100));
		return $out->toBool() ? ($out->data ?: array()) : array();
	}

	public static function orderLogActionLabel($action)
	{
		$m = array(
			'order_created'    => '주문 생성',
			'paid'             => '결제완료',
			'deposit_confirmed' => '입금확인',
			'status_change'    => '상태변경',
			'shipping'         => '발송처리',
			'cancel'           => '주문취소',
			'refund'           => '환불',
			'claim_request'    => '교환/반품 신청',
			'claim_approve'    => '클레임 승인',
			'claim_reject'     => '클레임 반려',
			'claim_complete'   => '클레임 완료',
		);
		return isset($m[$action]) ? $m[$action] : $action;
	}

	/** 회원의 적용 적립률 (%) — 기본 적립률과 회원 그룹별 등급 적립률 중 최고값 */
	public static function memberPointRate($member_srl, $config = null)
	{
		if ($config === null) $config = self::getConfig();
		$rate = (int)$config->point_rate;
		$grades = isset($config->grade_rates) && is_array($config->grade_rates) ? $config->grade_rates : array();
		if ($member_srl && count($grades) && class_exists('MemberModel'))
		{
			$groups = MemberModel::getMemberGroups((int)$member_srl);
			if (is_array($groups))
			{
				foreach (array_keys($groups) as $g)
				{
					if (isset($grades[(int)$g]) && (int)$grades[(int)$g] > $rate) $rate = (int)$grades[(int)$g];
				}
			}
		}
		return $rate;
	}

	/**
	 * 회원의 그룹별 가격 할인 규칙 목록 (해당 회원이 속한 그룹의 규칙만).
	 * 각 규칙: array('type'=>'percent'|'amount','value'=>int)
	 */
	public static function memberDiscountRules($member_srl, $config = null)
	{
		if ($config === null) $config = self::getConfig();
		$defs = isset($config->grade_discounts) && is_array($config->grade_discounts) ? $config->grade_discounts : array();
		if (!$member_srl || !count($defs) || !class_exists('MemberModel')) return array();
		$groups = MemberModel::getMemberGroups((int)$member_srl);
		if (!is_array($groups)) return array();
		$rules = array();
		foreach (array_keys($groups) as $g)
		{
			if (!isset($defs[(int)$g])) continue;
			$d = $defs[(int)$g];
			$type = (isset($d['type']) && $d['type'] === 'amount') ? 'amount' : 'percent';
			$val = max(0, (int)($d['value'] ?? 0));
			if ($val <= 0) continue;
			$rules[] = array('type' => $type, 'value' => $val);
		}
		return $rules;
	}

	/** 단가에 회원 그룹 할인을 적용한 가격 (여러 그룹이면 가장 큰 할인 적용). */
	public static function discountedUnitPrice($price, $member_srl, $config = null)
	{
		$price = (int)$price;
		if ($price <= 0 || !$member_srl) return $price;
		$best = $price;
		foreach (self::memberDiscountRules($member_srl, $config) as $d)
		{
			$p = ($d['type'] === 'amount') ? ($price - $d['value']) : (int)floor($price * (100 - min(100, $d['value'])) / 100);
			if ($p < 0) $p = 0;
			if ($p < $best) $best = $p;
		}
		return $best;
	}

	/** 현재 로그인 회원 기준 member_srl (없으면 0) */
	public static function currentMemberSrl()
	{
		if (Context::get('is_logged'))
		{
			$logged = Context::get('logged_info');
			return (int)$logged->member_srl;
		}
		return 0;
	}

	/** 회원 할인 표시용 배지 텍스트 (예: "회원 10% 할인" / "회원 2,000원 할인"), 없으면 '' */
	public static function memberDiscountBadge($member_srl, $config = null)
	{
		$rules = self::memberDiscountRules($member_srl, $config);
		if (!count($rules)) return '';
		// 표시에는 첫 규칙 기준(퍼센트 우선) — 실제 적용은 상품별 최대 할인
		$pct = 0; $amt = 0;
		foreach ($rules as $d)
		{
			if ($d['type'] === 'percent') $pct = max($pct, $d['value']);
			else $amt = max($amt, $d['value']);
		}
		if ($pct > 0) return str_replace('%s', $pct, '회원 %s% 할인');
		if ($amt > 0) return str_replace('%s', number_format($amt), '회원 %s원 할인');
		return '';
	}

	/** 택배사 → 배송조회 URL 템플릿 (단일 관리) */
	public static function carrierMap()
	{
		return array(
			'CJ대한통운'      => 'https://trace.cjlogistics.com/next/tracking.html?wblNo=%s',
			'우체국택배'      => 'https://service.epost.go.kr/trace.RetrieveDomRigiTraceList.comm?sid1=%s',
			'한진택배'        => 'https://www.hanjin.com/kor/CMS/DeliveryMgr/WaybillResult.do?mCode=MN038&schLang=KR&wblnumText2=%s',
			'롯데택배'        => 'https://www.lotteglogis.com/home/reservation/tracking/linkView?InvNo=%s',
			'로젠택배'        => 'https://www.ilogen.com/web/personal/trace/%s',
			'GS Postbox 택배' => 'https://www.gspostbox.com/p/d_tracking?invoice_no=%s',
			'CU 편의점택배'   => 'https://www.cupost.co.kr/postbox/delivery/local.cupost?invoice_no=%s',
		);
	}

	/** 택배사 이름 목록 (설정·주문폼 공용) */
	public static function carrierList()
	{
		return array('CJ대한통운', '우체국택배', '한진택배', '롯데택배', '로젠택배', 'GS Postbox 택배', 'CU 편의점택배', '경동택배', '대신택배', '일양로지스', '자체배송');
	}

	/** 택배사 송장 → 배송조회 URL (지원 택배사만, 미지원/빈 송장이면 '') */
	public static function trackingUrl($company, $invoice)
	{
		$inv = preg_replace('/[^0-9]/', '', (string)$invoice);
		if ($inv === '') return '';
		$company = trim((string)$company);
		if ($company === '자체배송') return '';
		$map = self::carrierMap();
		// 정확 일치 우선
		if (isset($map[$company])) return sprintf($map[$company], rawurlencode($inv));
		// 부분 일치 (구버전 자유입력 호환)
		foreach ($map as $name => $tpl)
		{
			if ($company !== '' && (strpos($company, $name) !== false || strpos($name, $company) !== false))
			{
				return sprintf($tpl, rawurlencode($inv));
			}
		}
		// 미지원/표기불일치: 통합 조회(스마트택배) 폴백
		return 'https://info.sweettracker.co.kr/tracking/5?t_invoice=' . rawurlencode($inv);
	}

	/** 쇼핑몰 인스턴스 mid (알림 URL용) */
	public static function getShopMid()
	{
		static $mid = null;
		if ($mid !== null) return $mid;
		$out = executeQueryArray('module.getMidList', (object) array('module' => 'ddggshop'));
		$mid = ($out->toBool() && $out->data) ? $out->data[0]->mid : '';
		return $mid;
	}

	/** 고객용 주문 상세 URL */
	public static function memberOrderUrl($order_code)
	{
		$mid = self::getShopMid();
		if (!$mid) return '';
		return getUrl('', 'mid', $mid, 'act', 'dispDdggshopOrderResult', 'order_code', $order_code);
	}
	/** 상품 상세 URL (알림 등에서 사용 — 쇼핑몰 mid를 명시해 standalone 라우팅 오류 방지) */
	public static function productUrl($item_srl)
	{
		$mid = self::getShopMid();
		if (!$mid) return '';
		return getUrl('', 'mid', $mid, 'act', 'dispDdggshopProductDetail', 'item_srl', (int)$item_srl);
	}

	/** 관리자 콘솔 주문 상세 URL */
	public static function consoleOrderUrl($order_srl)
	{
		$mid = self::getShopMid();
		if (!$mid) return '';
		return getUrl('', 'mid', $mid, 'act', 'dispDdggshopConsoleOrderView', 'order_srl', $order_srl);
	}

	/** 사이트 관리자 회원 srl 목록 */
	public static function getAdminMemberSrls()
	{
		$out = executeQueryArray('member.getMemberList', (object) array('is_admin' => 'Y', 'list_count' => 20));
		$srls = array();
		if ($out->toBool() && $out->data) foreach ($out->data as $m) $srls[] = (int)$m->member_srl;
		return $srls;
	}

	/** 기간별 인기 상품 (결제완료 이상, 매출순) */
	public static function getTopItems($from_date = '', $to_date = '', $limit = 10)
	{
		$args = new stdClass;
		$args->status_list = array('paid', 'preparing', 'shipping', 'delivered');
		if ($from_date) $args->from_date = $from_date;
		if ($to_date) $args->to_date = $to_date;
		$args->list_count = $limit;
		$output = executeQueryArray('ddggshop.getTopItems', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	/** 기본 배송정책에 따른 1건 배송비 (무료/임계/기본) */
	public static function basePolicyFee($item_total, $config)
	{
		if (($config->delivery_fee_type ?? 'paid') === 'free') return 0;
		$th = (int)$config->free_threshold;
		if ($th > 0 && (int)$item_total >= $th) return 0;
		return (int)$config->base_delivery_fee;
	}

	/**
	 * 배송비 계산. 상품별 배송비(무료/개별/기본) 중 가장 비싼 1건(묶음배송) + 도서산간 추가.
	 * $rows: 각 row에 ship_fee_type/ship_fee 가 있으면 상품별 적용. 없으면 기본정책.
	 */
	public static function calcShippingFee($rows, $config, $item_total, $extra = 0)
	{
		if (!is_array($rows) || !count($rows))
		{
			$fee = ((int)$item_total > 0) ? self::basePolicyFee($item_total, $config) : 0;
		}
		else
		{
			$fees = array();
			foreach ($rows as $r)
			{
				$type = isset($r->ship_fee_type) && $r->ship_fee_type ? $r->ship_fee_type : 'default';
				if ($type === 'free') $fees[] = 0;
				elseif ($type === 'fixed') $fees[] = max(0, (int)(isset($r->ship_fee) ? $r->ship_fee : 0));
				else $fees[] = self::basePolicyFee($item_total, $config);
			}
			$fee = $fees ? max($fees) : 0;
		}
		// 도서산간 등 우편번호 구간 추가배송비
		if ($fee > 0 || $item_total > 0) $fee += max(0, (int)$extra);
		return $fee;
	}

	/**
	 * 이 주문이 배송 주소를 필요로 하는가 (주문설정 정책 + 상품별 설정)
	 * always: 항상 / never: 안받음 / auto: 장바구니에 배송상품(require_address=Y)이 하나라도 있으면 받음
	 */
	public static function orderNeedsAddress($rows, $config = null)
	{
		if ($config === null) $config = self::getConfig();
		$policy = $config->address_policy ?? 'auto';
		if ($policy === 'always') return true;
		if ($policy === 'never') return false;
		if (!is_array($rows) || !count($rows)) return true;
		foreach ($rows as $r)
		{
			$ra = isset($r->require_address) ? $r->require_address : 'Y';
			if ($ra !== 'N') return true;
		}
		return false;
	}

	public static function calcOrderTotals($item_total, $config = null, $rows = null, $extra = 0, $needs_address = true)
	{
		if ($config === null) $config = self::getConfig();
		$item_total = (int)$item_total;
		// 주소를 받지 않는 주문(기프트콘·콘텐츠 등)은 배송비 없음
		$delivery_fee = $needs_address ? self::calcShippingFee($rows, $config, $item_total, $extra) : 0;
		$totals = new stdClass;
		$totals->item_total = $item_total;
		$totals->delivery_fee = $delivery_fee;
		$totals->payment_price = $item_total + $delivery_fee;
		$totals->point_earn = (int)floor($item_total * (int)$config->point_rate / 100);
		return $totals;
	}

	/* ===================== 주문 ===================== */

	public static function getOrder($order_srl)
	{
		$args = new stdClass;
		$args->order_srl = (int)$order_srl;
		$output = executeQuery('ddggshop.getOrder', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	public static function getOrderByCode($order_code)
	{
		$args = new stdClass;
		$args->order_code = (string)$order_code;
		$output = executeQuery('ddggshop.getOrderByCode', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	public static function getOrderItemList($order_srl)
	{
		$args = new stdClass;
		$args->order_srl = (int)$order_srl;
		$output = executeQueryArray('ddggshop.getOrderItemList', $args);
		$rows = $output->toBool() ? ($output->data ?: array()) : array();
		// 스냅샷에 상품코드가 없는 과거 주문은 현재 상품에서 보완
		foreach ($rows as $r)
		{
			if ((!isset($r->item_code) || $r->item_code === '' || $r->item_code === null) && (int)$r->item_srl)
			{
				$it = self::getItem((int)$r->item_srl);
				$r->item_code = ($it && $it->item_code) ? $it->item_code : '';
			}
		}
		return $rows;
	}

	public static function getOrderAddress($order_srl)
	{
		$args = new stdClass;
		$args->order_srl = (int)$order_srl;
		$output = executeQuery('ddggshop.getOrderAddress', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 관리자 주문 목록 (전체 output) */
	public static function getOrderList($args = null)
	{
		if (!is_object($args)) $args = new stdClass;
		if (!isset($args->list_count)) $args->list_count = 20;
		if (!isset($args->page)) $args->page = 1;
		return executeQueryArray('ddggshop.getOrderList', $args);
	}

	/** 상품명으로 주문 srl 목록 (주문 검색용) */
	public static function getOrderItemSrlsByName($keyword)
	{
		$args = new stdClass;
		$args->item_name = (string)$keyword;
		$output = executeQueryArray('ddggshop.getOrderItemSrls', $args);
		$srls = array();
		if ($output->toBool() && is_array($output->data))
		{
			foreach ($output->data as $r) $srls[(int)$r->order_srl] = (int)$r->order_srl;
		}
		return array_values($srls);
	}

	/** 회원 주문 내역 (전체 output) */
	public static function getMemberOrders($member_srl, $page = 1, $list_count = 20, $status = '')
	{
		$args = new stdClass;
		$args->member_srl = (int)$member_srl;
		$args->page = max(1, (int)$page);
		$args->list_count = (int)$list_count;
		if ($status !== '') $args->status = $status;
		return executeQueryArray('ddggshop.getMemberOrders', $args);
	}

	/** 결제 로그 목록 */
	public static function getPaymentLogList($args = null)
	{
		if (!is_object($args)) $args = new stdClass;
		if (!isset($args->list_count)) $args->list_count = 30;
		if (!isset($args->page)) $args->page = 1;
		return executeQueryArray('ddggshop.getPaymentLogList', $args);
	}

	/** 매출 합계/주문 수 (status 또는 status_list, from_date 조건) */
	public static function getSalesSummary($args = null)
	{
		if (!is_object($args)) $args = new stdClass;
		$output = executeQuery('ddggshop.getSalesSum', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		$ret = new stdClass;
		$ret->total_sales = $row ? (int)$row->total_sales : 0;
		$ret->order_count = $row ? (int)$row->order_count : 0;
		return $ret;
	}

	/** 기간 매출원가 합계(현재 상품 원가 기준 — 예상이익 계산용). 반환: int */
	public static function getCostSum($args = null)
	{
		if (!is_object($args)) $args = new stdClass;
		$output = executeQuery('ddggshop.getCostSum', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		return $row ? (int)$row->total_cost : 0;
	}

	/** 상태 라벨 */
	public static function statusLabel($status, $lang = 'ko')
	{
		$map = array(
			'ready'     => '결제 진행중',
			'pending'   => '입금대기',
			'paid'      => '결제완료',
			'preparing' => '배송준비',
			'shipping'  => '배송중',
			'delivered' => '배송완료',
			'cancelled' => '취소',
			'refunded'  => '환불',
		);
		return self::i18nLabel('status_' . $status, isset($map[$status]) ? $map[$status] : $status, $lang);
	}

	/** 라벨 다국어 헬퍼: $lang 이 ko 가 아니고 사전에 키가 있으면 번역, 아니면 $fallback(한국어) */
	protected static function i18nLabel($key, $fallback, $lang)
	{
		if ($lang && $lang !== 'ko')
		{
			$dict = self::loadLangStrings($lang);
			if (isset($dict[$key]) && $dict[$key] !== '') return $dict[$key];
		}
		return $fallback;
	}

	/* ===================== 상품 후기 ===================== */

	/** 후기 목록 (전체 output) */
	public static function getReviewList($item_srl, $page = 1, $list_count = 10)
	{
		$args = new stdClass;
		$args->item_srl = (int)$item_srl;
		$args->status = 'Y';
		$args->page = max(1, (int)$page);
		$args->list_count = (int)$list_count;
		return executeQueryArray('ddggshop.getReviewList', $args);
	}

	/** 후기 통계 {count, avg} */
	public static function getReviewStats($item_srl)
	{
		$args = new stdClass;
		$args->item_srl = (int)$item_srl;
		$args->status = 'Y';
		$output = executeQuery('ddggshop.getReviewStats', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		$ret = new stdClass;
		$ret->count = $row ? (int)$row->cnt : 0;
		$ret->avg = ($row && $row->avg_rating) ? round((float)$row->avg_rating, 1) : 0;
		return $ret;
	}

	public static function getReview($review_srl)
	{
		$args = new stdClass;
		$args->review_srl = (int)$review_srl;
		$output = executeQuery('ddggshop.getReview', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 회원이 해당 상품에 이미 작성한 리뷰 수 (1인 1리뷰·중복적립 방지용) */
	public static function getMemberItemReviewCount($item_srl, $member_srl)
	{
		if (!$member_srl) return 0;
		$out = executeQuery('ddggshop.getMemberItemReviewCount', (object) array('item_srl' => (int)$item_srl, 'member_srl' => (int)$member_srl));
		return ($out->toBool() && $out->data) ? (int)$out->data->count : 0;
	}

	/** 리뷰 images 컬럼(JSON) → URL 배열 */
	public static function parseReviewImages($review)
	{
		if (!$review || empty($review->images)) return array();
		$arr = json_decode($review->images, true);
		return is_array($arr) ? $arr : array();
	}

	/* ===================== 위시리스트 (찜) ===================== */

	/** 찜 여부 */
	public static function isWished($item_srl, $member_srl)
	{
		if (!$member_srl) return false;
		$out = executeQuery('ddggshop.getWishlistCount', (object) array('member_srl' => (int)$member_srl, 'item_srl' => (int)$item_srl));
		return ($out->toBool() && $out->data && (int)$out->data->count > 0);
	}

	/** 찜 토글: 추가/삭제. 반환: array('wished'=>bool) */
	public static function toggleWishlist($item_srl, $member_srl)
	{
		$item_srl = (int)$item_srl; $member_srl = (int)$member_srl;
		if (self::isWished($item_srl, $member_srl))
		{
			executeQuery('ddggshop.deleteWishlist', (object) array('member_srl' => $member_srl, 'item_srl' => $item_srl));
			return array('wished' => false);
		}
		executeQuery('ddggshop.insertWishlist', (object) array('wish_srl' => getNextSequence(), 'member_srl' => $member_srl, 'item_srl' => $item_srl, 'regdate' => date('YmdHis')));
		return array('wished' => true);
	}

	/** 찜 목록(상품 조인) */
	public static function getWishlistItems($member_srl, $page = 1, $list_count = 24)
	{
		$args = new stdClass;
		$args->member_srl = (int)$member_srl;
		$args->page = max(1, (int)$page);
		$args->list_count = (int)$list_count;
		return executeQueryArray('ddggshop.getWishlistItems', $args);
	}

	/* ===================== 다국어 (i18n, 스토어프론트) ===================== */

	const LANG_COOKIE = 'ddggshop_lang';

	/** langs 디렉터리 경로 */
	public static function langDir()
	{
		return __DIR__ . '/langs';
	}

	/** 설치된 언어 목록 스캔 → array(코드 => 표기명). ko 우선 정렬 */
	public static function getAvailableLanguages()
	{
		static $cache = null;
		if ($cache !== null) return $cache;
		$cache = array();
		$dir = self::langDir();
		foreach ((array) @glob($dir . '/*.php') as $file)
		{
			$code = strtolower(basename($file, '.php'));
			if (!preg_match('/^[a-z0-9\-]+$/', $code)) continue;
			$data = @include $file;
			if (!is_array($data)) continue;
			$cache[$code] = isset($data['name']) && $data['name'] !== '' ? (string) $data['name'] : $code;
		}
		if (isset($cache['ko']))
		{
			$ko = array('ko' => $cache['ko']);
			unset($cache['ko']);
			$cache = $ko + $cache;
		}
		return $cache;
	}

	/** 관리자가 켠 노출 언어 목록 (available 와 교집합, 기본언어는 항상 포함) */
	public static function getEnabledLanguages($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$available = self::getAvailableLanguages();
		$enabled = (isset($config->enabled_langs) && is_array($config->enabled_langs)) ? $config->enabled_langs : array('ko', 'en');
		$default = self::getDefaultLang($config);
		$out = array();
		foreach ($enabled as $code)
		{
			$code = strtolower((string) $code);
			if (isset($available[$code])) $out[$code] = $available[$code];
		}
		if (!isset($out[$default]) && isset($available[$default])) $out[$default] = $available[$default];
		if (!count($out) && isset($available['ko'])) $out['ko'] = $available['ko'];
		return $out;
	}

	/** 기본 언어 코드 */
	public static function getDefaultLang($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$def = strtolower((string) ($config->default_lang ?? 'ko'));
		$available = self::getAvailableLanguages();
		return isset($available[$def]) ? $def : (isset($available['ko']) ? 'ko' : (string) key($available));
	}

	/**
	 * 현재 방문자에게 적용할 언어 코드
	 * 우선순위: 수동선택(쿠키) → 브라우저 언어 자동감지 → 기본언어
	 */
	public static function getActiveLang($config = null)
	{
		if ($config === null) $config = self::getConfig();
		// 다국어 미사용 시 항상 기본언어
		if (($config->use_multilang ?? 'N') !== 'Y') return self::getDefaultLang($config);
		$enabled = self::getEnabledLanguages($config);
		// 1) 방문자가 직접 고른 언어(쿠키) — 최우선
		$cookie = isset($_COOKIE[self::LANG_COOKIE]) ? strtolower((string) $_COOKIE[self::LANG_COOKIE]) : '';
		if ($cookie !== '' && isset($enabled[$cookie])) return $cookie;
		// 2) 브라우저 언어 자동감지 (Accept-Language)
		$browser = self::detectBrowserLang($enabled);
		if ($browser !== '') return $browser;
		// 3) 관리자 지정 기본언어
		return self::getDefaultLang($config);
	}

	/** 브라우저 Accept-Language 를 노출 언어 목록과 매칭 (없으면 '') */
	public static function detectBrowserLang($enabled)
	{
		$al = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
		if ($al === '' || !is_array($enabled) || !count($enabled)) return '';
		$prefs = array();
		foreach (explode(',', $al) as $part)
		{
			$part = trim($part);
			if ($part === '') continue;
			$code = $part; $q = 1.0;
			if (strpos($part, ';') !== false)
			{
				list($code, $qpart) = array_pad(explode(';', $part, 2), 2, '');
				if (preg_match('/q=([0-9.]+)/', $qpart, $m)) $q = (float) $m[1];
			}
			$code = strtolower(trim($code));
			if ($code !== '') $prefs[] = array($code, $q);
		}
		usort($prefs, function ($a, $b) { return ($b[1] < $a[1]) ? -1 : (($b[1] > $a[1]) ? 1 : 0); });
		foreach ($prefs as $p)
		{
			$code = $p[0];
			if (isset($enabled[$code])) return $code;                 // 정확 일치 (예: zh-cn)
			$primary = preg_replace('/[-_].*$/', '', $code);          // ko-kr → ko
			if (isset($enabled[$primary])) return $primary;
			foreach ($enabled as $ec => $name)                         // enabled 코드의 primary 비교
			{
				if (preg_replace('/[-_].*$/', '', $ec) === $primary) return $ec;
			}
		}
		return '';
	}

	/** 한 언어의 strings 배열 로드 (파일 캐시) */
	public static function loadLangStrings($code)
	{
		static $loaded = array();
		$code = strtolower((string) $code);
		if (isset($loaded[$code])) return $loaded[$code];
		$file = self::langDir() . '/' . $code . '.php';
		$strings = array();
		if (preg_match('/^[a-z0-9\-]+$/', $code) && is_file($file))
		{
			$data = @include $file;
			if (is_array($data) && isset($data['strings']) && is_array($data['strings'])) $strings = $data['strings'];
		}
		$loaded[$code] = $strings;
		return $strings;
	}

	/**
	 * 스토어프론트 번역 사전 반환 (활성 언어를 ko 위에 병합 → 누락 키는 한국어로 대체).
	 * 템플릿에서 {$L['키']} 로 사용.
	 */
	public static function getLangDict($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$active = self::getActiveLang($config);
		$base = self::loadLangStrings('ko');
		if ($active === 'ko') return $base;
		return array_merge($base, self::loadLangStrings($active));
	}

	/** 카테고리 표시명 (활성 언어 기준, 번역 없으면 기본명). name_i18n = {"en":"...","ja":"..."} */
	public static function categoryDisplayName($cat, $lang = null, $config = null)
	{
		if (!is_object($cat)) return '';
		$base = (string) ($cat->category_name ?? '');
		if ($config === null) $config = self::getConfig();
		if ($lang === null) $lang = self::getActiveLang($config);
		if ($lang === '' || $lang === self::getDefaultLang($config)) return $base;
		$raw = $cat->name_i18n ?? '';
		if ($raw === '' || $raw === null) return $base;
		$map = json_decode($raw, true);
		if (is_array($map) && isset($map[$lang]) && trim((string) $map[$lang]) !== '') return (string) $map[$lang];
		return $base;
	}

	/** 카테고리 목록/트리의 category_name 을 활성 언어로 치환 (스토어프론트 표시용) */
	public static function localizeCategoryNames(&$cats, $lang = null, $config = null)
	{
		if (!is_array($cats)) return;
		if ($config === null) $config = self::getConfig();
		if ($lang === null) $lang = self::getActiveLang($config);
		if ($lang === self::getDefaultLang($config)) return; // 기본 언어는 원본 그대로
		foreach ($cats as $c)
		{
			if (!is_object($c)) continue;
			$c->category_name = self::categoryDisplayName($c, $lang, $config);
			if (isset($c->children) && is_array($c->children) && count($c->children))
			{
				self::localizeCategoryNames($c->children, $lang, $config);
			}
		}
	}

	/* ===================== 최근 본 상품 (쿠키 기반) ===================== */

	const RECENT_COOKIE = 'ddggshop_recent';
	const RECENT_MAX = 20;

	/** 상세 조회 시 최근 본 상품에 추가(쿠키, 최신 우선·중복 제거·최대 20개) */
	public static function pushRecentItem($item_srl)
	{
		$item_srl = (int)$item_srl;
		if ($item_srl <= 0) return;
		$srls = self::getRecentItemSrls();
		$srls = array_values(array_diff($srls, array($item_srl)));
		array_unshift($srls, $item_srl);
		$srls = array_slice($srls, 0, self::RECENT_MAX);
		$value = implode(',', $srls);
		@setcookie(self::RECENT_COOKIE, $value, time() + 60 * 60 * 24 * 30, '/');
		$_COOKIE[self::RECENT_COOKIE] = $value;
	}

	/** 최근 본 상품 srl 목록(쿠키) */
	public static function getRecentItemSrls()
	{
		$raw = isset($_COOKIE[self::RECENT_COOKIE]) ? (string)$_COOKIE[self::RECENT_COOKIE] : '';
		if ($raw === '') return array();
		$srls = array();
		foreach (explode(',', $raw) as $s)
		{
			$s = (int)$s;
			if ($s > 0 && !in_array($s, $srls, true)) $srls[] = $s;
		}
		return $srls;
	}

	/** 최근 본 상품 카드(현재 상품 제외, 판매중만, 쿠키 순서 유지) */
	public static function getRecentItems($exclude_srl = 0, $limit = 8)
	{
		$srls = self::getRecentItemSrls();
		if ($exclude_srl) $srls = array_values(array_diff($srls, array((int)$exclude_srl)));
		$srls = array_slice($srls, 0, (int)$limit);
		return self::getItemsBySrls($srls);
	}

	/** item_srl 목록으로 상품 조회(판매중), 입력 순서 보존 */
	public static function getItemsBySrls($item_srls, $status = 'sale')
	{
		$item_srls = array_values(array_filter(array_map('intval', (array)$item_srls)));
		if (!count($item_srls)) return array();
		$args = new stdClass;
		$args->item_srls = $item_srls;
		if ($status) $args->status = $status;
		$out = executeQueryArray('ddggshop.getItemsBySrls', $args);
		$rows = $out->toBool() ? ($out->data ?: array()) : array();
		$by = array();
		foreach ($rows as $r) $by[(int)$r->item_srl] = $r;
		$ordered = array();
		foreach ($item_srls as $s) { if (isset($by[$s])) $ordered[] = $by[$s]; }
		return $ordered;
	}

	/* ===================== 연관 / 교차판매 상품 ===================== */

	/** 같은 카테고리 인기 상품(현재 상품 제외) */
	public static function getRelatedItems($item, $limit = 8)
	{
		if (!$item || !$item->category_srl) return array();
		$out = executeQueryArray('ddggshop.getRelatedItems', (object) array(
			'category_srl' => (int)$item->category_srl,
			'item_srl'     => (int)$item->item_srl,
			'status'       => 'sale',
			'list_count'   => (int)$limit,
		));
		return $out->toBool() ? ($out->data ?: array()) : array();
	}

	/** 함께 구매한 상품(주문 동시구매 빈도순) */
	public static function getCrossSellItems($item_srl, $limit = 8)
	{
		$out = executeQueryArray('ddggshop.getCrossSellItemSrls', (object) array('item_srl' => (int)$item_srl, 'list_count' => (int)$limit));
		$rows = $out->toBool() ? ($out->data ?: array()) : array();
		$srls = array();
		foreach ($rows as $r) $srls[] = (int)$r->item_srl;
		return self::getItemsBySrls($srls);
	}

	/* ===================== 상품 문의 (Q&A) ===================== */

	public static function getQnaList($item_srl, $page = 1, $list_count = 10)
	{
		$args = new stdClass;
		$args->item_srl = (int)$item_srl;
		$args->status = 'Y';
		$args->page = max(1, (int)$page);
		$args->list_count = (int)$list_count;
		$output = executeQueryArray('ddggshop.getQnaList', $args);
		return $output->toBool() ? ($output->data ?: array()) : array();
	}

	public static function getQna($qna_srl)
	{
		$args = new stdClass;
		$args->qna_srl = (int)$qna_srl;
		$output = executeQuery('ddggshop.getQna', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/** 관리자 Q&A 목록 (전체 output, 상품명 조인) */
	public static function getQnaListAdmin($args = null)
	{
		if (!is_object($args)) $args = new stdClass;
		if (!isset($args->list_count)) $args->list_count = 20;
		if (!isset($args->page)) $args->page = 1;
		return executeQueryArray('ddggshop.getQnaListAdmin', $args);
	}

	/**
	 * 알림센터(ncenterlite)로 알림 전송. 미설치/오류 시 조용히 무시.
	 *
	 * @param int|array $to_srls 수신 회원 srl(들)
	 * @param string $message 알림 내용
	 * @param string $url 이동 URL
	 * @param int $from_srl 발신 회원 srl(0=시스템)
	 */
	public static function sendShopNotify($to_srls, $message, $url = '', $from_srl = 0)
	{
		$to_srls = array_values(array_unique(array_filter(array_map('intval', (array)$to_srls), function ($v) { return $v > 0; })));
		if (!count($to_srls)) return;
		if (!is_dir(\RX_BASEDIR . 'modules/ncenterlite')) return; // 알림센터 미설치
		try
		{
			$oNc = getController('ncenterlite');
			if (!is_object($oNc) || !method_exists($oNc, 'sendNotification')) return;
			foreach ($to_srls as $srl)
			{
				$oNc->sendNotification((int)$from_srl, $srl, $message, $url);
			}
		}
		catch (\Throwable $e) {}
	}

	/* ===================== 결제 게이트웨이 ===================== */

	/** 결제수단 코드로 게이트웨이 인스턴스 생성 (팩토리) */
	public static function getGateway($method, $config = null)
	{
		if ($config === null) $config = self::getConfig();
		$dir = __DIR__ . '/gateways/';
		require_once $dir . 'PaymentGateway.interface.php';
		require_once $dir . 'BaseGateway.abstract.php';
		switch ($method)
		{
			case 'toss':
				require_once $dir . 'TossGateway.php';
				return new TossGateway($config);
			case 'point':
				require_once $dir . 'PointGateway.php';
				return new PointGateway($config);
			case 'banktransfer':
			default:
				require_once $dir . 'BanktransferGateway.php';
				return new BanktransferGateway($config);
		}
	}

	/**
	 * 카테고리별 판매중 상품 개수(하위 카테고리 합산 포함).
	 * 반환: array(category_srl => count)
	 */
	public static function getCategoryItemCounts()
	{
		$out = executeQueryArray('ddggshop.getCategoryItemCounts', (object) array('status' => 'sale'));
		$direct = array();
		if ($out->toBool() && $out->data)
		{
			foreach ($out->data as $r) $direct[(int) $r->category_srl] = (int) $r->cnt;
		}
		// 하위 카테고리 합산 (자식 → 부모 누적)
		$cats = self::getCategoryList();
		$byParent = array();
		foreach ($cats as $c) $byParent[(int) $c->parent_srl][] = (int) $c->category_srl;
		$total = array();
		$calc = function ($srl) use (&$calc, $byParent, $direct) {
			$sum = isset($direct[$srl]) ? $direct[$srl] : 0;
			if (!empty($byParent[$srl]))
			{
				foreach ($byParent[$srl] as $ch) $sum += $calc($ch);
			}
			return $sum;
		};
		foreach ($cats as $c) $total[(int) $c->category_srl] = $calc((int) $c->category_srl);
		return $total;
	}

	/**
	 * 상품목록 가격대 필터 밴드 정규화.
	 * 반환: array of array('min'=>int,'max'=>int,'label'=>string) (라벨 없는 행 제외)
	 */
	public static function getPriceBands($config = null)
	{
		if ($config === null) $config = self::getConfig();
		$raw = (isset($config->price_bands) && is_array($config->price_bands)) ? $config->price_bands : array();
		$bands = array();
		foreach ($raw as $b)
		{
			$b = (array) $b;
			$label = trim((string) ($b['label'] ?? ''));
			if ($label === '') continue;
			$min = max(0, (int) ($b['min'] ?? 0));
			$max = max(0, (int) ($b['max'] ?? 0));
			if ($min > 0 && $max > 0 && $min > $max) { $t = $min; $min = $max; $max = $t; }
			$bands[] = array('min' => $min, 'max' => $max, 'label' => $label);
		}
		return $bands;
	}

	/** 결제수단 표시 라벨 */
	public static function payMethodLabel($method, $lang = 'ko')
	{
		$map = array(
			'toss'         => '카드/간편결제 (토스페이먼츠)',
			'banktransfer' => '무통장입금',
			'point'        => '포인트 결제 (적립금 전액)',
		);
		return self::i18nLabel('pay_' . $method, isset($map[$method]) ? $map[$method] : $method, $lang);
	}
}
