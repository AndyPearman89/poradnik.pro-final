<?php
/**
 * PearTree Pro SLA Monitoring Service
 *
 * Tracks partner endpoint SLAs and triggers alerts
 *
 * @package PearTree_Pro
 */

declare( strict_types=1 );

namespace PearTree_Pro;

/**
 * SlaMonitor: Track and monitor partner SLA metrics
 */
class SlaMonitor {

	const OPTION_PREFIX = 'peartree_sla_';
	const ALERT_SUCCESS_RATE_THRESHOLD = 0.95; // 95%
	const ALERT_RESPONSE_TIME_THRESHOLD = 5000; // 5 seconds in ms
	const METRICS_WINDOW_SECONDS = 86400; // 24 hours

	/**
	 * Record a partner request/response
	 *
	 * @param string $partner_id Partner ID.
	 * @param int    $response_code HTTP response code.
	 * @param int    $response_time_ms Response time in milliseconds.
	 * @return array Updated metrics.
	 */
	public static function recordPartnerResponse(
		string $partner_id,
		int $response_code,
		int $response_time_ms
	): array {
		if ( empty( $partner_id ) ) {
			return [];
		}

		$metrics = self::getPartnerMetrics( $partner_id );
		$now     = time();

		// Increment total attempts
		$metrics['total_requests'] = ( $metrics['total_requests'] ?? 0 ) + 1;
		$metrics['last_check_ts']  = $now;
		$metrics['response_time_ms'] = $response_time_ms;

		// Track success vs failure
		if ( $response_code >= 200 && $response_code < 300 ) {
			$metrics['successful_requests'] = ( $metrics['successful_requests'] ?? 0 ) + 1;
			$metrics['last_error']          = null;
			$metrics['last_error_ts']       = null;
		} else {
			$metrics['failed_requests'] = ( $metrics['failed_requests'] ?? 0 ) + 1;
			$metrics['last_error']      = "HTTP {$response_code}";
			$metrics['last_error_ts']   = $now;
		}

		// Update cumulative response time
		$metrics['total_response_time_ms'] = ( $metrics['total_response_time_ms'] ?? 0 ) + $response_time_ms;

		// Save metrics
		self::savePartnerMetrics( $partner_id, $metrics );

		// Check for alert conditions
		self::checkAlertConditions( $partner_id, $metrics );

		return $metrics;
	}

	/**
	 * Get partner metrics
	 *
	 * @param string $partner_id Partner ID.
	 * @return array Metrics data.
	 */
	public static function getPartnerMetrics( string $partner_id ): array {
		if ( empty( $partner_id ) ) {
			return [];
		}

		$metrics = get_option( self::OPTION_PREFIX . $partner_id, [] );
		if ( ! is_array( $metrics ) ) {
			$metrics = [];
		}

		return $metrics;
	}

	/**
	 * Calculate success rate (0-1)
	 *
	 * @param array $metrics Metrics data.
	 * @return float Success rate 0-1.
	 */
	public static function calculateSuccessRate( array $metrics ): float {
		$total = $metrics['total_requests'] ?? 0;
		if ( $total === 0 ) {
			return 1.0; // No requests yet = good
		}
		$successful = $metrics['successful_requests'] ?? 0;
		return (float) ( $successful / $total );
	}

	/**
	 * Calculate average response time
	 *
	 * @param array $metrics Metrics data.
	 * @return int Average response time in ms.
	 */
	public static function calculateAverageResponseTime( array $metrics ): int {
		$total = $metrics['total_requests'] ?? 0;
		if ( $total === 0 ) {
			return 0;
		}
		$total_time = $metrics['total_response_time_ms'] ?? 0;
		return (int) ( $total_time / $total );
	}

	/**
	 * Check alert conditions and trigger if needed
	 *
	 * @param string $partner_id Partner ID.
	 * @param array  $metrics Metrics data.
	 * @return void
	 */
	private static function checkAlertConditions( string $partner_id, array $metrics ): void {
		$total         = $metrics['total_requests'] ?? 0;
		$success_rate  = self::calculateSuccessRate( $metrics );
		$avg_response_time = self::calculateAverageResponseTime( $metrics );

		// Alert: Low success rate (but only after 5+ requests for stability)
		if ( $total >= 5 && $success_rate < self::ALERT_SUCCESS_RATE_THRESHOLD ) {
			self::triggerAlert(
				$partner_id,
				'LOW_SUCCESS_RATE',
				sprintf(
					'Partner success rate %.1f%% below threshold %.1f%%',
					$success_rate * 100,
					self::ALERT_SUCCESS_RATE_THRESHOLD * 100
				)
			);
		}

		// Alert: High response time
		if ( $avg_response_time > self::ALERT_RESPONSE_TIME_THRESHOLD ) {
			self::triggerAlert(
				$partner_id,
				'HIGH_RESPONSE_TIME',
				sprintf(
					'Partner average response time %dms above threshold %dms',
					$avg_response_time,
					self::ALERT_RESPONSE_TIME_THRESHOLD
				)
			);
		}

		// Alert: Complete failure (all recent attempts failed)
		if ( $total >= 3 && $success_rate === 0.0 ) {
			self::triggerAlert(
				$partner_id,
				'COMPLETE_FAILURE',
				"Partner endpoint completely unavailable (0% success rate)"
			);
		}
	}

	/**
	 * Trigger an alert action
	 *
	 * @param string $partner_id Partner ID.
	 * @param string $alert_type Alert type.
	 * @param string $message Alert message.
	 * @return void
	 */
	public static function triggerAlert( string $partner_id, string $alert_type, string $message ): void {
		/**
		 * Do action: peartree_sla_alert
		 *
		 * @param string $partner_id Partner ID
		 * @param string $alert_type Alert type (LOW_SUCCESS_RATE, HIGH_RESPONSE_TIME, COMPLETE_FAILURE)
		 * @param string $message Alert message
		 */
		do_action( 'peartree_sla_alert', $partner_id, $alert_type, $message );
	}

	/**
	 * Save partner metrics to options
	 *
	 * @param string $partner_id Partner ID.
	 * @param array  $metrics Metrics data.
	 * @return void
	 */
	private static function savePartnerMetrics( string $partner_id, array $metrics ): void {
		if ( empty( $partner_id ) ) {
			return;
		}
		update_option( self::OPTION_PREFIX . $partner_id, $metrics );
	}

	/**
	 * Get all partner SLA report
	 *
	 * @return array Array of partner_id => metrics
	 */
	public static function getPartnerReport(): array {
		global $wpdb;

		$report = [];
		// Find all SLA option keys
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				self::OPTION_PREFIX . '%'
			)
		);

		foreach ( (array) $rows as $option_name ) {
			$partner_id = substr( $option_name, strlen( self::OPTION_PREFIX ) );
			$metrics    = get_option( $option_name, [] );
			if ( is_array( $metrics ) && ! empty( $metrics ) ) {
				$report[ $partner_id ] = [
					'metrics'    => $metrics,
					'success_rate' => self::calculateSuccessRate( $metrics ),
					'avg_response_time_ms' => self::calculateAverageResponseTime( $metrics ),
				];
			}
		}

		return $report;
	}

	/**
	 * Reset metrics for a partner (for testing)
	 *
	 * @param string $partner_id Partner ID.
	 * @return void
	 */
	public static function resetMetrics( string $partner_id ): void {
		if ( empty( $partner_id ) ) {
			return;
		}
		delete_option( self::OPTION_PREFIX . $partner_id );
	}
}
