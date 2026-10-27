<?php
/** Closes the workspace and preserves both footer hook families. @package GlotFrame */
defined( 'ABSPATH' ) || exit;
use Deckerweb\GlotFrame\Shell;
?>
</main>
<?php
if ( Shell::region( 'after' ) ) :
	?>
	<div class="glotframe-region glotframe-after"><?php echo Shell::region( 'after' ); ?></div><?php endif; ?>
<footer class="glotframe-region glotframe-footer">
<?php
if ( Shell::region( 'footer' ) ) {
	echo Shell::region( 'footer' ); } else {
	?>
<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<?php the_privacy_policy_link( '<p>', '</p>' ); ?>
<p><a href="https://glotpress.blog/">GlotPress</a></p>
	<?php } ?>
</footer>
</div>
</div>
<?php
gp_footer();
wp_footer();
?>
</body>
</html>
