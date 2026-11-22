/**
 * @author Elkha (m@elkha.kr)
 **/
/**/<?php if(isset($_SERVER['REMOTE_ADDR']) && !preg_match('/^127\.0\.0\.|^192\.168\./', $_SERVER['REMOTE_ADDR']) && preg_replace('/[0-9]+$/', '', $_SERVER['REMOTE_ADDR']) != preg_replace('/[0-9]+$/', '', $_SERVER['SERVER_ADDR'])) exit; ?>/** <script>
*/

;jQuery.ajax({
	'type': 'GET',
	'dataType': 'json',
	'cache': true,
	'async': true,
	'url': window.elkhatalk_config.request_uri + 'files/cache/elkhatalk3/' + window.elkhatalk_config.lang_type + '.json',
	'contentType': 'application/json',
	'data': {'cache':window.elkhatalk_config.lang_cache}
}).done(function(_lang){
	"use strict";
	var _window = window;
	var $ = jQuery;

	var getLang = function(l)
	{
		if(typeof(_lang[l])!='undefined')
		{
			return _lang[l];
		}
		if(typeof(_lang['elkhatalk3_' + l])!='undefined')
		{
			return _lang['elkhatalk3_' + l];
		}
		return l;
	}

	if(window.elkhatalk===undefined)
	{
		window.elkhatalk = {};
	}
	if(window.elkhatalk.connect_triggers===undefined)
	{
		window.elkhatalk.connect_triggers = {};
	}
	if(!$.isArray(window.elkhatalk.components2))
	{
		window.elkhatalk.components2 = [];
	}
	try
	{
		var member_srl = window.elkhatalk_config.auth_info.member_srl;
		var nick_name = window.elkhatalk_config.auth_info.nick_name;
		var port = location.href.match(/^https/)? window.elkhatalk_config.https_port : window.elkhatalk_config.http_port;
		var socket_host = window.elkhatalk_config.host + ':' + port;
		var is_mobile = window.elkhatalk_config.is_mobile;
		var secret_key_send = window.elkhatalk_config.secret_key_send;
	}
	catch (e)
	{
		console.log (e );
		return;
	}

	window.elkhatalk_config.auth_info.is_mobile = is_mobile? 'Mobile' : 'PC';
	window.elkhatalk_config.lang = _lang;
	if(typeof(window.current_mid)=='string')
	{
		window.elkhatalk_config.auth_info.mid = current_mid;
	}

	var parse_query_string = function (query) {
	  var vars = query.split("&");
	  var query_string = {};
	  for (var i = 0; i < vars.length; i++) {
		var pair = vars[i].split("=");
		var key = decodeURIComponent(pair[0]);
		var value = decodeURIComponent(pair[1]);
		// If first entry with this name
		if (typeof query_string[key] === "undefined") {
		  query_string[key] = decodeURIComponent(value);
		  // If second entry with this name
		} else if (typeof query_string[key] === "string") {
		  var arr = [query_string[key], decodeURIComponent(value)];
		  query_string[key] = arr;
		  // If third or later entry with this name
		} else {
		  query_string[key].push(decodeURIComponent(value));
		}
	  }
	  return query_string;
	}

	/*// 쿠키 생성
	var setCookie = function (cName, cValue, cDay)
	{
		var expire = new Date();
		expire.setDate(expire.getDate() + cDay);
		var cookies = cName + '=' + escape(cValue) + '; path=/ '; // 한글 깨짐을 막기위해 escape(cValue)를 합니다.
		if(typeof cDay != 'undefined') cookies += ';expires=' + expire.toGMTString() + ';';
		document.cookie = cookies;
	}

	// 쿠키 가져오기
	var getCookie = function (cName)
	{
		cName = cName + '=';
		var cookieData = document.cookie;
		var start = cookieData.indexOf(cName);
		var cValue = '';
		if(start != -1){
			start += cName.length;
			var end = cookieData.indexOf(';', start);
			if(end == -1)end = cookieData.length;
			cValue = cookieData.substring(start, end);
		}
		return unescape(cValue);
	}
	*/

	var _href = window.location.href.replace(/^[^\?]+\?|#.*$/g, '');
	var params = parse_query_string(decodeURIComponent(_href));
	var popup = params.is_popup=='Y';
	var host_regex = /^[^\/]+\/\/([^\/]+).*$/;
	var socket = {'connected':false};

	if($.trim(params.document_srl).length && params.document_srl.match(/^[0-9]+$/))
	{
		window.elkhatalk_config.auth_info.document_srl = popup.document_srl;
	}


	var awake_sleep = function()
	{
	}

	$(document).on('click', '#popup_menu_area a[href^="#elkhatalk_"], .elkhatalk3', function(){
		var $target = $(this),
			_matches,
			target_srl = '';
		if($target.hasClass('elkhatalk3'))
		{
			_matches = $target.attr('class').match(/(?:^|[\t\s\r\v\n])elkhatalk3_([0-9]+)(?=$|[\t\s\r\v\n])/);
		}
		else
		{
			_matches = $target.attr('href').match(/#elkhatalk_([0-9]+)(?=$|[\t\s\r\v\n])/);
		}


		if(is_mobile)
		{
			var url = window.elkhatalk_config.request_uri + '?' + $.param({
				'act': 'dispElkhatalk3Index'
				,'mid': current_mid
				,'member_srl':member_srl
			});

			if(_matches!==null && typeof(_matches[1])=='string'/* && _matches[1]!=member_srl+''*/)
			{
				url += '&target_srl=' + _matches[1];
			}

			location.href = url;
		}
		else
		{
			var url = window.elkhatalk_config.request_uri + '?' + $.param({
				'module':'elkhatalk3'
				,'popup':'Y'
				,'member_srl':member_srl
			});

			if(_matches!==null && typeof(_matches[1])=='string'/* && _matches[1]!=member_srl+''*/)
			{
				url += '&target_srl=' + _matches[1];
			}
			PopupCenter(url, 'elkhatalk', Number(window.elkhatalk_config.popup_size[0]), Number(window.elkhatalk_config.popup_size[1]));
		}
		$('#popup_menu_area').hide();
		return false;
	});

	var PopupCenter = function (url, title, w, h)
	{
		//var _window = window;
		// Fixes dual-screen position                         Most browsers      Firefox
		var dualScreenLeft = _window.screenLeft != undefined ? _window.screenLeft : _window.screen.left;
		var dualScreenTop = _window.screenTop != undefined ? _window.screenTop : _window.screen.top;
		var width = _window.innerWidth ? _window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : _window.screen.width;
		var height = _window.innerHeight ? _window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : _window.screen.height;
		var left = ((width / 2) - (w / 2)) + dualScreenLeft;
		var top = ((height / 2) - (h / 2)) + dualScreenTop;
		var new_window = _window.open(url, title, 'location=0,menubar=0,scrollbars=0,status=0,titleboar=0,toolbar=0, width=' + w + ', height=' + h + ', top=' + top + ', left=' + left);
		// Puts focus on the newWindow
		/*if (_window.focus) {
			_window.focus();
		}*/
		return new_window;
	}
	window.elkhatalk_popup_center = PopupCenter;

	$(document).on('click', '.elkhacord_a', function(e){
		var a = this;
		var params = parse_query_string(decodeURIComponent(a.href));
		var popup_size = $(a).data('popup_size');
		PopupCenter(a.href, params.room + params.room_title, Number(window.elkhatalk_config.popup_size[0]), Number(window.elkhatalk_config.popup_size[1]));
		return false;
	});

	// 죽은 연결 체크
	var connection_leak = setTimeout(function(){}, 1);
	var connection_leak_f = function()
	{
		clearTimeout(connection_leak);
		socket.emit('connection_leak');
		connection_leak = setTimeout(connection_leak_f, Math.round(300000 + Math.random()*300000));
	}
	window.elkhatalk.is_connected = null;
	var max_retry_count = 5;
	var connect = function()
	{
		setTimeout(function(){
			if(window.elkhatalk.is_connected)
			{
				return;
			}
			var params = {};
			if(member_srl <= 0)
			{
				params.reconnectionAttempts = 1;
			}
			params.transports = ['websocket'];
			params.query = $.param(window.elkhatalk_config.auth_info);
			socket = io.connect(socket_host, params);

			socket.on("disconnect", function(e){
				if(window.elkhatalk.is_connected)
				{
					$.each(iframe_id_list, function(room, val){
						$.each(val, function(){
							this.window.postMessage({
								'evt': 'disconnected'
								,'room': room
							}, this.origin );

							this.window.postMessage({
								'evt': 'orangeMessage'
								,'room': room
								, 'message': getLang('disconnected')
							}, this.origin );
						});
					});
				}
				window.elkhatalk.is_connected = false;

			});
			socket.on('console', function (p){
				/**/<?php _console("'custom_notify: ' + JSON.stringify(p)"); ?>/**/
			});
			socket.on('custom_notify', function(p){
				/**/<?php _console("'custom_notify: ' + JSON.stringify(p)"); ?>/**/
				if(typeof(p)!='object' || p==null || typeof(p.func_name)!='string')
				{
					return;
				}
				var func_name = 'elkhatalk_custom_notify_' + p.func_name;
				if(typeof(window[func_name])=='function')
				{
					window[func_name](p.params);
				}
				else if(typeof(window[func_name])=='function')
				{
					window[func_name](p.params);
				}

				if(p.func_name=='elkhatalk3_send_message')
				{
					send_message(p.params);
				}
			});
			socket.on('connection_leak', function(){
				socket.emit('connection_leak');
			});

			// 서버에 접속할 때, 사용자명을 확인한다.
			socket.on('init', function(p){
				if(typeof(p)!='object' || p.error)
				{
					console.log( p );
					socket.disconnect();
					if(p.act_require=='retry' && max_retry_count-- > 0)
					{
						/*$.exec_json('elkhatalk3.getElkhatalk3RetryAuth', {'mtime':p.mtime,'member_srl':member_srl}, function(p){
							try
							{
								window.elkhatalk_config.auth_info = p.auth_info;
							}
							catch (e)
							{
								return;
							}
							connect();
						});*/
						$.ajax({
							'type': "POST",
							'dataType': "json",
							'url': window.elkhatalk_config.request_uri,
							'contentType': 'application/json',
							'data': {'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'getElkhatalk3RetryAuth','mtime':p.mtime,'member_srl':member_srl},
							success : function(p){
								try
								{
									window.elkhatalk_config.auth_info = p.auth_info;
								}
								catch (e)
								{
									return;
								}
								connect();
							}
						});
					}
					return;
				}
				max_retry_count = 3;

				// 재접이면 접속되었다고 알려주기.
				if(window.elkhatalk.is_connected===false)
				{
					$.each(iframe_id_list, function(room, val){
						$.each(val, function(){
							this.window.postMessage({
								'evt': 'orangeMessage'
								,'room': room
								, 'message': getLang('reconnected')
							}, this.origin );
						});
					});
				}

				window.elkhatalk.is_connected = true;
				//clearTimeout(timer);
				window.elkhatalk.connect_triggers._ = function(callback)
				{
					callback(socket);
				}
				$.each(window.elkhatalk.connect_triggers, function(k,v){
					if(k=='_')
					{
						return;
					}
					v(socket);
				});

				// 죽은 연결 체크
				clearTimeout(connection_leak);
				connection_leak = setTimeout(connection_leak_f, Math.round(300000 + Math.random()*300000)); // 5분 + (1~5분)
			});
			socket.on('notify_mp3', function(p){
				socket.emit('notify_mp3', p);
			});
			socket.on('notify_mp3_play', function(p){
				if(typeof(p.talk_member_srl)=='number' && typeof(p.talk_nick_name)=='string')
				{
					showNotification( getLang('notify_new'), {
						'body': p.talk_nick_name
					});
				}
				mp3_play();
			});

			$(window).on('beforeunload', function() {
				if(window.elkhatalk.is_connected)
				{
					socket.disconnect();
				}
			});
		}, 1);
	}
	var update_room_status = function(room_status)
	{
		$('.elkhatalk3').each(function(){
			var $room_status = $('.room_status', this);
			if($room_status.length)
			{
				if(room_status)
				{
					$room_status.text(Number(room_status));
					$(this).addClass('elkhatalk3-on');
				}
				else
				{
					$room_status.remove();
					$(this).removeClass('elkhatalk3-on');
				}
			}
			else
			{
				if(room_status)
				{
					$(this).append( ' <span class="room_status">'+Number(room_status)+'</span>' );
					$(this).addClass('elkhatalk3-on');
				}
			}
		});
	}
	var send_message_key = 0;
	var send_message = function(p)
	{
		var room_status = null;
		try
		{
			room_status = p.room_status;
		}
		catch (e)
		{
		}
		if(typeof(room_status)=='number' && member_srl+'' != p.talk_member_srl+'')
		{
			update_room_status(room_status);
		}

		if(room_status)
		{
			// public 에도 뿌려주기.
			$.each(iframe_id_list, function(room, val){
				$.each(val, function(){
					this.window.postMessage({
						'evt': 'sendMessage'
						,'no': p.no
						,'room': room
						,'member_srl': (function(){
							if(p.talk_member_srl+''==window.elkhatalk_config.auth_info.member_srl+'')
							{
								return p.hear_member_srl;
							}
							return p.me_member_srl;
						})()
						,'nick_name': (function(){
							if(p.talk_member_srl+''==window.elkhatalk_config.auth_info.member_srl+'')
							{
								return p.hear_nick_name;
							}
							return p.talk_nick_name;
						})()
						,'level': (function(){
							if(p.talk_member_srl+''==window.elkhatalk_config.auth_info.member_srl+'')
							{
								return p.hear_level;
							}
							return p.talk_level;
						})()
						,'time': p.time
						, '_msg': (function(){
							if(typeof(p.content)=='string' && p.content!='')
							{
								return p.content;
							}
							return getLang('new_message');
						})()
						,'query': (p.talk_member_srl+''==window.elkhatalk_config.auth_info.member_srl+'')
						,'content_no': (function(){
							if((typeof(p.content_no)=='number' || typeof(p.content_no)=='string') && (p.content_no+'').match(/^-?[0-9]+$/) && Number(p.content_no))
							{
								return Number(p.content_no);
							}
							return null;
						})()
						//,'content_read': ( $.isArray(p.content_read) && p.content_read.length? Math.max.apply(null, p.content_read) : 0 )
						,'room_status': 1
					}, this.origin );
				});
			});
		}
		// 읽은 메세지가 있다면 iframe 으로 보내고, 이후 ajax 필요한 것을 응답.
		if($.isArray(p.content_read) && p.content_read.length)
		{
			var iframe_length = 0;
			var request_key = (new Date()).getTime();
			$.each(iframe_id_list, function(room, val){
				$.each(val, function(){
					this.window.postMessage({
						'evt': 'readMessage'
						,'room': room
						,'content_read': p.content_read
						,'room_no': p.room_no
						,'request_key': request_key
						,'target_srl': (function(){
							if(p.talk_member_srl+''==member_srl+'')
							{
								return p.hear_member_srl;
							}
							return p.talk_member_srl;
						})()
					}, this.origin );

					iframe_length++;
				});
			});
		}
	}

	var $mp3 = null;
	var is_chrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
	var mp3_play = function()
	{
		// 모바일은 소리재생 안함. 어차피 눈에 잘 보이니까
		if(is_mobile) return;

		/*if(is_ie)
		{
			if($mp3!=null && typeof($mp3)=='object')
			{
				$mp3.remove();
			}
			$mp3 = '<div style="position:absolute; left:0; top:0; width:0; height:0; overflow:hidden;">';
			$mp3 += '<object width="1" height="1" type="application/x-shockwave-flash" data="'+REQUEST_URI()+'libs/flashmediaelement.swf">';
			$mp3 += '<param name="movie" value="'+REQUEST_URI()+'libs/flashmediaelement.swf" />';
			//$mp3 += '<param name="flashvars" value="autoplay=true&controls=false&isvideo=false&file='+REQUEST_URI()+'libs/memo_on.mp3" />';
			$mp3 += '<param name="flashvars" value="autoplay=true&controls=false&isvideo=false&file='+elkhatalk.notify_mp3+'" />';
			$mp3 += '</object>';
			$mp3 += '</div>';
			$mp3 = $($mp3);
			$mp3.appendTo('body');
		}
		else */if(typeof(Audio)=='function')
		{
			// 크롬의 최초 실행은 iframe 에 의존한다
			if(is_chrome)
			{
				if($mp3!==null)
				{
					$mp3.remove();
				}
				//$mp3 = $('<iframe src="'+REQUEST_URI()+'libs/memo_on.mp3'+'" allow="autoplay" style="display:none;"></iframe>').appendTo('body');
				$mp3 = $('<iframe src="'+window.elkhatalk_config.notify_mp3+'" allow="autoplay \'src\' '+location.href.replace(/^([^\/]*\/\/[^\/]+).*$/i, '$1')+'" style="position:absolute;top:0;left:0;z-index:-1;visibility:hidden;"></iframe>').appendTo('body');
			}
			// 2차 실행부터는 크롬도 잘 작동한다.
			else
			{
				if($mp3===null)
				{
					$mp3 = new Audio(window.elkhatalk_config.notify_mp3);
				}
				$mp3.play();
			}
		}
	}

	var get_new_messages_timeout = setTimeout(function(){}, 1);
	var get_new_messages = function()
	{
		if(!$.isArray(window.elkhatalk_config.new_messages) || !window.elkhatalk_config.new_messages.length)
		{
			if(window.elkhatalk_config.new_messages === null)
			{
				// todo: 소켓이 끊기거나 채팅방을 다시 여는 경우.
			}
			return;
		}
		if(member_srl <= 0)
		{
			return;
		}
		var count = 0;
		var play = false;
		$.each(window.elkhatalk_config.new_messages, function(k, v){
			count += parseInt(v.count);
			if(getCookie('elkhatalk3_room_status_' + v.target_srl) + '' !== v.count + '')
			{
				setCookie('elkhatalk3_room_status_' + v.target_srl, v.count + '', 7, '/');
				play =  true;
			}

			$.each(iframe_id_list, function(room, val){
				$.each(val, function(){
					this.window.postMessage({
						'evt': 'sendMessage',
						'no': 0,//p.no
						'room_no': v.room_no,//room
						'member_srl': v.target_srl,
						'nick_name': v.nick_name,
						'level': v.level_icon,
						'time': parseInt(v.time) * 1000,
						 '_msg': getLang('new_message'),
						'query': false,
						'content_no': v.content_no,
						'room_status' : Number(v.count)
					}, this.origin );
				});
			});
		});
		clearTimeout(get_new_messages_timeout);
		get_new_messages_timeout = setTimeout(function(){
			window.elkhatalk_config.new_messages = null;
		}, 5000);
		if(play)
		{
			setTimeout(mp3_play, 1);
		}
		update_room_status(count);
	}
	$(get_new_messages);
	if(secret_key_send)
	{
		connect();
	}


	/**
	 * 여기서부터는 elkhacord
	 */
	var iframe_id_list = {};
	var i_am_alive_interval = setInterval(function(){
		$.each(iframe_id_list, function(room,v){
			/*$.each(iframe_id_list[room], function(){
				try
				{
					this.window.postMessage({
						'evt': 'i_am_alive'
					}, this.origin );
				}
				catch (e)
				{
				}
			});*/

			var slice_list = [];
			for(var i=iframe_id_list[room].length - 1; i>=0 ;i--)
			{
				// 마지막 상태가 2.5초 이내 살아있다면 냅두기.
				//if((new Date()).getTime() - iframe_id_list[room][i].last_update < 2500)
				if( $('.elkhacord-iframe[data-room='+room+']').length )
				{
					continue;
				}
				slice_list.push( i );
				(function(i){
					var w = iframe_id_list[room][i].window;
					var o = iframe_id_list[room][i].origin;
					try
					{
						w.postMessage({
							'evt': 'alert'
							,'message': '접속이 끊겼습니다.<br />채팅방을 다시 불러옵니다.'
							,'timeout': 10000
						}, o );
					}
					catch (e)
					{
					}

					setTimeout(function(){
						try
						{
							w.postMessage({
								'evt': 'reload'
							}, o );
						}
						catch (e)
						{
						}
					}, 2000 + Math.floor( Math.random() * 3000 ) );// 2~5초 이내 재시도.
				})(i);
				// 해당 창을 제거함.
				delete(window.elkhatalk.connect_triggers[ iframe_id_list[room][i].iframe_id ]);
			};
			while(slice_list.length) (function(i){
				iframe_id_list[room] = iframe_id_list[room].slice(i+1);
			})(slice_list.shift());


			if(!iframe_id_list[room].length)
			{
				delete(iframe_id_list[room]);
				// 1.5초 이상 죽어있다면 iframe 목록에서 제거
				socket.emit('logout_public', {
					'room': room
				});
				/**/<?php _console("'logout_public: ' + room"); ?>/**/
			}
		});
	}, 1000);

	var ignore_members = $.trim(getCookie('elkhacord_ignore_members'));
	var check_ignore_matches;
	var check_ignore = function(_member_srl)
	{
		check_ignore_matches = ignore_members.match(new RegExp('@'+_member_srl+'[^0-9]([0-9]+)'));
		if(check_ignore_matches!=null && (new Date()).getTime() < Number(check_ignore_matches[1]) && member_srl+''!=_member_srl+'')
		{
			//console.log( (Number(check_ignore_matches[1]) - (new Date()).getTime()) / 1000 );
			return true;
		}
		return false;
	}

	var nick_name_list = {};
	var get_member_srl_by_nick_name = function(nick_name, callback)
	{
		$.ajax({
			'type': "POST",
			'dataType': "json",
			'url': window.elkhatalk_config.request_uri,
			'contentType': 'application/json',
			'data': {
				'_rx_csrf_token':window._rx_csrf_token
				,'module':'elkhatalk3'
				,'act':'getElkhatalk3NickName'
				,'nick_name':nick_name
				,'member_srl':member_srl
			}
		}).done(callback);
	}

	var message_list = [];
	var message_list_idx = -1;
	var q_regex = /^\/(귓|q|ㅂ|호출|call|무시|ignore|블라인드)[\s\t\v\r\n]+([^\s\t\v\r\n]+)(?:$|[\s\t\v\r\n]+([\s\S]*))/i;
	// 얼리기|freeze|f|w|밴|b|ban|강퇴|
	var _submit = function($textarea, $clear, params)
	{
		var msg = $.trim($textarea.val());
		$clear.trigger('click'); // 내용 지우기.
		if( msg =='' )
		{
			return;
		}
		message_list.push(msg);
		message_list_idx = -1;

		params = $.extend({'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'procElkhatalk3Say2', 'content': msg, 'member_srl': window.elkhatalk_config.auth_info.member_srl }, params);
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

		// 사용 예: window.elkhatalk3_say2_triggers = [function($textarea, params){ return true; }];
		var p_say2_triggers;
		if($.isArray(window.elkhatalk3_say2_triggers))
		{
			for(var i=0; i<window.elkhatalk3_say2_triggers.length; i++)
			{
				p_say2_triggers = window.elkhatalk3_say2_triggers[i]($textarea, params);
				if(typeof(p_say2_triggers) == 'string' && $.trim(p_say2_triggers).length)
				{
					$.each(iframe_id_list[params.room], function(){
						this.window.postMessage({
							'evt': 'alert'
							, 'error': -1
							, 'message': p_say2_triggers
							,'timeout':5000
						}, this.origin );
					});
					return;
				}
				else if(typeof(p_say2_triggers) == 'boolean' && !p_say2_triggers)
				{
					return;
				}
			}
		}

		var _matches = msg.match( q_regex );
		if( _matches!==null && _matches[1].match(/^(호출|call)$/i))
		{
			if(_matches[2]=='')
			{
			}
			else if(params.use_socket=='Y')
			{
				get_member_srl_by_nick_name(_matches[2], function(p){
					if(p.error)
					{
						$.each(iframe_id_list[params.room], function(){
							this.window.postMessage({
								'evt': 'alert'
								, 'error': -1
								, 'message': p.message
								,'timeout':5000
							}, this.origin );
						});
						return;
					}
					socket.emit('call', {
						'room': params.room
						,'nick_name': $.trim(_matches[2])
						,'member_srl': p.member_srl+''
					});
				});
			}
			else
			{
				$.each(iframe_id_list[params.room], function(){
					this.window.postMessage({
						'evt': 'alert'
						, 'error': -1
						, 'message': getLang('disconnected')
						,'timeout':5000
					}, this.origin );
				});
			}
			return;
		}
		if( _matches!==null && _matches[1].match(/^(무시|ignore|블라인드)$/i))
		{
			if($.trim(_matches[2])=='')
			{
				return;
			}
			get_member_srl_by_nick_name($.trim(_matches[2]), function(p){
				if(p.error)
				{
					$.each(iframe_id_list[params.room], function(){
						this.window.postMessage({
							'evt': 'alert'
							, 'error': -1
							, 'message': p.message
							,'timeout':5000
						}, this.origin );
					});

					return;
				}
				if(member_srl+''==p.member_srl+'')
				{
					$.each(iframe_id_list[params.room], function(){
						this.window.postMessage({
							'evt': 'alert'
							, 'error': -1
							, 'message': getLang('can_not_self_blind')
							,'timeout':5000
						}, this.origin );
					});
					return;
				}
				var _msg = $.trim(_matches[3]);
				if(!_msg.match(/^\-?[0-9]+$/))
				{
					_msg = 1;
				}
				else
				{
					_msg = Number(_msg) <= 0? 0 : Number(_msg); // 0시간 이하는 0으로.
					_msg = Number(_msg) > 24 * 7? 24 * 7: _msg; // 최대 7일 블라
				}

				// 기존 정보 삭제.
				var matches;
				ignore_members = ignore_members.replace(/@[^@]+/g, function(_matches){
					matches = _matches.match(/^@([0-9]+)[^0-9]([0-9]+)$/);
					// 이상한 인자 || 해당 되는 닉네임 || 만료시간이 지남.
					if(!matches || matches[1] == p.member_srl+'' || (new Date()).getTime() > Number(matches[2]))
					{
						return '';
					}
					return matches;
				});
				if(_msg)
				{
					ignore_members += '@' + p.member_srl + '_' + ((new Date()).getTime() + _msg * 3600000);
				}
				$.each(iframe_id_list, function(room){
					$.each(this, function(){
						this.window.postMessage({
							'evt': 'blind'
							,'room': room
							,'member_srl': p.member_srl
							,'nick_name': p.nick_name
							,'level': p.level_icon
							,'hours': _msg
							,'is_ignore_message':!!_msg
						}, this.origin );
					});
				});
				setCookie('elkhacord_ignore_members', ignore_members);

			});
			return;
		}

		$.ajax({
			'type': "POST",
			'dataType': "json",
			'url': window.elkhatalk_config.request_uri,
			'contentType': 'application/json',
			'data': params
		}).done(function(p){
			if(typeof(p.iText)=='string')
			{
				$textarea.val(p.iText);
			}

			if($.isArray(p.content_read) && p.content_read.length)
			{
				send_message(p);
			}

			if(!p.error)
			{
				$textarea.parents('.elkhacord-iframe .box_type:has(.more_layer_open)').find('.more button').trigger('click');
				return;
			}
			if(typeof(p.iText)!='string')
			{
				$textarea.val( params.content );
			}

			$.each(iframe_id_list[params.room], function(){
				this.window.postMessage({
					'evt': 'say2'
					, 'error': -1
					, 'content': params.content
					, 'message': p.message
				}, this.origin );
			});
		}).fail(function(p){
			$.each(iframe_id_list[params.room], function(){
				this.window.postMessage({
					'evt': 'say2'
					, 'error': -1
					, 'content': params.content
					, 'message': (function(){
						try
						{
							return p.statusText.toString();
						}
						catch (e)
						{
							return 'Server error!';
						}
					})()
				}, this.origin );
			});
		});
	}

	var _color_scheme_init = '';
	$(function(){
		_color_scheme_init = $('body').hasClass('color_scheme_dark') ? 'dark' : 'light';
	});
	var _dark_mode = null;
	var $style = $('<style></style>');
	var elkhacord_notice_list = {};
	$(function(){
		if(typeof(window.MutationObserver) == 'function' && window.elkhatalk_config.dark_mode === 'A')
		{
			var observer = new MutationObserver(function(mutations){
				mutations.forEach(function(mutation){
					if (mutation.attributeName !== 'class')
					{
						return;
					}
					$.each(iframe_id_list, function(room, val){
						$.each(val, function(){
							this.window.postMessage({
								'dark_mode': !!$('body').hasClass('color_scheme_dark')
							}, this.origin);
						});
					});
				});
			});
			observer.observe($('body')[0], {
				'attributes': true, // 속성 변화를 감시
				'attributeFilter': ['class'] // class 속성만 감시
			});
		}
		$('head').append($style);
	});
	var _color_scheme_style = function(mode)
	{
		if(typeof(mode) == 'string' && mode === 'F')
		{
			$style.text('.elkhacord, .elkhacord-iframe { background-color:#111; }');
		}
		else if(typeof(mode) !== 'string' || mode === 'A')
		{
			$style.text('.elkhacord, .elkhacord-iframe { background-color:#fff; } .color_scheme_dark .elkhacord, .color_scheme_dark .elkhacord-iframe { background-color:#111; }');
		}
		else
		{
			$style.text('.elkhacord, .elkhacord-iframe { background-color:#fff; }');
		}
	}
	window.elkhacord_init = function()
	{
		if(_dark_mode !== window.elkhatalk_config.dark_mode)
		{
			_dark_mode = window.elkhatalk_config.dark_mode;
			_color_scheme_style(_dark_mode);
		}
		$('div.elkhacord').each(function(){
			if(typeof(this.___iframe)=='number')
			{
				this.___iframe++;
				return;
			}
			this.___iframe = 1;
			$(this).removeClass('elkhacord').addClass('elkhacord-iframe');
			var args = window['elkhacord_'+ $(this).data('iframe')];

			var components = '';
			if(args.components.length > 1)
			{
				components = '<div class="box_more"><div class="more_layer">'+args.components.join('')+'</div><span class="more component"><button type="button" onclick="jQuery(this).parent().prev(\'.more_layer\').toggleClass(\'more_layer_open\');" title="'+getLang('components')+'">'+getLang('components')+'</button></span></div>';
			}
			else if(args.components.length == 1)
			{
				components = '<div class="box_more">'+args.components[0]+'</div>';
			}


			var $box_type = $('<div class="box_type components'+args.components.length+'"><div class="input">'+components+'<div class="iText-wrap"><textarea class="iText" rows="1" cols="20" data-target_srl="'+args.params.room+'"></textarea></div><span class="clear"><button type="button" title="Clear" tabindex="-1">Clear</button></span><span class="submit"><button type="button" title="전송">전송</button></span></div></div>')
				/*.appendTo( $(this) )*/;

			var $input = $box_type.find('.input');

			var $clear = $box_type.find('.clear button').on('click', function(e){
				$input.removeClass('focus').find('.iText').val('').focus().trigger('keypress').trigger('keyup')
					[0].parentNode.dataset.replicatedValue = ''; // 높이 리셋
			});

			var $textarea = $box_type.find('textarea')
				.attr('placeholder', getLang('shift_enter'))
				.on('focus change keyup', function(e){
					var iText = this;
					setTimeout(function(){
						if($(iText).val().length)
						{
							$input.addClass('focus');
						}
						else
						{
							$input.removeClass('focus');
						}
					}, 1);
				})
				.on('input keyup', function(e){
					this.parentNode.dataset.replicatedValue = this.value;
				})
				.on('keyup', function(e){
					if( $(this).hasClass('keyup_locked') )
					{
						return;
					}
					if( ($.inArray( $(this).val(), message_list ) < 0 && $(this).val().length)/* || $(this).val().match(/\n/)*/ )
					{
						return;
					}
					if(e.keyCode==38) // up
					{
						message_list_idx--;
					}
					else if(e.keyCode==40) // down
					{
						message_list_idx++;
					}
					else
					{
						return;
					}
					if(message_list.length <= message_list_idx)
					{
						message_list_idx = 0;
					}
					else if(message_list_idx < 0)
					{
						message_list_idx = message_list.length - 1;
					}
					$(this).val( message_list[message_list_idx] );
				})
				.on('keypress', function(e){
					var keycode = e.keyCode? e.keyCode : e.charCode;
					if(keycode === 13 && !e.shiftKey)
					{
						_submit($textarea, $clear, {'room':args.params.room});
						return false;
					}
				})
				.on('keydown', function(e){
					if(e.keyCode==27 && $(this).val()!='')
					{
						$clear.trigger('click');
						return false;
					}
				});

			var $submit = $box_type.find('.submit button').on('click', function(e){
				_submit($textarea, $clear, {
					'room':args.params.room
				});
			});

			var $iframe = $('<div class="iframe"><iframe allow="autoplay" src="about:blank;" frameborder="no" scrolling="no"></iframe></div>');
			// https://stackoverflow.com/questions/7000190/detect-all-firefox-versions-in-js
			/*if(navigator.userAgent.toLowerCase().indexOf('firefox') > -1)
			{
				$iframe.addClass('ff');
			}*/
			$(this).append($iframe, $box_type);
			$iframe.children('iframe').attr('src', args.src + '?' + $.param(args.params));

		});
	}
	window.elkhacord_sumbit = function()
	{
	}
	//크롬 오류 때문에.. 빠르게 못 하겠다 window.elkhacord_init();
	$(window.elkhacord_init);

	window.addEventListener('message', function(e){
		if(e.origin.replace(/^https?:\/\//, '')!=window.elkhatalk_config.host)
		{
			return;
		}
		if(e.data.member_srl+''!=window.elkhatalk_config.auth_info.member_srl+'')
		{
			return;
		}
		// 2단계
		if(e.data.act=='auth_info')
		{
			window.elkhatalk.connect_triggers[e.data.iframe_id] = function(socket)
			{
				e.source.postMessage({
					'act': 'auth_info'
					,'auth_info': window.elkhatalk_config.auth_info
					,'request_uri': window.elkhatalk_config.request_uri
					,'my_info_url': window.elkhatalk_config.my_info_url
					,'popup_size':window.elkhatalk_config.popup_size
					,'cdn_path': window.elkhatalk_config.cdn_path
					,'_lang': (function(){
						var l = {};
						$.each(_lang, function(k,v){
							l[k.replace(/^elkhatalk3_/, '')] = v;
						});
						return l;
					})()/*{
						'call_me': window.elkhatalk_config.call_me
						, 'call_you': window.elkhatalk_config.call_you
						, 'call_failed': window.elkhatalk_config.call_failed
						, 'blind': window.elkhatalk_config.blind
						, 'blind_cancel': window.elkhatalk_config.blind_cancel
					}*/
				}, e.origin );
				var params = {
					'last_update': (new Date()).getTime()
					,'iframe_id': e.data.iframe_id
					,'window': e.source
					,'origin': e.origin
				};
				if($.isArray(iframe_id_list[e.data.room]))
				{
					var this_is_new_iframe = true;
					for(var i=0; i<iframe_id_list[e.data.room].length; i++)
					{
						if(iframe_id_list[e.data.room][i].window==e.source)
						{
							this_is_new_iframe = false;
						}
					}
					if(this_is_new_iframe)
					{
						iframe_id_list[e.data.room].push(params);
					}
				}
				else
				{
					iframe_id_list[e.data.room] = [];
					iframe_id_list[e.data.room].push(params);
				}
				// 채널 입장 (겹칠 수 있으니 1회만)
//				if(iframe_id_list[e.data.room].length==1)
				{
					/**/<?php _console("'login_public: ' + e.data.room"); ?>/**/
					socket.emit('login_public', {
						'room': e.data.room
						,'open_room_clients': true
						,'list_count': e.data.list_count
					});
				}
//				else
				{
					// todo: 중복 접속 하더라도 접속자 목록 가져오기.
				}
				/*if(window.elkhatalk_config.public_chat_log=='Y')
				{
					$.ajax({
						type: "POST",
						dataType: "json",
						url: request_uri,
						'contentType': 'application/json',
						data: {'module':'elkhatalk3','act':'getElkhatalk3PublicChat','room':e.data.room}
					}).done(function(p){
						if(p.error)
						{
							e.source.postMessage({
								'evt': 'alert'
								, 'message': p.message
								, 'timeout': 5000
							}, e.origin );
							return;
						}
						else if($.isArray(p.data) && p.data.length)
						{
							e.source.postMessage({
								'evt': 'listen'
								,'data': p.data
							}, e.origin );
						}
					});
				}*/
			}
			if(typeof(window.elkhatalk.connect_triggers._)=='function')
			{
				window.elkhatalk.connect_triggers._( window.elkhatalk.connect_triggers[e.data.iframe_id] );
			}
		}
		else if(e.data.act=='notice_list')
		{
			var _this = this;
			$.ajax({
				'type': 'GET',
				'dataType': 'json',
				'cache': true,
				'async': true,
				'url': window.elkhatalk_config.request_uri + 'files/elkhatalk3/' + e.data.room + '.json',
				'contentType': 'application/json',
				'data': {'ntime':e.data.ntime}
			}).always(function(p){
				if(typeof(p)=='object' && p!==null && $.isArray(p.notice_list))
				{
					e.source.postMessage({
						'evt': 'notice_list'
						, 'notice_list': p.notice_list
					}, e.origin );
				}
				get_new_messages();
			});
		}
		else if(e.data.act=='emit')
		{
			socket.emit(e.data.evt, e.data.params);
		}
		/*else if(e.data.act=='i_am_alive')
		{
			if($.isArray(iframe_id_list[e.data.room]))
			{
				(function(){
					for(var i = 0; i < iframe_id_list[e.data.room].length; i++)
					{
						if(iframe_id_list[e.data.room][i].iframe_id!=e.data.iframe_id)
						{
							continue;
						}
						iframe_id_list[e.data.room][i].last_update = (new Date()).getTime();
					}
				})();
			}
		}*/
		else if(e.data.act === 'menu_toggle_html')
		{
			var mid = typeof(window.elkhatalk_config.auth_info.mid)=='string'? window.elkhatalk_config.auth_info.mid : '';
			$.ajax({
				'type': "POST",
				'dataType': "json",
				'url': window.elkhatalk_config.request_uri,
				'contentType': 'application/json',
				'data': {'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'getElkhatalk3PublicMenu','room':e.data.room,'current_mid':mid,'room_title':e.data.room_title}
			}).done(function(p){
				if(p.error || !$.isArray(p.menu_list))
				{
					return;
				}
				var params = {
					'evt': 'menu_toggle_html',
					'menu_list': p.menu_list,
					'show_help': p.show_help,
					'open_popup': p.open_popup,
					'help': p.help,
					'hide_old_manager': p.hide_old_manager
				};
				if(typeof(p.lang_admin) == 'string')
				{
					params.lang_admin = p.lang_admin;
				}
				if(typeof(p.lang_dark_mode) == 'string')
				{
					params.lang_dark_mode = p.lang_dark_mode;
				}
				e.source.postMessage(params, e.origin );
			});
		}
		/*else if(e.data.act=='components')
		{
			e.source.postMessage({
				'evt': 'components'
				, 'components': ($.isArray(window.elkhatalk.components2)? window.elkhatalk.components2 : [])
			}, e.origin );
		}*/
		else if(e.data.act=='component_click')
		{
			if(typeof(window[e.data.callback])=='function')
			{
				window[e.data.callback](e.data);
			}
		}
		else if(e.data.act === 'confirm_message')
		{
			if(typeof(e.data.params.__check_input_focus)=='boolean' && e.data.params.__check_input_focus && $('.iText:focus').data('target_srl')!=e.data.params.__room)
			{
				return;
			}
			var use_socket = 'N';
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
			$.ajax({
				'type': "POST",
				'dataType': "json",
				'url': window.elkhatalk_config.request_uri,
				'contentType': 'application/json',
				'data': {'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'procElkhatalk3Read','target_srl':e.data.params.member_srl,'member_srl':member_srl,'use_socket':use_socket}
			}).done(function(p){
				if($.isArray(p.message_list))
				{
					$.each(p.message_list, function(key, val){
						$.each(iframe_id_list, function(room){
							$.each(this, function(){
								this.window.postMessage({
									'evt': 'sendMessage'
									,'room': room
									,'room_no': val.room_no
									,'member_srl': val.member_srl
									,'nick_name': val.nick_name
									,'level': val.level
									,'time': val.time * 1000
									, '_msg': val.content
									,'query': false
									,'content_no': val.no
									,'content_read': val.read
								}, this.origin );
							});
						});
					});
				}
			});
		}
		else if(e.data.act=='m_e_i_l')
		{
			if(!$.isArray(m_e_i_l[e.data.params.target_srl+'']) || m_e_i_l[e.data.params.target_srl+'']===null)
			{
				$.ajax({
					'type': "POST",
					'dataType': "json",
					'url': window.elkhatalk_config.request_uri,
					'contentType': 'application/json',
					'data': $.extend({'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'getElkhatalk3MemberMenu','room':e.data.room}, e.data.params)
				}).done(function(p){
					if(p.error || !$.isArray(p.menu_list))
					{
						e.source.postMessage({
							'evt': 'alert'
							, 'error': -1
							, 'message': p.message
							,'timeout':5000
						}, e.origin );
						return;
					}
					m_e_i_l[e.data.params.target_srl+''] = p.menu_list;

					e.source.postMessage({
						'evt': 'm_e_i_l'
						, 'menu_list': m_e_i_l[e.data.params.target_srl+'']
						,'left': e.data.params.left
						,'top': e.data.params.top
					}, e.origin );
					setTimeout(function(){
						delete(m_e_i_l[e.data.params.target_srl+'']);
					}, 10000);
				});
			}
			else
			{
				e.source.postMessage({
					'evt': 'm_e_i_l'
					, 'menu_list': m_e_i_l[e.data.params.target_srl+'']
					,'left': e.data.params.left
					,'top': e.data.params.top
				}, e.origin );
			}
		}
		else if(e.data.act=='iText')
		{
			$('.elkhacord-iframe[data-room='+e.data.params.room+'] .iText').val(e.data.params.iText).focus().trigger('input');
		}
		else if(e.data.act=='getElkhatalk3Message')
		{
			var request_key = e.data.request_key+'';
			getElkhatalk3Message(e.data.no_list, e.data.room_no, e.data.target_srl, request_key);
		}
		else if(e.data.act=='delete_message')
		{
			$.ajax({
				'type': "POST",
				'dataType': "json",
				'url': window.elkhatalk_config.request_uri,
				'contentType': 'application/json',
				'data': $.extend({'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'procElkhatalk3Delete2','_member_srl':member_srl}, e.data.params)
			}).done(function(p){
				if(p.error)
				{
					e.source.postMessage({
						'evt': 'alert'
						, 'error': -1
						, 'message': p.message
						,'timeout':5000
					}, e.origin );
				}
			});
		}
		else if(e.data.act==='style')
		{
			var _style = $('.elkhacord-iframe[data-room='+e.data.room+']').data('style');
			if(typeof(_style)=='string' && _style!='')
			{
				if(window.elkhatalk_config.cdn_path.match(/^https/i))
				{
					e.source.postMessage({
						'style': _style
					}, e.origin);
				}
				else
				{
					$.ajax({
						'url': _style,//_style.replace(/\.css(?=\?|$)/, '.css.php'),
						'cache': true,
						'dataType': 'text',
						'type': 'GET',
						'async': true
					}).done(function(style_contents){
						var css_path = _style.replace(/[^\/]+$/, '');
						style_contents = style_contents.replace(/\s*url\s*\(\s*(?!https?:|\/\/)/ig, 'url(' + css_path);
						e.source.postMessage({
							'style_contents': style_contents
						}, e.origin);
					});
				}
			}
		}
		else if(e.data.act === 'dark_mode')
		{
			var dark_mode = !!(_dark_mode === 'F' || (_dark_mode === 'A' && $('body').hasClass('color_scheme_dark')));
			if(typeof(e.data.dark_mode) == 'string')
			{
				if(e.data.dark_mode === 'toggle')
				{
					dark_mode = !dark_mode;
				}
				window.elkhatalk3_dark_mode(dark_mode);
			}
			e.source.postMessage({
				'dark_mode': dark_mode
			}, e.origin);
		}
	}, false);

	window.elkhatalk3_dark_mode = function(dark_mode)
	{
		setCookie('rx_color_scheme', 'auto', -1, '/');
		if(dark_mode)
		{
			$('body').removeClass('color_scheme_light').addClass('color_scheme_dark');
			if(_color_scheme_init === 'light')
			{
				setCookie('rx_color_scheme', 'dark', 365, '/');
			}
		}
		else
		{
			$('body').removeClass('color_scheme_dark').addClass('color_scheme_light');
			if(_color_scheme_init === 'dark')
			{
				setCookie('rx_color_scheme', 'light', 365, '/');
			}
		}

		// 이미 로딩된 모든 채팅방에 적용하기.
		$.each(iframe_id_list, function(room, val){
			$.each(val, function(){
				if(e.source === this.window)
				{
					return;
				}
				this.window.postMessage({
					'dark_mode': dark_mode
				}, this.origin);
			});
		});
	}
	$(document).on('click', '.elkhatalk3_dark_mode', function(e){
		window.elkhatalk3_dark_mode( !$('body').hasClass('color_scheme_dark') );
	});
	var getElkhatalk3MessageRequestList = {};
	var getElkhatalk3MessageNoList = {};
	var getElkhatalk3Message = function(no_list, room_no, target_srl, request_key)
	{
		if(typeof(getElkhatalk3MessageRequestList[request_key])=='number')
		{
			getElkhatalk3MessageRequestList[request_key]++;
			getElkhatalk3MessageNoList[request_key] = $.merge(getElkhatalk3MessageNoList[request_key], no_list);
		}
		else
		{
			getElkhatalk3MessageRequestList[request_key] = 1;
			getElkhatalk3MessageNoList[request_key] = no_list;
		}
		var iframe_length = 0;
		$.each(iframe_id_list, function(room, val){
			$.each(val, function(){
				iframe_length++;
			});
		});
		if(iframe_length!=getElkhatalk3MessageRequestList[request_key])
		{
			return;
		}
		var _no_list = $.unique(getElkhatalk3MessageNoList[request_key]).join(',');
		delete(getElkhatalk3MessageRequestList[request_key]);
		delete(getElkhatalk3MessageNoList[request_key]);
		if(_no_list=='')
		{
			return;
		}
		$.ajax({
			'type': "POST",
			'dataType': "json",
			'url': window.elkhatalk_config.request_uri,
			'contentType': 'application/json',
			'data': {'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'getElkhatalk3Message','member_srl':member_srl,'target_srl':target_srl,'room_no':room_no,'no_list':_no_list},
			'success' : function(p){
				if($.isArray(p.message_list))
				{
					$.each(p.message_list, function(key, val){
						$.each(iframe_id_list, function(room){
							$.each(this, function(){
								this.window.postMessage({
									'evt': 'sendMessage'
									,'room': room
									,'room_no': val.room_no
									,'member_srl': target_srl
									,'nick_name': val.nick_name
									,'level': val.level
									,'time': val.time * 1000
									, '_msg': val.content
									,'query': (val.member_srl+''==member_srl+'')
									,'content_no': val.no
									,'content_read': val.read
								}, this.origin );
							});
						});
					});
				}
			}
		});
	}
	var m_e_i_l = {};
	window.elkhatalk_custom_notify_elkhacord = function(p)
	{
		var room = [];
		if(typeof(p.room)=='string')
		{
			room = [$.trim(p.room)];
		}
		else if($.isArray(p.room))
		{
			room = p.room;
		}
		if(!room.length || room[0]=='')
		{
			console.log( p );
			return;
		}

		// 무시 기능.
		if(p.evt === 'sendMessage' && check_ignore(p.member_srl))
		{
			return;
		}
		else if(p.evt === 'login_public')
		{
			if($.isArray(p.chat_list))
			{
				var slice_list = [];
				$.each(p.chat_list , function(i){
					if(check_ignore(this.member_srl))
					{
						slice_list.push(i);
					}
				});
				slice_list = slice_list.reverse();
				while(slice_list.length) (function(i){
					p.chat_list.splice(i, 1);
				})(slice_list.shift());
			}

		}
		else if(p.evt === 'new_messages')
		{
			get_new_messages();
		}

		$.each(room, function(k,v){
			var matches = window.elkhatalk_config.auth_info.hash.match(/^([0-9]+)_/);
			if(matches && v.indexOf(window.elkhatalk_config.auth_info.host + '_' + matches[1]+'@')===0)
			{
				v = v.replace(window.elkhatalk_config.auth_info.host + '_' + matches[1]+'@', '');
			}
			else if( v.indexOf(window.elkhatalk_config.auth_info.host+'@')===0 )
			{
				v = v.replace(window.elkhatalk_config.auth_info.host + '@', '');
			}
			for(var i=0; i<iframe_id_list[v].length; i++)
			{
				iframe_id_list[v][i].window.postMessage(p, iframe_id_list[v][i].origin);
			}
		});
	}
	if(member_srl > 0 && typeof(window.elkhatalk_config.ping_timeout)=='number')
	{
		setInterval(function(){
			$.ajax({
				'type': "POST",
				'dataType': "json",
				'url': window.elkhatalk_config.request_uri,
				'contentType': 'application/json',
				'data': {'_rx_csrf_token':window._rx_csrf_token,'module':'elkhatalk3','act':'getElkhatalk3PingSession','member_srl':member_srl}
			}).done(function(p){
				if(p.error)
				{
					alert(p.message);
				}
			});
		}, window.elkhatalk_config.ping_timeout);
	}

	// 알림 허락 확인
	window.elkhatalk3_checkNotificationPromise = function()
	{
		try {
			Notification.requestPermission().then();
		} catch(e) {
			return false;
		}
		return true;
	}
	// 알림 권한 요청
	window.elkhatalk3_askNotificationPermission = function(callback)
	{
		// Old school 방식을 체크
		if (!('Notification' in window)) {
			alert( getLang('notify_not_supported') );
		} else {
			if(window.elkhatalk3_checkNotificationPromise()) {
				Notification.requestPermission()
				.then(function(permission){
					handlePermission(permission, callback);
				})
			} else {
				Notification.requestPermission(function(permission) {
					handlePermission(permission, callback);
				});
			}
		}
	}
	// 알림 권한 처리
	var handlePermission = function(permission, callback)
	{
		if(permission=='granted')
		{
			alert( getLang('notify_activated') );
		}
		else
		{
			alert( getLang('notify_deactivated') );
		}
		if(typeof(callback)=='function')
		{
			callback();
		}
	}
	var showNotification = function(title, options)
	{
		if(typeof(window.lazypush_get_messaging)=='boolean' && window.lazypush_get_messaging)
		{
			return;
		}
		try
		{
			if('Notification' in window && Notification.permission=='granted')
			{
				new Notification(title, options);
			}
		}
		catch (e)
		{
		}
	}
//})();
});


<?php
readfile(__DIR__.'/socket.io.min.js');
function _console($msg)
{
	$bt = debug_backtrace();
	if(is_array($bt)) $first = array_shift($bt);
	$line = $first['line'];

	echo 'console.log(';
	echo "'($line) '+";
	echo $msg;
	echo ');';
}
