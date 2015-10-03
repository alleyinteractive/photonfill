<?php
/**
 * @package Photonfill
 * @subpackage Plugin
 * @version 0.1
 */
/*
Plugin Name: Photonfill
Plugin URI: http://github.com/willgladstone/photonfill
Description: Integrate Jetpack Photon and Picturefill into WP images
Author: Will Gladstone, Alexi Maschas
Version: 0.1
Author URI: http://www.alleyinteractive.com/
*/

require_once( dirname( __FILE__ ) . '/php/class-plugin-dependency.php' );

function photonfill_init(){
	require_once( dirname( __FILE__ ) . '/php/class-photonfill.php' );
	require_once( dirname( __FILE__ ) . '/functions.php' );

	add_action( 'wp_enqueue_scripts', 'photonfill_enqueue_assets' );
}
add_action('plugins_loaded', 'photonfill_init');

function photonfill_dependency() {
	$photonfill_dependency = new Plugin_Dependency( 'Jetpack', 'Jetpack by WordPress.com', 'http://jetpack.me/' );
	if( ! $photonfill_dependency->verify() ) {
		// Cease activation
	 	die( $photonfill_dependency->message() );
	}
}
register_activation_hook( __FILE__, 'photonfill_dependency' );

/**
 * Get the base URL for this plugin.
 * @return string URL pointing to Fieldmanager Plugin top directory.
 */
function photonfill_get_baseurl() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Enqueue scripts and styles
 */
function photonfill_enqueue_assets() {
	wp_enqueue_script( 'picturefilljs', photonfill_get_baseurl() . '/js/picturefill.min.js', array( 'jquery' ), '2.3.1' );
}
