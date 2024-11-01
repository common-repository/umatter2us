jQuery( document ).ready( function( $ ) {
	var um2u_generate_panel_dialog = $( '<div></div>' )
		.html( 'Generating csv file...' )
		.dialog(
			{
				autoOpen: false,
				title: 'New Panel',
				modal: true,
				draggable: false,
				resizable: false
			}
		);

	$( '#um2u_create_panel_button' ).click( function() {
		um2u_generate_panel_dialog.dialog( 'open' );
		$.post( ajaxurl, {
			action:   'um2u_generate_panel_csv',
			panelID:  $( 'select[name="umatter2us_settings[qtrx_panel_to_modify]"]'  ).val(),
			surveyID: $( 'select[name="umatter2us_settings[qtrx_survey_to_import]"]' ).val()
		}, function( link ) {
			um2u_generate_panel_dialog.html( link );
		});
	});
	var get_libs = function() {
		var user  = $( '#qualtrics_username' ).val(),
			token = $( '#qualtrics_token'    ).val();
		if ( user && token ) {
			$.get( ajaxurl, { action: 'get_libraries', user: user, token: token } )
			.done( function( res ) { 
				if ( res.success ) { 
					$( '#qualtrics_library' ).replaceWith( res.opts );
					$( '#qualtrics_library' ).change( get_surveys ); 
				} 
			})
			.fail( function( res ) {
				$( '<div></div>' )
				.html( res.message )
				.dialog( { title: 'Error', modal: true, buttons: { "OK": function() { $( this ).dialog( 'close' ); } } } )
			});
		}
	};
	var get_surveys = function() {
		var user  = $( '#qualtrics_username' ).val(),
			token = $( '#qualtrics_token'    ).val(),
			lib   = $( '#qualtrics_library'  ).val();
		$.get( ajaxurl, { action: 'get_surveys', user: user, token: token, library: lib } )
		.done( function( res ) {
			if ( res.success ) {
				if ( res.success ) { 
					$( '#self_eval_survey' ).replaceWith( res.opts );
				} 
			}
		})
		.fail( function( res ) {
			$( '<div></div>' )
			.html( res.message )
			.dialog( { title: 'Error', modal: true, buttons: { "OK": function() { $( this ).dialog( 'close' ); } } } )
		});
	};
	$( '#qualtrics_username, #qualtrics_token' ).change( get_libs );
	$( '#qualtrics_library' ).change( get_surveys );
});