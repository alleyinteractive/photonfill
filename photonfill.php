<?php
/**
 * @package Photonfill
 * @subpackage Plugin
 * @version 0.1.4
 */
/*
Plugin Name: Photonfill
Plugin URI: http://github.com/willgladstone/photonfill
Description: Integrate Jetpack Photon and Picturefill into WP images
Author: Will Gladstone
Version: 0.1.4
Author URI: http://www.alleyinteractive.com/
*/

require_once( dirname( __FILE__ ) . '/php/class-plugin-dependency.php' );

function photonfill_init() {
	require_once( dirname( __FILE__ ) . '/php/class-photonfill-transform.php' );
	require_once( dirname( __FILE__ ) . '/php/class-photonfill.php' );
	require_once( dirname( __FILE__ ) . '/functions.php' );

	add_action( 'wp_enqueue_scripts', 'photonfill_enqueue_assets' );
	add_action( 'admin_enqueue_scripts', 'photonfill_enqueue_assets' );
}
add_action( 'plugins_loaded', 'photonfill_init' );

function photonfill_dependency() {
	$photonfill_dependency = new Plugin_Dependency( 'Jetpack', 'Jetpack by WordPress.com', 'http://jetpack.me/' );
	if ( ! $photonfill_dependency->verify() ) {
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
	wp_enqueue_script( 'picturefilljs', photonfill_get_baseurl() . 'js/picturefill.min.js', array( 'jquery' ), '2.3.1' );

	if ( photonfill_use_lazyload() ) {
		wp_enqueue_script( 'lazysizesjs', photonfill_get_baseurl() . 'js/lazysizes.min.js', array( 'jquery' ), '1.2.3rc1' );
		if ( is_admin() ) {
			add_filter( 'mce_external_plugins', 'photonfill_admin_tinymce_js' );
		}
	}
}

/**
 * Load tinyMCE photonfill plugin
 */
function photonfill_admin_tinymce_js( $plugins ) {
	$plugins['photonfill'] = photonfill_get_baseurl() . 'js/photonfill-tinymce-plugin.js';
	return $plugins;
}


/**
 * Are we using lazyloads?
 * Default is false.
 */
function photonfill_use_lazyload() {
	return apply_filters( 'photonfill_use_lazyload', false );
}

/**
 * Our photon hook prefix as this plugin supports both Jetpack Photon and My-Photon
 * @return string. (Either 'jetpack' or 'my');
 */
function photonfill_hook_prefix() {
	// If photon module is active, then use it over my photon.
	if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'photon' ) ) {
		return 'jetpack';
	} elseif ( class_exists( 'My_Photon_Settings' ) && My_Photon_Settings::get( 'active' ) ) {
		return 'my';
	}
	return 'jetpack';
}
