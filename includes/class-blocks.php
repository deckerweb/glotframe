<?php
/** Layout patterns and native GlotPress data blocks. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;
/** Registers editable starting points and renders bounded, permission-aware project data. */
final class Blocks {
	/** @var array Request-local project catalogs, isolated by website. */
	private static array $catalogs = array();
	/** @var array Request-local progress HTML, isolated by website and user. */
	private static array $progress = array();
	/** Register metadata and editor integration without loading frontend assets globally. @return void */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor' ) );
		add_filter( 'block_categories_all', array( self::class, 'categories' ), 10, 2 );
	}
	/** Register four metadata-based dynamic blocks and seven core-block patterns. @return void */
	public static function register(): void {
		wp_register_script( 'glotframe-blocks', plugins_url( 'assets/blocks.js', GLOTFRAME_FILE ), array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ), GLOTFRAME_VERSION, true );
		wp_register_style( 'glotframe-data-blocks', plugins_url( 'assets/projects.css', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION );
		foreach ( array( 'project-list', 'current-project', 'project-actions', 'progress', 'statistics' ) as $name ) {
			register_block_type( dirname( GLOTFRAME_FILE ) . '/blocks/' . $name, array( 'render_callback' => array( self::class, 'render' ) ) );
		}
		register_block_pattern_category( 'glotframe', array( 'label' => __( 'GlotFrame layouts', 'glotframe' ) ) );
		foreach ( self::patterns() as $slug => $pattern ) {
			register_block_pattern(
				'glotframe/' . $slug,
				array(
					'title'      => $pattern['title'],
					'categories' => array( 'glotframe' ),
					'postTypes'  => array( Layouts::POST_TYPE ),
					'content'    => $pattern['content'],
				)
			);
		}
	}
	/** Add the data-block category only to the layout editor.
	 * @param array $categories Existing categories.
	 * @param \WP_Block_Editor_Context $context Active editor.
	 * @return array Categories with the layout section when applicable.
	 */
	public static function categories( array $categories, $context ): array {
		if ( isset( $context->post ) && Layouts::POST_TYPE === $context->post->post_type ) {
			$categories[] = array(
				'slug'  => 'glotframe',
				'title' => 'GlotFrame',
			);
		}
		return $categories;
	}
	/** Load controls only for authorized layout editing, using visible project names as plain data. @return void */
	public static function editor(): void {
		$screen = get_current_screen();
		if ( ! $screen || Layouts::POST_TYPE !== $screen->post_type || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_script( 'glotframe-blocks' );
		wp_enqueue_style( 'glotframe-data-blocks' );
		$projects = array();
		if ( Shell::available() ) {
			$groups = array();
			foreach ( self::catalog() as $p ) {
				$groups[ (int) $p->parent_project_id ][] = $p;
			}
			$queue = array( 0 );
			$seen  = array();
			while ( $queue ) {
				$parent = array_shift( $queue );
				if ( isset( $seen[ $parent ] ) ) {
					continue;
				}$seen[ $parent ] = true;
				foreach ( Navigation::visible_children( $groups[ $parent ] ?? array(), $parent ) as $p ) {
					$projects[] = array(
						'value' => (int) $p->id,
						'label' => $p->path,
					);
					if ( isset( $groups[ (int) $p->id ] ) ) {
						$queue[] = (int) $p->id;
					}
				}
			}
		}
		wp_localize_script(
			'glotframe-blocks',
			'glotframeBlocks',
			array(
				'projects' => $projects,
				'labels'   => array(
					'project-list'    => __( 'Project overview', 'glotframe' ),
					'current-project' => __( 'Current project', 'glotframe' ),
					'project-actions' => __( 'Project actions', 'glotframe' ),
					'progress'        => __( 'Translation progress', 'glotframe' ),
					'statistics'      => __( 'Project-group statistics', 'glotframe' ),
					'settings'        => __( 'GlotPress data', 'glotframe' ),
					'project'         => __( 'Project', 'glotframe' ),
					'automatic'       => __( 'Current project (automatic)', 'glotframe' ),
					'source'          => __( 'Projects to show', 'glotframe' ),
					'roots'           => __( 'Top-level projects', 'glotframe' ),
					'children'        => __( 'Subprojects', 'glotframe' ),
					'view'            => __( 'View', 'glotframe' ),
					'list'            => __( 'List', 'glotframe' ),
					'grid'            => __( 'Grid', 'glotframe' ),
					'sort'            => __( 'Sort order', 'glotframe' ),
					'asc'             => __( 'Name A–Z', 'glotframe' ),
					'desc'            => __( 'Name Z–A', 'glotframe' ),
					'active'          => __( 'Active projects only', 'glotframe' ),
					'locale'          => __( 'Language code (optional)', 'glotframe' ),
					'limit'           => __( 'Maximum translation sets', 'glotframe' ),
					'description'     => __( 'Show description', 'glotframe' ),
					'preview'         => __( 'Choose a project to preview context-dependent content.', 'glotframe' ),
				),
			)
		);
	}
	/** Fetch the native project catalog once per website in this request.
	 * @return array<int,\GP_Project> Native projects indexed by ID.
	 */
	public static function catalog(): array {
		$blog = get_current_blog_id();
		if ( ! isset( self::$catalogs[ $blog ] ) ) {
			self::$catalogs[ $blog ] = array();
			if ( Shell::available() ) {
				foreach ( \GP::$project->all( 'name ASC' ) as $p ) {
					self::$catalogs[ $blog ][ (int) $p->id ] = $p;
				}
			}
		}
		return self::$catalogs[ $blog ];
	}
	/** Build the visible project tree once per call, preserving native filters and rejecting cycles.
	 * @return array<int,\GP_Project> Visible projects indexed by ID for the current website/user.
	 */
	public static function visible_catalog(): array {
		$groups = array();
		foreach ( self::catalog() as $p ) {
			$groups[ (int) $p->parent_project_id ][] = $p;
		}
		$queue = array( 0 );
		$seen  = array();
		$out   = array();
		while ( $queue ) {
			$parent = array_shift( $queue );
			if ( isset( $seen[ $parent ] ) ) {
				continue;
			}$seen[ $parent ] = true;
			foreach ( Navigation::visible_children( $groups[ $parent ] ?? array(), $parent ) as $p ) {
				$out[ (int) $p->id ] = $p;
				if ( isset( $groups[ (int) $p->id ] ) ) {
							$queue[] = (int) $p->id;
				}
			}
		}
		return $out;
	}

	/** Check visibility along the whole ancestor chain, rejecting missing projects and cycles.
	 * @param int $id Requested project ID.
	 * @return bool Whether native listing filters retain the entire path.
	 */
	public static function visible( int $id ): bool {
		$all  = self::catalog();
		$seen = array();
		while ( $id ) {
			if ( ! isset( $all[ $id ] ) || isset( $seen[ $id ] ) ) {
				return false;
			}
			$seen[ $id ] = true;
			$p           = $all[ $id ];
			$parent      = (int) $p->parent_project_id;
			$siblings    = array_filter(
				$all,
				static function ( $item ) use ( $parent ) {
					return (int) $item->parent_project_id === $parent;
				}
			);
			$allowed     = Navigation::visible_children( $siblings, $parent );
			if ( ! in_array(
				$id,
				array_map(
					static function ( $item ) {
						return (int) $item->id;
					},
					$allowed
				),
				true
			) ) {
				return false;
			}
			$id = $parent;
		}
		return true;
	}
	/** Resolve a configured project or the native request context, respecting listing filters.
	 * @param array $attributes Validated block attributes.
	 * @return \GP_Project|null Visible project or no applicable context.
	 */
	private static function project( array $attributes ) {
		$id      = absint( $attributes['projectId'] ?? 0 );
		$context = Shell::project();
		if ( ! $id && $context ) {
			$id = (int) $context->id;
		}
		return $id && self::visible( $id ) ? ( self::catalog()[ $id ] ?? null ) : null;
	}
	/** Render native links and escaped data; does not accept HTML or execute writes.
	 * @param array $attributes Block attributes supplied by WordPress.
	 * @param string $content Unused saved inner content.
	 * @param \WP_Block $block Current block metadata and context.
	 * @return string Safe frontend content or an empty string without a supported runtime/context.
	 */
	public static function render( array $attributes, string $content, $block ): string {
		if ( ! Shell::available() ) {
			return '';
		}
		$project = self::project( $attributes );
		$kind    = substr( $block->name, strpos( $block->name, '/' ) + 1 );
		$html    = '';
		if ( 'project-list' === $kind ) {
			$parent = 'children' === ( $attributes['source'] ?? 'roots' ) ? ( $project ? (int) $project->id : -1 ) : 0;
			if ( $parent < 0 ) {
				return '';
			}
			$items = Navigation::visible_children(
				array_values(
					array_filter(
						self::catalog(),
						static function ( $p ) use ( $parent ) {
							return (int) $p->parent_project_id === $parent;
						}
					)
				),
				$parent
			);
			if ( ! empty( $attributes['activeOnly'] ) ) {
				$items = array_values(
					array_filter(
						$items,
						static function ( $p ) {
							return (bool) $p->active;
						}
					)
				);
			}
			if ( 'desc' === ( $attributes['sort'] ?? 'asc' ) ) {
				$items = array_reverse( $items );
			}
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'manage_options' ) ) {
				$items = array_slice( $items, 0, 6 ); }
			$view = 'grid' === ( $attributes['view'] ?? 'list' ) ? 'grid' : 'list';
			$html = '<div class="gf-project-collection gf-view-' . esc_attr( $view ) . '" data-gf-view="' . esc_attr( $view ) . '">';
			foreach ( $items as $item ) {
				$html .= '<article class="gf-project-card"><h3><a href="' . esc_url( gp_url_project( $item ) ) . '">' . esc_html( $item->name ) . '</a></h3><span class="gf-project-status">' . esc_html( $item->active ? __( 'Active', 'glotframe' ) : __( 'Inactive', 'glotframe' ) ) . '</span>';
				if ( ! isset( $attributes['description'] ) || $attributes['description'] ) {
					$html .= '<p>' . esc_html( wp_html_excerpt( wp_strip_all_tags( $item->description ), 180, '…' ) ) . '</p>';
				}
				$html .= '</article>';
			}
			$html .= '</div>';
			if ( ! $items ) {
				$html = '<p>' . esc_html__( 'No projects to display.', 'glotframe' ) . '</p>';
			}
		} elseif ( 'current-project' === $kind && $project ) {
			$html = '<h2><a href="' . esc_url( gp_url_project( $project ) ) . '">' . esc_html( $project->name ) . '</a></h2>';
			if ( ! isset( $attributes['description'] ) || $attributes['description'] ) {
				$html .= '<p>' . esc_html( wp_strip_all_tags( $project->description ) ) . '</p>';
			}
		} elseif ( 'project-actions' === $kind ) {
			foreach ( Navigation::project_actions( $project ) as $action ) {
				$html .= '<a class="glotframe-project-action" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a> ';
			}
		} elseif ( 'statistics' === $kind && $project ) {
			wp_enqueue_style( 'glotframe-statistics' );
			$html = Statistics::compact( (int) $project->id );
		} elseif ( 'progress' === $kind && $project ) {
			$html = self::progress( $project, $attributes );
		}
		return $html ? '<section ' . get_block_wrapper_attributes( array( 'class' => 'gf-data-block' ) ) . '>' . $html . '</section>' : '';
	}
	/** Render bounded progress values through GlotPress's own cached statistics helpers.
	 * @param \GP_Project $project Visible target project.
	 * @param array $attributes Optional locale and a limit clamped to 1–20.
	 * @return string Escaped progress list, memoized per user and website for this request only.
	 */
	private static function progress( $project, array $attributes ): string {
		$locale = isset( $attributes['locale'] ) && is_string( $attributes['locale'] ) ? sanitize_key( $attributes['locale'] ) : '';
		$limit  = max( 1, min( 20, absint( $attributes['limit'] ?? 5 ) ) );
		$key    = get_current_blog_id() . ':' . get_current_user_id() . ':' . $project->id . ':' . $locale . ':' . $limit;
		if ( isset( self::$progress[ $key ] ) ) {
			return self::$progress[ $key ];
		}
		$sets  = \GP::$translation_set->by_project_id( $project->id );
		$html  = '';
		$count = 0;
		foreach ( $sets as $set ) {
			if ( $locale && $set->locale !== $locale ) {
				continue;
			}
			if ( $count++ >= $limit ) {
				break;
			}
			$percent = max( 0, min( 100, (int) $set->percent_translated() ) );
			$label   = $set->name . ' · ' . $set->locale;
			$html   .= '<div class="gf-progress-item"><a href="' . esc_url( gp_url_project( $project, array( $set->locale, $set->slug ) ) ) . '">' . esc_html( $label ) . '</a><span> ' . esc_html( number_format_i18n( $percent ) ) . '%</span><progress max="100" value="' . esc_attr( (string) $percent ) . '" aria-label="' . esc_attr( $label ) . '"></progress></div>';
		}
		if ( ! $html ) {
			$html = '<p>' . esc_html__( 'No translation sets to display.', 'glotframe' ) . '</p>';
		}
		self::$progress[ $key ] = $html;
		return $html;
	}
	/** Return freely editable starting points made only from supported core blocks.
	 * @return array<string,array<string,string>> Pattern titles and serialized block markup.
	 */
	public static function patterns(): array {
		$paragraph = static function ( $text ) {
			return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
		};
		$heading   = static function ( $text ) {
			return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $text ) . '</h2><!-- /wp:heading -->';
		};
		$button    = '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( Shell::available() ? gp_url_public_root() : home_url( '/' ) ) . '">' . esc_html__( 'Browse projects', 'glotframe' ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
		$group     = static function ( $content ) {
			return '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">' . $content . '</div><!-- /wp:group -->';
		};
		return array(
			'compact-header'   => array(
				'title'   => __( 'Compact header', 'glotframe' ),
				'content' => $group( '<!-- wp:site-title /-->' . $paragraph( __( 'A place for your translation community.', 'glotframe' ) ) ),
			),
			'welcome'          => array(
				'title'   => __( 'Welcome and introduction', 'glotframe' ),
				'content' => $group( $heading( __( 'Welcome to our translation community', 'glotframe' ) ) . $paragraph( __( 'Choose a project and help make it available in your language.', 'glotframe' ) ) . $button ),
			),
			'quick-start'      => array(
				'title'   => __( 'Quick start in three steps', 'glotframe' ),
				'content' => $group( $heading( __( 'Start translating', 'glotframe' ) ) . '<!-- wp:columns --><div class="wp-block-columns">' . '<!-- wp:column --><div class="wp-block-column">' . $paragraph( __( '1. Choose a project and your language.', 'glotframe' ) ) . '</div><!-- /wp:column -->' . '<!-- wp:column --><div class="wp-block-column">' . $paragraph( __( '2. Read the project guidelines and glossary.', 'glotframe' ) ) . '</div><!-- /wp:column -->' . '<!-- wp:column --><div class="wp-block-column">' . $paragraph( __( '3. Suggest a translation for review.', 'glotframe' ) ) . '</div><!-- /wp:column -->' . '</div><!-- /wp:columns -->' ),
			),
			'announcement'     => array(
				'title'   => __( 'Announcement', 'glotframe' ),
				'content' => $group( $heading( __( 'Community notice', 'glotframe' ) ) . $paragraph( __( 'Add a project announcement, maintenance notice or translation guideline here.', 'glotframe' ) ) ),
			),
			'help'             => array(
				'title'   => __( 'Help and contact', 'glotframe' ),
				'content' => $group( $heading( __( 'Need help?', 'glotframe' ) ) . $paragraph( __( 'Link to your translation guide, glossary and contact details here.', 'glotframe' ) ) ),
			),
			'community-footer' => array(
				'title'   => __( 'Community footer', 'glotframe' ),
				'content' => $group( '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->' . $paragraph( __( 'Translations are made possible by our community. Thank you for contributing.', 'glotframe' ) ) ),
			),
			'legal-footer'     => array(
				'title'   => __( 'Compact legal footer', 'glotframe' ),
				'content' => $group( $paragraph( __( 'Add your legal notice, privacy policy and contact links here.', 'glotframe' ) ) ),
			),
		);
	}
}
