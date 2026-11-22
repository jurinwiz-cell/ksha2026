<?php
/**
 * 무통장입금 게이트웨이
 * 서버 승인이 없으며, 주문은 입금대기(pending) 상태로 남고
 * 관리자가 입금 확인 시 결제완료(paid)로 전환한다.
 * @author 딱따고기 (https://ddgglabs.org)
 */
class BanktransferGateway extends BaseGateway
{
	public function getName()
	{
		return 'banktransfer';
	}

	public function getClientScript()
	{
		return '';
	}

	public function requiresClientPayment()
	{
		return false;
	}

	public function buildRequest($order)
	{
		return array(
			'gateway'   => 'banktransfer',
			'bank_info' => (string)$this->config->bank_info,
			'amount'    => (int)$order->payment_price,
		);
	}

	/** 무통장입금은 즉시 승인하지 않는다 (입금대기 유지) */
	public function approve($order, $params)
	{
		return $this->result(true, 'pending', '', null);
	}

	public function cancel($order, $reason)
	{
		return $this->result(true, '취소되었습니다.', '', null);
	}
}
