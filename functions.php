<?php
/**
 * Returns the photonfill img element markup. For use out of loop.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @param array. $attr. (can set alt and class)
 * @return array.
 */
function photonfill_get_image( $attachment_id, $img_size, $attr = array() ) {
	return Photonfill()->get_attachment_image( $attachment_id, $img_size, $attr );
}

/**
 * Returns the photonfill img element markup. For use out of loop.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @param array. $attr. (can set alt and class)
 * @return array.
 */
function photonfill_get_lazyload_image( $attachment_id, $img_size, $attr = array() ) {
	return Photonfill()->get_lazyload_image( $attachment_id, $img_size, $attr );
}

/**
 * Returns the photonfill picture element markup. For use out of loop.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @param array. $attr. (can set alt and class)
 * @return array.
 */
function photonfill_get_picture( $attachment_id, $img_size, $attr = array() ) {
	return Photonfill()->get_attachment_picture( $attachment_id, $img_size, $attr );
}

/**
 * Returns the photonfill image object which contains the image stack for the specified size w/ corresponding breakpoints.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @return array.
 */
function photonfill_get_image_object( $attachment_id, $img_size = 'full' ) {
	return Photonfill()->create_image_object( $attachment_id, $img_size );
}

/**
 * Use an external url to generate a photonfill image element. Does not support lazy loading.
 * @param string. $img_url.
 * @param string. $img_size;
 * @param array. $attr. (can set alt and class)
 * @return string. html element.
 */
function photonfill_get_url_image( $img_url, $img_size, $attr = array() ) {
	return Photonfill()->get_url_image( $img_url, $img_size, $attr );
}

/**
 * Returns an array of breakpoint urls for a specific image size.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @param int. $pixeldensity. (1 or 2)
 * @return array.
 */
function photonfill_get_breakpoint_urls( $attachment_id, $img_size, $pixel_density = 1 ) {
	return Photonfill()->get_breakpoint_urls( $attachment_id, $img_size, $pixel_density );
}

/**
 * Returns a single breakpoint url for a specific image size.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @param string. $breakpoint.
 * @param int. $pixeldensity. (1 or 2)
 * @return array.
 */
function photonfill_get_breakpoint_url( $attachment_id, $img_size, $breakpoint, $pixel_density = 1 ) {
	return Photonfill()->get_breakpoint_url( $attachment_id, $img_size, $breakpoint, $pixel_density );
}

/**
 * Get a srcset or sizes attribute of an attachment
 * Returns a comma delimited attribute of an image.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @param string. $attr_name. (sizes/data-sizes or srcset/data-srcset)
 * @return string. Comma delimited attribute.
 */
function photonfill_get_image_attribute( $attachment_id, $img_size = 'full', $attr_name = 'srcset' ) {
	return Photonfill()->get_responsive_image_attribute( $attachment_id, $img_size, $attr_name );
}

/**
 * This function exists to attempt to create a label from a slug.
 * If the label is complex you can use the filter hook to create a label lookup or modify single slugs.
 * @param string $slug
 * @param string $callback Callback function to perform on wordified slug
 * @return string
 */
function photonfill_wordify_slug( $slug, $callback = 'ucwords' ) {
	$label = str_replace( array( '-', '_' ), ' ', $slug );
	if ( function_exists( $callback ) ) {
		$label = $callback( $label );
	}
	return apply_filters( 'photonfill_slug_label', $label, $slug, $callback );
}
