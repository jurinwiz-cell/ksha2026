<?php
/**
 * 쇼핑몰 (DDGG Shop)
 * 상품·카테고리·장바구니·주문·결제를 갖춘 단일 쇼핑몰 모듈.
 * @author 딱따고기 (https://ddgglabs.org)
 */
class ddggshop extends ModuleObject
{
	/** 이 모듈이 생성/관리하는 테이블 목록 */
	protected static $tables = array(
		'ddggshop_item',
		'ddggshop_category',
		'ddggshop_cart',
		'ddggshop_order',
		'ddggshop_order_item',
		'ddggshop_order_address',
		'ddggshop_payment_log',
		'ddggshop_tag',
		'ddggshop_item_tag',
		'ddggshop_banner',
		'ddggshop_address',
		'ddggshop_review',
		'ddggshop_qna',
		'ddggshop_item_option',
		'ddggshop_shipfee',
		'ddggshop_coupon',
		'ddggshop_coupon_log',
		'ddggshop_coupon_issue',
		'ddggshop_wishlist',
		'ddggshop_home_block',
		'ddggshop_claim',
		'ddggshop_order_log',
	);

	/** ddggshop_item 에 추가된 컬럼 (마이그레이션용) */
	protected static $item_columns = array(
		'brand'        => array('varchar', 100),
		'manufacturer' => array('varchar', 100),
		'origin'       => array('varchar', 100),
		'attributes'   => array('text', null),
		'has_options'  => array('char', 1),
		'option_groups' => array('text', null),
		'ship_fee_type' => array('varchar', 10),
		'ship_fee'      => array('number', null),
		'require_address' => array('char', 1, 'Y', true),
		'buy_limit'    => array('number', null),
		'grant_group'  => array('char', 1),
		'grant_add_groups' => array('varchar', 255),
		'grant_del_groups' => array('varchar', 255),
	);

	/** 기타 테이블에 추가된 컬럼 (마이그레이션용) */
	protected static $extra_columns = array(
		'ddggshop_order_item' => array('option_srl' => array('bignumber', null), 'item_code' => array('varchar', 100)),
		'ddggshop_category'   => array('name_i18n' => array('text', null)),
		'ddggshop_cart'       => array('option_label' => array('varchar', 250)),
		'ddggshop_order'      => array('coupon_code' => array('varchar', 40)),
		'ddggshop_coupon'     => array('auto_signup' => array('char', 1)),
		'ddggshop_review'     => array('images' => array('text', null), 'point_given' => array('char', 1)),
		'ddggshop_banner'     => array(
			'link_target' => array('char', 6),
			'content' => array('varchar', 500),
			'text_align' => array('char', 10),
			'text_valign' => array('char', 10),
			'title_color' => array('varchar', 20),
			'content_color' => array('varchar', 20),
			'title_size' => array('number', 4),
			'content_size' => array('number', 4),
			'font_family' => array('varchar', 60),
			'overlay' => array('char', 1),
		),
	);

	/** 설치 시 호출 */
	public function moduleInstall()
	{
		return new BaseObject();
	}

	/** 업데이트 필요 여부 (테이블 미생성 / 트리거 미등록 시 true) */
	public function checkUpdate()
	{
		$oDB = DB::getInstance();
		foreach (self::$tables as $table)
		{
			if (!$oDB->isTableExists($table)) return true;
		}
		if ($oDB->isTableExists('ddggshop_item'))
		{
			foreach (self::$item_columns as $col => $def)
			{
				if (!$oDB->isColumnExists('ddggshop_item', $col)) return true;
			}
		}
		foreach (self::$extra_columns as $table => $cols)
		{
			if (!$oDB->isTableExists($table)) continue;
			foreach ($cols as $col => $def)
			{
				if (!$oDB->isColumnExists($table, $col)) return true;
			}
		}
		if (!ModuleModel::getTrigger('menu.getModuleListInSitemap', 'ddggshop', 'controller', 'triggerMenuModuleList', 'after')) return true;
		if (!ModuleModel::getTrigger('member.insertMember', 'ddggshop', 'controller', 'triggerDdggshopMemberJoin', 'after')) return true;
		// 주문번호 UNIQUE 제약(중복 주문번호 방지)
		if ($oDB->isTableExists('ddggshop_order') && !$oDB->isIndexExists('ddggshop_order', 'unique_order_code')) return true;
		return false;
	}

