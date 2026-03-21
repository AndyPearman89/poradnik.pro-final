<?php
/**
 * Unit Tests for TemplateRenderValidator
 *
 * Tests template structure and render validation
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

// Load validator
require_once __DIR__ . '/../poradnik.pro/inc/TemplateRenderValidator.php';

use PoradnikPro\TemplateRenderValidator;

// ============================================================================
// TEST SUITE
// ============================================================================

class TemplateRenderValidatorTestRunner {
	private $tests = [];
	private $passed = 0;
	private $failed = 0;

	public function addTest( $name, callable $fn ) {
		$this->tests[] = [ 'name' => $name, 'fn' => $fn ];
	}

	public function run() {
		echo "TemplateRenderValidator Unit Tests\n";
		echo "===================================\n\n";

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

		echo "\n===================================\n";
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

$runner = new TemplateRenderValidatorTestRunner();

// ============================================================================
// CONTRACT 1: Validate proper HTML structure
// ============================================================================
$runner->addTest( 'Validate proper HTML structure', function () {
	$html = '<html><head><title>Test</title></head><body><h1>Hello</h1></body></html>';

	$result = TemplateRenderValidator::validateHtmlStructure( $html );
	assert( $result['valid'] === true, 'Expected valid HTML structure' );
	assert( empty( $result['errors'] ), 'Expected no errors' );
} );

// ============================================================================
// CONTRACT 2: Reject invalid HTML structure
// ============================================================================
$runner->addTest( 'Reject invalid HTML structure', function () {
	$html = '<head><title>Test</title></head><div>No html tag</div>';

	$result = TemplateRenderValidator::validateHtmlStructure( $html );
	assert( $result['valid'] === false, 'Expected invalid structure' );
	assert( ! empty( $result['errors'] ), 'Expected error messages' );
} );

// ============================================================================
// CONTRACT 3: Validate page title
// ============================================================================
$runner->addTest( 'Validate page title', function () {
	$html = '<html><head><title>This is a Good Page Title for SEO</title></head><body></body></html>';

	$result = TemplateRenderValidator::validatePageTitle( $html );
	assert( ! empty( $result['title'] ), 'Expected title present' );
	assert( $result['present'] === true, 'Expected present flag' );
	assert( $result['length_ok'] === true, 'Expected length 30-60 characters' );
} );

// ============================================================================
// CONTRACT 4: Validate meta tags
// ============================================================================
$runner->addTest( 'Validate meta tags', function () {
	$html = '<head>' .
		'<meta charset="utf-8">' .
		'<meta name="viewport" content="width=device-width">' .
		'<meta name="description" content="Test page">' .
		'<meta property="og:title" content="Test">' .
		'<meta property="og:url" content="http://example.com">' .
		'</head>';

	$result = TemplateRenderValidator::validateMetaTags( $html );
	assert( $result['valid'] === true, 'Expected all required meta tags' );
	assert( $result['found']['viewport'] === true, 'Expected viewport meta' );
	assert( $result['found']['description'] === true, 'Expected description meta' );
} );

// ============================================================================
// CONTRACT 5: Check for CTA elements
// ============================================================================
$runner->addTest( 'Check for CTA elements', function () {
	$html = '<body>' .
		'<div class="lead-form">' .
		'<button type="submit">Contact Us</button>' .
		'</div>' .
		'</body>';

	$result = TemplateRenderValidator::validateCTA( $html );
	assert( $result['hasCTA'] === true, 'Expected CTA detected' );
	assert( $result['count'] > 0, 'Expected CTA count > 0' );
} );

// ============================================================================
// CONTRACT 6: Validate main content section
// ============================================================================
$runner->addTest( 'Validate main content section', function () {
	$html = '<body>' .
		'<main id="main">' .
		'<article><h1>Title</h1><p>Content</p></article>' .
		'</main>' .
		'</body>';

	$result = TemplateRenderValidator::validateMainContent( $html );
	assert( $result['valid'] === true, 'Expected valid main content' );
	assert( $result['hasMain'] === true, 'Expected main tag' );
	assert( $result['hasArticle'] === true, 'Expected article tag' );
} );

// ============================================================================
// CONTRACT 7: Check for JSON-LD schema
// ============================================================================
$runner->addTest( 'Check for JSON-LD schema', function () {
	$html = '<head><script type="application/ld+json">{"@context":"https://schema.org","@type":"Article"}</script></head>';

	$result = TemplateRenderValidator::validateSchemaMarkup( $html );
	assert( $result['hasSchema'] === true, 'Expected schema present' );
	assert( $result['schemaCount'] > 0, 'Expected schema count > 0' );
} );

// ============================================================================
// CONTRACT 8: Validate single-question template
// ============================================================================
$runner->addTest( 'Validate single-question template', function () {
	$html = '<html><head><title>Question Page</title>' .
		'<meta name="description" content="Q&A">' .
		'<script type="application/ld+json">{"@type":"QAPage"}</script>' .
		'</head><body>' .
		'<div class="question"><h1>What is this?</h1></div>' .
		'<div class="answer"><p>Answer text</p></div>' .
		'<a class="cta-link" href="/contact">Contact</a>' .
		'</body></html>';

	$result = TemplateRenderValidator::validateSingleQuestion( $html );
	assert( $result['valid'] === true, 'Expected valid question template' );
	assert( $result['hasQuestion'] === true, 'Expected question section' );
	assert( $result['hasAnswers'] === true, 'Expected answers section' );
	assert( $result['hasCTA'] === true, 'Expected CTA' );
	assert( $result['schemaPresent'] === true, 'Expected schema' );
} );

// ============================================================================
// CONTRACT 9: Validate archive-local template
// ============================================================================
$runner->addTest( 'Validate archive-local template', function () {
	$html = '<html><head><title>Local Services</title>' .
		'<meta name="description" content="Local listings">' .
		'<script type="application/ld+json">{"@type":"LocalBusiness"}</script>' .
		'</head><body>' .
		'<div class="page-title"><h1>Services in City</h1></div>' .
		'<div class="archive"><ul class="items">' .
		'<li>Item 1</li><li>Item 2</li>' .
		'</ul></div>' .
		'<a href="/contact" class="contact">Get Help</a>' .
		'</body></html>';

	$result = TemplateRenderValidator::validateArchiveLocal( $html );
	assert( $result['valid'] === true, 'Expected valid archive template' );
	assert( $result['hasListing'] === true, 'Expected listing section' );
	assert( $result['hasCTA'] === true, 'Expected CTA' );
	assert( $result['schemaPresent'] === true, 'Expected schema' );
} );

// ============================================================================
// CONTRACT 10: Validate single-ranking template
// ============================================================================
$runner->addTest( 'Validate single-ranking template', function () {
	$html = '<html><head><title>Best Products 2024</title>' .
		'<meta name="description" content="Product ranking">' .
		'<script type="application/ld+json">{"@type":"ItemList"}</script>' .
		'</head><body>' .
		'<h1 class="title">Best Products</h1>' .
		'<div class="featured"><div class="best">1. Product</div></div>' .
		'<ol class="ranking">' .
		'<li>Item 1</li><li>Item 2</li>' .
		'</ol>' .
		'<table><tr><td>Feature</td></tr></table>' .
		'<a href="/buy" class="cta-link">View Products</a>' .
		'</body></html>';

	$result = TemplateRenderValidator::validateSingleRanking( $html );
	assert( $result['valid'] === true, 'Expected valid ranking template' );
	assert( $result['hasRankingList'] === true, 'Expected ranking list' );
	assert( $result['hasCTA'] === true, 'Expected CTA' );
	assert( $result['hasComparisonTable'] === true, 'Expected comparison table' );
} );

// ============================================================================
// CONTRACT 11: Validate accessibility features
// ============================================================================
$runner->addTest( 'Validate accessibility features', function () {
	$html = '<html lang="pl"><head></head><body>' .
		'<img src="test.jpg" alt="Test image">' .
		'<label for="name">Name:</label><input id="name" type="text">' .
		'</body></html>';

	$result = TemplateRenderValidator::validateAccessibility( $html );
	assert( $result['valid'] === true, 'Expected no accessibility issues' );
	assert( empty( $result['issues'] ), 'Expected no issues: ' . json_encode( $result['issues'] ) );
} );

// ============================================================================
// CONTRACT 12: General render validation
// ============================================================================
$runner->addTest( 'General render validation', function () {
	$html = '<html><head>' .
		'<title>Test Page Title That Is Long Enough</title>' .
		'<meta charset="utf-8">' .
		'<meta name="description" content="Test">' .
		'</head><body>' .
		'<main><article><h1>Content</h1></article></main>' .
		'</body></html>';

	$result = TemplateRenderValidator::validateRender( $html );
	assert( $result['valid'] === true, 'Expected valid render' );
	assert( $result['structure']['valid'] === true, 'Expected valid structure' );
	assert( $result['title']['present'] === true, 'Expected title' );
	assert( $result['mainContent']['valid'] === true, 'Expected main content' );
} );

// ============================================================================
// Run tests
// ============================================================================
$runner->run();
