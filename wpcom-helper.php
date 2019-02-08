<?php
/**
 * Helper file which gets included during the `wpcom_vip_load_plugin` activation
 * from theme's functions.php on the WordPress.com VIP platform.
 *
 * @package Photonfill
 */

/**
 * Call photonfill_init function manually, as it was supposed to get called
 * on plugins_loaded hook, which already fired before we included plugin's files.
 */
photonfill_init();
