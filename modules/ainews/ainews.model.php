<?php
/**
 * @class  ainewsModel
 * @author 관리자 (admin@example.com)
 * @brief  ainews module model class.
 */
class ainewsModel extends ainews
{
	/**
	 * @brief 초기화
	 */
	public function init()
	{
	}

	/**
	 * @brief 기사 목록 가져오기
	 */
	public function getArticles($args = null)
	{
		$output = executeQueryArray('ainews.getArticles', $args);
		return $output;
	}

	/**
	 * @brief 뉴스레터 목록 가져오기
	 */
	public function getNewsletters($args = null)
	{
		$output = executeQueryArray('ainews.getNewsletters', $args);
		return $output;
	}
}
