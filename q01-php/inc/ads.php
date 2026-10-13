<?php
/**
 * Ad placeholder helper. In a real theme this lives in functions.php (or an
 * included file) so templates and tests can share it.
 *
 * Assumptions (see q01-php/ANSWER.md):
 * - The marker is the exact, case-sensitive text "[ad]". Variants such as
 *   "[AD]" or "[ad 3]" are left untouched.
 * - Each marker becomes an empty div numbered by its position on the page
 *   (ad-1, ad-2, ...), counted across all posts in the listing, so every slot
 *   has a unique class the JavaScript ad loader can target.
 * - Markers are replaced BEFORE the_content filters run. wpautop() treats a
 *   <div> as a block element, so a marker on its own line does not end up
 *   wrapped in <p>, and a registered [ad] shortcode (if one ever exists)
 *   cannot double-process it.
 */

if ( ! function_exists( 'bcm_replace_ad_markers' ) ) {
	/**
	 * @param string $content Raw post content.
	 * @param int    $counter Running slot number, passed by reference so the
	 *                        numbering continues across posts on one page.
	 */
	function bcm_replace_ad_markers( string $content, int &$counter ): string {
		return preg_replace_callback(
			'/\[ad\]/',
			static function () use ( &$counter ): string {
				$counter++;
				// Leave for the JavaScript handler to replace with a live ad.
				return sprintf(
					'<div class="ad ad-%1$d" data-ad-slot="%1$d"></div>',
					$counter
				);
			},
			$content
		);
	}
}
