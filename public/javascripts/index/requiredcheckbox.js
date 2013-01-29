var nextUrl;
function updateNextButton() 
{
	if ($('#required').is(':checked')) {
		$('#nextbutton').removeClass('disabled').removeClass('secondary');;
		$('#nextbutton').attr('href', nextUrl);
	} else {
		$('#nextbutton').addClass('disabled').addClass('secondary');;
		$('#nextbutton').removeAttr('href');
	}
}

$(document).ready(function(){
	nextUrl = $('#nextbutton').attr('href');
	$('#nextbutton').removeAttr('href')
	$('#required').change(function() {
		updateNextButton()
		});
	updateNextButton();
});