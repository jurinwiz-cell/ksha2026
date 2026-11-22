<?php
/**
 * 쇼핑몰 - 프론트 컨트롤러 (장바구니/주문/결제)
 * @author 딱따고기 (https://ddgglabs.org)
 */
class ddggshopController extends ddggshop
{
	/**
	 * menu.getModuleListInSitemap 트리거: 인스턴스가 없어도 메뉴 추가 목록에 노출.
	 */
	/** 회원 가입 시 자동발급 쿠폰 발급 */
	public function triggerDdggshopMemberJoin($obj)
	{
		$member_srl = is_object($obj) ? (int)($obj->member_srl ?? 0) : 0;
		if (!$member_srl) return new BaseObject();
		$issued = 0;
		foreach (ddggshopModel::getAutoSignupCouponSrls() as $cs)
		{
			if (ddggshopModel::issueCoupon($cs, $member_srl)) $issued++;
		}
		if ($issued > 0)
		{
			$mid = ddggshopModel::getShopMid();
			$url = $mid ? getUrl('', 'mid', $mid, 'act', 'dispDdggshopMyCoupons') : '';
			ddggshopModel::notifyMember($member_srl, '🎁 가입을 환영합니다! 쿠폰 ' . $issued . '장이 발급되었습니다.', $url, 'coupon');
		}
		return new BaseObject();
	}

	/** 스토어프론트 언어 전환 (쿠키 저장 후 원래 페이지로 리다이렉트) */
	public function procDdggshopSetLang()
	{
		$config = ddggshopModel::getConfig();
		// 주의: 'lang' 은 Rhymix 예약 Context 변수(전역 Lang 객체)이므로 파라미터명은 'setlang' 사용
		$setlang = strtolower(trim((string)Context::get('setlang')));
		$enabled = ddggshopModel::getEnabledLanguages($config);
		if ($setlang !== '' && isset($enabled[$setlang]))
		{
			@setcookie(ddggshopModel::LANG_COOKIE, $setlang, time() + 60 * 60 * 24 * 365, '/');
			$_COOKIE[ddggshopModel::LANG_COOKIE] = $setlang;
		}
		// 원래 보던 페이지로 복귀 (오픈 리다이렉트 방지: 내부 경로만 허용)
		$redirect = (string)Context::get('redirect_url');
		if ($redirect === '' || !preg_match('#^' . preg_quote(RX_BASEURL, '#') . '#', $redirect))
		{
			$referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
			$host = parse_url($referer, PHP_URL_HOST);
			$redirect = ($referer !== '' && $host === ($_SERVER['HTTP_HOST'] ?? '')) ? $referer : getUrl('', 'mid', ddggshopModel::getShopMid(), 'act', 'dispDdggshopIndex');
		}
		$this->setRedirectUrl($redirect);
	}

	public function triggerMenuModuleList(&$moduleList)
	{
		if (is_array($moduleList) && !in_array('ddggshop', $moduleList))
		{
			$moduleList[] = 'ddggshop';
		}
		return new BaseObject();
	}

	/* ===================== 장바구니 ===================== */

	/** 장바구니 담기. 입력: item_srl, qty */
	public function procDdggshopAddCart()
	{
		$item_srl = (int)Context::get('item_srl');
		$qty = max(1, (int)Context::get('qty'));
		$option_srl = (int)Context::get('option_srl');

		$item = ddggshopModel::getItem($item_srl);
		if (!$item)
		{
			return new BaseObject(-1, '상품을 찾을 수 없습니다.');
		}
		if ($item->status !== 'sale')
		{
			return new BaseObject(-1, '판매 중인 상품이 아닙니다.');
		}
		if (!ddggshopModel::canViewPrice($item))
		{
			return new BaseObject(-1, '이 상품은 로그인(또는 권한 있는 회원)만 구매할 수 있습니다.');
		}

		// 옵션 처리
		$unit_price = (int)$item->price;
		$option_label = '';
		if ($item->has_options === 'Y')
		{
			if (!$option_srl) return new BaseObject(-1, '옵션을 선택해 주세요.');
			$opt = ddggshopModel::getItemOption($option_srl);
			if (!$opt || (int)$opt->item_srl !== $item_srl || $opt->status !== 'Y')
			{
				return new BaseObject(-1, '선택한 옵션을 찾을 수 없습니다.');
			}
			if ($item->use_stock === 'Y' && (int)$opt->stock < $qty)
			{
				return new BaseObject(-1, '선택한 옵션의 재고가 부족합니다.');
			}
			$unit_price = (int)$item->price + (int)$opt->price_add;
			$option_label = $opt->option_label;
		}
		else
		{
			$option_srl = 0;
			if ($item->use_stock === 'Y' && $item->stock < $qty)
			{
				return new BaseObject(-1, '재고가 부족합니다.');
			}
		}

		$key = ddggshopModel::getCartKey();

		// 1인당 구매 한도 (buy_limit>0): 과거구매 + 장바구니 + 이번 수량 <= 한도
		if ((int)($item->buy_limit ?? 0) > 0)
		{
			$member_srl = 0;
			if (Context::get('is_logged')) { $lg = Context::get('logged_info'); $member_srl = (int)$lg->member_srl; }
			$remaining = ddggshopModel::buyLimitRemaining($item, $member_srl);
			$in_cart = ddggshopModel::getCartItemQty($key, $item_srl);
			if ($in_cart + $qty > $remaining)
			{
				$allow = max(0, $remaining - $in_cart);
				return new BaseObject(-1, '이 상품은 1인당 ' . (int)($item->buy_limit ?? 0) . '개까지 구매할 수 있습니다.' . ($allow > 0 ? (' 추가로 ' . $allow . '개 담을 수 있습니다.') : ' 더 담을 수 없습니다.'));
			}
		}

		// 동일 상품/옵션이 이미 담겨 있으면 수량 합산
		$findArgs = ddggshopModel::applyCartKey(new stdClass, $key);
		$findArgs->item_srl = $item_srl;
		$findArgs->option_srl = $option_srl;
		$exist = executeQuery('ddggshop.getCartItem', $findArgs);
		$existRow = ($exist->toBool() && $exist->data) ? $exist->data : null;

		if ($existRow)
		{
			$u = new stdClass;
			$u->cart_srl = $existRow->cart_srl;
			$u->qty = (int)$existRow->qty + $qty;
			ddggshopModel::applyCartKey($u, $key);
			$output = executeQuery('ddggshop.updateCartQty', $u);
		}
		else
		{
			$a = new stdClass;
			$a->cart_srl = getNextSequence();
			$a->member_srl = $key->member_srl ? (int)$key->member_srl : 0;
			$a->session_key = $key->session_key ? (string)$key->session_key : '';
			$a->item_srl = $item_srl;
			$a->option_srl = $option_srl;
			$a->option_label = $option_label;
			$a->qty = $qty;
			$a->price = $unit_price;
			$output = executeQuery('ddggshop.insertCart', $a);
		}
		if (!$output->toBool()) return $output;

		$detail = ddggshopModel::getCartDetail($key);
		$this->add('cart_count', $detail->count);
		$this->setMessage('장바구니에 담았습니다.');
	}

	/** 장바구니 수량 변경. 입력: cart_srl, qty */
	public function procDdggshopUpdateCart()
	{
		$cart_srl = (int)Context::get('cart_srl');
		$qty = max(1, (int)Context::get('qty'));
		$key = ddggshopModel::getCartKey();

		$u = new stdClass;
		$u->cart_srl = $cart_srl;
		$u->qty = $qty;
		ddggshopModel::applyCartKey($u, $key);
		$output = executeQuery('ddggshop.updateCartQty', $u);
		if (!$output->toBool()) return $output;

		$detail = ddggshopModel::getCartDetail($key);
		$this->add('cart_count', $detail->count);
	}

