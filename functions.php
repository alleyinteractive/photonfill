<?php
/**
 * Returns the photonfill image object which contains the image stack for the specified size w/ corresponding breakpoints.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @return array.
 */
function photonfill_get_image_object( $attachment_id, $img_size = 'full' ) {
	$photonfill = Photonfill();
	return $photonfill->create_image_object( $attachment_id, $img_size );
}

/**
 * Returns an array of breakpoint urls for a specific image size.
 * @param int. $attachment_id.
 * @param string. $img_size.
 * @param int. $pixeldensity. (1 or 2)
 * @return array.
 */
function photonfill_get_breakpoint_urls( $attachment_id, $img_size, $pixel_density = 1 ) {
	$photonfill = Photonfill();
	return $photonfill->get_breakpoint_urls( $attachment_id, $img_size, $pixel_density );
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
	$photonfill = Photonfill();
	return $photonfill->get_breakpoint_url( $attachment_id, $img_size, $breakpoint, $pixel_density );
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
	$photonfill = Photonfill();
	return $photonfill->get_responsive_image_attribute( $attachment_id, $img_size, $attr_name );
}
