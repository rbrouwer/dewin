var history = [];
var stop = 0;
var maxId = 0;
var callbackurl;
var processId;
						
function sent(input, clean){
	$.post(callbackurl, {"stdin":input, "processId":processId}, function(data){
		return true;
	});
	if(clean == true){
		$(".stdin").val("").focus();
	} else {
		$(".stdin").focus();
	}
	if(
	input.charCodeAt(0) != 3 ||
		input.charCodeAt(0) != 4 ||
		input.charCodeAt(0) != 26 
){
		history.push(input);
		stop = history.length;
	}
}
						
function handleInput(e){
	if(e.target.readonly) return false;
	code = e.keyCode ? e.keyCode : e.which;

	if(code.toString() == 13) sent(e.target.value, true);
	if(code.toString() == 9) {
		e.preventDefault();
		sent(e.target.value + String.fromCharCode(9), false);
		return false;
	}
	if(code.toString() == 38) {
		if(stop <= 0) stop = 1;
		e.target.value = history[stop-1];
		stop--;
	}
	if(code.toString() == 40) {
		stop++;
		if(stop >= history.length){
			e.target.value = '';
			stop = history.length;
		} else {
			e.target.value = history[stop];
		}

	}
	if(e.ctrlKey && code.toString() == 67) {
		e.preventDefault();
		sent(String.fromCharCode(3), true);
		return false;
	}
	if(e.ctrlKey && code.toString() == 68) {
		e.preventDefault();
		sent(String.fromCharCode(4), true);
		return false;
	}
	if(e.ctrlKey && code.toString() == 90) {
		e.preventDefault();
		sent(String.fromCharCode(26), true);
		return false;
	}
}

function receive(){
	$.getJSON(callbackurl, {"minId":maxId, "processId":processId}, function(data){
		if (data.sent == true){
			maxId=data.maxId;
			for (i in data.stdout) {
				handleOutput(data.stdout[i].replace(/<\/span>/g, "</span>\n"));
			}
		}
	});
}

function handleOutput(output){
	if(output != "" || output != null){
		$("label").before( output );
	}
	$(".stdin").parent().css({ 
		"left":$("pre > span:last").width() + "px"
	});
	$(".stdin").removeAttr("readonly").focus();
	window.scrollTo(0,document.body.scrollHeight);
}
						
$(document).ready(function() {$(".stdin").val("").focus(); setInterval("receive()", 2500)});