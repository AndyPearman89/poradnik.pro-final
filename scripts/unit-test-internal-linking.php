<?php
/**
 * Unit Tests for InternalLinkingController
 *
 * Tests link hierarchy management and depth control
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

// Load services
require_once __DIR__ . '/../poradnik.pro/inc/InternalLinkingController.php';
require_once __DIR__ . '/../poradnik.pro/inc/LocalPageGenerator.php';

use PoradnikPro\InternalLinkingController;
use PoradnikPro\LocalPageGenerator;

// Mock WordPress functions if not available
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://example.com/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'Poradnik Pro';
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( $title );
		$title = preg_replace( '/[^a-z0-9\s-]/', '', $title );
		$title = preg_replace( '/\s+/', '-', trim( $title ) );
		$title = preg_replace( '/-+/', '-', $title );
		return $title;
	}
}

if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $path = '' ) {
		return 'http://example.com/wp-content/themes/poradnik-pro/' . ltrim( $path, '/' );
	}
}

// ============================================================================
// TEST SUITE
// ============================================================================

class InternalLinkingControllerTestRunner {
	private $tests = [];
	private $passed = 0;
	private $failed = 0;

	public function addTest( $name, callable $fn ) {
		$this->tests[] = [ 'name' => $name, 'fn' => $fn ];
	}

	public function run() {
		echo "InternalLinkingController Unit Tests\n";
		echo "=====================================\n\n";

		foreach ( $this->tests as $test ) {
			try {
				call_user_func( $test['fn'] );
				echo "✓ {$test['name']}\n";
				$this->passed++;
			} catch ( Exception $e ) {
				echo "✗ {$test['name']}\n";
				echo "  Error: {$e->getMessage()}\n";
				$this->failed++;
			}
		}

		echo "\n=====================================\n";
		$total = $this->passed + $this->failed;
		echo "Results: {$this->passed}/{$total} passed\n";
		if ( $this->failed > 0 ) {
			echo "FAILED: {$this->failed} test(s) failed\n";
			exit( 1 );
		}
		echo "Overall: PASS\n";
		exit( 0 );
	}
}

$runner = new InternalLinkingControllerTestRunner();

// ============================================================================
// CONTRACT 1: Get internal links for guide pages
// ============================================================================
$runner->addTest( 'Get internal links for guide pages', function () {
	$links = InternalLinkingController::getInternalLinks( 'guide_type', [
		'category' => 'prawo-rodzinne',
		'tags'     => [ 'custody', 'divorce' ],
	] );

	assert( isset( $links['related_guides'] ), 'Expected related_guides' );
	assert( isset( $links['rankings'] ), 'Expected rankings' );
	assert( isset( $links['depth'] ), 'Expected depth' );
	assert( $links['depth'] === 1, 'Expected depth 1 for guides' );
} );

// ============================================================================
// CONTRACT 2: Get internal links for ranking pages
// ============================================================================
$runner->addTest( 'Get internal links for ranking pages', function () {
	$links = InternalLinkingController::getInternalLinks( 'ranking_type', [
		'category' => 'legal-services',
		'service'  => 'prawo-rodzinne',
	] );

	assert( isset( $links['guides'] ), 'Expected guides' );
	assert( isset( $links['local_pages'] ), 'Expected local_pages' );
	assert( isset( $links['related_rankings'] ), 'Expected related_rankings' );
	assert( $links['depth'] === 2, 'Expected depth 2 for rankings' );
} );

// ============================================================================
// CONTRACT 3: Get internal links for local pages
// ============================================================================
$runner->addTest( 'Get internal links for local pages', function () {
	$links = InternalLinkingController::getInternalLinks( 'local_page', [
		'service'  => 'prawo-rodzinne',
		'location' => 'warszawa',
	] );

	assert( isset( $links['related_locations'] ), 'Expected related_locations' );
	assert( isset( $links['guides'] ), 'Expected guides' );
	assert( $links['depth'] === 3, 'Expected depth 3 for local pages' );
} );

// ============================================================================
// CONTRACT 4: Related locations for local pages
// ============================================================================
$runner->addTest( 'Get related locations for service', function () {
	$links = InternalLinkingController::getInternalLinks( 'local_page', [
		'service' => 'prawo-rodzinne',
	] );

	$locations = $links['related_locations'];
	assert( count( $locations ) > 0, 'Expected related locations' );
	assert( $locations[0]['type'] === 'local_page', 'Expected local_page type' );
	assert( strpos( $locations[0]['url'], 'uslugi/' ) !== false, 'Expected uslugi URL pattern' );
} );

// ============================================================================
// CONTRACT 5: Validate link depth prevents circular linking
// ============================================================================
$runner->addTest( 'Validate link depth prevents circular linking', function () {
	$same_url = 'http://example.com/page';
	$valid = InternalLinkingController::validateLinkDepth( $same_url, $same_url );
	assert( $valid === false, 'Expected circular link validation to fail' );

	$different_url = 'http://example.com/other';
	$valid = InternalLinkingController::validateLinkDepth( $same_url, $different_url );
	assert( $valid === true, 'Expected different URL to pass validation' );
} );

// ============================================================================
// CONTRACT 6: Score links prioritizes conversion (local pages)
// ============================================================================
$runner->addTest( 'Score links prioritizes local pages', function () {
	$candidates = [
		[ 'url' => 'http://example.com/guide', 'type' => 'guide_type', 'title' => 'Guide' ],
		[ 'url' => 'http://example.com/local', 'type' => 'local_page', 'title' => 'Local' ],
		[ 'url' => 'http://example.com/ranking', 'type' => 'ranking_type', 'title' => 'Ranking' ],
	];

	$scored = InternalLinkingController::scoreLinks( 'ranking_type', $candidates );
	assert( $scored[0]['type'] === 'local_page', 'Expected local_page first (highest score)' );
} );

// ============================================================================
// CONTRACT 7: Get anchor text suggestions for guide pages
// ============================================================================
$runner->addTest( 'Get anchor text suggestions for guides', function () {
	$anchors = InternalLinkingController::getAnchorTextSuggestions( 'guide_type', [
		'category' => 'prawo-rodzinne',
	] );

	assert( isset( $anchors['read-more'] ), 'Expected read-more anchor' );
	assert( isset( $anchors['more-info'] ), 'Expected more-info anchor' );
	assert( ! empty( $anchors['read-more'] ), 'Expected non-empty read-more text' );
} );

// ============================================================================
// CONTRACT 8: Get anchor text suggestions for ranking pages
// ============================================================================
$runner->addTest( 'Get anchor text suggestions for rankings', function () {
	$anchors = InternalLinkingController::getAnchorTextSuggestions( 'ranking_type', [
		'service' => 'prawo-rodzinne',
	] );

	assert( isset( $anchors['top-choice'] ), 'Expected top-choice anchor' );
	assert( isset( $anchors['best-of'] ), 'Expected best-of anchor' );
	assert( strpos( $anchors['best-of'], 'prawo' ) !== false, 'Expected service in anchor text' );
} );

// ============================================================================
// CONTRACT 9: Get anchor text suggestions for local pages
// ============================================================================
$runner->addTest( 'Get anchor text suggestions for local pages', function () {
	$anchors = InternalLinkingController::getAnchorTextSuggestions( 'local_page', [
		'service'  => 'prawo-rodzinne',
		'location' => 'warszawa',
	] );

	assert( isset( $anchors['location'] ), 'Expected location anchor' );
	assert( isset( $anchors['specialists'] ), 'Expected specialists anchor' );
	assert( strpos( $anchors['location'], 'warszawa' ) !== false, 'Expected location in anchor' );
} );

// ============================================================================
// CONTRACT 10: MAX_LINK_DEPTH constant is set
// ============================================================================
$runner->addTest( 'MAX_LINK_DEPTH constant is configured', function () {
	// Check if constant is accessible by testing its effects
	$guide_links = InternalLinkingController::getInternalLinks( 'guide_type', [] );
	assert( isset( $guide_links['depth'] ), 'Expected depth key in response' );
	assert( $guide_links['depth'] >= 1, 'Expected minimum depth 1' );
} );

// ============================================================================
// CONTRACT 11: Link depth is respected in linking context
// ============================================================================
$runner->addTest( 'Link depth is respected in results', function () {
	$guide_links    = InternalLinkingController::getInternalLinks( 'guide_type', [ 'category' => 'legal' ] );
	$ranking_links  = InternalLinkingController::getInternalLinks( 'ranking_type', [ 'service' => 'legal' ] );
	$local_links    = InternalLinkingController::getInternalLinks( 'local_page', [ 'service' => 'legal' ] );

	assert( $guide_links['depth'] === 1, 'Expected guide depth 1' );
	assert( $ranking_links['depth'] === 2, 'Expected ranking depth 2' );
	assert( $local_links['depth'] === 3, 'Expected local page depth 3' );

	// Each level depth should be higher than previous
	assert( $ranking_links['depth'] > $guide_links['depth'], 'Expected ranking > guide depth' );
	assert( $local_links['depth'] > $ranking_links['depth'], 'Expected local > ranking depth' );
} );

// ============================================================================
// CONTRACT 12: Link strategy descriptions included
// ============================================================================
$runner->addTest( 'Link strategy descriptions are included', function () {
	$guide_links   = InternalLinkingController::getInternalLinks( 'guide_type', [] );
	$ranking_links = InternalLinkingController::getInternalLinks( 'ranking_type', [] );
	$local_links   = InternalLinkingController::getInternalLinks( 'local_page', [] );

	assert( isset( $guide_links['depth_strategy'] ), 'Expected depth_strategy in guide links' );
	assert( isset( $ranking_links['depth_strategy'] ), 'Expected depth_strategy in ranking links' );
	assert( isset( $local_links['depth_strategy'] ), 'Expected depth_strategy in local links' );

	assert( ! empty( $guide_links['depth_strategy'] ), 'Expected non-empty strategy' );
	assert( ! empty( $ranking_links['depth_strategy'] ), 'Expected non-empty strategy' );
	assert( ! empty( $local_links['depth_strategy'] ), 'Expected non-empty strategy' );
} );

// ============================================================================
// Run tests
// ============================================================================
$runner->run();
