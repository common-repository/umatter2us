var um2u_mark_post_read = function( postid, userid ) {
	jQuery.post(
		UM2UReadPostWidget.ajaxurl, {
			action:    'um2u_mark_read',
			um2unonce: UM2UReadPostWidget.um2unonce,
			postid:    postid,
			userid:    userid
		},
		function ( response ) {
			jQuery( '#um2u_read_post' ).html( response.message );
			var el = jQuery( '#' + response.user ).detach();
			jQuery( '#um2u_readers' ).prepend( el );
			jQuery( '#um2u_no_readers' ).remove();
		}
	);
};
jQuery( document ).ready( function( $ ) {
	$( '#um2u_read_list_toggle' ).click( function( e ) {
		e.preventDefault();
		$( '#um2u_read_list' ).slideToggle( 'fast', function() {
			if ( $( '#um2u_read_list_toggle' ).html() == 'Show Readers' ) {
				$( '#um2u_read_list_toggle' ).html( 'Hide Readers' );
			} else {
				$( '#um2u_read_list_toggle' ).html( 'Show Readers' );
			}
		});
	});
});
