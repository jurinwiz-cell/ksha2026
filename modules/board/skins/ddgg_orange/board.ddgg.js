/* 딱따고기연구소 게시판 스킨 — 보조 스크립트 */
jQuery(function ($) {
	// 댓글/게시글 본문 첫 요소 위쪽 여백 제거
	$('.ddgg-board .c-body.rhymix_content > *:first-child').css('margin-top', 0);
	$('.ddgg-board .post-body > *:first-child').css('margin-top', 0);

	// 모바일에서는 페이지 번호를 현재 페이지 주변 5개만 노출
	function limitPager() {
		var mobile = window.matchMedia('(max-width: 680px)').matches;
		$('.ddgg-board .ddgg-pager').each(function () {
			var $p = $(this);
			var $nums = $p.children('a:not(.pg-edge), strong'); // 숫자 링크 + 현재(active)
			if (!mobile) { $nums.css('display', ''); return; }
			var ai = $nums.index($p.children('strong').first());
			if (ai < 0) ai = 0;
			var start = ai - 2, end = ai + 2;
			if (start < 0) { end += -start; start = 0; }
			if (end > $nums.length - 1) { start -= (end - ($nums.length - 1)); end = $nums.length - 1; }
			if (start < 0) start = 0;
			$nums.each(function (i) {
				$(this).css('display', (i >= start && i <= end) ? '' : 'none');
			});
		});
	}
	limitPager();
	var t;
	$(window).on('resize', function () { clearTimeout(t); t = setTimeout(limitPager, 150); });
});
