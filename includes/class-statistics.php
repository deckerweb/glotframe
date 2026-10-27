<?php
/** Current-state statistics for operators, translators and project groups. @package GlotFrame */
namespace Deckerweb\GlotFrame;
defined( 'ABSPATH' ) || exit;
/** Aggregates visible active strings and retained contribution records without inventing historical events. */
final class Statistics {
	/** Register operator page and locally served chart styles. @return void */
	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'init', array( self::class, 'styles' ) );
	}
	/** Register shared statistics styles without global loading. @return void */
	public static function styles(): void {
		wp_register_style( 'glotframe-statistics', plugins_url( 'assets/statistics.css', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION );
	}
	/** Add an operator-only submenu before the external GP shortcut. @return void */
	public static function menu(): void {
		add_submenu_page( 'glotframe', __( 'Statistics', 'glotframe' ), __( 'Statistics', 'glotframe' ), 'manage_options', 'glotframe-statistics', array( self::class, 'admin' ) );
	}
	/** Load styles only on the statistics admin screen.
	 * @param string $hook Current admin screen.
	 * @return void Enqueues local dashboard styles.
	 */
	public static function assets( string $hook ): void {
		if ( 'glotframe_page_glotframe-statistics' === $hook ) {
			wp_enqueue_style( 'glotframe-admin', plugins_url( 'assets/admin.css', GLOTFRAME_FILE ), array(), GLOTFRAME_VERSION );
			self::controls();}
	}
	/** Load the searchable project filter only for full statistics pages. @return void */
	public static function controls(): void {
		wp_enqueue_style( 'glotframe-statistics' );
		wp_enqueue_style( 'glotframe-tom-select', plugins_url( 'assets/vendor/tom-select/tom-select.css', GLOTFRAME_FILE ), array(), '2.6.2' );
		wp_enqueue_script( 'glotframe-tom-select', plugins_url( 'assets/vendor/tom-select/tom-select.complete.min.js', GLOTFRAME_FILE ), array(), '2.6.2', true );
		wp_enqueue_script( 'glotframe-statistics', plugins_url( 'assets/statistics.js', GLOTFRAME_FILE ), array( 'glotframe-tom-select' ), GLOTFRAME_VERSION, true );
		wp_localize_script( 'glotframe-statistics', 'glotframeStatistics', array( 'all' => __( 'All visible projects', 'glotframe' ) ) );
	}

	/** Return the native GP root statistics destination without creating write routes.
	 * @return string Native GP projects URL with a read-only statistics marker.
	 */
	public static function url(): string {
		return Shell::available() ? add_query_arg( 'glotframe_statistics', '1', gp_url( '/projects/' ) ) : '';
	}
	/** Check the explicit frontend marker; access is checked separately.
	 * @return bool Whether this request asks for the statistics page.
	 */
	public static function requested(): bool {
		return isset( $_GET['glotframe_statistics'] ) && is_string( $_GET['glotframe_statistics'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['glotframe_statistics'] ) );
	}
	/** Read a scalar GET filter only; this handler never modifies data.
	 * @param string $key Filter key.
	 * @param string $fallback Fallback value.
	 * @return string Sanitized scalar input.
	 */
	private static function filter( string $key, string $fallback = '' ): string {
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $fallback;
	}
	/** Read selected project IDs, preserving an explicit invalid selection for collect().
	 * @return int[] Untrusted IDs; [0] represents an invalid explicit selection, [] means no filter.
	 */
	private static function selected(): array {
		$raw = isset( $_GET['gf_projects'] ) ? map_deep( wp_unslash( $_GET['gf_projects'] ), 'sanitize_text_field' ) : array();
		if ( ! is_array( $raw ) ) {
			return array( 0 );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', array_filter( $raw, 'is_scalar' ) ) ) ) );
		// Preserve an explicit invalid filter so collect() cannot broaden it to all projects.
		return $raw && ! $ids ? array( 0 ) : $ids;
	}
	/** Collect a current snapshot using batched prepared read queries.
	 * @param int[] $roots Selected projects; descendants are included. Empty selects visible projects.
	 * @param string $locale Optional exact GP language code.
	 * @param string $slug Optional exact translation-set slug.
	 * @param bool $personal Whether to count only this user's retained contribution records.
	 * @param int $days Optional submission window for personal counts, not for completion ratios.
	 * @param bool $hidden Whether to include hidden originals only where native GP write permission permits.
	 * @return array Current scoped aggregates, never another user's personal records or a historical timeline.
	 */
	public static function collect( array $roots = array(), string $locale = '', string $slug = '', bool $personal = false, int $days = 0, bool $hidden = false ): array {

		$requested = $roots;
		$visible   = Blocks::visible_catalog();
		$roots     = array_values( array_intersect( array_map( 'absint', $roots ), array_keys( $visible ) ) );
		$scope     = $visible;
		if ( $roots ) {
			$scope = array();
			foreach ( $visible as $id => $p ) {
				$path        = $id;
					$visited = array();
				while ( $path && isset( $visible[ $path ] ) && ! isset( $visited[ $path ] ) ) {
					if ( in_array( $path, $roots, true ) ) {
						$scope[ $id ] = $p;
							break;
					}
					$visited[ $path ] = true;
					$path             = (int) $visible[ $path ]->parent_project_id;}
			}
		}
		// Nonempty invalid selections must never silently broaden into a global view.
		if ( $requested && ! $roots ) {
			$scope = array();
		}
		$data = array(
			'projects'         => array(),
			'locales'          => array(),
			'slugs'            => array(),
			'sets'             => array(),
			'originals'        => 0,
			'tasks'            => 0,
			'current'          => 0,
			'waiting'          => 0,
			'fuzzy'            => 0,
			'changesrequested' => 0,
			'warnings'         => 0,
			'mine'             => array(),
			'recent'           => array(),
			'oldest'           => null,
			'contributors'     => 0,
			'hidden'           => false,
			'roots'            => 0,
			'active'           => 0,
			'inactive'         => 0,
			'generated'        => time(),
		);
		if ( ! Shell::available() || ! $scope ) {
			return $data;
		}
		$writers = array();
		if ( $hidden ) {
			$admin = \GP::$permission->current_user_can( 'admin' );
			foreach ( $scope as $id => $p ) {
				if ( $admin || \GP::$permission->current_user_can( 'write', 'project', $id ) ) {
					$writers[] = $id;
				}
			}
		}
		$data['hidden'] = (bool) $writers;
		foreach ( $scope as $id => $p ) {
			$data['projects'][ $id ] = array(
				'project' => $p,
				'tasks'   => 0,
				'current' => 0,
				'waiting' => 0,
				'fuzzy'   => 0,
				'locales' => array(),
			);
			if ( ! (int) $p->parent_project_id ) {
				++$data['roots'];
			}if ( $p->active ) {
				++$data['active'];
			} else {
				++$data['inactive'];
			}
		}
		$originals    = array();
		$contributors = array();
		$uid          = get_current_user_id();
		$days         = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 0;
		$cutoff       = $days ? gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) : '';
		foreach ( array_chunk( array_keys( $scope ), 200 ) as $ids ) {
			// SQL fragments contain only generated placeholders; GP prepares every value and table identifier.
			$marks = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$write = array_values( array_intersect( $writers, $ids ) );
			if ( ! $write ) {
				$write = array( 0 );
			}
			$write_marks = implode( ',', array_fill( 0, count( $write ), '%d' ) );
			$rows        = \GP::$original->many_no_map( "SELECT project_id,COUNT(*) AS total,SUM(priority<>%d) AS public_count FROM %i WHERE status=%s AND project_id IN ($marks) GROUP BY project_id", ...array_merge( array( -2, \GP::$original->table, '+active' ), $ids ) );
			foreach ( $rows as $row ) {
				$originals[ (int) $row->project_id ] = in_array( (int) $row->project_id, $writers, true ) ? (int) $row->total : (int) $row->public_count;
			}
			$sets          = \GP::$translation_set->many_no_map( "SELECT id,project_id,locale,slug,name FROM %i WHERE project_id IN ($marks) ORDER BY name ASC", ...array_merge( array( \GP::$translation_set->table ), $ids ) );
			$selected_sets = array();
			foreach ( $sets as $set ) {
				$data['locales'][ $set->locale ] = $set->locale;
				$data['slugs'][ $set->slug ]     = $set->slug;
				if ( ( $locale && $set->locale !== $locale ) || ( $slug && $set->slug !== $slug ) ) {
							continue;
				}
				$selected_sets[ (int) $set->id ] = $set;
				$data['sets'][ (int) $set->id ]  = $set;}
			if ( ! $selected_sets ) {
				continue;
			}
			foreach ( $selected_sets as $set ) {
				$pid = (int) $set->project_id;
				if ( ! isset( $data['projects'][ $pid ] ) ) {
							$data['projects'][ $pid ] = array(
								'project' => $scope[ $pid ],
								'tasks'   => 0,
								'current' => 0,
								'waiting' => 0,
								'fuzzy'   => 0,
								'locales' => array(),
							);
				}
				$count                              = $originals[ $pid ] ?? 0;
				$data['tasks']                     += $count;
				$data['projects'][ $pid ]['tasks'] += $count;
				if ( ! isset( $data['projects'][ $pid ]['locales'][ $set->locale ] ) ) {
							$data['projects'][ $pid ]['locales'][ $set->locale ] = array(
								'tasks'   => 0,
								'current' => 0,
							);
				}
				$data['projects'][ $pid ]['locales'][ $set->locale ]['tasks'] += $count;}
			$set_ids = array_keys( $selected_sets );
			foreach ( array_chunk( $set_ids, 200 ) as $batch ) {
				$set_marks = implode( ',', array_fill( 0, count( $batch ), '%d' ) );
				$query     = "SELECT t.translation_set_id,t.status,COUNT(*) AS records,COUNT(DISTINCT t.original_id) AS distinct_originals,SUM(CASE WHEN t.warnings IS NOT NULL AND t.warnings<>'' AND t.status IN ('current','waiting') THEN 1 ELSE 0 END) AS warnings,MIN(t.date_added) AS first_added FROM %i AS t INNER JOIN %i AS o ON o.id=t.original_id INNER JOIN %i AS s ON s.id=t.translation_set_id AND s.project_id=o.project_id WHERE o.status=%s AND t.translation_set_id IN ($set_marks) AND (o.priority<>%d OR o.project_id IN ($write_marks)) GROUP BY t.translation_set_id,t.status";
				$params    = array_merge( array( \GP::$translation->table, \GP::$original->table, \GP::$translation_set->table, '+active' ), $batch, array( -2 ), $write );
				foreach ( \GP::$translation->many_no_map( $query, ...$params ) as $row ) {
					$set    = $selected_sets[ (int) $row->translation_set_id ];
					$pid    = (int) $set->project_id;
					$status = $row->status;
					$n      = (int) $row->records;
					if ( 'current' === $status ) {
						$n                                    = min( $originals[ $pid ] ?? 0, (int) $row->distinct_originals );
						$data['current']                     += $n;
						$data['projects'][ $pid ]['current'] += $n;
						$data['projects'][ $pid ]['locales'][ $set->locale ]['current'] += $n;
					} elseif ( in_array( $status, array( 'waiting', 'fuzzy', 'changesrequested' ), true ) ) {
									$data[ $status ] += $n;
						if ( isset( $data['projects'][ $pid ][ $status ] ) ) {
							$data['projects'][ $pid ][ $status ] += $n;
						}
					}
					$data['warnings'] += (int) $row->warnings;
					if ( 'waiting' === $status && $row->first_added && ( ! $data['oldest'] || $row->first_added < $data['oldest'] ) ) {
						$data['oldest'] = $row->first_added;
					}
				}
				if ( $personal && $uid ) {
					$query  = "SELECT t.status,t.translation_set_id,COUNT(*) AS records,MAX(t.date_added) AS recent FROM %i AS t INNER JOIN %i AS o ON o.id=t.original_id INNER JOIN %i AS s ON s.id=t.translation_set_id AND s.project_id=o.project_id WHERE o.status=%s AND t.translation_set_id IN ($set_marks) AND (o.priority<>%d OR o.project_id IN ($write_marks)) AND t.user_id=%d AND (%s='' OR t.date_added>=%s) GROUP BY t.status,t.translation_set_id";
					$params = array_merge( array( \GP::$translation->table, \GP::$original->table, \GP::$translation_set->table, '+active' ), $batch, array( -2 ), $write, array( $uid, $cutoff, $cutoff ) );
					foreach ( \GP::$translation->many_no_map( $query, ...$params ) as $row ) {
						$data['mine'][ $row->status ] = ( $data['mine'][ $row->status ] ?? 0 ) + (int) $row->records;
						$id                           = (int) $row->translation_set_id;
						if ( ! isset( $data['recent'][ $id ] ) || $row->recent > $data['recent'][ $id ] ) {
											$data['recent'][ $id ] = $row->recent;
						}
					}
				}
				if ( is_admin() && current_user_can( 'manage_options' ) ) {
					$query  = "SELECT DISTINCT t.user_id FROM %i AS t INNER JOIN %i AS o ON o.id=t.original_id INNER JOIN %i AS s ON s.id=t.translation_set_id AND s.project_id=o.project_id WHERE o.status=%s AND t.translation_set_id IN ($set_marks) AND (o.priority<>%d OR o.project_id IN ($write_marks)) AND t.user_id IS NOT NULL AND t.user_id>0";
					$params = array_merge( array( \GP::$translation->table, \GP::$original->table, \GP::$translation_set->table, '+active' ), $batch, array( -2 ), $write );
					foreach ( \GP::$translation->many_no_map( $query, ...$params ) as $contributor ) {
						$contributors[ (int) $contributor->user_id ] = true;
					}
				}
			}
		}
		foreach ( $scope as $id => $p ) {
			$data['originals'] += $originals[ $id ] ?? 0;
		}
		$data['contributors'] = count( $contributors );
		ksort( $data['locales'] );
		ksort( $data['slugs'] );
		arsort( $data['recent'] );
		return $data;
	}
	/** Allowed output for the locally generated statistics graphic.
	 * @return array HTML and SVG allowlist; no script, links or styles.
	 */
	private static function chart_tags(): array {
		return array(
			'p'          => array(),
			'figure'     => array( 'class' => true ),
			'figcaption' => array(),
			'svg'        => array(
				'viewbox'    => true,
				'role'       => true,
				'aria-label' => true,
			),
			'rect'       => array(
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'class'  => true,
			),
		);
	}
	/** Render an accessible two-part completion graphic with exact numbers outside the SVG.
	 * @param int $current Completed tasks.
	 * @param int $total Total tasks.
	 * @return string SVG plus accessible caption, or an empty-state message.
	 */
	public static function chart( int $current, int $total ): string {
		if ( ! $total ) {
			return '<p>' . esc_html__( 'No translation tasks in this selection.', 'glotframe' ) . '</p>';
		}
		$percent = round( min( $current, $total ) / $total * 100, 1 );
		/* translators: 1: completion percentage, 2: completed tasks, 3: total tasks. */
		$label = sprintf( __( '%1$s%% complete · %2$s of %3$s translation tasks', 'glotframe' ), number_format_i18n( $percent, 1 ), number_format_i18n( $current ), number_format_i18n( $total ) );
		return '<figure class="gf-stat-chart"><svg viewBox="0 0 100 5" role="img" aria-label="' . esc_attr( $label ) . '"><rect width="100" height="5" rx="1" class="gf-chart-rest"/><rect width="' . esc_attr( (string) $percent ) . '" height="5" rx="1" class="gf-chart-current"/></svg><figcaption>' . esc_html( $label ) . '</figcaption></figure>';
	}
	/** Render a compact project-group block from public active strings only.
	 * @param int $project Root project ID.
	 * @return string Reusable read-only summary with a chart.
	 */
	public static function compact( int $project ): string {
		if ( ! Shell::available() || ! $project || ! Settings::get()['statistics'] ) {
			return '';
		}
		$data = self::collect( array( $project ) );
		/* translators: 1: project count, 2: source-string count. */
		return '<div class="gf-statistics"><p>' . esc_html( sprintf( __( '%1$s projects · %2$s source strings', 'glotframe' ), number_format_i18n( count( $data['projects'] ) ), number_format_i18n( $data['originals'] ) ) ) . '</p>' . self::chart( $data['current'], $data['tasks'] ) . '</div>';
	}
	/** Render the operator page only after both WordPress capability and runtime checks. @return void */
	public static function admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap glotframe-admin">';
		Admin_Chrome::header( __( 'Statistics', 'glotframe' ) );
		echo '<hr class="wp-header-end">';
		if ( ! Shell::available() || ! Settings::get()['statistics'] ) {
			echo '<p>' . esc_html__( 'Statistics are unavailable or disabled.', 'glotframe' ) . '</p>';
			Admin_Chrome::footer();
			echo '</div>';
			return;}
		self::view( true );
		Admin_Chrome::footer();
		echo '</div>';
	}
	/** Render filtered operator/group/personal summaries; frontend personal records always belong to the current user.
	 * @param bool $admin Whether the authorized operator page is rendering.
	 * @return void Emits escaped controls, charts, work links and a paged project matrix.
	 */
	public static function view( bool $admin = false ): void {
		if ( ! Shell::available() || ! Settings::get()['statistics'] || ( ! $admin && ! is_user_logged_in() ) ) {
			return;
		}
		wp_enqueue_style( 'glotframe-statistics' );
		$mode     = self::filter( 'gf_mode', $admin ? 'projects' : 'mine' );
		$personal = 'mine' === $mode;
		$locale   = self::filter( 'gf_locale' );
		$slug     = self::filter( 'gf_set' );
		$days     = absint( self::filter( 'gf_days', '0' ) );
		$hidden   = '1' === self::filter( 'gf_hidden' );
		$selected = self::selected();
		$data     = self::collect( $selected, $locale, $slug, $personal, $days, $hidden );
		$visible  = Blocks::visible_catalog();
		echo '<div class="gf-statistics"><form method="get" class="gf-stat-filters">';
		if ( $admin ) {
			echo '<input type="hidden" name="page" value="glotframe-statistics">';
		} else {
			echo '<input type="hidden" name="glotframe_statistics" value="1">';
		}
		echo '<label>' . esc_html__( 'View', 'glotframe' ) . '<select name="gf_mode">';
		foreach ( array(
			'projects' => __( 'Project groups', 'glotframe' ),
			'mine'     => __( 'My contributions', 'glotframe' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $mode, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}echo '</select></label>';
		echo '<label>' . esc_html__( 'Projects including descendants', 'glotframe' ) . '<select name="gf_projects[]" multiple size="4" data-gf-project-filter>';
		foreach ( $visible as $id => $p ) {
			echo '<option value="' . esc_attr( (string) $id ) . '" ' . selected( in_array( $id, $selected, true ), true, false ) . '>' . esc_html( $p->path ) . '</option>';
		}echo '</select></label>';
		foreach ( array(
			'gf_locale' => array( __( 'Language', 'glotframe' ), $data['locales'], $locale ),
			'gf_set'    => array( __( 'Translation-set variant', 'glotframe' ), $data['slugs'], $slug ),
		) as $name => $def ) {
			echo '<label>' . esc_html( $def[0] ) . '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'All', 'glotframe' ) . '</option>';
			foreach ( $def[1] as $value ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $def[2], $value, false ) . '>' . esc_html( $value ) . '</option>';
			}echo '</select></label>';}
		if ( $personal ) {
			echo '<label>' . esc_html__( 'Stored submission dates', 'glotframe' ) . '<select name="gf_days">';
			foreach ( array(
				0  => __( 'All retained records', 'glotframe' ),
				7  => __( 'Last 7 days', 'glotframe' ),
				30 => __( 'Last 30 days', 'glotframe' ),
				90 => __( 'Last 90 days', 'glotframe' ),
			) as $value => $label ) {
				echo '<option value="' . esc_attr( (string) $value ) . '" ' . selected( $days, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}echo '</select></label>';}
		if ( \GP::$permission->current_user_can( 'admin' ) ) {
			echo '<label class="gf-checkbox-row"><input type="checkbox" name="gf_hidden" value="1" ' . checked( $hidden, true, false ) . '><span>' . esc_html__( 'Include authorized hidden strings', 'glotframe' ) . '</span></label>';
		}
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply filters', 'glotframe' ) . '</button></form>';
		echo '<p class="gf-stat-note">' . esc_html__( 'No project selection means all visible projects. Counts use active source strings; hidden strings are excluded unless explicitly authorized. Translation tasks count each selected language/set separately.', 'glotframe' ) . '</p>';
		$cards = array(
			__( 'Projects', 'glotframe' )          => count( $data['projects'] ),
			__( 'Source strings', 'glotframe' )    => $data['originals'],
			__( 'Translation tasks', 'glotframe' ) => $data['tasks'],
			__( 'Waiting proposals', 'glotframe' ) => $data['waiting'],
			__( 'Fuzzy proposals', 'glotframe' )   => $data['fuzzy'],
			__( 'Warnings', 'glotframe' )          => $data['warnings'],
		);
		if ( $admin ) {
			$cards[ __( 'Root projects', 'glotframe' ) ]           = $data['roots'];
			$cards[ __( 'Subprojects', 'glotframe' ) ]             = count( $data['projects'] ) - $data['roots'];
			$cards[ __( 'Active projects', 'glotframe' ) ]         = $data['active'];
			$cards[ __( 'Inactive projects', 'glotframe' ) ]       = $data['inactive'];
			$cards[ __( 'Attributed contributors', 'glotframe' ) ] = $data['contributors'];}
		$cards[ __( 'Changes requested', 'glotframe' ) ] = $data['changesrequested'];
		echo '<h2>' . esc_html__( 'Selected project scope', 'glotframe' ) . '</h2><dl class="gf-stat-cards">';
		foreach ( $cards as $label => $value ) {
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( number_format_i18n( $value ) ) . '</dd></div>';
		}echo '</dl>' . wp_kses( self::chart( $data['current'], $data['tasks'] ), self::chart_tags() );
		if ( $personal ) {
			echo '<h2>' . esc_html__( 'My retained contributions', 'glotframe' ) . '</h2><p>' . esc_html__( 'These are attributed records still present in the selected active projects, not a complete activity log or a quality ranking. Imports and removed records can affect the counts. The date filter applies only to your contribution records.', 'glotframe' ) . '</p><dl class="gf-stat-cards">';
			foreach ( array(
				'current'          => __( 'Current', 'glotframe' ),
				'waiting'          => __( 'Waiting', 'glotframe' ),
				'fuzzy'            => __( 'Fuzzy', 'glotframe' ),
				'rejected'         => __( 'Rejected', 'glotframe' ),
				'old'              => __( 'Older versions', 'glotframe' ),
				'changesrequested' => __( 'Changes requested', 'glotframe' ),
			) as $status => $label ) {
				echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( number_format_i18n( $data['mine'][ $status ] ?? 0 ) ) . '</dd></div>';
			}echo '</dl><h3>' . esc_html__( 'Recently submitted records', 'glotframe' ) . '</h3><ul>';
			foreach ( array_slice( $data['recent'], 0, 10, true ) as $id => $date ) {
				$set = $data['sets'][ $id ];
				$p   = $visible[ (int) $set->project_id ];
				$url = gp_url_project( $p, array( $set->locale, $set->slug ) );
				echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $p->name . ' · ' . $set->locale . ' · ' . $set->name ) . '</a> <time>' . esc_html( $date ) . '</time></li>';}
			echo '</ul>';
		}
		$rows = array_values( $data['projects'] );
		usort(
			$rows,
			static function ( $a, $b ) {
				return ( $b['tasks'] - $b['current'] ) <=> ( $a['tasks'] - $a['current'] );
			}
		);
		$page      = max( 1, absint( self::filter( 'gf_page', '1' ) ) );
		$pages     = max( 1, (int) ceil( count( $rows ) / 20 ) );
		$page      = min( $pages, $page );
		$rows      = array_slice( $rows, ( $page - 1 ) * 20, 20 );
		$languages = $locale ? array( $locale ) : array_slice( array_keys( $data['locales'] ), 0, 12 );
		if ( $data['oldest'] ) {
			/* translators: %s: stored proposal date in UTC. */
			echo '<p>' . esc_html( sprintf( __( 'Oldest stored waiting proposal: %s', 'glotframe' ), $data['oldest'] . ' UTC' ) ) . '</p>';
		}
		echo '<h2>' . esc_html__( 'Projects needing attention', 'glotframe' ) . '</h2><p>' . esc_html__( 'The completion bars use source-string-weighted totals. Proposal counts and warnings can overlap and are not slices of the completion chart. Work links show up to five matching sets per project; select a language to narrow the list.', 'glotframe' ) . '</p><div class="gf-stat-scroll"><table class="gf-stat-table"><thead><tr><th>' . esc_html__( 'Project', 'glotframe' ) . '</th><th>' . esc_html__( 'Completion', 'glotframe' ) . '</th><th>' . esc_html__( 'Remaining tasks', 'glotframe' ) . '</th><th>' . esc_html__( 'Work queue', 'glotframe' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$p = $row['project'];
			echo '<tr><th><a href="' . esc_url( gp_url_project( $p ) ) . '">' . esc_html( $p->name ) . '</a></th><td>' . wp_kses( self::chart( $row['current'], $row['tasks'] ), self::chart_tags() ) . '</td><td>' . esc_html( number_format_i18n( max( 0, $row['tasks'] - $row['current'] ) ) ) . '</td><td>';
			$links = 0;
			foreach ( $data['sets'] as $set ) {
				if ( (int) $set->project_id !== (int) $p->id || $links++ >= 5 ) {
					continue;
				}
				$url = add_query_arg( 'filters[status]', 'waiting', gp_url_project( $p, array( $set->locale, $set->slug ) ) );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $set->locale . ' · ' . $set->name . ' · ' . __( 'Waiting', 'glotframe' ) ) . '</a> ';
				$open = add_query_arg( 'filters[status]', 'untranslated', gp_url_project( $p, array( $set->locale, $set->slug ) ) );
				echo '<a href="' . esc_url( $open ) . '">' . esc_html( $set->locale . ' · ' . __( 'Untranslated', 'glotframe' ) ) . '</a> ';}
			echo '</td></tr>';
		}echo '</tbody></table></div>';
		echo '<h2>' . esc_html__( 'Project and language matrix', 'glotframe' ) . '</h2><p>' . esc_html__( 'Up to 12 language columns are shown. Select a language above to inspect any other language. Empty cells mean no matching set.', 'glotframe' ) . '</p><div class="gf-stat-scroll"><table class="gf-stat-table gf-stat-matrix"><thead><tr><th>' . esc_html__( 'Project', 'glotframe' ) . '</th>';
		foreach ( $languages as $language ) {
			echo '<th scope="col">' . esc_html( $language ) . '</th>';
		}echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row['project']->name ) . '</th>';
			foreach ( $languages as $language ) {
				$item      = $row['locales'][ $language ] ?? null;
					$value = $item && $item['tasks'] ? round( $item['current'] / $item['tasks'] * 100 ) : null;
					echo '<td class="' . ( null === $value ? '' : 'gf-heat-' . (int) floor( $value / 25 ) ) . '">' . ( null === $value ? '—' : esc_html( number_format_i18n( $value ) ) . '%' ) . '</td>';
			}echo '</tr>';}
		echo '</tbody></table></div><nav class="gf-stat-pages" aria-label="' . esc_attr__( 'Statistics pages', 'glotframe' ) . '">';for ( $n = max( 1, $page - 2 );$n <= min( $pages, $page + 2 );$n++ ) {
			if ( $n === $page ) {
				echo '<strong aria-current="page">' . esc_html( (string) $n ) . '</strong>';
			} else {
				echo '<a href="' . esc_url( add_query_arg( 'gf_page', $n ) ) . '">' . esc_html( (string) $n ) . '</a>';
			}
		}
		/* translators: %s: snapshot generation date and time. */
		echo '</nav><p class="gf-stat-note">' . esc_html__( 'Current snapshot; no historical trend is inferred.', 'glotframe' ) . ' ' . esc_html( sprintf( __( 'Updated: %s', 'glotframe' ), wp_date( 'Y-m-d H:i:s', $data['generated'] ) ) ) . '</p></div>';
	}
}
