var remoteProject = '';
var changeTestButton = false;
var selects = [];
var inputs = [];
var textareas = [];

var tagsToReplace = {
	'&': '&amp;',
	'"': '&quot;',
	'<': '&lt;',
	'>': '&gt;'
};

function makeSuggestion() {
	var attr = $('select[name=serverId] option:selected').attr('data');
	var value;
	console.debug(attr);
	if (typeof attr !== 'undefined' && attr !== '') {
		if (remoteProject != '') {
			value = "http://"+remoteProject.split("_").reverse().join(".")+"."+attr
		} else {
			value = "http://"+sourceProject+"."+attr
		}
	} else {
		if (remoteProject != '') {
			value = "http://"+remoteProject.split("_").reverse().join(".")
		} else {
			value = "http://"+sourceProject
		}
	}
	$("input[name=url]").val(value)
}

function setRemoteProject() {
	var baseUrl = $('select[name=serverId] option:selected').attr('data');
	var desiredUrl = $('input[name=url]').val();
	if (typeof desiredUrl !== 'undefined' && desiredUrl !== null) {
		desiredUrl = desiredUrl.replace(new RegExp("^((.+?)://|)(www\.|)"), '');
	} else {
		return;
	}
	if (typeof baseUrl !== 'undefined' && baseUrl !== null) {
		var regex = new RegExp("^(.*)\."+baseUrl.replace("/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g", "\\$&")+"$","i");
		var replace = "$1"
		if (regex.test(desiredUrl)) {
			remoteProject = desiredUrl.replace(regex,replace).split(".").reverse().join("_");
		} else {
			remoteProject = '';
		}
	} else {
		remoteProject = desiredUrl.split(".").reverse().join("_");
	}
}

function replaceTag(tag) {
	return tagsToReplace[tag] || tag;
}

