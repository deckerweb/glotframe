<?php
/**
 * GlotPress HTML shell and scoped appearance.
 *
 * @package GlotFrame
 */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Owns the standalone layout provider and only replaces GlotPress's outer HTML templates. */
final class Shell {
	/** @var bool Whether the full translations view requested a wide workspace. */
	private static bool $wide = false;
	/** @var string Native outer view name for narrowly scoped layout enhancements. */
	private static string $view = '';
	/** @var array Native root-project URLs supplied by the projects view. */
	private static array $roots = array();
	/** @var \GP_Project|null Project context supplied by the native GlotPress view. */
	private static $project = null;
	/** @var bool Whether the native project form needs search enhancement. */
	private static bool $project_form = false;
	/** @var array<string,string> Pre-rendered block regions, collected before the head. */
	private static array $regions = array();
	/**
	* Register template selection hooks; no frontend assets outside the shell.
	*
	* @return void
	*/
	public static function register_hooks(): void {
		add_filter( 'gp_tmpl_load_locations', array( self::class, 'locations' ), 20, 4 );
	}
	/**
	* Check the supported runtime without requiring GlotPress files.
	*
	* @return bool Availability.
	*/
	public static function available(): bool {
		return defined( 'GP_VERSION' ) && version_compare( GP_VERSION, '4.0', '>=' ) && version_compare( GP_VERSION, '5.0', '<' ) && function_exists( 'gp_tmpl_header' ) && function_exists( 'gp_head' );
	}
	/**
	 * Select the shell only for standard HTML header/footer requests.
	 * @param array $locations Existing template paths.
	 * @param string $template GlotPress template name.
	 * @param array $args Template data.
	 * @param string|null $explicit Explicit priority directory, respected when supplied.
	 * @return array Original paths or prepended shell provider path.
	 */
	public static function locations( $locations, $template, $args, $explicit ): array {
		if ( ! self::available() || ! Settings::get()['enabled'] || null !== $explicit ) {
			return $locations;
		}
		if ( ! in_array( $template, array( 'header', 'footer' ), true ) ) {
			self::$view = sanitize_html_class( $template ); }
		if ( 'projects' === $template && isset( $args['projects'] ) && is_array( $args['projects'] ) ) {
			foreach ( $args['projects'] as $root ) {
				if ( $root instanceof \GP_Project ) {
					self::$roots[] = gp_url_project( $root ); }
			}
		}
		if ( 'projects' === $template && Statistics::requested() && is_user_logged_in() && Settings::get()['statistics'] ) {
			self::$view = 'statistics';
			array_unshift( $locations, dirname( GLOTFRAME_FILE ) . '/templates/statistics/' ); }
		if ( isset( $args['project'] ) && $args['project'] instanceof \GP_Project ) {
			self::$project = $args['project'];
		}
		if ( in_array( $template, array( 'project-new', 'project-edit' ), true ) ) {
			self::$project_form = true;
		}
		if ( 'translations' === $template ) {
			self::$wide = true;
		}
		if ( in_array( $template, array( 'header', 'footer' ), true ) ) {
			array_unshift( $locations, dirname( GLOTFRAME_FILE ) . '/templates/' );
		}
		return $locations;
	}
	/**
	 * Render blocks before the head so their scripts/styles can be printed correctly.
	 * @return void Stores request-local region HTML and enqueues shell assets.
	 */
	public static function prepare(): void {
		$settings = Settings::get();
		Localization::scripts();
		if ( Statistics::requested() && is_user_logged_in() && $settings['statistics'] ) {
			Statistics::controls(); }
		if ( self::$wide ) {
			add_filter( 'gettext_glotpress', array( self::class, 'priority_label' ), 20, 3 );
		}
		foreach ( array( 'header', 'before', 'after', 'footer' ) as $region ) {
			self::$regions[ $region ] = Layouts::render( $settings[ $region ] );
		}
		gp_enqueue_styles( array( 'gp-base' ) );
		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_style( 'glotframe', plugins_url( 'assets/frontend.css', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION );
		wp_add_inline_style( 'glotframe', self::css( $settings ) );
		wp_enqueue_style( 'glotframe-data-blocks' );
		wp_enqueue_script( 'glotframe-projects', plugins_url( 'assets/projects.js', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION, true );
		wp_localize_script(
			'glotframe-projects',
			'glotframeProjects',
			array(
				'roots'     => self::$roots,
				'rootLabel' => __( 'Root projects', 'glotframe' ),
				'labels'    => array(
					'changesrequested' => __( 'Changes requested', 'glotframe' ),
					'with warnings'    => __( 'With warnings', 'glotframe' ),
					'hidden'           => __( 'Hidden', 'glotframe' ),
					'low'              => __( 'Low', 'glotframe' ),
					'normal'           => __( 'Normal', 'glotframe' ),
					'high'             => __( 'High', 'glotframe' ),
				),
				'view'      => $settings['project_view'],
				'site'      => (string) get_current_blog_id(),
				'search'    => __( 'Find a project…', 'glotframe' ),
				'list'      => __( 'List', 'glotframe' ),
				'grid'      => __( 'Grid', 'glotframe' ),
				'empty'     => __( 'No matching projects.', 'glotframe' ),
				'viewLabel' => __( 'Project view', 'glotframe' ),
			)
		);
		wp_enqueue_script( 'glotframe', plugins_url( 'assets/frontend.js', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION, true );
		if ( self::$project_form ) {
			wp_enqueue_style( 'glotframe-tom-select', plugins_url( 'assets/vendor/tom-select/tom-select.css', GLOTFRAME_FILE ), array(), '2.6.2' );
			wp_enqueue_style( 'glotframe-project-select', plugins_url( 'assets/project-select.css', GLOTFRAME_FILE ), array( 'glotframe-tom-select', 'glotframe' ), GLOTFRAME_VERSION );
			wp_enqueue_script( 'glotframe-tom-select', plugins_url( 'assets/vendor/tom-select/tom-select.complete.min.js', GLOTFRAME_FILE ), array(), '2.6.2', true );
			wp_enqueue_script( 'glotframe-project-select', plugins_url( 'assets/project-select.js', GLOTFRAME_FILE ), array( 'glotframe-tom-select' ), GLOTFRAME_VERSION, true );
			wp_localize_script(
				'glotframe-project-select',
				'glotframeProjectSelect',
				array(
					'search' => __( 'Search projects…', 'glotframe' ),
					'empty'  => __( 'No matching projects.', 'glotframe' ),
				)
			);
		}
		add_filter( 'pre_get_document_title', array( self::class, 'title' ), 99 );
	}
	/** Keep the translation table's compact priority heading independent of GP language packs.
	 * @param string $translation Existing translated label.
	 * @param string $text Original GlotPress message.
	 * @param string $domain Message domain supplied by WordPress.
	 * @return string Compact heading for this shell's translation view, otherwise unchanged text.
	 */
	public static function priority_label( string $translation, string $text, string $domain ): string {
		return self::$wide && 'glotpress' === $domain && in_array( $text, array( 'Prio', 'Prio.', 'Prior.' ), true ) ? 'Prio' : $translation;
	}

	/** Return the current native project without resolving or fetching URLs.
	 * @return \GP_Project|null Project supplied by GlotPress or null on global pages.
	 */
	public static function project() {
		return self::$project;
	}

	/**
	* Return GlotPress's title for the WordPress head.
	*
	* @return string Plain document title.
	*/
	public static function title(): string {
		return html_entity_decode( wp_strip_all_tags( gp_title() ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}
	/**
	 * Print both hook families while avoiding a second enqueue cycle.
	 * @return void Emits the head and preserves the original GlotPress callback afterward.
	 */
	public static function head(): void {
		if ( ! current_theme_supports( 'title-tag' ) ) {
			echo '<title>' . esc_html( self::title() ) . '</title>';
		}
		wp_head();
		$priority = has_action( 'gp_head', 'wp_enqueue_scripts' );
		if ( false !== $priority ) {
			remove_action( 'gp_head', 'wp_enqueue_scripts', $priority );
		}
		try {
			gp_head();
		} finally {
			if ( false !== $priority ) {
				add_action( 'gp_head', 'wp_enqueue_scripts', $priority );
			}
		}
	}
	/**
	 * Retrieve trusted output generated through the supported block renderer.
	 * @param string $name Region key.
	 * @return string Rendered HTML.
	 */
	public static function region( string $name ): string {
		return self::$regions[ $name ] ?? '';
	}
	/**
	* Return scoped classes reflecting the selected layout.
	*
	* @return string Body classes.
	*/
	public static function classes(): string {
		$s = Settings::get();
		return 'glotframe-view-' . self::$view . ' ' . ( self::$wide ? 'glotframe-translations ' : '' ) . ( self::$project_form ? 'glotframe-project-form-page ' : '' ) . 'glotframe no-js glotframe--' . $s['layout'] . ' glotframe--' . $s['table_density'] . ( self::$wide && $s['wide_editor'] ? ' glotframe--wide' : '' );
	}
	/**
	 * Build CSS only from normalized numbers, colors and validated attachment URLs.
	 * @param array $s Normalized settings.
	 * @return string Scoped stylesheet.
	 */
	public static function css( array $s ): string {
		$fonts  = array(
			'system' => 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
			'serif'  => 'Georgia,"Times New Roman",serif',
			'mono'   => 'ui-monospace,Menlo,Consolas,monospace',
		);
		$css    = 'body.glotframe{';
		$tokens = Presets::tokens( $s );
		foreach ( $tokens as $key => $value ) {
			$css .= '--gf-' . $key . ':' . $value . ';';
		}
		$css .= 'color-scheme:' . $tokens['scheme'] . ';';
		foreach ( array( 'width', 'work_width', 'padding', 'radius', 'font_size' ) as $key ) {
			$css .= '--gf-' . str_replace( '_', '-', $key ) . ':' . (int) $s[ $key ] . 'px;';
		}
		$css .= '--gf-font:' . $fonts[ $s['font'] ] . ';}';
		$url  = $s['background_image'] ? wp_get_attachment_image_url( $s['background_image'], 'full' ) : false;
		if ( $url ) {
			// Percent encoding also prevents quotes, backslashes or delimiters escaping CSS url().
			$url  = str_replace( array( '"', "'", '(', ')', '\\', "\n", "\r", '<', '>' ), array( '%22', '%27', '%28', '%29', '%5C', '', '', '%3C', '%3E' ), esc_url_raw( $url ) );
			$css .= 'body.glotframe{--gf-background-image:url("' . $url . '");background-image:var(--gf-background-image);}';
		}
		return $css;
	}
}
