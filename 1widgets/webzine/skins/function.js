jQuery(function($){

$(document).ready(function() {		
	
	$('#gallery a').eq(0).addClass('show');
	slideShow();

});

function slideShow() {

	$('#gallery a').css({opacity: 0.0});

	$('#gallery a:first').css({opacity: 1.0});

	$('#gallery .caption').css({opacity: 0.8});

	$('#gallery .caption').css({width: $('#gallery a').find('img').css('width')});

	$('#gallery .content').html($('#gallery a:first').find('img').attr('rel'))
	.animate({opacity: 0.8}, 300);
	
	var gallery = {
		curIndex: 0,
		len: $('#gallery a').length,
		intervalHandler: function() {

			var current = $('#gallery a').eq(this.curIndex);
			
			
			
			if (this.curIndex < this.len - 1)
			{
				this.curIndex++;
			}
			else
			{
				this.curIndex = 0;
			}
			

			var next = $('#gallery a').eq(this.curIndex);	
			

			
			var caption = next.find('img').attr('rel');	
			
			next.css({opacity: 0.0})
			.addClass('show')
			.animate({opacity: 1.0}, 1000);

			current.animate({opacity: 0.0}, 1000)
			.removeClass('show');
			
			$('#gallery .caption').animate({opacity: 0.0}, { queue:false, duration:0 }).animate({height: '1px'}, { queue:true, duration:300 });	

			$('#gallery .caption').animate({opacity: 0.8},100 ).animate({height: '100px'},300 ); //캡션의 높이와 속도를 조절 할 수 있습니다.
			
			$('#gallery .content').html(caption);	
			
		
		}
	};

	
	setInterval($.proxy(gallery.intervalHandler, gallery), 5000); //이미지가 바뀌는 시간을 조절 할 수 있습니다.

}


});
