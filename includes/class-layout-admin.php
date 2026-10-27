<?php
/**
 * Native layout-list conveniences and protected draft duplication.
 *
 * @package GlotFrame
 */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Keeps layout management within native WordPress screens and permissions. */
final class Layout_Admin {

	/** Register narrowly scoped list hooks and the nonce-protected action. @return void */
	public static function register_hooks(): void {
		add_filter( 'manage_' . Layouts::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Layouts::POST_TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( self::class, 'actions' ), 10, 2 );
		add_action( 'admin_action_glotframe_duplicate_layout', array( self::class, 'duplicate' ) );
	}

	/** Insert the usage column beside the title, preserving other columns.
	 * @param array $columns Existing native columns.
	 * @return array Columns including the localized usage heading.
	 */
	public static function columns( array $columns ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'title' === $key ) {
				$result['gf_usage'] = __( 'Used as', 'glotframe' );
			}
		}
		return $result;
	}

	/** Describe stored assignments, including those not currently displayed.
	 * @param string $column Requested native custom column.
	 * @param int    $post_id Layout ID on the current website.
	 * @return void Escaped labels only; no visitor-facing change.
	 */
	public static function column( string $column, int $post_id ): void {
		if ( 'gf_usage' !== $column || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$stored = get_option( 'glotframe_settings', array() );
		$used   = array();
		foreach ( Layouts::regions() as $region => $label ) {
			if ( is_array( $stored ) && isset( $stored[ $region ] ) && is_scalar( $stored[ $region ] ) && absint( $stored[ $region ] ) === $post_id ) {
				$used[] = $label;
			}
		}
		if ( ! $used ) {
			echo '<span class="gf-layout-muted">' . esc_html__( 'Not assigned', 'glotframe' ) . '</span>';
			return;
		}
		echo '<ul class="gf-layout-usage">';
		foreach ( $used as $label ) {
			echo '<li>' . esc_html( $label ) . '</li>';
		}
		echo '</ul>';
		if ( ! Layouts::is_available( get_post( $post_id ) ) ) {
			echo '<span class="gf-layout-muted">' . esc_html__( 'Not displayed: layout unavailable', 'glotframe' ) . '</span>';
		}
	}

	/** Return an authorized native edit URL for an existing, non-trashed layout.
	 * @param int $post_id Candidate layout ID.
	 * @return string Raw administrative URL, or an empty string.
	 */
	public static function edit_link( int $post_id ): string {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || Layouts::POST_TYPE !== $post->post_type || 'trash' === $post->post_status || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}
		return (string) get_edit_post_link( $post_id, 'raw' );
	}

	/** Provide selection-aware edit links without exposing other post types.
	 * @return array<int,string> Available choices and any editable stale assignments.
	 */
	public static function edit_links(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}
		$posts  = get_posts(
			array(
				'post_type'      => Layouts::POST_TYPE,
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$ids    = wp_list_pluck( $posts, 'ID' );
		$stored = get_option( 'glotframe_settings', array() );
		foreach ( array_keys( Layouts::regions() ) as $region ) {
			if ( is_array( $stored ) && isset( $stored[ $region ] ) && is_scalar( $stored[ $region ] ) ) {
				$ids[] = absint( $stored[ $region ] );
			}
		}
		$links = array();
		foreach ( array_unique( $ids ) as $post_id ) {
			$link = self::edit_link( (int) $post_id );
			if ( $link ) {
				$links[ $post_id ] = $link;
			}
		}
		return $links;
	}

	/** Offer duplication only for editable layouts on website-level screens.
	 * @param array    $actions Existing native row actions.
	 * @param \WP_Post $post Row's post object.
	 * @return array Original actions plus a nonce-protected draft action when permitted.
	 */
	public static function actions( array $actions, \WP_Post $post ): array {
		if ( is_network_admin() || Layouts::POST_TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) || ! self::edit_link( $post->ID ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'glotframe_duplicate_layout',
					'layout' => $post->ID,
				),
				admin_url( 'admin.php' )
			),
			'glotframe_duplicate_' . $post->ID
		);
		/* translators: %s: layout title. */
		$label                   = sprintf( __( 'Duplicate as draft: %s', 'glotframe' ), $post->post_title ? $post->post_title : __( 'Untitled layout', 'glotframe' ) );
		$actions['gf_duplicate'] = '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $label ) . '">' . esc_html__( 'Duplicate as draft', 'glotframe' ) . '</a>';
		return $actions;
	}

	/** Create an independent draft from native layout content, then open its editor.
	 * @return void Exits after redirect or an escaped native error page.
	 */
	public static function duplicate(): void {
		if ( is_network_admin() || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this layout.', 'glotframe' ), '', array( 'response' => 403 ) );
		}
		// The following nonce is scoped to this source ID; only native post content is copied.
		$post_id = isset( $_GET['layout'] ) && is_string( $_GET['layout'] ) ? absint( $_GET['layout'] ) : 0;
		check_admin_referer( 'glotframe_duplicate_' . $post_id );
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || Layouts::POST_TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'This layout cannot be duplicated.', 'glotframe' ), '', array( 'response' => 403 ) );
		}
		$title = $post->post_title ? $post->post_title : __( 'Untitled layout', 'glotframe' );
		/* translators: %s: source layout title. */
		$title = sprintf( __( '%s (copy)', 'glotframe' ), $title );
		$id    = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => Layouts::POST_TYPE,
					'post_status'    => 'draft',
					'post_title'     => $title,
					'post_content'   => $post->post_content,
					'post_password'  => $post->post_password,
					'post_author'    => get_current_user_id(),
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html__( 'The draft could not be created. The original layout is unchanged.', 'glotframe' ), '', array( 'response' => 500 ) );
		}
		wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
		exit;
	}
}
