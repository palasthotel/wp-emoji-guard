<?php
/**
 * Runs when the plugin is deleted under Plugins - not on deactivation. Removes the
 * reference option and the hidden reference post, on every site of a network.
 *
 * @package Palasthotel\EmojiGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$emoji_guard_uninstall_site = function () {
	global $wpdb;

	delete_option( '_emoji_guard_validation' );
	delete_option( '_emoji_guard_content_reference' );

	// The post type is not registered while uninstalling, so the posts are found by
	// their type directly; wp_delete_post() takes the post meta with them.
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM $wpdb->posts WHERE post_type = %s",
		'emoji_guard'
	) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $emoji_guard_site_id ) {
		switch_to_blog( $emoji_guard_site_id );
		$emoji_guard_uninstall_site();
		restore_current_blog();
	}
} else {
	$emoji_guard_uninstall_site();
}
