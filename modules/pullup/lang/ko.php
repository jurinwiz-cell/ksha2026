<?php

$lang->pullup = '게시글 끌어올리기';
$lang->cmd_pullup = '끌어올리기';
$lang->cmd_pullup_config = '환경설정';
$lang->cmd_pullup_list = '끌어올리기 기록';

$lang->pullup_point = '차감 포인트';
$lang->pullup_point_help = '끌어올리기 1회당 차감할 포인트입니다. 0으로 설정하면 포인트 차감 없이 확인 후 끌어올립니다.';
$lang->pullup_target_modules = '적용 게시판';
$lang->pullup_target_modules_help = '끌어올리기 기능을 사용할 게시판을 선택하세요.';
$lang->pullup_min_days_after_write = '작성 후 제한일수';
$lang->pullup_min_days_after_write_help = '게시글 작성 후 설정한 일수가 지나야 끌어올리기가 가능합니다. 0이면 제한이 없습니다.';
$lang->pullup_min_days_after_bump = '재끌어올리기 제한일수';
$lang->pullup_min_days_after_bump_help = '마지막 끌어올리기 후 설정한 일수가 지나야 다시 끌어올리기가 가능합니다. 0이면 제한이 없습니다.';
$lang->pullup_update_regdate = '게시일자 갱신';
$lang->pullup_update_regdate_on = '갱신함';
$lang->pullup_update_regdate_off = '갱신 안 함';
$lang->pullup_update_regdate_help = '끌어올릴 때 게시글의 작성일자(게시일)를 현재 시각으로 갱신합니다. "갱신함"이면 끌어올린 글이 방금 작성된 것처럼 표시됩니다.';
$lang->unit_day = '일';
$lang->unit_hour = '시간';
$lang->unit_minute = '분';

$lang->pullup_log_document = '게시글';
$lang->pullup_log_member = '끌어올린 회원';
$lang->pullup_log_point = '차감 포인트';
$lang->pullup_log_date = '끌어올린 일시';
$lang->pullup_no_log = '끌어올리기 기록이 없습니다.';
$lang->pullup_no_board = '게시판이 없습니다. 먼저 게시판을 생성하세요.';

$lang->msg_pullup_confirm = '이 게시글을 끌어올리시겠습니까?';
$lang->msg_pullup_confirm_point = '%s 포인트를 차감하고 이 게시글을 끌어올리시겠습니까?';

$lang->success_pullup = '게시글을 끌어올렸습니다.';

$lang->msg_invalid_request = '잘못된 요청입니다.';
$lang->msg_not_logged = '로그인이 필요합니다.';
$lang->msg_pullup_not_target = '끌어올리기를 사용할 수 없는 게시판입니다.';
$lang->msg_pullup_not_permitted = '작성자 또는 관리자만 끌어올릴 수 있습니다.';
$lang->msg_pullup_too_early_write = '아직 끌어올릴 수 없습니다. %s 후에 끌어올릴 수 있습니다. (가능 시각: %s)';
$lang->msg_pullup_too_early_bump = '아직 끌어올릴 수 없습니다. 마지막 끌어올리기로부터 %s 후에 끌어올릴 수 있습니다. (가능 시각: %s)';
$lang->msg_pullup_not_enough_point = '포인트가 부족하여 끌어올릴 수 없습니다.';
