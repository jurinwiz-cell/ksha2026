;(function($){
	var overlayScrollbars = window.OverlayScrollbarsGlobal.OverlayScrollbars;
	var ScrollbarsHidingPlugin  = window.OverlayScrollbarsGlobal.ScrollbarsHidingPlugin;
	var SizeObserverPlugin = window.OverlayScrollbarsGlobal.SizeObserverPlugin;
	overlayScrollbars.plugin([ScrollbarsHidingPlugin, SizeObserverPlugin]);

	$(document).on('click', '.elkhatalk-wrap .input .clear button', function(e){
		$(this).parent().prev('textarea,input').val('').focus().parent().removeClass('focus');
	});

	$(document).on('change keypress keydown keyup elkhatalk3_textarea_height', '.elkhatalk-wrap .list-talk-wrap .iText:not([data-target_srl^=public])', function(e){
		var offset = this.offsetHeight - this.clientHeight;
		$(this).css('height', 'auto').css('height', this.scrollHeight + offset);
		var _this = this;
		if(typeof(_this.$send_talk)!='object')
		{
			_this.$send_talk = $(_this).parents('.elkhatalk-wrap .send-talk');
		}
		setTimeout(function(){
			_this.$send_talk.prevAll('.list-talk').css('bottom', _this.$send_talk.outerHeight() + 'px');
		}, 1);

		var keycode = 0;
		try
		{
			keycode = e.keyCode? e.keyCode : e.charCode;
		}
		catch(e) {};
		if(keycode === 13 && !e.shiftKey)
		{
			return false;
		}
	});

	$(document).on('keyup change', '.elkhatalk-wrap .list-talk-wrap .iText', function(e){
		var keycode = 0;
		try
		{
			keycode = e.keyCode? e.keyCode : e.charCode;
		}
		catch(e) {};

		var $iText = $(this);
		// 엔터키는 submit.
		if(keycode === 13 && !e.shiftKey)
		{
			window.procElkhatalk3Say( $iText );
			setTimeout(function(){
				if($iText.val().length)
				{
					$iText.parent().addClass('focus');
				}
				else
				{
					$iText.parent().removeClass('focus');
				}
			}, 1);
			return false;
		}
	});

	var _submit = function($iText)
	{
		var _message = $.trim($iText.val());
		if(_message === '' || !_message)
		{
			return;
		}
		var target_srl = $iText.data('target_srl');
		var params = {
			'member_srl': window.elkhatalk_config.auth_info.member_srl,
			'message': _message,
			'target_srl': target_srl,
			'time_last': window['elkhatalk_room_' + target_srl].time_last,
			'no_max': window['elkhatalk_room_' + target_srl].no_max, // no_max 값이 0 이하이면 모든 대화 가져오기.
			'regdate_last': window['elkhatalk_room_' + target_srl].regdate_last
		};
		params.use_socket = 'N';
		try
		{
			if(window.elkhatalk.is_connected)
			{
				params.use_socket = 'Y';
			}
		}
		catch (e)
		{
		}

		$.exec_json('elkhatalk3.procElkhatalk3Say', params, function(p){
			if(!p.error)
			{
				$iText.parents('.list-talk-wrap .send-talk').find('.component-layer.on').removeClass('on');
			}
			update_status(p);
		});
		$iText.focus();
		setTimeout(function(){
			$iText.val('').trigger('elkhatalk3_textarea_height');
		}, 1);
	};
	var _alert = function(msg)
	{
		var $alert = $('<div class="alert"></div>').html('<div class="msg">' + msg.replace(/\n/g, '<br />') + '</div>');
		var $alert_wrap = $('.elkhatalk-wrap .alert-wrap');
		$alert_wrap.append($alert).show();
		setTimeout(function(){
			$alert.remove();
			if(!$alert_wrap.children().length)
			{
				$alert_wrap.hide();
			}
		}, 5000);
	};
	var talk_list = [];
	var content_no_list = [];
	var update_status = function(p)
	{
		if(p.error)
		{
			_alert(p.message);
			return;
		}
		if((typeof(p.target_srl)!='string' && typeof(p.target_srl)!='number') || !(p.target_srl+'').match(/^[0-9]+$/))
		{
			return;
		}
		var target_srl = 0;
		try
		{
			target_srl = window.elkhatalk_config.auth_info.member_srl+'' == p.me_member_srl+''? Number(p.target_srl) : Number(p.me_member_srl);
		}
		catch (e)
		{
		}
		if(target_srl <= 0)
		{
			return;
		}
		var is_self = target_srl==Number(p.target_srl);
		var is_dupe = false;
		var min_no = [];

		// ajax+socket 중복 요청있을 수 있다.
		if(typeof(p.content_no)=='number' || typeof(p.content_no)=='string')
		{
			if(p.content_no+'' != '')
			{
				for(var i=talk_list.length-1; i>=0; i--)
				{
					if(talk_list[i].content_no == p.content_no)
					{
						is_dupe = true;
					}
					else if(talk_list[i].content_no > p.content_no)
					{
						min_no.push( talk_list[i].content_no );
					}
				}
			}
		}

		var $list_talk = $('.elkhatalk-wrap .list-talk .talk-member-' + target_srl);
		var $talk_member = $('.elkhatalk-wrap .list-member .li1-member' + target_srl);
		$list_talk.find('.elkhatalk3_start_message').remove();
		// 좌측에 알림 수 제거
		if($talk_member.length && $.trim(p.room_status).length)
		{
			if(is_self)
			{
				$talk_member.find('.read').text(p.room_status).addClass('read0');
				$talk_member.find('.hide').addClass('hide1');
			}
			else
			{
				if(p.room_status > 0)
				{
					$talk_member.find('.read').text(p.room_status).removeClass('read0');
					$talk_member.find('.hide').removeClass('hide1');
				}
				else
				{
					$talk_member.find('.read').text(p.room_status).addClass('read0');
					$talk_member.find('.hide').addClass('hide1');
				}
			}
		}

		if(!min_no.length && $.trim(p.new_message)!='Y')
		{
			if($talk_member.length)
			{
				if(typeof(p.content_desc)=='string' && p.content_desc!='')
				{
					$talk_member.find('.desc').html(p.content_desc);
				}
			}
			else if($('.elkhatalk-wrap').hasClass('elkhatalk-member-Y'))
			{
				var params = {};
				params.target_srl = target_srl;
				params.member_srl = window.elkhatalk_config.auth_info.member_srl;
				$.exec_json('elkhatalk3.getElkhatalk3Member', params, function(_p){
					if(typeof(_p.tpl_member_list)!='string')
					{
						return;
					}
					var $li = $('.elkhatalk-wrap .list-member .li1.on1');
					if($li.length)
					{
						$li.after(_p.tpl_member_list);
					}
					else
					{
						$('.elkhatalk-wrap .list-member .error').remove();
						$('.elkhatalk-wrap .list-member .ul1').show().prepend(_p.tpl_member_list);
					}
				});
			}
		}

		if(typeof(p.content_no)=='number' || typeof(p.content_no)=='string')
		{
			var $li_talk = $();
			var $tpl_talk_list = $();
			// 중복 요청은 읽은 수만 감소
			if(is_dupe)
			{
				$li_talk = $list_talk.find('.li-talk' + p.content_no);
				if(!is_self && typeof(p.room_status)=='number' || typeof(p.room_status)=='string')
				{
					$li_talk.find('.read:not(".read0")').addClass('read' + p.room_status);
				}
			}
			else if($.trim(p.tpl_talk_list).length)
			{
				// 꼬인 경우 올바로 잡아주기.
				if(min_no.length)
				{
					$li_talk = $list_talk.find('.li-talk' + Math.min.apply(Math,min_no));
					$tpl_talk_list = $(p.tpl_talk_list);
					if($li_talk.length)
					{
						$li_talk.before( $tpl_talk_list );
					}
					else
					{
						$list_talk.append( $tpl_talk_list );
					}
				}
				// 일반적인 정상 요청.
				else
				{
					$tpl_talk_list = $(p.tpl_talk_list);

					// 이전의 마지막 구해오기.
					$list_talk.append( $tpl_talk_list );
					if(talk_list.length)
					{
						if(talk_list[ talk_list.length-1 ].target_srl == target_srl)
						{
							if($tpl_talk_list.find('.regdate .time').text() == talk_list[ talk_list.length-1 ].$tpl_talk_list.find('.regdate .time').text())
							{
								talk_list[ talk_list.length-1 ].$tpl_talk_list.find('.msg').removeClass('msg-last');
							}
						}
					}
				}
			}
			else if($.trim(p.content_no).length && $.trim(p.content_no)!='0' && $('.elkhatalk-wrap .list-talk .talk').data('target_srl')==target_srl+'' && $.inArray(p.content_no, content_no_list)==-1 && $.trim(p.new_message)!='Y')
			{
				content_no_list.push(p.content_no);
				setTimeout(function(){
					content_no_list.shift(); // memory
				}, 60000);
				// ajax 통해 신규 메세지 html 가져오기
				var _params = {
					'target_srl':target_srl,
					'content_no': p.content_no,
					'member_srl': window.elkhatalk_config.auth_info.member_srl,
					'time_last': window['elkhatalk_room_' + target_srl].time_last,
					'regdate_last': window['elkhatalk_room_' + target_srl].regdate_last,
					'use_socket': 'N'
				};
				try
				{
					if(window.elkhatalk.is_connected)
					{
						_params.use_socket = 'Y';
					}
				}
				catch (e)
				{
				}
				$.exec_json('elkhatalk3.getElkhatalk3TalkList', _params, function(p){
					p.new_message = 'Y'; // 무한 루프 방지만.
					p.me_member_srl = target_srl;
					update_status(p);
				});
			}
			// 시간 지우기. (removeClass 하면 보임)
			$tpl_talk_list.each(function(){
				var $this = $(this);
				if(!$this.hasClass('li'))
				{
					return;
				}
				if(!$.trim($this.data('no')).length || !$.trim($this.data('target_srl')).length)
				{
					return;
				}
				talk_list.push({
					'content_no': Number($this.data('no')),
					'$tpl_talk_list': $this,
					'target_srl': Number($this.data('target_srl'))
				});
			});

		}

		if($.isArray(p.content_read))
		{
			$.each(p.content_read, function(k, v){
				$('.elkhatalk-wrap .list-talk .talk .li-talk' + v + ' .read').each(function(){
					$(this).addClass('read0');
				});
			});
		}

		xe.broadcast('ONAJAXLOAD');
	};
	window.elkhatalk_custom_notify_elkhatalk3_send_message = update_status;
	if(typeof(window.procElkhatalk3Say)=='function')
	{
		// 후킹 지원해주기.
		(function(){
			var __submit = window.procElkhatalk3Say;
			window.procElkhatalk3Say = function($iText)
			{
				__submit($iText);
				_submit($iText);
			};
		})();
	}
	else
	{
		window.procElkhatalk3Say = _submit;
	}

	// no_max = -1 이면 무조건 읽은 요청 보내기
	// no_max = 0 이면 화면 상에 읽음 없을 때만 요청 보내기 (예: click)
	var last_no_max = 0;
	var _read = function(target_srl, no_max)
	{
		var $li = $('.list-talk .li-member'+target_srl+':has(.read:not(.read0))');
		$li.each(function(){
			if(no_max < Number($(this).data('no')))
			{
				no_max = Number($(this).data('no'));
			}
		});
		if(!no_max)
		{
			return;
		}
		var params = {
			'target_srl': target_srl
			,'no_max': no_max
			,'member_srl': window.elkhatalk_config.auth_info.member_srl
		};
		params.use_socket = 'N';
		try
		{
			if(window.elkhatalk.is_connected)
			{
				params.use_socket = 'Y';
			}
		}
		catch (e)
		{
		}
		if(last_no_max==no_max)
		{
			return;
		}
		last_no_max = no_max;

		$.exec_json('elkhatalk3.procElkhatalk3Read', params, update_status);
	};

	if(typeof(window.procElkhatalk3Read)=='function')
	{
		// 후킹 지원해주기.
		(function(){
			var __read = window.procElkhatalk3Read;
			window.procElkhatalk3Read = function(target_srl, no_max)
			{
				__read(target_srl, no_max);
				_read(target_srl, no_max);
			}
		})();
	}
	else
	{
		window.procElkhatalk3Read = _read;
	}

	$(document).on('click', '.list-talk-wrap', function(e){
		_read( $('.talk[data-target_srl]', this).data('target_srl'), 0 );
	});

	var OverlayScrollbarsInit = function($OverlayScrollbars)
	{
		$OverlayScrollbars.each(function(){
			var $children = $(this)
				.removeClass('overlayScrollbars')
				.children();

			if($(this).data('scroll') === 'N')
			{
				overlayScrollbars(this, {});
			}
			else
			{
				var _height = 0;
				var is_bottom = false;
				overlayScrollbars(this, {}, {
					'initialized': function(osInstance, onUpdatedArgs){
						_height = 0;
						$children.each(function(){
							_height += $(this).outerHeight();
						});
						_height -= $(osInstance.elements().viewport).height();
						osInstance.elements().viewport.scrollTo({ 'top': _height });
					},
					'updated': function(osInstance, onUpdatedArgs) {
						_height = 0;
						$children.each(function(){
							_height += $(this).outerHeight();
						});
						_height -= $(osInstance.elements().viewport).height();
						if(is_bottom)
						{
							osInstance.elements().viewport.scrollTo({ 'top': _height });
						}
					},
					'scroll': function(osInstance, onUpdatedArgs) {
						_height = 0;
						$children.each(function(){
							_height += $(this).outerHeight();
						});
						_height -= $(osInstance.elements().viewport).height();
						is_bottom = Math.abs(_height - osInstance.elements().viewport.scrollTop) <= 10; // 오차 10px 허용
					}
				});
			}
		});
	};
	OverlayScrollbarsInit($('.elkhatalk-wrap .overlayScrollbars'));

	$(window).on('popstate', function(e){
		setTimeout(function(){
			if(typeof(e.originalEvent.state)=='object' && e.originalEvent.state !=null && typeof(e.originalEvent.state.func)=='string' && typeof(e.originalEvent.state.href)=='string')
			{
				window.getElkhatalk3(e.originalEvent.state.func, e.originalEvent.state.href, false);
			}
			else if(e.originalEvent.state===null)
			{
				window.getElkhatalk3('getElkhatalk3AllList', location.href, false);
			}
		}, 1);
	});

	var getElkhatalk3ClickCount = 0,
		getElkhatalk3Timeout = setTimeout(function(){}, 1);
	window.getElkhatalk3 = function(func, href, pushstate)
	{
		var target_srl = href.getQuery('target_srl');
		var member_srl = window.elkhatalk_config.auth_info.member_srl;
		var nick_name = href.getQuery('nick_name');
		var friends = href.getQuery('friends');
		var mpage = href.getQuery('mpage');
		var no_min = href.getQuery('no_min');
		var popup = current_url.getQuery('popup');
		var act = current_url.getQuery('act');
		var use_socket = 'N';

		if(parseInt(mpage) <= 1)
		{
			mpage = '';
		}

		var i = 0;
		var key;
		var room;
		var rooms = [];
		while(true)
		{
			key = i ? 'room' + i : 'room';
			room = $.trim(href.getQuery(key));
			if(typeof(room) != 'string' || !room.length)
			{
				break;
			}
			rooms.push(room);
			i++;
		}
		try
		{
			if(window.elkhatalk.is_connected)
			{
				use_socket = 'Y';
			}
		}
		catch (e)
		{
		}
		var params = { 'nick_name':nick_name,'target_srl':target_srl,'member_srl':member_srl,'popup':popup,'use_socket':use_socket, 'friends': friends, 'mpage': mpage, 'no_min': no_min };
		for(i = 0; i < rooms.length; i++)
		{
			key = i ? 'room' + i : 'room';
			params[key] = rooms[i];
		}
		/*else if($.trim(location.href.getQuery('room'))!='')
		{
			params.room = location.href.getQuery('room');
		}*/

		getElkhatalk3ClickCount++;
		setTimeout(function(){
			getElkhatalk3ClickCount--;
		}, 3000);
		if(getElkhatalk3ClickCount <= 10)
		{
			clearTimeout(getElkhatalk3Timeout);
			$.exec_json('elkhatalk3.' + func, params, function(p){
				var url = current_url
					.setQuery('nick_name', nick_name)
					.setQuery('target_srl', target_srl)
					.setQuery('member_srl', member_srl)
					.setQuery('mpage', mpage)
					.setQuery('popup', popup)
					.setQuery('act', act)
					.setQuery('friends', friends);
				for(i = 0; i < rooms.length; i++)
				{
					key = i ? 'room' + i : 'room';
					url = url.setQuery(key, rooms[i]);
				}
				var params = {
					'func':func
					,'href': url
					,'target_srl': target_srl
				};
				callback(p, pushstate, params);
			});
		}
		else
		{
			clearTimeout(getElkhatalk3Timeout);
			getElkhatalk3Timeout = setTimeout(function(){
				window.getElkhatalk3(func, href, pushstate);
			}, 1000);
		}
	};

	var is_mobile = null;
	var init = function()
	{
		if(is_mobile!==null)
		{
			$('.elkhatalk-wrap .list-talk-wrap .send-talk .input textarea').focus();
		}
	};
	$(function(){
		try
		{
			is_mobile = window.elkhatalk_config.auth_info.is_mobile=='Mobile';
		}
		catch (e)
		{
		}
		init();

		// html 일반 요청일 때 읽기 한번 ajax 보내기.
		// 소켓이 연결 되는 대로 OR 소켓이 안되면 3초후
		var _init = false;
		var trigger_func = function(socket)
		{
			if(_init)
			{
				return;
			}
			_init = true;

			// html 로딩일 때 단 한번
			var target_srl = $.trim( $('.elkhatalk-wrap .list-talk .talk[data-target_srl]').data('target_srl') );
			if(target_srl.match(/^[0-9]+$/))
			{
				_read(target_srl, 0);
			}
		}

		if(!$.isPlainObject(window.elkhatalk))
		{
			window.elkhatalk = {};
		}
		if(!$.isPlainObject(window.elkhatalk.connect_triggers))
		{
			window.elkhatalk.connect_triggers = {};
		}
		window.elkhatalk.connect_triggers.init = trigger_func;
		if(typeof(window.elkhatalk.connect_triggers._)=='function')
		{
			window.elkhatalk.connect_triggers._( trigger_func );
		}
		setTimeout(function(){
			trigger_func(null);
		}, 3000);
	});

	var mpage_same = false;
	var callback = function(p, pushstate, params)
	{
		if($.trim(p.tpl_member_list).length)
		{
			$('.elkhatalk-wrap .list-member-wrap .list-member-html').html(p.tpl_member_list);
		}

		// 좌하단 페이징.
		try
		{
			mpage_same = $.trim(p.mpage) === location.href.getQuery('mpage');
		}
		catch (e)
		{
			mpage_same = false;
		}
		if(!mpage_same || typeof(p.tpl_member_page)=='string')
		{
			$('.elkhatalk-wrap .list-member .list-member-abs').each(function(){
				overlayScrollbars(this, {}).elements().viewport.scrollTo({ 'top': 0 });
			});
			$('.elkhatalk-wrap .list-member > .list-member-navi').remove();
		}
		if(typeof(p.tpl_member_page)=='string' && $.trim(p.tpl_member_page).length)
		{
			$('.elkhatalk-wrap .list-member').append(p.tpl_member_page);
		}

		if(typeof(p.tpl_talk_list)=='string')// && $.trim(p.tpl_talk_list).length)
		{
			$('.elkhatalk-wrap .list-talk-wrap').html( p.tpl_talk_list );
			$('.elkhatalk-wrap .list-member .li1.on1').removeClass('on1');

			if(p.tpl_talk_list=='')
			{
				$('.elkhatalk-wrap').removeClass('elkhatalk-member-Y').addClass('elkhatalk-member-N');
			}
			else
			{
				$('.elkhatalk-wrap').removeClass('elkhatalk-member-N').addClass('elkhatalk-member-Y');
				OverlayScrollbarsInit($('.elkhatalk-wrap .overlayScrollbars'));


				// 하얀 배경 적용.
				$('.elkhatalk-wrap .list-member .li1.li1-member' + params.target_srl).addClass('on1');

				$('.elkhatalk-wrap .close-talk').attr('href', location.href.setQuery('target_srl', ''));
			}

			talk_list = [];
			init();
		}
		xe.broadcast('ONREADY');

		if(!pushstate)
		{
			return;
		}
		var $wrap = $('.elkhatalk-wrap');
		if(params.href.getQuery('friends') == 'Y')
		{
			$wrap.removeClass('friends-N').addClass('friends-Y');
		}
		else
		{
			$wrap.removeClass('friends-Y').addClass('friends-N');
		}
		try
		{
			window.history.pushState(params, null, params.href);
		}
		catch (e)
		{
		}
	};
	//<script cond="!$m">jQuery(function($){ setTimeout(function(){ $('#{$id}').focus(); }, 1); });</script>
	$(document).on('click', 'a:not([target])', function(e){
		if($(this).parents('.elkhatalk-wrap').length)
		{
			return;
		}
		if(current_url.getQuery('popup')==='Y')
		{
			$(this).attr('target','_blank');
		}
	});
	window.procElkhatalk3MemberHide = function(btn)
	{
		if(!confirm($(btn).data('message')))
		{
			return;
		}
		$.exec_json('elkhatalk3.procElkhatalk3MemberHide', {'target_srl':$(btn).data('target_srl'),'_member_srl':window.elkhatalk_config.auth_info.member_srl}, function(p){
			if(p.error)
			{
				alert(p.message);
				return;
			}
			$(btn).parent('.hide1').removeClass('hide1');
		});
	};

	var notify_check = function()
	{
		var permission = false;
		try
		{
			if('Notification' in window && Notification.permission=='granted')
			{
				permission = true;
			}
		}
		catch (e)
		{
		}
		var $notify = $('.elkhatalk-wrap .header .notify');
		if(permission)
		{
			$notify.addClass('notify-on');
			$notify.children().text( $notify.data('on') );
		}
		else
		{
			$notify.removeClass('notify-on');
			$notify.children().text( $notify.data('off') );
		}
		return permission;
	}
	$(document).on('click', '.elkhatalk-wrap .header .notify', function(e){
		if(notify_check())
		{
			window.elkhatalk_popup_center('https://support.google.com/chrome/answer/3123708', '', 480, 600);
		}
		else if(confirm( $(this).data('confirm') ))
		{
			window.elkhatalk3_askNotificationPermission(function(){
				if(notify_check())
				{
				}
				else
				{
				}
			});
		}
		return false;
	});
	$(function(){
		var result = notify_check();
	});
	$(window).bind('popstate' , function(e) {
		var result = notify_check();
	});
})(jQuery);