	/** 장바구니 삭제. 입력: cart_srl */
	public function procDdggshopDeleteCart()
	{
		$cart_srl = (int)Context::get('cart_srl');
		$key = ddggshopModel::getCartKey();

		$d = new stdClass;
		$d->cart_srl = $cart_srl;
		ddggshopModel::applyCartKey($d, $key);
		$output = executeQuery('ddggshop.deleteCart', $d);
		if (!$output->toBool()) return $output;

		$detail = ddggshopModel::getCartDetail($key);
		$this->add('cart_count', $detail->count);
	}

	/* ===================== 주문 ===================== */

	/** 유니크한 주문번호 생성 */
	protected function generateOrderCode()
	{
		for ($i = 0; $i < 5; $i++)
		{
			$code = date('YmdHis') . sprintf('%03d', mt_rand(0, 999));
			if (!ddggshopModel::getOrderByCode($code)) return $code;
		}
		return date('YmdHis') . sprintf('%05d', mt_rand(0, 99999));
	}

	/**
	 * 주문 생성. 장바구니 내용으로 서버에서 금액을 재계산해 주문을 만든다.
	 * 입력: orderer_name/phone/email, order_password(비회원), receiver_*, zipcode, addr1/2,
	 *       delivery_memo, pay_method, memo, shop_mid
	 */
	/** 쿠폰 코드 검증(미리보기). 입력: coupon_code, item_total */
	public function procDdggshopValidateCoupon()
	{
		if ((ddggshopModel::getConfig()->use_coupon ?? 'Y') !== 'Y') return new BaseObject(-1, '쿠폰 사용이 중지되어 있습니다.');
		$logged = Context::get('logged_info');
		$member_srl = $logged ? (int)$logged->member_srl : 0;
		$item_total = max(0, (int)Context::get('item_total'));
		$v = ddggshopModel::validateCoupon((string)Context::get('coupon_code'), $item_total, $member_srl);
		if (!$v->ok) return new BaseObject(-1, $v->message);
		$this->add('discount', $v->discount);
		$this->add('coupon_code', $v->coupon->code);
		$this->add('coupon_title', $v->coupon->title);
		$this->setMessage($v->message);
	}

