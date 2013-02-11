var nextUrl;
function updateNextButton() 
{
	var nextButton = $('#nextbutton');
	if ($(".required").filter(
		function() {
			if ($(this).is('[type=checkbox]')) {
				console.log("is checkbox");
				return !$(this).is(':checked');
			} else if ($(this).is('[type=radio]')) {
				console.log("is radio");
				return !$("input:radio.required:checked").val();
			} else {
				console.log("is something else");
				var val = $(this).val();
				console.log(val);
				return val == '';
			}
		}
	).length > 0) {
		console.log('required test failed');
		nextButton.addClass('disabled').addClass('secondary');
		if (nextButton.is('a')) {
			nextUrl = nextButton.attr('href');
			console.log('Link is removed: ' +nextUrl);
			nextButton.removeAttr('href');
		}
	} else {
		console.log('required test passed');
		nextButton.removeClass('disabled').removeClass('secondary');
		if (nextButton.is('a')) {
			nextButton.attr('href', nextUrl);
		}
	}
}

$(document).ready(function(){
	$('.required').change(function() {
		updateNextButton()
	});
	
	updateNextButton();
});