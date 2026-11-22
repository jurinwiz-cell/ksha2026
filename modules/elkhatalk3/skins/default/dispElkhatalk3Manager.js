(function($){
	window.procElkhatalk3ManageDeleteNotice = function(k)
	{
		if(!confirm('confirm?'))
		{
			return;
		}
		$.exec_json('elkhatalk3.procElkhatalk3ManageDeleteNotice', {
			'key': k
			,'room':current_url.getQuery('room')
			,'_member_srl': window.elkhatalk_config.auth_info.member_srl
		}, function(p){
			if(p.error)
			{
				alert(p.message);
				return;
			}
			location.reload();
		});
	}
	window.procElkhatalk3ManageDelete = function(target_srl, grants_remove)
	{
		if(!confirm('confirm?'))
		{
			return;
		}
		var params = {};
		params.member_srl_delete = target_srl;
		params.room = current_url.getQuery('room');
		params._member_srl = window.elkhatalk_config.auth_info.member_srl;
		params.grants_remove = grants_remove;
		$.exec_json('elkhatalk3.procElkhatalk3Manage', params, function(p){
			if(p.error)
			{
				alert(p.message);
				return;
			}
			location.reload();
		});
	}
	window.procElkhatalk3ManageClear = function(room)
	{
		if(!confirm('confirm?'))
		{
			return;
		}
		var params = {};
		params.room_clear = 'Y';
		params.room = current_url.getQuery('room');
		params.use_socket = 'Y';
		params._member_srl = window.elkhatalk_config.auth_info.member_srl;
		$.exec_json('elkhatalk3.procElkhatalk3Manage', params, function(p){
			if(p.error)
			{
				alert(p.message);
			}
		});
	}
	var chat_log = null;
	var grant_group_A = null;
	$(document).on('click', '#elkhatalk3_chat_log', function(e){
		chat_log = $(this).prop('checked');
	});
	$(document).on('click', '#elkhatalk3_grant_group_A', function(e){
		grant_group_A = $(this).prop('checked');
	});
	$(document).on('click', '.elkhatalk3_checkbox', function(e){
		if(chat_log==null)
		{
			chat_log = $('#elkhatalk3_chat_log').prop('checked');
		}
		if(grant_group_A==null)
		{
			grant_group_A = $('#elkhatalk3_grant_group_A').prop('checked');
		}
		if(this.name.match(/^cafe$|^group_srl/))
		{
			if(this.checked)
			{
				$('.elkhatalk3_checkbox[name=read],.elkhatalk3_checkbox[name=write]').prop('checked', false);
			}
		}
		else
		{
			if(this.checked)
			{
				$('.elkhatalk3_checkbox[name=cafe], .elkhatalk3_checkbox[name^=group_srl]').prop('checked', false);
				$('#elkhatalk3_grant_group_A').prop('disabled', true);
				if(this.name=='write')
				{
					$('.elkhatalk3_checkbox[name=read]').prop('checked', true);
				}
			}
			else if(this.name=='read')
			{
				$('.elkhatalk3_checkbox[name=write]').prop('checked', false);
			}
		}
		// elkhatalk3_grant_group_A
		if(this.name.match(/^group_srls/))
		{
			if(this.checked)
			{
				$('#elkhatalk3_grant_group_A').prop({'checked': grant_group_A, 'disabled': false});
			}
			else if( !$('.elkhatalk3_checkbox[name^=group_srls]:checked').length )
			{
				$('#elkhatalk3_grant_group_A').prop({'checked': false, 'disabled': true});
			}
		}
		// chat_log
		if($('.elkhatalk3_checkbox[name=read]').prop('checked'))
		{
			$('#elkhatalk3_chat_log').prop({'checked': chat_log, 'disabled': false});
		}
		else
		{
			$('#elkhatalk3_chat_log').prop({'checked': false, 'disabled': true});
		}
	});
	$(document).on('click', '#elkhatalk3_grant_cafe', function(e){
		$('input[name=nick_name_cafe]').prop('disabled', !this.checked);
	});
})(jQuery);