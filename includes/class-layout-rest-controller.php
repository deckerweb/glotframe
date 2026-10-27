<?php
/** Administrative REST access for block layouts. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Keeps published layout documents private to administrators while allowing block editing. */
final class Layout_REST_Controller extends \WP_REST_Posts_Controller {
	/**
	 * Require administrative access before the native collection permission check.
	 * @param \WP_REST_Request $request Current REST request.
	 * @return bool|\WP_Error Native result or access denial.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot access GlotFrame layouts.', 'glotframe' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return parent::get_items_permissions_check( $request );
	}
	/**
	 * Require administrative access before the native single-layout permission check.
	 * @param \WP_REST_Request $request Current REST request.
	 * @return bool|\WP_Error Native result or access denial.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot access GlotFrame layouts.', 'glotframe' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return parent::get_item_permissions_check( $request );
	}
}
