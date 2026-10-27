<?php
/**
 * Validated, site-scoped configuration.
 *
 * @package GlotFrame
 */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Owns defaults and the version-one settings schema; does not change foreign data. */
final class Settings {
	/**
	* Return complete safe defaults.
	*
	* @return array<string,mixed> Site defaults.
	*/
	public static function defaults(): array {
		return array(
			'schema'              => 5,
			'preset'              => 'white',
			'toolbar'             => 1,
			'toolbar_title'       => 'GlotPress',
			'toolbar_position'    => 'default',
			'toolbar_target'      => '',
			'toolbar_icon'        => '02',
			'menu_icon'           => '02',
			'gp_language'         => 'inherit',
			'statistics'          => 1,
			'project_view'        => 'list',
			'toolbar_projects'    => 1,
			'toolbar_subprojects' => 0,
			'toolbar_depth'       => 2,
			'homepage'            => 0,
			'slug'                => '',
			'enabled'             => 0,
			'layout'              => 'boxed',
			'width'               => 1280,
			'work_width'          => 1600,
			'wide_editor'         => 1,
			'padding'             => 24,
			'radius'              => 12,
			'background'          => '#eef1f5',
			'surface'             => '#ffffff',
			'text'                => '#17212b',
			'accent'              => '#3855a6',
			'border'              => '#718096',
			'background_image'    => 0,
			'font'                => 'system',
			'font_size'           => 16,
			'table_density'       => 'comfortable',
			'header'              => 0,
			'before'              => 0,
			'after'               => 0,
			'footer'              => 0,
			'delete_data'         => 0,
		);
	}
	/**
	* Read and normalize settings without modifying storage.
	*
	* @return array<string,mixed> Validated settings.
	*/
	public static function get(): array {
		return self::normalize( get_option( 'glotframe_settings', array() ) );
	}
	/**
	 * Normalize untrusted settings; unknown fields never enter the stored schema.
	 * @param mixed $input Submitted or persisted settings.
	 * @return array<string,mixed> Complete normalized settings.
	 */
	public static function normalize( $input ): array {
		$out = self::defaults();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( array( 'enabled', 'wide_editor', 'delete_data', 'toolbar', 'toolbar_projects', 'toolbar_subprojects', 'homepage', 'statistics' ) as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
					continue;
			} $out[ $key ] = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) && '1' === (string) $input[ $key ] ? 1 : 0;
		}
		foreach ( array(
			'width'         => array( 720, 2400 ),
			'work_width'    => array( 960, 3200 ),
			'padding'       => array( 0, 96 ),
			'radius'        => array( 0, 40 ),
			'font_size'     => array( 12, 24 ),
			'toolbar_depth' => array( 1, 6 ),
		) as $key => $range ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) {
				$out[ $key ] = max( $range[0], min( $range[1], (int) $input[ $key ] ) );
			}
		}
		foreach ( array( 'background', 'surface', 'text', 'accent', 'border' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) {
				$color       = sanitize_hex_color( $input[ $key ] );
				$out[ $key ] = $color ? $color : $out[ $key ];
			}
		}
		foreach ( array(
			'layout'           => array( 'boxed', 'full' ),
			'font'             => array( 'system', 'serif', 'mono' ),
			'table_density'    => array( 'comfortable', 'compact' ),
			'preset'           => array_keys( Presets::all() ),
			'project_view'     => array( 'list', 'grid' ),
			'toolbar_position' => array( 'default', 'left_start', 'left_end', 'right', 'before', 'after' ),
			'gp_language'      => array( 'inherit', 'de_DE', 'de_DE_formal' ),
			'toolbar_icon'     => array_map( 'strval', array_keys( Icons::choices() ) ),
			'menu_icon'        => array_map( 'strval', array_keys( Icons::choices() ) ),
		) as $key => $allowed ) {
			if ( isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ) {
				$out[ $key ] = $input[ $key ];
			}
		}
		foreach ( array( 'header', 'before', 'after', 'footer' ) as $key ) {
			$id          = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
			$post        = $id ? get_post( $id ) : null;
			$out[ $key ] = Layouts::is_available( $post ) ? $id : 0;
		}
		$out['toolbar_target'] = isset( $input['toolbar_target'] ) && is_string( $input['toolbar_target'] ) && preg_match( '/^[A-Za-z0-9_.:-]{1,120}$/D', $input['toolbar_target'] ) && 'glotframe-gp' !== $input['toolbar_target'] ? $input['toolbar_target'] : '';
		$out['toolbar_title']  = isset( $input['toolbar_title'] ) && is_string( $input['toolbar_title'] ) ? wp_html_excerpt( sanitize_text_field( $input['toolbar_title'] ), 40, '' ) : 'GlotPress';
		if ( '' === trim( $out['toolbar_title'] ) ) {
			$out['toolbar_title'] = 'GlotPress'; }
		$slug                    = isset( $input['slug'] ) && is_string( $input['slug'] ) ? trim( $input['slug'], '/ ' ) : '';
		$out['slug']             = preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ? $slug : '';
		$id                      = isset( $input['background_image'] ) && is_scalar( $input['background_image'] ) ? absint( $input['background_image'] ) : 0;
		$out['background_image'] = $id && wp_attachment_is_image( $id ) && get_post_mime_type( $id ) !== 'image/svg+xml' ? $id : 0;
		return $out;
	}
	/**
	 * Validate authorized settings independently of the optional GlotPress runtime.
	 * @param mixed $input Settings API submission.
	 * @return array<string,mixed> Existing or validated settings.
	 */
	public static function save( $input ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( 'glotframe_settings', 'glotframe_forbidden', __( 'You are not allowed to change these settings.', 'glotframe' ) );
			$stored = get_option( 'glotframe_settings', array() );
			return is_array( $stored ) ? $stored : self::defaults();
		}
		$input = is_array( $input ) ? $input : array();
		foreach ( array( 'enabled', 'wide_editor', 'delete_data', 'toolbar', 'toolbar_projects', 'toolbar_subprojects', 'homepage', 'statistics' ) as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
					$input[ $key ] = 0;
			}
		}
		$out         = self::normalize( $input );
		$unavailable = array();
		foreach ( Layouts::regions() as $region => $label ) {
			if ( ! empty( $input[ $region ] ) && empty( $out[ $region ] ) ) {
				$unavailable[] = $label;
			}
		}
		if ( self::get()['preset'] !== $out['preset'] && ! isset( $input['schema'] ) && ( ! isset( $input['palette_ready'] ) || '1' !== (string) $input['palette_ready'] ) ) {
			foreach ( Presets::all()[ $out['preset'] ]['colors'] as $key => $color ) {
				if ( array_key_exists( $key, $out ) ) {
							$out[ $key ] = $color;
				}
			}
		}
		if ( ! Routing::valid_slug( $out['slug'] ) || ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== trim( $input['slug'], '/ ' ) && '' === $out['slug'] ) ) {
			$out['slug'] = self::get()['slug'];
			add_settings_error( 'glotframe_settings', 'glotframe_slug', __( 'This URL path is reserved or already in use. The previous path has been kept.', 'glotframe' ) );
		}
		if ( $unavailable && ! in_array( 'glotframe_layouts_cleared', array_column( get_settings_errors(), 'code' ), true ) ) {
			$errors = array_filter(
				get_settings_errors(),
				static function ( $error ) {
					return 'error' === $error['type'];
				}
			);
			/* translators: %s: comma-separated layout region labels. */
			$message = $errors ? __( 'Unavailable layout assignments cleared: %s.', 'glotframe' ) : __( 'Settings saved. Unavailable layout assignments cleared: %s.', 'glotframe' );
			add_settings_error( 'glotframe_settings', 'glotframe_layouts_cleared', sprintf( $message, implode( ', ', $unavailable ) ), $errors ? 'warning' : 'success' );
		}
		return $out;
	}
}
