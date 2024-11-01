var um2u_generate_exceptions = function() {
	var rep = {};
	rep.repeat_on = new Array();
	rep.ends = {};
	jQuery( 'input[name="_repetition[repeat_on][]"]:checked' ).each( function() { rep.repeat_on.push( jQuery( this ).val() ); } );
	rep.frequency        = jQuery( 'select[name="_repetition[frequency]"]'         ).val();
	rep.every            = jQuery( 'select[name="_repetition[every]"]'             ).val();
	rep.repeat_by        = jQuery( 'input[name="_repetition[repeat_by]"]'          ).val();
	rep.starts_on        = jQuery( 'input[name="_repetition[starts_on]"]'          ).val();
	rep.ends.type        = jQuery( 'input[name="_repetition[ends][type]"]:checked' ).val();
	rep.ends.on          = jQuery( 'input[name="_repetition[ends][on]"]'           ).val();
	rep.ends.occurrences = jQuery( 'input[name="_repetition[ends][occurrences]"]'  ).val();
	jQuery.post(
		UM2UMeetings.ajaxurl, {
			action:    'um2u_generate_exceptions',
			um2u_exceptions_nonce: UM2UMeetings.um2u_exceptions_nonce,
			repetition: rep
		},
		function ( response ) {
			UM2UMeetings.um2u_exceptions_nonce = response.nonce;
			jQuery( '#um2u_exception_calendars' ).html( response.exceptions );
		}
	);
};
jQuery( document ).ready( function( $ ) {
	$( '#_um2u_meeting_start_time' ).datetimepicker({
		dateFormat: 'yy-mm-dd',
		onClose: function( dateText, inst ) {
			var endDateTextBox = $( '#_um2u_meeting_end_time' );
			if ( endDateTextBox.val() != '' ) {
				var testStartDate = new Date( dateText );
				var testEndDate = new Date( endDateTextBox.val() );
				if ( testStartDate > testEndDate ) endDateTextBox.val( dateText );
			} else {
				endDateTextBox.val( dateText );
			}
		},
		onSelect: function( seletedDateTime ) {
			var start = $( this ).datetimepicker( 'getDate' );
			$( '#_um2u_meeting_end_time' ).datetimepicker( 'option', 'minDate', new Date( start.getTime() ) ); 
		}
	});
	$( '#_um2u_meeting_end_time' ).datetimepicker({
		dateFormat: 'yy-mm-dd',
		onClose: function( dateText, inst ) {
			var startDateTextBox = $( '#_um2u_meeting_start_time' );
			if ( startDateTextBox.val() != '' ) {
				var testStartDate = new Date( startDateTextBox.val() );
				var testEndDate = new Date( dateText );
				if ( testStartDate > testEndDate ) startDateTextBox.val( dateText );
			} else {
				startDateTextBox.val( dateText );
			}
		},
		onSelect: function( selectedDateTime ) {
			var end = $( this ).datetimepicker( 'getDate' );
			$( '#_um2u_meeting_start_time' ).datetimepicker( 'option', 'maxDate', new Date( end.getTime() ) );
		}
	});
	$( '.um2u_invitee_group' ).change( function() {
		// get the list of group members
		var members = $( this ).val().split( ',' );
		if ( $( this ).is( ':checked' ) ) {
			$( members ).each( function( index, m ) {
				$( '#um2u_invitee_list input[value=' + m + ']' ).attr( 'checked', 'checked' );
			});
		} else {
			$( members ).each( function( index, m ) {
				$( '#um2u_invitee_list input[value=' + m + ']' ).removeAttr( 'checked' );
			});
		}
	});
	$( '#um2u_meeting_frequency' ).change( function() {
		switch( $( this ).val() ) {
			case 'Daily':
				$( '#um2u_repetition_timespan' ).html( 'days' );
				$( '#um2u_repeat_on' ).hide();
				$( '#um2u_repeat_by' ).hide();
				break;
			case 'Weekly':
				$( '#um2u_repetition_timespan' ).html( 'weeks' );
				$( '#um2u_repeat_on' ).show();
				$( '#um2u_repeat_by' ).hide();
				break;
			case 'Monthly': 
				$( '#um2u_repetition_timespan' ).html( 'months' );
				$( '#um2u_repeat_on' ).hide();
				$( '#um2u_repeat_by' ).show();
				break;
		}
	});
	$( '#um2u_repetition_starts_on, #um2u_meeting_repetition_ends_on' ).datepicker( { dateFormat: 'yy-mm-dd' } );
	$( '.um2u_repetition' ).change( um2u_generate_exceptions );
});