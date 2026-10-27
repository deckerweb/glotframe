<?php
/**
 * Settings API and accessible administration.
 *
 * @package GlotFrame
 */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Provides site-level settings, content selection, status and local help. */
final class Admin {
	/**
	* Register admin hooks without adding frontend work.
	*
	* @return void
	*/
	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_filter( 'dashboard_glance_items', array( self::class, 'portal_glance' ) );
		add_action( 'admin_init', array( self::class, 'settings' ) );
		add_action( 'admin_notices', array( self::class, 'dependency_notice' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GLOTFRAME_FILE ), array( self::class, 'links' ) );
	}
	/**
	* Add one functional settings menu; layout post type uses this parent.
	*
	* @return void
	*/
	public static function menu(): void {
		add_menu_page( 'GlotFrame', 'GlotFrame', 'manage_options', 'glotframe', array( self::class, 'page' ), 'dashicons-layout', 59 );
		add_submenu_page( 'glotframe', __( 'Settings', 'glotframe' ), __( 'Settings', 'glotframe' ), 'manage_options', 'glotframe', array( self::class, 'page' ) );
	}
	/** Explain the paused state without leaving administration or changing activation.
	 * @return string Localized dependency or permalink guidance.
	 */
	public static function dependency_message(): string {
		if ( defined( 'GP_VERSION' ) && version_compare( GP_VERSION, '4.0', '>=' ) && version_compare( GP_VERSION, '5.0', '<' ) && ! get_option( 'permalink_structure' ) ) {
			return __( 'GlotPress needs pretty permalinks. Choose a permalink structure other than Plain in Settings → Permalinks, then return here.', 'glotframe' );
		}
		return __( 'GlotFrame is paused because a supported GlotPress runtime is unavailable. Install or activate GlotPress 4.x. Settings and layouts are preserved.', 'glotframe' );
	}
	/** Show dependency guidance on relevant native admin screens, once per page.
	 * @return void Settings uses its own inline notice outside the title and slogan.
	 */
	public static function dependency_notice(): void {
		$screen = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || Shell::available() || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'plugins-network', 'edit-glotframe_layout', 'glotframe_layout', 'glotframe_page_glotframe-statistics' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-warning gf-dependency-notice"><p>' . esc_html( self::dependency_message() ) . '</p></div>';
	}
	/**
	* Register the single schema and semantic field groups.
	*
	* @return void
	*/
	public static function settings(): void {
		register_setting(
			'glotframe',
			'glotframe_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'save' ),
				'default'           => Settings::defaults(),
			)
		);
		$groups = array(
			'portal'     => __( 'Translation portal', 'glotframe' ),
			'navigation' => __( 'Toolbar navigation', 'glotframe' ),
			'layout'     => __( 'Workspace layout', 'glotframe' ),
			'content'    => __( 'Header, footer and content', 'glotframe' ),
			'design'     => __( 'Appearance', 'glotframe' ),
			'data'       => __( 'Stored data', 'glotframe' ),
			'language'   => __( 'GlotPress language', 'glotframe' ),
		);
		foreach ( $groups as $key => $label ) {
			add_settings_section( 'gf_' . $key, $label, '__return_false', 'glotframe' );
		}
		foreach ( self::fields() as $key => $field ) {
			add_settings_field(
				$key,
				$field[0],
				array( self::class, 'field' ),
				'glotframe',
				'gf_' . $field[1],
				array(
					'key'       => $key,
					'label_for' => in_array( $key, array( 'preset', 'toolbar_icon', 'menu_icon' ), true ) ? '' : 'gf-' . $key,
				)
			);
		}
	}
	/**
	* Describe fields centrally for consistent labels and output.
	*
	* @return array Field definitions.
	*/
	public static function fields(): array {
		return array(
			'gp_language'         => array(
				__( 'GlotPress interface language', 'glotframe' ),
				'language',
				'select',
				array(
					'inherit'      => __( 'Use the WordPress language', 'glotframe' ),
					'de_DE'        => __( 'German (informal)', 'glotframe' ),
					'de_DE_formal' => __( 'German (formal)', 'glotframe' ),
				),
			),
			'statistics'          => array( __( 'Enable statistics', 'glotframe' ), 'portal', 'checkbox' ),
			'homepage'            => array( __( 'Use GlotPress as the homepage', 'glotframe' ), 'portal', 'checkbox' ),
			'slug'                => array( __( 'GlotPress URL path', 'glotframe' ), 'portal', 'text' ),
			'toolbar'             => array( __( 'Enable GlotPress toolbar', 'glotframe' ), 'navigation', 'checkbox' ),
			'toolbar_title'       => array( __( 'Toolbar title', 'glotframe' ), 'navigation', 'text' ),
			'toolbar_position'    => array(
				__( 'Toolbar position', 'glotframe' ),
				'navigation',
				'select',
				array(
					'default'    => __( 'Standard position', 'glotframe' ),
					'left_start' => __( 'Left: at the beginning', 'glotframe' ),
					'left_end'   => __( 'Left: at the end', 'glotframe' ),
					'right'      => __( 'Right: before the user profile', 'glotframe' ),
					'before'     => __( 'Before a toolbar entry', 'glotframe' ),
					'after'      => __( 'After a toolbar entry', 'glotframe' ),
				),
			),
			'toolbar_target'      => array( __( 'Reference toolbar entry', 'glotframe' ), 'navigation', 'toolbar_target' ),
			'toolbar_icon'        => array( __( 'Toolbar icon', 'glotframe' ), 'navigation', 'icons', Icons::choices() ),
			'menu_icon'           => array( __( 'Admin menu icon', 'glotframe' ), 'navigation', 'icons', Icons::choices() ),
			'project_view'        => array(
				__( 'Default project view', 'glotframe' ),
				'layout',
				'select',
				array(
					'list' => __( 'List', 'glotframe' ),
					'grid' => __( 'Grid', 'glotframe' ),
				),
			),
			'toolbar_projects'    => array( __( 'Show all projects', 'glotframe' ), 'navigation', 'checkbox' ),
			'toolbar_subprojects' => array( __( 'Include subprojects', 'glotframe' ), 'navigation', 'checkbox' ),
			'toolbar_depth'       => array( __( 'Subproject depth', 'glotframe' ), 'navigation', 'number', 1, 6 ),
			'preset'              => array( __( 'Color preset', 'glotframe' ), 'design', 'presets' ),
			'enabled'             => array( __( 'Enable GlotFrame', 'glotframe' ), 'layout', 'checkbox' ),
			'layout'              => array(
				__( 'Layout', 'glotframe' ),
				'layout',
				'select',
				array(
					'boxed' => __( 'Boxed', 'glotframe' ),
					'full'  => __( 'Full width', 'glotframe' ),
				),
			),
			'width'               => array( __( 'Content width (px)', 'glotframe' ), 'layout', 'number', 720, 2400 ),
			'wide_editor'         => array( __( 'Wider translation editor', 'glotframe' ), 'layout', 'checkbox' ),
			'work_width'          => array( __( 'Translation editor width (px)', 'glotframe' ), 'layout', 'number', 960, 3200 ),
			'padding'             => array( __( 'Inner spacing (px)', 'glotframe' ), 'layout', 'number', 0, 96 ),
			'radius'              => array( __( 'Corner radius (px)', 'glotframe' ), 'layout', 'number', 0, 40 ),
			'header'              => array( __( 'Header', 'glotframe' ), 'content', 'layout' ),
			'before'              => array( __( 'Above the workspace', 'glotframe' ), 'content', 'layout' ),
			'after'               => array( __( 'Below the workspace', 'glotframe' ), 'content', 'layout' ),
			'footer'              => array( __( 'Footer', 'glotframe' ), 'content', 'layout' ),
			'background'          => array( __( 'Outer background', 'glotframe' ), 'design', 'color' ),
			'background_image'    => array( __( 'Background image', 'glotframe' ), 'design', 'image' ),
			'surface'             => array( __( 'Content background', 'glotframe' ), 'design', 'color' ),
			'text'                => array( __( 'Text color', 'glotframe' ), 'design', 'color' ),
			'accent'              => array( __( 'Accent color', 'glotframe' ), 'design', 'color' ),
			'border'              => array( __( 'Border color', 'glotframe' ), 'design', 'color' ),
			'font'                => array(
				__( 'Font family', 'glotframe' ),
				'design',
				'select',
				array(
					'system' => __( 'System sans serif', 'glotframe' ),
					'serif'  => __( 'Serif', 'glotframe' ),
					'mono'   => __( 'Monospace', 'glotframe' ),
				),
			),
			'font_size'           => array( __( 'Font size (px)', 'glotframe' ), 'design', 'number', 12, 24 ),
			'table_density'       => array(
				__( 'Table spacing', 'glotframe' ),
				'design',
				'select',
				array(
					'comfortable' => __( 'Comfortable', 'glotframe' ),
					'compact'     => __( 'Compact', 'glotframe' ),
				),
			),
			'delete_data'         => array( __( 'Delete GlotFrame data on uninstall', 'glotframe' ), 'data', 'checkbox' ),
		);
	}
	/**
	 * Render one escaped Settings API control.
	 * @param array $args Field key and associated label ID.
	 * @return void Emits a control.
	 */
	public static function field( array $args ): void {
		$key         = $args['key'];
		$field       = self::fields()[ $key ];
		$value       = Settings::get()[ $key ];
		$unavailable = false;
		$name        = 'glotframe_settings[' . $key . ']';
		$id          = 'gf-' . $key;
		$help        = self::descriptions();
		switch ( $field[2] ) {
			case 'presets':
				echo '<fieldset class="gf-presets"><legend class="screen-reader-text">' . esc_html__( 'Color preset', 'glotframe' ) . '</legend><input type="hidden" name="glotframe_settings[palette_ready]" id="gf-palette-ready" value="0">';
				foreach ( Presets::all() as $preset => $definition ) {
					echo '<label class="gf-preset gf-preset-' . esc_attr( $preset ) . '"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $preset ) . '" ' . checked( $value, $preset, false ) . '><span class="gf-preset-art" aria-hidden="true"><span></span><span></span><span></span></span><strong>' . esc_html( $definition['label'] ) . '</strong></label>';
				}
				echo '</fieldset>';
				break;
			case 'checkbox':
				echo '<div class="gf-checkbox-row"><input type="checkbox" id="' . esc_attr( $id ) . '" aria-label="' . esc_attr( $field[0] ) . '" aria-describedby="' . esc_attr( $id . '-description' ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( $value, 1, false ) . '><label class="description" id="' . esc_attr( $id . '-description' ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $help[ $key ] ?? $field[0] ) . '</label></div>';
				break;
			case 'toolbar_target':
				$choices = Navigation::targets();
				if ( $value && ! isset( $choices[ $value ] ) ) {
					/* translators: %s: saved toolbar entry ID absent from this screen. */
					$choices[ $value ] = sprintf( __( 'Unavailable on this page (%s)', 'glotframe' ), $value );
				}
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Choose a toolbar entry', 'glotframe' ) . '</option>';
				foreach ( $choices as $target => $label ) {
					echo '<option value="' . esc_attr( $target ) . '" ' . selected( $value, $target, false ) . '>' . esc_html( $label ) . '</option>'; }
				echo '</select>';
				break;
			case 'icons':
				echo '<fieldset class="gf-icon-options"><legend class="screen-reader-text">' . esc_html( $field[0] ) . '</legend>';
				foreach ( $field[3] as $icon => $label ) {
					echo '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $icon ) . '" ' . checked( $value, $icon, false ) . '><span aria-hidden="true" class="dashicons dashicons-layout gf-icon-preview ' . ( 'dashicon' !== $icon ? 'gf-interface-icon gf-icon-' . esc_attr( $icon ) : '' ) . '"></span><span>' . esc_html( $label ) . '</span></label>';
				}
				echo '</fieldset>';
				break;
			case 'select':
			case 'layout':
				$options = $field[3] ?? array( 0 => __( 'Default / no extra content', 'glotframe' ) );
				if ( 'layout' === $field[2] ) {
					$posts    = get_posts(
						array(
							'post_type'      => Layouts::POST_TYPE,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => 'title',
							'order'          => 'ASC',
							'has_password'   => false,
						)
					);
					$stored   = get_option( 'glotframe_settings', array() );
					$assigned = is_array( $stored ) && isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? absint( $stored[ $key ] ) : 0;
					if ( $assigned && ! $value ) {
						$value       = $assigned;
						$unavailable = true;
						/* translators: %d: stored layout ID. */
						$options[ $value ] = sprintf( __( 'Unavailable layout (ID %d)', 'glotframe' ), $value );
					}
					foreach ( $posts as $post ) {
						$options[ $post->ID ] = $post->post_title ? $post->post_title : __( 'Untitled layout', 'glotframe' );
					}
				}
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( $unavailable ? ' aria-describedby="' . esc_attr( $id . '-unavailable' ) . '"' : '' ) . '>';
				foreach ( $options as $option => $label ) {
					echo '<option value="' . esc_attr( (string) $option ) . '" ' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				if ( 'layout' === $field[2] ) {
					$edit = Layout_Admin::edit_link( (int) $value );
					echo ' <a class="gf-layout-edit" data-gf-layout="' . esc_attr( $id ) . '"' . ( $edit ? ' href="' . esc_url( $edit ) . '"' : ' hidden' ) . '>' . esc_html__( 'Edit layout', 'glotframe' ) . '</a>';
					if ( $unavailable ) {
						echo '<p class="description gf-layout-unavailable" id="' . esc_attr( $id . '-unavailable' ) . '" data-gf-layout="' . esc_attr( $id ) . '" data-layout-id="' . esc_attr( (string) $value ) . '">' . esc_html__( 'This layout is unavailable and is not displayed. Select a published layout or save to clear this assignment.', 'glotframe' ) . '</p>';
					}
				}
				break;
			case 'image':
				echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
				echo '<button type="button" class="button" id="gf-image-choose">' . esc_html__( 'Choose image', 'glotframe' ) . '</button> <button type="button" class="button" id="gf-image-remove">' . esc_html__( 'Remove image', 'glotframe' ) . '</button>';
				echo '<div id="gf-image-preview">';
				if ( $value ) {
					echo wp_get_attachment_image( $value, 'thumbnail' );
				}
				echo '</div>';
				break;
			default:
				echo '<input id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( 'color' === $field[2] ? 'text' : $field[2] ) . '" class="' . esc_attr( 'color' === $field[2] ? 'gf-color' : '' ) . '" value="' . esc_attr( (string) $value ) . '"';
				if ( 'number' === $field[2] ) {
					echo ' min="' . esc_attr( (string) $field[3] ) . '" max="' . esc_attr( (string) $field[4] ) . '" step="1"';
				}
				echo '>';
		}
		if ( 'checkbox' !== $field[2] && isset( $help[ $key ] ) ) {
			echo '<p class="description">' . esc_html( $help[ $key ] ) . '</p>';
		}
	}
	/** Describe controls once so checkbox explanations can serve as clickable labels.
	 * @return array<string,string> Localized descriptions by field key.
	 */
	private static function descriptions(): array {
		return array(
			'homepage'            => __( 'Temporarily redirects the WordPress homepage to GlotPress. Existing Reading settings are preserved and resume when this option is off or GlotFrame is inactive.', 'glotframe' ),
			'slug'                => __( 'One path segment, for example translate. Leave empty to keep the current GlotPress default. Existing links to an old custom path are not redirected automatically.', 'glotframe' ),
			'toolbar'             => __( 'Available in the WordPress admin and on the website for logged-in users with a visible toolbar.', 'glotframe' ),
			'toolbar_depth'       => __( 'Number of subproject levels below a top-level project, from 1 to 6. Applies when subprojects are enabled.', 'glotframe' ),
			'preset'              => __( 'Selecting a preset fills the color controls. You can adjust them before saving; content, widths and background images stay unchanged.', 'glotframe' ),
			'enabled'             => __( 'Applies to GlotPress HTML pages on this website. Your regular website pages keep their existing layout.', 'glotframe' ),
			'wide_editor'         => __( 'Uses the wider setting for translation tables in boxed layouts.', 'glotframe' ),
			'header'              => __( 'Publish layouts in the block editor, then select them here. The workspace navigation remains available.', 'glotframe' ),
			'background'          => __( 'The outer background is visible around boxed layouts.', 'glotframe' ),
			'accent'              => __( 'Choose colors with sufficient contrast for links, controls and keyboard focus.', 'glotframe' ),
			'delete_data'         => __( 'Off by default. Removes this website’s GlotFrame settings, layouts and their revisions when the plugin is deleted. Network deletion applies each website’s choice. Shared Library settings follow the Library’s separate cleanup policy.', 'glotframe' ),
			'statistics'          => __( 'Show statistics for administrators and signed-in translators.', 'glotframe' ),
			'toolbar_projects'    => __( 'Show the project tree in the GlotPress toolbar.', 'glotframe' ),
			'toolbar_subprojects' => __( 'Also include child projects to the configured depth.', 'glotframe' ),
			'toolbar_position'    => __( 'Applies to this website’s toolbar in the admin and frontend. The standard position remains the fallback.', 'glotframe' ),
			'toolbar_target'      => __( 'Choose an entry currently present in your toolbar. If it is missing on another page, GlotPress keeps its standard position.', 'glotframe' ),
		);
	}

	/**
	 * Load local admin assets on GlotFrame settings only.
	 * @param string $hook Current admin screen identifier.
	 * @return void Enqueues controls and accessible dialog controller.
	 */
	public static function assets( string $hook ): void {
		if ( 'toplevel_page_glotframe' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'glotframe-admin', plugins_url( 'assets/admin.css', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION );
		wp_enqueue_script( 'glotframe-admin', plugins_url( 'assets/admin.js', GLOTFRAME_FILE ), array( 'media-views', 'wp-color-picker' ), GLOTFRAME_VERSION, true );
		foreach ( Presets::all() as $key => $preset ) {
			$css = '.gf-preset-' . $key . '{';
			foreach ( $preset['colors'] as $token => $color ) {
				$css .= '--preview-' . $token . ':' . $color . ';';
			}wp_add_inline_style( 'glotframe-admin', $css . '}' );
		}
		wp_localize_script(
			'glotframe-admin',
			'glotframeAdmin',
			array(
				'title'       => __( 'Choose background image', 'glotframe' ),
				'choose'      => __( 'Use image', 'glotframe' ),
				'presets'     => Presets::all(),
				'layoutLinks' => Layout_Admin::edit_links(),
				'dirty'       => __( 'Unsaved changes', 'glotframe' ),
				'clean'       => __( 'No unsaved changes', 'glotframe' ),
				'saving'      => __( 'Saving…', 'glotframe' ),
				'review'      => __( 'Please review the settings errors.', 'glotframe' ),
				'errors'      => (bool) array_filter(
					get_settings_errors( 'glotframe_settings' ),
					static function ( $error ) {
						return 'error' === $error['type']; }
				),
			)
		);
	}
	/**
	 * Add the primary settings link before existing plugin actions.
	 * @param array $links Existing links.
	 * @return array Links for authorized website administrators.
	 */
	public static function links( array $links ): array {
		if ( current_user_can( 'manage_options' ) && ! is_network_admin() ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=glotframe' ) ) . '">' . esc_html__( 'Settings', 'glotframe' ) . '</a>' );
		}
		return $links;
	}
	/**
	* Render the functional settings page and local documentation.
	*
	* @return void
	*/
	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap glotframe-admin">';
		Admin_Chrome::header();
		echo '<hr class="wp-header-end">';
		settings_errors();
		if ( Shell::available() && Settings::get()['homepage'] ) {
			echo '<section class="gf-portal-status" aria-label="' . esc_attr__( 'Translation portal', 'glotframe' ) . '"><strong>' . esc_html__( 'Translation portal active', 'glotframe' ) . '</strong><p>' . esc_html__( 'The homepage opens GlotPress. Your WordPress administration and other pages remain accessible.', 'glotframe' ) . ' <a href="' . esc_url( gp_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open GlotPress ↗', 'glotframe' ) . '</a></p></section>';
		}
		$ready = Shell::available();
		if ( ! $ready ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( self::dependency_message() ) . '</p></div>';
		}
		echo '<nav class="gf-settings-nav" aria-labelledby="gf-settings-nav-title"><p id="gf-settings-nav-title">' . esc_html__( 'Jump to settings', 'glotframe' ) . '</p><ul>';
		foreach ( array(
			'frontend' => __( 'Frontend & layout', 'glotframe' ),
			'admin'    => __( 'Admin & toolbar', 'glotframe' ),
			'system'   => __( 'Data & system', 'glotframe' ),
		) as $target => $label ) {
			echo '<li><a href="#gf-' . esc_attr( $target ) . '"><span aria-hidden="true">↓</span>' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul></nav><form id="gf-settings-form" action="options.php" method="post"><fieldset>';
		settings_fields( 'glotframe' );
		self::sections();
		echo '<div class="gf-savebar"><div class="gf-save-actions"><span class="gf-save-status" role="status" aria-live="polite"></span><button type="submit" id="submit" class="button button-primary"><span class="gf-save-dot" aria-hidden="true" hidden>●</span><span>' . esc_html__( 'Save changes', 'glotframe' ) . '</span></button></div></div>';
		echo '</fieldset></form>';
		echo '<details id="gf-help"><summary>' . esc_html__( 'Documentation', 'glotframe' ) . '</summary>';
		self::help();
		echo '</details>';
		Admin_Chrome::footer();
		echo '</div>';
	}
	/** Render registered Settings API fields in coherent, accessible sections.
	 * @return void Keeps original field callbacks, names and nonce processing.
	 */
	private static function sections(): void {
		global $wp_settings_sections;
		$groups = array(
			'frontend' => array( __( 'Frontend & layout', 'glotframe' ), array( 'portal', 'layout', 'content', 'design', 'language' ) ),
			'admin'    => array( __( 'Admin & toolbar', 'glotframe' ), array( 'navigation' ) ),
			'system'   => array( __( 'Data & system', 'glotframe' ), array( 'data' ) ),
		);
		foreach ( $groups as $key => $group ) {
			echo '<section id="gf-' . esc_attr( $key ) . '" class="gf-settings-group" aria-labelledby="gf-heading-' . esc_attr( $key ) . '"><h2 id="gf-heading-' . esc_attr( $key ) . '">' . esc_html( $group[0] ) . '</h2>';
			foreach ( $group[1] as $section ) {
				$id         = 'gf_' . $section;
				$definition = $wp_settings_sections['glotframe'][ $id ] ?? null;
				if ( ! $definition ) {
							continue;
				}echo '<section class="gf-settings-card gf-settings-card-' . esc_attr( $section ) . '"><h3>' . esc_html( $definition['title'] ) . '</h3>';
				if ( 'content' === $section ) {
					echo '<div class="gf-layout-actions" role="group" aria-labelledby="gf-layout-actions-title"><strong id="gf-layout-actions-title">' . esc_html__( 'Block layouts', 'glotframe' ) . '</strong><div><a class="button" href="' . esc_url( admin_url( 'post-new.php?post_type=' . Layouts::POST_TYPE ) ) . '">' . esc_html__( 'Create block layout', 'glotframe' ) . '</a><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . Layouts::POST_TYPE ) ) . '">' . esc_html__( 'Manage layouts', 'glotframe' ) . '</a></div></div>';
				}
				if ( 'portal' === $section && Shell::available() && ! Settings::get()['homepage'] ) {
					echo '<p class="gf-portal-preview"><a class="button" href="' . esc_url( gp_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View GlotPress', 'glotframe' ) . ' <span aria-hidden="true">↗</span></a></p>';
				}
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( 'glotframe', $id );
				echo '</table></section>';}
			if ( 'system' === $key ) {
				echo '<p>' . esc_html__( 'Settings and layouts belong to this website. GlotPress remains responsible for translations and permissions.', 'glotframe' ) . '</p>';
			}
			echo '</section>';
		}
	}

	/**
	* Print concise usage and FAQ using the current administrative locale.
	*
	* @return void
	*/
	private static function help(): void {
		echo '<h2>' . esc_html__( 'Getting started', 'glotframe' ) . '</h2><ol>';
		foreach ( array( __( 'Create a layout, add blocks and publish it. You can create separate layouts for the header, footer and notices.', 'glotframe' ), __( 'Select your published layouts, choose the workspace width and colors, then enable GlotFrame and save.', 'glotframe' ), __( 'Open GlotPress to check the project list and translation editor, including the mobile layout.', 'glotframe' ) ) as $step ) {
			echo '<li>' . esc_html( $step ) . '</li>';
		}
		echo '</ol><h2>' . esc_html__( 'Questions by topic', 'glotframe' ) . '</h2>';
		$faq = array(
			__( 'Where can I find layout patterns and GlotPress data blocks?', 'glotframe' ) => __( 'Edit a GlotFrame layout and open the block inserter. Patterns appear under GlotFrame layouts; the five data blocks appear under GlotFrame. For context-dependent previews, choose a project in the block settings. Existing layouts are never replaced automatically.', 'glotframe' ),
			__( 'Do I need a specific theme or page builder?', 'glotframe' ) => __( 'No. GlotFrame supplies its own surrounding layout and uses WordPress presentation blocks. It does not import your active theme’s header or builder templates.', 'glotframe' ),
			__( 'Why is my layout missing from the selector?', 'glotframe' ) => __( 'Only published GlotFrame layouts without password protection can be selected. Drafts and private layouts are never shown to visitors.', 'glotframe' ),
			__( 'Which blocks can I use?', 'glotframe' )  => __( 'Groups, columns, text, headings, images, lists, buttons, spacing, site identity, navigation, social links and details, plus GlotPress project, action and progress blocks. Query, post-content, HTML, shortcode and reusable-content blocks are excluded.', 'glotframe' ),
			__( 'Does it change translation data or exports?', 'glotframe' ) => __( 'No. GlotFrame changes the surrounding HTML layout. Translation permissions, forms, downloads and API responses remain GlotPress’s responsibility.', 'glotframe' ),
			__( 'How does Multisite work?', 'glotframe' ) => __( 'Network activation makes GlotFrame available to every website. Each website keeps its own settings and layouts; there are no network-wide design overrides.', 'glotframe' ),
			__( 'What happens when I deactivate or delete it?', 'glotframe' ) => __( 'Deactivation preserves everything. Deletion preserves settings and layouts by default. Enable the deletion option only if you want to remove this website’s GlotFrame content too. Other plugins and translation data are retained.', 'glotframe' ),
		);
		foreach ( $faq as $question => $answer ) {
			echo '<h3>' . esc_html( $question ) . '</h3><p>' . esc_html( $answer ) . '</p>';
		}
	}
	/**
	* Print escaped, categorized release history, shared by help and modal.
	*
	* @return void
	*/
	public static function changelog(): void {
		foreach ( self::history() as $version => $entry ) {
			echo '<h3>' . esc_html( $version ) . ' · ' . esc_html( $entry['date'] ) . '</h3><ul>';
			foreach ( $entry['changes'] as $category => $message ) {
				$kind = array_search(
					$category,
					array(
						'new'      => __( 'New:', 'glotframe' ),
						'improved' => __( 'Improved:', 'glotframe' ),
						'fixed'    => __( 'Fixed:', 'glotframe' ),
						'misc'     => __( 'Misc:', 'glotframe' ),
					),
					true
				);
				echo '<li><span class="gf-badge gf-badge-' . esc_attr( $kind ? $kind : 'misc' ) . '">' . esc_html( $category ) . '</span> ' . esc_html( $message ) . '</li>';
			}
			echo '</ul>';
		}
	}
	/** Return localized, categorized history shared by local help and documentation export.
	 * @return array Versioned release entries with dates and changes.
	 */
	public static function history(): array {
		return array(
			'1.0.0' => array(
				'date'    => '2026-10-27',
				'changes' => array(
					__( 'New:', 'glotframe' )      => __( 'Initial release: block headers, footers and notice areas; seven layout patterns and five dynamic GlotPress blocks; boxed and full-width layouts with eight color presets, custom colors and backgrounds.', 'glotframe' ),
					__( 'Improved:', 'glotframe' ) => __( 'Searchable project lists and grids, searchable parent-project selection, clearer translation and glossary tables, configurable toolbar navigation and placement, and optional portal homepage and URL path.', 'glotframe' ),
					__( 'Misc:', 'glotframe' )     => __( 'Includes German informal and formal interface translations, current operator and personal statistics, layout management shortcuts, Site Health information and contextual WordPress Command Palette actions. Requires WordPress 6.4, PHP 8.3 and active GlotPress 4.x for workspace features; settings and layouts remain available when GlotPress is absent.', 'glotframe' ),
				),
			),
		);
	}
	/** Add a site-specific portal indicator to the existing dashboard summary.
	 * @param array $items Existing dashboard links.
	 * @return array Existing links with the active portal entry for administrators.
	 */
	public static function portal_glance( array $items ): array {
		if ( Shell::available() && Settings::get()['homepage'] && current_user_can( 'manage_options' ) ) {
			$items[] = '<a href="' . esc_url( admin_url( 'admin.php?page=glotframe' ) ) . '">' . esc_html__( 'GlotPress translation portal active', 'glotframe' ) . '</a>';
		}
		return $items;
	}
}
