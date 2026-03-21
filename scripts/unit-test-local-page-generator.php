<?php
/**
 * Unit Tests for LocalPageGenerator
 *
 * Tests local page URL generation, parsing, canonicalization,
 * and SEO metadata/schema generation
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

// Mock WordPress functions
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://localhost:8080' . ( empty( $path ) ? '' : '/' . ltrim( $path, '/' ) );
	}
}

if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $file = '' ) {
		return 'http://localhost:8080/wp-content/themes/poradnik.pro/' . ltrim( $file, '/' );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'Poradnik.pro';
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( str_replace( ' ', '-', trim( $title ) ) );
	}
}

if ( ! function_exists( 'ucwords' ) ) {
	function ucwords( $str ) {
		return \ucwords( $str );
	}
}

if ( ! function_exists( 'sprintf' ) ) {
	function sprintf( ...$args ) {
		return \sprintf( ...$args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	function str_starts_with( $haystack, $needle ) {
		return strpos( $haystack, $needle ) === 0;
	}
}

// Load LocalPageGenerator
require_once __DIR__ . '/../poradnik.pro/inc/LocalPageGenerator.php';

use PoradnikPro\LocalPageGenerator;

// ============================================================================
// TEST SUITE
// ============================================================================

class LocalPageGeneratorTestRunner {
	private $tests = [];
	private $passed = 0;
	private $failed = 0;

	public function addTest( $name, callable $fn ) {
		$this->tests[] = [ 'name' => $name, 'fn' => $fn ];
	}

	public function run() {
		echo "LocalPageGenerator Unit Tests\n";
		echo "==============================\n\n";

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

		echo "\n==============================\n";
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

$runner = new LocalPageGeneratorTestRunner();

// ============================================================================
// CONTRACT 1: Generate local page URL from service and location
// ============================================================================
$runner->addTest( 'Generate local page URL (prawo-rodzinne + Warszawa)', function () {
	$url = LocalPageGenerator::generateLocalPageUrl( 'prawo-rodzinne', 'Warszawa' );
	assert( $url === 'http://localhost:8080/uslugi/prawo-rodzinne-warszawa/', "Expected /uslugi/prawo-rodzinne-warszawa/, got {$url}" );
} );

// ============================================================================
// CONTRACT 2: Parse local page URL back to service and location
// ============================================================================
$runner->addTest( 'Parse local page URL to extract service and location', function () {
	$url = 'http://localhost:8080/uslugi/prawo-rodzinne-warszawa/';
	$parsed = LocalPageGenerator::parseLocalPageUrl( $url );
	assert( $parsed['service'] === 'prawo-rodzinne', "Expected service 'prawo-rodzinne', got {$parsed['service']}" );
	assert( $parsed['location'] === 'warszawa', "Expected location 'warszawa', got {$parsed['location']}" );
} );

// ============================================================================
// CONTRACT 3: Non-local URL parsing returns empty
// ============================================================================
$runner->addTest( 'Parse non-local URL returns empty array', function () {
	$url = 'http://localhost:8080/blog/some-article/';
	$parsed = LocalPageGenerator::parseLocalPageUrl( $url );
	assert( empty( $parsed ), "Expected empty array for non-local URL, got " . \json_encode( $parsed ) );
} );

// ============================================================================
// CONTRACT 4: Generate canonical URL
// ============================================================================
$runner->addTest( 'Generate canonical URL (normalized form)', function () {
	$canonical = LocalPageGenerator::getCanonicalUrl( 'prawo-rodzinne', 'Warszawa' );
	assert( $canonical === 'http://localhost:8080/uslugi/prawo-rodzinne-warszawa/', "Expected normalized canonical, got {$canonical}" );
} );

// ============================================================================
// CONTRACT 5: Generate meta description
// ============================================================================
$runner->addTest( 'Generate meta description for local page', function () {
	$desc = LocalPageGenerator::generateMetaDescription( 'prawo-rodzinne', 'Warszawa' );
	assert( strpos( $desc, 'Prawo Rodzinne' ) !== false, "Expected 'Prawo Rodzinne' in description" );
	assert( strpos( $desc, 'Warszawa' ) !== false, "Expected 'Warszawa' in description" );
	assert( strlen( $desc ) > 50 && strlen( $desc ) < 200, "Expected meta description length 50-200 chars" );
} );

// ============================================================================
// CONTRACT 6: Generate LocalBusiness schema
// ============================================================================
$runner->addTest( 'Generate LocalBusiness schema with service and location', function () {
	$schema = LocalPageGenerator::generateLocalBusinessSchema( 'prawo-rodzinne', 'Warszawa' );
	assert( $schema['@type'] === 'LocalBusiness', "Expected @type=LocalBusiness" );
	assert( $schema['@context'] === 'https://schema.org', "Expected schema.org @context" );
	assert( strpos( $schema['name'], 'Prawo Rodzinne' ) !== false, "Expected service name in schema" );
	assert( strpos( $schema['name'], 'Warszawa' ) !== false, "Expected location name in schema" );
	assert( ! empty( $schema['url'] ), "Expected url in schema" );
	assert( ! empty( $schema['areaServed'] ), "Expected areaServed in schema" );
	assert( $schema['areaServed']['@type'] === 'City', "Expected City type for areaServed" );
} );

// ============================================================================
// CONTRACT 7: Generate LocalBusiness schema with extras (phone, address, price)
// ============================================================================
$runner->addTest( 'Generate LocalBusiness schema with phone and address extras', function () {
	$schema = LocalPageGenerator::generateLocalBusinessSchema( 'prawo-rodzinne', 'Warszawa', [
		'phone'        => '+48123456789',
		'address'      => [
			'street'      => 'ul. Nowy Świat 10',
			'city'        => 'Warszawa',
			'postal_code' => '00-368',
		],
		'price_range'  => '$$$',
	] );
	assert( $schema['telephone'] === '+48123456789', "Expected phone in schema" );
	assert( ! empty( $schema['address'] ), "Expected address in schema" );
	assert( $schema['address']['streetAddress'] === 'ul. Nowy Świat 10', "Expected street address" );
	assert( $schema['address']['addressCountry'] === 'PL', "Expected Poland as country" );
	assert( $schema['priceRange'] === '$$$', "Expected price range in schema" );
} );

// ============================================================================
// CONTRACT 8: Generate meta title
// ============================================================================
$runner->addTest( 'Generate meta title for local page', function () {
	$title = LocalPageGenerator::generateMetaTitle( 'prawo-rodzinne', 'Warszawa' );
	assert( strpos( $title, 'Prawo Rodzinne' ) !== false, "Expected service name in title" );
	assert( strpos( $title, 'Warszawa' ) !== false, "Expected location in title" );
	assert( strpos( $title, 'Poradnik.pro' ) !== false, "Expected site name in title" );
} );

// ============================================================================
// CONTRACT 9: Generate OpenGraph metadata
// ============================================================================
$runner->addTest( 'Generate OpenGraph metadata for local page', function () {
	$og = LocalPageGenerator::generateOpenGraph( 'prawo-rodzinne', 'Warszawa' );
	assert( ! empty( $og['og:title'] ), "Expected og:title" );
	assert( ! empty( $og['og:description'] ), "Expected og:description" );
	assert( ! empty( $og['og:url'] ), "Expected og:url" );
	assert( $og['og:type'] === 'website', "Expected og:type=website" );
	assert( ! empty( $og['og:image'] ), "Expected og:image" );
} );

// ============================================================================
// CONTRACT 10: Get internal linking context
// ============================================================================
$runner->addTest( 'Get internal linking context (related pages)', function () {
	$context = LocalPageGenerator::getInternalLinkContext( 'prawo-rodzinne', 'Warszawa' );
	assert( ! empty( $context['related_locations'] ), "Expected related_locations array" );
	assert( count( $context['related_locations'] ) >= 2, "Expected multiple related locations" );
	assert( is_array( $context['related_guides'] ), "Expected related_guides array" );
	assert( is_array( $context['related_specialists'] ), "Expected related_specialists array" );
} );

// ============================================================================
// Run tests
// ============================================================================
$runner->run();
