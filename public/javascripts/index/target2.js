// Contains the filled in name of the project allowing the tool to suggest urls
var targetProject = '';

// Contains whenever the test button was pressed...
var changeTestButton = false;

// These 3 contain names of the elements in the custom form
var selects = [];
var inputs = [];
var textareas = [];

// TEMP: used for callback from source instance stuff.
instCallback = 'makeSuggestionInstance';

// Contains the ID of the suggested instance.
var suggestedInstance = '';

// Allow suggesting instances. This prevents locking users in when the desired URL.
var allowSuggestedInstances = false;

// I love escaping, these things are escaped in #customform elements
var tagsToReplace = {
	'&': '&amp;',
	'"': '&quot;',
	'<': '&lt;',
	'>': '&gt;'
};

// This suggests a desired url based on the server's baseurl and the project's source or target name
function makeSuggestion() {
	var attr = $('select[name=serverId] option:selected').attr('data');
	var value;
	if (typeof attr !== 'undefined' && attr !== '') {
		if (targetProject != '') {
			value = "http://"+targetProject.split("_").reverse().join(".")+"."+attr
		} else {
			value = "http://"+sourceProject+"."+attr
		}
	} else {
		if (targetProject != '') {
			value = "http://"+targetProject.split("_").reverse().join(".")
		} else {
			value = "http://"+sourceProject
		}
	}
	$("input[name=url]").val(value);
}

// This sets the suggested instance, when instance and the validator are ready.
function makeSuggestionInstance() {
	if ($('select[name=serverId]').val() === instServerId && allowSuggestedInstances === true && suggestedInstance !== '') {
		if ($('select[name=instance]').val() !== suggestedInstance) {
			$('select[name=instance]').val(suggestedInstance);
			allowSuggestedInstances = false;
		}
	}
	hideTargetForm();
}

// Reads the desired url and attempts to set the target project name.
// That way the desired url will keep the name when another server is selected.
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
			targetProject = desiredUrl.replace(regex,replace).split(".").reverse().join("_");
		} else {
			targetProject = '';
		}
	} else {
		targetProject = desiredUrl.split(".").reverse().join("_");
	}
}

// HTML escaping
function replaceTag(tag) {
	return tagsToReplace[tag] || tag;
}

