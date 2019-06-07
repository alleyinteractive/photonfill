<?php
/**
 * Photonfill Main Class
 *
 * @package Photonfill
 * @subpackage Plugin
 * @version 0.1.14
 */

if ( ! class_exists( 'Photonfill' ) ) {

	/** Photonfill class **/
	class Photonfill {

		/**
		 * Instance.
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * The breakpoints used for picturefill.
		 *
		 * @var $breakpoints
		 */
		public $breakpoints = array();

		/**
		 * All known image sizes
		 *
		 * @var $image_sizes
		 */
		public $image_sizes = array();

		/**
		 * Valid units accepted
		 *
		 * @var $valid_units
		 */
		private $valid_units = array( 'px', 'em' );

		/**
		 * If we make guesses on em. What is our base pixel unit.
		 * You can hook this if your theme is running something other than 16px.
		 * This is only relevant when guessing on images without a size specified and breakpoints are defined in em.
		 *
		 * @var $base_unit_pixel
		 */
		public $base_unit_pixel = 16;

		/**
		 * External URL ID slug.
		 * Instead of using an attachment ID, external images uses a generic slug.
		 *
		 * @var $external_url_slug. string.
		 */
		public $external_url_slug = 'external_url';

		/**
		 * Transform object.
		 * Used for hooking photon
		 *
		 * @var $transform
		 */
		public $transform = null;

		/**
		 * Our hook prefix ('jetpack' or 'my').
		 *
		 * @var $hook_prefix
		 */
		private $hook_prefix;

		/**
		 * Constructor
		 *
		 * @return void
		 */
		public function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		/**
		 * Instance constructor.
		 *
		 * @return instance
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Photonfill();
				self::$instance->setup();
			}
			return self::$instance;
		}

		/**
		 * Setup filters and actions.
		 */
		public function setup() {
			// Set our breakpoints.
			$this->base_unit_pixel = apply_filters( 'photonfill_base_unit_pixel', $this->base_unit_pixel );

			/**
			 * A breakpoint can accept the following parameters
			 *   'max' => int, // Max width of element
			 *   'min' => int, // Min width of element
			 *   'unit' => string, // [px(default),em] Currently does not support vw unit, multi units or calc function.
			 * )
			 * wp_get_attachment_image does not use pixel density.
			 */
			$this->breakpoints = apply_filters(
				'photonfill_breakpoints',
				array(
					'mobile' => array(
						'max' => 640,
					),
					'mini-tablet' => array(
						'min' => 640,
					),
					'tablet' => array(
						'min' => 800,
					),
					'desktop' => array(
						'min' => 1040,
					),
					'hd-desktop' => array(
						'min' => 1280,
					),
					'all' => array(
						'min' => 0,
					),
				)
			);

			// Set our url transform class.
			$this->transform = Photonfill_Transform();

			$this->hook_prefix = photonfill_hook_prefix();

			// Make sure to set image sizes after all image sizes have been added in theme.
			// Set priority to a high number to ensure images have been added.
			add_action( 'after_setup_theme', array( $this, 'set_image_sizes' ), 100 );

			// `wp_get_attachment_image` can use srcset attributes and not the picture elements.
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_img_srcset_attr' ), 20, 3 );
			// Make sure stringed sources go back w/ the full source.
			add_filter( 'wp_get_attachment_image_src', array( $this, 'set_original_src' ), 20, 4 );

			// Create our picture element.
			add_filter( 'post_thumbnail_html', array( $this, 'get_picturefill_html' ), 20, 5 );
			add_filter( 'get_image_tag', array( $this, 'get_image_tag_html' ), 20, 6 );

			// Disable creating multiple images for newly uploaded images.
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'disable_image_multi_resize' ) );

			// Skip inline image anchor tags.
			if ( apply_filters( 'photonfill_use_full_size_as_link', true ) ) {
				add_filter( $this->hook_prefix . '_photon_post_image_args', array( $this, 'skip_inline_anchor_links' ), 10, 2 );
			}

			// Parse legacy content images for lazyloading. You can technically use this for non-lazy loaded images as well as it just replaces the entire image tag.
			if ( apply_filters( 'photonfill_parse_legacy_content_images', false ) ) {
				add_filter( 'the_content', array( $this, 'filter_the_content_images' ) );
				add_filter( 'the_content_feed', array( $this, 'filter_the_content_images' ) );
				add_filter( 'get_post_gallery', array( $this, 'filter_the_content_images' ) );
			}

			// If we are lazyloading images add in simple style to help better calculate image sizes on the fly.
			if ( photonfill_use_lazyload() ) {
				add_filter( 'wp_head', array( $this, 'add_lazyload_image_size_styles' ) );
			}

			// Allow image sizes to be set when adding content via the modal in the admin area.
			if ( is_admin() ) {
				// Add breakpoint data to image metadata.
				add_filter( 'wp_get_attachment_metadata', array( $this, 'add_image_metadata' ), 20, 2 );

				// Add breakpoint data to image size dropdowns.
				add_filter( 'image_size_names_choose', array( $this, 'image_size_names_choose' ), 20 );

				add_filter( 'image_send_to_editor_url', array( $this, 'image_send_to_editor_url' ), 20, 4 );

				// When adding captions, set a width for the image so it can generate shortcode correctly.
				add_filter( 'image_send_to_editor', array( $this, 'add_width_for_captions' ), 10, 8 );

				// Ajax handlers used for Fieldmanager specific fixes for media meta boxes but can be used globally for any external ajax calls.
				add_action( 'wp_ajax_get_img_object', array( $this, 'ajax_get_img_object' ) );
				add_action( 'wp_ajax_nopriv_get_img_object', array( $this, 'ajax_get_img_object' ) );

				add_filter( 'fieldmanager_media_preview', array( $this, 'set_fieldmanager_media' ), 10, 3 );

				// Make sure we only prepare js attachment data for the query-attachments action.
				add_action( 'ajax_query_attachments_args', array( $this, 'set_prepare_js_hook' ) );

				add_filter( 'content_save_pre', array( $this, 'swap_lazyload_classes' ), 10, 1 );

				// Ensure the required attributes are allowed in the editor.
				add_filter( 'wp_kses_allowed_html', array( $this, 'photonfill_kses_allowed_html' ), 10, 2 );
			}
		}

		/**
		 * Set the src to the original when hooked.
		 *
		 * @param object $image Image object.
		 * @param int    $attachment_id Attachment id.
		 * @param string $size Size other than full.
		 * @param bool   $icon Icon, not used.
		 * @return object Image object
		 */
		public function set_original_src( $image, $attachment_id, $size, $icon ) {
			if ( ! photonfill_is_enabled() ) {
				return $image;
			}

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
			// Get theme set image sizes.
			$this->image_sizes = apply_filters( 'photonfill_image_sizes', $this->image_sizes );

			// If none, lets guess.
			if ( empty( $this->image_sizes ) ) {
				$this->image_sizes = $this->create_image_sizes();
			}

			// Make sure we have a full element so we can iterate properly later.
			if ( ! array_key_exists( 'full', $this->image_sizes ) ) {
				// Make sure the `full` breakpoint exists.
				foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
					$this->image_sizes['full'][ $breakpoint ] = array();
				}
			}
		}

		/**
		 * Use all image sizes to create a set of image sizes and breakpoints that can be used by picturefill
		 *
		 * @return array of image sizes & breakpoints for picturefill.
		 */
		public function create_image_sizes() {
			global $_wp_additional_image_sizes;

			$images_sizes = array();

			// @codingStandardsIgnoreStart
			$intermediate_sizes = get_intermediate_image_sizes();
			// @codingStandardsIgnoreEnd

			if ( ! empty( $intermediate_sizes ) ) {
				foreach ( $intermediate_sizes as $size ) {
					$width = isset( $_wp_additional_image_sizes[ $size ]['width'] ) ? intval( $_wp_additional_image_sizes[ $size ]['width'] ) : get_option( "{$size}_size_w" );
					$height = isset( $_wp_additional_image_sizes[ $size ]['height'] ) ? intval( $_wp_additional_image_sizes[ $size ]['height'] ) : get_option( "{$size}_size_h" );

					foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
						$breakpoint_width = $this->get_breakpoint_width( $breakpoint );
						if ( ! empty( $breakpoint_width ) ) {
							// Don't constrain the height.
							$new_size = wp_constrain_dimensions( $width, $height, $breakpoint_width, $height );
							$image_sizes[ $size ][ $breakpoint ] = array(
								'width' => $new_size[0],
								'height' => $new_size[1],
							);
						}
					}
				}
			}
			return $image_sizes;
		}

		/**
		 * Get the breakpoint width if set. Otherwise guess from max and min.
		 *
		 * @param string $breakpoint Breakpoint for width.
		 * @return Breakpoint width int or nothing.
		 */
		public function get_breakpoint_width( $breakpoint ) {
			if ( ! empty( $this->breakpoints[ $breakpoint ] ) ) {
				$breakpoint_widths = $this->breakpoints[ $breakpoint ];
				if ( ! empty( $breakpoint_widths['unit'] ) && 'em' === $breakpoint_widths['unit'] ) {
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
		 * Used when guessing image sizes. Will grab the closest larger min breakpoint width.
		 *
		 * @param int $size A min breakpoint width.
		 * @return size
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
					$index = array_search( $size, $mins, true );
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
		 *
		 * @param array $sizes Size info.
		 * @return Disable image resize on upload.
		 */
		public function disable_image_multi_resize( $sizes ) {
			if ( apply_filters( 'photonfill_enable_resize_upload', false ) ) {
				return $sizes;
			}
			return false;
		}

		/**
		 * Add picturefill img srcset attibute to wp_get_attachment_image.
		 *
		 * @param array  $attr Attribute for image.
		 * @param object $attachment Image attachment.
		 * @param string $size Size for image.
		 * @return array attributes for image
		 */
		public function add_img_srcset_attr( $attr, $attachment, $size ) {
			if ( ! photonfill_is_enabled() ) {
				return $attr;
			}

			if ( ! empty( $attachment->ID ) ) {
				$image = $this->create_image_object( $attachment->ID, $size );
				if ( ! empty( $image['id'] ) ) {
					$srcset = array();
					$sizes = array();

					$maxsize = 0;
					foreach ( $image['sizes'] as $breakpoint => $breakpoint_data ) {
						$maxsize = $breakpoint_data['src']['width'] > $maxsize ? $breakpoint_data['src']['width'] : $maxsize;
						// We don't allow pixel density here. Only in the picture element.
						$src = esc_url( $breakpoint_data['src']['url'] ) . ' ' . esc_attr( $breakpoint_data['src']['width'] . 'w' );
						if ( ! in_array( $src, $srcset, true ) ) {
							$srcset[] = $src;
						}

						$unit = ( ! empty( $breakpoint_data['size']['unit'] ) &&
							in_array( $breakpoint_data['size']['unit'], $this->valid_units, true ) ) ?
								$breakpoint_data['size']['unit'] :
								'px';

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
							if ( ! in_array( $size_attr, $sizes, true ) ) {
								$sizes[] = $size_attr;
							}
						}
					}

					if ( ! in_array( trim( $maxsize . 'px' ), $sizes, true ) ) {
						// Add in our default length.
						$sizes[] = trim( $maxsize . 'px' );
					}

					$attr['draggable'] = 'false';

					if ( photonfill_use_lazyload() ) {
						$attr['class'] .= ' lazyload';
						$attr['data-sizes'] = 'auto';
						$attr['data-srcset'] = implode( ',', $srcset );
						$full_src = wp_get_attachment_image_src( $attachment->ID, 'full' );
						$attr['data-src'] = esc_url( $full_src[0] );

						// Make sure core attributes aren't set here to ensure lazysizes will calculate its own data attributes.
						if ( isset( $attr['sizes'] ) ) {
							unset( $attr['sizes'] );
						}
						if ( isset( $attr['srcset'] ) ) {
							unset( $attr['srcset'] );
						}

						// Attempt to set a low-quality image src for initial load.
						$lofi_src = self::get_src_from_srcset( $attr['data-srcset'], true );
						if ( ! empty( $lofi_src ) ) {
							$attr['src'] = $lofi_src;
						}
					} else {
						$attr['sizes'] = implode( ',', $sizes );
						$attr['srcset'] = implode( ',', $srcset );
					}
				} // End if().
			} // End if().
			return $attr;
		}

		/**
		 * Create the necessary data structure for an attachment image.
		 *
		 * @param int    $attachment_id Attachment id.
		 * @param array  $current_size Current size.
		 * @param string $args Optional args. defined args are currently.
		 * @return array
		 */
		public function create_image_object( $attachment_id, $current_size, $args = array() ) {
			if ( ! empty( $attachment_id ) ) {
				$sizes = $this->image_sizes;
				$image_sizes = array();
				// Ensure size value is valid. Use 'full' if not.
				$current_size = $this->get_valid_size( $current_size );

				// If we are full, we may not have height and width params. Grab original dimensions.
				if ( 'full' === $current_size ) {
					$attachment_meta = wp_get_attachment_metadata( $attachment_id, true );
					$current_size = array( $attachment_meta['width'], $attachment_meta['height'] );
				}

				if ( ! is_array( $current_size ) && ! empty( $sizes[ $current_size ] ) ) {
					foreach ( $sizes[ $current_size ] as $breakpoint => $img_size ) {
						$default = ( ! empty( $img_size['default'] ) ) ? true : false;
						$current_w = empty( $img_size['width'] ) ? 0 : $img_size['width'];
						$current_h = empty( $img_size['height'] ) ? 0 : $img_size['height'];
						$transform_args = apply_filters(
							'photonfill_pre_transform_args',
							array(
								'attachment_id' => $attachment_id,
								'callback' => ( isset( $img_size['callback'] ) ) ? $img_size['callback'] : null,
								'crop' => ( isset( $img_size['crop'] ) ) ? $img_size['crop'] : true,
								'breakpoint' => $breakpoint,
								'image_size' => $current_size,
								'width' => $current_w,
								'height' => $current_h,
								'quality' => ( isset( $img_size['quality'] ) ) ? $img_size['quality'] : null,
							),
							$attachment_id,
							$current_size,
							$args
						);
						$this->transform->setup( $transform_args );
						$img_src = $this->get_img_src( $attachment_id, array( $current_w, $current_h ), $default );
						$image_sizes[ $breakpoint ] = array(
							'size' => $this->breakpoints[ $breakpoint ],
							'src' => $img_src,
						);
					}
				} elseif ( is_array( $current_size ) ) {
					// If our size in an array of ints parse it differently.
					foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
						$breakpoint_width = $this->get_breakpoint_width( $breakpoint );
						$breakpoint_height = ( ! empty( $breakpoint_widths['height'] ) ) ? $breakpoint_widths['height'] : 9999;
						$new_size = wp_constrain_dimensions( $current_size[0], $current_size[1], $breakpoint_width, $breakpoint_height );
						$transform_args = apply_filters(
							'photonfill_pre_transform_args',
							array(
								'attachment_id' => $attachment_id,
								'callback' => ( isset( $img_size['callback'] ) ) ? $img_size['callback'] : null,
								'crop' => ( isset( $breakpoint_widths['crop'] ) ) ? $breakpoint_widths['crop'] : true,
								'breakpoint' => $breakpoint,
								'image_size' => 'full',
								'width' => $new_size[0],
								'height' => $new_size[1],
								'quality' => ( isset( $breakpoint_widths['quality'] ) ) ? $breakpoint_widths['quality'] : null,
							),
							$attachment_id,
							$current_size,
							$args
						);
						$this->transform->setup( $transform_args );
						$img_src = $this->get_img_src( $attachment_id, $new_size );
						$image_sizes[ $breakpoint ] = array(
							'size' => $this->breakpoints[ $breakpoint ],
							'src' => $img_src,
						);
					}
				} // End if().

				return array(
					'id' => $attachment_id,
					'sizes' => $image_sizes,
					'args' => $args,
				);
			} // End if().
			return false;
		}

		/**
		 * Create the necessary data structure for an external url image.
		 *
		 * @param string $img_url Image url pre-filter.
		 * @param string $current_size Image size.
		 * @param array  $args Args for image.
		 * @return array of args
		 */
		public function create_url_image_object( $img_url, $current_size, $args = array() ) {
			if ( ! empty( $img_url ) ) {
				$sizes = $this->image_sizes;
				$image_sizes = array();

				if ( ! is_array( $current_size ) && ! empty( $sizes[ $current_size ] ) ) {
					foreach ( $sizes[ $current_size ] as $breakpoint => $img_size ) {
						$default = ( ! empty( $img_size['default'] ) ) ? true : false;
						$current_w = empty( $img_size['width'] ) ? 0 : $img_size['width'];
						$current_h = empty( $img_size['height'] ) ? 0 : $img_size['height'];
						$transform_args = apply_filters(
							'photonfill_pre_transform_args',
							array(
								'callback' => ( isset( $img_size['callback'] ) ) ? $img_size['callback'] : null,
								'crop' => ( isset( $img_size['crop'] ) ) ? $img_size['crop'] : true,
								'breakpoint' => $breakpoint,
								'image_size' => $current_size,
								'width' => $current_w,
								'height' => $current_h,
								'quality' => ( isset( $img_size['quality'] ) ) ? $img_size['quality'] : null,
							),
							$this->external_url_slug,
							$current_size,
							$args
						);
						$this->transform->setup( $transform_args );
						$img_src = $this->get_url_img_src( $img_url, array( $current_w, $current_h ), $default );
						$image_sizes[ $breakpoint ] = array(
							'size' => $this->breakpoints[ $breakpoint ],
							'src' => $img_src,
						);
					}
				} elseif ( is_array( $current_size ) ) {
					// If our size in an array of ints parse it differently.
					foreach ( $this->breakpoints as $breakpoint => $breakpoint_widths ) {
						$breakpoint_width = $this->get_breakpoint_width( $breakpoint );
						$breakpoint_height = ( ! empty( $breakpoint_widths['height'] ) ) ? $breakpoint_widths['height'] : 9999;
						$new_size = wp_constrain_dimensions( $current_size[0], $current_size[1], $breakpoint_width, $breakpoint_height );
						$transform_args = apply_filters(
							'photonfill_pre_transform_args',
							array(
								'callback' => ( isset( $img_size['callback'] ) ) ? $img_size['callback'] : null,
								'crop' => ( isset( $breakpoint_widths['crop'] ) ) ? $breakpoint_widths['crop'] : true,
								'breakpoint' => $breakpoint,
								'image_size' => 'full',
								'width' => $new_size[0],
								'height' => $new_size[1],
								'quality' => ( isset( $breakpoint_widths['quality'] ) ) ? $breakpoint_widths['quality'] : null,
							),
							$this->external_url_slug,
							$current_size,
							$args
						);
						$this->transform->setup( $transform_args );
						$img_src = $this->get_url_img_src( $img_url, $new_size );
						$image_sizes[ $breakpoint ] = array(
							'size' => $this->breakpoints[ $breakpoint ],
							'src' => $img_src,
						);
					}
				} // End if().

				return array(
					'id' => $this->external_url_slug,
					'sizes' => $image_sizes,
					'args' => $args,
				);
			} // End if().
			return false;
		}

		/**
		 * Add our photonfill sizes to image metadata
		 *
		 * @param array $data Existing image metadata.
		 * @param int   $attachment_id Image being examined.
		 * @return array image data with Photonfill
		 */
		public function add_image_metadata( $data, $attachment_id ) {
			if ( ! empty( $data['file'] ) ) {
				$width = ( ! empty( $data['width'] ) ) ? $data['width'] : 0;
				$height = ( ! empty( $data['height'] ) ) ? $data['height'] : 0;

				foreach ( $this->image_sizes as $size => $breakpoint ) {
					$data['sizes'][ $size ] = array(
						'file' => wp_basename( $data['file'] ),
						'width' => $width,
						'height' => $height,
						'mime-type' => 'image/jpeg',
					);
				}
			}
			return $data;
		}

		/**
		 * Add image sizes to image select dropdown.
		 *
		 * @param array $data Existing image data for dropdown.
		 * @return array image data
		 */
		public function image_size_names_choose( $data ) {
			foreach ( $this->image_sizes as $size => $breakpoint ) {
				$data[ $size ] = photonfill_wordify_slug( $size );
			}
			return $data;
		}

		/**
		 * Set a hook to properly prepare media browser js.
		 *
		 * @param array $query args.
		 * @return array $query args
		 */
		public function set_prepare_js_hook( $query ) {
			add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ) );
			return $query;
		}

		/**
		 * Pass Photon URLs to media browser so it doesn't show full-sized images
		 *
		 * @param array $attachment Attachment object.
		 * @return array Attachment object.
		 */
		public function prepare_attachment_for_js( $attachment ) {
			$photon_url_function = photonfill_hook_prefix() . '_photon_url';

			if ( ! empty( $attachment['sizes']['medium'] ) ) {
				$medium_size = $attachment['sizes']['medium'];

				$this->transform->setup(
					array(
						'width' => $medium_size['width'],
						'height' => $medium_size['height'],
					)
				);

				$attachment['sizes']['medium']['url'] = $photon_url_function(
					$medium_size['url'],
					array(
						'attachment_id' => $attachment['id'],
						'width' => $medium_size['width'],
						'height' => $medium_size['height'],
					)
				);
			} elseif ( ! empty( $attachment['sizes']['full']['url'] ) ) {
				$medium_size = array(
					'width' => 300,
					'height' => 225,
				);

				$this->transform->setup( $medium_size );

				$attachment['sizes']['medium']['url'] = $photon_url_function(
					$attachment['sizes']['full']['url'],
					array(
						'attachment_id' => $attachment['id'],
						'width' => $medium_size['width'],
						'height' => $medium_size['height'],
					)
				);
			}
			return $attachment;
		}

		/**
		 * Add image caption requires element to have a width to display shortcode correctly
		 *
		 * @param string $html Markup for image.
		 * @param id     $id Image id.
		 * @param string $caption Image caption.
		 * @param string $title Image title.
		 * @param string $align Image alignment.
		 * @param string $url Image url.
		 * @param string $size Image size.
		 * @param string $alt Image alt text.
		 * @return string html Markup for image
		 */
		public function add_width_for_captions( $html, $id, $caption, $title, $align, $url, $size, $alt = '' ) {
			$caption = apply_filters( 'image_add_caption_text', $caption, $id );
			$count = 0;
			// If we have an image with only one size, lets set that to the width, this allows the use images in the wp editor for changing sizes.
			$html = preg_replace( '/sizes\=\"(\d+)px\"/i', 'sizes="$1px" width="$1"', $html, 1, $count );
			if ( ! empty( $caption ) && 0 === $count ) {
				if ( is_numeric( $size ) ) {
					$size_px = $size;
				} elseif ( is_array( $size ) ) {
					$size_px = $size[0];
				} else {
					$attachment_meta = wp_get_attachment_metadata( $id, true );
					$size_px = $attachment_meta['width'];
				}
				$html = preg_replace( '/<img\s/i', '<img width="' . esc_attr( $size_px ) . '" ', $html );
			}
			return $html;
		}

		/**
		 * Ajax responder to get image object.
		 * This responder takes an attachment id and returns the photonfill image/picture element
		 * This is used for fieldmanager media metaboxes to override the local image element but it can be used for any ajax call.
		 * TODO: Get this to account for sizes other than full.
		 *
		 * @return void Echos image markup.
		 */
		public function ajax_get_img_object() {
			check_ajax_referer( 'photonfill_get_img_object', 'nonce' );
			if ( ! empty( $_POST['attachment'] ) ) {
				$attachment_id = absint( $_POST['attachment'] );
				echo wp_kses_post(
					$this->get_attachment_image(
						$attachment_id,
						'full',
						array(
							'style' => 'max-width:100%',
						)
					)
				);
			}
			exit();
		}

		/**
		 * Fix issues with fieldmanager loading images .
		 * Adding an image in a FM metabox does not use photonfill and it scales out of the metabox.
		 * We override the preview with our photonfill image and allow it to scale to the width of it's parent using inline styles.
		 *
		 * @param string $preview Preview HTML.
		 * @param string $value Current field value (not used).
		 * @param object $attachment Attachment object.
		 */
		public function set_fieldmanager_media( $preview, $value, $attachment ) {
			if ( ! empty( $attachment->ID ) && strpos( $attachment->post_mime_type, 'image/' ) === 0 ) {
				$preview = esc_html__( 'Uploaded image:', 'photonfill' ) . '<br />';
				$preview .= '<a href="#">' . $this->get_attachment_image(
					$attachment->ID,
					'full',
					array(
						'style' => 'max-width:100%',
					)
				) . '</a>';
				$preview .= sprintf( '<br /><a href="#" class="fm-media-remove fm-delete">%s</a>', esc_html__( 'remove', 'photonfill' ) );
			}
			return $preview;
		}

		/**
		 * Manipulate our img src.
		 *
		 * @param int     $attachment_id Attachment ID.
		 * @param array   $size This should always be an array of breakpoint width and height.
		 * @param boolean $default Should this be the default srcset for the img element.
		 * @return Image source.
		 */
		private function get_img_src( $attachment_id, $size = null, $default = false ) {
			if ( ! empty( $attachment_id ) ) {
				if ( empty( $size ) ) {
					$attachment_meta = wp_get_attachment_metadata( $attachment_id, true );
					$attachment_width = ( ! empty( $attachment_meta['width'] ) ) ? $attachment_meta['width'] : 0;
					$attachment_height = ( ! empty( $attachment_meta['height'] ) ) ? $attachment_meta['height'] : 0;
					$size = array( $attachment_width, $attachment_height );
				}

				$width = $size[0];
				$height = $size[1];

				$img_src = array(
					'width' => $width,
					'height' => $height,
					'default' => $default,
				);

				// A hack for the fact that photon doesn't work with wp_ajax calls due to is_admin forcing image downsizing to return the original image
				// We can also use this hack to bypass the image downsize hooks which can be problematic on some environments.
				if ( is_admin() || apply_filters( 'photonfill_bypass_image_downsize', false ) ) {
					// Support Jetpack Photon and My Photon.
					$photon_url_function = photonfill_hook_prefix() . '_photon_url';
					$attachment_src = wp_get_attachment_url( $attachment_id );
					$img_src['url'] = $photon_url_function(
						$attachment_src,
						array(
							'attachment_id' => $attachment_id,
							'width' => $img_src['width'],
							'height' => $img_src['height'],
						)
					);
					$img_src['url2x'] = $photon_url_function(
						$attachment_src,
						array(
							'attachment_id' => $attachment_id,
							'width' => ( absint( $img_src['width'] ) * 2 ),
							'height' => ( absint( $img_src['height'] ) * 2 ),
						)
					);
				} else {
					$attachment_src = wp_get_attachment_image_src( $attachment_id, $size );
					$attachment_src_2x = wp_get_attachment_image_src( $attachment_id, array( absint( $width ) * 2, absint( $height ) * 2 ) );
					$img_src['url'] = $attachment_src[0];
					$img_src['url2x'] = $attachment_src_2x[0];
				}

				return $img_src;
			} // End if().
			return false;
		}

		/**
		 * Manipulate a external url img src.
		 *
		 * @param string  $img_url Attachment ID.
		 * @param string  $size Image size.
		 * @param boolean $default Default or not.
		 * @return URL image source.
		 */
		private function get_url_img_src( $img_url, $size = null, $default = false ) {
			if ( ! empty( $img_url ) ) {
				$width = $size[0];
				$height = $size[1];

				$img_src = array(
					'width' => $width,
					'height' => $height,
					'default' => $default,
				);

				// Support Jetpack photon and My Photon.
				$photon_url_function = photonfill_hook_prefix() . '_photon_url';
				$img_src['url'] = $photon_url_function(
					$img_url,
					array(
						'width' => $img_src['width'],
						'height' => $img_src['height'],
					)
				);
				$img_src['url2x'] = $photon_url_function(
					$img_url,
					array(
						'width' => ( absint( $img_src['width'] ) * 2 ),
						'height' => ( absint( $img_src['height'] ) * 2 ),
					)
				);

				return $img_src;
			}
			return false;
		}

		/**
		 * Construct a class string for a picture element
		 *
		 * @param array  $class Array of current classes.
		 * @param int    $attachment_id ID of attachment.
		 * @param string $size Image size.
		 * @return string String of image classes.
		 */
		public function get_image_classes( $class = array(), $attachment_id = null, $size = 'full' ) {
			if ( ! is_array( $class ) ) {
				$class = explode( ' ', $class );
			}
			$size_string = $size;
			if ( is_array( $size ) ) {
				$size_string = implode( 'x', $size );
			}

			if ( is_int( $attachment_id ) ) {
				$class[] = 'wp-image-' . $attachment_id;
			}

			$class[] = 'size-' . esc_attr( $size_string );

			return apply_filters( 'photonfill_picture_class', rtrim( implode( ' ', $class ) ), $attachment_id, $size );
		}

		/**
		 * Alter the_post_thumbnail html.
		 *
		 * @param string       $html Markup for image.
		 * @param int          $post_id Post id.
		 * @param int          $post_thumbnail_id Post thumbnail image id.
		 * @param string       $size Image size.
		 * @param array|string $attr Image attributes.
		 * @return string Image markup.
		 */
		public function get_picturefill_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
			if ( ! photonfill_is_enabled() ) {
				return $html;
			}

			// Core permits $attr to be a query string or an array, but Photonfill expects an array.
			if ( ! is_array( $attr ) ) {
				$original = $attr;
				$attr = array();

				if ( is_string( $original ) && ! empty( $original ) ) {
					wp_parse_str( $original, $attr );
				}
			}

			if ( apply_filters( 'photonfill_use_picture_as_default', false ) ) {
				return $this->get_attachment_picture( $post_thumbnail_id, $size, $attr );
			} else {
				return $this->get_attachment_image( $post_thumbnail_id, $size, $attr );
			}
		}

		/**
		 * Alter get_image_tag html.
		 *
		 * @param string $html Markup for image.
		 * @param int    $id Attachment id.
		 * @param string $alt Image alt.
		 * @param string $title Image title.
		 * @param string $align Image align.
		 * @param string $size Image size.
		 * @return string Image markup.
		 */
		public function get_image_tag_html( $html, $id, $alt, $title, $align, $size ) {
			if ( ! photonfill_is_enabled() ) {
				return $html;
			}

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
			if ( apply_filters( 'photonfill_use_picture_as_default', false ) ) {
				return $this->get_attachment_picture( $id, $size, $attr );
			} else {
				return $this->get_attachment_image( $id, $size, $attr );
			}
		}

		/**
		 * Generate an img element for any attachment id.
		 *
		 * @param int    $attachment_id Attachment id.
		 * @param string $size Image size.
		 * @param array  $attr Image attributes.
		 * @return string Image markup.
		 */
		public function get_attachment_image( $attachment_id, $size = 'full', $attr ) {
			if ( ! empty( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
				// Ensure size value is valid. Use 'full' if not.
				$size = $this->get_valid_size( $size );
				$html = '';
				if ( is_feed() ) {
					$html = wp_get_attachment_image( $attachment_id, $size, false, $attr );
				} elseif ( photonfill_use_lazyload() ) {
					$html = $this->get_lazyload_image( $attachment_id, $size, $attr );
				} else {
					$attr['class']  = $this->get_image_classes( ( empty( $attr['class'] ) ? array() : $attr['class'] ), $attachment_id, $size );
					$attr['sizes']  = $this->get_responsive_image_attribute( $attachment_id, $size, 'sizes' );
					$attr['srcset'] = $this->get_responsive_image_attribute( $attachment_id, $size, 'srcset' );
					$html = $this->build_attachment_image( $attachment_id, $attr );
				}
				return $html;
			}
			return '';
		}

		/**
		 * Allow lazysizes to better calculate image width by setting img width before calculation.
		 *
		 * @return void
		 */
		public function add_lazyload_image_size_styles() {
			if ( photonfill_is_enabled() ) {
				echo '<style>img[data-sizes="auto"] { display: block; width: 100%; }</style>';
			}
		}

		/**
		 * Get a lazy loaded img element
		 *
		 * @param int    $attachment_id Attachment id.
		 * @param string $size Image size.
		 * @param array  $attr Image attributes.
		 * @return string Image markup.
		 */
		public function get_lazyload_image( $attachment_id, $size = 'full', $attr = array() ) {
			if ( empty( $attr['class'] ) ) {
				$attr['class'] = array( 'lazyload' );
			} else {
				if ( ! is_array( $attr['class'] ) ) {
					$attr['class'] = explode( ' ', $attr['class'] );
				}
				$attr['class'][] = 'lazyload';
			}
			$srcset = $this->get_responsive_image_attribute( $attachment_id, $size, 'data-srcset' );
			$sources = explode( ',', $srcset );
			$src = explode( ' ', $sources[0] );

			$attr['data-sizes'] = 'auto';
			$attr['data-src'] = $src[0];
			$attr['data-srcset'] = $srcset;
			$attr['class'] = $this->get_image_classes( $attr['class'], $attachment_id, $size );

			return $this->build_attachment_image( $attachment_id, $attr );
		}

		/**
		 * Generate a picture element for any attachment id.
		 *
		 * @param int    $attachment_id Attachment id.
		 * @param string $size Image size.
		 * @param array  $attr Image attributes.
		 * @return string Picture markup.
		 */
		public function get_attachment_picture( $attachment_id, $size = 'full', $attr ) {
			if ( ! empty( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
				// Ensure size value is valid. Use 'full' if not.
				$size = $this->get_valid_size( $size );
				$html = '';
				if ( is_feed() ) {
					$html = wp_get_attachment_image( $attachment_id, $size, false, $attr );
				} elseif ( photonfill_use_lazyload() ) {
					$html = $this->get_lazyload_image( $attachment_id, $size, $attr );
				} else {
					$featured_image = $this->create_image_object( $attachment_id, $size );
					$default_breakpoint = $size;
					$default_srcset = '';

					if ( ! empty( $featured_image['id'] ) ) {
						$classes = $this->get_image_classes( ( empty( $attr['class'] ) ? array() : $attr['class'] ), $attachment_id, $size );
						$html = '<picture id="picture-' . esc_attr( $attachment_id ) . '" class="' . esc_attr( $classes ) . ' " data-id="' . esc_attr( $featured_image['id'] ) . '">';
						// Here we set our source elements.
						foreach ( $featured_image['sizes'] as $breakpoint => $breakpoint_data ) {
							// If specified as default img fallback.
							if ( ! empty( $breakpoint_data['src']['default'] ) ) {
								$default_breakpoint = array( $breakpoint_data['src']['width'], $breakpoint_data['src']['height'] );
								$default_srcset = $breakpoint_data['src']['url'];
							}

							// Set our source element.
							$srcset_url = esc_url( $breakpoint_data['src']['url'] );

							$use_pixel_density = ( ! empty( $breakpoint_data['size']['pixel-density'] ) ) ? true : false;
							if ( $use_pixel_density && $breakpoint_data['size']['pixel-density'] > 1 ) {
								// Need to grab scaled up Photon args here.
								$srcset_url .= ', ' . esc_url( $breakpoint_data['src']['url2x'] ) . ' 2x';
							}

							// Set source media attribute.
							$srcset_media = '';
							$unit = ( ! empty( $breakpoint_data['size']['unit'] ) &&
								in_array( $breakpoint_data['size']['unit'], $this->valid_units, true ) ) ?
									$breakpoint_data['size']['unit'] :
									'px';
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

							// Write source element.
							$html .= "<source srcset=\"{$srcset_url}\" media=\"{$srcset_media}\" />";
						} // End foreach().

						// No fallback default has been set.
						if ( ( empty( $default_srcset ) && is_array( $default_breakpoint ) ) || is_string( $default_breakpoint ) ) {
							$default_src = wp_get_attachment_image_src( $attachment_id, $default_breakpoint );
							$default_srcset = $default_src[0];
						}

						// Set our default img element.
						$attr['srcset'] = $default_srcset;
						$html .= $this->build_attachment_image( $attachment_id, $attr );
						$html .= '</picture>';
					} // End if().
				} // End if().
				return $html;
			} // End if().
			return;
		}

		/**
		 * Get the HTML for the IMG Tag
		 *
		 * @param int   $attachment_id Attachment id.
		 * @param array $attr Image attributes.
		 * @return string
		 */
		private function build_attachment_image( $attachment_id = null, $attr ) {
			$attr = apply_filters( 'photonfill_img_attributes', $attr, $attachment_id );

			// Update image alt attribute if not set.
			if (
				! isset( $attr['alt'] )
				&& ! empty( $attachment_id )
				&& is_numeric( $attachment_id )
			) {
				$attr['alt'] = $this->get_alt_text( $attachment_id );
			}

			// Update image src attribute if not set.
			if (
				! isset( $attr['src'] )
				&& ! empty( $attachment_id )
				&& is_numeric( $attachment_id )
			) {
				if ( ! empty( $attr['data-srcset'] ) ) {
					$attr['src'] = self::get_src_from_srcset( $attr['data-srcset'] );
				} elseif ( ! empty( $attr['srcset'] ) ) {
					$attr['src'] = self::get_src_from_srcset( $attr['srcset'] );
				} elseif ( ! empty( $attr['data-src'] ) ) {
					$attr['src'] = $attr['data-src'];
				}
			}

			// If we are lazyloading, attempt to get a low-quality placeholder image.
			if ( photonfill_use_lazyload() && ! empty( $attr['data-srcset'] ) ) {
				$lofi_src = self::get_src_from_srcset( $attr['data-srcset'], true );
				if ( ! empty( $lofi_src ) ) {
					$attr['src'] = $lofi_src;
				}
			}

			$html = '<img ';
			foreach ( $attr as $key => $value ) {
				if ( is_bool( $value ) && $value ) {
					$html .= esc_attr( $key ) . ' ';
				} else {
					$html .= sprintf( '%s="%s" ', esc_attr( $key ), esc_attr( $value ) );
				}
			}
			$html .= '>';
			return $html;
		}

		/**
		 * Returns a comma delimited attribute of an image
		 *
		 * @param int    $attachment_id Attachment id.
		 * @param string $img_size Image size.
		 * @param array  $attr_name Image attribute.
		 * @return string Comma delimited attribute.
		 */
		public function get_responsive_image_attribute( $attachment_id, $img_size, $attr_name ) {
			$image = ( ! empty( $attachment_id ) && is_numeric( $attachment_id ) ) ? $this->create_image_object( $attachment_id, $img_size ) : $this->create_url_image_object( $attachment_id, $img_size );
			if ( ! empty( $image['id'] ) ) {
				$attr = array();
				$maxsize = 0;
				foreach ( $image['sizes'] as $breakpoint => $breakpoint_data ) {
					if ( in_array( $attr_name, array( 'srcset', 'data-srcset' ), true ) ) {
						$src = esc_url( $breakpoint_data['src']['url'] ) . ' ' . esc_attr( $breakpoint_data['src']['width'] . 'w' );
						if ( ! in_array( $src, $attr, true ) ) {
							$attr[] = $src;
						}
					} elseif ( in_array( $attr_name, array( 'sizes', 'data-sizes' ), true ) ) {
						$maxsize = $breakpoint_data['src']['width'] > $maxsize ? $breakpoint_data['src']['width'] : $maxsize;
						$unit = ( ! empty( $breakpoint_data['size']['unit'] ) &&
							in_array( $breakpoint_data['size']['unit'], $this->valid_units, true ) ) ?
								$breakpoint_data['size']['unit'] :
								'px';

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
							if ( ! in_array( $size_attr, $attr, true ) ) {
								$attr[] = $size_attr;
							}
						}
					}
				}

				if ( in_array( $attr_name, array( 'sizes', 'data-sizes' ), true ) && ! in_array( trim( $maxsize . 'px' ), $attr, true ) ) {
					// Add in our default length.
					$attr[] = trim( $maxsize . 'px' );
				}
				return implode( ',', $attr );
			} // End if().
			return '';
		}

		/**
		 * Return an array of urls for each breakpoint of an image size
		 *
		 * @param int    $attachment_id Attachment id.
		 * @param string $img_size Image size.
		 * @param int    $pixel_density 1 or 2.
		 * @return array of url's
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
		 *
		 * @param int    $attachment_id Attachment id.
		 * @param string $img_size Image size.
		 * @param string $breakpoint Image breakpoint.
		 * @param int    $pixel_density 1 or 2.
		 * @return string of breakpoint url
		 */
		public function get_breakpoint_url( $attachment_id, $img_size, $breakpoint, $pixel_density = 1 ) {
			$breakpoint_urls = $this->get_breakpoint_urls( $attachment_id, $img_size, $breakpoint, $pixel_density );
			return ( ! empty( $breakpoint_urls[ $breakpoint ] ) ) ? $breakpoint_urls[ $breakpoint ] : false;
		}

		/**
		 * Swap `lazyloaded` class for `lazyload`. Why is this necessary?
		 * We need to unveil lazyload images in TinyMCE, which removes the `lazyload` class and adds the `lazyloaded` class.
		 * This means on the front end lazysizes won't pick up on the image b/c it assumes the image has already been lazyloaded.
		 *
		 * @param string $content Image content.
		 */
		public function swap_lazyload_classes( $content ) {
			return preg_replace( '/(class=\\\\"[^"]*)(lazyloaded)([^"]*")/i', '$1lazyload$3', $content );
		}

		/**
		 * Use and external url to generate a photonfill image element.
		 *
		 * @param string $img_url An external url.
		 * @param string $size A supported WP image size.
		 * @param array  $attr (can set alt and class).
		 * @return string. HTML image element.
		 */
		public function get_url_image( $img_url, $size, $attr = array() ) {
			if ( ! empty( $img_url ) ) {
				// Ensure size value is valid. Use 'full' if not.
				$size = $this->get_valid_size( $size );

				$image = $this->create_url_image_object( $img_url, $size );

				if ( photonfill_use_lazyload() && ! is_feed() ) {
					$html = $this->get_lazyload_image( $img_url, $size, $attr );
				} else {
					$attr['class']  = $this->get_image_classes( ( empty( $attr['class'] ) ? array() : $attr['class'] ), $img_url, $size );
						$attr['src'] = $img_url;
					if ( ! is_feed() ) {
						$attr['sizes']  = $this->get_responsive_image_attribute( $img_url, $size, 'sizes' );
						$attr['srcset'] = $this->get_responsive_image_attribute( $img_url, $size, 'srcset' );
					}

					$html = $this->build_attachment_image( $img_url, $attr );
				}
				return $html;
			} // End if().
			return;
		}

		/**
		 * Set the size to a valid size if it has not been defined.
		 *
		 * @param mixed $size String or array(W,H).
		 * @return mixed String or array(W,H).
		 */
		public function get_valid_size( $size = 'full' ) {
			if ( empty( $size ) || ( is_string( $size ) && ( ! array_key_exists( $size, $this->image_sizes ) || 'post-thumbnail' === $size || 'full' === $size ) ) ) {
				$size = apply_filters( 'photonfill_fallback_image_size', 'full' );
			}
			return $size;
		}

		/**
		 * Allow specific tags and attributes to be saved
		 * which are required for photonfill to properly work
		 *
		 * @param array $allowed Allowed tags.
		 * @param mixed $context Context.
		 * @return array
		 */
		public function photonfill_kses_allowed_html( $allowed, $context ) {
			if ( is_array( $context ) ) {
				return $allowed;
			}

			if ( 'post' === $context ) {
				$allowed['img']['data-src'] = true;
				$allowed['img']['data-srcset'] = true;
				$allowed['img']['srcset'] = true;
				$allowed['img']['sizes'] = true;
				$allowed['img']['media'] = true;
				$allowed['img']['src'] = true;
				$allowed['source']['data-src'] = true;
				$allowed['source']['data-srcset'] = true;
				$allowed['source']['srcset'] = true;
				$allowed['source']['sizes'] = true;
				$allowed['source']['media'] = true;
			}

			return $allowed;
		}

		/**
		 * Get the alt attribute for image.
		 *
		 * @param  int $attachment_id   ID of the attachment.
		 * @return string               Value of alt text
		 */
		public function get_alt_text( $attachment_id ) {
			$attachment = get_post( $attachment_id );

			// First choose image's meta.
			$alt = trim( wp_strip_all_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );

			// If still empty, try this fallback.
			if ( empty( $alt ) ) {
				$alt = trim( wp_strip_all_tags( $attachment->post_excerpt ) ); // If not, use the caption.
			}

			// If still empty, set to nothing.
			// For a11y reasons, an empty string is better than the attachment name.
			if ( empty( $alt ) ) {
				$alt = '';
			}

			return $alt;
		}

		/**
		 * BEGIN INLINE IMAGE HANDLING
		 *
		 * The code below handles parsing images found in the content.
		 */

		/**
		 * BEGIN PARSE LAZYLOADED IMAGES
		 *
		 * Legacy content on sites that use lazy loading will not have the lazyloaded class set in the content as this is handled when a user inserts media into the content on the admin area.
		 * The below functions add in the necessary classes to content.
		 * By default this is disabled but can be enabled using the `photonfill_parse_legacy_lazyloaded_content_images`
		 */

		/**
		 * Parse all inline content and make sure they are being served up responsively.
		 *
		 * @param string $the_content The post content.
		 * @return string
		 */
		public function filter_the_content_images( $the_content ) {
			if ( ! photonfill_is_enabled() ) {
				return $the_content;
			}

			$class = ucfirst( $this->hook_prefix ) . '_Photon';
			$images = $class::parse_images_from_html( $the_content );

			if ( ! empty( $images ) ) {
				foreach ( $images[0] as $index => $tag ) {
					$size = '';
					if ( preg_match( '#class=["|\']?[^"\']*size-([^"\'\s]+)[^"\']*["|\']?#i', $images['img_tag'][ $index ], $size ) ) {
						$size = $size[1];
					}
					$size = $this->get_valid_size( $size );

					preg_match( '#alt=["|\']?([^"\']*)["|\']?#i', $images['img_tag'][ $index ], $alt );
					if ( preg_match( '#class=["|\']?([^"\']*wp-image-([\d]+)[^"\']*)["|\']?#i', $images['img_tag'][ $index ], $matches ) ) {
						$attr = array(
							'class' => $matches[1],
							'alt' => empty( $alt[1] ) ? '' : $alt[1],
						);

						// If a custom width has been set for the image enforce it with inline styles fo lazy loading.
						if ( photonfill_use_lazyload() && preg_match( '#width=["|\']?([\d]+)["|\']?#', $images['img_tag'][ $index ], $width ) ) {
							if ( preg_match( '#style=["|\']?([^"\']*)["|\']?#i', $images['img_tag'][ $index ], $style ) ) {
								$style = "width:{$width[1]}px; {$style[1]}";
							} else {
								$style = "width:{$width[1]}px";
							};
							$attr['style'] = $style;
						}

						// Since we don't have an image size, just set it to the full width.
						add_filter( 'photonfill_default_transform', array( $this, 'set_inline_content_default_transform' ) );

						if ( ! empty( $matches[2] ) && false === get_post_status( $matches[2] ) ) {
							$new_tag = $this->get_attachment_image( $matches[2], $size, $attr );
						} else {
							$new_tag = $this->get_url_image( $images['img_url'][ $index ], $size, $attr );
						}

						remove_filter( 'photonfill_default_transform', array( $this, 'set_inline_content_default_transform' ) );

						if ( ! empty( $new_tag ) ) {
							$the_content = str_replace( $images['img_tag'][ $index ], $new_tag, $the_content );
						}
					}
				} // End foreach().
			} // End if().

			return $the_content;
		}

		/**
		 * Allow a different transform for parsing inline legacy content images.
		 *
		 * @param string $transform The default transform. Uses 'scale_by_width' instead of 'center_crop' as plugin defaults.
		 * @return string
		 */
		public function set_inline_content_default_transform( $transform ) {
			return apply_filters( 'photonfill_legacy_content_images_default_transform', 'scale_by_width' );
		}

		/**
		 * END PARSE LAZYLOADED IMAGES
		 */

		/**
		 * BEGIN ANCHOR TAG LINKS
		 *
		 * The functions below are used to handle parsing the anchor tags in the content.
		 * You can disable this using the hook `photonfill_use_full_size_as_link`
		 */

		/**
		 * This is only called when we filter images in the content.
		 * We set a hook an track what image we are on. If ${prefix}_photon_get_url has no args passed to it, this means it is asking for a link.
		 * We simply set the action hook here.
		 *
		 * @param boolean $boolean Conditional.
		 * @param string  $src URL src.
		 * @return boolean
		 */
		public function skip_inline_anchor_links( $boolean, $src ) {
			add_filter( $this->hook_prefix . '_photon_pre_image_url', array( $this, 'set_full_img_url' ), 20, 2 );
			return $boolean;
		}

		/**
		 * Before photonfill touches the image args, check to see if it has been called without args.
		 * If it has set a hook to return the full image.
		 *
		 * @param string $url Original URL.
		 * @param array  $args New args for Photon.
		 * @return string Modified url string.
		 */
		public function set_full_img_url( $url, $args ) {
			if ( empty( $args ) ) {
				add_filter( $this->hook_prefix . '_photon_pre_args', array( $this, 'set_full_img_url_args' ), 250, 2 );
				remove_filter( $this->hook_prefix . '_photon_pre_image_url', array( $this, 'set_full_img_url' ), 20, 2 );
			}
			return $url;
		}

		/**
		 * Kill all the args and serve up the cdn original image.
		 *
		 * @param array $args Default args for Photon.
		 * @param array $url New args for Photon.
		 * @return array
		 */
		public function set_full_img_url_args( $args, $url ) {
			remove_filter( $this->hook_prefix . '_photon_pre_args', array( $this, 'set_full_img_url_args' ), 250, 2 );
			return array();
		}

		/**
		 * END ANCHOR TAG LINKS
		 */

		/**
		 * END INLINE IMAGE HANDLING
		 */

		/**
		 * Given a srcset string, returns a value for the `src` attribute to be
		 * used while lazyloading that is a small size and low quality. This
		 * ensures that there is a value for the `src` attribute to ensure that
		 * the document is valid HTML and works with assistive technology, but
		 * also ensures that the placeholder image that loads will use a
		 * fraction of the bandwidth of the original.
		 *
		 * @param string $srcset   The srcset string to parse.
		 * @param bool   $lazyload Whether to get a lo-fi placeholder for lazyload.
		 * @return string The URL to use in the `src` attribute on the image.
		 */
		private static function get_src_from_srcset( $srcset, $lazyload = false ) {

			// If $srcset is not a string, bail.
			if ( ! is_string( $srcset ) ) {
				return '';
			}

			// Attempt to split srcset by commas.
			$sources = explode( ',', $srcset );
			if ( empty( $sources ) ) {
				return '';
			}

			// Loop through srcset values and find the smallest one.
			$min = 0;
			$src = '';
			foreach ( $sources as $source ) {
				// Attempt to split the source.
				$source_parts = array_values(
					array_filter(
						explode( ' ', $source )
					)
				);
				if ( 2 !== count( $source_parts ) ) {
					continue;
				}

				// Determine if the specified width is smaller than the reference value.
				$width = (int) trim( $source_parts[1], 'w' );
				if ( 0 === $min || $width < $min ) {
					$min = $width;
					$src = $source_parts[0];
				}

				// If we don't want a lo-fi image, just return the first match.
				if ( ! $lazyload && ! empty( $src ) ) {
					return $src;
				}
			}

			// If we didn't net a src value, bail.
			if ( empty( $src ) ) {
				return '';
			}

			// Append the low-quality flag to the image URL.
			$src_query = (string) wp_parse_url( $src, PHP_URL_QUERY );
			parse_str( $src_query, $src_query_parts );
			$src_query_parts['quality'] = 1;
			return str_replace(
				$src_query,
				http_build_query( $src_query_parts ),
				$src
			);
		}
	}
} // End if().

/**
 * Return Photonfill instance.
 * Ignore coding standards for camelcase.
 **/
 // @codingStandardsIgnoreStart
function Photonfill() {
	return Photonfill::instance();
}
// @codingStandardsIgnoreEnd
add_action( 'after_setup_theme', 'Photonfill' );
