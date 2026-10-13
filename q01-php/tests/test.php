<?php
// Runs every Q1 check and writes the rendered HTML to ../evidence/.
$dir = dirname( __DIR__ );
$pass = 0; $fail = 0;
function render( $tpl, $get, $data ) {
	$cmd = sprintf( 'php %s %s %s %s 2>&1', escapeshellarg( __DIR__ . '/render.php' ), escapeshellarg( $tpl ), escapeshellarg( json_encode( $get ) ), escapeshellarg( $data ) );
	return shell_exec( $cmd );
}
function check( $label, $cond ) {
	global $pass, $fail;
	echo ( $cond ? 'PASS' : 'FAIL' ) . "  $label\n";
	$cond ? $pass++ : $fail++;
}
$tpl = "$dir/search-listing.php";
$scenarios = array(
	'missing-params' => array( array(), 'posts' ),
	'page-2'         => array( array( 'page' => '2' ), 'posts' ),
	'page-2-search'  => array( array( 'page' => '1', 's' => 'lagos' ), 'posts' ),
	'no-results'     => array( array( 's' => 'zzz-nothing' ), 'posts' ),
	'multiple-ads'   => array( array(), 'ads' ),
	'hostile-input'  => array( array( 'page' => '-3<script>', 's' => '<script>alert(1)</script>' ), 'posts' ),
	'page-too-high'  => array( array( 'page' => '99' ), 'posts' ),
);
$out = array();
foreach ( $scenarios as $name => [ $get, $data ] ) {
	$out[ $name ] = render( $tpl, $get, $data );
	file_put_contents( "$dir/evidence/$name.html", $out[ $name ] );
}
$noPhpErrors = static fn( $h ) => ! preg_match( '/(Warning|Notice|Deprecated|Fatal error|Uncaught)/', $h );

echo "== Missing parameters (?) ==\n";
$h = $out['missing-params'];
check( 'no PHP warnings/notices', $noPhpErrors( $h ) );
check( 'heading says Page 1 of 3, no search text', str_contains( $h, 'Page 1 of 3' ) && ! str_contains( $h, 'Search for' ) );
check( 'exactly 10 posts', 10 === substr_count( $h, '<article' ) );
check( 'first post is Post 1', str_contains( $h, '>Post 1<' ) );

echo "== Page 2 (?page=2) ==\n";
$h = $out['page-2'];
check( 'no PHP warnings/notices', $noPhpErrors( $h ) );
check( 'heading says Page 2 of 3', str_contains( $h, 'Page 2 of 3' ) );
check( '10 posts, Post 11 to Post 20', 10 === substr_count( $h, '<article' ) && str_contains( $h, '>Post 11<' ) && str_contains( $h, '>Post 20 about Lagos<' ) && ! str_contains( $h, '>Post 10<' ) );
check( 'pagination marks page 2 as current', str_contains( $h, '<span aria-current="page">2</span>' ) );

echo "== Search filter (?s=lagos) ==\n";
$h = $out['page-2-search'];
check( 'only the 5 matching posts', 5 === substr_count( $h, '<article' ) && 5 === substr_count( $h, 'about Lagos' ) );
check( 'heading shows search term and Page 1 of 1', str_contains( $h, 'Page 1 of 1: Search for “lagos”' ) );

echo "== No search results (?s=zzz-nothing) ==\n";
$h = $out['no-results'];
check( 'no PHP warnings/notices', $noPhpErrors( $h ) );
check( 'no articles, friendly message shown', 0 === substr_count( $h, '<article' ) && str_contains( $h, 'No posts found for “zzz-nothing”' ) );
check( 'heading still valid: Page 1 of 1', str_contains( $h, 'Page 1 of 1' ) );

echo "== Multiple [ad] markers ==\n";
$h = $out['multiple-ads'];
check( 'no PHP warnings/notices', $noPhpErrors( $h ) );
check( '5 ad divs across 2 posts', 5 === substr_count( $h, '<div class="ad ad-' ) );
check( 'slots numbered 1..5 uniquely across posts', str_contains( $h, 'ad-1"' ) && str_contains( $h, 'ad-5"' ) && ! str_contains( $h, 'ad-6"' ) );
check( 'no literal [ad] left', ! str_contains( $h, '[ad]' ) );
check( 'ordinary letters a/d untouched ("Middle about data")', str_contains( $h, 'Middle about data.' ) );
check( 'lookalikes [AD] and [ad 3] left alone', str_contains( $h, '[AD], [ad 3]' ) );
check( 'ad divs appear inside the article, not before the heading', strpos( $h, '<div class="ad' ) > strpos( $h, '<h2>' ) );

echo "== Hostile input ==\n";
$h = $out['hostile-input'];
check( 'no raw <script> in output', ! str_contains( $h, '<script>' ) );
check( 'negative/garbage page clamped to Page 1, no errors', $noPhpErrors( $h ) && str_contains( $h, 'Page 1 of 1' ) );

echo "== Page beyond the end (?page=99) ==\n";
$h = $out['page-too-high'];
check( 'shows no-results message instead of erroring', str_contains( $h, 'No posts found on this page.' ) && $noPhpErrors( $h ) );

echo "== Original code, for comparison ==\n";
$orig = render( "$dir/original.php", array(), 'posts' );
file_put_contents( "$dir/evidence/original-output.txt", $orig );
check( 'original fails with a fatal error (as described in ANSWER.md)', str_contains( $orig, 'Fatal error' ) );
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
