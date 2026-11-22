<?php

namespace Zittme\Modules\Zittme_pay\Models;

/**
 * 모듈 설정.
 *
 * 결제 설정은 게시판처럼 인스턴스마다 달라질 물건이 아니다. PG 계약은 사이트 단위로 맺으므로
 * 인스턴스별 extra_vars 가 아니라 모듈 전역 설정 하나에 담고, 관리자 탭들이 그것을 나눠 쓴다.
 */
class Config
{
	/**
	 * 설정 기본값.
	 *
	 * 여기 없는 키를 관리자 화면이나 결제 화면에서 읽으면 최초 설치 직후 undefined 가 된다.
	 * 새 설정을 추가할 때는 반드시 이 표에 기본값을 같이 넣을 것.
	 */
	public const DEFAULTS = [
		
		'enabled' => 'Y',
		
		'test_mode' => 'Y',
		'currency' => 'KRW',
		
		'extra_currencies' => [],
		
		'order_prefix' => 'ZP',

		
		
		'enabled_gateways' => ['banktransfer'],
		
		'gateway_labels' => [],
		'toss_client_key' => '',
		'toss_secret_key' => '',
		
		
		'inicis_mid' => '',
		'inicis_sign_key' => '',
		'inicis_api_key' => '',
		
		'kcp_site_cd' => '',
		'kcp_cert_info' => '',
		'kcp_priv_key' => '',
		'kcp_priv_pass' => '',
		
		'nicepay_client_id' => '',
		'nicepay_secret_key' => '',
		
		'portone_store_id' => '',
		'portone_channel_key' => '',
		'portone_api_secret' => '',
		'portone_pay_method' => 'CARD',
		'paypal_client_id' => '',
		'paypal_secret' => '',
		
		'paypal_currency' => 'USD',
		
		'paypal_exchange_rate' => '',
		
		'conekta_private_key' => '',
		'conekta_webhook_secret' => '',
		'conekta_currency' => 'MXN',
		
		'conekta_methods' => ['card', 'cash', 'bank_transfer'],
		'conekta_allow_krw' => 'N',

		
		'exchange_rates' => [],
		
		'exchange_rates_manual' => [],
		'exchange_auto' => 'N',
		
		'exchange_source' => 'erapi',
		'exchange_api_key' => '',
		'exchange_updated' => '',
		'exchange_attempted' => '',
		
		'bank_accounts' => [],
		
		'bank_due_days' => 3,

		
		'allow_partial_cancel' => 'Y',
		
		'cancel_reasons' => '',
		
		
		'auto_cancel_days' => 0,
		
		
		'allow_force_cancel' => 'Y',

		
		'notify_admin_email' => '',
		'notify_on_paid' => 'N',
		'notify_on_cancel' => 'N',

		
		
		'log_retention_days' => 1095,
		
		'webhook_ip_whitelist' => '',

		
		'biz_notice' => '',
	];

	/**
	 * @var ?object
	 */
	protected static $_cache = null;

	/**
	 * 설정을 읽는다. 빠진 키는 기본값으로 채워 돌려준다.
	 *
	 * @return object
	 */
	public static function getConfig(): object
	{
		if (self::$_cache === null)
		{
			$config = \ModuleModel::getModuleConfig('zittme_pay');
			if (!is_object($config))
			{
				$config = new \stdClass;
			}
			foreach (self::DEFAULTS as $key => $value)
			{
				if (!isset($config->{$key}))
				{
					$config->{$key} = $value;
				}
			}

			
			foreach (['enabled_gateways', 'gateway_labels', 'bank_accounts', 'exchange_rates', 'exchange_rates_manual', 'extra_currencies', 'conekta_methods'] as $key)
			{
				if (!is_array($config->{$key}))
				{
					$config->{$key} = [];
				}
			}

			self::$_cache = $config;
		}
		return self::$_cache;
	}

	/**
	 * 설정을 저장한다.
	 *
	 * @param object $config
	 * @return object
	 */
	public static function setConfig(object $config): object
	{
		$output = \ModuleController::getInstance()->insertModuleConfig('zittme_pay', $config);
		if ($output->toBool())
		{
			self::$_cache = null;
		}
		return $output;
	}

	/**
	 * 캐시를 버린다. 설정을 저장한 직후 다시 읽어야 할 때 쓴다.
	 *
	 * @return void
	 */
	public static function clearCache(): void
	{
		self::$_cache = null;
	}

	/**
	 * 취소 사유 목록.
	 *
	 * @return array
	 */
	public static function getCancelReasons(): array
	{
		$raw = (string)self::getConfig()->cancel_reasons;
		return array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $raw)), 'strlen'));
	}

	/**
	 * 웹훅을 보낸 IP 가 허용 대상인가.
	 *
	 * 화이트리스트가 비어 있으면 IP 로 막지 않는다. IP 는 보조 수단일 뿐이고, 진짜 방어선은
	 * "웹훅 본문을 믿지 않고 PG 에 재조회한다" 는 쪽이다. 끝에 * 를 붙여 대역을 쓸 수 있다.
	 *
	 * @param string $ipaddress
	 * @return bool
	 */
	public static function isAllowedWebhookIP(string $ipaddress): bool
	{
		$raw = (string)self::getConfig()->webhook_ip_whitelist;
		$list = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)), 'strlen');
		if (!count($list))
		{
			return true;
		}
		foreach ($list as $pattern)
		{
			if ($pattern === $ipaddress)
			{
				return true;
			}
			if (str_ends_with($pattern, '*') && str_starts_with($ipaddress, substr($pattern, 0, -1)))
			{
				return true;
			}
		}
		return false;
	}
}
