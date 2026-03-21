<?php
/**
 * Unit Tests for ThinContentGate
 *
 * Tests thin content detection and quality gating
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

// Load service
require_once __DIR__ . '/../poradnik.pro/inc/ThinContentGate.php';

use PoradnikPro\ThinContentGate;

// ============================================================================
// TEST SUITE
// ============================================================================

class ThinContentGateTestRunner {
	private $tests = [];
	private $passed = 0;
	private $failed = 0;

	public function addTest( $name, callable $fn ) {
		$this->tests[] = [ 'name' => $name, 'fn' => $fn ];
	}

	public function run() {
		echo "ThinContentGate Unit Tests\n";
		echo "==========================\n\n";

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

		echo "\n==========================\n";
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

$runner = new ThinContentGateTestRunner();

// ============================================================================
// CONTRACT 1: Detect thin content (very low word count)
// ============================================================================
$runner->addTest( 'Detect thin content (low word count)', function () {
	$html = '<html><body><p>Short content</p></body></html>';
	$result = ThinContentGate::assessContent( $html, 'guide' );

	assert( $result['risk_score'] >= 50, 'Expected high risk score for thin content' );
	assert( ! empty( $result['reasons'] ), 'Expected reasons provided' );
	assert( $result['word_count'] < 100, 'Expected low word count' );
} );

// ============================================================================
// CONTRACT 2: Accept quality guide content
// ============================================================================
$runner->addTest( 'Accept quality guide content', function () {
	$html = '<html><body>' .
		'<h1>Complete Guide</h1>' .
		'<p>This is a comprehensive guide covering multiple topics and providing detailed information.</p>' .
		'<h2>Section 1</h2>' .
		'<p>First section with lots of detailed information about the topic we are covering in great depth.</p>' .
		'<p>More paragraph content here to reach minimum word count for guides.</p>' .
		'<h2>Section 2</h2>' .
		'<p>Second section continuing the detailed coverage with multiple paragraphs.</p>' .
		'<p>Additional content here to ensure we meet quality standards for published guides.</p>' .
		'<h2>Conclusion</h2>' .
		'<p>Final section summarizing all the key points covered above in this comprehensive guide.</p>' .
		'</body></html>';
	
	$result = ThinContentGate::assessContent( $html, 'guide' );
	assert( $result['risk_score'] < 50, 'Expected low risk score for quality content' );
	assert( $result['word_count'] >= 200, 'Expected adequate word count' );
	assert( $result['heading_count'] >= 2, 'Expected multiple headings' );
} );

// ============================================================================
// CONTRACT 3: Detect rank with low word count
// ============================================================================
$runner->addTest( 'Flag ranking with insufficient content', function () {
	$html = '<html><body>' .
		'<h1>Best Products</h1>' .
		'<ol><li>Item 1</li><li>Item 2</li></ol>' .
		'</body></html>';
	
	$result = ThinContentGate::assessContent( $html, 'ranking' );
	assert( $result['risk_score'] > 50, 'Expected medium-high risk for thin ranking' );
} );

// ============================================================================
// CONTRACT 4: Accept local page with minimum content
// ============================================================================
$runner->addTest( 'Accept local page with minimum content', function () {
	$html = '<html><body>' .
		'<h1>Service in Location</h1>' .
		'<p>We provide excellent service in your city with professional specialists.</p>' .
		'<p>Contact us today for a consultation on our services in your location.</p>' .
		'<p>Our address and phone details are available below for your convenience.</p>' .
		'</body></html>';
	
	$result = ThinContentGate::assessContent( $html, 'local' );
	assert( $result['risk_score'] <= 50, 'Expected acceptable risk for local page' );
} );

// ============================================================================
// CONTRACT 5: Risk score 0-100 range
// ============================================================================
$runner->addTest( 'Risk score is within 0-100 range', function () {
	$html = '<html><body><p>Test</p></body></html>';
	$result = ThinContentGate::assessContent( $html, 'guide' );
	
	assert( $result['risk_score'] >= 0, 'Expected risk_score >= 0' );
	assert( $result['risk_score'] <= 100, 'Expected risk_score <= 100' );
} );

// ============================================================================
// CONTRACT 6: Classify risk levels correctly
// ============================================================================
$runner->addTest( 'Classify risk levels correctly', function () {
	assert( ThinContentGate::getRiskLevel( 80 ) === 'CRITICAL', 'Expected CRITICAL for 80' );
	assert( ThinContentGate::getRiskLevel( 60 ) === 'WARNING', 'Expected WARNING for 60' );
	assert( ThinContentGate::getRiskLevel( 30 ) === 'CAUTION', 'Expected CAUTION for 30' );
	assert( ThinContentGate::getRiskLevel( 10 ) === 'OK', 'Expected OK for 10' );
} );

// ============================================================================
// CONTRACT 7: Block publication for critical content
// ============================================================================
$runner->addTest( 'Block publication for critical content', function () {
	assert( ThinContentGate::shouldBlock( 80 ) === true, 'Expected block for score 80' );
	assert( ThinContentGate::shouldBlock( 75 ) === true, 'Expected block for score 75' );
	assert( ThinContentGate::shouldBlock( 50 ) === false, 'Expected no block for score 50' );
} );

// ============================================================================
// CONTRACT 8: Get recommendation for thin content
// ============================================================================
$runner->addTest( 'Get recommendation for thin content', function () {
	$html = '<p>Very short content</p>';
	$assessment = ThinContentGate::assessContent( $html, 'guide' );
	$rec = ThinContentGate::getRecommendation( $assessment );
	
	assert( isset( $rec['can_publish'] ), 'Expected can_publish key' );
	assert( ! empty( $rec['recommendation'] ), 'Expected recommendation text' );
	assert( ! empty( $rec['actions'] ), 'Expected actions array' );
} );

// ============================================================================
// CONTRACT 9: Generate full report
// ============================================================================
$runner->addTest( 'Generate full quality report', function () {
	$html = '<html><body>' .
		'<h1>Test Page</h1>' .
		'<p>Some content here with adequate length.</p>' .
		'<p>More content to improve the overall quality.</p>' .
		'<p>Final paragraph for the test.</p>' .
		'</body></html>';
	
	$assessment = ThinContentGate::assessContent( $html, 'guide' );
	$report = ThinContentGate::generateReport( $assessment );
	
	assert( isset( $report['assessment'] ), 'Expected assessment in report' );
	assert( isset( $report['recommendation'] ), 'Expected recommendation in report' );
	assert( isset( $report['report'] ), 'Expected report section' );
	assert( ! empty( $report['report']['metrics'] ), 'Expected metrics' );
} );

// ============================================================================
// CONTRACT 10: Detect missing guide structure
// ============================================================================
$runner->addTest( 'Detect missing guide structure', function () {
	// Guide with no subheadings
	$html = '<html><body>' .
		'<h1>Guide Title</h1>' .
		'<p>Lots and lots of content here without any H2 or H3 subheadings to break it up.</p>' .
		'<p>More content but still missing proper heading structure for a guide.</p>' .
		'<p>This should trigger a warning about guide structure.</p>' .
		'</body></html>';
	
	$result = ThinContentGate::assessContent( $html, 'guide' );
	assert( ! empty( $result['reasons'] ), 'Expected issues flagged' );
	assert( array_reduce( 
		$result['reasons'],
		fn( $found, $reason ) => $found || strpos( $reason, 'structure' ) !== false || strpos( $reason, 'heading' ) !== false,
		false
	), 'Expected structure issue in reasons' );
} );

// ============================================================================
// CONTRACT 11: Detect missing ranking structure
// ============================================================================
$runner->addTest( 'Detect missing ranking structure', function () {
	// Ranking without ordered list or table
	$html = '<html><body>' .
		'<h1>Best Products</h1>' .
		'<p>Item 1 - description</p>' .
		'<p>Item 2 - description</p>' .
		'<p>Item 3 - description</p>' .
		'</body></html>';
	
	$result = ThinContentGate::assessContent( $html, 'ranking' );
	assert( $result['risk_score'] > 40, 'Expected warning about ranking structure' );
} );

// ============================================================================
// CONTRACT 12: QA page assessment
// ============================================================================
$runner->addTest( 'Assess Q&A page content', function () {
	$html = '<html><body>' .
		'<div class="question"><h1>What is the best approach?</h1></div>' .
		'<div class="answer"><p>Answer paragraph one with detailed information.</p></div>' .
		'<div class="answer"><p>Answer paragraph two with more information.</p></div>' .
		'</body></html>';
	
	$result = ThinContentGate::assessContent( $html, 'qa' );
	assert( isset( $result['word_count'] ), 'Expected word count' );
	assert( $result['word_count'] > 0, 'Expected non-zero word count' );
} );

// ============================================================================
// Run tests
// ============================================================================
$runner->run();
