<?php
// Usage: php render.php <template.php> '<json $_GET>' <dataset>
// Renders a template against the WP stubs and prints the HTML.
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
require __DIR__ . '/wp-stubs.php';

$_GET = json_decode( $argv[2], true ) ?: array();
$posts = array();
switch ( $argv[3] ) {
	case 'ads': // One post with three markers, one with two, plus a lookalike that must NOT change.
		$posts[] = array( 'id' => 1, 'title' => 'Ads post A', 'content' => "Intro.\n[ad]\nMiddle about data.\n[ad]\nMore.\n[ad]\nEnd." );
		$posts[] = array( 'id' => 2, 'title' => 'Ads post B', 'content' => "Start [ad] then [ad]. Not markers: [AD], [ad 3], ad, a, d." );
		break;
	default: // 25 posts -> 3 pages of 10.
		for ( $i = 1; $i <= 25; $i++ ) {
			$posts[] = array( 'id' => $i, 'title' => "Post $i" . ( 0 === $i % 5 ? ' about Lagos' : '' ), 'content' => "Body of post $i." );
		}
}
$GLOBALS['TEST_POSTS'] = $posts;
include $argv[1];
