function updateNextButton() 
{
	if ($("input:radio.required:checked").val()) {
		$('#nextbutton').removeClass('disabled').removeClass('secondary');
	} else {
		$('#nextbutton').addClass('disabled').addClass('secondary');
	}
}

$(document).ready(function(){
	$('input:radio.required').change(function() {
		updateNextButton();
	});
	updateNextButton();
});