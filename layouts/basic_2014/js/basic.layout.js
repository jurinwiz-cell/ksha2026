/*
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
 */
(function($){
    $(function(){
        var $shrinkHeaderHeight = 72;
        var $fixedHeader = $('.fixed_header .header_wrap');
        var $gnb = $('.gnb');
        var $loginEl = $('.hover');
        var $searchEl = $('.click > a');

        // Fixed header
        $(window).scroll(function() {
        var scroll = getCurrentScroll();
            if( scroll >= $shrinkHeaderHeight ) {
               $fixedHeader.addClass('shrink');
            }
            else {
               $fixedHeader.removeClass('shrink');
            }
        });

        function getCurrentScroll() {
            return window.pageYOffset || document.documentElement.scrollTop;
        }

        // Gnb
        $gnb.find('>ul>li>ul').hide();
        $gnb.find('>ul>li>a')
        .mouseover(function(){
            $gnb.find('>ul>li>ul:visible').hide().parent('li').removeClass('on')
            $(this).next('ul:hidden').stop().fadeIn(200).parent('li').addClass('on')
        })
        .focus(function(){
            $(this).mouseover();
        })
        .end()
        .mouseleave(function(){
            $gnb.find('>ul>li>ul').hide().parent().removeClass('on')
        });

        // login popup
        $loginEl.hover(
            function(){
                $(this).find('.ly').stop().fadeIn(200);
            },
            function(){
                $(this).find('.ly').hide();
            }
        );

        // Search input popup
        $searchEl.click(function(){
            $(this).parent().find('.ly').toggle();
            return false;
        });

        // Colorset switcher (demo)
        var colorsetSwitcher = function() {
            var $colorsetLinks = $('.colorset_demo .colorset_list a');
            if (!$colorsetLinks.length) {
                return;
            }
            var COOKIE_NAME = 'rx_layout_colorset';
            var COOKIE_EXPIRE_DAYS = 365;

            $colorsetLinks.click(function(){
                var color = $(this).data('colorset') || '';
                var $body = $('body');

                // Rhymix mixes other classes into <body> (login state, mobile, etc.),
                // so only strip a previously applied color_* theme class instead of clearing everything.
                $body.removeClass(function(index, className){
                    var matched = className.match(/(^|\s)color_\S+/g);
                    return matched ? matched.join(' ') : '';
                });
                if (color) {
                    $body.addClass(color);
                }

                if (window.Cookies) {
                    Cookies.set(COOKIE_NAME, color, { expires: COOKIE_EXPIRE_DAYS, path: '/' });
                } else {
                    var expireDate = new Date();
                    expireDate.setTime(expireDate.getTime() + (COOKIE_EXPIRE_DAYS * 24 * 60 * 60 * 1000));
                    document.cookie = COOKIE_NAME + '=' + encodeURIComponent(color) + ';expires=' + expireDate.toUTCString() + ';path=/';
                }

                $colorsetLinks.parent('li').removeClass('active');
                $(this).parent('li').addClass('active');

                return false;
            });
        };
        colorsetSwitcher();

        // Scroll to top
        var scrollToTop = function() {
            var link = $('.btn_top');
            var windowW = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;

            $(window).scroll(function() {
                if (($(this).scrollTop() > 150) && (windowW > 1000)) {
                    link.fadeIn(100);
                } else {
                    link.fadeOut(100);
                }
            });

            link.click(function() {
                $('html, body').animate({scrollTop: 0}, 400);
                return false;
            });
        };
        scrollToTop();

    })
})(jQuery);
