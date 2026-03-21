<?php
/**
 * Local Page Generator Service
 *
 * Generates SEO-optimized local service pages with canonical URLs,
 * meta tags, and structured data (LocalBusiness schema)
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

namespace PoradnikPro;

/**
 * LocalPageGenerator: Dynamic generation of service + location pages
 */
class LocalPageGenerator {

	const ARCHIVE_LOCAL_SLUG = 'uslugi';
	const SEPARATOR = '-';

	/**
	 * Generate a local page URL from service and location
	 *
	 * @param string $service Service name or slug (e.g., "prawo-rodzinne", "home-repair").
	 * @param string $location Location name (e.g., "Warszawa", "Krakow").
	 * @return string Full page URL.
	 */
	public static function generateLocalPageUrl( string $service, string $location ): string {
		$service = sanitize_title( $service );
		$location = sanitize_title( $location );

		if ( empty( $service ) || empty( $location ) ) {
			return home_url( self::ARCHIVE_LOCAL_SLUG );
		}

		$slug = "{$service}" . self::SEPARATOR . "{$location}";
		return home_url( self::ARCHIVE_LOCAL_SLUG . '/' . $slug . '/' );
	}

	/**
	 * Parse a local page URL to extract service and location
	 *
	 * @param string $url URL to parse.
	 * @return array Array with 'service' and 'location' keys, or empty if not local page.
	 */
	public static function parseLocalPageUrl( string $url ): array {
		$base = home_url( self::ARCHIVE_LOCAL_SLUG );
		if ( strpos( $url, $base ) === false ) {
			return [];
		}

		$relative = str_replace( $base . '/', '', $url );
		$relative = trim( $relative, '/' );

		if ( empty( $relative ) || strpos( $relative, self::SEPARATOR ) === false ) {
			return [];
		}

		$parts = explode( self::SEPARATOR, $relative, 2 );
		if ( count( $parts ) !== 2 ) {
			return [];
		}

		return [
			'service'  => sanitize_title( $parts[0] ),
			'location' => sanitize_title( $parts[1] ),
		];
	}

	/**
	 * Generate canonical URL for a local page
	 *
	 * Local pages use normalized canonicals: /uslugi/service-location/
	 *
	 * @param string $service Service slug.
	 * @param string $location Location slug.
	 * @return string Canonical URL.
	 */
	public static function getCanonicalUrl( string $service, string $location ): string {
		return self::generateLocalPageUrl( $service, $location );
	}

	/**
	 * Generate meta description for a local page
	 *
	 * @param string $service Service name (display format).
	 * @param string $location Location name (display format).
	 * @return string Meta description.
	 */
	public static function generateMetaDescription( string $service, string $location ): string {
		// Remove dashes and capitalize
		$service_display = str_replace( '-', ' ', $service );
		$location_display = str_replace( '-', ' ', $location );

		return sprintf(
			__( '%s w %s — znaleź specjalistę, przeczytaj porady i skontaktuj się z ekspertem.', 'poradnik-pro' ),
			ucwords( $service_display ),
			ucwords( $location_display )
		);
	}

	/**
	 * Generate LocalBusiness schema for a service in a location
	 *
	 * @param string $service Service name.
	 * @param string $location Location name.
	 * @param array  $extras Optional extra data (price, phone, hours, etc).
	 * @return array Schema.org LocalBusiness structure.
	 */
	public static function generateLocalBusinessSchema(
		string $service,
		string $location,
		array $extras = []
	): array {
		$service_display = str_replace( '-', ' ', $service );
		$location_display = str_replace( '-', ' ', $location );

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'LocalBusiness',
			'name'        => sprintf( '%s - %s', ucwords( $service_display ), ucwords( $location_display ) ),
			'url'         => self::generateLocalPageUrl( $service, $location ),
			'areaServed'  => [
				'@type' => 'City',
				'name'  => ucwords( $location_display ),
			],
			'serviceType' => ucwords( $service_display ),
			'image'       => get_theme_file_uri( 'assets/images/og-default.jpg' ),
		];

		// Add optional structured data
		if ( ! empty( $extras['phone'] ) ) {
			$schema['telephone'] = $extras['phone'];
		}

		if ( ! empty( $extras['address'] ) ) {
			$schema['address'] = [
				'@type'           => 'PostalAddress',
				'streetAddress'   => $extras['address']['street'] ?? '',
				'addressLocality' => $extras['address']['city'] ?? ucwords( $location_display ),
				'postalCode'      => $extras['address']['postal_code'] ?? '',
				'addressCountry'  => 'PL',
			];
		}

		if ( ! empty( $extras['price_range'] ) ) {
			$schema['priceRange'] = $extras['price_range'];
		}

		return $schema;
	}

	/**
	 * Generate meta title for a local page
	 *
	 * @param string $service Service name (display format).
	 * @param string $location Location name (display format).
	 * @return string Page title.
	 */
	public static function generateMetaTitle( string $service, string $location ): string {
		return sprintf(
			__( '%s w %s | %s', 'poradnik-pro' ),
			ucwords( str_replace( '-', ' ', $service ) ),
			ucwords( str_replace( '-', ' ', $location ) ),
			get_bloginfo( 'name' )
		);
	}

	/**
	 * Generate OpenGraph metadata for a local page
	 *
	 * @param string $service Service slug.
	 * @param string $location Location slug.
	 * @return array Array with og:title, og:description, og:url, og:type.
	 */
	public static function generateOpenGraph( string $service, string $location ): array {
		$service_display = str_replace( '-', ' ', $service );
		$location_display = str_replace( '-', ' ', $location );

		return [
			'og:title'       => self::generateMetaTitle( $service_display, $location_display ),
			'og:description' => self::generateMetaDescription( $service, $location ),
			'og:url'         => self::generateLocalPageUrl( $service, $location ),
			'og:type'        => 'website',
			'og:image'       => get_theme_file_uri( 'assets/images/og-default.jpg' ),
		];
	}

	/**
	 * Build internal linking context for a local page
	 *
	 * Related pages:
	 * - Other locations for the same service
	 * - Related guides for the service
	 * - Specialists in the service + location
	 *
	 * @param string $service Service slug.
	 * @param string $location Location slug.
	 * @return array Array with related_locations, related_guides, etc.
	 */
	public static function getInternalLinkContext( string $service, string $location ): array {
		// Placeholder: In full system, fetch from DB
		return [
			'related_locations' => [
				// Other cities for same service: Warszawa, Krakow, Wroclaw, etc
				self::generateLocalPageUrl( $service, 'Warszawa' ),
				self::generateLocalPageUrl( $service, 'Krakow' ),
				self::generateLocalPageUrl( $service, 'Wroclaw' ),
			],
			'related_guides'    => [
				// Guides that discuss this service (would be queried from DB)
			],
			'related_specialists' => [
				// Specialists in service + location (would be queried from DB)
			],
		];
	}

	/**
	 * Check if pageis a valid local page
	 *
	 * @return bool True if current request is a local page.
	 */
	public static function isLocalPage(): bool {
		global $wp;
		$path = trim( (string) wp_parse_url( (string) home_url( add_query_arg( [], $wp->request ?? '' ) ), PHP_URL_PATH ), '/' );
		return str_starts_with( $path, self::ARCHIVE_LOCAL_SLUG . '/' );
	}

	/**
	 * Get current page's service and location (for use in template context)
	 *
	 * @return array With 'service' and 'location' keys if local page, else empty.
	 */
	public static function getCurrentPageContext(): array {
		global $wp;
		$url = home_url( add_query_arg( [], $wp->request ?? '' ) );
		return self::parseLocalPageUrl( $url );
	}
}
