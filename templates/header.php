<?php
/** GlotFrame's outer HTML provider. @package GlotFrame */
defined( 'ABSPATH' ) || exit;
use Deckerweb\GlotFrame\Shell;
use Deckerweb\GlotFrame\Navigation;
Shell::prepare();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php Shell::head(); ?>
</head>
<body <?php body_class( Shell::classes() ); ?>>
<?php wp_body_open(); ?>
<a class="glotframe-skip" href="#glotframe-main"><?php esc_html_e( 'Skip to translations', 'glotframe' ); ?></a>
<div class="glotframe-viewport">
<div class="glotframe-frame">
<?php if ( Shell::region( 'header' ) ) : ?>
<header class="glotframe-region glotframe-header"><?php echo Shell::region( 'header' ); // Rendered WordPress presentation blocks. ?></header>
<?php endif; ?>
<nav class="glotframe-navigation" aria-label="<?php esc_attr_e( 'Translation workspace', 'glotframe' ); ?>">
<a class="glotframe-home" href="<?php echo esc_url( gp_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
<div><?php echo wp_kses_post( gp_nav_menu() ); ?></div>
<div class="glotframe-project-actions">
<?php foreach ( Navigation::project_actions( Shell::project() ) as $glotframe_action ) : ?>
<a class="glotframe-project-action" href="<?php echo esc_url( $glotframe_action['url'] ); ?>"><span aria-hidden="true">＋</span><?php echo esc_html( $glotframe_action['label'] ); ?></a>
<?php endforeach; ?>
</div>
<?php
if ( is_user_logged_in() && \Deckerweb\GlotFrame\Settings::get()['statistics'] ) :
	?>
	<a href="<?php echo esc_url( \Deckerweb\GlotFrame\Statistics::url() ); ?>"><?php esc_html_e( 'Statistics', 'glotframe' ); ?></a><?php endif; ?>
<div class="glotframe-account"><?php echo wp_kses_post( gp_nav_menu( 'side' ) ); ?></div>
</nav>
<?php
if ( Shell::region( 'before' ) ) :
	?>
	<div class="glotframe-region glotframe-before"><?php echo Shell::region( 'before' ); ?></div><?php endif; ?>
<main id="glotframe-main" class="gp-content glotframe-main" tabindex="-1">
<?php echo wp_kses_post( gp_breadcrumb() ); ?>
<div id="gp-js-message" class="gp-js-message" aria-live="polite"></div>
<?php
if ( gp_notice( 'error' ) ) :
	?>
	<div class="error"><?php echo wp_kses_post( gp_notice( 'error' ) ); ?></div><?php endif; ?>
<?php
if ( gp_notice() ) :
	?>
	<div class="notice"><?php echo wp_kses_post( gp_notice() ); ?></div><?php endif; ?>
<?php do_action( 'gp_after_notices' ); ?>
