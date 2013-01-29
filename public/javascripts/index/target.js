var remoteProject = "";
var lastTargetId = "";
var changeTestButton = false;
function validateTargetId(showerrors) {
	if ($("#requiredSelect").val() == '') {
		if (showerrors === true) {
			$("#requiredSelect").addClass('error')
			$("#errorTargetId").remove()
			$("#requiredSelect").next().next().after('<small class="error" id="errorTargetId">You should select a target server!</small>')
		}
		return false
	} else {
		if (showerrors === true) {
			$("#requiredSelect").addClass('error')
			$("#errorTargetId").remove()
		}
		lastTargetId = $("#requiredSelect").val()
		return true
	}
}

function validateDesiredUrl(showerrors) {
	baseUrl = $('#requiredSelect option:selected').attr('data');
	if (typeof baseUrl !== 'undefined' && baseUrl !== false) {
		var regex = new RegExp("^((.+?)://|)(www\.|)(.*)\."+baseUrl.replace("/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g", "\\$&")+"$","i");
		var replace = "$4"
	} else {
		var regex = new RegExp("^((.+?)://|)(www\.|)","i");
		var replace = ""
	}
	if (regex.test($("#requiredText").val())) {
		remoteProject = $("#requiredText").val().replace(regex,replace).split(".").reverse().join("_")
		if (showerrors === true) {
			$("#requiredText").removeClass('error')
			$("#errorUrl").remove()
		}
		return true;
	} else {
		if (showerrors === true) {
			$("#requiredText").addClass('error')
			$("#errorUrl").remove()
			if (typeof baseUrl !== 'undefined' && baseUrl !== false) {
				$("#requiredText").after('<small class="error" id="errorUrl">This desired url must be in the following format: http://[project name].'+baseUrl+'!</small>')
			} else {
				$("#requiredText").after('<small class="error" id="errorUrl">This desired url must be an url!</small>')
			}
		}
	}
}

function validateDbPass(showerrors) {
	if ($("#requiredText2").val() == '') {
		if (showerrors === true) {
			$("#requiredText2").addClass('error')
			$("#errorDbPass").remove()
			$("#requiredText2").after('<small class="error" id="errorDbPass">The database password is required.</small>')
		}
		return false
	}
	ajaxValidatePass(showerrors)
	return false
}

function ajaxValidatePass(showerrors) {
	$('#nextbutton').addClass('disabled').addClass('secondary');
	$.post(callbackurl, {
		"serverId":$("#requiredSelect").val(), 
		"url":$("#requiredText").val(), 
		"dbPass":$("#requiredText2").val()
	}, function(data){
		if (data.valid == true){
			if (showerrors === true) {
				$("#requiredText2").removeClass('error')
				$("#errorDbPass").remove()
			}
			$('#nextbutton').removeClass('disabled').removeClass('secondary');
			if (changeTestButton) {
				$('#test').removeClass('alert').addClass('success');
				changeTestButton = false;
			}
			return true
		} else {
			if (showerrors === true) {
				$("#requiredText2").addClass('error')
				$("#errorDbPass").remove()
				$("#requiredText2").after('<small class="error" id="errorDbPass">The database password is incorrect.</small>')
			}
			return false
		}
	}, 'json');
}

function passedChecks(showerrors) {
	if (showerrors === true) {
		$("#requiredText2").removeClass('error')
		$("#errorDbPass").remove()
	}
	$('#nextbutton').removeClass('disabled').removeClass('secondary');
	if (changeTestButton) {
		$('#test').removeClass('alert').addClass('success');
		changeTestButton = false;
	}
}


$(document).ready(function(){
	$('#requiredSelect').change(function() {
		$('#test').removeClass('success').removeClass('alert');
		if (validateTargetId(true)){
			makeSuggestion()
			$("#requiredText2").val('')
			$("#requiredText2").removeClass('error')
			$("#errorDbPass").remove()
			//if (!validateDesiredUrl(true) || !validateDbPass(false)) {
			if (!validateDesiredUrl(true)) {
				$('#nextbutton').addClass('disabled').addClass('secondary');
			}
		}
	});
	$('#requiredText').change(function() {
		$('#test').removeClass('success').removeClass('alert');
		//if (!validateTargetId(false) || !validateDesiredUrl(true) || !validateDbPass(($("#requiredText2").val() == ''))) {
		if (!validateTargetId(false) || !validateDesiredUrl(true)) {
			$('#nextbutton').addClass('disabled').addClass('secondary');
		} else {
			passedChecks();
		}
	});
	$('#requiredText2').change(function() {
		$('#test').removeClass('success').removeClass('alert');
		//if (!validateTargetId(true) || !validateDesiredUrl(true) || !validateDbPass(true)) {
		if (!validateTargetId(true) || !validateDesiredUrl(true)) {
			$('#nextbutton').addClass('disabled').addClass('secondary');
		} else {
			passedChecks();
		}
	});
	//if (!validateTargetId(false) || !validateDesiredUrl(false) || !validateDbPass(false)) {
	if (!validateTargetId(false) || !validateDesiredUrl(false)) {
		$('#nextbutton').addClass('disabled').addClass('secondary');
	} else {
		passedChecks();
	}
	
	$("#test").click(function() {
		changeTestButton = true;
		//if (!validateTargetId(true) || !validateDesiredUrl(true) || !validateDbPass(true)) {
		if (!validateTargetId(true) || !validateDesiredUrl(true)) {
			$('#nextbutton').addClass('disabled').addClass('secondary');
			$('#test').removeClass('success').addClass('alert');
		} else {
			passedChecks();
		}
	});
	
	$(".showInformation").click(function() {
		$('#information').toggle();
	});
});