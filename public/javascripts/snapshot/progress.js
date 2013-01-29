function receive(){
	$.ajax({
		url: callbackurl,
		dataType: 'json',
		timeout: 2000,
		success: function(data){
			$("#progressbar").addClass('activeanimeter').removeClass('animeter');
			if (typeof data.status != 'undefined') {
				if (data.status == '') {
					$("#progresstext").html(defaultProcessText);
				} else {
					$("#progresstext").html(data.status);
				}
			}
			if (typeof data.percent != 'undefined') {
				$("#progressbar").css('width', data.percent+'%');
			}
			if (typeof data.member != 'undefined') {
				$("#member").html('Creating a snapshot of '+name+' for '+data.member+'.');
			}
			if (typeof data.exit != 'undefined') {
				$("#progressbar").addClass('animeter').removeClass('activeanimeter');
				if (data.exit === 'success') {
					window.location=successUrl;
				} else if (data.exit === 'fail') {
					// In case something failed,
					window.location=failUrl;
				} else if (data.exit === 'debugSuccess') {
					// Enable restart
					$('.debug').removeClass('disabled').removeClass('secondary').show();
					// Enable next LINK to successUrl (Hide the next button)
					$('#nextButton').hide().addClass('disabled').addClass('secondary');
					$('#nextLink').removeClass('disabled').removeClass('secondary').attr('href', successUrl).show();
				} else if (data.exit === 'debugFail') {
					// Enable restart
					$('.debug').removeClass('disabled').removeClass('secondary').show();
					// Enable next LINK to failUrl (Hide the next button)
					$('#nextButton').hide().addClass('disabled').addClass('secondary');
					$('#nextLink').hide().removeClass('disabled').removeClass('secondary').attr('href', failUrl).show();
				} else if (data.exit === 'debug')  {
					// Enable restart
					$('.debug').removeClass('disabled').removeClass('secondary').show();
					// Enable next BUTTON (Hide the next link button)
					$('#nextButton').removeClass('disabled').removeClass('secondary').show();
					$('#nextLink').hide().addClass('disabled').addClass('secondary').attr('href', '');
				}
			}
		},
		error: function(){
			$("#progressbar").addClass('animeter').removeClass('activeanimeter');
		}
	});
}

function prepareDebug(url){
	$('#nextbutton').removeClass('disabled').removeClass('secondary');
	$('#debugButton').removeClass('disabled').removeClass('secondary');
}

$(document).ready(function() {
	setInterval("receive()", 2500);
	receive();
});