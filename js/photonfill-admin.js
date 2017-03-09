/**
 * Play nice with Fieldmanager metaboxes.
 *
 * @package Photonfill
 **/

( function( $ ) {
	$( document ).on( 'fieldmanager_media_preview', function( event, wrapper, attachment, wp ) {
		$.ajax( {
			type: 'POST',
			url: photonfill_wp_vars['wp_ajax_url'],
			data: {
				action : 'get_img_object',
				attachment : attachment.id,
				nonce: photonfill_wp_vars['photonfill_get_img_object_nonce'],
			},
			dataType: 'html',
		} )
		.done( function( response ) {
			wrapper.empty();
			wrapper.append( '<a />' )
			.find( 'a:last' )
				.attr( 'href', '#' )
				.append( response )
				.end()
			.append( '<br />' )
			.append( '<a />' )
			.find( 'a:last' )
				.addClass( 'fm-media-remove fm-delete' )
				.attr( 'href', '#' )
				.append( photonfill_wp_vars['photonfill_i18n']['remove'] );
		} );
	} );
	// Set labels without dimensions for add media button modal.
	$( document ).ready( function() {
		$( '#tmpl-attachment-display-settings' ).text( function( index, text ) {
			return text.replace( / &ndash; {{ size.width }} &times; {{ size.height }}/g, '' );
		} );
	} );
})( jQuery );
