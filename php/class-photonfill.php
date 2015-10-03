<?php
if ( !class_exists( 'Photonfill' ) ) {

	class Photonfill {

		private static $instance;

		/**
		 * The breakpoints used for picturefill.
		 */
		public $breakpoints = array();

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
			$this->breakpoints = apply_filters( 'photonfill_breakpoints', array(
				'mobile' => array( 'max' => 640 ), // Max
				'mini-tablet' => 640, // Min
				'tablet' => 800, // Min
				'desktop' => 1040, // Min
				'hd-desktop' => 1280, // Min
			) );

			// Make sure to set image sizes after all image sizes have been added in theme.
			add_action( 'after_setup_theme', array( $this, 'set_image_sizes' ), 200 );

			// wp_get_attachment_image can use srcset attributes and not the picture elements
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_img_srcset_attr' ), 20, 3 );

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
			foreach( array_merge( array( 'full' ), get_intermediate_image_sizes() ) as $size ) {
				if ( 'full' !== $size ) {
					$width = isset( $_wp_additional_image_sizes[ $size ]['width'] ) ? intval( $_wp_additional_image_sizes[$size]['width'] ) : get_option( "{$size}_size_w" );
					$height = isset( $_wp_additional_image_sizes[ $size ]['height'] ) ? intval( $_wp_additional_image_sizes[$size]['height'] ) : get_option( "{$size}_size_h" );
				}

				foreach( $this->breakpoints as $breakpoint => $breakpoint_width ) {
					if ( 'full' === $size ) {
						$image_sizes[ $size ][ $breakpoint ] = array();
					} else {
						// Don't constrain the height.
						$new_size = wp_constrain_dimensions( $width, $height, $breakpoint_width, $height );
						$image_sizes[ $size ][ $breakpoint ] = $new_size;
					}
				}
			}
			return apply_filters( 'photonfill_image_sizes', $image_sizes );
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

				foreach ( $image['sizes'] as $breakpoint => $breakpoint_data ) {
					$srcset[] = "{$breakpoint_data['src']['url']} {$breakpoint_data['width']}w";
					$sizes[] = "(min-width: {$breakpoint_data['width']}px) {$breakpoint_data['src']['width']}px";
				}
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
					$image_sizes[ $breakpoint ] = array( 'width' => $this->breakpoints[ $breakpoint ], 'src' => $img_src );
				}
			} elseif ( is_array( $current_size ) ) {
				foreach ( $this->breakpoints as $breakpoint => $breakpoint_width ) {
					$new_size = wp_constrain_dimensions( $current_size[0], $current_size[1], $breakpoint_width, 9999 );
					$img_src = $this->get_img_src( $attachment_id, $new_size );
					$image_sizes[ $breakpoint ] = array( 'width' => $this->breakpoints[ $breakpoint ], 'src' => $img_src );
				}
			}

			return array( 'id' => $attachment_id, 'sizes' => $image_sizes, 'args' => $args );
		}

		/**
		 * Manipulate our img src
		 */
		private function get_img_src( $attachment_id, $size ) {
			$size = empty( $size ) ? 'full' : $size;
			$attachment_src = wp_get_attachment_image_src( $attachment_id, $size );

			// Lets make this more readable and remove any unwanted data.
			$img_src = array(
				'url' => $attachment_src[0],
				'width' => $attachment_src[1],
				'height' => $attachment_src[2],
			);

			// A hack for the fact that photon doesn't work with wp_ajax calls due to is_admin forcing image downsizing to return the original image
			if ( is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$photon_url = jetpack_photon_url( $img_src['url'], array( 'resize' => $img_src['width'] . ',' . $img_src['height'] ) );
				$img_src['url'] = $photon_url;
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
			$html = $this->get_attachment_image( $post_thumbnail_id, $size, $attr );
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

			$html = $this->get_attachment_image( $id, $size, $attr );
			return $html;
		}

		/**
		 * Generate a picture element for any attachment id.
		 */
		public function get_attachment_image( $attachment_id, $size, $attr ) {
			$featured_image = $this->create_image_object( $attachment_id, $size );

			$classes = $this->get_image_classes( ( empty( $attr['class'] ) ? array() : $attr['class'] ), $attachment_id, $size );
			$html = '';
			if ( ! empty( $featured_image['id'] ) ) {
				$html = '<picture id="picture-' . esc_attr( $attachment_id ) . '" class="' . esc_attr( $classes ) . ' " data-id=' . esc_attr( $featured_image['id'] ) . '">';
				foreach ( $featured_image['sizes'] as $breakpoint => $src ) {
					$html .= '<source srcset="' . esc_url( $src['src']['url'] ) . '" media="(max-width: ' . intval( $src['width'] ) . 'px)" />';
				}
				add_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );
				$html .= wp_get_attachment_image( $attachment_id, $size );
				remove_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );
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