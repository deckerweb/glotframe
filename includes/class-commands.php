<?php
/** Contextual links in the existing WordPress Command Palette. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Adds useful layout shortcuts without mounting another palette or adding hotkeys. */
final class Commands {
	/** Observe native admin/editor asset loading. @return void */
	public static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ), 99 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'assets' ), 99 );
	}
	/** Return authorized navigation commands without duplicating generic menu commands.
	 * @return array[] Stable names, translated labels and native editor URLs.
	 */
	public static function entries(): array {
		if ( is_network_admin() || ! current_user_can( 'manage_options' ) ) {
			return array();
		}
		$entries = array(
			array(
				'name'  => 'glotframe/create-layout',
				'label' => __( 'GlotFrame: create a block layout', 'glotframe' ),
				'url'   => admin_url( 'post-new.php?post_type=' . Layouts::POST_TYPE ),
			),
		);
		$s       = Settings::get();
		foreach ( array(
			'header' => __( 'GlotFrame: edit the assigned header', 'glotframe' ),
			'footer' => __( 'GlotFrame: edit the assigned footer', 'glotframe' ),
		) as $region => $label ) {
			$url = Layout_Admin::edit_link( $s[ $region ] );
			if ( $url ) {
				$entries[] = array(
					'name'  => 'glotframe/edit-' . $region,
					'label' => $label,
					'url'   => $url,
				);
			}
		}
		return $entries;
	}
	/** Enqueue only when WordPress itself already supplies a Command Palette.
	 * @return void Adds a small local registration script; no frontend assets or requests.
	 */
	public static function assets(): void {
		$screen = get_current_screen();
		$editor = $screen && $screen->is_block_editor() && wp_script_is( 'wp-commands', 'registered' );
		if ( wp_script_is( 'glotframe-commands', 'enqueued' ) || ( ! wp_script_is( 'wp-commands', 'enqueued' ) && ! $editor ) ) {
			return;
		}
		$entries = self::entries();
		if ( ! $entries ) {
			return;
		}
		wp_enqueue_script( 'glotframe-commands', plugins_url( 'assets/commands.js', GLOTFRAME_FILE ), array( 'wp-commands', 'wp-data' ), GLOTFRAME_VERSION, true );
		wp_localize_script( 'glotframe-commands', 'glotframeCommands', array( 'entries' => $entries ) );
	}
}
