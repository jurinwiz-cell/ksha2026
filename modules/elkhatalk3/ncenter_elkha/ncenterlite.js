(function($){
	var getElkhatalk3ClickCount = 0,
		getElkhatalk3Timeout = setTimeout(function(){}, 1);
	window.$$ncenterlite =
	{
		'total_count': 0
		,$nc_container: $()
		,'list':[]
		,'useProfileImage': 'Y'
		,'page':1
		,'close': function()
		{
			if(this.$nc_container.find('.list-ncenter').length)
			{
				this.$nc_container.find('.list-ncenter').remove();
				this.$nc_container.children('.list:not(:has(ul))').remove();
				return false;
			}
			this.$nc_container.hide();
			return false;
		}
		,'init': function(nc_container, params)
		{
			this.$nc_container = $(nc_container);
			this.total_count = params.total_count;
		}
		,'update_count': function(total_count, has_more_check)
		{
			this.total_count = total_count;
			this.$nc_container.find('.nc_menu .notify strong').text(total_count);
			var has_more = false;
			if(total_count)
			{
				this.$nc_container.removeClass('count-0');
				if(total_count > 5)
				{
					has_more = true;
				}
			}
			else
			{
				this.$nc_container.addClass('count-0');
			}
			if(has_more && has_more_check)
			{
				this.$nc_container.addClass('has_more');
			}
			this.$nc_container.show();
		}
		,'getMyNotifyListTpl': function(params)
		{
			var _this = this;

			// 너무 여러번 클릭되지 않게.
			getElkhatalk3ClickCount++;
			setTimeout(function(){
				getElkhatalk3ClickCount--;
			}, 3000);
			if(getElkhatalk3ClickCount <= 10)
			{
				clearTimeout(getElkhatalk3Timeout);
			}
			else
			{
				clearTimeout(getElkhatalk3Timeout);
				getElkhatalk3Timeout = setTimeout(function(){
					_this.getMyNotifyListTpl(params);
				}, 1000);
				return false;
			}

			if(_this.$nc_container.find('.list-ncenter').length && params.page <= 1 && _this.page <= 1)
			{
				_this.$nc_container.find('.list-ncenter').remove();
				_this.$nc_container.children('.list:not(:has(ul))').remove();
				_this.$nc_container.removeClass('has_more');
				return false;
			}
			if(this.total_count <= 0)
			{
				return false;
			}
			_this.page = params.page;
			$.exec_json('ncenterlite.getMyNotifyListTpl', params, function(p){
				_this.list = p.list;
				_this.useProfileImage = p.useProfileImage;
				try
				{
					_this.update_count(p.list.page.total_count, true);
				}
				catch (e)
				{
					_this.update_count(0, true);
				}
				// 다음을 숨기기
				if(p.list.page.cur_page >= p.list.page.last_page)
				{
					_this.$nc_container.removeClass('has_more');
				}
				// 결과가 없으면 취소.
				if(p.cur_page > 1 && (!$.isArray(p.list.data) || !p.list.data.length))
				{
					return;
				}
				_this.compile();
			});
			return false;
		}
		,'compile': function()
		{
			var _this = this;
			_this.$nc_container.find('.list-ncenter').remove();
			var $ul = $('<ul class="list-ncenter"></ul>');
			$.each(_this.list.data, function(){
				var $li = $('<li></li>').appendTo($ul);
				var $a = $('<a></a>').attr('href', this.url).appendTo($li);
				if(_this.useProfileImage=='Y' && $.trim(this.profileImage).length)
				{
					var $img = $('<img src="" alt="" />').attr('src', this.profileImage).appendTo($a);
					$li.addClass('profileImage');
				}
				var $msg = $('<span class="msg"></span>').html(this.text).appendTo($a);
				var $ago = $('<span class="ago"></span>').text(this.ago).appendTo($li);
			});
			var $list = _this.$nc_container.children('.list');
			if($list.length)
			{
				$list.append($ul);
				$list.appendTo(_this.$nc_container);
			}
			else
			{
				$('<div class="list"></div>').html($ul).appendTo(_this.$nc_container);
			}
		}
		,'delete_all': function()
		{
			if(!confirm('모두 지우겠습니까?'))
			{
				return false;
			}
			exec_xml('ncenterlite', 'procNcenterliteNotifyReadAll');
			this.update_count(0, true);
			this.$nc_container.find('.list-ncenter').remove();
			this.$nc_container.children('.list:not(:has(ul))').remove();
			return false;
		}
		,'more': function()
		{
			this.page++;
			this.getMyNotifyListTpl({'page': this.page});
			return false;
		}
		,'highlight': function()
		{
			this.$nc_container.find('.nc_menu').fadeIn().fadeOut().fadeIn().fadeOut().fadeIn();
		}
	};

	window.elkhatalk_custom_notify_ncenter = function(p)
	{
		var _count = 1;
		try
		{
			_count = typeof(p.count)=='number'? p.count : _count;
		}
		catch (e)
		{
		}
		window.$$ncenterlite.update_count( window.$$ncenterlite.total_count + _count, false);
		if(window.$$ncenterlite.total_count + _count > 0)
		{
			window.$$ncenterlite.highlight();
			if(typeof(window.epush_trigger)=='string')
			{
				$.get(window.epush_trigger);
			}
		}
	}

	/*// https://krasimirtsonev.com/blog/article/Javascript-template-engine-in-just-20-line
	var TemplateEngine = function(html, options) {
		var re = /<%([^%>]+)?%>/g, reExp = /(^( )?(if|for|else|switch|case|break|{|}))(.*)?/g, code = 'var r=[];\\n', cursor = 0, match;
		var add = function(line, js) {
			js? (code += line.match(reExp) ? line + '\\n' : 'r.push(' + line + ');\\n') :
				(code += line != '' ? 'r.push("' + line.replace(/"/g, '\\\\"') + '");\\n' : '');
			return add;
		}
		while(match = re.exec(html)) {
			add(html.slice(cursor, match.index))(match[1], true);
			cursor = match.index + match[0].length;
		}
		add(html.substr(cursor, html.length - cursor));
		code += 'return r.join("");';
		return new Function(code.replace(/[\\r\\t\\n]/g, '')).apply(options);
	}*/
})(jQuery);