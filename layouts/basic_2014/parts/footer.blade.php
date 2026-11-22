{{-- Original design/markup: Copyright (C) NAVER <http://www.navercorp.com> (XE Contest 2014 Template A / Basic) --}}
{{-- Rhymix layout port: Copyright (C) YJSoft --}}
{{-- This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. --}}
<div class="footer_wrap">
	<footer class="footer">
		<div class="f_info">
			<p class="f_logo">
				<a href="{{ !empty($layout_info->FOOTER_LOGO_URL) ? $layout_info->FOOTER_LOGO_URL : getUrl('') }}">
				@if(!empty($layout_info->FOOTER_LOGO_IMG))
					<img src="{{ $layout_info->FOOTER_LOGO_IMG }}" alt="{{ ($layout_info->FOOTER_LOGO_TEXT ?? '') ?: Context::getSiteTitle() }}">
				@elseif(!empty($layout_info->FOOTER_LOGO_TEXT))
					{{ $layout_info->FOOTER_LOGO_TEXT }}
				@else
					{{ Context::getSiteTitle() }}
				@endif
				</a>
			</p>
			<ul class="lst_sns">
			@if(!empty($layout_info->SNS_FACEBOOK_URL))
				<li><a href="{{ $layout_info->SNS_FACEBOOK_URL }}" target="_blank"><i class="xe-facebook"></i><span class="blind">Facebook</span></a></li>
			@endif
			@if(!empty($layout_info->SNS_TWITTER_URL))
				<li><a href="{{ $layout_info->SNS_TWITTER_URL }}" target="_blank"><i class="xe-twitter"></i><span class="blind">Twitter</span></a></li>
			@endif
			@if(!empty($layout_info->SNS_LINKEDIN_URL))
				<li><a href="{{ $layout_info->SNS_LINKEDIN_URL }}" target="_blank"><i class="xe-linkedin"></i><span class="blind">LinkedIn</span></a></li>
			@endif
			@if(!empty($layout_info->SNS_GITHUB_URL))
				<li><a href="{{ $layout_info->SNS_GITHUB_URL }}" target="_blank"><i class="xe-github"></i><span class="blind">GitHub</span></a></li>
			@endif
			@if(!empty($layout_info->SNS_RSS_URL))
				<li><a href="{{ $layout_info->SNS_RSS_URL }}" target="_blank"><i class="xe-social-rssfeed"></i><span class="blind">RSS Feed</span></a></li>
			@endif
			@if(!empty($layout_info->SNS_YOUTUBE_URL))
				<li><a href="{{ $layout_info->SNS_YOUTUBE_URL }}" target="_blank"><i class="xe-youtube"></i><span class="blind">YouTube</span></a></li>
			@endif
			</ul>
			@if(!empty($layout_info->FOOTER_SITE_NAME))
			<p class="site_name">
				{{ $layout_info->FOOTER_SITE_NAME }}
				@if(!empty($layout_info->FOOTER_SITE_URL))
				<br><a href="{{ $layout_info->FOOTER_SITE_URL }}">{{ $layout_info->FOOTER_SITE_URL }}</a>
				@endif
			</p>
			@endif
			@if(!empty($layout_info->FOOTER_COPYRIGHT))
			<p class="copyright">{!! $layout_info->FOOTER_COPYRIGHT !!}</p>
			@endif
		</div>
		<div class="f_info2">
			<div class="site_map">
				<ul>
				@foreach(($FOOTER_MENU->list ?? []) as $_footer_top)
					<li>
						<a href="{{ $_footer_top['href'] }}">{{ $_footer_top['link'] }}</a>
						@if(!empty($_footer_top['list']))
						<ul>
						@foreach($_footer_top['list'] as $_footer_child)
							<li><a href="{{ $_footer_child['href'] }}">{{ $_footer_child['link'] }}</a></li>
						@endforeach
						</ul>
						@endif
					</li>
				@endforeach
				</ul>
			</div>
			<div class="f_more_info">
				<ul>
				@foreach(($FOOTER_BOTTOM->list ?? []) as $_footer_bottom_item)
					<li><a href="{{ $_footer_bottom_item['href'] }}">{{ $_footer_bottom_item['link'] }}</a></li>
				@endforeach
				</ul>
			</div>
		</div>
	</footer>
</div>
