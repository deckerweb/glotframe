<?php
/**
 * Optional site-owned cleanup; translation data is never touched.
 *
 * @package GlotFrame
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Apply the current website's explicit deletion preference; layouts include revisions.
 * @return void Removes only GlotFrame data when opted in.
 */
function glotframe_uninstall_site(): void {
	$settings = get_option( 'glotframe_settings', array() );
	if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
		return;
	}
	do {
		$ids = get_posts(
			array(
				'post_type'      => 'glotframe_layout',
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $ids as $id ) {
			if ( ! wp_delete_post( $id, true ) ) {
					return;
			}
		}
	} while ( count( $ids ) === 100 );
	delete_option( 'glotframe_settings' );
}
if ( is_multisite() ) {
	$glotframe_offset = 0;
	do {
		$glotframe_sites = get_sites(
			array(
				'number' => 100,
				'offset' => $glotframe_offset,
				'fields' => 'ids',
			)
		);
		foreach ( $glotframe_sites as $glotframe_site_id ) {
			switch_to_blog( $glotframe_site_id );
			try {
					glotframe_uninstall_site();
			} finally {
					restore_current_blog();
			}
		}
		$glotframe_offset += 100;
	} while ( count( $glotframe_sites ) === 100 );
} else {
	glotframe_uninstall_site();
}
require_once __DIR__ . '/includes/deckerweb-plugin-library/lifecycle.php';
deckerweb_library_uninstall_v3( __DIR__ . '/glotframe.php' );
$glotframe_updater_class = require __DIR__ . '/includes/deckerweb-github-release-updater-v2.php';
$glotframe_updater_class::clear_repository_cache( 'https://github.com/deckerweb/glotframe' );
