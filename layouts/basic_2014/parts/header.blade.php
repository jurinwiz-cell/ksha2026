{{-- Original design/markup: Copyright (C) NAVER <http://www.navercorp.com> (XE Contest 2014 Template A / Basic) --}}
{{-- Rhymix layout port: Copyright (C) YJSoft --}}
{{-- This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. --}}
@php
    $member_config = MemberModel::getMemberConfig();
    $profileImage = null;
    if ($is_logged && ($member_config->profile_image ?? 'N') == 'Y') {
        $profileImage = MemberModel::getProfileImage($logged_info->member_srl);
    }
    $login_modal_use = ($layout_info->LOGIN_MODAL_USE ?? 'N') == 'Y';
@endphp
<div class="container fixed_header">
	<p class="skip"><a href="#content">{{ $lang->skip_to_content ?? '메뉴 건너뛰기' }}</a></p>
	<div class="header_wrap">
		<header class="header">
			<h1>
				<a href="{{ !empty($layout_info->LOGO_URL) ? $layout_info->LOGO_URL : getUrl('') }}" id="siteTitle">
					@if(!empty($layout_info->LOGO_IMG))
					<img src="{{ $layout_info->LOGO_IMG }}" alt="{{ ($layout_info->LOGO_TEXT ?? '') ?: Context::getSiteTitle() }}">
					@elseif(!empty($layout_info->LOGO_TEXT))
					{{ $layout_info->LOGO_TEXT }}
					@else
					{{ Context::getSiteTitle() }}
					@endif
				</a>
			</h1>
			<div class="side">
				<ul>
					<li class="click">
						<a href="#"><i class="xe-magnifier"></i><span class="blind">{{ $lang->cmd_search }}</span></a>
						<div class="ly ly_search">
							<form action="{{ getUrl() }}" method="get" class="search" no-error-return-url="true">
								<input type="hidden" name="mid" value="{{ $mid ?? '' }}">
								<input type="hidden" name="act" value="IS">
								<input type="text" name="is_keyword" value="{{ $is_keyword ?? '' }}" required placeholder="{{ $lang->cmd_search }}" title="{{ $lang->cmd_search }}">
								<input type="submit" value="{{ $lang->cmd_search }}">
							</form>
							<span class="edge"></span>
						</div>
					</li>
					@guest
					<li class="hover">
						<a href="{{ $login_modal_use ? '#' : getUrl('', 'act', 'dispMemberLoginForm') }}"{!! $login_modal_use ? ' class="btn_login_modal"' : '' !!}><i class="xe-user"></i><span class="blind">{{ $lang->cmd_login }}/{{ $lang->cmd_signup }}</span></a>
						<div class="ly ly_login">
							<ul>
								<li><a href="{{ $login_modal_use ? '#' : getUrl('', 'act', 'dispMemberLoginForm') }}"{!! $login_modal_use ? ' class="btn_login_modal"' : '' !!}>{{ $lang->cmd_login }}</a></li>
								<li><a href="{{ getUrl('', 'act', 'dispMemberSignUpForm') }}">{{ $lang->cmd_signup }}</a></li>
							</ul>
							<span class="edge"></span>
						</div>
					</li>
					@endguest
					@auth('member')
					<li class="hover">
						<a href="{{ getUrl('', 'act', 'dispMemberInfo') }}" class="login_after">
							@if($profileImage)
							<img src="{{ $profileImage->src }}" alt="{{ $logged_info->nick_name }}님 프로필">
							@else
							<img src="../img/ico_default.jpg" alt="{{ $logged_info->nick_name }}님 프로필">
							@endif
						</a>
						<div class="ly ly_login">
							<ul>
								@foreach ($logged_info->menu_list as $key => $val)
								<li><a href="{{ getUrl('act', $key) }}">{{ $val }}</a></li>
								@endforeach
								@if(($logged_info->is_admin ?? 'N') == 'Y')
								<li><a href="{{ getUrl('', 'module', 'admin') }}">{{ $lang->cmd_management }}</a></li>
								@endif
								<li class="logout"><a href="{{ getUrl('', 'act', 'dispMemberLogout') }}">{{ $lang->cmd_logout }}</a></li>
							</ul>
							<span class="edge"></span>
						</div>
					</li>
					@endauth
				</ul>
			</div>
			<!-- GNB -->
			<nav class="gnb" id="gnb">
				<ul>
				@foreach(($GNB->list ?? []) as $key1 => $val1)
					<li class="{{ ($val1['selected'] ?? false) ? 'active' : '' }}{{ !empty($val1['class']) ? ' '.$val1['class'] : '' }}">
						<a href="{{ $val1['href'] }}" target="_blank"|if="($val1['open_window'] ?? 'N') == 'Y'">{{ $val1['link'] }}</a>
						@if(!empty($val1['list']))
						<ul class="depth2">
						@foreach($val1['list'] as $key2 => $val2)
							@if(!empty($val2['list']))
							<li class="divide{{ ($val2['selected'] ?? false) ? ' active' : '' }}">
								<span>{{ $val2['link'] }}</span>
								<ul class="depth3">
								@foreach($val2['list'] as $key3 => $val3)
									<li class="{{ ($val3['selected'] ?? false) ? 'active' : '' }}"><a href="{{ $val3['href'] }}" target="_blank"|if="($val3['open_window'] ?? 'N') == 'Y'">{{ $val3['link'] }}</a></li>
								@endforeach
								</ul>
							</li>
							@else
							<li class="{{ ($val2['selected'] ?? false) ? 'active' : '' }}"><a href="{{ $val2['href'] }}" target="_blank"|if="($val2['open_window'] ?? 'N') == 'Y'">{{ $val2['link'] }}</a></li>
							@endif
						@endforeach
						</ul>
						@endif
					</li>
				@endforeach
				@if(($layout_info->COLORSET_DEMO_USE ?? 'N') == 'Y')
					<li class="colorset_demo">
						<a href="#">{{ $lang->colorset_demo ?? '컬러테마' }}</a>
						<ul class="depth2 colorset_list">
						@php
							$_colorset_current = $layout_info->colorset ?? '';
							$_colorset_choices = [
								'' => 'Royal Classic',
								'color_grape_fruit' => 'Grape Fruit',
								'color_hot_pink' => 'Hot Pink',
								'color_orchid' => 'Orchid',
								'color_blue_jeans' => 'Blue Jeans',
								'color_mint' => 'Mint',
								'color_sunflower' => 'Sunflower',
								'color_bitter_sweet' => 'Bitter Sweet',
								'color_java_coffee' => 'Java Coffee',
								'color_gray' => 'Gray',
							];
						@endphp
						@foreach($_colorset_choices as $_colorset_value => $_colorset_label)
							<li class="{{ $_colorset_current === $_colorset_value ? 'active' : '' }}"><a href="#" data-colorset="{{ $_colorset_value }}">{{ $_colorset_label }}</a></li>
						@endforeach
						</ul>
					</li>
				@endif
				</ul>
			</nav>
			<!-- /GNB -->
		</header>
	</div>
