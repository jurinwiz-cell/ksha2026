{{-- Original design/markup: Copyright (C) NAVER <http://www.navercorp.com> (XE Contest 2014 Template A / Basic) --}}
{{-- Rhymix layout port: Copyright (C) YJSoft --}}
{{-- This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. --}}
@php
	$_lnb_root = null;
	foreach (($GNB->list ?? []) as $_lnb_top) {
		if (!empty($_lnb_top['selected'])) {
			$_lnb_root = $_lnb_top;
			break;
		}
	}
	$_lnb_items = $_lnb_root['list'] ?? [];
@endphp
<nav class="lnb">
	@if(($layout_info->LNB_TOP_IMAGE_USE ?? 'N') == 'Y' && !empty($layout_info->LNB_TOP_IMAGE))
	<div class="top_thumb">
		<img src="{{ $layout_info->LNB_TOP_IMAGE }}" alt="">
	</div>
	@endif
	<ul>
	@foreach($_lnb_items as $_lnb_item)
		@if(!empty($_lnb_item['list']))
		<li class="divide{{ !empty($_lnb_item['selected']) ? ' active' : '' }}">
			<span>{{ $_lnb_item['link'] }}</span>
			<ul>
			@foreach($_lnb_item['list'] as $_lnb_child)
				<li class="{{ !empty($_lnb_child['selected']) ? 'active' : '' }}"><a href="{{ $_lnb_child['href'] }}">{{ $_lnb_child['link'] }}</a></li>
			@endforeach
			</ul>
		</li>
		@else
		<li class="{{ !empty($_lnb_item['selected']) ? 'active' : '' }}"><a href="{{ $_lnb_item['href'] }}">{{ $_lnb_item['link'] }}</a></li>
		@endif
	@endforeach
	</ul>
</nav>