// HTML escaping
function htmlspecialchars(str) {
	return str.replace(/[&"<>]/g, replaceTag);
}

// Target ajax form validator function
function ajaxFormValidate() {
	// Remove any state from the test button
	$('#test').removeClass('secondary').removeClass('success').removeClass('alert').html('Testing&hellip;');

	// When an instance is select, do not bother to ajax validate the form. Instances should ALWAYS be valid.
	if ($('select[name=instance]').val() != '') {
		// Incase the test button was pressed, update the test button.
		if (changeTestButton) {
			$('#test').addClass('success').html('Passed');
		}
		
		// Enable the next button
		$('#nextbutton').removeClass('disabled').removeClass('secondary');
		changeTestButton = false;
	} else {
		// When there is no instance selects, validate using ajax
		var postData = getFormdata();
		$.ajax({
			url: callbackurl,
			dataType: 'json',
			type: 'POST',
			data: postData,
			timeout: 2000,
			success: function(data){
				// No data is also a fail!
				if (typeof data === 'undefined' || data === null) {
					processFailedAjaxReply();
				} else {
					// Reset the button, so processAjaxReply can change it further.
					$('#test').html('Test').removeAttr("disabled").removeClass('disabled');
					processAjaxReply(data);
				}
			},
			error: function(){
				processFailedAjaxReply();
			}
		});
	}
}

// First fill is used to restore the previous state of the form!
function ajaxFormFirstFill() {
	// Allow suggestions in this case and also feel the instance dropdown
	allowSuggestedInstances = true;
	ajaxInstanceFormFill();
	
	// Get the previous state
	$.ajax({
		url: callbackurl,
		dataType: 'json',
		type: 'GET',
		timeout: 2000,
		success: function(data){
			processAjaxReply(data);
		},
		error: function(){
			setTimeout(function() {ajaxFormFirstFill();}, 3000);
		}
	});
}

// Ajax validation form 
function processFailedAjaxReply() {
	$('#nextbutton').addClass('disabled').addClass('secondary');
	$('#test').addClass('secondary').addClass('disabled').attr("disabled", "disabled").html('Retrying&hellip;');
	setTimeout(function() {ajaxFormValidate();}, 3000);
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
	
	// Set values
	if (typeof data.values !== 'undefined') {
		// Reset suggested instance
		suggestedInstance = '';
		
		// foreach value, look up which type and set the value
		for(var value in data.values) {
			if ($.inArray(value, selects) !== -1) {
				$('#customTargetForm select[name='+htmlspecialchars(value)+']').val(data.values[value]);
			} else if ($.inArray(value, inputs) !== -1) {
				$('#customTargetForm input[name='+htmlspecialchars(value)+']').val(data.values[value]);
			} else if ($.inArray(value, textareas) !== -1) {
				$('#customTargetForm textarea[name='+htmlspecialchars(value)+']').val(data.values[value]);
			} else if (value === 'instance') {
				suggestedInstance = data.values[value];
			}
		}
	}
	
	// Re-do all errors...
	$('#serverIdError, #instanceError, #urlError').remove();
	$('select[name=serverId], select[name=instance], input[name=url]').removeClass('error');
	if (typeof data.errors !== 'undefined' && data.errors !== null) {
		for(var error in data.errors) {
			if (error === 'serverId' || error === 'instance') {
				$('select[name='+error+']').addClass('error').next().next()
				.after('<small class="error" id="'+error+'Error">'+htmlspecialchars(data.errors[error])+'</small>');
			} else if (error === 'url') {
				if ($('input[name=url]').val() !== '' || changeTestButton) {
					$('input[name=url]').addClass('error')
					.after('<small class="error" id="urlError">'+htmlspecialchars(data.errors[error])+'</small>');
				}
			} else if ($.inArray(error, selects) !== -1) {
				if ($('#customTargetForm select[name='+htmlspecialchars(error)+']').val() !== '' || changeTestButton) {
					$('#customTargetForm select[name='+htmlspecialchars(error)+']').addClass('error')
					.after('<br/><br/><small class="error">'+htmlspecialchars(data.errors[error])+'</small>');
				}
			} else if ($.inArray(error, inputs) !== -1) {
				if ($('#customTargetForm input[name='+htmlspecialchars(error)+']').val() !== '' || changeTestButton) {
					$('#customTargetForm input[name='+htmlspecialchars(error)+']').addClass('error')
					.after('<small class="error">'+htmlspecialchars(data.errors[error])+'</small>');
				}
			} else if ($.inArray(error, textareas) !== -1) {
				if ($('#customTargetForm textarea[name='+htmlspecialchars(error)+']').val() !== '' || changeTestButton) {
					$('#customTargetForm textarea[name='+htmlspecialchars(error)+']').addClass('error')
					.after('<small class="error">'+htmlspecialchars(data.errors[error])+'</small>');
				}
			}
		}
	}
	
	// Change buttons
	if(typeof data.errors !== 'undefined' && data.errors !== null) {
		if (changeTestButton) {
			$('#test').addClass('alert').html('Failed');
		}
		$('#nextbutton').addClass('disabled').addClass('secondary');
	} else {
		if (changeTestButton) {
			$('#test').addClass('success').html('Passed');
		}
		$('#nextbutton').removeClass('disabled').removeClass('secondary');
	}
	
	// After validation, the form is rebuild and possibly other values are loaded, do the instance suggestion...
	makeSuggestionInstance();
	
	// Stop changing the test button...
	changeTestButton = false;
}

// This removes all but the focussed field
function removeFields(element, fields) {
	// Remove around the field
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

		// If this is the selected field, remove all attributes, but name(for selecting it in the first place) and type.
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
	// Build 
	} else {
		selects = [];
		inputs = [];
		textareas = [];
		$('#customTargetForm').empty();
	}
}

// Adds a basic field
// before element if element is supply and prepend is true
// or else prepanded in customTargetForm div.
function createTextField(name, field, prepend, element) {
	var input;
	// Add the basic element
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
	// Re-add the label
	} else {
		input = $('#customTargetForm input[name='+htmlspecialchars(name)+']');
		if (typeof field.label !== 'undefined') {
			input.before('<label>'+htmlspecialchars(field.label)+'</label>');
		}
	}
	
	// Add all other attributes
	for(var attr in field) {
		if (attr !== "type" && attr !== "label") {
			input.attr(attr, field[attr]);
		}
	}
}

