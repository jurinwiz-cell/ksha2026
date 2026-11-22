<?php
/**
 * 쇼핑몰 - 관리자 컨트롤러
 * 상품/카테고리 CRUD, 주문 상태/배송 처리, 입금확인, 설정 저장.
 * @author 딱따고기 (https://ddgglabs.org)
 */
class ddggshopAdminController extends ddggshop
{
	/** 콘솔에서 호출된 경우(console_return 전달) 해당 콘솔 URL로 리다이렉트 */
	public function setRedirectUrl($url = './', $output = null)
	{
		$ret = Context::get('console_return');
		if ($ret) $url = $ret;
		return parent::setRedirectUrl($url, $output);
	}

	/* ===================== 상품 ===================== */

	public function procDdggshopAdminInsertItem()
	{
		$item_srl = (int)Context::get('item_srl');
		$item_name = trim((string)Context::get('item_name'));
		if ($item_name === '')
		{
			return new BaseObject(-1, '상품명을 입력해 주세요.');
		}

		$existing = $item_srl ? ddggshopModel::getItem($item_srl) : null;
		$is_edit = (bool)$existing;
		$item_srl_final = $is_edit ? $item_srl : getNextSequence();

		// 대표 이미지: 업로드 우선, 없으면 URL 필드
		$thumb = trim((string)Context::get('thumb'));
		$up = $this->uploadItemImage('thumb_file', $item_srl_final . '_t');
		if ($up !== '') $thumb = $up;

		// 추가 이미지: 유지된 기존 이미지(images[]) + 신규 업로드(다중)
		$images = array();
		$kept = Context::get('images');
		if (is_array($kept))
		{
			foreach ($kept as $u) { $u = trim((string)$u); if ($u !== '') $images[] = $u; }
		}
		else
		{
			// 구버전 폼(텍스트영역) 하위호환
			foreach (preg_split('/[\r\n]+/', (string)Context::get('images_text')) as $line)
			{
				$line = trim($line); if ($line !== '') $images[] = $line;
			}
		}
		foreach ($this->uploadItemImages('image_files', $item_srl_final) as $u) $images[] = $u;

		// 커스텀 속성: attr_name[] / attr_value[] → JSON
		$attrs = array();
		$an = Context::get('attr_name');
		$av = Context::get('attr_value');
		if (is_array($an))
		{
			foreach ($an as $i => $name)
			{
				$name = trim((string)$name);
				$val = isset($av[$i]) ? trim((string)$av[$i]) : '';
				if ($name !== '' && $val !== '') $attrs[] = array('name' => $name, 'value' => $val);
			}
		}

		// 옵션 그룹 파싱
		$opt_use = (Context::get('use_options') === 'Y');
		$g_names = Context::get('opt_gname');
		$g_values = Context::get('opt_gvalues');
		$groups = array();
		if ($opt_use && is_array($g_names))
		{
			foreach ($g_names as $i => $gn)
			{
				$gn = trim((string)$gn);
				$gv_raw = isset($g_values[$i]) ? (string)$g_values[$i] : '';
				$vals = array();
				foreach (preg_split('/\s*,\s*/', $gv_raw) as $v)
				{
					$v = trim($v);
					if ($v !== '') $vals[] = $v;
				}
				if ($gn !== '' && count($vals)) $groups[] = array('name' => $gn, 'values' => $vals);
			}
		}
		$has_options = ($opt_use && count($groups)) ? 'Y' : 'N';

		$args = new stdClass;
		$args->category_srl = (int)Context::get('category_srl');
		$args->item_name = $item_name;
		$args->item_code = trim((string)Context::get('item_code'));
		$args->price = max(0, (int)Context::get('price'));
		$args->cost_price = max(0, (int)Context::get('cost_price'));
		$args->stock = (int)Context::get('stock');
		$args->use_stock = (Context::get('use_stock') === 'N') ? 'N' : 'Y';
		$args->buy_limit = max(0, (int)Context::get('buy_limit'));
		// 구매 후 회원 그룹 변경
		$args->grant_group = (Context::get('grant_group') === 'Y') ? 'Y' : 'N';
		$g_add = Context::get('grant_add_groups'); if (!is_array($g_add)) $g_add = $g_add ? array($g_add) : array();
		$g_del = Context::get('grant_del_groups'); if (!is_array($g_del)) $g_del = $g_del ? array($g_del) : array();
		$args->grant_add_groups = implode(',', array_values(array_filter(array_map('intval', $g_add))));
		$args->grant_del_groups = implode(',', array_values(array_filter(array_map('intval', $g_del))));
		$args->summary = trim((string)Context::get('summary'));
		$args->content = Context::get('content');
		$args->thumb = $thumb;
		$args->images = $images ? json_encode($images) : '';
		$args->brand = trim((string)Context::get('brand'));
		$args->manufacturer = trim((string)Context::get('manufacturer'));
		$args->origin = trim((string)Context::get('origin'));
		$args->attributes = $attrs ? json_encode($attrs, JSON_UNESCAPED_UNICODE) : '';
		$args->has_options = $has_options;
		$args->option_groups = ($has_options === 'Y') ? json_encode($groups, JSON_UNESCAPED_UNICODE) : '';
		$sft = (string)Context::get('ship_fee_type');
		$args->ship_fee_type = in_array($sft, array('default', 'free', 'fixed'), true) ? $sft : 'default';
		$args->ship_fee = ($args->ship_fee_type === 'fixed') ? max(0, (int)Context::get('ship_fee')) : 0;
		$args->require_address = (Context::get('require_address') === 'N') ? 'N' : 'Y';
		$args->point_rate = (Context::get('point_rate') === '' || Context::get('point_rate') === null) ? -1 : (int)Context::get('point_rate');
		$args->delivery_fee_type = 'F';
		$args->status = in_array(Context::get('status'), array('sale', 'soldout', 'hidden'), true) ? Context::get('status') : 'sale';
		$args->is_recommend = 'N';
		$args->is_new = 'N';
		$args->last_update = date('YmdHis');
		$args->item_srl = $item_srl_final;

		// 정렬값(선택): 입력하면 그대로, 비우면 신규는 item_srl, 수정은 기존값 유지
		$lo_raw = Context::get('list_order');
		$lo_set = ($lo_raw !== '' && $lo_raw !== null);

		if ($is_edit)
		{
			if ($lo_set) $args->list_order = (int)$lo_raw;
			$output = executeQuery('ddggshop.updateItem', $args);
		}
		else
		{
			$args->view_count = 0;
			$args->buy_count = 0;
			$args->list_order = $lo_set ? (int)$lo_raw : $item_srl_final;
			$logged = Context::get('logged_info');
			$args->member_srl = $logged ? (int)$logged->member_srl : 0;
			$args->regdate = date('YmdHis');
			$output = executeQuery('ddggshop.insertItem', $args);
		}
		if (!$output->toBool()) return $output;

		// 상품 태그 매핑 갱신
		executeQuery('ddggshop.deleteItemTagsByItem', (object) array('item_srl' => $item_srl_final));
		$tags = Context::get('tags');
		if (!is_array($tags)) $tags = $tags ? array($tags) : array();
		foreach ($tags as $t)
		{
			$tsrl = (int)$t;
			if (!$tsrl) continue;
			$ta = new stdClass;
			$ta->item_tag_srl = getNextSequence();
			$ta->item_srl = $item_srl_final;
			$ta->tag_srl = $tsrl;
			executeQuery('ddggshop.insertItemTag', $ta);
		}

		// 옵션 조합 갱신 (전체 삭제 후 재삽입)
		executeQuery('ddggshop.deleteItemOptionsByItem', (object) array('item_srl' => $item_srl_final));
		if ($has_options === 'Y')
		{
			$labels = Context::get('opt_label');
			$prices = Context::get('opt_price');
			$stocks = Context::get('opt_stock');
			if (is_array($labels))
			{
				$ord = 1;
				foreach ($labels as $i => $lb)
				{
					$lb = trim((string)$lb);
					if ($lb === '') continue;
					$o = new stdClass;
					$o->option_srl = getNextSequence();
					$o->item_srl = $item_srl_final;
					$o->option_label = $lb;
					$o->price_add = isset($prices[$i]) ? (int)$prices[$i] : 0;
					$o->stock = isset($stocks[$i]) ? (int)$stocks[$i] : 0;
					$o->list_order = $ord++;
					$o->status = 'Y';
					executeQuery('ddggshop.insertItemOption', $o);
				}
			}
			// 옵션 사용 상품: 본체 재고는 직접 입력이 아니라 옵션 재고 합계로 설정
			ddggshopModel::syncItemStockFromOptions($item_srl_final);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminItemList'));
	}

	/** 단일 이미지 업로드 ($_FILES[$field]) → 공개 URL ('' 면 없음) */
	protected function uploadItemImage($field, $name)
	{
		$file = isset($_FILES[$field]) ? $_FILES[$field] : null;
		if (!$file || empty($file['tmp_name']) || !empty($file['error'])) return '';
		return $this->saveUploadedImage($file, $name);
	}

	/** 다중 이미지 업로드 ($_FILES[$field][]) → URL 배열 */
	protected function uploadItemImages($field, $name_prefix)
	{
		$out = array();
		if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['tmp_name'])) return $out;
		$f = $_FILES[$field];
		$cnt = count($f['tmp_name']);
		for ($i = 0; $i < $cnt; $i++)
		{
			if (empty($f['tmp_name'][$i]) || !empty($f['error'][$i])) continue;
			$one = array('name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i]);
			$u = $this->saveUploadedImage($one, $name_prefix . '_' . $i . '_' . mt_rand(1000, 9999));
			if ($u !== '') $out[] = $u;
		}
		return $out;
	}

