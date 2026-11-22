(function($){
	window.procElkhatalk3AdminGetAuthKey = function(renew)
	{
		if(!confirm('go?'))
		{
			return false;
		}
		$.exec_json('elkhatalk3.procElkhatalk3AdminGetAuthKey', {'renew':renew}, function(p){
			alert(p.message);
			if(!p.error)
			{
				location.reload();
			}
		});
	}
	window.procElkhatalk3AdminInstall = function()
	{
		if(!confirm('go?'))
		{
			return false;
		}
		$.exec_json('elkhatalk3.procElkhatalk3AdminInstall', {}, function(p){
			alert(p.message);
			if(!p.error)
			{
				location.reload();
			}
		});
	}
	window.procElkhatalk3AdminUninstall = function()
	{
		if(!confirm('go?'))
		{
			return false;
		}
		$.exec_json('elkhatalk3.procElkhatalk3AdminUninstall', {}, function(p){
			alert(p.message);
			if(!p.error)
			{
				location.reload();
			}
		});
	}
	window.procElkhatalk3AdminInstall2 = function()
	{
		if(!confirm('go?'))
		{
			return false;
		}
		$.exec_json('elkhatalk3.procElkhatalk3AdminInstall2', {}, function(p){
			alert(p.message);
			if(!p.error)
			{
				location.reload();
			}
		});
	}
	window.procElkhatalk3AdminUninstall2 = function()
	{
		if(!confirm('go?'))
		{
			return false;
		}
		$.exec_json('elkhatalk3.procElkhatalk3AdminUninstall2', {}, function(p){
			alert(p.message);
			if(!p.error)
			{
				location.reload();
			}
		});
	}
	$(document).on('keyup change blur', '.html_embed input', function(e){
		var params = {};
		params.room = $('.html_embed').find('[name=room]').val();
		params.width = $('.html_embed').find('[name=width]').val();
		params.height = $('.html_embed').find('[name=height]').val();
		params.title = $('.html_embed').find('[name=title]').val();
		params.html_embed_iframe = $('.html_embed').find('[name=html_embed_iframe]:checked').val();
		$.exec_json('elkhatalk3.getElkhatalk3AdminEmbedHtml', params, function(p){
			$('#html_embed').val(p.html);
		});
	});
	/*$(document).on('click', '#test', function(e) {
		$('[name=host],[name=http_port],[name=https_port],[name=secret_key],[name=your_host],[name=your_scheme],[name=secret_key_send]').prop('disabled', !!$(this).prop('checked'));
	})*/
})(jQuery);
