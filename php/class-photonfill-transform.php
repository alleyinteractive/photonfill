<?php
/**
 * Photonfill Transform
 *
 * @package Photonfill
 * @subpackage Plugin
 * @version 0.1.14
 */

if ( ! class_exists( 'Photonfill_Transform' ) ) {

	/**
	 * Photonfill Transform class.
	 */
	class Photonfill_Transform {

		/**
		 * Instance.
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * Our hook prefix ('jetpack' or 'my').
		 *
		 * @var $hook_prefix
		 */
		private $hook_prefix;

		/**
		 * Breakpoint.
		 *
		 * @var $breakpoint
		 */
		public $breakpoint = '';


		/**
		 * Photon arguments.
		 *
		 * @var $args
		 */
		public $args = array();

		/**
		 * Empty constructor.
		 */
		public function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		/**
		 * Generate instance.
		 *
		 * @return $instance of Photonfill Transform.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Photonfill_Transform();
				self::$instance->set_hooks();
			}
			return self::$instance;
		}

		/**
		 * Set our transform hooks.
		 */
		public function set_hooks() {
			$this->hook_prefix = photonfill_hook_prefix();

			// Override Photon arg.
			add_filter( $this->hook_prefix . '_photon_image_downsize_array', array( $this, 'set_photon_args' ), 5, 2 );
			add_filter( $this->hook_prefix . '_photon_image_downsize_string', array( $this, 'set_photon_args' ), 5, 2 );

			// If we're using the photonfill_bypass_image_downsize, we skip downsize, and now need to
			// ensure the Photon args are being set (but with a lower priority).
			if ( apply_filters( 'photonfill_bypass_image_downsize', false ) ) {
				add_filter( $this->hook_prefix . '_photon_pre_args', array( $this, 'set_photon_args' ), 4, 3 );
			}

			// Transform our Photon url.
			add_filter( $this->hook_prefix . '_photon_pre_args', array( $this, 'transform_photon_url' ), 5, 3 );
		}

		/**
		 * Set our transform attributes.
		 *
		 * @param array $args defaults to empty.
		 */
		public function setup( $args = array() ) {
			$this->args = $args;
		}

		/**
		 * Add in necessary args expected with photonfill
		 *
		 * @param array $args Default args for Photon.
		 * @param mixed $data New args for Photon.
		 * @return array of merged args for Photon.
		 */
		public function set_photon_args( $args, $data ) {
			$args = $this->args;
			// Fall back on data if empty.
			if ( ! empty( $data['size'] ) && ! empty( $data['transform'] ) ) {
				if ( empty( $args['width'] ) && ! empty( $data['image_args']['width'] ) ) {
					$args['width'] = $data['image_args']['width'];
				}
				if ( empty( $args['height'] ) && ! empty( $data['image_args']['height'] ) ) {
					$args['height'] = $data['image_args']['height'];
				}
			} else {
				if ( empty( $args['width'] ) && ! empty( $data['width'] ) ) {
					$args['width'] = $data['width'];
				}
				if ( empty( $args['height'] ) && ! empty( $data['height'] ) ) {
					$args['height'] = $data['height'];
				}
			}
			if ( empty( $args['attachment_id'] ) && ! empty( $data['attachment_id'] ) ) {
				$args['attachment_id'] = $data['attachment_id'];
			}

			// Ensure attachment_id / height / width are at least set, if negotiation failed.
			foreach ( [ 'attachment_id', 'height', 'width' ] as $required_property ) {
				if ( ! isset( $args[ $required_property ] ) ) {
					$args[ $required_property ] = 0;
				}
			}

			return $args;
		}

		/**
		 * Transform our Photon url.
		 *
		 * @param array  $args Array of Photon args.
		 * @param string $image_url URL of image.
		 */
		public function transform_photon_url( $args, $image_url ) {
			if ( ! photonfill_is_enabled() ) {
				return $args;
			}

			// Make sure we've properly instantiated our args.
			// If not then photon_url is being called directly and image downsize has been skipped.
			// We can only set width and height at this point.
			// This is easily remidied by simply passing the minimum args of attachment_id, width & height to the jetpack_photon_url() function.
			if ( ! empty( $this->args ) ) {
				$args = wp_parse_args( $args, $this->args );
			}

			if ( empty( $args['attachment_id'] ) && ! empty( $this->args['attachment_id'] ) ) {
				if ( empty( $this->args['width'] ) && empty( $this->args['height'] ) ) {
					$size = explode( ',', reset( $args ) );
					$this->args['width'] = empty( $size[0] ) ? 0 : absint( $size[0] );
					$this->args['height'] = empty( $size[1] ) ? 0 : absint( $size[1] );
				}
				$args = $this->args;
			}
			// If we have a resize parameter grab those dimensions as height & width.
			if ( ! empty( $args['resize'] ) && is_string( $args['resize'] ) ) {
				$size = explode( ',', $args['resize'] );
				$args['width'] = empty( $size[0] ) ? 0 : absint( $size[0] );
				$args['height'] = empty( $size[1] ) ? 0 : absint( $size[1] );
			}

			// If a callback is defined use it to alter our args.
			if ( ! empty( $args['callback'] ) && function_exists( $args['callback'] ) ) {
				return $args['callback']( $args );
			} elseif ( ! empty( $args['callback'] ) && method_exists( $this, $args['callback'] ) ) {
				return $this->{$args['callback']}( $args );
			}

			return $this->default_transform( $args );
		}

		/**
		 * Get the width and height of the default transform. Can be used for other transforms.
		 *
		 * @param array $args Photon args for extracting height and width.
		 * @return array Dimensions.
		 */
		public function get_dimensions( $args ) {
			if (
				empty( $args['height'] )
				&& empty( $args['width'] )
			) {
				return false;
			}
			// We are only going to use the size if none are defined in the transform, which shouldn't happen.
			if ( isset( $args['crop'] ) && false === $args['crop'] ) {
				$h = 100;
			} else {
				$h = $args['height'] . 'px';
			}

			return array(
				'width' => $args['width'],
				'height' => $h,
			);
		}

		/**
		 * Calculate the proper scaled height offset using original attachment aspect ratio.
		 *
		 * @param array $size Dimensions array.
		 * @return float/int offset
		 */
		public function get_center_crop_offset( $size ) {
			// Return the whole image.
			$offset = 0;
			$width = $size['width'];
			$height = $size['height'];
			if ( 100 !== $height && preg_match( '/^(\d+)px$/i', $height, $matches ) ) {
				$height = $matches[1];
				if ( ! empty( $this->args['attachment_id'] ) ) {
					$attachment_meta = wp_get_attachment_metadata( $this->args['attachment_id'], true );
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
		 *
		 * @param array $args Args to be conditionally set.
		 * @return array Merged args with defaults.
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

		/** All transform functions can be found below. **/

		/**
		 * Our default photon transform sets it to fit to width and crop the height from the top left if crop value is not false.
		 *
		 * @param array $args Default transform args.
		 * @return array Processed args.
		 */
		public function default_transform( $args ) {
			// Override the default transform.
			$default_method = apply_filters( 'photonfill_default_transform', 'center_crop' );
			// If an external method is defined use it as default.
			if ( function_exists( $default_method ) ) {
				return $default_method( $args );
			} elseif ( method_exists( $this, $default_method ) ) {
				return $this->$default_method( $args );
			}
			return $this->center_crop( $args );
		}

		/**
		 * Crop image from the top down.
		 * Will fit width of image first.
		 *
		 * @param array $args Transform args.
		 * @return array Processed args.
		 */
		public function top_down_crop( $args ) {
			$size = $this->get_dimensions( $args );
			if ( ! empty( $size ) ) {
				return $this->set_conditional_args( array(
					'w' => $size['width'],
					'crop' => '0,0,100,' . $size['height'],
				) );
			}
		}

		/**
		 * Crop image from the center out.
		 * Will always fit width of image. Will only crop height from center if scaled height is greater than defined height.
		 *
		 * @param array $args Transform args.
		 * @return array Processed args.
		 */
		public function center_crop( $args ) {
			$size = $this->get_dimensions( $args );
			if ( ! empty( $size ) ) {
				$horizontal_offset = $this->get_center_crop_offset( $size );
				return $this->set_conditional_args( array(
					'w' => $size['width'],
					'crop' => '0,' . $horizontal_offset . 'px,100,' . $size['height'],
				) );
			}
		}

		/**
		 * Crop original image from specified crop dimensions, then scale to width.
		 * Will always fit width of image. Will only crop height if scaled height is greater than defined height.
		 *
		 * @param array $args Transform args.
		 * @return array Processed args.
		 */
		public function custom_crop( $args ) {
			$size = $this->get_dimensions( $args );
			if ( ! empty( $size ) ) {
				return $this->set_conditional_args( array(
					'crop' => $args['crop'],
					'w' => $size['width'],
				) );
			}
		}

		/**
		 * Resize and crop an image to exact width,height pixel dimensions.
		 *
		 * @param array $args Transform args.
		 * @return array Processed args.
		 */
		public function resize( $args ) {
			$size = $this->get_dimensions( $args );
			if ( ! empty( $size ) ) {
				// return only required args.
				return $this->set_conditional_args( array(
					'resize' => $size['width'] . ',' . $size['height'],
				) );
			}
		}

		/**
		 * Fit an image to a containing box of width,height dimensions. Image aspect ratio is maintained.
		 * Image is never cropped.
		 *
		 * @param array $args Transform args.
		 * @return array Processed args.
		 */
		public function fit( $args ) {
			$size = $this->get_dimensions( $args );
			if ( ! empty( $size ) ) {
				// return only required args.
				return $this->set_conditional_args( array(
					'fit' => $size['width'] . ',' . $size['height'],
				) );
			}
		}

		/**
		 * Fit an image to a containing box of width. Image aspect ratio is maintained.
		 * Image is never cropped.
		 *
		 * @param array $args Transform args.
		 * @return array Processed args.
		 */
		public function scale_by_width( $args ) {
			$size = $this->get_dimensions( $args );
			if ( ! empty( $size ) ) {
				// return only required args.
				return $this->set_conditional_args( array(
					'w' => $size['width'],
				) );
			}
		}
	}
} // End if().

/**
 * Ignore coding standards for camelcase.
 **/
 // @codingStandardsIgnoreStart
function Photonfill_Transform() {
	return Photonfill_Transform::instance();
}
// @codingStandardsIgnoreEnd
