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
		 * Current Image Size.
		 */
		public $image_size = '';

		/**
		 * Current Image Width.
		 */
		public $width = '';

		/**
		 * Current Image Width.
		 */
		public $height = '';

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

		public static function instance( $args = array(), $breakpoint = null, $image_size = null, $dimensions = array() ) {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Photonfill_Transform();
				self::$instance->setup( $args, $breakpoint, $image_size, $dimensions );
			}
			return self::$instance;
		}

		/**
		 * Set our transform attributes
		 */
		public function setup( $args = array(), $breakpoint = null, $image_size = null, $dimensions = array() ) {
			$this->args = $args;
			$this->breakpoint = $breakpoint;
			$this->image_size = $image_size;
			if ( is_array( $dimensions ) && count( $dimensions ) == 2 ) {
				$this->width = $dimensions[0];
				$this->height = $dimensions[1];
			}
		}

		/**
		 * Our default photon transform sets it to fit to width and crop the height
		 */
		public function default_transform( $args ) {
			// We are only going to use the size if none are defined in the transform, which shouldn't happen.
			$size = explode( ',', reset( $args ) );
			if ( empty( $this->height ) ) {
				$h = ( ! empty( $size[1] ) ) ? strval( absint( $size[1] ) ) . 'px' : 100;
			} else {
				$h = $this->height . 'px';
			}

			if ( empty( $this->width ) ) {
				$w = ( ! empty( $size[0] ) ) ? absint( $size[0] ) : 0;
			} else {
				$w = $this->width;
			}

			$args['fit'] = $w . ', 9999';
			$args['crop'] = '0,0,100,' . $h;
			return $args;
		}
	}
}

function Photonfill_Transform( $args = array(), $breakpoint = null, $image_size = null, $dimensions = array() ) {
	return Photonfill_Transform::instance( $args, $breakpoint, $image_size, $dimensions );
}
