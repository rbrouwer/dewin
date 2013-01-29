function fixZebraColors() {
	$('table tbody tr:not(:hidden)').filter(":even").addClass('odd').removeClass('even');
	$('table tbody tr:not(:hidden)').filter(":odd").addClass('even').removeClass('odd');
}

function showAllSql() {
	$('table tbody tr:hidden').show();
}

function showCheckedSql() {
	$('table tbody tr:visible').filter(".notImportant").hide();
	$('table tbody tr').filter(":not(.notImportant)").each(function (index) {
		if ($(this).find('input.sqlCheck:checkbox').is(':checked')) {
			$(this).show();
		} else {
			$(this).hide();
		}
	});
}

function showUncheckedSql() {
	$('table tbody tr:visible').filter(".notImportant").hide();
	$('table tbody tr:hidden').filter(":not(.notImportant)").show();
}

function changeSqlMode() {
	if ($('#sqlSelect').val() == '1') {
		showCheckedSql()
	} else if ($('#sqlSelect').val() == '2') {
		showUncheckedSql();
	} else {
		showAllSql();
	}
	fixZebraColors();
}

function onSelect() 
{
	if ($(".dbStrategy").filter(function() {return $(this).val() == '';}).length > 0) {
		$('#nextbutton').addClass('disabled').addClass('secondary');;
	} else {
		$('#nextbutton').removeClass('disabled').removeClass('secondary');;
	}

	if ($(".dbStrategy").filter(function() {return $(this).val() == '2';}).length > 0) {
		$('.manualMode').show();
		showCheckedSql();
		fixZebraColors();
		$('#sqlSelect').val("1");
	} else {
		$('.manualMode').hide();
	}
}

$(document).ready(function() {
	$(document).on('click', 'td[data-reveal-id]', function ( event ) {
		event.preventDefault();
		var modalLocation = $( this ).attr( 'data-reveal-id' );
		$( '#' + modalLocation ).reveal( $( this ).data() );
	});
	
	$('input.sqlCheck:checkbox').change(function() {
		if ($(this).is(':checked') == false && $('#sqlSelect').val() == '1') {
			$(this).parent('td').parent('tr').fadeOut('fast', function () {
				fixZebraColors();
			});
		}
	});
	
	$('.dbStrategy').change(function() {
		
		onSelect();
	});
	$('#sqlSelect').change(function() {
		changeSqlMode();
	});
	onSelect();
	
});