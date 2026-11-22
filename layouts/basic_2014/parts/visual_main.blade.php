{{-- Original design/markup: Copyright (C) NAVER <http://www.navercorp.com> (XE Contest 2014 Template A / Basic) --}}
{{-- Rhymix layout port: Copyright (C) YJSoft --}}
{{-- This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. --}}
@php
	$_visual_base = \RX_BASEURL . ltrim($layout_info->path ?? '', './');
	$_visual_image_1 = ($layout_info->VISUAL_IMAGE_1 ?? '') ?: $_visual_base.'img/imgslider_01.jpg';
	$_visual_title_1 = ($layout_info->VISUAL_TITLE_1 ?? '') ?: 'SHARING, PUBLISHING. & PLEASURE.';
	$_visual_desc_1 = ($layout_info->VISUAL_DESC_1 ?? '') ?: '지식을 나누고 컨텐츠를 출판하며 즐거움을 함께합니다.';
	$_visual_image_2 = ($layout_info->VISUAL_IMAGE_2 ?? '') ?: $_visual_base.'img/imgslider_02.jpg';
	$_visual_title_2 = ($layout_info->VISUAL_TITLE_2 ?? '') ?: 'MAKING WEB CULTURES';
	$_visual_desc_2 = ($layout_info->VISUAL_DESC_2 ?? '') ?: '올바른 웹 문화를 지향합니다.';
	$_visual_image_3 = ($layout_info->VISUAL_IMAGE_3 ?? '') ?: $_visual_base.'img/imgslider_03.jpg';
	$_visual_title_3 = ($layout_info->VISUAL_TITLE_3 ?? '') ?: 'EVOLUTION & INNOVATION TOGETHER';
	$_visual_desc_3 = ($layout_info->VISUAL_DESC_3 ?? '') ?: '함께 진화하고 혁신을 추구합니다.';
	$_visual_image_4 = ($layout_info->VISUAL_IMAGE_4 ?? '') ?: $_visual_base.'img/imgslider_04.jpg';
	$_visual_title_4 = ($layout_info->VISUAL_TITLE_4 ?? '') ?: 'CREATE A GOOD DESIGN WITH THE POSSIBILITY OF TECHNOLOGY';
	$_visual_desc_4 = ($layout_info->VISUAL_DESC_4 ?? '') ?: '기술의 가능성을 발굴하고 좋은 디자인을 만들어 갑니다.';
@endphp
<load target="../css/camera.css" />
<load target="../js/jquery.easing.1.3.js" type="body" />
<load target="../js/camera.js" type="body" />
<div class="visual main">
	<div class="camera_wrap">
		<div data-src="{{ $_visual_image_1 }}">
			<div class="camera_caption fadeIn">
				<h1>{{ $_visual_title_1 }}</h1>
				<p>{{ $_visual_desc_1 }}</p>
			</div>
		</div>
		<div data-src="{{ $_visual_image_2 }}">
			<div class="camera_caption fadeIn">
				<h1>{{ $_visual_title_2 }}</h1>
				<p>{{ $_visual_desc_2 }}</p>
			</div>
		</div>
		<div data-src="{{ $_visual_image_3 }}">
			<div class="camera_caption fadeIn">
				<h1>{{ $_visual_title_3 }}</h1>
				<p>{{ $_visual_desc_3 }}</p>
			</div>
		</div>
		<div data-src="{{ $_visual_image_4 }}">
			<div class="camera_caption fadeIn">
				<h1>{{ $_visual_title_4 }}</h1>
				<p>{{ $_visual_desc_4 }}</p>
			</div>
		</div>
	</div>
</div>
<script>
jQuery(function($){
	$('.camera_wrap').camera({
		height: '478px',
		pagination: true,
		thumbnails: false,
		playPause: false,
		loader: 'none',
		fx: 'simpleFade',
		time: 3000
	});
});
</script>