	public function procDdggshopCreateOrder()
	{
		$config = ddggshopModel::getConfig();

		// 주문 항목 구성: 바로구매(buy_item_srl) 또는 장바구니
		$buy_item_srl = (int)Context::get('buy_item_srl');
		$from_cart = !$buy_item_srl;
		$key = null;
		$rows = array();
		$item_total = 0;

		if ($buy_item_srl)
		{
			$bqty = max(1, (int)Context::get('buy_qty'));
			$buy_option_srl = (int)Context::get('buy_option_srl');
			$bitem = ddggshopModel::getItem($buy_item_srl);
			if (!$bitem) return new BaseObject(-1, '상품을 찾을 수 없습니다.');
			if ($bitem->status !== 'sale') return new BaseObject(-1, '판매 중인 상품이 아닙니다.');
			if (!ddggshopModel::canViewPrice($bitem)) return new BaseObject(-1, '이 상품은 로그인(또는 권한 있는 회원)만 구매할 수 있습니다.');

			$bprice = (int)$bitem->price;
			$boption_name = '';
			if ($bitem->has_options === 'Y')
			{
				if (!$buy_option_srl) return new BaseObject(-1, '옵션을 선택해 주세요.');
				$bopt = ddggshopModel::getItemOption($buy_option_srl);
				if (!$bopt || (int)$bopt->item_srl !== $buy_item_srl || $bopt->status !== 'Y') return new BaseObject(-1, '선택한 옵션을 찾을 수 없습니다.');
				if ($bitem->use_stock === 'Y' && (int)$bopt->stock < $bqty) return new BaseObject(-1, '선택한 옵션의 재고가 부족합니다.');
				$bprice += (int)$bopt->price_add;
				$boption_name = $bopt->option_label;
			}
			else
			{
				$buy_option_srl = 0;
				if ($bitem->use_stock === 'Y' && $bitem->stock < $bqty) return new BaseObject(-1, '재고가 부족합니다.');
			}
			$bprice = ddggshopModel::discountedUnitPrice($bprice, ddggshopModel::currentMemberSrl(), $config);
			$sub = $bprice * $bqty;
			$rows[] = (object) array('item_srl' => $bitem->item_srl, 'option_srl' => $buy_option_srl, 'item_name' => $bitem->item_name, 'option_name' => $boption_name, 'thumb' => $bitem->thumb, 'price' => $bprice, 'qty' => $bqty, 'subtotal' => $sub, 'ship_fee_type' => (isset($bitem->ship_fee_type) && $bitem->ship_fee_type) ? $bitem->ship_fee_type : 'default', 'ship_fee' => (int)(isset($bitem->ship_fee) ? $bitem->ship_fee : 0), 'require_address' => (isset($bitem->require_address) && $bitem->require_address === 'N') ? 'N' : 'Y');
			$item_total = $sub;
		}
		else
		{
			$key = ddggshopModel::getCartKey();
			$detail = ddggshopModel::getCartDetail($key);
			if (!count($detail->rows)) return new BaseObject(-1, '장바구니가 비어 있습니다.');
			if ($detail->has_unavailable) return new BaseObject(-1, '품절되었거나 구매할 수 없는 상품이 있습니다. 장바구니를 확인해 주세요.');
			// 판매 공개 권한 검증
			foreach ($detail->rows as $cr)
			{
				$cit = ddggshopModel::getItem($cr->item_srl);
				if ($cit && !ddggshopModel::canViewPrice($cit))
				{
					return new BaseObject(-1, '구매 권한이 없는 상품이 포함되어 있습니다. 로그인 후 다시 시도해 주세요.');
				}
			}
			$rows = $detail->rows;
			$item_total = $detail->item_total;
		}

		// 이 주문이 배송 주소를 필요로 하는가 (서버 재판정 — 클라이언트 신뢰 안 함)
		$needs_address = ddggshopModel::orderNeedsAddress($rows, $config);

		$orderer_name = trim((string)Context::get('orderer_name'));
		$orderer_phone = trim((string)Context::get('orderer_phone'));
		$receiver_name = trim((string)Context::get('receiver_name'));
		$receiver_phone = trim((string)Context::get('receiver_phone'));
		$addr1 = trim((string)Context::get('addr1'));
		$pay_method = (string)Context::get('pay_method');

		if ($orderer_name === '' || $orderer_phone === '')
		{
			return new BaseObject(-1, '주문자명·연락처를 입력해 주세요.');
		}
		if ($needs_address)
		{
			if ($receiver_name === '' || $receiver_phone === '' || $addr1 === '')
			{
				return new BaseObject(-1, '받는분·받는분 연락처·주소를 입력해 주세요.');
			}
		}
		else
		{
			// 주소 미수집 주문: 받는분 정보를 주문자로 대체(빈 값 방지), 주소는 비움
			if ($receiver_name === '') $receiver_name = $orderer_name;
			if ($receiver_phone === '') $receiver_phone = $orderer_phone;
		}
		if (!in_array($pay_method, $config->pay_methods, true))
		{
			return new BaseObject(-1, '결제수단을 선택해 주세요.');
		}

		$is_logged = Context::get('is_logged');
		$member_srl = 0;
		if ($is_logged)
		{
			$logged = Context::get('logged_info');
			$member_srl = (int)$logged->member_srl;
		}

		// 1인당 구매 한도 재검증 (buy_limit>0) — 상품별 주문수량 합산 후 과거구매+주문수량 <= 한도
		$order_qty_by_item = array();
		foreach ($rows as $r) { $isrl = (int)$r->item_srl; $order_qty_by_item[$isrl] = (isset($order_qty_by_item[$isrl]) ? $order_qty_by_item[$isrl] : 0) + (int)$r->qty; }
		foreach ($order_qty_by_item as $isrl => $oqty)
		{
			$lim_item = ddggshopModel::getItem($isrl);
			if (!$lim_item || (int)($lim_item->buy_limit ?? 0) <= 0) continue;
			$remaining = ddggshopModel::buyLimitRemaining($lim_item, $member_srl);
			if ($oqty > $remaining)
			{
				return new BaseObject(-1, '[' . $lim_item->item_name . '] 은(는) 1인당 ' . (int)($lim_item->buy_limit ?? 0) . '개까지 구매할 수 있습니다.' . ($remaining > 0 ? (' 최대 ' . $remaining . '개까지 주문 가능합니다.') : ' 이미 구매 한도에 도달했습니다.'));
			}
		}

		// 비회원은 주문조회용 비밀번호 필요
		$order_password = '';
		if (!$member_srl)
		{
			$raw_pw = trim((string)Context::get('order_password'));
			if (strlen($raw_pw) < 4)
			{
				return new BaseObject(-1, '비회원 주문은 4자 이상의 주문조회 비밀번호가 필요합니다.');
			}
			$order_password = getModel('member')->hashPassword($raw_pw);
		}

		$ship_extra = 0;
		if ($needs_address)
		{
			$ship_extra = ddggshopModel::matchShipFee(Context::get('zipcode'));
			$region_extra = ddggshopModel::regionExtraFee(Context::get('ship_region_srl'));
			if ($region_extra > $ship_extra) $ship_extra = $region_extra; // 우편번호 자동가산과 중복 없이 큰 값 적용
		}
		$totals = ddggshopModel::calcOrderTotals($item_total, $config, $rows, $ship_extra, $needs_address);

		// 쿠폰 적용 (서버 재검증) — 쿠폰 시스템 OFF면 무시
		$coupon_code = trim((string)Context::get('coupon_code'));
		if (($config->use_coupon ?? 'Y') !== 'Y') $coupon_code = '';
		$coupon = null; $discount = 0;
		if ($coupon_code !== '')
		{
			$cv = ddggshopModel::validateCoupon($coupon_code, $item_total, $member_srl);
			if (!$cv->ok) return new BaseObject(-1, '쿠폰: ' . $cv->message);
			$coupon = $cv->coupon; $discount = (int)$cv->discount;
		}
		$final_pay = max(0, (int)$totals->payment_price - $discount);

		// 적립금 사용 (설정 ON + 회원 + 보유 잔액 검증)
		$used_point = max(0, (int)Context::get('used_point'));
		if ($used_point > 0)
		{
			if (($config->use_point ?? 'N') !== 'Y' || !$member_srl)
			{
				$used_point = 0;
			}
			else
			{
				$bal = class_exists('PointModel') ? (int)abs(PointModel::getPoint($member_srl)) : 0;
				if ($used_point > $bal) return new BaseObject(-1, '보유 적립금이 부족합니다.');
				if ($used_point > $final_pay) $used_point = $final_pay;
			}
		}
		$final_pay = max(0, $final_pay - $used_point);

		// 포인트 전액결제: 보유 적립금만으로 결제(결제금액 0원). 부족하면 불가.
		$is_point_pay = ($pay_method === 'point');
		if ($is_point_pay)
		{
			if (($config->use_point ?? 'N') !== 'Y' || ($config->use_point_full ?? 'N') !== 'Y')
			{
				return new BaseObject(-1, '포인트 결제를 사용할 수 없습니다.');
			}
			if (!$member_srl)
			{
				return new BaseObject(-1, '포인트 결제는 로그인 후 이용할 수 있습니다.');
			}
			if ($final_pay > 0)
			{
				return new BaseObject(-1, '보유 적립금이 부족하여 전액 결제할 수 없습니다. (부족 ' . number_format($final_pay) . '원)');
			}
			if ($used_point <= 0)
			{
				return new BaseObject(-1, '사용할 적립금이 없습니다.');
			}
		}

		$gateway = ddggshopModel::getGateway($pay_method, $config);
		// 토스 등 외부 결제는 결제 완료 전까지 'ready'(미결제), 무통장은 'pending'(입금대기)
		// 포인트 전액결제는 외부 PG 없이 즉시 확정 → 'ready'로 생성 후 finalize에서 paid 전이
		if ($is_point_pay)
		{
			$initial_status = 'ready';
		}
		else
		{
			$initial_status = $gateway->requiresClientPayment() ? 'ready' : 'pending';
		}

		$order_srl = getNextSequence();
		$order_code = $this->generateOrderCode();

		$oDB = DB::getInstance();
		$soldout_events = array();
		$oDB->begin();
		try
		{
			$o = new stdClass;
			$o->order_srl = $order_srl;
			$o->order_code = $order_code;
			$o->member_srl = $member_srl;
			$o->orderer_name = $orderer_name;
			$o->orderer_phone = trim((string)Context::get('orderer_phone'));
			$o->orderer_email = trim((string)Context::get('orderer_email'));
			$o->order_password = $order_password;
			$o->total_price = $totals->item_total;
			$o->delivery_fee = $totals->delivery_fee;
			$o->discount_price = $discount;
			$o->coupon_code = $coupon_code;
			$o->used_point = $used_point;
			$o->payment_price = $final_pay;
			$o->pay_method = $pay_method;
			$o->status = $initial_status;
			$o->memo = trim((string)Context::get('memo'));
			// 주문번호 UNIQUE 충돌(동시 생성) 시 코드 재생성 후 재시도 — DB 제약이 최종 안전망
			$out = null;
			for ($try = 0; $try < 5; $try++)
			{
				$out = executeQuery('ddggshop.insertOrder', $o);
				if ($out->toBool()) break;
				$o->order_code = $this->generateOrderCode();
			}
			if (!$out || !$out->toBool()) throw new Exception('order_code');
			$order_code = $o->order_code;

			// 쿠폰 사용 기록 (+ 회원 보유쿠폰 사용 처리)
			if ($coupon)
			{
				ddggshopModel::recordCouponUse($coupon, $member_srl, $order_srl, $discount);
				if ($member_srl) ddggshopModel::markCouponUsedByMember($coupon->coupon_srl, $member_srl, $order_srl);
			}

			// 주문 상품 스냅샷 (적립률: 회원 등급 적립률 반영)
			$earn_rate = ddggshopModel::memberPointRate($member_srl, $config);
			foreach ($rows as $row)
			{
				$pt = (int)floor($row->subtotal * $earn_rate / 100);
				$oi = new stdClass;
				$oi->order_item_srl = getNextSequence();
				$oi->order_srl = $order_srl;
				$oi->item_srl = $row->item_srl;
				$oi->option_srl = isset($row->option_srl) ? (int)$row->option_srl : 0;
				$oi->item_name = $row->item_name;
				$oi->item_code = isset($row->item_code) ? (string)$row->item_code : '';
				if ($oi->item_code === '' && (int)$row->item_srl)
				{
					$snap_item = ddggshopModel::getItem((int)$row->item_srl);
					if ($snap_item) $oi->item_code = (string)$snap_item->item_code;
				}
				$oi->option_name = isset($row->option_name) ? $row->option_name : (isset($row->option_label) ? $row->option_label : '');
				$oi->thumb = $row->thumb;
				$oi->price = $row->price;
				$oi->qty = $row->qty;
				$oi->subtotal = $row->subtotal;
				$oi->point_amount = $pt;
				$oiOut = executeQuery('ddggshop.insertOrderItem', $oi);
				if (!$oiOut->toBool()) throw new Exception($oiOut->getMessage());
			}

			// 배송지 (주소 미수집 주문은 주소 필드가 비워진 채 저장됨)
			$ad = new stdClass;
			$ad->address_srl = getNextSequence();
			$ad->order_srl = $order_srl;
			$ad->receiver_name = $receiver_name;
			$ad->receiver_phone = $receiver_phone;
			$ad->zipcode = $needs_address ? trim((string)Context::get('zipcode')) : '';
			$ad->addr1 = $needs_address ? $addr1 : '';
			$ad->addr2 = $needs_address ? trim((string)Context::get('addr2')) : '';
			$ad->delivery_memo = $needs_address ? trim((string)Context::get('delivery_memo')) : '';
			$adOut = executeQuery('ddggshop.insertOrderAddress', $ad);
			if (!$adOut->toBool()) throw new Exception($adOut->getMessage());

			// 무통장입금: 즉시 재고 차감 + (장바구니 주문이면) 장바구니 비움
			// 토스(ready)·포인트 전액결제(ready→finalize)는 결제 확정 시 차감
			if ($initial_status === 'pending')
			{
				$soldout_events = $this->applyStockForOrder($order_srl);
				if ($from_cart) $this->clearOrderCart($member_srl, $key);
			}

			$oDB->commit();
		}
		catch (Exception $e)
		{
			$oDB->rollback();
			if (strpos($e->getMessage(), 'STOCK:') === 0)
			{
				return new BaseObject(-1, '재고가 부족한 상품이 있습니다: ' . substr($e->getMessage(), 6));
			}
			return new BaseObject(-1, '주문 생성 중 오류가 발생했습니다.');
		}

		// 적립금 차감: 무통장(입금대기)은 주문 시 즉시, 토스는 결제 승인 시 차감
		if ($used_point > 0 && $member_srl && $initial_status === 'pending')
		{
			getController('point')->setPoint($member_srl, $used_point, 'minus', 'ddggshop_use');
		}

		// 품절 관리자 알림 (재고 차감이 일어난 무통장 주문)
		$this->notifySoldOut($soldout_events);

		// 알림: 무통장(입금대기)만 주문 시점에 발송. 토스(ready)는 결제 승인 후 발송.
		if ($initial_status === 'pending')
		{
			$admin_url = ddggshopModel::consoleOrderUrl($order_srl);
			foreach (ddggshopModel::getAdminMemberSrls() as $asrl)
			{
				ddggshopModel::notifyMember($asrl, '🛒 신규 주문 ' . $order_code . ' (입금대기 ' . number_format($final_pay) . '원)', $admin_url, 'admin_neworder');
			}
			if ($member_srl)
			{
				ddggshopModel::notifyMember($member_srl, '주문이 접수되었습니다. 입금 확인 후 처리됩니다. (' . $order_code . ')', ddggshopModel::memberOrderUrl($order_code), 'order_received');
			}
		}

		// 회원 배송지 주소록 저장 (요청 시)
		if ($member_srl && Context::get('save_address') === 'Y')
		{
			$this->saveOrderAddressToBook($member_srl, $ad);
		}

		// 결제 단계에서 장바구니 비움 여부 판단용 메타
		if (!isset($_SESSION['ddggshop_order_meta']) || !is_array($_SESSION['ddggshop_order_meta']))
		{
			$_SESSION['ddggshop_order_meta'] = array();
		}
		$_SESSION['ddggshop_order_meta'][$order_srl] = array('from_cart' => $from_cart);

		ddggshopModel::logOrder($order_srl, 'order_created', '', $initial_status, '결제수단: ' . ddggshopModel::payMethodLabel($pay_method));

		// 주문조회 접근 권한 부여 (이 세션에서 결과 확인 가능)
		$this->grantOrderAccess($order_srl);

		$order = ddggshopModel::getOrder($order_srl);

		// 포인트 전액결제: 외부 PG 없이 즉시 결제완료 확정(재고차감·적립금차감·적립·알림·장바구니 비움)
		if ($is_point_pay)
		{
			$fin = $this->finalizeApprovedOrder($order, '');
			if (!$fin->toBool())
			{
				return new BaseObject(-1, '포인트 결제 처리 중 오류가 발생했습니다.');
			}
			$order = ddggshopModel::getOrder($order_srl);
		}

		$shop_mid = (string)Context::get('shop_mid') ?: $this->module_info->mid;

		$this->add('order_code', $order_code);
		$this->add('pay_method', $pay_method);
		$this->add('requires_client', $gateway->requiresClientPayment() ? 1 : 0);
		$this->add('result_url', getNotEncodedFullUrl('', 'mid', $shop_mid, 'act', 'dispDdggshopOrderResult', 'order_code', $order_code));

		if ($gateway->requiresClientPayment())
		{
			// 토스: 클라이언트가 결제창을 띄우는 데 필요한 데이터
			$first = $rows[0]->item_name;
			$order_name = (count($rows) > 1) ? sprintf('%s 외 %d건', $first, count($rows) - 1) : $first;
			$req = $gateway->buildRequest($order);
			$this->add('client_key', $req['clientKey']);
			$this->add('toss_sdk', $gateway->getClientScript());
			$this->add('order_id', $order_code);
			$this->add('order_name', $order_name);
			$this->add('amount', (int)$order->payment_price);
			$this->add('customer_name', $orderer_name);
			$this->add('success_url', getNotEncodedFullUrl('', 'mid', $shop_mid, 'act', 'dispDdggshopOrderResult', 'order_code', $order_code));
			$this->add('fail_url', getNotEncodedFullUrl('', 'mid', $shop_mid, 'act', 'dispDdggshopOrderResult', 'order_code', $order_code, 'pay_fail', '1'));
		}
		$this->setMessage('주문이 생성되었습니다.');
	}

