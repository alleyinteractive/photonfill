<?php
/**
 * Photonfill.
 *
 * @package Photonfill
 * @subpackage Plugin
 * @version 0.2.0
 */

/*
Plugin Name: Photonfill
Plugin URI: https://github.com/alleyinteractive/photonfill
Description: Integrate Jetpack Photon and Picturefill into WP images
Author: Will Gladstone
Version: 0.2.1
Author URI: https://www.alleyinteractive.com/
*/

/**
 * Setup Photonfill.
 **/
function photonfill_init() {
	require_once( dirname( __FILE__ ) . '/php/class-photonfill-transform.php' );
	require_once( dirname( __FILE__ ) . '/php/class-photonfill.php' );
	require_once( dirname( __FILE__ ) . '/functions.php' );
	add_action( 'wp_enqueue_scripts', 'photonfill_enqueue_assets' );
	add_action( 'admin_enqueue_scripts', 'photonfill_enqueue_assets' );
}
add_action( 'plugins_loaded', 'photonfill_init' );

/**
 * Make sure we have the necessary plugins installed and activated.
 * Special exceptions for VIP and VIP Go.
 *
 * @return void
 */
function photonfill_dependency() {
	if ( ! class_exists( 'My_Photon_Settings' ) && ! class_exists( 'Jetpack' ) ) {
		die( esc_html( __( 'Photonfill requires that either Jetpack Photon or My Photon is installed and active.' ) ) );
	} elseif ( ! class_exists( 'My_Photon_Settings' ) && class_exists( 'Jetpack' ) && ( ! defined( 'VIP_GO_ENV' ) && ! defined( 'WPCOM_IS_VIP_ENV' ) && ! Jetpack::is_module_active( 'photon' ) ) ) {
		die( esc_html( __( 'Photonfill requires that Jetpack Photon is active.' ) ) );
	} elseif ( class_exists( 'My_Photon_Settings' ) && ! My_Photon_Settings()->get( 'active' ) ) {
		die( esc_html( __( 'Photonfill requires that My Photon is active.' ) ) );
	}
}
register_activation_hook( __FILE__, 'photonfill_dependency' );

/**
 * Get the base URL for this plugin.
 *
 * @return string URL pointing to Fieldmanager Plugin top directory.
 */
function photonfill_get_baseurl() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Enqueue scripts and styles
 */
function photonfill_enqueue_assets() {
	wp_enqueue_script( 'picturefilljs', photonfill_get_baseurl() . 'vendor/picturefill.min.js', array( 'jquery' ), '2.3.1', true );

	if ( photonfill_use_lazyload() ) {
		wp_enqueue_script( 'lazysizesjs', photonfill_get_baseurl() . 'vendor/lazysizes.min.js', array( 'jquery' ), '1.2.3rc1', true );
		if ( is_admin() ) {
			add_filter( 'mce_external_plugins', 'photonfill_admin_tinymce_js' );
		}
	}

	// Fieldmanager Media Metabox Fixes.
	if ( is_admin() ) {
		wp_enqueue_script( 'photonfill-admin', photonfill_get_baseurl() . 'js/photonfill-admin.js', array( 'jquery' ), 1.0 );
		wp_localize_script( 'photonfill-admin', 'photonfill_wp_vars', array(
			'wp_ajax_url' => admin_url( 'admin-ajax.php' ),
			'photonfill_get_img_object_nonce' => wp_create_nonce( 'photonfill_get_img_object' ),
			'photonfill_i18n' => array(
				'remove' => __( 'remove', 'photonfill' ),
			),
		) );
	}
}

/**
 * Dequeue devicepx-jetpack.js.
 * This is used for hi-rez avatars and zoomed browser.
 * It does nothing we actually want as you should have images defined for hi-rez devices.
 *
 * @return void
 */
function photonfill_dequeue_devicepx() {
	wp_dequeue_script( 'devicepx' );
}
add_action( 'wp_enqueue_scripts', 'photonfill_dequeue_devicepx', 20 );
add_action( 'customize_controls_enqueue_scripts', 'photonfill_dequeue_devicepx', 20 );
add_action( 'admin_enqueue_scripts', 'photonfill_dequeue_devicepx', 20 );


/**
 * Load photonfill plugin into TinyMCE.
 *
 * @param array $plugins Array of TinyMCE plugins.
 * @return filtered array
 */
function photonfill_admin_tinymce_js( $plugins ) {
	$plugins['photonfill'] = photonfill_get_baseurl() . 'js/photonfill-tinymce-plugin.js';
	return $plugins;
}


/**
 * Are we using lazyloads?
 *
 * @return boolean Using lazyload, defaults to false.
 */
function photonfill_use_lazyload() {
	return apply_filters( 'photonfill_use_lazyload', false );
}

/**
 * Our photon hook prefix as this plugin supports both Jetpack Photon and My Photon
 *
 * @return string. (Either 'jetpack' or 'my');
 */
function photonfill_hook_prefix() {
	// If photon module is active, then use it over My Photon.
	$prefix = 'jetpack';
	if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'photon' ) ) {
		$prefix = 'jetpack';
	} elseif ( class_exists( 'My_Photon_Settings' ) && My_Photon_Settings()->get( 'active' ) ) {
		$prefix = 'my';
	}
	// This setting fails under certain circumstances, like with VIP Go mu-plugins, so a filter is necessary.
	return apply_filters( 'photonfill_hook_prefix', $prefix );
}
