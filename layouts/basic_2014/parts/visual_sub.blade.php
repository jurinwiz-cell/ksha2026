{{-- Original design/markup: Copyright (C) NAVER <http://www.navercorp.com> (XE Contest 2014 Template A / Basic) --}}
{{-- Rhymix layout port: Copyright (C) YJSoft --}}
{{-- This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. --}}
@php
	$_bc_items = [];
	$_bc_nodes = $GNB->list ?? [];
	while (!empty($_bc_nodes)) {
		$_bc_found = null;
		foreach ($_bc_nodes as $_bc_candidate) {
			if (!empty($_bc_candidate['selected'])) {
				$_bc_found = $_bc_candidate;
				break;
			}
		}
		if (!$_bc_found) {
			break;
		}
		$_bc_items[] = $_bc_found;
		$_bc_nodes = $_bc_found['list'] ?? [];
	}
	$_bc_title = $module_info->browser_title ?? '';
	if (empty($_bc_title) && !empty($_bc_items)) {
		$_bc_title = end($_bc_items)['link'] ?? '';
	}
	if (empty($_bc_title)) {
		$_bc_title = Context::getSiteTitle();
	}
@endphp
<div class="visual sub{{ ($layout_info->SUB_VISUAL_BG_USE ?? 'N') == 'Y' ? ' sub_img' : '' }}">
	{{-- 라이믹스의 상대경로 자동 변환(_convertRelativePaths)은 템플릿 소스에 그대로 적힌 리터럴 경로만 컴파일 시점에 바꿔준다 - {{ }} 표현식(삼항연산자 폴백 포함)의 런타임 결과는 절대 건드리지 않는다. 그래서 기본값은 반드시 별도 분기의 리터럴 문자열로 둬야 한다(RHYMIX_LAYOUT_AUTHORING_GUIDE.md 4.1절). --}}
	@if(!empty($layout_info->SUB_VISUAL_BG_IMAGE))
	<span class="bg_img" style="background-image:url('{{ $layout_info->SUB_VISUAL_BG_IMAGE }}')"></span>
	@else
	<span class="bg_img" style="background-image:url('../img/subtitle.png')"></span>
	@endif
	<span class="mask"></span>
	<div class="sub_title">
		<h1>{{ $_bc_title }}</h1>
		<div class="page_location">
			<p>You are here:</p>
			<ul>
				<li><a href="{{ getUrl('') }}">Home</a></li>
				@foreach($_bc_items as $_bc_item)
				<li><a href="{{ $_bc_item['href'] }}">{{ $_bc_item['link'] }}</a></li>
				@endforeach
			</ul>
		</div>
	</div>
</div>
