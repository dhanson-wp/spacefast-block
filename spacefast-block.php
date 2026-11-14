<?php
/**
 * Plugin Name:       Spacefast Block
 * Description:       Show any Spacefast Space inline, full page, as a card, or as a link.
 * Version:           0.2.0
 * Requires at least: 6.7
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

define( 'SPACEFAST_BLOCK_VERSION', '0.2.0' );
define( 'SPACEFAST_BLOCK_DIR', plugin_dir_path( __FILE__ ) );

require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-url.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-relay.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-display.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-metadata.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-space-lookup.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-verified-hosts.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-api-client.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-connection.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-rest-controller.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-relay-page.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-relay-controller.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/class-takeover.php';
require_once SPACEFAST_BLOCK_DIR . 'includes/render-helpers.php';

/**
 * Registers the Spacefast block from the build directory.
 */
function register_block() {
	if ( ! file_exists( SPACEFAST_BLOCK_DIR . 'build/space/block.json' ) ) {
		return;
	}
	register_block_type( SPACEFAST_BLOCK_DIR . 'build/space' );
}
add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Tells users who can activate plugins when the plugin has not been built.
 */
function maybe_show_build_notice() {
	if ( file_exists( SPACEFAST_BLOCK_DIR . 'build/space/block.json' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	wp_admin_notice(
		sprintf(
			/* translators: %s: build command. */
			__( 'Spacefast Block needs to be built before it can work. Run %s in the plugin folder.', 'spacefast-block' ),
			'<code>npm install &amp;&amp; npm run build</code>'
		),
		array(
			'type'               => 'error',
			'additional_classes' => array( 'spacefast-block-build-notice' ),
		)
	);
}
add_action( 'admin_notices', __NAMESPACE__ . '\maybe_show_build_notice' );

Rest_Controller::init();
Relay_Controller::init();
Takeover::init();
