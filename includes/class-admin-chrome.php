<?php
/**
 * Shared visual identity and accessible local footer for GlotFrame administration.
 *
 * @package GlotFrame
 */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Keeps settings, statistics and the native layout list visually connected without replacing WordPress screens. */
final class Admin_Chrome {
	/** @var bool Whether this request has already printed its shared footer. */
	private static bool $footer_printed = false;
	/** Register narrowly scoped shared assets and native-list integration. @return void */
	public static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'all_admin_notices', array( self::class, 'list_header' ), 100 );
		add_action( 'admin_menu', array( self::class, 'history_menu' ), 50 );
		add_filter( 'parent_file', array( self::class, 'history_parent' ) );
		add_action( 'admin_footer', array( self::class, 'list_footer' ), 5 );
	}
	/** Identify the website's native layout overview, preserving other post lists and editors.
	 * @return bool Whether this is the supported list screen for an authorized administrator.
	 */
	public static function is_list(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'edit-glotframe_layout' === $screen->id && ! is_network_admin() && current_user_can( 'manage_options' );
	}
	/** Enqueue shared identity/footer assets only on the three supported admin screens.
	 * @param string $hook Current admin screen hook.
	 * @return void No media or color-picker dependencies are added to the list or statistics.
	 */
	public static function assets( string $hook ): void {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || ( ! in_array( $hook, array( 'toplevel_page_glotframe', 'glotframe_page_glotframe-statistics', 'admin_page_glotframe-history' ), true ) && ! self::is_list() ) ) {
			return;
		}
		wp_enqueue_style( 'glotframe-admin', plugins_url( 'assets/admin.css', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION );
		wp_enqueue_script( 'glotframe-admin-chrome', plugins_url( 'assets/admin-chrome.js', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION, true );
	}
	/** Print one decorative A1 icon with a translated title and selected slogan.
	 * @param string $section Optional translated page label.
	 * @param bool $secondary Whether the native screen retains its own primary heading.
	 * @return void Emits static local image sources with a PNG fallback and escaped text.
	 */
	public static function header( string $section = '', bool $secondary = false ): void {
		echo '<header class="gf-admin-header"><picture class="gf-brand-icon" aria-hidden="true"><source type="image/svg+xml" srcset="' . esc_url( plugins_url( 'assets/images/glotframe-icon.svg', GLOTFRAME_FILE ) ) . '"><img src="' . esc_url( plugins_url( 'assets/images/glotframe-icon-128.png', GLOTFRAME_FILE ) ) . '" width="64" height="64" alt=""></picture><div class="gf-brand-copy">';
		echo $secondary ? '<h2 class="gf-brand-title">' : '<h1 class="gf-brand-title">';
		echo 'GlotFrame' . ( $section ? ' · ' . esc_html( $section ) : '' ) . ' <small>' . esc_html( GLOTFRAME_VERSION ) . '</small>';
		echo $secondary ? '</h2>' : '</h1>';
		echo '<p>' . esc_html__( 'Shape GlotPress. Do more with ease.', 'glotframe' ) . '</p></div></header>';
	}
	/** Add the shared header above the native list, retaining its title, notices and table. @return void */
	public static function list_header(): void {
		if ( self::is_list() ) {
			echo '<div class="wrap glotframe-admin gf-native-branding">';
			self::header( '', true );
			echo '</div>';
		}
	}
	/** Print a shared list footer before footer scripts; JavaScript may place it inside the native content wrap.
	 * @return void The server-rendered fallback remains usable without JavaScript.
	 */
	public static function list_footer(): void {
		if ( self::is_list() ) {
			echo '<div id="gf-native-footer" class="glotframe-admin gf-native-footer-fallback">';
			self::footer();
			echo '</div>';
		}
	}
	/** Register a capability-protected history page without adding another visible submenu. @return void */
	public static function history_menu(): void {
		add_submenu_page( '', __( 'Changelog', 'glotframe' ), __( 'Changelog', 'glotframe' ), 'manage_options', 'glotframe-history', array( self::class, 'history_page' ) );
	}
	/** Keep the hidden history page associated with GlotFrame's navigation.
	 * @param string $parent_menu Existing parent menu file.
	 * @return string GlotFrame on this page only, otherwise the original parent.
	 */
	public static function history_parent( string $parent_menu ): string {
		$screen = get_current_screen();
		return $screen && 'admin_page_glotframe-history' === $screen->id ? 'glotframe' : $parent_menu;
	}
	/** Return a real local history destination, independent of the documentation accordion.
	 * @return string Protected administrative page URL.
	 */
	public static function history_url(): string {
		return admin_url( 'admin.php?page=glotframe-history' );
	}
	/** Render the native-link fallback when the modal is unavailable or JavaScript is disabled. @return void */
	public static function history_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return; }
		echo '<div class="wrap glotframe-admin">';
		self::header( __( 'Changelog', 'glotframe' ) );
		echo '<hr class="wp-header-end"><section class="gf-history-page"><h2 class="screen-reader-text">' . esc_html__( 'Changelog', 'glotframe' ) . '</h2>';
		Admin::changelog();
		echo '</section>';
		self::footer();
		echo '</div>';
	}
	/** Print a single shared footer with real local-documentation fallbacks and a native dialog.
	 * @return void Emits escaped metadata, license and documentation links plus categorized history.
	 */
	public static function footer(): void {
		if ( self::$footer_printed ) {
			return;
		}
		self::$footer_printed = true;
		$docs                 = admin_url( 'admin.php?page=glotframe#gf-help' );
		$history              = self::history_url();
		echo '<footer class="gf-admin-footer"><div class="gf-footer-identity"><strong>GlotFrame</strong><span>' . esc_html( GLOTFRAME_VERSION ) . '</span></div><nav class="gf-footer-links" aria-label="' . esc_attr__( 'GlotFrame resources', 'glotframe' ) . '"><a href="' . esc_url( $docs ) . '">' . esc_html__( 'Documentation', 'glotframe' ) . '</a><a href="' . esc_url( $history ) . '" data-gf-dialog="gf-changelog-dialog">' . esc_html__( 'Changelog', 'glotframe' ) . '</a><a href="' . esc_url( plugins_url( 'LICENSE', GLOTFRAME_FILE ) ) . '">' . esc_html__( 'Plugin license: GPL v2 or later', 'glotframe' ) . '</a></nav><p class="gf-footer-copyright">Copyright © 2017–' . esc_html( wp_date( 'Y' ) ) . ' David Decker – DECKERWEB.</p></footer>';
		echo '<dialog id="gf-changelog-dialog" class="gf-dialog" aria-labelledby="gf-dialog-title" hidden><div class="gf-dialog-heading"><h2 id="gf-dialog-title">' . esc_html__( 'Changelog', 'glotframe' ) . '</h2><form method="dialog"><button class="button" type="submit">' . esc_html__( 'Close', 'glotframe' ) . '</button></form></div><div class="gf-dialog-body">';
		Admin::changelog();
		echo '</div></dialog>';
	}
}