	/** 업데이트 실행 (스키마로 테이블 생성 + 기본 태그 시드) */
	public function moduleUpdate()
	{
		$oDB = DB::getInstance();
		foreach (self::$tables as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				$oDB->createTableByXmlFile($this->module_path . 'schemas/' . $table . '.xml');
				if ($table === 'ddggshop_tag')
				{
					$this->seedDefaultTags();
				}
				if ($table === 'ddggshop_home_block')
				{
					$this->seedDefaultHomeBlocks();
				}
			}
		}
		// 상품 테이블 신규 컬럼 추가
		if ($oDB->isTableExists('ddggshop_item'))
		{
			foreach (self::$item_columns as $col => $def)
			{
				if (!$oDB->isColumnExists('ddggshop_item', $col))
				{
					$oDB->addColumn('ddggshop_item', $col, $def[0], $def[1], isset($def[2]) ? $def[2] : null, isset($def[3]) ? $def[3] : false);
				}
			}
		}
		// 기타 테이블 신규 컬럼 추가
		foreach (self::$extra_columns as $table => $cols)
		{
			if (!$oDB->isTableExists($table)) continue;
			foreach ($cols as $col => $def)
			{
				if (!$oDB->isColumnExists($table, $col))
				{
					$oDB->addColumn($table, $col, $def[0], $def[1]);
				}
			}
		}
		// 주문번호 UNIQUE 인덱스 추가(기존 설치 마이그레이션)
		if ($oDB->isTableExists('ddggshop_order') && !$oDB->isIndexExists('ddggshop_order', 'unique_order_code'))
		{
			$oDB->addIndex('ddggshop_order', 'unique_order_code', array('order_code'), true);
		}
		return new BaseObject();
	}

	/** 기본 태그 4종 시드 (추천/신상품/인기상품/할인상품) */
	protected function seedDefaultTags()
	{
		$defaults = array(
			array('추천', 'manual', '#ff4d2d'),
			array('신상품', 'manual', '#1a9c54'),
			array('인기상품', 'popular', '#2c5cdc'),
			array('할인상품', 'manual', '#c83a3a'),
		);
		$order = 1;
		foreach ($defaults as $d)
		{
			$args = new stdClass;
			$args->tag_srl = getNextSequence();
			$args->title = $d[0];
			$args->auto_source = $d[1];
			$args->badge_color = $d[2];
			$args->show_on_home = 'Y';
			$args->show_badge = 'Y';
			$args->home_count = 8;
			$args->list_order = $order++;
			$args->status = 'Y';
			executeQuery('ddggshop.insertTag', $args);
		}
	}

	/** 기본 홈 블록 시드 (신상품/베스트셀러/추천 — 태그 설정 없이 바로 채워지는 규칙형) */
	protected function seedDefaultHomeBlocks()
	{
		$defaults = array(
			array('신상품', 'new'),
			array('베스트셀러', 'best'),
			array('추천 상품', 'recommend'),
		);
		$order = 1;
		foreach ($defaults as $d)
		{
			$args = new stdClass;
			$args->block_srl = getNextSequence();
			$args->block_type = 'products';
			$args->title = $d[0];
			$args->source_type = $d[1];
			$args->source_value = '';
			$args->list_count = 8;
			$args->layout = 'grid';
			$args->list_order = $order++;
			$args->status = 'Y';
			$args->regdate = date('YmdHis');
			executeQuery('ddggshop.insertHomeBlock', $args);
		}
	}

	public function recompileCache()
	{
	}
}
