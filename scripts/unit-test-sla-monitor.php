<?php
/**
 * Unit Tests for SlaMonitor Service
 *
 * Tests SLA tracking, calculation, and alerting
 *
 * @package PearTree_Pro
 */

declare( strict_types=1 );

// Mock WordPress functions for unit testing
if ( ! function_exists( 'get_option' ) ) {
	$mockOptions = [];
	function get_option( $option, $default = false ) {
		global $mockOptions;
		return $mockOptions[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $mockOptions;
		$mockOptions[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $mockOptions;
		unset( $mockOptions[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	$triggeredAlerts = [];
	function do_action( $hook, ...$args ) {
		global $triggeredAlerts;
		if ( $hook === 'peartree_sla_alert' ) {
			$triggeredAlerts[] = [
				'partner_id' => $args[0] ?? null,
				'alert_type' => $args[1] ?? null,
				'message'    => $args[2] ?? null,
			];
		}
	}
}

// Load SlaMonitor class
require_once __DIR__ . '/../poradnik.pro/inc/SlaMonitor.php';

use PearTree_Pro\SlaMonitor;

// ============================================================================
// TEST SUITE: SlaMonitor Unit Contracts
// ============================================================================

class SlaMonitorTestRunner {
	private $tests = [];
	private $passed = 0;
	private $failed = 0;

	public function addTest( $name, callable $fn ) {
		$this->tests[] = [ 'name' => $name, 'fn' => $fn ];
	}

	public function run() {
		echo "Running SlaMonitor Unit Tests\n";
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

$runner = new SlaMonitorTestRunner();

// ============================================================================
// CONTRACT 1: Record successful response
// ============================================================================
$runner->addTest( 'Record successful response (200)', function () {
	global $mockOptions;
	$mockOptions = [];

	$result = SlaMonitor::recordPartnerResponse( 'partner-1', 200, 500 );

	assert( $result['total_requests'] === 1, 'Expected 1 total request' );
	assert( $result['successful_requests'] === 1, 'Expected 1 successful request' );
	assert( $result['response_time_ms'] === 500, 'Expected 500ms response time' );
	assert( empty( $result['last_error'] ), 'Expected no error for 200' );
} );

// ============================================================================
// CONTRACT 2: Record failed response
// ============================================================================
$runner->addTest( 'Record failed response (500)', function () {
	global $mockOptions;
	$mockOptions = [];

	$result = SlaMonitor::recordPartnerResponse( 'partner-1', 500, 2000 );

	assert( $result['total_requests'] === 1, 'Expected 1 total request' );
	assert( $result['failed_requests'] === 1, 'Expected 1 failed request' );
	assert( $result['last_error'] === 'HTTP 500', 'Expected error message' );
	assert( ! empty( $result['last_error_ts'] ), 'Expected error timestamp' );
} );

// ============================================================================
// CONTRACT 3: Calculate success rate
// ============================================================================
$runner->addTest( 'Calculate success rate (3/5 = 60%)', function () {
	global $mockOptions;
	$mockOptions = [];

	// Record 3 successes, 2 failures
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 500 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 500 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 500 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 500, 500 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 500, 500 );

	$metrics = SlaMonitor::getPartnerMetrics( 'partner-1' );
	$rate    = SlaMonitor::calculateSuccessRate( $metrics );

	assert( abs( $rate - 0.6 ) < 0.01, "Expected success rate 0.6, got {$rate}" );
} );

// ============================================================================
// CONTRACT 4: Calculate average response time
// ============================================================================
$runner->addTest( 'Calculate average response time (1000, 2000, 3000 = 2000)', function () {
	global $mockOptions;
	$mockOptions = [];

	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 1000 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 2000 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 3000 );

	$metrics = SlaMonitor::getPartnerMetrics( 'partner-1' );
	$avg     = SlaMonitor::calculateAverageResponseTime( $metrics );

	assert( $avg === 2000, "Expected avg 2000ms, got {$avg}" );
} );

// ============================================================================
// CONTRACT 5: Trigger alert for low success rate
// ============================================================================
$runner->addTest( 'Trigger alert for low success rate (<95%)', function () {
	global $mockOptions, $triggeredAlerts;
	$mockOptions = [];
	$triggeredAlerts = [];

	// Record 1 success, 9 failures = 10% success rate (below 95%)
	for ( $i = 0; $i < 9; $i++ ) {
		SlaMonitor::recordPartnerResponse( 'partner-1', 500, 500 );
	}
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 500 );

	// Should have triggered alert
	$alerts = array_filter( $triggeredAlerts, fn( $a ) => $a['alert_type'] === 'LOW_SUCCESS_RATE' );
	assert( count( $alerts ) > 0, 'Expected LOW_SUCCESS_RATE alert' );
} );

// ============================================================================
// CONTRACT 6: Trigger alert for high response time
// ============================================================================
$runner->addTest( 'Trigger alert for high response time (>5000ms)', function () {
	global $mockOptions, $triggeredAlerts;
	$mockOptions = [];
	$triggeredAlerts = [];

	// Record 3 slow responses (average > 5000ms)
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 6000 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 6000 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 6000 );

	$alerts = array_filter( $triggeredAlerts, fn( $a ) => $a['alert_type'] === 'HIGH_RESPONSE_TIME' );
	assert( count( $alerts ) > 0, 'Expected HIGH_RESPONSE_TIME alert' );
} );

// ============================================================================
// CONTRACT 7: Trigger alert for complete failure
// ============================================================================
$runner->addTest( 'Trigger alert for complete failure (0% success)', function () {
	global $mockOptions, $triggeredAlerts;
	$mockOptions = [];
	$triggeredAlerts = [];

	// Record 3 failures = complete failure
	SlaMonitor::recordPartnerResponse( 'partner-1', 500, 500 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 503, 500 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 504, 500 );

	$alerts = array_filter( $triggeredAlerts, fn( $a ) => $a['alert_type'] === 'COMPLETE_FAILURE' );
	assert( count( $alerts ) > 0, 'Expected COMPLETE_FAILURE alert' );
} );

// ============================================================================
// CONTRACT 8: Persist and retrieve metrics
// ============================================================================
$runner->addTest( 'Persist and retrieve partner metrics', function () {
	global $mockOptions;
	$mockOptions = [];

	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 500 );
	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 600 );

	$metrics = SlaMonitor::getPartnerMetrics( 'partner-1' );

	assert( ! empty( $metrics ), 'Expected metrics to be retrieved' );
	assert( $metrics['total_requests'] === 2, 'Expected 2 total requests' );
	assert( $metrics['successful_requests'] === 2, 'Expected 2 successful requests' );
} );

// ============================================================================
// CONTRACT 9: Empty metrics return 1.0 success rate
// ============================================================================
$runner->addTest( 'Empty metrics return 1.0 success rate', function () {
	$rate = SlaMonitor::calculateSuccessRate( [] );
	assert( $rate === 1.0, "Expected 1.0 for empty metrics, got {$rate}" );
} );

// ============================================================================
// CONTRACT 10: Reset metrics clears options
// ============================================================================
$runner->addTest( 'Reset metrics clears partner data', function () {
	global $mockOptions;
	$mockOptions = [];

	SlaMonitor::recordPartnerResponse( 'partner-1', 200, 500 );
	assert( ! empty( $mockOptions['peartree_sla_partner-1'] ), 'Expected metrics before reset' );

	SlaMonitor::resetMetrics( 'partner-1' );
	assert( empty( $mockOptions['peartree_sla_partner-1'] ?? null ), 'Expected metrics cleared after reset' );
} );

// ============================================================================
// RUN TESTS
// ============================================================================
$runner->run();