	/** 업로드 이미지 저장 후 공개 URL 반환 ('' = 실패/없음) */
	protected function saveUploadedImage($file, $name)
	{
		if (empty($file['tmp_name']) || !empty($file['error'])) return '';
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'), true)) return '';
		$dir = RX_BASEDIR . 'files/attach/ddggshop/item';
		FileHandler::makeDir($dir);
		$filename = $name . '.' . $ext;
		$target = $dir . '/' . $filename;
		if (!Rhymix\Framework\Storage::moveUploadedFile($file['tmp_name'], $target, 'move'))
		{
			return '';
		}
		// 대표이미지는 파일명이 고정(상품번호_t)이라 교체 시 같은 URL로 덮어써짐 → 캐시 무효화용 버전값 부여
		return RX_BASEURL . 'files/attach/ddggshop/item/' . $filename . '?v=' . time();
	}

	/** 상품 엑셀(CSV) 일괄 가져오기 (업서트). 컬럼 순서는 내보내기와 동일 */
	public function procDdggshopAdminImportItems()
	{
		$f = isset($_FILES['csv_file']) ? $_FILES['csv_file'] : null;
		if (!$f || empty($f['tmp_name']) || !empty($f['error'])) return new BaseObject(-1, 'CSV 파일을 선택해 주세요.');
		$fp = fopen($f['tmp_name'], 'r');
		if (!$fp) return new BaseObject(-1, '파일을 읽을 수 없습니다.');

		$header = fgetcsv($fp); // 헤더 스킵
		$created = 0; $updated = 0; $skipped = 0;
		$logged = Context::get('logged_info');
		$st_map = array('판매중' => 'sale', '품절' => 'soldout', '숨김' => 'hidden');
		$sf_map = array('기본' => 'default', '무료' => 'free', '개별' => 'fixed');
		while (($r = fgetcsv($fp)) !== false)
		{
			if (count($r) < 2) { continue; }
			$name = trim((string)($r[1] ?? ''));
			if ($name === '') { $skipped++; continue; }
			$srl = (int)($r[0] ?? 0);
			$existing = $srl ? ddggshopModel::getItem($srl) : null;

			$st = trim((string)($r[9] ?? 'sale'));
			$st = $st_map[$st] ?? (in_array($st, array('sale', 'soldout', 'hidden'), true) ? $st : 'sale');
			$sft = trim((string)($r[13] ?? 'default'));
			$sft = $sf_map[$sft] ?? (in_array($sft, array('default', 'free', 'fixed'), true) ? $sft : 'default');
			$us = trim((string)($r[8] ?? 'Y'));
			$us = (in_array($us, array('N', '무제한', '안함', 'n'), true)) ? 'N' : 'Y';

			$a = $existing ? clone $existing : new stdClass;
			$a->item_name = $name;
			$a->item_code = trim((string)($r[2] ?? ''));
			$a->category_srl = (int)($r[3] ?? 0);
			$a->price = max(0, (int)($r[5] ?? 0));
			$a->cost_price = max(0, (int)($r[6] ?? 0));
			$a->stock = (int)($r[7] ?? 0);
			$a->use_stock = $us;
			$a->status = $st;
			$a->brand = trim((string)($r[10] ?? ''));
			$a->manufacturer = trim((string)($r[11] ?? ''));
			$a->origin = trim((string)($r[12] ?? ''));
			$a->ship_fee_type = $sft;
			$a->ship_fee = ($sft === 'fixed') ? max(0, (int)($r[14] ?? 0)) : 0;
			$a->point_rate = (isset($r[15]) && $r[15] !== '') ? (int)$r[15] : -1;
			$a->last_update = date('YmdHis');

			if ($existing)
			{
				executeQuery('ddggshop.updateItem', $a);
				$updated++;
			}
			else
			{
				$a->item_srl = getNextSequence();
				$a->summary = ''; $a->content = ''; $a->thumb = ''; $a->images = '';
				$a->attributes = ''; $a->has_options = 'N'; $a->option_groups = '';
				$a->delivery_fee_type = 'F'; $a->is_recommend = 'N'; $a->is_new = 'N';
				$a->view_count = 0; $a->buy_count = 0; $a->list_order = $a->item_srl;
				$a->member_srl = $logged ? (int)$logged->member_srl : 0;
				$a->regdate = date('YmdHis');
				executeQuery('ddggshop.insertItem', $a);
				$created++;
			}
		}
		fclose($fp);
		$this->setMessage('가져오기 완료 — 신규 ' . $created . ' · 수정 ' . $updated . ' · 건너뜀 ' . $skipped);
		$this->setRedirectUrl(Context::get('console_return') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminItemList'));
	}

	public function procDdggshopAdminDeleteItem()
	{
		$item_srl = (int)Context::get('item_srl');
		$output = executeQuery('ddggshop.deleteItem', (object) array('item_srl' => $item_srl));
		if (!$output->toBool()) return $output;
		executeQuery('ddggshop.deleteItemTagsByItem', (object) array('item_srl' => $item_srl));
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminItemList'));
	}

	/* ===================== 카테고리 ===================== */

	public function procDdggshopAdminInsertCategory()
	{
		$category_srl = (int)Context::get('category_srl');
		$name = trim((string)Context::get('category_name'));
		if ($name === '')
		{
			return new BaseObject(-1, '카테고리명을 입력해 주세요.');
		}

		$parent_srl = (int)Context::get('parent_srl');
		// 자기 자신/하위를 부모로 지정 방지 + depth 계산 (최대 3단계: 0,1,2)
		$depth = 0;
		if ($parent_srl)
		{
			$parent = ddggshopModel::getCategory($parent_srl);
			if (!$parent) { $parent_srl = 0; }
			elseif ($category_srl && in_array($parent_srl, ddggshopModel::getDescendantCategorySrls($category_srl), true))
			{
				return new BaseObject(-1, '자기 자신 또는 하위 카테고리를 상위로 지정할 수 없습니다.');
			}
			else { $depth = min(2, (int)$parent->depth + 1); }
		}

		$args = new stdClass;
		$args->category_name = $name;
		$args->parent_srl = $parent_srl;
		$args->depth = $depth;
		$args->status = (Context::get('status') === 'N') ? 'N' : 'Y';
		$args->list_order = (int)Context::get('list_order');
		// 카테고리명 다국어 번역 (cat_i18n[<lang>] = 번역명)
		$i18n = Context::get('cat_i18n');
		$map = array();
		if (is_array($i18n))
		{
			foreach ($i18n as $code => $val)
			{
				$code = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$code);
				$val = trim((string)$val);
				if ($code !== '' && $val !== '') $map[$code] = $val;
			}
		}
		$args->name_i18n = count($map) ? json_encode($map, JSON_UNESCAPED_UNICODE) : '';

		if ($category_srl && ddggshopModel::getCategory($category_srl))
		{
			$args->category_srl = $category_srl;
			$output = executeQuery('ddggshop.updateCategory', $args);
		}
		else
		{
			$args->category_srl = getNextSequence();
			if (!$args->list_order) $args->list_order = $args->category_srl;
			$args->item_count = 0;
			$args->regdate = date('YmdHis');
			$output = executeQuery('ddggshop.insertCategory', $args);
		}
		if (!$output->toBool()) return $output;
		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminCategory'));
	}

