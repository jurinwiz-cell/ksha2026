<?php
/**
 * 포인트(적립금) 전액결제 게이트웨이
 * 외부 PG 없이 보유 적립금만으로 결제한다. 결제금액(payment_price)은 0원이며,
 * 사용 적립금(used_point)이 주문 총액 전체를 충당한 경우에만 사용된다.
 * 주문은 즉시 결제완료(paid)로 확정된다.
 * @author 딱따고기 (https://ddgglabs.org)
 */
class PointGateway extends BaseGateway
{
	public function getName()
	{
		return 'point';
	}

	public function getClientScript()
	{
		return '';
	}

	/** 클라이언트 결제창 불필요 (서버에서 즉시 확정) */
	public function requiresClientPayment()
	{
		return false;
	}

	public function buildRequest($order)
	{
		return array(
			'gateway' => 'point',
			'amount'  => (int)$order->payment_price,
		);
	}

	/** 포인트 결제는 즉시 결제완료로 확정 */
	public function approve($order, $params)
	{
		return $this->result(true, 'paid', '', null);
	}

	public function cancel($order, $reason)
	{
		return $this->result(true, '취소되었습니다.', '', null);
	}
}
