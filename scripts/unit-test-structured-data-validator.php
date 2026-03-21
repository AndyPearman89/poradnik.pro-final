<?php
/**
 * Unit Tests for StructuredDataValidator
 *
 * Tests schema.org JSON-LD validation
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

// Load validator
require_once __DIR__ . '/../poradnik.pro/inc/StructuredDataValidator.php';

use PoradnikPro\StructuredDataValidator;

// ============================================================================
// TEST SUITE
// ============================================================================

class StructuredDataValidatorTestRunner {
	private $tests = [];
	private $passed = 0;
	private $failed = 0;

	public function addTest( $name, callable $fn ) {
		$this->tests[] = [ 'name' => $name, 'fn' => $fn ];
	}

	public function run() {
		echo "StructuredDataValidator Unit Tests\n";
		echo "====================================\n\n";

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

		echo "\n====================================\n";
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

$runner = new StructuredDataValidatorTestRunner();

// ============================================================================
// CONTRACT 1: Validate correct Article schema
// ============================================================================
$runner->addTest( 'Validate correct Article schema', function () {
	$schema = [
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'headline'      => 'Test Article',
		'url'           => 'http://example.com/article',
		'datePublished' => '2024-01-15T10:30:00Z',
		'dateModified'  => '2024-01-16T14:45:00Z',
	];

	$result = StructuredDataValidator::validate( $schema );
	assert( $result['valid'] === true, 'Expected valid Article schema' );
	assert( empty( $result['errors'] ), 'Expected no errors: ' . \json_encode( $result['errors'] ) );
} );

// ============================================================================
// CONTRACT 2: Reject schema with missing @context
// ============================================================================
$runner->addTest( 'Reject schema with missing @context', function () {
	$schema = [
		'@type'    => 'Article',
		'headline' => 'Test',
		'url'      => 'http://example.com',
	];

	$result = StructuredDataValidator::validate( $schema );
	assert( $result['valid'] === false, 'Expected invalid schema' );
	assert( ! empty( $result['errors'] ), 'Expected error messages' );
	assert( in_array( 'Missing @context', $result['errors'] ), 'Expected @context error' );
} );

// ============================================================================
// CONTRACT 3: Reject schema with missing required properties
// ============================================================================
$runner->addTest( 'Reject Article schema with missing headline', function () {
	$schema = [
		'@context' => 'https://schema.org',
		'@type'    => 'Article',
		'url'      => 'http://example.com',
	];

	$result = StructuredDataValidator::validate( $schema );
	assert( $result['valid'] === false, 'Expected invalid schema' );
	assert( ! empty( $result['errors'] ), 'Expected error for missing headline' );
} );

// ============================================================================
// CONTRACT 4: Validate LocalBusiness schema
// ============================================================================
$runner->addTest( 'Validate LocalBusiness schema with areaServed', function () {
	$schema = [
		'@context'   => 'https://schema.org',
		'@type'      => 'LocalBusiness',
		'name'       => 'Test Business',
		'url'        => 'http://example.com',
		'areaServed' => [
			'@type' => 'City',
			'name'  => 'Warszawa',
		],
	];

	$result = StructuredDataValidator::validate( $schema );
	assert( $result['valid'] === true, 'Expected valid LocalBusiness schema' );
} );

// ============================================================================
// CONTRACT 5: Reject LocalBusiness with invalid areaServed
// ============================================================================
$runner->addTest( 'Reject LocalBusiness with incomplete areaServed', function () {
	$schema = [
		'@context'   => 'https://schema.org',
		'@type'      => 'LocalBusiness',
		'name'       => 'Test Business',
		'url'        => 'http://example.com',
		'areaServed' => [
			'@type' => 'City',
		],
	];

	$result = StructuredDataValidator::validate( $schema );
	assert( $result['valid'] === false, 'Expected invalid areaServed' );
	assert( ! empty( $result['errors'] ), 'Expected error messages' );
} );

// ============================================================================
// CONTRACT 6: Extract JSON-LD from HTML
// ============================================================================
$runner->addTest( 'Extract JSON-LD schema from HTML', function () {
	$html = '<html><body>' .
		'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Test"}</script>' .
		'</body></html>';

	$schemas = StructuredDataValidator::extractSchemasFromHtml( $html );
	assert( count( $schemas ) === 1, 'Expected 1 schema extracted' );
	assert( $schemas[0]['@type'] === 'Article', 'Expected Article schema' );
	assert( $schemas[0]['headline'] === 'Test', 'Expected headline property' );
} );

// ============================================================================
// CONTRACT 7: Extract multiple schemas from HTML
// ============================================================================
$runner->addTest( 'Extract multiple JSON-LD schemas from HTML', function () {
	$html = '<body>' .
		'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Article 1","url":"http://test.com"}</script>' .
		'<script type="application/ld+json">{"@context":"https://schema.org","@type":"LocalBusiness","name":"Business","url":"http://test.com","areaServed":{"@type":"City","name":"Warszawa"}}</script>' .
		'</body>';

	$schemas = StructuredDataValidator::extractSchemasFromHtml( $html );
	assert( count( $schemas ) === 2, "Expected 2 schemas, got " . count( $schemas ) );
	assert( $schemas[0]['@type'] === 'Article', 'Expected first schema is Article' );
	assert( $schemas[1]['@type'] === 'LocalBusiness', 'Expected second schema is LocalBusiness' );
} );

// ============================================================================
// CONTRACT 8: Validate HTML with valid schemas
// ============================================================================
$runner->addTest( 'Validate HTML content (all valid schemas)', function () {
	$html = '<body><script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Test","url":"http://test.com"}</script></body>';

	$result = StructuredDataValidator::validateHtml( $html );
	assert( $result['schemas_found'] === 1, 'Expected 1 schema found' );
	assert( $result['all_valid'] === true, 'Expected all schemas valid' );
} );

// ============================================================================
// CONTRACT 9: Validate HTML with invalid schemas
// ============================================================================
$runner->addTest( 'Validate HTML content (invalid schemas)', function () {
	$html = '<body><script type="application/ld+json">{"@type":"Article"}</script></body>';

	$result = StructuredDataValidator::validateHtml( $html );
	assert( $result['schemas_found'] === 1, 'Expected 1 schema found' );
	assert( $result['all_valid'] === false, 'Expected invalid schema' );
	assert( ! empty( $result['results'][0]['errors'] ), 'Expected error details' );
} );

// ============================================================================
// CONTRACT 10: Check for valid schema presence
// ============================================================================
$runner->addTest( 'hasValidSchema returns true for valid content', function () {
	$html = '<body><script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Test","url":"http://test.com"}</script></body>';

	$has_valid = StructuredDataValidator::hasValidSchema( $html );
	assert( $has_valid === true, 'Expected hasValidSchema to return true' );
} );

// ============================================================================
// CONTRACT 11: Validate ISO 8601 dates in Article schema
// ============================================================================
$runner->addTest( 'Validate ISO 8601 date formats in Article', function () {
	$schema = [
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'headline'      => 'Test',
		'url'           => 'http://test.com',
		'datePublished' => '2024-01-15T10:30:00Z',
		'dateModified'  => '2024-01-16T14:45:00Z',
	];

	$result = StructuredDataValidator::validateArticleSchema( $schema );
	assert( $result['valid'] === true, 'Expected valid Article with ISO 8601 dates' );
} );

// ============================================================================
// CONTRACT 12: Reject invalid ISO 8601 dates
// ============================================================================
$runner->addTest( 'Reject invalid ISO 8601 date formats', function () {
	$schema = [
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'headline'      => 'Test',
		'url'           => 'http://test.com',
		'datePublished' => '01/15/2024', // Wrong format
	];

	$result = StructuredDataValidator::validateArticleSchema( $schema );
	assert( $result['valid'] === false, 'Expected invalid date format error' );
	assert( ! empty( $result['errors'] ), 'Expected error messages' );
} );

// ============================================================================
// Run tests
// ============================================================================
$runner->run();
