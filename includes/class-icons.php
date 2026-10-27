<?php
/** Curated local interface icons with native WordPress fallbacks. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;
/** Supplies a fixed icon selection with native WordPress fallbacks. */
final class Icons {
	/** Return translated names for the bundled icons and native fallback.
	 * @return array<string,string> Valid settings keys and labels.
	 */
	public static function choices(): array {
		return array(
			'dashicon' => __( 'WordPress icon', 'glotframe' ),
			'01'       => __( 'Frame', 'glotframe' ),
			'02'       => __( 'Dialogue frame', 'glotframe' ),
			'03'       => __( 'Globe', 'glotframe' ),
			'04'       => __( 'Languages', 'glotframe' ),
			'05'       => __( 'Dialogues', 'glotframe' ),
			'06'       => __( 'Brackets', 'glotframe' ),
			'07'       => __( 'Layout', 'glotframe' ),
			'08'       => __( 'Panels', 'glotframe' ),
			'09'       => __( 'Grid', 'glotframe' ),
			'10'       => __( 'Layers', 'glotframe' ),
			'11'       => __( 'Project tree', 'glotframe' ),
			'12'       => __( 'Folder', 'glotframe' ),
			'13'       => __( 'Exchange', 'glotframe' ),
			'14'       => __( 'Text lines', 'glotframe' ),
			'15'       => __( 'Compass', 'glotframe' ),
			'16'       => __( 'Connection', 'glotframe' ),
		);
	}
	/** Build mask styles only from allowlisted local files. Native Dashicons remain when masks are unavailable.
	 * @return string Scoped CSS for the toolbar and current admin menu choice.
	 */
	public static function css(): string {
		$css = '';
		foreach ( self::choices() as $key => $label ) {
			if ( 'dashicon' === $key || ! is_file( dirname( GLOTFRAME_FILE ) . '/assets/icons/' . $key . '.svg' ) ) {
				continue;
			}
			$url  = esc_url( plugins_url( 'assets/icons/' . $key . '.svg', GLOTFRAME_FILE ) );
			$css .= '.gf-icon-' . $key . '{--gf-icon:url("' . $url . '")}';
		}
		$s   = Settings::get();
		$key = $s['menu_icon'];
		if ( 'dashicon' !== $key && is_file( dirname( GLOTFRAME_FILE ) . '/assets/icons/' . $key . '.svg' ) ) {
			$url  = esc_url( plugins_url( 'assets/icons/' . $key . '.svg', GLOTFRAME_FILE ) );
			$css .= '@supports (mask-image:url("")){#toplevel_page_glotframe .wp-menu-image:before{content:"";width:20px;height:20px;padding:0;margin:7px auto;display:block;background:currentColor;mask:url("' . $url . '") center/contain no-repeat;-webkit-mask:url("' . $url . '") center/contain no-repeat}}';
		}
		return $css;
	}
	/** Return decorative toolbar markup with a native layout Dashicon underneath the mask enhancement.
	 * @param string $key Validated icon identifier.
	 * @return string Trusted markup using escaped class names.
	 */
	public static function toolbar( string $key ): string {
		$class = isset( self::choices()[ $key ] ) && 'dashicon' !== $key && is_file( dirname( GLOTFRAME_FILE ) . '/assets/icons/' . $key . '.svg' ) ? ' gf-interface-icon gf-icon-' . $key : '';
		return '<span class="ab-icon dashicons dashicons-layout' . esc_attr( $class ) . '" aria-hidden="true"></span>';
	}
}
