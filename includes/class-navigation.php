<?php
/** Admin shortcuts and site-local GlotPress toolbar navigation. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;

/** Supplies shortcuts using live GlotPress URLs and its project visibility filters. */
final class Navigation {
	/** @var array<string,string> Visible top-level destinations captured before the native toolbar is bound. */
	private static array $targets = array();
	/** Register menu, toolbar and tiny local navigation assets. @return void */
	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 999 );
		add_action( 'admin_bar_menu', array( self::class, 'toolbar' ), 90 );
		add_action( 'wp_before_admin_bar_render', array( self::class, 'position' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}
	/** Add a final, visibly separated external submenu without a placeholder admin page.
	 * @return void Adds the current GlotPress destination when its runtime is ready.
	 */
	public static function menu(): void {
		global $submenu;
		if ( Shell::available() && isset( $submenu['glotframe'] ) ) {
			// Keep the native menu renderer, bypassing plugin_basename() for this external URL.
			$submenu['glotframe'][] = array( __( 'Open GlotPress ↗', 'glotframe' ), 'manage_options', gp_url_public_root() );
		}
	}
	/** Enqueue external-link enhancement only in admin and toolbar styles only where needed.
	 * @return void Loads local assets without HTTP requests to third parties.
	 */
	public static function assets(): void {
		if ( ! Shell::available() || is_network_admin() ) {
			return;
		}
		if ( is_admin() ) {
			wp_enqueue_script( 'glotframe-navigation', plugins_url( 'assets/navigation.js', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION, true );
		}
		if ( is_admin() || ( is_admin_bar_showing() && Settings::get()['toolbar'] ) ) {
			wp_enqueue_style( 'glotframe-interface-icons', plugins_url( 'assets/icons.css', GLOTFRAME_FILE ), array( 'dashicons' ), GLOTFRAME_VERSION );
			wp_add_inline_style( 'glotframe-interface-icons', Icons::css() );
			wp_enqueue_style( 'glotframe-navigation', plugins_url( 'assets/navigation.css', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION );
		}
	}
	/** Build the toolbar for the current website and logged-in user only.
	 * @param \WP_Admin_Bar $bar Native WordPress toolbar instance.
	 * @return void Adds root, projects, language/user shortcuts and authorized administration.
	 */
	public static function toolbar( $bar ): void {
		$s = Settings::get();
		if ( ! Shell::available() || ! $s['toolbar'] || ! is_user_logged_in() || is_network_admin() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'glotframe-gp',
				'title' => Icons::toolbar( $s['toolbar_icon'] ) . '<span class="ab-label">' . esc_html( $s['toolbar_title'] ) . '</span>',
				'href'  => gp_url( '/' ),
				'meta'  => array( 'title' => __( 'Translation workspace', 'glotframe' ) ),
			)
		);
		foreach ( self::project_actions( Shell::project() ) as $id => $action ) {
			$bar->add_node(
				array(
					'id'     => 'glotframe-' . $id,
					'parent' => 'glotframe-gp',
					'title'  => esc_html( $action['label'] ),
					'href'   => $action['url'],
				)
			);
		}
		if ( $s['statistics'] && $s['enabled'] ) {
			$bar->add_node(
				array(
					'id'     => 'glotframe-statistics',
					'parent' => 'glotframe-gp',
					'title'  => __( 'Statistics', 'glotframe' ),
					'href'   => Statistics::url(),
				)
			); }
		if ( $s['toolbar_projects'] ) {
			$bar->add_node(
				array(
					'id'     => 'glotframe-projects',
					'parent' => 'glotframe-gp',
					'title'  => __( 'All projects', 'glotframe' ),
					'href'   => gp_url( '/projects/' ),
				)
			);
			$children = array();
			foreach ( \GP::$project->all( 'name ASC' ) as $project ) {
				$children[ (int) $project->parent_project_id ][] = $project;
			}
			self::projects( $bar, $children, 0, 'glotframe-projects', $s['toolbar_subprojects'] ? $s['toolbar_depth'] : 0, array() );
		}
		$bar->add_node(
			array(
				'id'     => 'glotframe-locales',
				'parent' => 'glotframe-gp',
				'title'  => __( 'Languages', 'glotframe' ),
				'href'   => gp_url( '/languages/' ),
			)
		);
		$bar->add_node(
			array(
				'id'     => 'glotframe-profile',
				'parent' => 'glotframe-gp',
				'title'  => __( 'My GlotPress profile', 'glotframe' ),
				'href'   => gp_url_profile( wp_get_current_user()->user_nicename ),
			)
		);
		$bar->add_node(
			array(
				'id'     => 'glotframe-gp-settings',
				'parent' => 'glotframe-gp',
				'title'  => __( 'GlotPress preferences', 'glotframe' ),
				'href'   => gp_url( '/settings' ),
			)
		);
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_group(
				array(
					'id'     => 'glotframe-admin-group',
					'parent' => 'glotframe-gp',
				)
			);
			$bar->add_node(
				array(
					'id'     => 'glotframe-design',
					'parent' => 'glotframe-admin-group',
					'title'  => __( 'GlotFrame settings', 'glotframe' ),
					'href'   => admin_url( 'admin.php?page=glotframe' ),
				)
			);
			$bar->add_node(
				array(
					'id'     => 'glotframe-layouts',
					'parent' => 'glotframe-admin-group',
					'title'  => __( 'Manage layouts', 'glotframe' ),
					'href'   => admin_url( 'edit.php?post_type=' . Layouts::POST_TYPE ),
				)
			);
		}
	}
	/** Return available toolbar destinations collected from this user's actual toolbar.
	 * @return array<string,string> Request-local ID/label choices, never persisted as a menu snapshot.
	 */
	public static function targets(): array {
		return self::$targets;
	}
	/** Identify a top-level menu, including menus in native top-level groups.
	 * @param object $node Unbound native toolbar node.
	 * @param array $nodes Complete unbound node map.
	 * @return bool Whether this is a menu rather than a nested menu or group.
	 */
	private static function is_top_menu( $node, array $nodes ): bool {
		if ( ! preg_match( '/^[A-Za-z0-9_.:-]{1,120}$/D', (string) $node->id ) || in_array( $node->id, array( 'root', 'search' ), true ) || ! empty( $node->group ) ) {
			return false;
		}
		$parent = $node->parent;
		return ! $parent || 'root' === $parent || ( isset( $nodes[ $parent ] ) && ! empty( $nodes[ $parent ]->group ) && ( ! $nodes[ $parent ]->parent || 'root' === $nodes[ $parent ]->parent ) );
	}
	/** Capture menu choices and place only GlotFrame relative to complete native registrations.
	 * @return void Preserves every other node's data, parent and relative order through public toolbar APIs.
	 */
	public static function position(): void {
		global $wp_admin_bar;
		if ( ! $wp_admin_bar instanceof \WP_Admin_Bar || is_network_admin() ) {
			return;
		}
		$s             = Settings::get();
		$screen        = is_admin() && function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$needs_choices = $screen && 'toplevel_page_glotframe' === $screen->id;
		if ( ! $needs_choices && ( 'default' === $s['toolbar_position'] || ! $wp_admin_bar->get_node( 'glotframe-gp' ) ) ) {
			return; }
		$nodes = $wp_admin_bar->get_nodes();
		if ( ! $nodes ) {
			return;
		}
		self::$targets = array();
		foreach ( $nodes as $id => $node ) {
			if ( 'glotframe-gp' !== $id && self::is_top_menu( $node, $nodes ) ) {
				$label                = 'my-account' === $id ? __( 'User profile', 'glotframe' ) : trim( wp_specialchars_decode( wp_strip_all_tags( (string) $node->title ), ENT_QUOTES ) );
				self::$targets[ $id ] = $label ? $label : $id;
			}
		}
		if ( ! isset( $nodes['glotframe-gp'] ) ) {
			return;
		}
		$mode = $s['toolbar_position'];
		if ( ! Shell::available() || ! $s['toolbar'] || ! is_user_logged_in() ) {
			return; }
		if ( 'default' === $mode ) {
			return;
		}
		$target = $s['toolbar_target'];
		$after  = 'after' === $mode;
		if ( 'right' === $mode ) {
			$target = isset( $nodes['my-account'] ) && 'top-secondary' === $nodes['my-account']->parent ? 'my-account' : '';
		}
		if ( in_array( $mode, array( 'left_start', 'left_end' ), true ) ) {
			$left = array_filter(
				$nodes,
				/** Identify ordinary left-side peers without changing their own order.
				 * @param object $node Unbound native toolbar item.
				 * @return bool Whether this is a left-side root menu other than GlotFrame.
				 */
				static function ( $node ) {
					return empty( $node->group ) && ( ! $node->parent || 'root' === $node->parent ) && 'glotframe-gp' !== $node->id;
				}
			);
			$target = $left ? ( 'left_start' === $mode ? array_key_first( $left ) : array_key_last( $left ) ) : '';
			$after  = 'left_end' === $mode;
		}
		if ( ! $target || ! isset( self::$targets[ $target ] ) ) {
			return; // A missing destination keeps the untouched registration at its standard position.
		}
		$own         = clone $nodes['glotframe-gp'];
		$own->parent = $nodes[ $target ]->parent;
		$ordered     = array();
		foreach ( $nodes as $id => $node ) {
			if ( 'glotframe-gp' === $id ) {
				continue; }
			if ( $target === $id && ! $after ) {
				$ordered[ $own->id ] = $own; }
			$ordered[ $id ] = $node;
			if ( $target === $id && $after ) {
				$ordered[ $own->id ] = $own; }
		}
		// WP offers no insertion-index API. Re-register the identical snapshot in its new order before binding.
		// Descendants and foreign menu metadata are retained; only our root node changes place/parent.
		foreach ( $nodes as $id => $node ) {
			$wp_admin_bar->remove_node( $id ); }
		foreach ( $ordered as $node ) {
			$wp_admin_bar->add_node( $node ); }
	}
	/** Return creation links only where the native GlotPress write checks permit them.
	 * @param \GP_Project|null $project Current view's project, when available.
	 * @return array<string,array<string,string>> Stable action IDs with labels and native GET URLs; writes stay in GlotPress.
	 */
	public static function project_actions( $project = null ): array {
		if ( ! Shell::available() || ! is_user_logged_in() ) {
			return array();
		}
		$actions = array();
		if ( \GP::$permission->current_user_can( 'write', 'project', null ) ) {
			$actions['new-project'] = array(
				'label' => __( 'New project', 'glotframe' ),
				'url'   => gp_url_project( '-new' ),
			);
		}
		if ( $project instanceof \GP_Project && $project->id && \GP::$permission->current_user_can( 'write', 'project', $project->id ) ) {
			$actions['new-subproject'] = array(
				'label' => __( 'Add subproject', 'glotframe' ),
				'url'   => add_query_arg( 'parent_project_id', (int) $project->id, gp_url_project( '-new' ) ),
			);
		}
		return $actions;
	}

	/** Apply the same visibility contract used by GlotPress project listings.
	 * @param array $projects Candidate native projects.
	 * @param int $parent_id Expected parent ID, zero for roots.
	 * @return array Visible projects with matching parent IDs.
	 */
	public static function visible_children( array $projects, int $parent_id ): array {
		$visible = apply_filters( 'gp_projects', $projects, $parent_id );
		return is_array( $visible ) ? array_values(
			array_filter(
				$visible,
				static function ( $p ) use ( $parent_id ) {
					return $p instanceof \GP_Project && (int) $p->parent_project_id === $parent_id;
				}
			)
		) : array();
	}

	/** Recursively render a filtered project tree with bounded depth and cycle protection.
	 * @param \WP_Admin_Bar $bar WordPress toolbar.
	 * @param array $children Projects grouped by parent ID from one database query.
	 * @param int $parent_id GlotPress parent project ID, zero at root.
	 * @param string $node Parent toolbar node ID.
	 * @param int $remaining Additional subproject levels still allowed.
	 * @param int[] $visited Ancestor project IDs to reject cycles.
	 * @return void Adds visible projects without changing permissions or fetching each parent separately.
	 */
	private static function projects( $bar, array $children, int $parent_id, string $node, int $remaining, array $visited ): void {
		// Use the same public visibility filter as GlotPress's top_level/sub_projects helpers.
		$projects = self::visible_children( $children[ $parent_id ] ?? array(), $parent_id );
		if ( ! is_array( $projects ) ) {
			return;
		}
		$seen = array();
		foreach ( $projects as $project ) {
			if ( ! $project instanceof \GP_Project || (int) $project->parent_project_id !== $parent_id || in_array( (int) $project->id, $visited, true ) || isset( $seen[ $project->id ] ) ) {
				continue;
			}
			$seen[ $project->id ] = true;
			$id                   = 'glotframe-project-' . (int) $project->id;
			$bar->add_node(
				array(
					'id'     => $id,
					'parent' => $node,
					'title'  => esc_html( $project->name ),
					'href'   => gp_url_project( $project ),
				)
			);
			if ( $remaining > 0 ) {
				self::projects( $bar, $children, (int) $project->id, $id, $remaining - 1, array_merge( $visited, array( (int) $project->id ) ) );
			}
		}
	}
}