	/**
	 * 토스 결제 승인 처리 (성공 리다이렉트에서 뷰가 호출).
	 * 금액 위변조 검증 → PG 승인 → 재고차감 + 포인트적립 + 장바구니비움.
	 */
	public function approveOrderPayment($order, $paymentKey, $amount)
	{
		if (!$order) return new BaseObject(-1, '주문을 찾을 수 없습니다.');
		if ($order->status === 'paid') return new BaseObject(0, '이미 결제된 주문입니다.');
		if (!in_array($order->status, array('ready', 'pending'), true)) return new BaseObject(-1, '결제할 수 없는 주문 상태입니다.');

		if ((int)$amount !== (int)$order->payment_price)
		{
			$this->logPay($order, 'approve', array('reason' => 'amount mismatch', 'got' => $amount), '', 'F', '');
			return new BaseObject(-1, '결제 금액이 일치하지 않습니다.');
		}

		$config = ddggshopModel::getConfig();
		$gateway = ddggshopModel::getGateway($order->pay_method, $config);
		$res = $gateway->approve($order, array(
			'paymentKey' => $paymentKey,
			'orderId'    => $order->order_code,
			'amount'     => (int)$amount,
		));
		$this->logPay($order, 'approve', array('paymentKey' => $paymentKey, 'amount' => $amount), $res->raw, $res->success ? 'S' : 'F', $res->tid);

		if (!$res->success)
		{
			return new BaseObject(-1, $res->message ?: '결제 승인에 실패했습니다.');
		}

		return $this->finalizeApprovedOrder($order, $res->tid);
	}