	/** 추가배송비(우편번호 구간) 규칙 등록 */
	public function procDdggshopAdminSaveShipFee()
	{
		$zs = preg_replace('/[^0-9]/', '', (string)Context::get('zip_start'));
		$ze = preg_replace('/[^0-9]/', '', (string)Context::get('zip_end'));
		$region = trim((string)Context::get('region'));
		if ($zs === '' && $ze === '')
		{
			// 우편번호 없이 등록 = 해외/지역 선택형 규칙 → 지역명 필수
			if ($region === '') return new BaseObject(-1, '우편번호 구간 또는 지역명 중 하나 이상을 입력해 주세요.');
		}
		else
		{
			if ($zs === '' || $ze === '') return new BaseObject(-1, '우편번호는 시작/끝을 모두 입력하거나, 둘 다 비워 지역명만 입력해 주세요.');
			if ($ze < $zs) { $t = $zs; $zs = $ze; $ze = $t; }
		}
		$a = new stdClass;
		$a->fee_srl = getNextSequence();
		$a->region = $region;
		$a->zip_start = $zs;
		$a->zip_end = $ze;
		$a->extra_fee = max(0, (int)Context::get('extra_fee'));
		$a->list_order = $a->fee_srl;
		$a->status = 'Y';
		$output = executeQuery('ddggshop.insertShipFee', $a);
		if (!$output->toBool()) return $output;
		$this->setMessage('success_registed');
	}

	/* ===================== 쿠폰 ===================== */

	public function procDdggshopAdminSaveCoupon()
	{
		$coupon_srl = (int)Context::get('coupon_srl');
		$code = strtoupper(trim((string)Context::get('code')));
		if ($code === '') return new BaseObject(-1, '쿠폰 코드를 입력해 주세요.');
		$existing = ddggshopModel::getCouponByCode($code);
		if ($coupon_srl > 0)
		{
			if (!ddggshopModel::getCoupon($coupon_srl)) return new BaseObject(-1, '수정할 쿠폰을 찾을 수 없습니다.');
			if ($existing && (int)$existing->coupon_srl !== $coupon_srl) return new BaseObject(-1, '이미 존재하는 쿠폰 코드입니다.');
		}
		elseif ($existing)
		{
			return new BaseObject(-1, '이미 존재하는 쿠폰 코드입니다.');
		}
		$a = new stdClass;
		$a->code = $code;
		$a->title = trim((string)Context::get('title'));
		$a->discount_type = (Context::get('discount_type') === 'percent') ? 'percent' : 'amount';
		$a->discount_value = max(0, (int)Context::get('discount_value'));
		$a->min_order = max(0, (int)Context::get('min_order'));
		$a->max_discount = max(0, (int)Context::get('max_discount'));
		$sd = preg_replace('/[^0-9]/', '', (string)Context::get('start_date'));
		$a->start_date = $sd ? substr($sd . '000000', 0, 14) : '';
		$ed = preg_replace('/[^0-9]/', '', (string)Context::get('end_date'));
		$a->end_date = $ed ? substr($ed . '235959', 0, 14) : '';
		$a->usage_limit = max(0, (int)Context::get('usage_limit'));
		$a->per_member = max(0, (int)Context::get('per_member'));
		$a->auto_signup = (Context::get('auto_signup') === 'Y') ? 'Y' : 'N';
		if ($coupon_srl > 0)
		{
			// 수정: 사용횟수·상태는 보존
			$a->coupon_srl = $coupon_srl;
			$output = executeQuery('ddggshop.updateCoupon', $a);
			if (!$output->toBool()) return $output;
			$this->setMessage('success_updated');
		}
		else
		{
			$a->coupon_srl = getNextSequence();
			$a->used_count = 0;
			$a->status = 'Y';
			$output = executeQuery('ddggshop.insertCoupon', $a);
			if (!$output->toBool()) return $output;
			$this->setMessage('success_registed');
		}
	}

	public function procDdggshopAdminDeleteCoupon()
	{
		executeQuery('ddggshop.deleteCoupon', (object) array('coupon_srl' => (int)Context::get('coupon_srl')));
		$this->setMessage('success_deleted');
	}

	/** 쿠폰 일괄 발급 (전체 회원 또는 특정 그룹) */
	public function procDdggshopAdminIssueCoupon()
	{
		$coupon_srl = (int)Context::get('coupon_srl');
		if (!$coupon_srl || !ddggshopModel::getCoupon($coupon_srl)) return new BaseObject(-1, '쿠폰을 찾을 수 없습니다.');
		$group_srl = (int)Context::get('group_srl');
		if ($group_srl)
		{
			$out = executeQueryArray('member.getMemberListWithinGroup', (object) array('selected_group_srl' => array($group_srl), 'list_count' => 100000));
		}
		else
		{
			$out = executeQueryArray('member.getMemberList', (object) array('list_count' => 100000));
		}
		$members = ($out->toBool() && $out->data) ? $out->data : array();
		$cnt = 0;
		foreach ($members as $m)
		{
			if (ddggshopModel::issueCoupon($coupon_srl, (int)$m->member_srl)) $cnt++;
		}
		$this->setMessage($cnt . '명에게 쿠폰을 발급했습니다.');
	}

	/** 회원 검색 (쿠폰 개별발급 모달용) */
	public function procDdggshopAdminSearchMember()
	{
		$keyword = trim((string)Context::get('keyword'));
		$members = ($keyword === '') ? array() : ddggshopModel::searchMembers($keyword, 20);
		$this->add('members', $members);
	}

