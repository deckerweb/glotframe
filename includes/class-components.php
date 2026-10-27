<?php
/**
 * Pinned shared components and host-specific package validation.
 *
 * @package GlotFrame
 */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Initializes the public updater and rejects mismatched update packages. */
final class Components {
	/** @var bool Whether updater configuration failed safely. */
	private static bool $failed = false;
	/** @var string Implementation class actually registered by this host. */
	private static string $implementation = '';
	/**
	* Register host lifecycle hooks; the Library bootstrap is loaded by the main file.
	*
	* @return void
	*/
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'init' ) );
		add_filter( 'upgrader_source_selection', array( self::class, 'validate' ), 30, 4 );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}
	/**
	* Load translations and register the pinned updater without fetching metadata.
	*
	* @return void
	*/
	public static function init(): void {
		load_plugin_textdomain( 'glotframe', false, dirname( plugin_basename( GLOTFRAME_FILE ) ) . '/languages' );
		try {
			$class = require __DIR__ . '/deckerweb-github-release-updater-v2.php';
			if ( ! is_string( $class ) || ! class_exists( $class, false ) || ! defined( $class . '::SUPPORTS_HOST_TRANSLATIONS' ) ) {
				self::$failed = true;
				return;
			}
			$updater = new $class( GLOTFRAME_FILE, GLOTFRAME_REPOSITORY, 'GlotFrame', __( 'Shape your GlotPress workspace with block headers, footers and flexible layouts.', 'glotframe' ), array(), array( 'translate' => require __DIR__ . '/updater-translations.php' ) );
			$updater->register();
			self::$implementation = $class;
		} catch ( \Throwable $error ) {
			self::$failed = true;
		}
	}
	/** Expose local readiness and version without the updater object or credentials.
	 * @return array{available:bool,version:string} Host-bound updater status.
	 */
	public static function status(): array {
		$constant = self::$implementation . '::IMPLEMENTATION_VERSION';
		$version  = self::$implementation && defined( $constant ) ? constant( $constant ) : '';
		return array(
			'available' => ! self::$failed && is_string( $version ) && '' !== $version,
			'version'   => is_string( $version ) ? $version : '',
		);
	}
	/**
	* Show a targeted, safe configuration notice.
	*
	* @return void
	*/
	public static function notice(): void {
		$screen = get_current_screen();
		if ( self::$failed && current_user_can( 'update_plugins' ) && $screen && in_array( $screen->id, array( 'plugins', 'toplevel_page_glotframe' ), true ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'GlotFrame update checks are unavailable. Reinstall a complete GlotFrame package to restore the updater.', 'glotframe' ) . '</p></div>';
		}
	}
	/**
	 * Check exact identity, offered version and platform requirements after normalization.
	 * @param string|\WP_Error $source Normalized extraction directory.
	 * @param string $remote_source Original extraction directory.
	 * @param object $upgrader Native upgrader instance.
	 * @param array $context Native per-package context, including bulk variants.
	 * @return string|\WP_Error Unchanged source or safe rejection.
	 */
	public static function validate( $source, $remote_source, $upgrader, $context ) {
		$file = plugin_basename( GLOTFRAME_FILE );
		if ( is_wp_error( $source ) || ! is_array( $context ) || ( $context['plugin'] ?? '' ) !== $file || ( isset( $context['type'] ) && 'plugin' !== $context['type'] ) || ( isset( $context['action'] ) && 'update' !== $context['action'] ) || ( ! isset( $context['type'], $context['action'] ) && ! ( $upgrader instanceof \Plugin_Upgrader && true === $upgrader->bulk ) ) ) {
			return $source;
		}
		$error = new \WP_Error( 'glotframe_package', __( 'The GlotFrame update package could not be verified.', 'glotframe' ) );
		global $wp_filesystem;
		if ( ! $wp_filesystem || ! is_string( $source ) ) {
			return $error;
		}
		$main = trailingslashit( $source ) . 'glotframe.php';
		$size = $wp_filesystem->size( $main );
		if ( ! $size || $size > 262144 ) {
			return $error;
		}
		$text = $wp_filesystem->get_contents( $main );
		if ( ! is_string( $text ) ) {
			return $error;
		}
		$headers = array();
		foreach ( array( 'Plugin Name', 'Version', 'Text Domain', 'Update URI', 'Requires at least', 'Requires PHP' ) as $field ) {
			$headers[ $field ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $field, '/' ) . ':\s*(.+)$/mi', substr( $text, 0, 8192 ), $match ) ? trim( $match[1] ) : '';
		}
		$updates = get_site_transient( 'update_plugins' );
		$offer   = is_object( $updates ) ? ( $updates->response[ $file ] ?? null ) : null;
		$version = is_object( $offer ) ? ( $offer->new_version ?? '' ) : '';
		if ( ! is_string( $version ) || ! $version || $headers['Version'] !== $version || ! version_compare( $version, GLOTFRAME_VERSION, '>' ) || 'GlotFrame' !== $headers['Plugin Name'] || 'glotframe' !== $headers['Text Domain'] || GLOTFRAME_REPOSITORY !== $headers['Update URI'] ) {
			return $error;
		}
		foreach ( array(
			'Requires at least' => get_bloginfo( 'version' ),
			'Requires PHP'      => PHP_VERSION,
		) as $key => $platform ) {
			if ( ! preg_match( '/^\d+\.\d+(?:\.\d+)?$/D', $headers[ $key ] ) || version_compare( $platform, $headers[ $key ], '<' ) ) {
				return $error;
			}
		}
		if ( version_compare( $headers['Requires at least'], '6.4', '<' ) || version_compare( $headers['Requires PHP'], '8.3', '<' ) ) {
			return $error;
		}
		return $source;
	}
}
