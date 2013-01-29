function updateNextButton() 
{
	if ($("#required").val() == '') {
		$('#nextbutton').addClass('disabled').addClass('secondary');
	} else {
		$('#nextbutton').removeClass('disabled').removeClass('secondary');
	}
}

$(document).ready(function(){
	$('#required').change(function() {
		updateNextButton()
		});
	updateNextButton()
});