<?php
/**
 * Removes the data Spacefast Block stores when the plugin is deleted.
 *
 * @package Spacefast_Block
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the stored keys, confirmed hosts, and cached lookups for the current site.
 */
function spacefast_block_uninstall_site() {
	global $wpdb;

	// Hosts confirmed as Spaces.
	delete_option( 'spacefast_block_space_hosts' );

	// The encrypted Spacefast key, for every user.
	delete_metadata( 'user', 0, '_spacefast_block_key', '', true );

	// Connection (device flow) and lookup transients, with their timeouts.
	$patterns = array(
		$wpdb->esc_like( '_transient_spacefast_block_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_spacefast_block_' ) . '%',
		$wpdb->esc_like( '_site_transient_spacefast_block_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_spacefast_block_' ) . '%',
	);
	foreach ( $patterns as $pattern ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

if ( is_multisite() ) {
	foreach (
		get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $spacefast_block_site_id
	) {
		switch_to_blog( $spacefast_block_site_id );
		spacefast_block_uninstall_site();
		restore_current_blog();
	}
} else {
	spacefast_block_uninstall_site();
}