	/** 쿠폰 개별 발급 (특정 회원 — 회원번호/아이디/이메일/닉네임, 여러 명은 줄바꿈·쉼표 구분) */
	public function procDdggshopAdminIssueCouponMember()
	{
		$coupon_srl = (int)Context::get('coupon_srl');
		if (!$coupon_srl || !ddggshopModel::getCoupon($coupon_srl)) return new BaseObject(-1, '쿠폰을 찾을 수 없습니다.');
		$raw = trim((string)Context::get('members'));
		if ($raw === '') return new BaseObject(-1, '대상 회원을 입력해 주세요.');

		$tokens = preg_split('/[\r\n,]+/', $raw);
		$issued = 0; $dup = 0; $notfound = array();
		$seen = array();
		foreach ($tokens as $tok)
		{
			$tok = trim($tok);
			if ($tok === '') continue;
			$member_srl = ddggshopModel::resolveMemberSrl($tok);
			if (!$member_srl) { $notfound[] = $tok; continue; }
			if (isset($seen[$member_srl])) continue;
			$seen[$member_srl] = true;
			if (ddggshopModel::issueCoupon($coupon_srl, $member_srl)) $issued++;
			else $dup++;
		}

		$msg = $issued . '명에게 발급했습니다.';
		if ($dup) $msg .= ' (이미 보유 ' . $dup . '명 제외)';
		if (count($notfound)) $msg .= ' 회원을 찾지 못함: ' . implode(', ', $notfound);
		$this->setMessage($msg);
	}

	/** 추가배송비 규칙 삭제 */
	public function procDdggshopAdminDeleteShipFee()
	{
		executeQuery('ddggshop.deleteShipFee', (object) array('fee_srl' => (int)Context::get('fee_srl')));
		$this->setMessage('success_deleted');
	}

	/** 카테고리 순서 일괄 변경 (드래그앤드롭). 입력: srls = 콤마구분 category_srl 순서 */
	public function procDdggshopAdminReorderCategory()
	{
		$srls = (string)Context::get('srls');
		$arr = array_filter(array_map('intval', explode(',', $srls)));
		$i = 1;
		foreach ($arr as $srl)
		{
			executeQuery('ddggshop.updateCategoryOrder', (object) array('category_srl' => $srl, 'list_order' => $i++));
		}
		$this->setMessage('success_updated');
	}

	/**
	 * 상품 순서 일괄 변경 (드래그앤드롭). 입력: srls = 콤마구분 item_srl (위→아래 순서)
	 * 목록은 list_order DESC 정렬이므로, 드래그한 상품들의 기존 list_order 값을
	 * 내림차순으로 재분배(맨 위 = 가장 큰 값)하여 다른 페이지 상품과의 순서를 보존한다.
	 */
	public function procDdggshopAdminReorderItem()
	{
		$srls = (string)Context::get('srls');
		$arr = array_values(array_filter(array_map('intval', explode(',', $srls))));
		if (!count($arr)) { $this->setMessage('success_updated'); return; }

		$output = executeQueryArray('ddggshop.getItemsBySrls', (object) array('item_srls' => $arr));
		$orders = array();
		if ($output->toBool() && $output->data)
		{
			foreach ($output->data as $row) $orders[] = (int)$row->list_order;
		}
		rsort($orders); // 내림차순(맨 위가 가장 큰 list_order)

		foreach ($arr as $idx => $srl)
		{
			$lo = isset($orders[$idx]) ? $orders[$idx] : (count($arr) - $idx);
			executeQuery('ddggshop.updateItemOrder', (object) array('item_srl' => $srl, 'list_order' => $lo));
		}
		$this->setMessage('success_updated');
	}

	/* ===================== 홈 화면 블록 빌더 ===================== */

	public function procDdggshopAdminSaveHomeBlock()
	{
		$block_srl = (int)Context::get('block_srl');
		$block_type = (Context::get('block_type') === 'promo') ? 'promo' : 'products';
		$title = trim((string)Context::get('title'));

		$args = new stdClass;
		$args->block_type = $block_type;
		$args->title = $title;
		$args->layout = (Context::get('layout') === 'scroll') ? 'scroll' : 'grid';
		$lc = (int)Context::get('list_count');
		$args->list_count = min(40, max(1, $lc ?: 8));
		$args->status = (Context::get('status') === 'Y') ? 'Y' : 'N';

		if ($block_type === 'promo')
		{
			$image = trim((string)Context::get('image'));
			$imgfile = isset($_FILES['image_file']) ? $_FILES['image_file'] : null;
			if ($imgfile && !empty($imgfile['tmp_name']))
			{
				$up = $this->saveUploadedImage($imgfile, 'homeblock_' . ($block_srl ?: getNextSequence()));
				if ($up !== '') $image = $up;
			}
			if ($image === '') return new BaseObject(-1, '프로모션 이미지를 등록해 주세요.');
			$args->image = $image;
			$args->link = trim((string)Context::get('promo_link'));
			$args->source_type = '';
			$args->source_value = '';
		}
		else
		{
			$args->link = trim((string)Context::get('link'));
			$source_type = (string)Context::get('source_type');
			if (!in_array($source_type, array_keys(ddggshopModel::blockSourceList()), true))
			{
				return new BaseObject(-1, '상품 소스를 선택해 주세요.');
			}
			if ($title === '') return new BaseObject(-1, '블록 제목을 입력해 주세요.');
			$args->source_type = $source_type;
			$args->image = '';
			if ($source_type === 'manual')
			{
				$srls = ddggshopModel::parseSrlList((string)Context::get('item_srls'));
				if (!count($srls)) return new BaseObject(-1, '노출할 상품을 1개 이상 선택해 주세요.');
				$args->source_value = json_encode(array_values($srls));
			}
			elseif ($source_type === 'category')
			{
				$cat = (int)Context::get('category_srl');
				if (!$cat) return new BaseObject(-1, '카테고리를 선택해 주세요.');
				$args->source_value = (string)$cat;
			}
			elseif ($source_type === 'tag')
			{
				$tag = (int)Context::get('tag_srl');
				if (!$tag) return new BaseObject(-1, '태그를 선택해 주세요.');
				$args->source_value = (string)$tag;
			}
			else
			{
				$args->source_value = '';
			}
		}

		if ($block_srl && ddggshopModel::getHomeBlock($block_srl))
		{
			$args->block_srl = $block_srl;
			$output = executeQuery('ddggshop.updateHomeBlock', $args);
		}
		else
		{
			$args->block_srl = getNextSequence();
			$args->list_order = $args->block_srl; // 끝에 추가(정렬은 드래그로)
			$args->regdate = date('YmdHis');
			$output = executeQuery('ddggshop.insertHomeBlock', $args);
		}
		if (!$output->toBool()) return $output;
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('console_return') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopConsoleHomeBlocks'));
	}

	public function procDdggshopAdminDeleteHomeBlock()
	{
		$block_srl = (int)Context::get('block_srl');
		if (!$block_srl) return new BaseObject(-1, '잘못된 요청입니다.');
		executeQuery('ddggshop.deleteHomeBlock', (object) array('block_srl' => $block_srl));
		$this->setMessage('success_deleted');
	}

	/** 쿠폰 시스템 사용 on/off 토글 */
	public function procDdggshopAdminToggleCoupon()
	{
		$config = ModuleModel::getModuleConfig('ddggshop');
		if (!is_object($config)) $config = new stdClass;
		$cur = isset($config->use_coupon) ? $config->use_coupon : 'Y';
		$new = ($cur === 'Y') ? 'N' : 'Y';
		$config->use_coupon = $new;
		$output = getController('module')->insertModuleConfig('ddggshop', $config);
		if (!$output->toBool()) return $output;
		$this->add('use_coupon', $new);
		$this->setMessage($new === 'Y' ? '쿠폰 시스템을 사용합니다.' : '쿠폰 시스템을 사용하지 않습니다.');
	}

