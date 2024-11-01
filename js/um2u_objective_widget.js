var um2u_adopt_objective = function( postid, userid ) {
	jQuery.post(
		UM2UObjectiveWidget.ajaxurl, {
			action:    'um2u_adopt_objective',
			um2u_objective_nonce: UM2UObjectiveWidget.um2u_objective_nonce,
			postid:    postid,
			userid:    userid
		},
		function ( response ) {
			jQuery( '#um2u_objective_widget' ).html( response.message );
			var el = jQuery( '#adopter_' + response.user ).detach();
			jQuery( '#um2u_adopters' ).prepend( el );
			jQuery( '#adopter_' + response.user ).removeClass().addClass( 'um2u_progress_0' );
			jQuery( '#um2u_no_adopters' ).remove();
			jQuery( '.objective_progress li' ).hover( 
				function() {
					o = jQuery( this ).index() * 24;
					jQuery( this ).parent().css( 'background-position', '0 -' + o + 'px' );
				},
				function() {
					o = jQuery( '.objective_progress li.selected' ).index() * 24;
					jQuery( '.objective_progress' ).css( 'background-position', '0 -' + o + 'px' );
				}
			);
		}
	);
};
var um2u_update_objective_progress = function( postid, userid, progress ) {
	if ( jQuery( '#ok_to_update_objective_progress' ).attr( 'title' ) == 'true' ) {
		jQuery.post(
			UM2UObjectiveWidget.ajaxurl, {
				action: 'um2u_update_objective_progress',
				um2u_objective_status_nonce: UM2UObjectiveWidget.um2u_objective_status_nonce,
				postid: postid,
				userid: userid,
				progress: progress
			},
			function ( response ) {
				UM2UObjectiveWidget.um2u_objective_status_nonce = response.nonce;
				if ( -1 != response.progress ) {
					var n = parseInt( response.progress ) + 1;
					jQuery( 'ul.objective_progress li' ).removeClass( 'selected' );
					jQuery( 'ul.objective_progress li:nth-of-type(' + n + ')' ).addClass( 'selected' );
				}
				jQuery( '#adopter_' + response.user ).removeClass().addClass( 'um2u_progress_' + response.progress );
			}
		);
	}
};
var um2u_activate_progress_hover = function() {
	var o;
	jQuery( '.objective_progress li' ).hover( 
		function() {
			o = jQuery( this ).index() * 24;
			jQuery( this ).parent().css( 'background-position', '0 -' + o + 'px' );
		},
		function() {
			o = jQuery( 'li.selected', jQuery( this ).parent() ).index() * 24;
			jQuery( jQuery( this ).parent() ).css( 'background-position', '0 -' + o + 'px' );
		}
	);
};
jQuery( document ).ready( function( $ ) {
	if ( $( '#ok_to_update_objective_progress' ).attr( 'title' ) == 'true' ) um2u_activate_progress_hover();
	$( '#um2u_adopted_list_toggle' ).click( function( e ) {
		e.preventDefault();
		$( '#um2u_adopted_list' ).slideToggle( 'fast', function() {
			if ( $( '#um2u_adopted_list_toggle' ).html() == 'Show Adopters' ) {
				$( '#um2u_adopted_list_toggle' ).html( 'Hide Adopters' );
			} else {
				$( '#um2u_adopted_list_toggle' ).html( 'Show Adopters' );
			}
		});
	});
	$( '.list_toggler' ).click( function( e ) {
		var link = $( this );
		e.preventDefault();
		$( '#' + link.attr( 'title' ) ).slideToggle( 'fast', function() {
			if ( link.html() == 'show' ) {
				link.html( 'hide' );
			} else {
				link.html( 'show' );
			}
		});
	});
});
