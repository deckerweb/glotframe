<?php
/** Read-only GP statistics view using the native projects route. @package GlotFrame */
defined( 'ABSPATH' ) || exit;
use Deckerweb\GlotFrame\Statistics;
gp_title( __( 'Statistics', 'glotframe' ) . ' ‹ GlotPress' );
gp_breadcrumb( array( __( 'Statistics', 'glotframe' ) ) );
gp_tmpl_header();
echo '<h2>' . esc_html__( 'Translation statistics', 'glotframe' ) . '</h2>';
Statistics::view();
gp_tmpl_footer();
