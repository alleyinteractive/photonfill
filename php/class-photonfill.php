<?php
if ( !class_exists( 'Photonfill' ) ) {

	class Photonfill {

		private static $instance;

		/**
		 * The breakpoints used for picturefill.
		 */
		public $breakpoints = array();

		/**
		 * All known image sizes
		 */
		public $image_sizes = array();

		/**
		 * Valid units accepted
		 */
		private $valid_units = array( 'px', 'em', 'vw' );

		/**
		 * Class params.
		 */
		public $params = array();

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
				self::$instance = new Photonfill();
				self::$instance->setup();
			}
			return self::$instance;
		}

		public function setup() {
			// Set our breakpoints
			/**
			 * A breakpoint can accept the following parameters
			 * 'breakpoint_name' => array(
			 *		'default' => boolean, // Must set with hook. Set a breakpoint as the default img element. Defaults to full size.
			 *		'max' => int,
			 *		'min' => int,
			 *		'unit' => string, // [px(default),em,vw] Currently does not support multi units or calc function.
			 *		'pixel-density'	=> boolean, // [false(default)] If set, this will override the image srcset widths with 2x.
			 * )
			 * wp_get_attachment_image does not use pixel density.
			 */
			$this->breakpoints = apply_filters( 'photonfill_breakpoints', array(
				'mobile' => array( 'max' => 640 ),
				'mini-tablet' => array( 'min' => 640 ),
				'tablet' => array( 'min' => 800 ),
				'desktop' => array( 'min' => 1040 ),
				'hd-desktop' => array( 'min' => 1280 ),
			) );

			// Make sure to set image sizes after all image sizes have been added in theme.
			add_action( 'after_setup_theme', array( $this, 'set_image_sizes' ), 200 );

			// wp_get_attachment_image can use srcset attributes and not the picture elements
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_img_srcset_attr' ), 20, 3 );
			// Make sure stringed sources go back w/ the full source.
			add_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );

			// Create our picture element
			add_filter( 'post_thumbnail_html', array( $this, 'get_picturefill_html' ), 20, 5 );
			add_filter( 'get_image_tag', array( $this, 'get_picturefill_html' ), 20, 5 );
			add_filter( 'get_image_tag', array( $this, 'get_image_tag_html' ), 20, 6 );

			// Disable creating multiple images for newly uploaded images
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'disable_image_multi_resize' ) );
		}

		/**
		 * Set the src to the original when hooked
		 */
		public function set_original_src( $image, $attachment_id, $size, $icon ) {
			if ( is_string( $size ) && 'full' !== $size ) {
				remove_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );
				$full_src = wp_get_attachment_image_src( $attachment_id, 'full');
				$image[0] = $full_src[0];
				add_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );
			}
			return $image;
		}

		/**
		 * Set image sizes after theme setup.
		 */
		public function set_image_sizes() {
			$this->image_sizes = $this->create_image_sizes();
		}

		/**
		 * Use all image sizes to create a set of image sizes and breakpoints that can be used by picturefill
		 *
		 */
		public function create_image_sizes() {
			global $_wp_additional_image_sizes;

			$images_sizes = array();
			foreach ( get_intermediate_image_sizes() as $size ) {
				$width = isset( $_wp_additional_image_sizes[ $size ]['width'] ) ? intval( $_wp_additional_image_sizes[$size]['width'] ) : get_option( "{$size}_size_w" );
				$height = isset( $_wp_additional_image_sizes[ $size ]['height'] ) ? intval( $_wp_additional_image_sizes[$size]['height'] ) : get_option( "{$size}_size_h" );

				foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
					// If a max breakpoint is set, the use it. Otherwise get the next closest larger min valueas the constraint.
					$breakpoint_width = ( ! empty( $breakpoint_widths['max'] ) ) ? $breakpoint_widths['max'] : $this->get_closest_min_breakpoint( $breakpoint_widths['min'] );

					// Don't constrain the height.
					$new_size = wp_constrain_dimensions( $width, $height, $breakpoint_width, $height );
					$image_sizes[ $size ][ $breakpoint ] = $new_size;
				}
			}

			$image_sizes = apply_filters( 'photonfill_image_sizes', $image_sizes );
			if ( ! array_key_exists( 'full', $image_sizes ) ) {
				// Make sure the 'full' breakpoint exists
				foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
					$image_sizes[ 'full' ][ $breakpoint ] = array();
				}
			}
			return $image_sizes;

		}

		/**
		 * Used when guessing image sizes.  Will grab the closest larger min breakpoint width.
		 * @param int. A min breakpoint width.
		 * @return int
		 */
		public function get_closest_min_breakpoint( $size ) {
			if ( ! empty( $size ) ) {
				$mins = array();
				foreach ( array_values( $this->breakpoints ) as $values ) {
					if ( ! empty( $values['min'] ) ) {
						$mins[] = $values['min'];
					}
				}
				if ( ! empty( $mins ) ) {
					sort( $mins );
					$index = array_search( $size, $mins );
					if ( $index && ! empty( $mins[ $index + 1 ] ) ) {
						return $mins[ $index + 1 ];
					}
				}
			}
			return $size;
		}

		/**
		 * No need to create a bunch of images for resizing if we are using this plugin
		 * Hook this if you want the images uploaded.
		 */
		public function disable_image_multi_resize( $sizes ) {
			if ( apply_filters( 'photonfill_enable_resize_upload', false ) ) {
				return $sizes;
			}
			return false;
		}

		/**
		 * Add picturefill img srcset attibute to wp_get_attachment_image
		 */
		public function add_img_srcset_attr( $attr, $attachment, $size ) {
			$image = $this->create_image_object( $attachment->ID, $size );
			if ( ! empty( $image['id'] ) ) {
				if ( isset( $attr['src'] ) ) {
					unset( $attr['src'] );
				}
				$srcset = array();
				$sizes = array();

				$maxsize = 0;
				foreach ( $image['sizes'] as $breakpoint => $breakpoint_data ) {
					$maxsize = $breakpoint_data['src']['width'] > $maxsize ? $breakpoint_data['src']['width'] : $maxsize;
					// We don't allow pixel density here. Only in the picture element.
					$srcset[] = esc_url( $breakpoint_data['src']['url'] ) . ' ' . esc_attr( $breakpoint_data['src']['width'] . 'w' );

					$unit = ( ! empty( $breakpoint_data['size']['unit'] ) && in_array( $breakpoint_data['size']['unit'], $this->valid_units ) ) ? $breakpoint_data['size']['unit'] : 'px';

					$breakpoint_size_string = '';
					if ( ! empty( $breakpoint_data['size']['min'] ) ) {
						$breakpoint_size_string .= '(min-width: ' . esc_attr( $breakpoint_data['size']['min'] . $unit ) . ') ';
					}
					if ( ! empty( $breakpoint_data['size']['max'] ) ) {
						$breakpoint_size_string .= ( ! empty( $breakpoint_data['size']['min'] ) ) ? ' and ' : '';
						$breakpoint_size_string .= '(max-width: ' . esc_attr( $breakpoint_data['size']['max'] . $unit ) . ') ';
					}
					$sizes[] = $breakpoint_size_string . esc_attr( $breakpoint_data['src']['width'] ) . 'px';
				}
				// Add in our default length
				$sizes[] = esc_attr( $maxsize . 'px' );

				$attr['draggable'] = 'false';
				$attr['sizes'] = implode( ',' ,  $sizes );
				$attr['srcset'] = implode( ',' ,  $srcset );
			}

			return $attr;
		}

		/**
		 * Create the necessary data structure for an attachment image.
		 * @param int $attachment_id
		 * @param array $sizes
		 * @param string $arg Optional args. defined args are currently
		 * @return array
		 */
		public function create_image_object( $attachment_id, $current_size, $args = array() ) {
			$sizes = $this->image_sizes;
			$image_sizes = array();

			if ( ! is_array( $current_size ) && ! empty( $sizes[ $current_size ] ) ) {
				foreach ( $sizes[ $current_size ] as $breakpoint => $img_size ) {
					$img_src = $this->get_img_src( $attachment_id, $img_size );
					$image_sizes[ $breakpoint ] = array( 'size' => $this->breakpoints[ $breakpoint ], 'src' => $img_src );
				}
			} elseif ( is_array( $current_size ) ) {
				foreach ( $this->breakpoints as $breakpoint => $breakpoint_width ) {
					$new_size = wp_constrain_dimensions( $current_size[0], $current_size[1], $breakpoint_width, 9999 );
					$img_src = $this->get_img_src( $attachment_id, $new_size );
					$image_sizes[ $breakpoint ] = array( 'size' => $this->breakpoints[ $breakpoint ], 'src' => $img_src );
				}
			}

			return array( 'id' => $attachment_id, 'sizes' => $image_sizes, 'args' => $args );
		}

		/**
		 * Manipulate our img src
		 * @param int
		 * @param array. This should always be an array of breakpoint width and height
		 */
		private function get_img_src( $attachment_id, $size ) {
			if ( empty( $size ) ) {
				$attachment_meta = wp_get_attachment_metadata( $attachment_id );
				$size = array( $attachment_meta['width'], $attachment_meta['height'] );
			} elseif ( is_string( $size ) ) {

			}

			$width = $size[0];
			$height = $size[1];
			$attachment_src = wp_get_attachment_image_src( $attachment_id, $size );
			$attachment_src_2x = wp_get_attachment_image_src( $attachment_id, array( absint( $width ) * 2, absint( $height ) * 2 ) );

			$img_src = array(
				'url' => $attachment_src[0],
				'url2x' => $attachment_src_2x[0],
				'width' => $width,
				'height' => $height,
			);

			// A hack for the fact that photon doesn't work with wp_ajax calls due to is_admin forcing image downsizing to return the original image
			if ( is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$img_src['url'] = jetpack_photon_url( $img_src['url'], array( 'resize' => $img_src['width'] . ',' . $img_src['height'] ) );
				$img_src['url2x'] = jetpack_photon_url( $img_src['url2x'], array( 'resize' => (string) ( absint( $img_src['width'] ) * 2 ) . ',' . (string) ( absint( $img_src['height'] ) * 2 ) ) );
			}
			return $img_src;
		}

		/**
		 * Construct a class string for a picture element
		 */
		public function get_image_classes( $class = array(), $attachment_id = null, $size = 'full' ) {
			if ( ! is_array( $class ) ) {
				$class = array( $class );
			}
			$class[] = 'size-'  . esc_attr( $size );

			return apply_filters( 'photonfill_picture_class', rtrim( implode( ' ', $class ) ), $attachment_id, $size );
		}

		public function get_picturefill_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
			$html = $this->get_attachment_picture( $post_thumbnail_id, $size, $attr );
			return $html;
		}

		public function get_image_tag_html( $html, $id, $alt, $title, $align, $size ) {
			$attr = array();
			if ( ! empty( $alt ) ) {
				$attr['alt'] = $alt;
			}
			if ( ! empty( $title ) ) {
				$attr['title'] = $title;
			}
			if ( ! empty( $align ) ) {
				$attr['class'] = array( 'align' . esc_attr( $align ) );
			}

			$html = $this->get_attachment_picture( $id, $size, $attr );
			return $html;
		}

		/**
		 * Generate a picture element for any attachment id.
		 */
		public function get_attachment_picture( $attachment_id, $size, $attr ) {
			$featured_image = $this->create_image_object( $attachment_id, $size );
			$classes = $this->get_image_classes( ( empty( $attr['class'] ) ? array() : $attr['class'] ), $attachment_id, $size );
			$default_breakpoint = $size;
			$html = '';
			if ( ! empty( $featured_image['id'] ) ) {
				$html = '<picture id="picture-' . esc_attr( $attachment_id ) . '" class="' . esc_attr( $classes ) . ' " data-id=' . esc_attr( $featured_image['id'] ) . '">';
				foreach ( $featured_image['sizes'] as $breakpoint => $breakpoint_data ) {
					$default_breakpoint = ( ! empty( $breakpoint_data['size']['default'] ) ) ? array( $breakpoint_data['src']['width'], $breakpoint_data['src']['height'] ) : $default_breakpoint;
					$html .= '<source srcset="' . esc_url( $breakpoint_data['src']['url'] );
					$use_pixel_density = ( ! empty( $breakpoint_data['size']['pixel-density'] ) ) ? true : false;
					if ( $use_pixel_density ) {
						// Need to grab scaled up photon args here
						$html .= ', ' . esc_url( $breakpoint_data['src']['url2x'] ) . ' 2x';
					}

					$unit = ( ! empty( $breakpoint_data['size']['unit'] ) && in_array( $breakpoint_data['size']['unit'], $this->valid_units ) ) ? $breakpoint_data['size']['unit'] : 'px';
					$html .= '" media="';
					if ( ! empty( $breakpoint_data['size']['min'] ) ) {
						$html .= '(min-width: ' . esc_attr( $breakpoint_data['size']['min'] . $unit ) . ')';
					}
					if ( ! empty( $breakpoint_data['size']['max'] ) ) {
						$html .= ( ! empty( $breakpoint_data['size']['min'] ) ) ? ' and ' : '';
						$html .= '(max-width: ' . esc_attr( $breakpoint_data['size']['max'] . $unit ) . ')';
					}
					$html .= '" />';
				}
				$alt = ( ! empty( $attr['alt'] ) ) ? ' alt=' . esc_attr( $attr['alt'] ) : '';
				$default_src = wp_get_attachment_image_src( $attachment_id, $default_breakpoint );
				$html .= '<img srcset="' . esc_url( $default_src[0] ) . '"' . $alt . '>';
				$html .= '</picture>';
			}
			return $html;
		}
	}
}

function Photonfill() {
	return Photonfill::instance();
}
add_action( 'after_setup_theme', 'Photonfill' );