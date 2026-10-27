<?php
/** Scoped German GlotPress interface catalogs and language choice. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;
/** Complements native GP translation loading without editing its files or WordPress language packs. */
final class Localization {
	/** @var \MO|null Current request's supported German catalog. */
	private static $catalog = null;
	/** @var \MO|null Host catalog used for GP-scoped interface labels. */
	private static $host = null;
	/** @var string Active GP interface language, empty outside this request scope. */
	private static string $locale = '';
	/** @var array|null Native part-of-speech labels to restore after the GP request. */
	private static ?array $parts = null;
	/** @var bool Whether this class switched the WordPress locale inside a GP request. */
	private static bool $switched = false;
	/** Register GP-scoped lifecycle hooks and domain-specific translation filters. @return void */
	public static function register_hooks(): void {
		add_action( 'gp_before_request', array( self::class, 'begin' ), 1, 2 );
		add_action( 'gp_after_request', array( self::class, 'end' ), 999 );
		add_filter( 'load_script_translation_file', array( self::class, 'script_file' ), 20, 3 );
		add_filter( 'language_attributes', array( self::class, 'html_language' ) );
		foreach ( array( 'glotframe' ) as $domain ) {
			add_filter( 'gettext_' . $domain, array( self::class, 'singular' ), 15, 3 );
			add_filter( 'gettext_with_context_' . $domain, array( self::class, 'context' ), 15, 4 );
			add_filter( 'ngettext_' . $domain, array( self::class, 'plural' ), 15, 5 );
			add_filter( 'ngettext_with_context_' . $domain, array( self::class, 'plural_context' ), 15, 6 );
		}
		add_filter( 'gettext_glotpress', array( self::class, 'singular' ), 15, 3 );
		add_filter( 'gettext_with_context_glotpress', array( self::class, 'context' ), 15, 4 );
		add_filter( 'ngettext_glotpress', array( self::class, 'plural' ), 15, 5 );
		add_filter( 'ngettext_with_context_glotpress', array( self::class, 'plural_context' ), 15, 6 );
	}
	/** Activate catalogs only for GP interface requests, retaining export/API behavior.
	 * @param string $route_class Native route class.
	 * @param string $method Native route method.
	 * @return void Imports a local MO catalog and optionally switches this request's locale.
	 */
	public static function begin( string $route_class, string $method ): void {
		if ( ! Shell::available() || ! empty( \GP::$current_route->api ) || str_contains( $method, 'export' ) ) {
			return;
		}
		$choice = Settings::get()['gp_language'];
		$locale = 'inherit' === $choice ? determine_locale() : $choice;
		if ( ! in_array( $locale, array( 'de_DE', 'de_DE_formal' ), true ) ) {
			return;
		}
		if ( get_locale() !== $locale ) {
			self::$switched = switch_to_locale( $locale );
		}
		if ( ! class_exists( 'MO' ) ) {
			require_once ABSPATH . WPINC . '/pomo/mo.php';
		}
		self::$locale = $locale;
		$host         = new \MO();
		if ( $host->import_from_file( dirname( GLOTFRAME_FILE ) . '/languages/glotframe-' . $locale . '.mo' ) ) {
			self::$host = $host;
		}
		$catalog = new \MO();
		if ( $catalog->import_from_file( dirname( GLOTFRAME_FILE ) . '/languages/gp/glotpress-' . $locale . '.mo' ) ) {
			self::$catalog = $catalog;
		}
		if ( self::$catalog && isset( \GP::$glossary_entry->parts_of_speech ) ) {
			self::$parts = \GP::$glossary_entry->parts_of_speech;
			foreach ( self::$parts as $key => $label ) {
				if ( self::has( self::$catalog, (string) $key, 'part-of-speech' ) ) {
					\GP::$glossary_entry->parts_of_speech[ $key ] = self::$catalog->translate( (string) $key, 'part-of-speech' ); }
			}
		}
		self::scripts();
	}
	/** Apply bundled JS catalogs to native registered GP handles in the active German locale. @return void */
	public static function scripts(): void {
		if ( ! self::$catalog ) {
			return;
		}
		foreach ( array( 'gp-common', 'gp-editor', 'gp-glossary', 'gp-mass-create-sets-page' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_set_script_translations( $handle, 'glotpress', dirname( GLOTFRAME_FILE ) . '/languages/gp' );
			}
		}
	}
	/** Restore the outer request's language after GP completes. @return void */
	public static function end(): void {
		if ( null !== self::$parts ) {
			\GP::$glossary_entry->parts_of_speech = self::$parts;
			self::$parts                          = null; }
		self::$catalog = null;
		self::$host    = null;
		self::$locale  = '';
		if ( self::$switched ) {
			restore_previous_locale();
			self::$switched = false;}
	}
	/** Translate a singular GP message, preserving unknown native translations.
	 * @param string $translated Existing translation.
	 * @param string $text Original message.
	 * @param string $domain Native domain.
	 * @return string Catalog translation or unchanged native result.
	 */
	public static function singular( string $translated, string $text, string $domain ): string {
		$catalog = 'glotframe' === $domain ? self::$host : self::$catalog;
		return self::has( $catalog, $text ) ? $catalog->translate( $text ) : $translated;
	}
	/** Translate a contextual GP message.
	 * @param string $translated Existing translation.
	 * @param string $text Original message.
	 * @param string $context Message context.
	 * @param string $domain Native domain.
	 * @return string Contextual translation or native result.
	 */
	public static function context( string $translated, string $text, string $context, string $domain ): string {
		$catalog = 'glotframe' === $domain ? self::$host : self::$catalog;
		return self::has( $catalog, $text, $context ) ? $catalog->translate( $text, $context ) : $translated;
	}
	/** Translate native GP plurals with the catalog's plural rule.
	 * @param string $translated Existing result.
	 * @param string $single Singular original.
	 * @param string $plural Plural original.
	 * @param int $number Actual quantity.
	 * @param string $domain Native domain.
	 * @return string Correct plural form or native result.
	 */
	public static function plural( string $translated, string $single, string $plural, int $number, string $domain ): string {
		$catalog = 'glotframe' === $domain ? self::$host : self::$catalog;
		return self::has( $catalog, $single ) ? $catalog->translate_plural( $single, $plural, $number ) : $translated;
	}
	/** Translate contextual native GP plurals.
	 * @param string $translated Existing result.
	 * @param string $single Singular original.
	 * @param string $plural Plural original.
	 * @param int $number Actual quantity.
	 * @param string $context Message context.
	 * @param string $domain Native domain.
	 * @return string Contextual plural form or native result.
	 */
	public static function plural_context( string $translated, string $single, string $plural, int $number, string $context, string $domain ): string {
		$catalog = 'glotframe' === $domain ? self::$host : self::$catalog;
		return self::has( $catalog, $single, $context ) ? $catalog->translate_plural( $single, $plural, $number, $context ) : $translated;
	}
	/** Test an exact catalog key without changing unknown messages.
	 * @param \MO|null $catalog Loaded catalog.
	 * @param string $text Original singular message.
	 * @param string $context Optional gettext context.
	 * @return bool Whether the catalog has this message.
	 */
	private static function has( $catalog, string $text, string $context = '' ): bool {
		return $catalog && isset( $catalog->entries[ ( '' !== $context ? $context . chr( 4 ) : '' ) . $text ] );
	}
	/** Select the bundled native script catalog even if the corresponding WordPress core pack is absent.
	 * @param string|false $file Default translation file.
	 * @param string $handle Registered script handle.
	 * @param string $domain Script text domain.
	 * @return string|false Local catalog file or the original candidate.
	 */
	public static function script_file( $file, string $handle, string $domain ) {
		if ( 'glotpress' !== $domain || ! self::$locale || ! in_array( $handle, array( 'gp-common', 'gp-editor', 'gp-glossary', 'gp-mass-create-sets-page' ), true ) ) {
			return $file;
		}
		$path = dirname( GLOTFRAME_FILE ) . '/languages/gp/glotpress-' . self::$locale . '-' . $handle . '.json';
		return is_file( $path ) ? $path : $file;
	}
	/** Keep the HTML language correct when a GP-specific language is selected.
	 * @param string $attributes WordPress language attributes.
	 * @return string GP-scoped German language tag or original attributes.
	 */
	public static function html_language( string $attributes ): string {
		return self::$locale ? preg_replace( '/lang="[^"]*"/', 'lang="de-DE"', $attributes ) : $attributes;
	}
}
