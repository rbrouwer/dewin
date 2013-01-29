var maxId = 0;
function receive(){
	$.ajax({
		url: callbackUrl,
		dataType: 'json',
		data: { minId: maxId },
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
			if (typeof data.exit != 'undefined') {
				$("#progressbar").addClass('animeter').removeClass('activeanimeter');
				if (data.exit === 'Success') {
					if (debugMode > 0) {
						prepareDebug(successUrl);
					} else {
						window.location=successUrl;
					}
				} else {
					if (debugMode > 0) {
						prepareDebug(failUrl);
					} else {
						window.location=failUrl;
					}
				}
			}
			if (typeof data.maxId != 'undefined') {
				maxId=data.maxId;
			}
			if (typeof data.stdout != 'undefined') {
				for (i in data.stdout) {
					handleOutput(data.stdout[i].replace(/<\/span>/g, "</span>\n"));
				}
			}
		},
		error: function(){
			$("#progressbar").addClass('animeter').removeClass('activeanimeter');
		}
	});
}

function handleOutput(output){
	if(output != "" || output != null){
		var line = $(output);
		
		// Add it to the console
		$("#console").append( line );
		
		// Color/other ANSI removal!
		line.find('tt').css('color', '').css('background-color', '').css('font-weight', '').css('text-decoration', '').css('blink', '');
		
		// Need to add the \n for the pre style
		$("#console").append( "\n" );
		
		// Scroll to the end
		$('#console').scrollTop($('#console')[0].scrollHeight);
	}
}

function prepareDebug(url){
	$('#nextbutton').removeClass('disabled').removeClass('secondary').attr('href', url);
	$('#debugButton').removeClass('disabled').removeClass('secondary');
}

$(document).ready(function() {
	setInterval("receive()", 2500);
	receive();
	$(".showInformation").click(function() {
			$('.information').toggle();
	});
});