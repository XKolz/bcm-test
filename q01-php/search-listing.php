<?php
/**
 * Q1 corrected version: paginated, searchable post listing with ad slots.
 *
 * Use as a page template in the theme, e.g. /listing/?page=2&s=lagos.
 * In the real theme, inc/ads.php is required from functions.php.
 */

require_once __DIR__ . '/inc/ads.php';

// Read and sanitise the query string. Missing or invalid "page" falls back to 1.
$page   = isset( $_GET['page'] ) ? max( 1, (int) wp_unslash( $_GET['page'] ) ) : 1;
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$q = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 10,
		'paged'               => $page, // WP_Query paginates with "paged", not "page".
		's'                   => $search, // Empty string = no search filter.
		'ignore_sticky_posts' => true,
	)
);

$pages     = max( 1, (int) $q->max_num_pages );
$ad_number = 0;
?>
<h1>
	<?php
	if ( '' !== $search ) {
		/* translators: 1: current page, 2: total pages, 3: search term. */
		printf(
			esc_html__( 'Page %1$s of %2$s: Search for “%3$s”', 'bcm' ),
			esc_html( number_format_i18n( $page ) ),
			esc_html( number_format_i18n( $pages ) ),
			esc_html( $search )
		);
	} else {
		printf(
			esc_html__( 'Page %1$s of %2$s', 'bcm' ),
			esc_html( number_format_i18n( $page ) ),
			esc_html( number_format_i18n( $pages ) )
		);
	}
	?>
</h1>

<?php if ( $q->have_posts() ) : ?>
	<?php
	while ( $q->have_posts() ) :
		$q->the_post();

		$content = bcm_replace_ad_markers( get_the_content(), $ad_number );
		$content = apply_filters( 'the_content', $content ); // wpautop, embeds, shortcodes.
		$content = str_replace( ']]>', ']]&gt;', $content ); // Same as core the_content().
		?>
		<article <?php post_class(); ?>>
			<h2><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h2>
			<div class="entry-content">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- post content, filtered by the_content as in core. ?>
			</div>
		</article>
	<?php endwhile; ?>

	<nav class="pagination" aria-label="<?php esc_attr_e( 'Results pages', 'bcm' ); ?>">
		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'base'     => add_query_arg( 'page', '%#%' ),
					'format'   => '',
					'current'  => $page,
					'total'    => $pages,
					'add_args' => '' !== $search ? array( 's' => rawurlencode( $search ) ) : false,
				)
			)
		);
		?>
	</nav>
<?php else : ?>
	<p class="no-results">
		<?php
		if ( '' !== $search ) {
			printf( esc_html__( 'No posts found for “%s”.', 'bcm' ), esc_html( $search ) );
		} else {
			esc_html_e( 'No posts found on this page.', 'bcm' );
		}
		?>
	</p>
<?php endif; ?>
<?php
wp_reset_postdata(); // Restore the global $post for anything rendered after this.
