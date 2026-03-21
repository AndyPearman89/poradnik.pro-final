<?php
/**
 * Structured Data Validator Service
 *
 * Validates schema.org JSON-LD structured data for SEO compliance
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

namespace PoradnikPro;

/**
 * StructuredDataValidator: Validate and test schema.org markup
 */
class StructuredDataValidator {

	const SCHEMA_CONTEXT = 'https://schema.org';

	// Required properties per schema type
	const REQUIRED_PROPERTIES = [
		'Article'      => [ '@type', 'headline', 'url' ],
		'LocalBusiness' => [ '@type', 'name', 'url', 'areaServed' ],
		'FAQPage'      => [ '@type', 'mainEntity' ],
		'ItemList'     => [ '@type', 'name', 'itemListOrder' ],
		'News'         => [ '@type', 'headline', 'url', 'datePublished' ],
	];

	/**
	 * Validate a schema.org JSON-LD object
	 *
	 * @param array $schema Schema object to validate.
	 * @return array Array with 'valid' (bool) and 'errors' (string[]).
	 */
	public static function validate( array $schema ): array {
		$errors = [];

		// Check @context
		if ( empty( $schema['@context'] ) ) {
			$errors[] = 'Missing @context';
		} elseif ( $schema['@context'] !== self::SCHEMA_CONTEXT ) {
			$errors[] = "Invalid @context: expected {self::SCHEMA_CONTEXT}";
		}

		// Check @type
		if ( empty( $schema['@type'] ) ) {
			$errors[] = 'Missing @type';
		} else {
			$type = $schema['@type'];

			// Check required properties for this type
			if ( isset( self::REQUIRED_PROPERTIES[ $type ] ) ) {
				foreach ( self::REQUIRED_PROPERTIES[ $type ] as $required_prop ) {
					if ( empty( $schema[ $required_prop ] ) ) {
						$errors[] = "Missing required property: {$required_prop} (for @type={$type})";
					}
				}
			}
		}

		// Validate nested structures
		if ( ! empty( $schema['@type'] ) && $schema['@type'] === 'LocalBusiness' ) {
			if ( ! empty( $schema['areaServed'] ) && is_array( $schema['areaServed'] ) ) {
				if ( empty( $schema['areaServed']['@type'] ) || empty( $schema['areaServed']['name'] ) ) {
					$errors[] = 'Invalid areaServed: must have @type and name';
				}
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Extract all JSON-LD schemas from HTML
	 *
	 * @param string $html HTML content.
	 * @return array Array of schema objects parsed from page.
	 */
	public static function extractSchemasFromHtml( string $html ): array {
		$schemas = [];
		$pattern = '/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/s';

		if ( ! preg_match_all( $pattern, $html, $matches ) ) {
			return $schemas;
		}

		foreach ( $matches[1] as $json_text ) {
			$decoded = json_decode( trim( $json_text ), true );
			if ( is_array( $decoded ) ) {
				$schemas[] = $decoded;
			}
		}

		return $schemas;
	}

	/**
	 * Validate all schemas in HTML content
	 *
	 * @param string $html HTML content.
	 * @return array Array with 'schemas_found' (int), 'all_valid' (bool), 'results' (validation results).
	 */
	public static function validateHtml( string $html ): array {
		$schemas = self::extractSchemasFromHtml( $html );
		$results = [];

		foreach ( $schemas as $index => $schema ) {
			$results[ $index ] = self::validate( $schema );
		}

		$all_valid = ! empty( $results ) && ! in_array( false, array_column( $results, 'valid' ), true );

		return [
			'schemas_found' => count( $schemas ),
			'all_valid'     => $all_valid,
			'results'       => $results,
		];
	}

	/**
	 * Check if HTML contains at least one valid schema
	 *
	 * @param string $html HTML content.
	 * @return bool True if at least one valid schema found.
	 */
	public static function hasValidSchema( string $html ): bool {
		$schemas = self::extractSchemasFromHtml( $html );
		foreach ( $schemas as $schema ) {
			$validation = self::validate( $schema );
			if ( $validation['valid'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get schema type from object
	 *
	 * @param array $schema Schema object.
	 * @return string Schema @type value or 'Unknown'.
	 */
	public static function getSchemaType( array $schema ): string {
		return $schema['@type'] ?? 'Unknown';
	}

	/**
	 * Validate Article schema specifically
	 *
	 * @param array $schema Article schema object.
	 * @return array Validation result.
	 */
	public static function validateArticleSchema( array $schema ): array {
		$result = self::validate( $schema );

		if ( $schema['@type'] ?? null !== 'Article' ) {
			$result['errors'][] = 'Expected @type=Article';
			$result['valid']    = false;
		}

		// Check datePublished and dateModified formats
		if ( ! empty( $schema['datePublished'] ) ) {
			if ( ! self::isValidIso8601( (string) $schema['datePublished'] ) ) {
				$result['errors'][] = 'Invalid datePublished format (expected ISO8601)';
				$result['valid']    = false;
			}
		}

		if ( ! empty( $schema['dateModified'] ) ) {
			if ( ! self::isValidIso8601( (string) $schema['dateModified'] ) ) {
				$result['errors'][] = 'Invalid dateModified format (expected ISO8601)';
				$result['valid']    = false;
			}
		}

		return $result;
	}

	/**
	 * Check if string is ISO 8601 date format
	 *
	 * @param string $date Date string.
	 * @return bool True if valid ISO8601.
	 */
	private static function isValidIso8601( string $date ): bool {
		$pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?$/';
		return (bool) preg_match( $pattern, $date );
	}
}
