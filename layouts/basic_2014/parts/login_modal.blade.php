{{-- Original design/markup: Copyright (C) NAVER <http://www.navercorp.com> (XE Contest 2014 Template A / Basic) --}}
{{-- Rhymix layout port: Copyright (C) YJSoft --}}
{{-- This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. --}}
@guest
<load target="../css/login_widget.css" />
@php
	$member_config = MemberModel::getMemberConfig();
	$_login_validator_id = 'layouts/basic_2014/parts/login_modal/1';
@endphp
<div class="login_widget">
	<div class="ly_dimmed"></div>
	<div class="signin">
		<div class="login-header">
			<h1>
				@if(!($layout_info->LOGIN_MODAL_LOGO_IMG ?? ''))
				<img src="../img/img_hlogin.png" width="70" height="70" alt="">
				@else
				<img src="{{ $layout_info->LOGIN_MODAL_LOGO_IMG }}" width="70" height="70" alt="">
				@endif
				<span class="blind">{{ $lang->cmd_login }}</span>
			</h1>
		</div>
		<div class="login-body">
			<form id="fo_login_widget" action="{{ getUrl('', 'act', 'procMemberLogin') }}" method="post">
				<fieldset>
				<legend class="blind">{{ $lang->cmd_login }}</legend>
					<input type="hidden" name="act" value="procMemberLogin">
					<input type="hidden" name="success_return_url" value="{{ getRequestUriByServerEnviroment() }}">
					<input type="hidden" name="xe_validator_id" value="{{ $_login_validator_id }}">
					@if(!empty($XE_VALIDATOR_MESSAGE) && ($XE_VALIDATOR_ID ?? '') == $_login_validator_id)
					<div class="control-group">
						<p class="error">{{ $XE_VALIDATOR_MESSAGE }}</p>
					</div>
					@endif
					<div class="control-group">
						@if(($member_config->identifier ?? '') == 'email_address')
						<input type="email" name="user_id" id="user_id" required placeholder="{{ $lang->email_address }}" title="{{ $lang->email_address }}">
						@else
						<input type="text" name="user_id" id="user_id" required placeholder="{{ $lang->user_id }}" title="{{ $lang->user_id }}">
						@endif
						<input type="password" name="password" id="user_pw" required placeholder="{{ $lang->password }}" title="{{ $lang->password }}">
					</div>
					<div class="control-group">
						<label for="keep_signed"><input type="checkbox" name="keep_signed" id="keep_signed" value="Y">{{ $lang->keep_signed }}</label>
						<div id="warning">
							<p>{{ $lang->about_keep_warning }}</p>
						</div>
					</div>
					<div class="control-group">
						{!! \SpamfilterModel::getCaptcha('login') !!}
						<input type="submit" value="{{ $lang->cmd_login }}" class="btn_submit">
					</div>
				</fieldset>
			</form>
			@if(class_exists('Rhymix\\Modules\\Sociallogin\\Models\\Config') && \Rhymix\Modules\Sociallogin\Models\Config::isEnabled())
			<div class="login-social">
				<p>or</p>
				@load('^/modules/sociallogin/skins/default/css/default.css')
				{!! \Rhymix\Modules\Sociallogin\Models\User::getSocialloginButtons() !!}
			</div>
			@endif
		</div>
		<div class="login-footer">
			<a href="{{ getUrl('', 'act', 'dispMemberFindAccount') }}">{{ $lang->cmd_find_member_account }}</a>
			<span class="bar">|</span>
			<a href="{{ getUrl('', 'act', 'dispMemberSignUpForm') }}">{{ $lang->cmd_signup }}</a>
		</div>
		<a href="#" class="btn_close"><i class="xe-close"></i><span class="blind">닫기</span></a>
	</div>
</div>
<script>
jQuery(function($){
	var $modal = $('.login_widget');
	var $warning = $modal.find('#warning');
	$warning.hide();
	$modal.find('#keep_signed').change(function(){
		if ($(this).is(':checked')) {
			$warning.slideDown(200);
		} else {
			$warning.slideUp(200);
		}
	});

	function openLoginModal(e) {
		e.preventDefault();
		$modal.fadeIn(200);
	}
	function closeLoginModal(e) {
		if (e) { e.preventDefault(); }
		$modal.hide();
	}

	$(document).on('click', '.btn_login_modal', openLoginModal);
	$modal.find('.btn_close, .ly_dimmed').on('click', closeLoginModal);
});
</script>
@endguest