	/**
	 * 결제 확정(이미 PG 승인/완료가 확인된 주문)을 마무리: 가드 전이(ready/pending→paid) +
	 * 재고차감 + 포인트적립/적립금차감 + 알림 + 장바구니 비움. 멱등(다른 경로가 먼저 확정하면 무처리).
	 * 리다이렉트 승인과 웹훅이 공유한다.
	 */
	protected function finalizeApprovedOrder($order, $tid)
	{
		$oDB = DB::getInstance();
		$soldout_events = array();
		$oDB->begin();
		try
		{
			// 가드 업데이트: ready/pending → paid 로만 전이(중복 승인/콜백/웹훅 멱등 처리)
			$u = new stdClass;
			$u->order_srl = $order->order_srl;
			$u->status = 'paid';
			$u->pg_tid = $tid;
			$u->paid_date = date('YmdHis');
			$u->from_status = array('ready', 'pending');
			$o1 = executeQuery('ddggshop.updateOrderPaymentGuarded', $u);
			if (!$o1->toBool()) throw new Exception();
			if ($oDB->getAffectedRows() < 1)
			{
				// 다른 콜백/웹훅이 먼저 결제완료 처리함 → 부작용 없이 종료
				$oDB->rollback();
				return new BaseObject(0, '이미 결제된 주문입니다.');
			}

			// 결제 완료 후 재고 차감(이미 입금 완료이므로 guard 없이 차감 — 환불 위험 회피)
			$soldout_events = $this->applyStockForOrder($order->order_srl, false);
			$this->awardOrderPoints($order);
			// 적립금 차감(사용)도 트랜잭션 내부에서 처리 → 부분 실패 방지
			if ($order->member_srl && (int)$order->used_point > 0)
			{
				getController('point')->setPoint((int)$order->member_srl, (int)$order->used_point, 'minus', 'ddggshop_use');
			}
			$oDB->commit();
		}
		catch (Exception $e)
		{
			$oDB->rollback();
			return new BaseObject(-1, '주문 처리 중 오류가 발생했습니다.');
		}

		ddggshopModel::logOrder((int)$order->order_srl, 'paid', '', 'paid', '결제완료');

		// 구매 후 회원 그룹 변경 (콘텐츠 상품 등) — 커밋 이후 적용
		ddggshopModel::applyItemGroupGrants($order);

		// 품절 관리자 알림
		$this->notifySoldOut($soldout_events);

		// 알림: 결제완료 (회원) + 신규 주문 (관리자) — 토스는 결제 승인 후에만 발송
		if ($order->member_srl)
		{
			ddggshopModel::notifyMember((int)$order->member_srl, '💳 결제가 완료되었습니다. 주문 ' . $order->order_code, ddggshopModel::memberOrderUrl($order->order_code), 'paid');
		}
		$admin_url = ddggshopModel::consoleOrderUrl($order->order_srl);
		foreach (ddggshopModel::getAdminMemberSrls() as $asrl)
		{
			ddggshopModel::notifyMember($asrl, '🛒 신규 주문 ' . $order->order_code . ' (결제완료 ' . number_format($order->payment_price) . '원)', $admin_url, 'admin_neworder');
		}

		// 장바구니 주문이었으면 비움 (바로구매는 장바구니 보존)
		$meta = isset($_SESSION['ddggshop_order_meta'][$order->order_srl]) ? $_SESSION['ddggshop_order_meta'][$order->order_srl] : null;
		$from_cart = $meta ? !empty($meta['from_cart']) : true;
		if ($from_cart)
		{
			$key = ddggshopModel::getCartKey();
			$this->clearOrderCart((int)$order->member_srl, $key);
		}
		unset($_SESSION['ddggshop_order_meta'][$order->order_srl]);

		return new BaseObject(0, 'success');
	}

	/**
	 * 토스페이먼츠 웹훅 수신 (check_csrf=false).
	 * 리다이렉트 누락(고객이 결제 직후 창 닫음 등)에 대비한 결제 확정 이중화.
	 * 웹훅 본문을 신뢰하지 않고 토스 API로 결제를 재조회(query)해 검증 후 확정한다. 항상 200 OK 응답.
	 */
	public function procDdggshopPaymentWebhook()
	{
		$raw = file_get_contents('php://input');
		$data = @json_decode($raw);
		$payload = (is_object($data) && isset($data->data) && is_object($data->data)) ? $data->data : $data;

		$order_code = '';
		$paymentKey = '';
		$status = '';
		if (is_object($payload))
		{
			$order_code = isset($payload->orderId) ? trim((string)$payload->orderId) : '';
			$paymentKey = isset($payload->paymentKey) ? trim((string)$payload->paymentKey) : '';
			$status = isset($payload->status) ? (string)$payload->status : '';
		}

		if ($order_code !== '' && $paymentKey !== '')
		{
			$order = ddggshopModel::getOrderByCode($order_code);
			if ($order && $order->pay_method === 'toss' && in_array($order->status, array('ready', 'pending'), true))
			{
				$config = ddggshopModel::getConfig();
				$gw = ddggshopModel::getGateway('toss', $config);
				$q = $gw->query($paymentKey);
				ddggshopModel::logPayment($order, 'webhook', array('event' => $status, 'paymentKey' => $paymentKey), $q->raw, $q->success ? 'S' : 'F', $paymentKey);
				// 토스 재조회 결과가 DONE + 금액 일치일 때만 확정
				if ($q->success && $q->message === 'DONE' && (int)$q->amount === (int)$order->payment_price)
				{
					$this->finalizeApprovedOrder($order, $paymentKey);
				}
			}
		}

		// Toss는 2xx 응답을 받지 못하면 재시도하므로 항상 200 OK
		while (ob_get_level()) { @ob_end_clean(); }
		header('Content-Type: text/plain; charset=UTF-8');
		echo 'OK';
		exit;
	}

	/** 미결제(ready) 주문 완전 삭제 — 토스 결제 취소/실패 정리 */
	public function deleteReadyOrder($order)
	{
		if (!$order || $order->status !== 'ready') return;
		executeQuery('ddggshop.deleteOrderItemsByOrder', (object) array('order_srl' => $order->order_srl));
		executeQuery('ddggshop.deleteOrderAddressByOrder', (object) array('order_srl' => $order->order_srl));
		executeQuery('ddggshop.deleteOrder', (object) array('order_srl' => $order->order_srl));
		unset($_SESSION['ddggshop_order_meta'][$order->order_srl]);
	}

	/** 결제창에서 취소(창 닫음 등) 시 미결제 주문 정리 */
	public function procDdggshopCancelOrder()
	{
		$order_code = trim((string)Context::get('order_code'));
		$order = ddggshopModel::getOrderByCode($order_code);
		if ($order && $order->status === 'ready' && isset($_SESSION['ddggshop_order_access'][$order->order_srl]))
		{
			$this->deleteReadyOrder($order);
		}
		$this->setMessage('취소되었습니다.');
	}

	/** 주문 배송지를 회원 주소록에 저장 (중복 제외) */
	protected function saveOrderAddressToBook($member_srl, $ad)
	{
		$list = ddggshopModel::getAddressBookList($member_srl);
		foreach ($list as $a)
		{
			if ($a->addr1 === $ad->addr1 && $a->addr2 === $ad->addr2 && $a->receiver_name === $ad->receiver_name) return;
		}
		$args = new stdClass;
		$args->address_book_srl = getNextSequence();
		$args->member_srl = (int)$member_srl;
		$args->label = '';
		$args->receiver_name = $ad->receiver_name;
		$args->receiver_phone = $ad->receiver_phone;
		$args->zipcode = $ad->zipcode;
		$args->addr1 = $ad->addr1;
		$args->addr2 = $ad->addr2;
		$args->is_default = count($list) ? 'N' : 'Y';
		executeQuery('ddggshop.insertAddressBook', $args);
	}