	/** 카테고리 상품 개수 표시 토글 */
	public function procDdggshopAdminToggleCategoryCount()
	{
		$config = ModuleModel::getModuleConfig('ddggshop');
		if (!is_object($config)) $config = new stdClass;
		$cur = isset($config->show_category_count) ? $config->show_category_count : 'N';
		$new = ($cur === 'Y') ? 'N' : 'Y';
		$config->show_category_count = $new;
		$output = getController('module')->insertModuleConfig('ddggshop', $config);
		if (!$output->toBool()) return $output;
		$this->add('show_category_count', $new);
		$this->setMessage($new === 'Y' ? '카테고리에 상품 개수를 표시합니다.' : '카테고리 상품 개수를 표시하지 않습니다.');
	}

	/** 블록 노출/숨김 토글 (라이브 편집용) */
	public function procDdggshopAdminToggleHomeBlock()
	{
		$block_srl = (int)Context::get('block_srl');
		$block = ddggshopModel::getHomeBlock($block_srl);
		if (!$block) return new BaseObject(-1, '블록을 찾을 수 없습니다.');
		$new = ($block->status === 'Y') ? 'N' : 'Y';
		executeQuery('ddggshop.updateHomeBlockStatus', (object) array('block_srl' => $block_srl, 'status' => $new));
		$this->add('status', $new);
		$this->setMessage('success_updated');
	}

	public function procDdggshopAdminReorderHomeBlocks()
	{
		$srls = (string)Context::get('srls');
		$arr = array_filter(array_map('intval', explode(',', $srls)));
		$i = 1;
		foreach ($arr as $srl)
		{
			executeQuery('ddggshop.updateHomeBlockOrder', (object) array('block_srl' => $srl, 'list_order' => $i++));
		}
		$this->setMessage('success_updated');
	}

	/** 직접 선택(manual) 블록용 상품 검색 — JSON 반환 */
	public function procDdggshopAdminSearchItems()
	{
		$kw = trim((string)Context::get('keyword'));
		$args = new stdClass;
		if ($kw !== '') $args->search_keyword = $kw;
		$args->list_count = 24;
		$args->page = 1;
		$args->sort_index = 'list_order';
		$items = ddggshopModel::getItemListData($args);
		$res = array();
		foreach ($items as $it)
		{
			$res[] = array(
				'item_srl'  => (int)$it->item_srl,
				'item_name' => $it->item_name,
				'thumb'     => $it->thumb,
				'price'     => (int)$it->price,
			);
		}
		$this->add('items', $res);
	}

