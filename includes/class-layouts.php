<?php
/**
 * Administrative block content for the surrounding layout.
 *
 * @package GlotFrame
 */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Registers protected layout content and renders only supported presentation blocks. */
final class Layouts {
	const POST_TYPE = 'glotframe_layout';
	/**
	* Register content and editor hooks.
	*
	* @return void
	*/
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'allowed_block_types_all', array( self::class, 'allowed' ), 10, 2 );
	}
	/**
	* Register layouts with administrative capabilities and no public URLs.
	*
	* @return void
	*/
	public static function register(): void {
		$caps = array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'manage_options' );
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'                => array(
					'name'          => __( 'GlotFrame layouts', 'glotframe' ),
					'singular_name' => __( 'GlotFrame layout', 'glotframe' ),
					'add_new_item'  => __( 'Add layout', 'glotframe' ),
					'edit_item'     => __( 'Edit layout', 'glotframe' ),
				),
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => true,
				'show_in_menu'          => 'glotframe',
				'rest_controller_class' => Layout_REST_Controller::class,
				'show_in_rest'          => true,
				'rewrite'               => false,
				'query_var'             => false,
				'exclude_from_search'   => true,
				'capabilities'          => $caps,
				'map_meta_cap'          => false,
				'supports'              => array( 'title', 'editor', 'revisions' ),
				'template'              => array( array( 'core/group', array( 'layout' => array( 'type' => 'constrained' ) ), array( array( 'core/paragraph', array( 'placeholder' => __( 'Add your header, footer or notice content.', 'glotframe' ) ) ) ) ) ),
			)
		);
	}
	/**
	* Return safe presentational blocks, excluding query/content/template recursion.
	*
	* @return string[] Block names.
	*/
	public static function blocks(): array {
		return array( 'glotframe/project-list', 'glotframe/current-project', 'glotframe/project-actions', 'glotframe/progress', 'glotframe/statistics', 'core/group', 'core/columns', 'core/column', 'core/paragraph', 'core/heading', 'core/image', 'core/list', 'core/list-item', 'core/buttons', 'core/button', 'core/separator', 'core/spacer', 'core/site-logo', 'core/site-title', 'core/site-tagline', 'core/navigation', 'core/navigation-link', 'core/navigation-submenu', 'core/home-link', 'core/social-links', 'core/social-link', 'core/details' );
	}
	/**
	 * Restrict this editor without changing other content editors.
	 * @param bool|string[] $allowed Existing editor policy.
	 * @param \WP_Block_Editor_Context $context Current editor context.
	 * @return bool|string[] Existing policy or supported subset.
	 */
	public static function allowed( $allowed, $context ) {
		if ( ! isset( $context->post ) || self::POST_TYPE !== $context->post->post_type ) {
			if ( true === $allowed ) {
				$allowed = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ); }
			return is_array( $allowed ) ? array_values( array_diff( $allowed, array( 'glotframe/project-list', 'glotframe/current-project', 'glotframe/project-actions', 'glotframe/progress', 'glotframe/statistics' ) ) ) : $allowed;
		}
		return is_array( $allowed ) ? array_values( array_intersect( self::blocks(), $allowed ) ) : ( false === $allowed ? false : self::blocks() );
	}
	/** Determine whether a post can be displayed as layout content.
	 * @param mixed $post Candidate post object.
	 * @return bool Published, unprotected GlotFrame layout.
	 */
	public static function is_available( $post ): bool {
		return $post instanceof \WP_Post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
	}
	/** Return the four assignable content regions.
	 * @return array<string,string> Setting keys and localized labels.
	 */
	public static function regions(): array {
		return array(
			'header' => __( 'Header', 'glotframe' ),
			'before' => __( 'Above the workspace', 'glotframe' ),
			'after'  => __( 'Below the workspace', 'glotframe' ),
			'footer' => __( 'Footer', 'glotframe' ),
		);
	}
	/**
	 * Render a published, unprotected layout. Does not execute shortcodes or the_content.
	 * @param int $id Layout post ID in the current website.
	 * @return string Rendered HTML or empty output.
	 */
	public static function render( int $id ): string {
		$post = get_post( $id );
		if ( ! self::is_available( $post ) ) {
			return '';
		}
		$html = '';
		foreach ( self::filter_blocks( parse_blocks( $post->post_content ) ) as $block ) {
			$html .= render_block( $block );
		}
		return $html;
	}
	/**
	 * Remove unsupported blocks recursively, including their content.
	 * @param array $blocks Parsed blocks.
	 * @return array Safe block tree.
	 */
	private static function filter_blocks( array $blocks ): array {
		$safe = array();
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) || ! in_array( $block['blockName'], self::blocks(), true ) ) {
				continue;
			}
			// Keep innerContent placeholders aligned when an unsupported child is removed.
			$children = $block['innerBlocks'];
			$index    = 0;
			$content  = array();
			$kept     = array();
			foreach ( $block['innerContent'] as $part ) {
				if ( null !== $part ) {
					$content[] = $part;
					continue;
				}
				$child    = $children[ $index++ ] ?? null;
				$filtered = $child ? self::filter_blocks( array( $child ) ) : array();
				if ( $filtered ) {
					$kept[]    = $filtered[0];
					$content[] = null;
				}
			}
			$block['innerBlocks']  = $kept;
			$block['innerContent'] = $content;
			$safe[]                = $block;
		}
		return $safe;
	}
}
