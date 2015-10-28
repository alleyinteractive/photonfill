<?php
if ( ! class_exists( 'Photonfill' ) ) {

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
		private $valid_units = array( 'px', 'em' );

		/**
		 * If we make guesses on em. What is our base pixel unit.
		 * You can hook this if your theme is running something other than 16px.
		 * This is only relevant when guessing on images without a size specified and breakpoints are defined in em.
		 */
		public $base_unit_pixel = 16;

		/**
		 * Transform object.
		 * Used for hooking photon
		 */
		public $transform = null;

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
			 *		'max' => int, // Max width of element
			 *		'min' => int, // Min width of element
			 *		'unit' => string, // [px(default),em] Currently does not support vw unit, multi units or calc function.
			 * )
			 * wp_get_attachment_image does not use pixel density.
			 */
			$this->base_unit_pixel = apply_filters( 'photonfill_base_unit_pixel', $this->base_unit_pixel );

			$this->breakpoints = apply_filters( 'photonfill_breakpoints', array(
				'mobile' => array( 'max' => 640 ),
				'mini-tablet' => array( 'min' => 640 ),
				'tablet' => array( 'min' => 800 ),
				'desktop' => array( 'min' => 1040 ),
				'hd-desktop' => array( 'min' => 1280 ),
				'all' => array( 'min' => 0 ),
			) );

			// Set our url transform class
			$this->transform = Photonfill_Transform();

			// Make sure to set image sizes after all image sizes have been added in theme.  Set priority to a high number to ensure images have been added.
			add_action( 'after_setup_theme', array( $this, 'set_image_sizes' ), 100 );

			// wp_get_attachment_image can use srcset attributes and not the picture elements
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_img_srcset_attr' ), 20, 3 );
			// Make sure stringed sources go back w/ the full source.
			add_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );

			// Create our picture element
			add_filter( 'post_thumbnail_html', array( $this, 'get_picturefill_html' ), 20, 5 );
			add_filter( 'get_image_tag', array( $this, 'get_image_tag_html' ), 20, 6 );

			// Disable creating multiple images for newly uploaded images
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'disable_image_multi_resize' ) );
		}

		/**
		 * Set the src to the original when hooked
		 */
		public function set_original_src( $image, $attachment_id, $size, $icon ) {
			if ( is_string( $size ) && 'full' !== $size && wp_attachment_is_image( $attachment_id ) ) {
				remove_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );
				$full_src = wp_get_attachment_image_src( $attachment_id, 'full' );
				$image[0] = $full_src[0];
				add_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );
			}
			return $image;
		}

		/**
		 * Set image sizes after theme setup.
		 */
		public function set_image_sizes() {
			// Get theme set image sizes
			$this->image_sizes = apply_filters( 'photonfill_image_sizes', $this->image_sizes );

			// If none, lets guess.
			if ( empty( $this->image_sizes ) ) {
				$this->image_sizes = $this->create_image_sizes();
			}

			// Make sure we have a full element so we can iterate properly later.
			if ( ! array_key_exists( 'full', $this->image_sizes ) ) {
				// Make sure the 'full' breakpoint exists
				foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
					$this->image_sizes['full'][ $breakpoint ] = array();
				}
			}
		}

		/**
		 * Use all image sizes to create a set of image sizes and breakpoints that can be used by picturefill
		 *
		 */
		public function create_image_sizes() {
			global $_wp_additional_image_sizes;

			$images_sizes = array();
			foreach ( get_intermediate_image_sizes() as $size ) {
				$width = isset( $_wp_additional_image_sizes[ $size ]['width'] ) ? intval( $_wp_additional_image_sizes[ $size ]['width'] ) : get_option( "{$size}_size_w" );
				$height = isset( $_wp_additional_image_sizes[ $size ]['height'] ) ? intval( $_wp_additional_image_sizes[ $size ]['height'] ) : get_option( "{$size}_size_h" );

				foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
					$breakpoint_width = $this->get_breakpoint_width( $breakpoint );
					if ( ! empty( $breakpoint_width ) ) {
						// Don't constrain the height.
						$new_size = wp_constrain_dimensions( $width, $height, $breakpoint_width, $height );
						$image_sizes[ $size ][ $breakpoint ] = array( 'width' => $new_size[0], 'height' => $new_size[1] );
					}
				}
			}
			return $image_sizes;
		}

		/**
		 * Get the breakpoint width if set. Otherwise guess from max and min.
		 *
		 */
		public function get_breakpoint_width( $breakpoint ) {
			if ( ! empty( $this->breakpoints[ $breakpoint ] ) ) {
				$breakpoint_widths = $this->breakpoints[ $breakpoint ];
				if ( ! empty( $breakpoint_widths['unit'] ) && 'em' == $breakpoint_widths['unit'] ) {
					$multiplier = $this->base_unit_pixel;
				} else {
					$multiplier = 1;
				}
				$breakpoint_width = 0;
				if ( ! empty( $breakpoint_widths['width'] ) ) {
					$breakpoint_width = $breakpoint_widths['width'];
				} elseif ( ! empty( $breakpoint_widths['max'] ) ) {
					$breakpoint_width = $breakpoint_widths['max'];
				} elseif ( ! empty( $breakpoint_widths['min'] ) ) {
					$breakpoint_width = $this->get_closest_min_breakpoint( $breakpoint_widths['min'] );
				}
				return $breakpoint_width * $multiplier;
			}
			return;
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
			if ( ! empty( $attachment->ID ) ) {
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
						$src = esc_url( $breakpoint_data['src']['url'] ) . ' ' . esc_attr( $breakpoint_data['src']['width'] . 'w' );
						if ( ! in_array( $src, $srcset ) ) {
							$srcset[] = $src;
						}

						$unit = ( ! empty( $breakpoint_data['size']['unit'] ) && in_array( $breakpoint_data['size']['unit'], $this->valid_units ) ) ? $breakpoint_data['size']['unit'] : 'px';

						$breakpoint_size_string = '';
						if ( ! empty( $breakpoint_data['size']['min'] ) ) {
							$breakpoint_size_string .= '(min-width: ' . esc_attr( $breakpoint_data['size']['min'] . $unit ) . ')';
						}
						if ( ! empty( $breakpoint_data['size']['max'] ) ) {
							$breakpoint_size_string .= ( ! empty( $breakpoint_data['size']['min'] ) ) ? ' and ' : '';
							$breakpoint_size_string .= '(max-width: ' . esc_attr( $breakpoint_data['size']['max'] . $unit ) . ')';
						}
						if ( ! empty( $breakpoint_size_string ) ) {
							$size_attr = $breakpoint_size_string . ' ' . esc_attr( $breakpoint_data['src']['width'] ) . 'px';
							if ( ! in_array( $size_attr, $sizes ) ) {
								$sizes[] = $size_attr;
							}
						}
					}

					if ( ! in_array( trim( $maxsize . 'px' ), $sizes ) ) {
						// Add in our default length
						$sizes[] = trim( $maxsize . 'px' );
					}

					$attr['draggable'] = 'false';

					if ( photonfill_use_lazyload() ) {
						$attr['class'] .= ' lazyload';
						$attr['data-sizes'] = 'auto';
						$attr['data-srcset'] = implode( ',' ,  $srcset );
						$full_src = wp_get_attachment_image_src( $attachment->ID, 'full' );
						$attr['data-src'] = esc_url( $full_src[0] );
					} else {
						$attr['sizes'] = implode( ',' ,  $sizes );
						$attr['srcset'] = implode( ',' ,  $srcset );
					}
				}
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
			if ( ! empty( $attachment_id ) ) {
				$sizes = $this->image_sizes;
				$image_sizes = array();
				// wp_get_attachment_image may pass this by default. Handle it as size 'full'.
				if ( 'post-thumbnail' == $current_size ) {
					$current_size = 'full';
				}

				// If we are full, we may not have height and width params. Grab original dimensions.
				if ( 'full' == $current_size ) {
					$attachment_meta = wp_get_attachment_metadata( $attachment_id );
					$current_size = array( $attachment_meta['width'], $attachment_meta['height'] );
				}

				if ( ! is_array( $current_size ) && ! empty( $sizes[ $current_size ] ) ) {
					foreach ( $sizes[ $current_size ] as $breakpoint => $img_size ) {
						$default = ( ! empty( $img_size['default'] ) ) ? true : false;
						$current_w = empty( $img_size['width'] ) ? 0 : $img_size['width'];
						$current_h = empty( $img_size['height'] ) ? 0 : $img_size['height'];
						$transform_args = array(
							'attachment_id' => $attachment_id,
							'callback' => ( isset( $img_size['callback'] ) ) ? $img_size['callback'] : null,
							'crop' => ( isset( $img_size['crop'] ) ) ? $img_size['crop'] : true,
							'breakpoint' => $breakpoint,
							'image_size' => $current_size,
							'width' => $current_w,
							'height' => $current_h,
							'quality' => ( isset( $img_size['quality'] ) ) ? $img_size['quality'] : null,
						);
						$this->transform->setup( $transform_args );
						$img_src = $this->get_img_src( $attachment_id, array( $current_w, $current_h ), $default );
						$image_sizes[ $breakpoint ] = array( 'size' => $this->breakpoints[ $breakpoint ], 'src' => $img_src );
					}
				} elseif ( is_array( $current_size ) ) {
					foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
						$breakpoint_width = $this->get_breakpoint_width( $breakpoint );
						$breakpoint_height = ( ! empty( $breakpoint_widths['height'] ) ) ? $breakpoint_widths['height'] : 9999;
						$new_size = wp_constrain_dimensions( $current_size[0], $current_size[1], $breakpoint_width, $breakpoint_height );
						$transform_args = array(
							'attachment_id' => $attachment_id,
							'callback' => ( isset( $img_size['callback'] ) ) ? $img_size['callback'] : null,
							'crop' => ( isset( $breakpoint_widths['crop'] ) ) ? $breakpoint_widths['crop'] : true,
							'breakpoint' => $breakpoint,
							'image_size' => 'full',
							'width' => $new_size[0],
							'height' => $new_size[1],
							'quality' => ( isset( $breakpoint_widths['quality'] ) ) ? $breakpoint_widths['quality'] : null,
						);
						$this->transform->setup( $transform_args );
						$img_src = $this->get_img_src( $attachment_id, $new_size );
						$image_sizes[ $breakpoint ] = array( 'size' => $this->breakpoints[ $breakpoint ], 'src' => $img_src );
					}
				}

				return array( 'id' => $attachment_id, 'sizes' => $image_sizes, 'args' => $args );
			}
			return false;
		}

		/**
		 * Manipulate our img src
		 * @param int
		 * @param array. This should always be an array of breakpoint width and height
		 * @param boolean. Should this be the default srcset for the img element.
		 */
		private function get_img_src( $attachment_id, $size, $default = false ) {
			if ( ! empty( $attachment_id ) ) {
				if ( empty( $size ) ) {
					$attachment_meta = wp_get_attachment_metadata( $attachment_id );
					$attachment_width = ( ! empty( $attachment_meta['width'] ) ) ? $attachment_meta['width'] : 0;
					$attachment_height = ( ! empty( $attachment_meta['height'] ) ) ? $attachment_meta['height'] : 0;
					$size = array( $attachment_width, $attachment_height );
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
					'default' => $default,
				);

				// A hack for the fact that photon doesn't work with wp_ajax calls due to is_admin forcing image downsizing to return the original image
				if ( is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					// Support jetpack photon and my_photon
					$photon_url_function = photonfill_hook_prefix() . '_photon_url';
					$img_src['url'] = $photon_url_function( $img_src['url'], array( 'attachment_id' => $attachment_id, 'width' => $img_src['width'], 'height' => $img_src['height'] ) );
					$img_src['url2x'] = $photon_url_function( $img_src['url2x'], array( 'attachment_id' => $attachment_id, 'width' => absint( $img_src['width'] * 2 ), 'height' => absint( $img_src['height'] * 2 ) ) );
				}
				return $img_src;
			}
			return false;
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

		/**
		 * Alter the_post_thumbnail html.
		 */
		public function get_picturefill_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
			$html = $this->get_attachment_picture( $post_thumbnail_id, $size, $attr );
			return $html;
		}

		/**
		 * Alter get_image_tag html.
		 */
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
		public function get_attachment_picture( $attachment_id, $size = 'full', $attr ) {
			if ( ! empty( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
				// This means post thumbnail was called w/o a size arg.
				if ( 'post-thumbnail' == $size ) {
					$size = 'full';
				}
				$alt = ( ! empty( $attr['alt'] ) ) ? ' alt=' . esc_attr( $attr['alt'] ) : '';
				$html = '';
				if ( photonfill_use_lazyload() ) {
					$full_src = wp_get_attachment_image_src( $attachment_id, 'full' );
					$attr['class'][] = 'lazyload';
					$classes = $this->get_image_classes( $attr['class'], $attachment_id, $size );
					$html = '<img data-sizes="auto" data-src="'. esc_url( $full_src[0] ) .'" data-srcset="' . esc_attr( $this->get_responsive_image_attribute( $attachment_id, $size, 'data-srcset' ) ) . '" class="' . esc_attr( $classes ) . '" ' . $alt . '>';
				} else {
					$featured_image = $this->create_image_object( $attachment_id, $size );
					$default_breakpoint = $size;
					if ( ! empty( $featured_image['id'] ) ) {
						$classes = $this->get_image_classes( ( empty( $attr['class'] ) ? array() : $attr['class'] ), $attachment_id, $size );
						$html = '<picture id="picture-' . esc_attr( $attachment_id ) . '" class="' . esc_attr( $classes ) . ' " data-id=' . esc_attr( $featured_image['id'] ) . '">';
						// Here we set our source elements
						foreach ( $featured_image['sizes'] as $breakpoint => $breakpoint_data ) {
							// If specified as default img fallback.
							if ( ! empty( $breakpoint_data['src']['default'] ) ) {
								$default_breakpoint = array( $breakpoint_data['src']['width'], $breakpoint_data['src']['height'] );
								$default_srcset = $breakpoint_data['src']['url'];
							}

							// Set our source element
							$srcset_url = esc_url( $breakpoint_data['src']['url'] );

							$use_pixel_density = ( ! empty( $breakpoint_data['size']['pixel-density'] ) ) ? true : false;
							if ( $use_pixel_density && $breakpoint_data['size']['pixel-density'] > 1 ) {
								// Need to grab scaled up photon args here
								$srcset_url .= ', ' . esc_url( $breakpoint_data['src']['url2x'] ) . ' 2x';
							}

							// Set Source media Attribute
							$srcset_media = '';
							$unit = ( ! empty( $breakpoint_data['size']['unit'] ) && in_array( $breakpoint_data['size']['unit'], $this->valid_units ) ) ? $breakpoint_data['size']['unit'] : 'px';
							if ( ! empty( $breakpoint_data['size']['min'] ) ) {
								$srcset_media .= '(min-width: ' . esc_attr( $breakpoint_data['size']['min'] . $unit ) . ')';
							}
							if ( ! empty( $breakpoint_data['size']['max'] ) ) {
								$srcset_media .= ( ! empty( $breakpoint_data['size']['min'] ) ) ? ' and ' : '';
								$srcset_media .= '(max-width: ' . esc_attr( $breakpoint_data['size']['max'] . $unit ) . ')';
							}
							if ( empty( $srcset_media ) ) {
								$srcset_media = 'all';
							}

							// Write source element
							$html .= "<source srcset=\"{$srcset_url}\" media=\"{$srcset_media}\" />";
						}
						// No fallback default has been set.
						if ( is_string( $default_breakpoint ) ) {
							$default_src = wp_get_attachment_image_src( $attachment_id, $default_breakpoint );
							$default_srcset = $default_src[0];
						}
						// Set our default img element
						$html .= '<img srcset="' . esc_url( $default_srcset ) . '"' . $alt . '>';
						$html .= '</picture>';
					}
				}
				return $html;
			}
			return;
		}

		/**
		 * Returns a comma delimited attribute of an image
		 * @param int. $attachment_id
		 * @param string. $img_size
		 * @param string. $attr_name. (sizes/data-sizes or srcset/data-srcset)
		 * @return string. Comma delimited attribute.
		 */
		public function get_responsive_image_attribute( $attachment_id, $img_size, $attr_name ) {
			$image = $this->create_image_object( $attachment_id, $img_size );
			if ( ! empty( $image['id'] ) ) {
				$attr = array();
				$maxsize = 0;
				foreach ( $image['sizes'] as $breakpoint => $breakpoint_data ) {
					if ( in_array( $attr_name, array( 'srcset', 'data-srcset' ) ) ) {
						$src = esc_url( $breakpoint_data['src']['url'] ) . ' ' . esc_attr( $breakpoint_data['src']['width'] . 'w' );
						if ( ! in_array( $src, $attr ) ) {
							$attr[] = $src;
						}
					} elseif ( in_array( $attr_name, array( 'sizes', 'data-sizes' ) ) ) {
						$maxsize = $breakpoint_data['src']['width'] > $maxsize ? $breakpoint_data['src']['width'] : $maxsize;
						$unit = ( ! empty( $breakpoint_data['size']['unit'] ) && in_array( $breakpoint_data['size']['unit'], $this->valid_units ) ) ? $breakpoint_data['size']['unit'] : 'px';

						$breakpoint_size_string = '';
						if ( ! empty( $breakpoint_data['size']['min'] ) ) {
							$breakpoint_size_string .= '(min-width: ' . esc_attr( $breakpoint_data['size']['min'] . $unit ) . ')';
						}
						if ( ! empty( $breakpoint_data['size']['max'] ) ) {
							$breakpoint_size_string .= ( ! empty( $breakpoint_data['size']['min'] ) ) ? ' and ' : '';
							$breakpoint_size_string .= '(max-width: ' . esc_attr( $breakpoint_data['size']['max'] . $unit ) . ')';
						}
						if ( ! empty( $breakpoint_size_string ) ) {
							$size_attr = $breakpoint_size_string . ' ' . esc_attr( $breakpoint_data['src']['width'] ) . 'px';
							if ( ! in_array( $size_attr, $attr ) ) {
								$attr[] = $size_attr;
							}
						}
					}
				}

				if ( in_array( $attr_name, array( 'sizes', 'data-sizes' ) ) && ! in_array( trim( $maxsize . 'px' ), $attr ) ) {
					// Add in our default length
					$attr[] = trim( $maxsize . 'px' );
				}
				return implode( ',', $attr );
			}
			return;
		}

		/**
		 * Return an array of urls for each breakpoint of an image size
		 * @param int. $attachment_id.
		 * @param string. $img_size.
		 * @param int. $pixeldensity. (1 or 2)
		 * @return array.
		 */
		public function get_breakpoint_urls( $attachment_id, $img_size, $pixel_density = 1 ) {
			$image_object = $this->create_image_object( $attachment_id, $img_size );
			$breakpoint_urls = array();
			foreach ( $image_object['sizes'] as $breakpoint => $data ) {
				if ( $pixel_density > 1 ) {
					$breakpoint_urls[ $breakpoint ] = $data['src']['url2x'];
				} else {
					$breakpoint_urls[ $breakpoint ] = $data['src']['url'];
				}
			}
			return $breakpoint_urls;
		}

		/**
		 * Returns a single breakpoint url for a specific image size.
		 * @param int. $attachment_id.
		 * @param string. $img_size.
		 * @param string. $breakpoint.
		 * @param int. $pixeldensity. (1 or 2)
		 * @return string.
		 */
		public function get_breakpoint_url( $attachment_id, $img_size, $breakpoint, $pixel_density = 1 ) {
			$breakpoint_urls = $this->get_breakpoint_urls( $attachment_id, $img_size, $breakpoint, $pixel_density );
			return ( ! empty( $breakpoint_urls[ $breakpoint ] ) ) ? $breakpoint_urls[ $breakpoint ] : false;
		}
	}
}

function Photonfill() {
	return Photonfill::instance();
}
add_action( 'after_setup_theme', 'Photonfill' );
