/**
 * @version 1.0
 * @date April 27, 2011
 * @author Elkha (elkha1914@hotmail.com)
 * @copyright (c) 2011 Elkha (http://elkha.kr/)
 **/

/*
	$("#gnb").navi({
		show : { opacity: "show", height: "show"},
		hide : { height: "hide"},
		delay : 100
	});
 */

(function($){
	$.fn.navi = function(args){

		var elkha = jQuery.extend({
			show : { height: "show" },
			hide : { opacity: "hide" },
			speed: "fast",
			delay: 0,
			click: false
		}, args);

		// ieSelect
		var ieSelect = false;
		if($.browser.msie == true) ieSelect = parseInt($.browser.version);

		this.each(function(){
			var gnb = $(this);

			gnb.find("li:has(ul)").hover(function(){
				$(this).addClass("hover");
				if(elkha.click && $(this).children("a").attr("href")=="#") return;
				if(elkha.speed==0 || (ieSelect && ieSelect <= 6)) $(this).children("ul,div").show();
				else $(this).children("ul:not(:animated),div:not(:animated)").animate(elkha.show, elkha.speed);
			}, function(){
				$(this).removeClass("hover");
				if(elkha.click && $(this).children("a").attr("href")=="#") return;
				var hover = $(this);
				setTimeout(function(){
					if(hover.hasClass("hover")) return;
					if(elkha.speed==0 || (ieSelect && ieSelect <= 6)) hover.children("ul,div").hide();
					else hover.children("ul,div").animate(elkha.hide, elkha.speed);
				}, elkha.delay);
			});
			gnb.find("a").focus(function(){
				$(this).parent("li").prev("li").find("ul").hide();
				$(this).parent("li").next("li").find("ul").hide();
				if(elkha.click && $(this).attr("href")=="#") return;
				$(this).next("ul,div").show();
			});
/*
			$("*:not(#gnb a)").focus(function(){
				if(ieSelect) if($(this).parents("#gnb").attr("id")=="gnb") return; // 익스는 *:not 선택이 안되나 ㅜㅜ
				gnb.find("li > ul").hide();
			});
*/
			gnb.find("a").click(function(){
				if($(this).attr("href")=="#") {
					if(elkha.click) {
						if( $(this).next("ul,div").css("display")=="block" ) $(this).next("ul,div").hide();
						else $(this).next("ul,div").show();
					}
					return false;
				}
			});

		});

		return this;
	};

	window.GS_AD_BOOKMARK = function()
	{
		if (typeof(window.sidebar)=='object' && typeof(window.sidebar.addPanel)=='function')
			// Mozilla Firefox Bookmark
			window.sidebar.addPanel(document.title,window.location.href,'');
		else if(window.external && ('AddFavorite' in window.external))
			// IE Favorite
			window.external.AddFavorite(location.href,document.title);
		else if(window.opera && window.print)
			// Opera Hotlist
			this.title=document.title;
		else
			// webkit - safari/chrome
			alert('즐겨찾기에 추가하시려면 ' + (navigator.userAgent.toLowerCase().indexOf('mac') != - 1 ? 'Command/Cmd' : 'CTRL') + ' + D 버튼을 눌러주세요.');
		return false;
	}
	window.GS_LOGIN_AJAX = function(f)
	{
		var $form = $(f);
		$.exec_json('member.procMemberLogin', {
			user_id: $form.find('[name=user_id]').val()
			,password: $form.find('[name=password]').val()
		}, function(p){
			if(p.error) alert(p.message);
			else location.reload();
		});
		return false;
	}

})(jQuery);


jQuery(function($){
	var config = { delay: 100 };
	var $gnb = $('#gnb');
	if(typeof($gnb.attr('data-ani'))=='string' && $gnb.attr('data-ani')=='0')
	{
		config.speed = 0;
	}
	$gnb.navi(config);
	$gnb.find('li li:has(ul) > a').addClass('sub');

	$("#header .language .on button").click(function(){
		$("#header .language .ul").toggleClass("ul_on");
	});
	$('#column .ext .t a[href=#], #footer .map a[href=#]').click(function(){
		return false;
	});

});

;(function($){
	var $containerLayer,
		$contentLayer,
		$wrap;
	var toggleCookie = function(cookie_key)
	{
		var cookie_value = $.cookie(cookie_key);

		// [보이기] 값이 1인 경우
		if(typeof(cookie_value)=='string' && cookie_value=='1')
		{
			$.cookie(cookie_key, '0', { 'path': '/' });
			$contentLayer.html(configContainerLayer.html);
			$containerLayer.removeClass('containerLayerClose');

			if(!configContainerLayer.cookie) $wrap.removeClass('wrapStyleDefault');
			else $wrap.addClass('wrapContainerLayer');
		}
		// [숨기기] 값이 0 경우 1로
		else
		{
			$.cookie(cookie_key, '1', { 'path': '/' });
			$contentLayer.html('');
			$containerLayer.addClass('containerLayerClose');

			if(!configContainerLayer.cookie) $wrap.addClass('wrapStyleDefault');
			else $wrap.removeClass('wrapContainerLayer');
		}
	}
	window.toggleContainerLayer = function()
	{
		if(typeof($.cookie)=='function')
		{
			toggleCookie('toggleContainerLayer');
		}
		else
		{
			/*
			var $script = $('<script src="'+default_url.replace(/^[^\/]+\/\/[^\/]+(\/.*)$/,'$1')+'layouts/elkha_graystyle/js/jquery-cookie/jquery.cookie.js"></script>');
			$script.load(function(){
				toggleCookie('toggleContainerLayer');
			});
			$script.appendTo( $('head') );
			*/
			$.cachedScript( default_url.replace(/^[^\/]+\/\/[^\/]+(\/.*)$/,'$1')+'layouts/elkha_graystyle/js/jquery-cookie/jquery.cookie.js' )
				.done(function() {
				toggleCookie('toggleContainerLayer');
			});
		}
	}

	;if(typeof(jQuery.cachedScript)!='function') jQuery.cachedScript = function( url, options ) {

	  // Allow user to set any option except for dataType, cache, and url
	  options = $.extend( options || {}, {
		dataType: "script",
		cache: true,
		url: url
	  });

	  // Use $.ajax() since it is more flexible than $.getScript
	  // Return the jqXHR object so we can chain callbacks
	  return jQuery.ajax( options );
	};
	$(function(){
		if(typeof(configContainerLayer)=='object')
		{
			$containerLayer = $('.containerLayer');
			$contentLayer = $containerLayer.find('.contentLayer');
			$wrap = $('#wrap');
			// http://api.jquery.com/jquery.getscript/

			if(configContainerLayer.cookie)
			{
				// 숨기기
				//$contentLayer.html('');
			}
			else
			{
				// 보이기
				$contentLayer.html(configContainerLayer.html);
			}
		}
	});
})(jQuery);