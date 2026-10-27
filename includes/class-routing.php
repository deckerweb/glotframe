<?php
/** Optional GlotPress base path and reversible homepage entry. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Integrates with GlotPress's public URL filter without rewriting its routes or WP reading settings. */
final class Routing {
	/** Register early URL filtering and the optional homepage redirect. @return void */
	public static function register_hooks(): void {
		add_filter( 'gp_url_base_path', array( self::class, 'base' ), 20 );
		add_action( 'template_redirect', array( self::class, 'homepage' ), 1 );
	}
	/** Filter only the configured base; an empty override preserves GP_URL_BASE and other GP defaults.
	 * @param string $base Existing GlotPress base path.
	 * @return string Original or normalized site-relative base path.
	 */
	public static function base( string $base ): string {
		if ( ! defined( 'GP_VERSION' ) || version_compare( GP_VERSION, '4.0', '<' ) || version_compare( GP_VERSION, '5.0', '>=' ) ) {
			return $base;
		}
		$raw  = get_option( 'glotframe_settings', array() );
		$slug = is_array( $raw ) && isset( $raw['slug'] ) && is_string( $raw['slug'] ) ? $raw['slug'] : '';
		return '' !== $slug && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ? user_trailingslashit( '/' . $slug ) : $base;
	}
	/** Validate a single path segment against reserved endpoints and published content.
	 * @param string $slug Normalized candidate slug.
	 * @return bool Whether a custom prefix can be saved without a known route collision.
	 */
	public static function valid_slug( string $slug ): bool {
		if ( '' === $slug ) {
			return true;
		}
		$reserved = array( 'wp-admin', 'wp-json', 'wp-content', 'wp-includes', 'wp-login', 'wp-cron', 'xmlrpc', 'feed', 'search', 'author', 'category', 'tag', 'attachment', 'robots', 'sitemap', 'index', 'page', 'favicon' );
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) || in_array( $slug, $reserved, true ) ) {
			return false;
		}
		if ( get_page_by_path( $slug, OBJECT, get_post_types( array( 'public' => true ) ) ) ) {
			return false;
		}
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( is_array( $type->rewrite ) && trim( (string) ( $type->rewrite['slug'] ?? '' ), '/' ) === $slug ) {
				return false;
			}
		}
		return true;
	}
	/** Identify an eligible homepage without capturing posts, feeds, previews, REST or admin requests.
	 * @return bool Whether this GET/HEAD request should enter the portal.
	 */
	public static function should_redirect(): bool {
		return Shell::available() && ! is_admin() && Settings::get()['homepage'] && in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) && is_front_page() && ! is_feed() && ! is_preview() && ! is_glotpress();
	}
	/** Redirect only the WordPress homepage, with no permanent browser cache or settings replacement.
	 * @return void Sends a local temporary redirect and exits only for an eligible request.
	 */
	public static function homepage(): void {
		if ( self::should_redirect() ) {
			wp_safe_redirect( gp_url( '/' ), 302, 'GlotFrame' );
			exit;
		}
	}
}