// Adds a textarea
// before element if element is supply and prepend is true
// or else prepanded in customTargetForm div.
function createTextarea(name, field, prepend, element) {
	var textarea;
	// Add the basic element
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
	// Re-add the label
	} else {
		textarea = $('#customTargetForm textarea[name='+htmlspecialchars(name)+']');
		if (typeof field.label !== 'undefined') {
			textarea.before('<label>'+htmlspecialchars(field.label)+'</label>');
		}
	}
	
	// Add all other attributes
	for(var attr in field) {
		if (attr !== "type" && attr !== 'label') {
			textarea.attr(attr, field[attr]);
		}
	}
}

// Adds a select
// before element if element is supply and prepend is true
// or else prepanded in customTargetForm div.
function createSelect(name, field, prepend, element) {
	var select
	// Add the basic element
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
	// Re-add the label
	} else {
		select = $('#customTargetForm select[name='+htmlspecialchars(name)+']');
		if (typeof field.label !== 'undefined') {
			select.before('<label>'+htmlspecialchars(field.label)+'</label>');
		}
	}
	
	// Add all other attributes
	for(var attr in field) {
		if (attr !== "type" && attr !== 'content' && attr !== 'placeholder' && attr !== 'label') {
			select.attr(attr, field[attr]);
		} else if (attr === 'placeholder') {
			select.append($("<option></option>").text(field[attr]));
		}
	}
	
	// Add the options in of the select
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

// Returns all values from the form
function getFormdata() {
	var data=new Object();
	for(var i in selects)
	{
		data[selects[i]] = $("#targetForm select[name="+htmlspecialchars(selects[i])+"] option:selected").val();
	}
	
	for(var j in inputs)
	{
		data[inputs[j]] = $("#targetForm input[name="+htmlspecialchars(inputs[j])+"]").val();
	}
	
	for(var k in textareas)
	{
		data[textareas[k]] = $("#targetForm textarea[name="+htmlspecialchars(textareas[k])+"]").val();
	}
	
	data.serverId = $("#targetForm select[name=serverId]").val();
	data.url = $("#targetForm input[name=url]").val()
	
	return data;
}

// Changes the test button and does other calls that are appropiate
function onChangeInstance() {
	if ($('select[name=instance]').val() == '') {
		$('#test').html('Test').removeClass('secondary').removeClass('success').removeClass('alert').removeAttr("disabled").removeClass('disabled');
	} else {
		ajaxFormValidate();
	}
	hideTargetForm();
}

// Hide the remaining target form when an instance is selected
// otherwise show it all
function hideTargetForm() {
	if ($('select[name=instance]').val() != '') {
		$('.newTarget').hide();
	} else {
		$('.newTarget').show();
	}
}

// Document on ready...
$(document).ready(function(){
	ajaxFormFirstFill();
	
	$('select[name=serverId]').change(function(e) {
		makeSuggestion();
		allowSuggestedInstances = true;
	});
	
	$('select[name=instance]').change(function(e) {
		onChangeInstance();
	});
	
	$('input[name=url]').change(function(e) {
		setRemoteProject();
		allowSuggestedInstances = true;
	});
	$('#targetForm select[name=serverId], #targetForm input, #targetForm textarea').change(function(e) {
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