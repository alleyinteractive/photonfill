( function( $ ) {
	//Play nice with fieldmananger media metaboxes
	$(document).on( 'fieldmanager_media_preview', function( event, wrapper, attachment, wp ) {
		$.ajax({
			type: 'POST',
			url: photonfill_wp_vars['wp_ajax_url'],
			data: {
				action : 'get_img_object',
				attachment : attachment.id,
				nonce: photonfill_wp_vars['photonfill_get_img_object_nonce'],
			},
			dataType: 'html',
		})
		.done( function( response ) {
			wrapper.html( '<a href="#">' + response + '</a><br /><a class="fm-media-remove fm-delete" href="#">' + photonfill_wp_vars['photonfill_i18n']['remove'] + '</a>' );
		});
	});
})( jQuery );

