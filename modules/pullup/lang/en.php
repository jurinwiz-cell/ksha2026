<?php

$lang->pullup = 'Document Pull-up';
$lang->cmd_pullup = 'Pull up';
$lang->cmd_pullup_config = 'Configuration';
$lang->cmd_pullup_list = 'Pull-up Log';

$lang->pullup_point = 'Points to deduct';
$lang->pullup_point_help = 'Points deducted per pull-up. Set to 0 to bump after a simple confirmation without deducting points.';
$lang->pullup_target_modules = 'Target boards';
$lang->pullup_target_modules_help = 'Select the boards where the pull-up feature is available.';
$lang->pullup_min_days_after_write = 'Days after writing';
$lang->pullup_min_days_after_write_help = 'A document can be bumped only after this many days have passed since it was written. 0 means no restriction.';
$lang->pullup_min_days_after_bump = 'Days between pull-ups';
$lang->pullup_min_days_after_bump_help = 'A document can be bumped again only after this many days since the last pull-up. 0 means no restriction.';
$lang->pullup_update_regdate = 'Update published date';
$lang->pullup_update_regdate_on = 'Update';
$lang->pullup_update_regdate_off = 'Keep';
$lang->pullup_update_regdate_help = 'When pulling up, set the document\'s published date (regdate) to the current time. With "Update", a bumped document appears as if it was just written.';
$lang->unit_day = 'd';
$lang->unit_hour = 'h';
$lang->unit_minute = 'm';

$lang->pullup_log_document = 'Document';
$lang->pullup_log_member = 'Member';
$lang->pullup_log_point = 'Points';
$lang->pullup_log_date = 'Date';
$lang->pullup_no_log = 'No pull-up history.';
$lang->pullup_no_board = 'No boards found. Please create a board first.';

$lang->msg_pullup_confirm = 'Do you want to pull up this document?';
$lang->msg_pullup_confirm_point = 'Deduct %s points and pull up this document?';

$lang->success_pullup = 'The document has been pulled up.';

$lang->msg_invalid_request = 'Invalid request.';
$lang->msg_not_logged = 'Login is required.';
$lang->msg_pullup_not_target = 'The pull-up feature is not available on this board.';
$lang->msg_pullup_not_permitted = 'Only the author or a manager can pull up this document.';
$lang->msg_pullup_too_early_write = 'You cannot pull up yet. Available in %s (at %s).';
$lang->msg_pullup_too_early_bump = 'You cannot pull up yet. Available %s after the last pull-up (at %s).';
$lang->msg_pullup_not_enough_point = 'You do not have enough points to pull up.';
