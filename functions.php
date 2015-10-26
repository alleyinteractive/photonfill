<?php
/**
 *
 */
function photonfill_get_image_object( $attachment_id, $img_size = 'full' ) {
	$photonfill = Photonfill();
	return $photonfill->create_image_object( $attachment_id, $img_size );
}

/**
 * Get a srcset or sizes attribute of an attachment
 * Returns a comma delimited attribute of an image
 * @param int. $attachment_id
 * @param string. $img_size
 * @param string. $attr_name. (sizes/data-sizes or srcset/data-srcset)
 * @return string. Comma delimited attribute.
 */
function photonfill_get_image_attribute( $attachment_id, $img_size = 'full', $attr_name = 'srcset' ) {
	$photonfill = Photonfill();
	return $photonfill->get_responsive_image_attribute( $attachment_id, $img_size, $attr_name );
}