	/* ===================== 상품 후기 ===================== */

	public function procDdggshopWriteReview()
	{
		if (!Context::get('is_logged')) return new BaseObject(-1, '로그인이 필요합니다.');
		$logged = Context::get('logged_info');
		$item_srl = (int)Context::get('item_srl');
		$item = ddggshopModel::getItem($item_srl);
		if (!$item) return new BaseObject(-1, '상품을 찾을 수 없습니다.');
		if (!ddggshopModel::canWriteReview($item_srl)) return new BaseObject(-1, '후기를 작성할 권한이 없습니다.');

		$member_srl = (int)$logged->member_srl;
		// 1인 1리뷰(상품당) — 중복 작성·포인트 파밍 방지
		if (ddggshopModel::getMemberItemReviewCount($item_srl, $member_srl) > 0)
		{
			return new BaseObject(-1, '이미 이 상품에 후기를 작성하셨습니다.');
		}

		$rating = (int)Context::get('rating');
		if ($rating < 1) $rating = 5;
		if ($rating > 5) $rating = 5;
		$content = trim((string)Context::get('content'));
		if ($content === '') return new BaseObject(-1, '후기 내용을 입력해 주세요.');

		// 포토리뷰 이미지 업로드(최대 5장)
		$review_srl = getNextSequence();
		$images = $this->saveReviewImages($review_srl);
		$has_photo = count($images) > 0;

		// 리뷰 적립금 지급(설정 ON) — 사진 여부에 따라 차등
		$config = ddggshopModel::getConfig();
		$point_given = 'N';
		$award = 0;
		if (($config->use_review_point ?? 'N') === 'Y' && $member_srl)
		{
			$award = $has_photo ? (int)$config->photo_review_point : (int)$config->review_point;
			if ($award > 0) $point_given = 'Y';
		}

		$args = new stdClass;
		$args->review_srl = $review_srl;
		$args->item_srl = $item_srl;
		$args->member_srl = $member_srl;
		$args->member_name = $logged->nick_name;
		$args->rating = $rating;
		$args->content = $content;
		$args->images = $images ? json_encode(array_values($images)) : '';
		$args->point_given = $point_given;
		$args->status = 'Y';
		$output = executeQuery('ddggshop.insertReview', $args);
		if (!$output->toBool()) return $output;

		if ($point_given === 'Y')
		{
			getController('point')->setPoint($member_srl, $award, 'plus', 'ddggshop_review');
			$this->setMessage('후기가 등록되었습니다. 적립금 ' . number_format($award) . '원이 지급되었습니다.');
		}
		else
		{
			$this->setMessage($has_photo ? '포토 후기가 등록되었습니다.' : '후기가 등록되었습니다.');
		}
		$this->add('review_srl', $review_srl);
	}

	/** 리뷰 첨부 이미지 저장(input name="review_images[]", 최대 5장). 반환: URL 배열 */
	protected function saveReviewImages($review_srl)
	{
		$urls = array();
		if (empty($_FILES['review_images']) || empty($_FILES['review_images']['name'])) return $urls;
		$files = $_FILES['review_images'];
		$allow = array('jpg', 'jpeg', 'png', 'gif', 'webp');
		$dir = RX_BASEDIR . 'files/attach/ddggshop/review';
		FileHandler::makeDir($dir);
		$names = is_array($files['name']) ? $files['name'] : array($files['name']);
		$count = 0;
		foreach ($names as $i => $name)
		{
			if ($count >= 5) break;
			$tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
			$err = is_array($files['error']) ? $files['error'][$i] : $files['error'];
			if ($name === '' || $tmp === '' || $err) continue;
			$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (!in_array($ext, $allow, true)) continue;
			$filename = $review_srl . '_' . $count . '_' . substr(md5($name . microtime()), 0, 8) . '.' . $ext;
			$target = $dir . '/' . $filename;
			if (Rhymix\Framework\Storage::moveUploadedFile($tmp, $target, 'move'))
			{
				$urls[] = RX_BASEURL . 'files/attach/ddggshop/review/' . $filename;
				$count++;
			}
		}
		return $urls;
	}

	/** 위시리스트(찜) 토글 — 회원 전용, ajax. 입력: item_srl */
	public function procDdggshopToggleWishlist()
	{
		if (!Context::get('is_logged')) return new BaseObject(-1, '로그인이 필요합니다.');
		$logged = Context::get('logged_info');
		$item_srl = (int)Context::get('item_srl');
		$item = ddggshopModel::getItem($item_srl);
		if (!$item) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$r = ddggshopModel::toggleWishlist($item_srl, (int)$logged->member_srl);
		$this->add('wished', $r['wished'] ? 1 : 0);
		$this->setMessage($r['wished'] ? '찜 목록에 추가했습니다.' : '찜을 해제했습니다.');
	}

	public function procDdggshopDeleteReview()
	{
		$review_srl = (int)Context::get('review_srl');
		$review = ddggshopModel::getReview($review_srl);
		if (!$review) return new BaseObject(-1, '후기를 찾을 수 없습니다.');

		$is_owner = false;
		$is_admin = false;
		if (Context::get('is_logged'))
		{
			$logged = Context::get('logged_info');
			$is_owner = ((int)$logged->member_srl === (int)$review->member_srl);
			$is_admin = (isset($logged->is_admin) && $logged->is_admin === 'Y');
		}
		if (!$is_owner && !$is_admin) return new BaseObject(-1, '권한이 없습니다.');

		executeQuery('ddggshop.deleteReview', (object) array('review_srl' => $review_srl));
		$this->setMessage('삭제되었습니다.');
	}

	/* ===================== 상품 문의 (Q&A) ===================== */

	public function procDdggshopWriteQna()
	{
		if (!Context::get('is_logged')) return new BaseObject(-1, '로그인이 필요합니다.');
		$logged = Context::get('logged_info');
		$item_srl = (int)Context::get('item_srl');
		$item = ddggshopModel::getItem($item_srl);
		if (!$item) return new BaseObject(-1, '상품을 찾을 수 없습니다.');
		if (!ddggshopModel::canWriteQna()) return new BaseObject(-1, '문의를 작성할 권한이 없습니다.');
		$content = trim((string)Context::get('content'));
		if ($content === '') return new BaseObject(-1, '문의 내용을 입력해 주세요.');

		$args = new stdClass;
		$args->qna_srl = getNextSequence();
		$args->item_srl = $item_srl;
		$args->member_srl = (int)$logged->member_srl;
		$args->member_name = $logged->nick_name;
		$args->content = $content;
		$args->is_secret = (Context::get('is_secret') === 'Y') ? 'Y' : 'N';
		$args->status = 'Y';
		$output = executeQuery('ddggshop.insertQna', $args);
		if (!$output->toBool()) return $output;

		// 관리자 알림
		$cfg = ddggshopModel::getConfig();
		if (($cfg->notify_admin_qna ?? 'Y') === 'Y')
		{
			$url = ddggshopModel::productUrl($item_srl);
			$msg = '[' . $cfg->shop_name . '] 새 상품문의: ' . $item->item_name;
			ddggshopModel::sendShopNotify(ddggshopModel::getAdminMemberSrls(), $msg, $url, (int)$logged->member_srl);
		}

		$this->setMessage('문의가 등록되었습니다.');
	}

