jQuery(function($) {
	// user input text blur/focus/change
	var iText = $('.item .iLabel').next('.iText');
	$('.item .iLabel').css('position','absolute');
	iText
		.focus(function() {
			$(this).prev('.iLabel').css('visibility','hidden');
		})
		.blur(function() {
			if(!$(this).val()) {
				$(this).prev('.iLabel').css('visibility','visible');
			} else {
				$(this).prev('.iLabel').css('visibility','hidden');
			}
		})
		.change(function() {
			if(!$(this).val()) {
				$(this).prev('.iLabel').css('visibility','visible');
			} else {
				$(this).prev('.iLabel').css('visibility','hidden');
			}
		})
		.blur();
	// add class to the parent category 
	$('.cTab>li>ul>li.on_').parents('li:first').addClass('on');
	// delete the margin-top for the first child of the ccomments
	$('.feedback .xe_content>*:first-child').css('margin-top','0');
});
