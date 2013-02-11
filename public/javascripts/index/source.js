var instServerId = 0;
var instCallback = '';

function ajaxInstanceFormFill() {
	var postData = {'serverId': $('select[name=serverId]').val()};
	if (typeof instFilterNew != 'undefined' && instFilterNew === true) {
		postData['filterNew'] = 1;
	}
	if (typeof instFilterSource != 'undefined' && instFilterSource === true) {
		postData['filterSource'] = 1;
	}
	
	var selectBox = $('select[name=instance]');
	selectBox.empty();
	selectBox.append('<option value="">Reloading! Please wait&hellip;</option>');
	
	$.ajax({
		url: callbackurlInstance,
		dataType: 'json',
		type: 'POST',
		data: postData,
		success: function(data){
			fillInstanceForm(data);
		},
		error: function(){
			var selectBox = $('select[name=instance]');
			selectBox.empty();
			selectBox.append('<option value="">Failed! Retrying in 3 seconds! Please wait&hellip;</option>');
			setTimeout(function() {ajaxInstanceFormFill();}, 3000);
		}
	});
}

function fillInstanceForm(data) {
	var selectBox = $('select[name=instance]');
	selectBox.empty();
	if (typeof instDefaultText != 'undefined') {
		selectBox.append('<option value="">'+instDefaultText+'&hellip;</option>');
	} else {
		selectBox.append('<option value="">Select an application&hellip;</option>');
	}

	
	$.each(data, function(key, value) {
		if (typeof value == 'object' ) {
			selectBox.append('<optgroup label="'+key+'">');
			$.each(value, function(optValue, name) {
			  selectBox.append('<option value="'+optValue+'">'+name+'</option>');
			});
			selectBox.append('</optgroup>');
		} else {
		  selectBox.append('<option value="'+key+'">'+value+'</option>');
		}
	});
	
	instServerId = $('select[name=serverId]').val();
	
	if (instCallback !== '') {
		window[instCallback]();
	}
}

$(document).ready(function(){
	$('select[name=serverId]').change(function(e) {
		ajaxInstanceFormFill();
	});
});