function htmlspecialchars(str) {
	return str.replace(/[&"<>]/g, replaceTag);
}

function ajaxFormValidate() {
	$('#test').removeClass('secondary').removeClass('success').removeClass('alert');
	var postData = getFormdata();
	$.ajax({
		url: callbackurl,
		dataType: 'json',
		type: 'POST',
		data: postData,
		timeout: 2000,
		success: function(data){
			processAjaxReply(data);
		},
		error: function(){
			$('#nextbutton').addClass('disabled').addClass('secondary');
			$('#test').addClass('secondary');
		}
	});
}

function ajaxFormFirstFill() {
	$.ajax({
		url: callbackurl,
		dataType: 'json',
		type: 'GET',
		timeout: 2000,
		success: function(data){
			processAjaxReply(data);
		}
	});
}

function processAjaxReply(data) {
	// Get the focussed element around which the form will be build again.
	// (Dislike messing around with the element currently in focus)
	var element = $('#customTargetForm *:focus');
	
	// If there are fields, build the form
	if (typeof data.fields !== 'undefined') {
		// Remove all fields, so the form can be rebuild
		removeFields(element, data.fields);
		
		// Incase there is a focussed element, build around it
		var prepend;
		if (element.length !== 0 && element.attr('name') in data.fields) {
			prepend = true;
		} else {
			prepend = false;
		}
		
		// create the fields
		for(var field in data.fields) {
			if (element.attr('name') === field) {
				prepend = false;
			}
			if (typeof data.fields[field].type === 'undefined') {
				createTextField(field, data.fields[field], prepend, element);
			} else if(data.fields[field].type === 'select') {
				createSelect(field, data.fields[field], prepend, element);
			} else if(data.fields[field].type === 'textarea') {
				createTextarea(field, data.fields[field], prepend, element);
			} else {
				createTextField(field, data.fields[field], prepend, element);
			}
		}
	} else {
		// No fields = no custom form
		$('#customTargetForm').empty();
	}
	
	if (typeof data.values !== 'undefined') {
		for(var value in data.values) {
			if ($.inArray(value, selects) !== -1) {
				$('#customTargetForm select[name='+htmlspecialchars(value)+']').val(data.values[value]);
			} else if ($.inArray(value, inputs) !== -1) {
				$('#customTargetForm input[name='+htmlspecialchars(value)+']').val(data.values[value]);
			} else if ($.inArray(value, textareas) !== -1) {
				$('#customTargetForm textarea[name='+htmlspecialchars(value)+']').val(data.values[value]);
			}
		}
	}
	
	$('#serverIdError, #urlError').remove();
	$('select[name=serverId]').removeClass('error');
	$('input[name=url]').removeClass('error');
	if (typeof data.errors !== 'undefined' && data.errors !== null) {
		for(var value in data.errors) {
			if (value === 'serverId') {
				$('select[name=serverId]').addClass('error').next().next()
				.after('<small class="error" id="serverIdError">'+htmlspecialchars(data.errors[value])+'</small>');
			} else if (value === 'url') {
				if ($('input[name=url]').val() !== '' || changeTestButton) {
					$('input[name=url]').addClass('error')
					.after('<small class="error" id="urlError">'+htmlspecialchars(data.errors[value])+'</small>');
				}
			} else if ($.inArray(value, selects) !== -1) {
				if ($('#customTargetForm select[name='+htmlspecialchars(value)+']').val() !== '' || changeTestButton) {
					$('#customTargetForm select[name='+htmlspecialchars(value)+']').addClass('error')
					.after('<br/><br/><small class="error">'+htmlspecialchars(data.errors[value])+'</small>');
				}
			} else if ($.inArray(value, inputs) !== -1) {
				if ($('#customTargetForm input[name='+htmlspecialchars(value)+']').val() !== '' || changeTestButton) {
					$('#customTargetForm input[name='+htmlspecialchars(value)+']').addClass('error')
					.after('<small class="error">'+htmlspecialchars(data.errors[value])+'</small>');
				}
			} else if ($.inArray(value, textareas) !== -1) {
				if ($('#customTargetForm textarea[name='+htmlspecialchars(value)+']').val() !== '' || changeTestButton) {
					$('#customTargetForm textarea[name='+htmlspecialchars(value)+']').addClass('error')
					.after('<small class="error">'+htmlspecialchars(data.errors[value])+'</small>');
				}
			}
		}
	}
	
	if(typeof data.errors !== 'undefined' && data.errors !== null) {
		if (changeTestButton) {
			$('#test').addClass('alert');
		}
		$('#nextbutton').addClass('disabled').addClass('secondary');
	} else {
		if (changeTestButton) {
			$('#test').addClass('success');
		}
		$('#nextbutton').removeClass('disabled').removeClass('secondary');
	}
	changeTestButton = false;
}

function removeFields(element, fields) {
	if (element.length !== 0 && element.attr('name') in fields) {
		if ($.inArray(element.attr('name'), selects) !== -1) {
			selects = [ element.attr('name') ];
		} else {
			selects = [];
		}
		if ($.inArray(element.attr('name'), inputs) !== -1) {
			inputs = [ element.attr('name') ];
		} else {
			inputs = [];
		}
		if ($.inArray(element.attr('name'), textareas) !== -1) {
			textareas = [ element.attr('name') ];
		} else {
			textareas = [];
		}
		
		$('#customTargetForm label, #customTargetForm select[name!='+element.attr('name')+'], #customTargetForm input[name!='+element.attr('name')+'], #customTargetForm textarea[name!='+element.attr('name')+'], #customTargetForm br, #customTargetForm small').remove();

		removeAttribute = [];
		for (var i = 0; i < element.get(0).attributes.length; i++) {
			var attrib = element.get(0).attributes[i];
			if (attrib.specified && attrib.name !== 'name' && attrib.name !== 'type') {
				removeAttribute.push(attrib.name);
			}
		}
		
		for (i in removeAttribute) {
			element.removeAttr(removeAttribute[i]);
		}
	} else {
		selects = [];
		inputs = [];
		textareas = [];
		$('#customTargetForm').empty();
	}
}

function createTextField(name, field, prepend, element) {
	var input;
	if ($('#customTargetForm input[name='+htmlspecialchars(name)+']').length === 0) {
		if (typeof field.type !== 'undefined' && typeof field.label !== 'undefined') {
			input = $('<label>'+htmlspecialchars(field.label)+'</label>'+"\n"+'<input type="'+htmlspecialchars(field.type)+'" name="'+htmlspecialchars(name)+'" />');
		} else if(typeof field.type !== 'undefined') {
			input = $('<input type="'+htmlspecialchars(field.type)+'" name="'+htmlspecialchars(name)+'" />');
		} else if(typeof field.label !== 'undefined') {
			input = $('<label>'+htmlspecialchars(field.label)+'</label>'+"\n"+'<input type="text" name="'+htmlspecialchars(name)+'" />');
		} else {
			input = $('<input type="text" name="'+htmlspecialchars(name)+'" />');
		}
		if (element.length !== 0 && prepend) {
			element.before(input);
		} else {
			input.appendTo('#customTargetForm');
		}
		input.change(function(e) {
			ajaxFormValidate();
		});
		inputs.push(htmlspecialchars(name));
	} else {
		input = $('#customTargetForm input[name='+htmlspecialchars(name)+']');
		if (typeof field.label !== 'undefined') {
			input.before('<label>'+htmlspecialchars(field.label)+'</label>');
		}
	}
	
	for(var attr in field) {
		if (attr !== "type" && attr !== "label") {
			input.attr(attr, field[attr]);
		}
	}
}

function createTextarea(name, field, prepend, element) {
	var textarea;
	if ($('#customTargetForm textarea[name='+htmlspecialchars(name)+']').length === 0) {
		if (typeof field.label !== 'undefined') {
			textarea = $('<label>'+htmlspecialchars(field.label)+'</label>'+"\n"+'<textarea name="'+htmlspecialchars(name)+'" />');
		} else {
			textarea = $('<textarea name="'+htmlspecialchars(name)+'" />');
		}
		if (element.length !== 0 && prepend) {
			element.before(textarea);
		} else {
			textarea.appendTo('#customTargetForm');
		}
		textarea.change(function(e) {
			ajaxFormValidate();
		});
		textareas.push(htmlspecialchars(name));
	} else {
		textarea = $('#customTargetForm textarea[name='+htmlspecialchars(name)+']');
		if (typeof field.label !== 'undefined') {
			textarea.before('<label>'+htmlspecialchars(field.label)+'</label>');
		}
	}
	
	for(var attr in field) {
		if (attr !== "type" && attr !== 'label') {
			textarea.attr(attr, field[attr]);
		}
	}
}

function createSelect(name, field, prepend, element) {
	var select
	if ($('#customTargetForm select[name='+htmlspecialchars(name)+']').length === 0) {
		if (typeof field.label !== 'undefined') {
			select = $('<label>'+htmlspecialchars(field.label)+'</label>'+"\n"+'<select name="'+htmlspecialchars(name)+'" />');
		} else {
			select = $('<select name="'+htmlspecialchars(name)+'" />');
		}
		if (element.length !== 0 && prepend) {
			element.before(select);
		} else {
			select.appendTo('#customTargetForm');
		}
		select.change(function(e) {
			ajaxFormValidate();
		});
		selects.push(htmlspecialchars(name));
	} else {
		select = $('#customTargetForm select[name='+htmlspecialchars(name)+']');
		if (typeof field.label !== 'undefined') {
			select.before('<label>'+htmlspecialchars(field.label)+'</label>');
		}
	}
	
	for(var attr in field) {
		if (attr !== "type" && attr !== 'content' && attr !== 'placeholder' && attr !== 'label') {
			select.attr(attr, field[attr]);
		} else if (attr === 'placeholder') {
			select.append($("<option></option>").text(field[attr]));
		}
	}
	if (typeof field.content !== 'undefined') {
		if (typeof field.content !== 'undefined') {
			$('#customTargetForm select[name='+htmlspecialchars(name)+'] option:gt(0)').remove();
		} else {
			select.empty();
		}
		for(var value in field.content) {
			select.append($("<option></option>").attr("value", value).text(field.content[value]));
		}
	}
}

function getFormdata() {
	var data=new Object();
	for(var i in selects)
	{
		data[selects[i]] = $("#targetForm select[name="+htmlspecialchars(selects[i])+"] option:selected").val();
	}
	
	for(var i in inputs)
	{
		data[inputs[i]] = $("#targetForm input[name="+htmlspecialchars(inputs[i])+"]").val();
	}
	
	for(var i in textareas)
	{
		data[textareas[i]] = $("#targetForm textarea[name="+htmlspecialchars(textareas[i])+"]").val();
	}
	
	data.serverId = $("#targetForm select[name=serverId]").val();
	data.url = $("#targetForm input[name=url]").val()
	
	return data;
}

$(document).ready(function(){
	ajaxFormFirstFill();
	
	$('select[name=serverId]').change(function(e) {
		makeSuggestion();
	});
	$('input[name=url]').change(function(e) {
		setRemoteProject();
	});
	$('#targetForm select, #targetForm input, #targetForm textarea').change(function(e) {
		ajaxFormValidate();
	});
	$('#test').click(function() {
		changeTestButton = true;
		ajaxFormValidate();
	});
	
	$(".showInformation").click(function() {
		$('#information').toggle();
	});
});