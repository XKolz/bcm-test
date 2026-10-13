<?php
/**
 * Minimal WordPress stand-ins so the template can be run with plain PHP CLI.
 * Not WordPress: only enough behaviour to exercise the template's logic.
 * Posts come from $GLOBALS['TEST_POSTS']; WP_Query does real paging and a
 * simple case-insensitive title/content search, like the core "s" argument.
 */

class WP_Query {
	public array $posts = array();
	public int $max_num_pages = 0;
	public int $found_posts = 0;
	public array $query_vars;
	private int $i = -1;

	public function __construct( array $args ) {
		$this->query_vars = $args;
		$GLOBALS['LAST_QUERY_ARGS'] = $args;
		$all = $GLOBALS['TEST_POSTS'];
		$s   = $args['s'] ?? '';
		if ( '' !== $s ) {
			$all = array_values( array_filter( $all, static fn( $p ) =>
				stripos( $p['title'], $s ) !== false || stripos( $p['content'], $s ) !== false ) );
		}
		$per   = $args['posts_per_page'];
		$paged = max( 1, (int) ( $args['paged'] ?? 1 ) ); // Like core: "page" is ignored here.
		$this->found_posts   = count( $all );
		$this->max_num_pages = (int) ceil( $this->found_posts / $per );
		$this->posts         = array_slice( $all, ( $paged - 1 ) * $per, $per );
	}
	public function have_posts(): bool { return $this->i + 1 < count( $this->posts ); }
	public function the_post(): void { $this->i++; $GLOBALS['post'] = $this->posts[ $this->i ]; }
}

function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function sanitize_text_field( $v ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $v ) ) ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_html__( $t, $d = null ) { return esc_html( $t ); }
function esc_html_e( $t, $d = null ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function get_the_title() { return $GLOBALS['post']['title']; }
function the_title() { echo $GLOBALS['post']['title']; } // Core echoes and returns null.
function get_the_content() { return $GLOBALS['post']['content']; }
function the_permalink() { echo '/?p=' . $GLOBALS['post']['id']; }
function post_class() { echo 'class="post post-' . (int) $GLOBALS['post']['id'] . '"'; }
function apply_filters( $hook, $value ) { return $value; } // wpautop etc. not simulated.
function wp_kses_post( $v ) { return (string) $v; }
function wp_reset_postdata() { $GLOBALS['RESET_CALLED'] = true; }
function add_query_arg( $k, $v ) { return '?' . $k . '=' . $v; }
function paginate_links( array $a ) {
	if ( $a['total'] < 2 ) { return ''; }
	$out = array();
	for ( $n = 1; $n <= $a['total']; $n++ ) {
		$url = str_replace( '%#%', $n, $a['base'] );
		if ( $a['add_args'] ) { foreach ( $a['add_args'] as $k => $v ) { $url .= "&$k=$v"; } }
		$out[] = $n === $a['current'] ? "<span aria-current=\"page\">$n</span>" : "<a href=\"$url\">$n</a>";
	}
	return implode( ' ', $out );
}
