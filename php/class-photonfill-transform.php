<?php
if ( ! class_exists( 'Photonfill_Transform' ) ) {

	class Photonfill_Transform {

		private static $instance;

		/**
		 * The current breakpoint.
		 */
		public $breakpoint = '';

		/**
		 * Class photon args.
		 */
		public $args = array();

		/**
		 * Constructor
		 *
		 * @params string $name
		 * @params url $name optional
		 * @return void
		 */
		public function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Photonfill_Transform();
			}
			return self::$instance;
		}

		/**
		 * Set our transform attributes
		 */
		public function setup( $args = array() ) {
			$this->args = $args;
		}

		/**
		 * Set the quality for any transforms
		 */
		public function set_quality( $args ) {
			if ( ! empty( $this->args['quality'] && empty( $args['quality'] ) ) ) {
				$args['quality'] = $this->args['quality'];
			}
			return $args;
		}

		/**
		 * Get the width and height of the default transform. Can be used for other transforms.
		 */
		public function get_dimensions( $args ) {
			// We are only going to use the size if none are defined in the transform, which shouldn't happen.
			$size = explode( ',', reset( $args ) );
			if ( isset( $this->args['crop'] ) && false === $this->args['crop'] ) {
				$h = 100;
			} elseif ( empty( $this->args['height'] ) ) {
				$h = ( ! empty( $size[1] ) ) ? strval( absint( $size[1] ) ) . 'px' : 100;
			} else {
				$h = $this->args['height'] . 'px';
			}

			if ( empty( $this->args['width'] ) ) {
				$w = ( ! empty( $size[0] ) ) ? absint( $size[0] ) : 0;
			} else {
				$w = $this->args['width'];
			}

			return array( 'width' => $w, 'height' => $h );
		}

		/**
		 * Calculate the proper scaled height offset using original attachment aspect ratio
		 */
		public function get_center_crop_offset( $size ) {
			// Return the whole image
			$offset = 0;
			$width = $size[0];
			$height = $size[1];
			if ( 100 != $height && preg_match( '/^(\d+)px$/i',  $height, $matches ) ) {
				$height = $matches[1];
				if ( ! empty( $this->args['attachment_id'] ) ) {
					$attachment_meta = wp_get_attachment_metadata( $this->args['attachment_id'] );
					if ( ! empty( $attachment_meta['height'] ) && ! empty( $attachment_meta['width'] ) ) {
						$new_size = wp_constrain_dimensions( $attachment_meta['width'], $attachment_meta['height'], $width, 9999 );
						if ( $new_size[1] > $height ) {
							$offset = floor( ( $new_size[1] - $height ) / 2.00 );
						}
					}
				}
			}

			return $offset;
		}

		### All transform functions can be found below. ###

		/**
		 * Our default photon transform sets it to fit to width and crop the height from the top left if crop value is not false.
		 */
		public function default_transform( $args ) {
			$args = $this->set_quality( $args );

			$size = $this->get_dimensions( $args );

			$args['fit'] = $size['width'] . ', 9999';
			$args['crop'] = '0,0,100,' . $size['height'];
			return $args;
		}

		/**
		 * Crop image from the center out.
		 * Will always fit width of image.  Will only crop height from center if scaled height is greater than defined height.
		 */
		public function center_crop( $args ) {
			$this->set_quality( $args );
			// We won't crop if crop is explicitly set to false.
			if ( isset( $this->args['crop'] ) && false === $this->args['crop'] ) {
				return $args;
			} else {
				$size = $this->get_dimensions( $args );
				$horizontal_offset = $this->get_center_crop_offset( $size );
				$args['fit'] = $size[0] . ', 9999';
				$args['crop'] = '0,' . $horizontal_offset . ',100,' . $size[1];
			}

			return $args;
		}
	}
}

function Photonfill_Transform() {
	return Photonfill_Transform::instance();
}
