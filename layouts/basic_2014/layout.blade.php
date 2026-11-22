<!--
 * Original design/markup: Copyright (C) NAVER <http://www.navercorp.com>
 * (XE Contest 2014 Template A / Basic)
 *
 * Rhymix layout port: Copyright (C) YJSoft
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
-->
@include('parts/head')
@include('parts/header')

@if(($layout_info->PAGE_TYPE ?? 'SUB') == 'MAIN')
@include('parts/visual_main')
@else
@include('parts/visual_sub')
@endif

<div class="body {{ ($layout_info->PAGE_TYPE ?? 'SUB') == 'MAIN' ? 'main' : 'sub' }}{{ ($layout_info->COLUMN_TYPE ?? 'FULL') == 'RIGHT' ? ' right' : '' }}{{ ($layout_info->COLUMN_TYPE ?? 'FULL') == 'FULL' ? ' no_lnb' : '' }}">
@if(($layout_info->COLUMN_TYPE ?? 'FULL') != 'FULL')
@include('parts/lnb')
@endif
<div class="content" id="content">
{!! $content !!}
</div>
</div>
</div>

@include('parts/footer')

<a href="#" class="btn_top"><i class="xe-angle-up"><span class="blind">위로가기</span></i></a>

@if(($layout_info->LOGIN_MODAL_USE ?? 'N') == 'Y')
@include('parts/login_modal')
@endif