	public function procDdggshopAdminDeleteCategory()
	{
		$category_srl = (int)Context::get('category_srl');
		$output = executeQuery('ddggshop.deleteCategory', (object) array('category_srl' => $category_srl));
		if (!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminCategory'));
	}

	/* ===================== 주문 ===================== */

	public function procDdggshopAdminUpdateOrderStatus()
	{
		$order_srl = (int)Context::get('order_srl');
		$status = (string)Context::get('status');
		$valid = array('pending', 'paid', 'preparing', 'shipping', 'delivered', 'cancelled', 'refunded');
		if (!in_array($status, $valid, true))
		{
			return new BaseObject(-1, '잘못된 상태값입니다.');
		}
		$order = ddggshopModel::getOrder($order_srl);
		if (!$order) return new BaseObject(-1, '주문을 찾을 수 없습니다.');

		// 취소/환불은 재고복원·포인트회수/환불·쿠폰복원·PG취소를 일괄 처리(정합성)
		if (in_array($status, array('cancelled', 'refunded'), true))
		{
			$admin_memo = trim((string)Context::get('admin_memo'));
			if (in_array($order->status, array('cancelled', 'refunded'), true))
			{
				// 이미 취소/환불 상태 → 라벨만 전환(재고·포인트 부수효과 없이 상태/내역만 갱신)
				if ($order->status !== $status)
				{
					executeQuery('ddggshop.updateOrderStatus', (object) array('order_srl' => $order_srl, 'status' => $status, 'admin_memo' => ($admin_memo !== '' ? $admin_memo : (string)$order->admin_memo)));
					ddggshopModel::logOrder($order_srl, ($status === 'refunded' ? 'refund' : 'cancel'), $order->status, $status, $admin_memo);
					ddggshopModel::recordCancelClaim($order, $status, $admin_memo);
				}
			}
			else
			{
				$out = ddggshopModel::cancelOrder($order, $status, '관리자 ' . ddggshopModel::statusLabel($status) . ' 처리');
				if (!$out->toBool()) return $out;
				if ($admin_memo !== '')
				{
					executeQuery('ddggshop.updateOrderStatus', (object) array('order_srl' => $order_srl, 'status' => $status, 'admin_memo' => $admin_memo));
				}
				// 교환/반품 내역에 취소/환불 기록
				ddggshopModel::recordCancelClaim($order, $status, $admin_memo);
			}
		}
		else
		{
			$args = new stdClass;
			$args->order_srl = $order_srl;
			$args->status = $status;
			$args->admin_memo = (string)Context::get('admin_memo');
			$output = executeQuery('ddggshop.updateOrderStatus', $args);
			if (!$output->toBool()) return $output;
			ddggshopModel::logOrder($order_srl, 'status_change', $order->status, $status, (string)Context::get('admin_memo'));
		}
		// 회원 알림 (배송완료/취소/환불 등 주요 변경)
		if ($order && $order->member_srl && in_array($status, array('preparing', 'shipping', 'delivered', 'cancelled', 'refunded'), true))
		{
			ddggshopModel::notifyMember((int)$order->member_srl, '주문 ' . $order->order_code . ' 상태가 ‘' . ddggshopModel::statusLabel($status) . '’(으)로 변경되었습니다.', ddggshopModel::memberOrderUrl($order->order_code), 'status');
		}
		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminOrderView', 'order_srl', $order_srl));
	}

	public function procDdggshopAdminUpdateShipping()
	{
		$order_srl = (int)Context::get('order_srl');
		$order = ddggshopModel::getOrder($order_srl);
		if (!$order)
		{
			return new BaseObject(-1, '주문을 찾을 수 없습니다.');
		}
		$args = new stdClass;
		$args->order_srl = $order_srl;
		$args->shipping_company = trim((string)Context::get('shipping_company'));
		$args->shipping_invoice = trim((string)Context::get('shipping_invoice'));
		// 택배사는 설정의 배송설정 목록(carrierList) 값만 허용 — 표기 불일치로 배송조회 깨짐 방지
		if ($args->shipping_company !== '' && !in_array($args->shipping_company, ddggshopModel::carrierList(), true))
		{
			return new BaseObject(-1, '택배사는 쇼핑몰 배송설정 목록에서 선택해 주세요.');
		}
		// 송장 입력 시 배송중으로 (이미 배송완료면 유지)
		$args->status = ($order->status === 'delivered') ? 'delivered' : 'shipping';
		$output = executeQuery('ddggshop.updateOrderShipping', $args);
		if (!$output->toBool()) return $output;
		ddggshopModel::logOrder($order_srl, 'shipping', $order->status, $args->status, trim($args->shipping_company . ' ' . $args->shipping_invoice));
		// 회원 알림: 발송
		if ($order->member_srl)
		{
			$inv = $args->shipping_invoice ? (' (' . $args->shipping_company . ' ' . $args->shipping_invoice . ')') : '';
			ddggshopModel::notifyMember((int)$order->member_srl, '📦 상품이 발송되었습니다. 주문 ' . $order->order_code . $inv, ddggshopModel::memberOrderUrl($order->order_code), 'shipping');
		}
		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminOrderView', 'order_srl', $order_srl));
	}

	/** 무통장입금 확인 → 결제완료 + 포인트 적립 */
	public function procDdggshopAdminConfirmDeposit()
	{
		$order_srl = (int)Context::get('order_srl');
		$order = ddggshopModel::getOrder($order_srl);
		if (!$order)
		{
			return new BaseObject(-1, '주문을 찾을 수 없습니다.');
		}
		if ($order->status !== 'pending')
		{
			return new BaseObject(-1, '입금대기 상태의 주문만 확인할 수 있습니다.');
		}

		$oDB = DB::getInstance();
		$oDB->begin();
		try
		{
			// 가드 업데이트: pending → paid 로만 전이(중복 입금확인 멱등 처리)
			$u = new stdClass;
			$u->order_srl = $order_srl;
			$u->status = 'paid';
			$u->pg_tid = $order->pg_tid;
			$u->paid_date = date('YmdHis');
			$u->from_status = array('pending');
			$o1 = executeQuery('ddggshop.updateOrderPaymentGuarded', $u);
			if (!$o1->toBool()) throw new Exception();
			if ($oDB->getAffectedRows() < 1)
			{
				$oDB->rollback();
				return new BaseObject(0, '이미 결제완료 처리된 주문입니다.');
			}

			// 포인트 적립 (재고는 주문 생성 시 이미 차감됨)
			if ($order->member_srl)
			{
				$items = ddggshopModel::getOrderItemList($order_srl);
				$total_point = 0;
				foreach ($items as $oi) $total_point += (int)$oi->point_amount;
				if ($total_point > 0)
				{
					getController('point')->setPoint((int)$order->member_srl, $total_point, 'plus', 'ddggshop_order');
				}
			}
			$oDB->commit();
		}
		catch (Exception $e)
		{
			$oDB->rollback();
			return new BaseObject(-1, '처리 중 오류가 발생했습니다.');
		}

		ddggshopModel::logOrder($order_srl, 'deposit_confirmed', 'pending', 'paid', '입금확인');
		// 구매 후 회원 그룹 변경 (콘텐츠 상품 등)
		ddggshopModel::applyItemGroupGrants($order);
		if ($order->member_srl)
		{
			ddggshopModel::notifyMember((int)$order->member_srl, '💳 입금이 확인되어 결제완료 처리되었습니다. 주문 ' . $order->order_code, ddggshopModel::memberOrderUrl($order->order_code), 'paid');
		}
		$this->setMessage('입금이 확인되어 결제완료 처리되었습니다.');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminOrderView', 'order_srl', $order_srl));
	}

	/** 교환/반품 신청 처리: 승인(approve) / 반려(reject) / 완료(complete) */
	public function procDdggshopAdminProcessClaim()
	{
		$claim_srl = (int)Context::get('claim_srl');
		$action = (string)Context::get('claim_action');
		$claim = ddggshopModel::getClaim($claim_srl);
		if (!$claim) return new BaseObject(-1, '신청을 찾을 수 없습니다.');
		$order = ddggshopModel::getOrder($claim->order_srl);
		$memo = trim((string)Context::get('admin_memo'));

		$u = new stdClass;
		$u->claim_srl = $claim_srl;
		$u->admin_memo = $memo;
		$u->refund_amount = (int)$claim->refund_amount;
		$u->processed_date = date('YmdHis');

		if ($action === 'approve')
		{
			$u->status = 'approved';
			ddggshopModel::logOrder((int)$claim->order_srl, 'claim_approve', '', '', ddggshopModel::claimTypeLabel($claim->type) . ' 승인');
			$msg = '신청을 승인했습니다.';
		}
		elseif ($action === 'reject')
		{
			$u->status = 'rejected';
			ddggshopModel::logOrder((int)$claim->order_srl, 'claim_reject', '', '', $memo ?: ddggshopModel::claimTypeLabel($claim->type) . ' 반려');
			$msg = '신청을 반려했습니다.';
		}
		elseif ($action === 'complete')
		{
			$u->status = 'completed';
			// 반품 완료 → 전체 환불(취소 파이프라인 재사용)
			if ($claim->type === 'return' && $order && !in_array($order->status, array('cancelled', 'refunded'), true))
			{
				$ref = ddggshopModel::cancelOrder($order, 'refunded', '반품 환불 (' . $claim->order_code . ')');
				if (!$ref->toBool()) return $ref;
				$u->refund_amount = (int)$order->payment_price;
			}
			ddggshopModel::logOrder((int)$claim->order_srl, 'claim_complete', '', '', ddggshopModel::claimTypeLabel($claim->type) . ' 완료');
			$msg = ddggshopModel::claimTypeLabel($claim->type) . ' 처리를 완료했습니다.';
		}
		else
		{
			return new BaseObject(-1, '잘못된 동작입니다.');
		}

		$out = executeQuery('ddggshop.updateClaim', $u);
		if (!$out->toBool()) return $out;

		if ($claim->member_srl)
		{
			ddggshopModel::notifyMember((int)$claim->member_srl, '🔁 ' . ddggshopModel::claimTypeLabel($claim->type) . ' 신청이 ‘' . ddggshopModel::claimStatusLabel($u->status) . '’ 처리되었습니다. (주문 ' . $claim->order_code . ')', ddggshopModel::memberOrderUrl($claim->order_code));
		}
		$this->setMessage($msg);
	}

	/* ===================== 설정 ===================== */

	/** 설정 저장 (하위탭 sub별로 해당 키만 갱신, 나머지는 보존) */
	public function procDdggshopAdminSaveConfig()
	{
		$config = ModuleModel::getModuleConfig('ddggshop');
		if (!is_object($config)) $config = new stdClass;
		$sub = (string)Context::get('sub');

		if ($sub === 'payment')
		{
			$config->use_toss = (Context::get('use_toss') === 'Y') ? 'Y' : 'N';
			$config->toss_client_key = trim((string)Context::get('toss_client_key'));
			$config->toss_secret_key = trim((string)Context::get('toss_secret_key'));
			$config->use_banktransfer = (Context::get('use_banktransfer') === 'Y') ? 'Y' : 'N';
			$config->bank_info = trim((string)Context::get('bank_info'));
			$config->use_point_full = (Context::get('use_point_full') === 'Y') ? 'Y' : 'N';
		}
		elseif ($sub === 'delivery')
		{
			$config->delivery_company = trim((string)Context::get('delivery_company'));
			$config->delivery_fee_type = (Context::get('delivery_fee_type') === 'free') ? 'free' : 'paid';
			$config->base_delivery_fee = max(0, (int)Context::get('base_delivery_fee'));
			$config->free_threshold = max(0, (int)Context::get('free_threshold'));
			$config->island_fee = max(0, (int)Context::get('island_fee'));
			$config->delivery_info = Context::get('delivery_info');
			$config->exchange_return = Context::get('exchange_return');
		}
		elseif ($sub === 'order')
		{
			$ap = (string)Context::get('address_policy');
			$config->address_policy = in_array($ap, array('auto', 'always', 'never'), true) ? $ap : 'auto';
			$config->default_require_address = (Context::get('default_require_address') === 'N') ? 'N' : 'Y';
			$config->show_delivery_memo = (Context::get('show_delivery_memo') === 'Y') ? 'Y' : 'N';
			$config->address_input_mode = (Context::get('address_input_mode') === 'manual') ? 'manual' : 'search';
		}
		elseif ($sub === 'access')
		{
			$vis = (string)Context::get('price_visibility');
			$config->price_visibility = in_array($vis, array('all', 'member', 'group'), true) ? $vis : 'all';
			$config->price_scope = (Context::get('price_scope') === 'category') ? 'category' : 'whole';

			$groups = Context::get('price_groups');
			if (!is_array($groups)) $groups = $groups ? array($groups) : array();
			$config->price_groups = array_values(array_filter(array_map('intval', $groups)));

			$cats = Context::get('price_categories');
			if (!is_array($cats)) $cats = $cats ? array($cats) : array();
			$config->price_categories = array_values(array_filter(array_map('intval', $cats)));

			$config->price_hidden_text = trim((string)Context::get('price_hidden_text')) ?: '로그인 후 가격 확인';
			$config->price_denied_text = trim((string)Context::get('price_denied_text'));

			// 작성 권한 (리뷰 / 문의)
			$rp = (string)Context::get('review_perm');
			$config->review_perm = in_array($rp, array('buyer', 'member', 'group'), true) ? $rp : 'member';
			$rg = Context::get('review_groups');
			if (!is_array($rg)) $rg = $rg ? array($rg) : array();
			$config->review_groups = array_values(array_filter(array_map('intval', $rg)));

			$qp = (string)Context::get('qna_perm');
			$config->qna_perm = in_array($qp, array('member', 'group'), true) ? $qp : 'member';
			$qg = Context::get('qna_groups');
			if (!is_array($qg)) $qg = $qg ? array($qg) : array();
			$config->qna_groups = array_values(array_filter(array_map('intval', $qg)));
		}
		elseif ($sub === 'layout')
		{
			$mode = (string)Context::get('layout_mode');
			$config->layout_mode = ($mode === 'embedded') ? 'embedded' : 'standalone';
			$config->show_recent_widget = (Context::get('show_recent_widget') === 'Y') ? 'Y' : 'N';
			$rcount = (int)Context::get('recent_widget_count');
			if ($rcount < 1) $rcount = 5;
			if ($rcount > 30) $rcount = 30;
			$config->recent_widget_count = $rcount;
		}
		elseif ($sub === 'notify')
		{
			$config->use_order_notify = (Context::get('use_order_notify') === 'Y') ? 'Y' : 'N';
			$posted = Context::get('notify_events');
			if (!is_array($posted)) $posted = array();
			$events = array();
			foreach (array_keys(ddggshopModel::notifyEventList()) as $ek)
			{
				$events[$ek] = (isset($posted[$ek]) && $posted[$ek] === 'Y') ? 'Y' : 'N';
			}
			$config->notify_events = $events;
		}
		elseif ($sub === 'basic')
		{
			$config->shop_name = trim((string)Context::get('shop_name')) ?: '쇼핑몰';

			// 기본(primary) 색상
			$pc = trim((string)Context::get('primary_color'));
			if (strlen($pc) === 4 && preg_match('/^#[0-9a-fA-F]{3}$/', $pc))
			{
				$pc = '#' . $pc[1] . $pc[1] . $pc[2] . $pc[2] . $pc[3] . $pc[3];
			}
			$config->primary_color = preg_match('/^#[0-9a-fA-F]{6}$/', $pc) ? strtolower($pc) : '#ff4d2d';

			// 로고 이미지 (업로드 우선, 없으면 URL 유지, 제거 체크 시 삭제)
			$logo = trim((string)Context::get('logo_image'));
			$lf = isset($_FILES['logo_file']) ? $_FILES['logo_file'] : null;
			if ($lf && !empty($lf['tmp_name']) && empty($lf['error']))
			{
				$up = $this->saveUploadedImage($lf, 'logo_' . date('YmdHis'));
				if ($up === '') return new BaseObject(-1, '로고 이미지 저장에 실패했습니다. (이미지 파일만 가능)');
				$logo = $up;
			}
			if (Context::get('remove_logo') === 'Y') $logo = '';
			$config->logo_image = $logo;

			// 다국어(i18n)
			$config->use_multilang = (Context::get('use_multilang') === 'Y') ? 'Y' : 'N';
			$available = ddggshopModel::getAvailableLanguages();
			$en = Context::get('enabled_langs');
			if (!is_array($en)) $en = $en ? array($en) : array();
			$en = array_values(array_filter(array_map('strval', $en), function ($c) use ($available) { return isset($available[strtolower($c)]); }));
			if (!count($en)) $en = isset($available['ko']) ? array('ko') : array_slice(array_keys($available), 0, 1);
			$config->enabled_langs = $en;
			$dl = strtolower(trim((string)Context::get('default_lang')));
			$config->default_lang = isset($available[$dl]) ? $dl : (isset($available['ko']) ? 'ko' : (string)key($available));

			// 법적 필수정보 (전자상거래법 사업자 표시사항)
			$config->company_name    = trim((string)Context::get('company_name'));
			$config->ceo_name        = trim((string)Context::get('ceo_name'));
			$config->biz_address     = trim((string)Context::get('biz_address'));
			$config->biz_phone       = trim((string)Context::get('biz_phone'));
			$config->biz_reg_no      = trim((string)Context::get('biz_reg_no'));
			$config->privacy_officer = trim((string)Context::get('privacy_officer'));
			$config->mailorder_no    = trim((string)Context::get('mailorder_no'));
			$config->terms_url       = trim((string)Context::get('terms_url'));
			$config->privacy_url     = trim((string)Context::get('privacy_url'));
			$config->bizinfo_url     = trim((string)Context::get('bizinfo_url'));
		}
		else // etc
		{
			$sub = 'etc';
			$config->point_rate = max(0, (int)Context::get('point_rate'));
			$config->use_point = (Context::get('use_point') === 'Y') ? 'Y' : 'N';
			// 성장 기능
			$config->use_wishlist = (Context::get('use_wishlist') === 'N') ? 'N' : 'Y';
			$config->use_recent = (Context::get('use_recent') === 'N') ? 'N' : 'Y';
			$config->use_related = (Context::get('use_related') === 'N') ? 'N' : 'Y';
			$config->use_review_point = (Context::get('use_review_point') === 'Y') ? 'Y' : 'N';
			$config->review_point = max(0, (int)Context::get('review_point'));
			$config->photo_review_point = max(0, (int)Context::get('photo_review_point'));
			// 등급(회원그룹)별 적립률
			$gr = Context::get('grade_rate');
			$rates = array();
			if (is_array($gr))
			{
				foreach ($gr as $gsrl => $rv) { $rv = (int)$rv; if ($rv > 0) $rates[(int)$gsrl] = $rv; }
			}
			$config->grade_rates = $rates;
			// 등급(회원그룹)별 가격 할인 (할인율 % 또는 할인금액 원)
			$gdt = Context::get('grade_disc_type');
			$gdv = Context::get('grade_disc_value');
			$discs = array();
			if (is_array($gdv))
			{
				foreach ($gdv as $gsrl => $vv)
				{
					$vv = (int)$vv;
					if ($vv <= 0) continue;
					$tp = (is_array($gdt) && isset($gdt[$gsrl]) && $gdt[$gsrl] === 'amount') ? 'amount' : 'percent';
					if ($tp === 'percent') $vv = min(100, $vv);
					$discs[(int)$gsrl] = array('type' => $tp, 'value' => $vv);
				}
			}
			$config->grade_discounts = $discs;
			// 알림
			$config->notify_admin_qna = (Context::get('notify_admin_qna') === 'Y') ? 'Y' : 'N';
			$config->notify_member_answer = (Context::get('notify_member_answer') === 'Y') ? 'Y' : 'N';
			$config->notify_soldout = (Context::get('notify_soldout') === 'Y') ? 'Y' : 'N';
			// 상품목록 가격대 필터
			$bmin = Context::get('band_min');
			$bmax = Context::get('band_max');
			$blabel = Context::get('band_label');
			$bands = array();
			if (is_array($blabel))
			{
				foreach ($blabel as $i => $lab)
				{
					$lab = trim((string) $lab);
					if ($lab === '') continue;
					$mn = isset($bmin[$i]) ? max(0, (int) preg_replace('/[^0-9]/', '', (string) $bmin[$i])) : 0;
					$mx = isset($bmax[$i]) ? max(0, (int) preg_replace('/[^0-9]/', '', (string) $bmax[$i])) : 0;
					if ($mn > 0 && $mx > 0 && $mn > $mx) { $t = $mn; $mn = $mx; $mx = $t; }
					$bands[] = array('min' => $mn, 'max' => $mx, 'label' => $lab);
				}
			}
			$config->price_bands = $bands;
		}

		$output = getController('module')->insertModuleConfig('ddggshop', $config);
		if (!$output->toBool()) return $output;

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminConfig', 'sub', $sub));
	}

	/* ===================== 태그 ===================== */

	public function procDdggshopAdminSaveTag()
	{
		$tag_srl = (int)Context::get('tag_srl');
		$title = trim((string)Context::get('title'));
		if ($title === '')
		{
			return new BaseObject(-1, '태그명을 입력해 주세요.');
		}
		$auto = Context::get('auto_source');
		if (!in_array($auto, array('manual', 'popular', 'new'), true)) $auto = 'manual';

		$args = new stdClass;
		$args->title = $title;
		$args->auto_source = $auto;
		$args->badge_color = trim((string)Context::get('badge_color'));
		$args->show_on_home = (Context::get('show_on_home') === 'N') ? 'N' : 'Y';
		$args->show_badge = (Context::get('show_badge') === 'N') ? 'N' : 'Y';
		$args->home_count = max(1, (int)Context::get('home_count'));
		$args->list_order = (int)Context::get('list_order');
		$args->status = (Context::get('status') === 'N') ? 'N' : 'Y';

		if ($tag_srl && ddggshopModel::getTag($tag_srl))
		{
			$args->tag_srl = $tag_srl;
			$output = executeQuery('ddggshop.updateTag', $args);
		}
		else
		{
			$args->tag_srl = getNextSequence();
			if (!$args->list_order) $args->list_order = $args->tag_srl;
			$output = executeQuery('ddggshop.insertTag', $args);
		}
		if (!$output->toBool()) return $output;
		$this->setMessage('success_registed');
	}

	public function procDdggshopAdminDeleteTag()
	{
		$tag_srl = (int)Context::get('tag_srl');
		executeQuery('ddggshop.deleteItemTagsByTag', (object) array('tag_srl' => $tag_srl));
		$output = executeQuery('ddggshop.deleteTag', (object) array('tag_srl' => $tag_srl));
		if (!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
	}

	/* ===================== 배너 ===================== */

	public function procDdggshopAdminSaveBanner()
	{
		$banner_srl = (int)Context::get('banner_srl');
		$existing = $banner_srl ? ddggshopModel::getBanner($banner_srl) : null;
		$is_edit = (bool)$existing;
		if (!$is_edit) $banner_srl = getNextSequence();

		$image = trim((string)Context::get('image'));

		// 이미지 파일 업로드 처리 (있으면 우선)
		$file = isset($_FILES['banner_image']) ? $_FILES['banner_image'] : null;
		if ($file && !empty($file['tmp_name']) && empty($file['error']))
		{
			$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
			if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true))
			{
				return new BaseObject(-1, '이미지 파일(jpg, png, gif, webp)만 업로드할 수 있습니다.');
			}
			$dir = RX_BASEDIR . 'files/attach/ddggshop/banner';
			FileHandler::makeDir($dir);
			$filename = $banner_srl . '_' . date('YmdHis') . '.' . $ext;
			$target = $dir . '/' . $filename;
			if (!Rhymix\Framework\Storage::moveUploadedFile($file['tmp_name'], $target, 'move'))
			{
				return new BaseObject(-1, '이미지 저장에 실패했습니다.');
			}
			$image = RX_BASEURL . 'files/attach/ddggshop/banner/' . $filename;
		}

		// 수정인데 새 이미지가 없으면 기존 이미지 유지
		if ($is_edit && $image === '') $image = $existing->image;
		if ($image === '')
		{
			return new BaseObject(-1, '배너 이미지를 업로드하거나 URL을 입력해 주세요.');
		}

		$align = (string)Context::get('text_align');
		$valign = (string)Context::get('text_valign');
		$args = new stdClass;
		$args->banner_srl = $banner_srl;
		$args->title = trim((string)Context::get('title'));
		$args->content = trim((string)Context::get('content'));
		$args->image = $image;
		$args->link = trim((string)Context::get('link'));
		$args->link_target = (Context::get('link_target') === 'blank') ? 'blank' : 'self';
		$args->text_align = in_array($align, array('left', 'center', 'right'), true) ? $align : 'left';
		$args->text_valign = in_array($valign, array('top', 'middle', 'bottom'), true) ? $valign : 'middle';
		$args->title_color = trim((string)Context::get('title_color')) ?: '#ffffff';
		$args->content_color = trim((string)Context::get('content_color')) ?: '#ffffff';
		$args->title_size = min(120, max(10, (int)Context::get('title_size') ?: 30));
		$args->content_size = min(80, max(8, (int)Context::get('content_size') ?: 16));
		$args->font_family = trim((string)Context::get('font_family'));
		$args->overlay = (Context::get('overlay') === 'Y') ? 'Y' : 'N';
		$args->list_order = (int)Context::get('list_order') ?: $banner_srl;
		$args->status = (Context::get('status') === 'N') ? 'N' : 'Y';
		$output = $is_edit ? executeQuery('ddggshop.updateBanner', $args) : executeQuery('ddggshop.insertBanner', $args);
		if (!$output->toBool()) return $output;

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDdggshopAdminConfig', 'sub', 'home'));
	}

