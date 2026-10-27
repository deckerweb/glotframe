<?php
/** Native, local-only Site Health diagnostics. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Describes the current website without exposing content, paths or credentials. */
final class Site_Health {
	/** Register native diagnostic filters; no new endpoint or background task. @return void */
	public static function register_hooks(): void {
		add_filter( 'debug_information', array( self::class, 'information' ) );
		add_filter( 'site_status_tests', array( self::class, 'tests' ) );
	}

	/** Describe region availability without including layout IDs, titles or content.
	 * @return array<string,string> Localized region labels and availability states.
	 */
	public static function regions(): array {
		$stored = get_option( 'glotframe_settings', array() );
		$result = array();
		foreach ( Layouts::regions() as $key => $label ) {
			$id               = is_array( $stored ) && isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? absint( $stored[ $key ] ) : 0;
			$result[ $label ] = ! $id ? __( 'Not assigned', 'glotframe' ) : ( Layouts::is_available( get_post( $id ) ) ? __( 'Available', 'glotframe' ) : __( 'Unavailable', 'glotframe' ) );
		}
		return $result;
	}

	/** Read component versions from local metadata and the already elected runtime.
	 * @return array<string,string> Bundled/active Library and host updater versions only.
	 */
	public static function components(): array {
		$file     = __DIR__ . '/deckerweb-plugin-library/compatibility.json';
		$manifest = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
		$runtime  = $GLOBALS['deckerweb_library_runtime_v1'] ?? null;
		$active   = is_object( $runtime ) && defined( get_class( $runtime ) . '::VERSION' ) ? constant( get_class( $runtime ) . '::VERSION' ) : '';
		$updater  = Components::status();
		return array(
			'library_bundled' => is_array( $manifest ) && is_string( $manifest['version'] ?? null ) ? $manifest['version'] : __( 'Unavailable', 'glotframe' ),
			'library_active'  => is_string( $active ) && $active ? $active : __( 'Unavailable', 'glotframe' ),
			'updater'         => $updater['available'] ? $updater['version'] : __( 'Unavailable', 'glotframe' ),
		);
	}

	/** Add a copyable plain-text diagnostic section for authorized administrators.
	 * @param array $info Existing Site Health information.
	 * @return array Information with a website-scoped GlotFrame section.
	 */
	public static function information( array $info ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $info;
		}
		$s                 = Settings::get();
		$c                 = self::components();
		$fields            = array(
			'version'           => array(
				'label' => __( 'Plugin version', 'glotframe' ),
				'value' => GLOTFRAME_VERSION,
			),
			'runtime'           => array(
				'label' => __( 'GlotPress integration', 'glotframe' ),
				'value' => Shell::available() ? __( 'Available', 'glotframe' ) : __( 'Paused', 'glotframe' ),
			),
			'gp_version'        => array(
				'label' => __( 'Active GlotPress version', 'glotframe' ),
				'value' => defined( 'GP_VERSION' ) ? (string) GP_VERSION : __( 'Unavailable', 'glotframe' ),
			),
			'gp_supported'      => array(
				'label' => __( 'Supported GlotPress series', 'glotframe' ),
				'value' => '4.x',
			),
			'layout_enabled'    => array(
				'label' => __( 'Workspace styling enabled', 'glotframe' ),
				'value' => $s['enabled'] ? __( 'Yes', 'glotframe' ) : __( 'No', 'glotframe' ),
			),
			'layout'            => array(
				'label' => __( 'Layout', 'glotframe' ),
				'value' => 'boxed' === $s['layout'] ? __( 'Boxed', 'glotframe' ) : __( 'Full width', 'glotframe' ),
			),
			'layout_regions'    => array(
				'label' => __( 'Layout assignments', 'glotframe' ),
				'value' => self::regions(),
			),
			'layouts_published' => array(
				'label' => __( 'Published layouts', 'glotframe' ),
				'value' => (int) wp_count_posts( Layouts::POST_TYPE )->publish,
			),
			'homepage'          => array(
				'label' => __( 'Portal homepage configured', 'glotframe' ),
				'value' => $s['homepage'] ? __( 'Yes', 'glotframe' ) : __( 'No', 'glotframe' ),
			),
			'toolbar'           => array(
				'label' => __( 'GlotPress toolbar enabled', 'glotframe' ),
				'value' => $s['toolbar'] ? __( 'Yes', 'glotframe' ) : __( 'No', 'glotframe' ),
			),
			'library_bundled'   => array(
				'label' => __( 'Bundled Library version', 'glotframe' ),
				'value' => $c['library_bundled'],
			),
			'library_active'    => array(
				'label' => __( 'Active shared Library version', 'glotframe' ),
				'value' => $c['library_active'],
			),
			'updater'           => array(
				'label' => __( 'Host updater version', 'glotframe' ),
				'value' => $c['updater'],
			),
		);
		$info['glotframe'] = array(
			'label'       => 'GlotFrame',
			'description' => __( 'Local diagnostics for this website. Layout content, project names, file paths and credentials are not included.', 'glotframe' ),
			'fields'      => $fields,
		);
		return $info;
	}

	/** Register one inexpensive native status check for the current website.
	 * @param array $tests Existing direct/asynchronous tests.
	 * @return array Tests with the GlotFrame readiness check when authorized.
	 */
	public static function tests( array $tests ): array {
		if ( current_user_can( 'manage_options' ) ) {
			$tests['direct']['glotframe_environment'] = array(
				'label' => __( 'GlotFrame availability', 'glotframe' ),
				'test'  => array( self::class, 'check' ),
			);
		}
		return $tests;
	}

	/** Report a safe pause or unusable assignment as recommended, never a critical failure.
	 * @return array Native Site Health result with escaped description and a local action link.
	 */
	public static function check(): array {
		$result = array(
			'label'       => __( 'GlotFrame is ready for this website', 'glotframe' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'GlotFrame',
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => 'glotframe_environment',
		);
		if ( ! current_user_can( 'manage_options' ) ) {
			return $result;
		}
		$notes = array();
		if ( ! Shell::available() ) {
			$notes[] = Admin::dependency_message();
		}
		if ( in_array( __( 'Unavailable', 'glotframe' ), self::regions(), true ) ) {
			$notes[] = __( 'Some assigned layouts are unavailable and are not displayed. Review their publication and assignment.', 'glotframe' );
		}
		if ( __( 'Unavailable', 'glotframe' ) === self::components()['library_active'] ) {
			$notes[] = __( 'The shared Library runtime is unavailable. Reinstall a complete GlotFrame package.', 'glotframe' );
		}
		if ( ! Components::status()['available'] ) {
			$notes[] = __( 'The host updater is unavailable. Reinstall a complete GlotFrame package.', 'glotframe' );
		}
		if ( $notes ) {
			$result['status'] = 'recommended';
			$result['label']  = __( 'Review the GlotFrame configuration', 'glotframe' );
		} else {
			$notes[] = __( 'The supported GlotPress runtime and host updater are available. Assigned layouts can be displayed.', 'glotframe' );
		}
		foreach ( $notes as $note ) {
			$result['description'] .= '<p>' . esc_html( $note ) . '</p>';
		}
		$result['actions'] = '<p><a href="' . esc_url( admin_url( 'admin.php?page=glotframe' ) ) . '">' . esc_html__( 'Open GlotFrame settings', 'glotframe' ) . '</a></p>';
		return $result;
	}
}
