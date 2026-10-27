<?php
/** Curated workspace palettes and accessible semantic colors. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Supplies complete color palettes independently of layout and content settings. */
final class Presets {
	/** Return named palettes with color-pair choices designed for readable controls.
	 * @return array<string,array> Label, scheme and palette values.
	 */
	public static function all(): array {
		return array(
			'white'      => array(
				'label'  => __( 'White', 'glotframe' ),
				'scheme' => 'light',
				'colors' => array(
					'background' => '#eef1f5',
					'surface'    => '#ffffff',
					'text'       => '#17212b',
					'accent'     => '#3855a6',
					'border'     => '#718096',
					'muted'      => '#4b596b',
					'subtle'     => '#f3f5f8',
					'field'      => '#ffffff',
				),
			),
			'charcoal'   => array(
				'label'  => __( 'Night owl · Charcoal', 'glotframe' ),
				'scheme' => 'dark',
				'colors' => array(
					'background' => '#101113',
					'surface'    => '#1c1e22',
					'text'       => '#f1f3f5',
					'accent'     => '#a9c3ff',
					'border'     => '#77818f',
					'muted'      => '#bdc5d0',
					'subtle'     => '#282c33',
					'field'      => '#14171b',
				),
			),
			'midnight'   => array(
				'label'  => __( 'Midnight blue', 'glotframe' ),
				'scheme' => 'dark',
				'colors' => array(
					'background' => '#081220',
					'surface'    => '#102139',
					'text'       => '#edf5ff',
					'accent'     => '#87cfff',
					'border'     => '#718ba9',
					'muted'      => '#b4c9e1',
					'subtle'     => '#18314e',
					'field'      => '#0b192c',
				),
			),
			'sand'       => array(
				'label'  => __( 'Sunlit sand', 'glotframe' ),
				'scheme' => 'light',
				'colors' => array(
					'background' => '#f0e5c8',
					'surface'    => '#fff9e9',
					'text'       => '#352c1d',
					'accent'     => '#715014',
					'border'     => '#8c7854',
					'muted'      => '#68583d',
					'subtle'     => '#f2e7c9',
					'field'      => '#fffdf5',
				),
			),
			'terracotta' => array(
				'label'  => __( 'Warm terracotta', 'glotframe' ),
				'scheme' => 'light',
				'colors' => array(
					'background' => '#eee0d8',
					'surface'    => '#fff8f3',
					'text'       => '#382a26',
					'accent'     => '#923e2e',
					'border'     => '#997567',
					'muted'      => '#70564c',
					'subtle'     => '#f3e5dc',
					'field'      => '#fffdfb',
				),
			),
			'harbor'     => array(
				'label'  => __( 'Harbor blue', 'glotframe' ),
				'scheme' => 'light',
				'colors' => array(
					'background' => '#d9e6f2',
					'surface'    => '#f0f6fc',
					'text'       => '#25384a',
					'accent'     => '#275e8b',
					'border'     => '#6b86a0',
					'muted'      => '#4b647b',
					'subtle'     => '#e1edf7',
					'field'      => '#f9fcff',
				),
			),
			'bordeaux'   => array(
				'label'  => __( 'Soft Bordeaux', 'glotframe' ),
				'scheme' => 'light',
				'colors' => array(
					'background' => '#e6d9dd',
					'surface'    => '#f7eef0',
					'text'       => '#38272d',
					'accent'     => '#7b3449',
					'border'     => '#92727e',
					'muted'      => '#69515b',
					'subtle'     => '#eadde2',
					'field'      => '#fbf6f7',
				),
			),
			'sage'       => array(
				'label'  => __( 'Sage green', 'glotframe' ),
				'scheme' => 'light',
				'colors' => array(
					'background' => '#dce8df',
					'surface'    => '#f0f6ef',
					'text'       => '#26382b',
					'accent'     => '#2c6548',
					'border'     => '#728775',
					'muted'      => '#4d6553',
					'subtle'     => '#e1ecdf',
					'field'      => '#f8fbf6',
				),
			),

		);
	}
	/** Calculate relative luminance using the WCAG sRGB formula.
	 * @param string $hex Validated three- or six-digit hex color.
	 * @return float Relative luminance from zero to one.
	 */
	public static function luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$values = array();
		foreach ( str_split( $hex, 2 ) as $part ) {
			$v        = hexdec( $part ) / 255;
			$values[] = $v <= 0.04045 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
		}
		return 0.2126 * $values[0] + 0.7152 * $values[1] + 0.0722 * $values[2];
	}
	/** Choose legible black or white text for a colored button.
	 * @param string $background Validated button background.
	 * @return string Higher-contrast text color.
	 */
	public static function on_color( string $background ): string {
		$l = self::luminance( $background );
		return ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / 0.05 ) ? '#ffffff' : '#000000';
	}
	/** Build semantic surface, feedback and control colors from a selected palette.
	 * @param array $settings Normalized user settings.
	 * @return array<string,string> Color tokens used only within GlotFrame.
	 */
	public static function tokens( array $settings ): array {
		$preset = self::all()[ $settings['preset'] ];
		$dark   = self::luminance( $settings['surface'] ) < 0.179;
		$colors = array_merge( $preset['colors'], array_intersect_key( $settings, $preset['colors'] ) );
		// Individual surface changes also switch control and feedback surfaces coherently.
		if ( ( 'dark' === $preset['scheme'] ) !== $dark ) {
			$base = self::all()[ $dark ? 'charcoal' : 'white' ]['colors'];
			foreach ( array( 'muted', 'subtle', 'field' ) as $key ) {
				$colors[ $key ] = $base[ $key ];
			}
		}
		$ratio                  = ( max( self::luminance( $colors['border'] ), self::luminance( $colors['field'] ) ) + 0.05 ) / ( min( self::luminance( $colors['border'] ), self::luminance( $colors['field'] ) ) + 0.05 );
		$colors['field-border'] = $ratio >= 3 ? $colors['border'] : $colors['muted'];
		return $colors + array(
			'on-accent'  => self::on_color( $settings['accent'] ),
			'scheme'     => $dark ? 'dark' : 'light',
			'success'    => $dark ? '#a4e8bd' : '#1e6238',
			'success-bg' => $dark ? '#153b2a' : '#e7f4eb',
			'warning'    => $dark ? '#ffe09a' : '#6c4b00',
			'warning-bg' => $dark ? '#443718' : '#fff0c2',
			'danger'     => $dark ? '#ffb5b5' : '#9b2727',
			'danger-bg'  => $dark ? '#492326' : '#fdeaea',
			'neutral-bg' => $dark ? '#303742' : '#e9edf3',
		);
	}
}