	public function procDdggshopAdminDeleteBanner()
	{
		$banner_srl = (int)Context::get('banner_srl');
		$output = executeQuery('ddggshop.deleteBanner', (object) array('banner_srl' => $banner_srl));
		if (!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
	}

	/* ===================== 상품 문의 ===================== */

	public function procDdggshopAdminAnswerQna()
	{
		$qna_srl = (int)Context::get('qna_srl');
		$answer = trim((string)Context::get('answer'));
		$u = new stdClass;
		$u->qna_srl = $qna_srl;
		$u->answer = $answer;
		$u->answered_date = ($answer !== '') ? date('YmdHis') : '';
		$output = executeQuery('ddggshop.updateQnaAnswer', $u);
		if (!$output->toBool()) return $output;

		// 문의 작성 회원에게 답변 알림
		if ($answer !== '')
		{
			$cfg = ddggshopModel::getConfig();
			$qna = ddggshopModel::getQna($qna_srl);
			if ($qna && (int)$qna->member_srl > 0 && ($cfg->notify_member_answer ?? 'Y') === 'Y')
			{
				$item = ddggshopModel::getItem((int)$qna->item_srl);
				$item_name = $item ? $item->item_name : '상품';
				$url = ddggshopModel::productUrl((int)$qna->item_srl);
				$msg = '[' . $cfg->shop_name . '] 상품문의에 답변이 등록되었습니다: ' . $item_name;
				ddggshopModel::sendShopNotify((int)$qna->member_srl, $msg, $url, 0);
			}
		}

		$this->setMessage('success_updated');
	}

	public function procDdggshopAdminDeleteQna()
	{
		$qna_srl = (int)Context::get('qna_srl');
		$output = executeQuery('ddggshop.deleteQna', (object) array('qna_srl' => $qna_srl));
		if (!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
	}
}
