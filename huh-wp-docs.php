<?php
/**
 * @wordpress-plugin
 * Plugin Name: Huh! WP Docs
 * Plugin URI:  https://silvanhagen.com
 * Description: Show help docs easily in the WordPress admin and the theme customizer.
 * Version:     1.0.0
 * Author:      neverything
 * Author URI:  https://silvanhagen.com
 * Text Domain: huh-wp-docs
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: neverything/huh-wp-docs
 *
 *
 * Note: This plugin is based on the awesome <https://secretpizza.party/huh-making-documentation-easier/> and a fork of
 * it (<https://github.com/Daronspence/huh>) that allows to use multiple markdown files. Thanks for that!
 *
 * @package Neverything\WP_Huh
 */

namespace Neverything\WP_Huh;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	include __DIR__ . '/vendor/autoload.php';
}
if ( ! class_exists( __NAMESPACE__ . '\Plugin' ) ) {
	trigger_error( sprintf( '%s does not exist. Check Composer\'s autoloader.', __NAMESPACE__ . '\Plugin' ) );

	return;
}
/**
 * Initializes the plugin.
 *
 * @since 1.0.0
 */
function init() {
	/**
	 * Default docs url.
	 */
	$doc_urls['all'] = 'https://raw.githubusercontent.com/neverything/huh-wp-docs/master/README.md';

	$plugin = new Plugin();
	$plugin->init( $doc_urls );
}

add_action( 'after_setup_theme', __NAMESPACE__ . '\init', 100 );
