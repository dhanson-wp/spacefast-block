<?php
/**
 * Plugin Name:       Spacefast Block
 * Description:       Show any Spacefast Space inline, full page, as a card, or as a link.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Derek Hanson
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       spacefast-block
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

defined( 'ABSPATH' ) || exit;

define( 'SPACEFAST_BLOCK_VERSION', '0.1.0' );
define( 'SPACEFAST_BLOCK_DIR', plugin_dir_path( __FILE__ ) );

require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-url.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-metadata.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-lookup.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-api-client.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-connection.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-rest-controller.php';

/**
 * Registers the Spacefast block from the build directory.
 */
function register_block() {
	register_block_type( SPACEFAST_BLOCK_DIR . 'build/space' );
}
add_action( 'init', __NAMESPACE__ . '\register_block' );

Rest_Controller::init();