	public function procDdggshopDeleteQna()
	{
		$qna_srl = (int)Context::get('qna_srl');
		$qna = ddggshopModel::getQna($qna_srl);
		if (!$qna) return new BaseObject(-1, '문의를 찾을 수 없습니다.');
		$is_owner = false;
		$is_admin = false;
		if (Context::get('is_logged'))
		{
			$lg = Context::get('logged_info');
			$is_owner = ((int)$lg->member_srl === (int)$qna->member_srl);
			$is_admin = (isset($lg->is_admin) && $lg->is_admin === 'Y');
		}
		if (!$is_owner && !$is_admin) return new BaseObject(-1, '권한이 없습니다.');
		executeQuery('ddggshop.deleteQna', (object) array('qna_srl' => $qna_srl));
		$this->setMessage('삭제되었습니다.');
	}

	/* ===================== 주문 취소 (고객) ===================== */

	/**
	 * 고객 주문 취소: 배송준비(preparing) 전(pending/paid)까지 가능.
	 * 토스 결제완료건은 PG 취소(환불), 재고 복원, 적립 포인트 회수.
	 */
	public function procDdggshopCancelMyOrder()
	{
		$order_code = trim((string)Context::get('order_code'));
		$order = ddggshopModel::getOrderByCode($order_code);
		if (!$order) return new BaseObject(-1, '주문을 찾을 수 없습니다.');

		$allowed = false;
		if (Context::get('is_logged') && $order->member_srl)
		{
			$lg = Context::get('logged_info');
			if ((int)$lg->member_srl === (int)$order->member_srl) $allowed = true;
		}
		if (isset($_SESSION['ddggshop_order_access'][$order->order_srl])) $allowed = true;
		if (!$allowed) return new BaseObject(-1, '권한이 없습니다.');

		if (!in_array($order->status, array('pending', 'paid'), true))
		{
			return new BaseObject(-1, '배송 준비 단계 이후에는 주문을 취소할 수 없습니다.');
		}

		// 재고복원·포인트회수/환불·쿠폰복원·PG취소를 모델에서 일괄 처리(정합성)
		$out = ddggshopModel::cancelOrder($order, 'cancelled', '고객 주문취소');
		if (!$out->toBool()) return $out;

		if ($order->member_srl)
		{
			ddggshopModel::notifyMember((int)$order->member_srl, '주문 ' . $order->order_code . '이(가) 취소되었습니다.', ddggshopModel::memberOrderUrl($order->order_code), 'cancelled');
		}
		// 관리자 알림 (주문 생성 시와 동일하게) — 고객 취소 시 관리자에게도 통지
		$admin_url = ddggshopModel::consoleOrderUrl((int)$order->order_srl);
		foreach (ddggshopModel::getAdminMemberSrls() as $asrl)
		{
			ddggshopModel::notifyMember($asrl, '❌ 주문 취소 ' . $order->order_code . ' (고객 취소)', $admin_url, 'admin_cancel');
		}
		$this->setMessage('주문이 취소되었습니다.');
	}

	/** 고객 교환/반품 신청 (배송완료 주문). 입력: order_code, claim_type, reason, items */
	public function procDdggshopRequestClaim()
	{
		if (!Context::get('is_logged')) return new BaseObject(-1, '로그인이 필요합니다.');
		$logged = Context::get('logged_info');
		$order = ddggshopModel::getOrderByCode(trim((string)Context::get('order_code')));
		if (!$order) return new BaseObject(-1, '주문을 찾을 수 없습니다.');
		if ((int)$order->member_srl !== (int)$logged->member_srl) return new BaseObject(-1, '권한이 없습니다.');
		if ($order->status !== 'delivered') return new BaseObject(-1, '배송완료된 주문만 교환/반품을 신청할 수 있습니다.');
		if (ddggshopModel::getOpenClaim((int)$order->order_srl)) return new BaseObject(-1, '이미 진행 중인 교환/반품 신청이 있습니다.');

		$type = (Context::get('claim_type') === 'exchange') ? 'exchange' : 'return';
		$reason = trim((string)Context::get('reason'));
		if ($reason === '') return new BaseObject(-1, '사유를 입력해 주세요.');

		$args = new stdClass;
		$args->claim_srl = getNextSequence();
		$args->order_srl = (int)$order->order_srl;
		$args->order_code = $order->order_code;
		$args->member_srl = (int)$logged->member_srl;
		$args->type = $type;
		$args->reason = $reason;
		$args->items = trim((string)Context::get('items'));
		$args->status = 'requested';
		$args->refund_amount = 0;
		$args->admin_memo = '';
		$out = executeQuery('ddggshop.insertClaim', $args);
		if (!$out->toBool()) return $out;

		ddggshopModel::logOrder((int)$order->order_srl, 'claim_request', '', '', ddggshopModel::claimTypeLabel($type) . ' 신청: ' . $reason);
		$admin_url = ddggshopModel::consoleOrderUrl($order->order_srl);
		foreach (ddggshopModel::getAdminMemberSrls() as $asrl)
		{
			ddggshopModel::notifyMember($asrl, '🔁 ' . ddggshopModel::claimTypeLabel($type) . ' 신청 — 주문 ' . $order->order_code, $admin_url);
		}
		$this->setMessage(ddggshopModel::claimTypeLabel($type) . ' 신청이 접수되었습니다.');
	}

	/* ===================== 배송지 주소록 ===================== */

	public function procDdggshopSaveAddress()
	{
		$logged = Context::get('logged_info');
		$member_srl = (int)$logged->member_srl;
		$receiver = trim((string)Context::get('receiver_name'));
		if ($receiver === '') return new BaseObject(-1, '받는분 이름을 입력해 주세요.');

		$is_default = (Context::get('is_default') === 'Y') ? 'Y' : 'N';
		if ($is_default === 'Y')
		{
			executeQuery('ddggshop.unsetDefaultAddressBook', (object) array('member_srl' => $member_srl, 'is_default' => 'N'));
		}

		$args = new stdClass;
		$args->label = trim((string)Context::get('label'));
		$args->receiver_name = $receiver;
		$args->receiver_phone = trim((string)Context::get('receiver_phone'));
		$args->zipcode = trim((string)Context::get('zipcode'));
		$args->addr1 = trim((string)Context::get('addr1'));
		$args->addr2 = trim((string)Context::get('addr2'));
		$args->is_default = $is_default;

		$srl = (int)Context::get('address_book_srl');
		if ($srl)
		{
			$existing = ddggshopModel::getAddressBook($srl);
			if (!$existing || (int)$existing->member_srl !== $member_srl) return new BaseObject(-1, '권한이 없습니다.');
			$args->address_book_srl = $srl;
			$args->member_srl = $member_srl;
			$output = executeQuery('ddggshop.updateAddressBook', $args);
		}
		else
		{
			$args->address_book_srl = getNextSequence();
			$args->member_srl = $member_srl;
			if (!count(ddggshopModel::getAddressBookList($member_srl))) $args->is_default = 'Y';
			$output = executeQuery('ddggshop.insertAddressBook', $args);
		}
		if (!$output->toBool()) return $output;
		$this->setMessage('success_registed');
	}

