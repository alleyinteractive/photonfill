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
				self::$instance->set_hooks();
			}
			return self::$instance;
		}

		/**
		 * Set our transform hooks
		 *
		 */
		public function set_hooks() {
			$hook_prefix = photonfill_hook_prefix();

			// Override photon args
			add_filter( $hook_prefix . '_photon_image_downsize_array', array( $this, 'set_photon_args' ), 5, 2 );
			add_filter( $hook_prefix . '_photon_image_downsize_string', array( $this, 'set_photon_args' ), 5, 2 );

			// Transform our photon url
			add_filter( $hook_prefix . '_photon_pre_args', array( $this, 'transform_photon_url' ), 5, 3 );
		}

		/**
		 * Set our transform attributes
		 */
		public function setup( $args = array() ) {
			$this->args = $args;
		}

		/**
		 * Add in necessary args expected with photonfill
		 */
		public function set_photon_args( $args, $data ) {
			$args = $this->args;
			// Fall back on data if empty
			if ( ! empty( $data['size'] ) && ! empty( $data['transform'] ) ) {
				$args['width'] = empty( $args['width'] ) ? $data['image_args']['width'] : $args['width'];
				$args['height'] = empty( $args['height'] ) ? $data['image_args']['height'] : $args['height'];
			} else {
				$args['width'] = empty( $args['width'] ) ? $data['width'] : $args['width'];
				$args['height'] = empty( $args['height'] ) ? $data['height'] : $args['height'];
			}
			$args['attachment_id'] = empty( $args['attachment_id'] ) ? $data['attachment_id'] : $args['attachment_id'];

			return $args;
		}

		/**
		 * Transform our photon url
		 */
		public function transform_photon_url( $args, $image_url, $scheme = null ) {
			// Make sure we've properly instantiated our args.
			// If not then photon_url is being called directly and image downsize has been skipped.
			// We can only set width and height at this point.
			// This is easily remidied by simply passing the minimum args of attachment_id, width & height to the jetpack_photon_url() function.
			if ( empty( $args['attachment_id'] ) && ! empty( $this->args['attachment_id'] ) ) {
				if ( empty( $this->args['width'] ) && empty( $this->args['height'] ) ) {
					$size = explode( ',', reset( $args ) );
					$this->args['width'] = empty( $size[0] ) ? 0 : absint( $size[0] );
					$this->args['height'] = empty( $size[1] ) ? 0 : absint( $size[1] );
				}
				$args = $this->args;
			}

			// If a callback is defined use it to alter our args
			if ( ! empty( $args['callback'] ) && function_exists( $args['callback'] ) ) {
				return $args['callback']( $args );
			} elseif ( ! empty( $args['callback'] ) && method_exists( $this, $args['callback'] ) ) {
				return $this->$args['callback']( $args );
			}

			return $this->default_transform( $args );
		}

		/**
		 * Get the width and height of the default transform. Can be used for other transforms.
		 */
		public function get_dimensions( $args ) {
			// We are only going to use the size if none are defined in the transform, which shouldn't happen.
			if ( isset( $args['crop'] ) && false === $args['crop'] ) {
				$h = 100;
			} else {
				$h = $args['height'] . 'px';
			}

			return array( 'width' => $args['width'], 'height' => $h );
		}

		/**
		 * Calculate the proper scaled height offset using original attachment aspect ratio
		 */
		public function get_center_crop_offset( $size ) {
			// Return the whole image
			$offset = 0;
			$width = $size['width'];
			$height = $size['height'];
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

		/**
		 * Only set photon args if they have been set. This allows default photon functionality to work.
		 */
		public function set_conditional_args( $args ) {
			if ( ! empty( $this->args['quality'] ) ) {
				$args['quality'] = $this->args['quality'];
			}
			// Remove cropping if crop is false.
			if ( isset( $this->args['crop'] ) && false === $this->args['crop'] ) {
				unset( $args['crop'] );
			}
			return $args;
		}

		### All transform functions can be found below. ###

		/**
		 * Our default photon transform sets it to fit to width and crop the height from the top left if crop value is not false.
		 */
		public function default_transform( $args ) {
			// Override the default transform
			$default_method = apply_filters( 'photonfill_default_transform', 'center_crop' );
			// If an external method is defined use it as default
			if ( function_exists( $default_method ) ) {
				return $default_method( $args );
			} elseif ( method_exists( $this, $default_method ) ) {
				return $this->$default_method( $args );
			}
			return $this->center_crop( $args );
		}

		/**
		 * Crop image from the top down
		 * Will fit width of image first
		 */
		public function top_down_crop( $args ) {
			$size = $this->get_dimensions( $args );
			return $this->set_conditional_args( array(
				'w' => $size['width'],
				'crop' => '0,0,100,' . $size['height'],
			) );
		}

		/**
		 * Crop image from the center out.
		 * Will always fit width of image.  Will only crop height from center if scaled height is greater than defined height.
		 */
		public function center_crop( $args ) {
			$size = $this->get_dimensions( $args );
			$horizontal_offset = $this->get_center_crop_offset( $size );
			return $this->set_conditional_args( array(
				'w' => $size['width'],
				'crop' => '0,' . $horizontal_offset . 'px,100,' . $size['height'],
			) );
		}
	}
}

function Photonfill_Transform() {
	return Photonfill_Transform::instance();
}
