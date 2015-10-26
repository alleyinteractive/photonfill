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

		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Photonfill_Transform();
			}
			return self::$instance;
		}

		/**
		 * Set our transform attributes
		 */
		public function setup( $args = array(), $breakpoint = null, $image_size = null, $width = null, $height = null ) {
			$this->args = $args;
			$this->breakpoint = $breakpoint;
			$this->image_size = $image_size;
			$this->width = $width;
			$this->height = $height;
		}

		/**
		 * Our default photon transform sets it to fit to width and crop the height
		 */
		public function default_transform( $args ) {
			// We are only going to use the size if none are defined in the transform, which shouldn't happen.
			$size = explode( ',', reset( $args ) );
			if ( isset( $this->args['crop'] ) && false === $this->args['crop'] ) {
				$h = 9999;
			} elseif ( empty( $this->height ) ) {
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

function Photonfill_Transform() {
	return Photonfill_Transform::instance();
}