	public function procDdggshopDeleteAddress()
	{
		$logged = Context::get('logged_info');
		$member_srl = (int)$logged->member_srl;
		$srl = (int)Context::get('address_book_srl');
		$output = executeQuery('ddggshop.deleteAddressBook', (object) array('address_book_srl' => $srl, 'member_srl' => $member_srl));
		if (!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
	}

	public function procDdggshopSetDefaultAddress()
	{
		$logged = Context::get('logged_info');
		$member_srl = (int)$logged->member_srl;
		$srl = (int)Context::get('address_book_srl');
		$a = ddggshopModel::getAddressBook($srl);
		if (!$a || (int)$a->member_srl !== $member_srl) return new BaseObject(-1, '권한이 없습니다.');

		executeQuery('ddggshop.unsetDefaultAddressBook', (object) array('member_srl' => $member_srl, 'is_default' => 'N'));
		$u = new stdClass;
		$u->address_book_srl = $srl;
		$u->member_srl = $member_srl;
		$u->label = $a->label;
		$u->receiver_name = $a->receiver_name;
		$u->receiver_phone = $a->receiver_phone;
		$u->zipcode = $a->zipcode;
		$u->addr1 = $a->addr1;
		$u->addr2 = $a->addr2;
		$u->is_default = 'Y';
		executeQuery('ddggshop.updateAddressBook', $u);
		$this->setMessage('기본 배송지로 설정했습니다.');
	}

	/**
	 * 주문 상품들의 재고 차감 + 판매수 증가 (원자적).
	 * @param bool $guard true면 재고 부족 시 차감 거부(0건) → Exception('STOCK:상품명') 발생(주문 생성 단계).
	 *                    false면 차감만 수행(이미 결제 완료된 토스 승인 단계 — 환불 위험 회피).
	 * 반드시 DB 트랜잭션 내부에서 호출할 것.
	 */
	protected function applyStockForOrder($order_srl, $guard = true)
	{
		$items = ddggshopModel::getOrderItemList($order_srl);
		$oDB = DB::getInstance();
		$soldout = array();
		foreach ($items as $oi)
		{
			if (!$oi->item_srl) continue;
			$item = ddggshopModel::getItem($oi->item_srl);
			if (!$item) continue;
			$qty = (int)$oi->qty;
			if ($item->use_stock === 'Y' && (int)$oi->option_srl)
			{
				if ($guard)
				{
					executeQuery('ddggshop.decreaseItemOptionStockGuarded', (object) array('option_srl' => (int)$oi->option_srl, 'qty' => $qty));
					if ($oDB->getAffectedRows() < 1) throw new Exception('STOCK:' . $oi->item_name);
				}
				else
				{
					executeQuery('ddggshop.addItemOptionStock', (object) array('option_srl' => (int)$oi->option_srl, 'stock_delta' => -$qty));
				}
				// 구매수 증가 + 본체 재고를 옵션 합계로 동기화
				executeQuery('ddggshop.addItemStock', (object) array('item_srl' => (int)$oi->item_srl, 'stock_delta' => 0, 'buy_count_delta' => $qty));
				ddggshopModel::syncItemStockFromOptions((int)$oi->item_srl);
				// 옵션 조합 품절 감지
				$opt = ddggshopModel::getItemOption((int)$oi->option_srl);
				if ($opt && (int)$opt->stock <= 0)
				{
					$soldout[] = array('item_srl' => (int)$oi->item_srl, 'item_name' => $oi->item_name, 'option_label' => ($opt->option_label ?? ($oi->option_name ?? '')));
				}
			}
			elseif ($item->use_stock === 'Y')
			{
				if ($guard)
				{
					executeQuery('ddggshop.decreaseItemStockGuarded', (object) array('item_srl' => (int)$oi->item_srl, 'qty' => $qty));
					if ($oDB->getAffectedRows() < 1) throw new Exception('STOCK:' . $oi->item_name);
				}
				else
				{
					executeQuery('ddggshop.addItemStock', (object) array('item_srl' => (int)$oi->item_srl, 'stock_delta' => -$qty, 'buy_count_delta' => $qty));
				}
				// 상품 품절 감지
				$fresh = ddggshopModel::getItem((int)$oi->item_srl);
				if ($fresh && (int)$fresh->stock <= 0)
				{
					$soldout[] = array('item_srl' => (int)$oi->item_srl, 'item_name' => $oi->item_name, 'option_label' => '');
				}
			}
			else
			{
				executeQuery('ddggshop.addItemStock', (object) array('item_srl' => (int)$oi->item_srl, 'stock_delta' => 0, 'buy_count_delta' => $qty));
			}
		}
		return $soldout;
	}

	/** 품절 관리자 알림 (트랜잭션 커밋 후 호출) */
	protected function notifySoldOut($soldout)
	{
		if (empty($soldout)) return;
		$cfg = ddggshopModel::getConfig();
		if (($cfg->notify_soldout ?? 'Y') !== 'Y') return;
		$admins = ddggshopModel::getAdminMemberSrls();
		if (empty($admins)) return;
		foreach ($soldout as $s)
		{
			$label = $s['item_name'] . ($s['option_label'] !== '' ? (' [' . $s['option_label'] . ']') : '');
			$url = ddggshopModel::productUrl((int) $s['item_srl']);
			ddggshopModel::sendShopNotify($admins, '[' . $cfg->shop_name . '] ⚠ 품절: ' . $label, $url, 0);
		}
	}

	/** 회원 주문에 포인트 적립 */
	protected function awardOrderPoints($order)
	{
		if (!$order->member_srl) return;
		$items = ddggshopModel::getOrderItemList($order->order_srl);
		$total_point = 0;
		foreach ($items as $oi) $total_point += (int)$oi->point_amount;
		if ($total_point > 0)
		{
			getController('point')->setPoint((int)$order->member_srl, $total_point, 'plus', 'ddggshop_order');
		}
	}

	/** 주문 소유자의 장바구니 비우기 */
	protected function clearOrderCart($member_srl, $key)
	{
		$c = new stdClass;
		if ($member_srl)
		{
			$c->member_srl = (int)$member_srl;
		}
		elseif ($key && $key->session_key)
		{
			$c->session_key = (string)$key->session_key;
		}
		else
		{
			return;
		}
		executeQuery('ddggshop.clearCart', $c);
	}

	/** 결제 로그 기록 */
	protected function logPay($order, $action, $req, $res, $result, $tid)
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

	/* ===================== 비회원 주문조회 ===================== */

	/** 이 세션에서 해당 주문 결과를 볼 수 있도록 권한 부여 */
	public function grantOrderAccess($order_srl)
	{
		if (!isset($_SESSION['ddggshop_order_access']) || !is_array($_SESSION['ddggshop_order_access']))
		{
			$_SESSION['ddggshop_order_access'] = array();
		}
		$_SESSION['ddggshop_order_access'][(int)$order_srl] = true;
	}

	/** 비회원 주문조회. 입력: order_code, order_password */
	public function procDdggshopOrderLookup()
	{
		$order_code = trim((string)Context::get('order_code'));
		$password = trim((string)Context::get('order_password'));
		$order = ddggshopModel::getOrderByCode($order_code);
		if (!$order)
		{
			return new BaseObject(-1, '주문번호를 찾을 수 없습니다.');
		}

		// 회원 주문이면 본인만
		if ($order->member_srl)
		{
			if (!Context::get('is_logged'))
			{
				return new BaseObject(-1, '회원 주문입니다. 로그인 후 마이페이지에서 확인해 주세요.');
			}
			$logged = Context::get('logged_info');
			if ((int)$logged->member_srl !== (int)$order->member_srl)
			{
				return new BaseObject(-1, '본인 주문만 조회할 수 있습니다.');
			}
		}
		else
		{
			// 비회원: 비밀번호 검증
			if (!$order->order_password || !getModel('member')->isValidPassword($order->order_password, $password))
			{
				return new BaseObject(-1, '비밀번호가 일치하지 않습니다.');
			}
		}

		$this->grantOrderAccess($order->order_srl);
		$shop_mid = (string)Context::get('shop_mid') ?: $this->module_info->mid;
		$this->add('result_url', getNotEncodedUrl('', 'mid', $shop_mid, 'act', 'dispDdggshopOrderResult', 'order_code', $order_code));
	}
}
