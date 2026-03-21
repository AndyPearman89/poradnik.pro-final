<?php
/**
 * Template Render Validator Service
 *
 * Validates proper rendering and structure of WordPress templates
 * Checks for required elements, metadata, and structural integrity
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

namespace PoradnikPro;

/**
 * TemplateRenderValidator: Template structure and content validation
 */
class TemplateRenderValidator {

	/**
	 * Validate HTML structure
	 *
	 * @param string $html HTML content to validate.
	 * @return array With 'valid' bool and 'errors' array.
	 */
	public static function validateHtmlStructure( string $html ): array {
		$errors = [];

		// Check basic structure
		if ( strpos( $html, '<html' ) === false ) {
			$errors[] = 'Missing <html> tag';
		}

		if ( strpos( $html, '<body' ) === false ) {
			$errors[] = 'Missing <body> tag';
		}

		if ( strpos( $html, '<head' ) === false ) {
			$errors[] = 'Missing <head> tag';
		}

		// Check for proper nesting (simplified)
		if ( substr_count( $html, '<html' ) !== substr_count( $html, '</html' ) ) {
			$errors[] = 'Unmatched <html> tags';
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Check required meta tags
	 *
	 * @param string $html HTML content.
	 * @return array With required tags and presence check.
	 */
	public static function validateMetaTags( string $html ): array {
		$required = [
			'viewport'      => 'meta.*viewport',
			'charset'       => 'meta.*charset|<meta.*http-equiv',
			'description'   => 'meta.*description',
			'og:title'      => 'property=["\']og:title["\']',
			'og:description' => 'property=["\']og:description["\']',
			'og:url'        => 'property=["\']og:url["\']',
		];

		$found = [];
		foreach ( $required as $name => $pattern ) {
			$found[ $name ] = preg_match( '/' . $pattern . '/i', $html ) > 0;
		}

		$missing = array_keys( array_filter( $found, fn( $v ) => ! $v ) );

		return [
			'found'    => $found,
			'missing'  => $missing,
			'valid'    => empty( $missing ),
		];
	}

	/**
	 * Validate page title
	 *
	 * @param string $html HTML content.
	 * @return array With title value and validity.
	 */
	public static function validatePageTitle( string $html ): array {
		$matches = [];
		preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $matches );

		$title = $matches[1] ?? '';

		return [
			'title'           => $title,
			'present'         => ! empty( $title ),
			'length_ok'       => strlen( $title ) >= 30 && strlen( $title ) <= 60,
			'length'          => strlen( $title ),
		];
	}

	/**
	 * Check for primary CTA (Call-to-Action)
	 *
	 * @param string $html HTML content.
	 * @return array With CTA detection and type.
	 */
	public static function validateCTA( string $html ): array {
		$ctaTypes = [
			'lead_form'     => preg_match( '/class=["\'][^"\']*(?:lead|contact|form)[^"\']*["\']/i', $html ) > 0,
			'contact_btn'   => preg_match( '/class=["\'][^"\']*(?:contact|call|message)[^"\']*["\']/i', $html ) > 0,
			'cta_link'      => preg_match( '/<a[^>]*(?:href|class)=[^>]*(?:cta|contact|lead)[^>]*>/i', $html ) > 0,
			'phone_link'    => preg_match( '/href=["\']tel:/i', $html ) > 0,
			'email_link'    => preg_match( '/href=["\']mailto:/i', $html ) > 0,
		];

		return [
			'ctaTypes' => $ctaTypes,
			'hasCTA'   => array_reduce( $ctaTypes, fn( $c, $v ) => $c || $v, false ),
			'count'    => count( array_filter( $ctaTypes ) ),
		];
	}

	/**
	 * Check for main content section
	 *
	 * @param string $html HTML content.
	 * @return array With main content validation.
	 */
	public static function validateMainContent( string $html ): array {
		$hasMain = preg_match( '/<main[^>]*>|<div[^>]*id=["\']?main["\']?/i', $html ) > 0;
		$hasArticle = preg_match( '/<article/i', $html ) > 0;
		$hasContent = preg_match( '/<div[^>]*class=["\'][^"\']*(?:content|post)[^"\']*["\']/i', $html ) > 0;

		return [
			'hasMain'      => $hasMain,
			'hasArticle'   => $hasArticle,
			'hasContent'   => $hasContent,
			'valid'        => $hasMain || $hasArticle || $hasContent,
		];
	}

	/**
	 * Check for JSON-LD schema
	 *
	 * @param string $html HTML content.
	 * @return array With schema validation.
	 */
	public static function validateSchemaMarkup( string $html ): array {
		$schemaCount = preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>/i', $html );

		return [
			'hasSchema'     => $schemaCount > 0,
			'schemaCount'   => $schemaCount,
			'schemas_found' => [],
		];
	}

	/**
	 * Validate single-question template structure
	 *
	 * @param string $html HTML content.
	 * @return array With validation results.
	 */
	public static function validateSingleQuestion( string $html ): array {
		$errors = [];
		$warnings = [];

		// Required elements for Q&A
		$hasQuestion = preg_match( '/class=["\'][^"\']*(?:question|title)[^"\']*["\']/i', $html ) > 0;
		$hasAnswers = preg_match( '/class=["\'][^"\']*(?:answer|response)[^"\']*["\']/i', $html ) > 0 ||
						preg_match( '/<ol|<ul/i', $html ) > 0;
		$hasCTA = self::validateCTA( $html )['hasCTA'];

		if ( ! $hasQuestion ) {
			$errors[] = 'Missing question title section';
		}

		if ( ! $hasAnswers ) {
			$errors[] = 'Missing answers/responses section';
		}

		if ( ! $hasCTA ) {
			$warnings[] = 'No CTA found (optional but recommended)';
		}

		// Check for schema
		$schema = self::validateSchemaMarkup( $html );
		if ( ! $schema['hasSchema'] ) {
			$warnings[] = 'No JSON-LD schema found';
		}

		// Meta tags
		$meta = self::validateMetaTags( $html );
		foreach ( $meta['missing'] as $tag ) {
			$warnings[] = "Missing meta tag: {$tag}";
		}

		return [
			'template'       => 'single-question',
			'valid'          => empty( $errors ),
			'errors'         => $errors,
			'warnings'       => $warnings,
			'hasQuestion'    => $hasQuestion,
			'hasAnswers'     => $hasAnswers,
			'hasCTA'         => $hasCTA,
			'schemaPresent'  => $schema['hasSchema'],
		];
	}

	/**
	 * Validate archive-local template structure
	 *
	 * @param string $html HTML content.
	 * @return array With validation results.
	 */
	public static function validateArchiveLocal( string $html ): array {
		$errors = [];
		$warnings = [];

		// Required elements for local page archive
		$hasTitle = preg_match( '/class=["\'][^"\']*(?:page-title|title)[^"\']*["\']/i', $html ) > 0;
		$hasListing = preg_match( '/class=["\'][^"\']*(?:list|grid|archive)[^"\']*["\']/i', $html ) > 0 ||
						preg_match( '/<ul|<ol|<div[^>]*class=["\'][^"\']*items/i', $html ) > 0;
		$hasCTA = self::validateCTA( $html )['hasCTA'];

		if ( ! $hasTitle ) {
			$warnings[] = 'Missing page title section';
		}

		if ( ! $hasListing ) {
			$errors[] = 'Missing listing/grid section';
		}

		if ( ! $hasCTA ) {
			$warnings[] = 'No CTA found';
		}

		// Check for schema (LocalBusiness, ItemList)
		$schema = self::validateSchemaMarkup( $html );
		if ( ! $schema['hasSchema'] ) {
			$warnings[] = 'No JSON-LD schema found';
		}

		// Meta tags
		$meta = self::validateMetaTags( $html );
		if ( ! empty( $meta['missing'] ) ) {
			foreach ( $meta['missing'] as $tag ) {
				$warnings[] = "Missing meta tag: {$tag}";
			}
		}

		return [
			'template'       => 'archive-local',
			'valid'          => empty( $errors ),
			'errors'         => $errors,
			'warnings'       => $warnings,
			'hasTitle'       => $hasTitle,
			'hasListing'     => $hasListing,
			'hasCTA'         => $hasCTA,
			'schemaPresent'  => $schema['hasSchema'],
		];
	}

	/**
	 * Validate single-ranking template structure
	 *
	 * @param string $html HTML content.
	 * @return array With validation results.
	 */
	public static function validateSingleRanking( string $html ): array {
		$errors = [];
		$warnings = [];

		// Required elements for ranking
		$hasTitle = preg_match( '/class=["\'][^"\']*(?:title|heading)[^"\']*["\']/i', $html ) > 0;
		$hasTopProducts = preg_match( '/class=["\'][^"\']*(?:top|featured|best)[^"\']*["\']/i', $html ) > 0;
		$hasRankingList = preg_match( '/class=["\'][^"\']*(?:ranking|list|products)[^"\']*["\']/i', $html ) > 0 ||
				preg_match( '/<ol|<table/i', $html ) > 0;
		$hasCTA = self::validateCTA( $html )['hasCTA'];

		if ( ! $hasTitle ) {
			$warnings[] = 'Missing ranking title';
		}

		if ( ! $hasTopProducts ) {
			$warnings[] = 'Missing top products/items section';
		}

		if ( ! $hasRankingList ) {
			$errors[] = 'Missing ranking list';
		}

		if ( ! $hasCTA ) {
			$errors[] = 'No CTA found (critical for conversions)';
		}

		// Check for comparison table or similar
		$hasTable = preg_match( '/<table/i', $html ) > 0;
		if ( ! $hasTable ) {
			$warnings[] = 'No comparison table found';
		}

		// Schema
		$schema = self::validateSchemaMarkup( $html );
		if ( ! $schema['hasSchema'] ) {
			$warnings[] = 'No JSON-LD schema found';
		}

		// Meta tags
		$meta = self::validateMetaTags( $html );
		if ( ! empty( $meta['missing'] ) ) {
			foreach ( array_slice( $meta['missing'], 0, 2 ) as $tag ) {
				$warnings[] = "Missing meta tag: {$tag}";
			}
		}

		return [
			'template'            => 'single-ranking',
			'valid'               => empty( $errors ),
			'errors'              => $errors,
			'warnings'            => $warnings,
			'hasTitle'            => $hasTitle,
			'hasTopProducts'      => $hasTopProducts,
			'hasRankingList'      => $hasRankingList,
			'hasCTA'              => $hasCTA,
			'hasComparisonTable'  => $hasTable,
			'schemaPresent'       => $schema['hasSchema'],
		];
	}

	/**
	 * Check for accessibility features
	 *
	 * @param string $html HTML content.
	 * @return array With accessibility validation.
	 */
	public static function validateAccessibility( string $html ): array {
		$issues = [];

		// Check for lang attribute
		if ( ! preg_match( '/<html[^>]*lang=/i', $html ) ) {
			$issues[] = 'Missing lang attribute on <html>';
		}

		// Check for alt text in images
		$imageMatches = [];
		preg_match_all( '/<img[^>]*>/i', $html, $imageMatches );
		$imagesWithoutAlt = 0;
		foreach ( $imageMatches[0] as $img ) {
			if ( ! preg_match( '/alt=/i', $img ) ) {
				$imagesWithoutAlt++;
			}
		}
		if ( $imagesWithoutAlt > 0 ) {
			$issues[] = "{$imagesWithoutAlt} image(s) missing alt text";
		}

		// Check for form labels
		if ( preg_match( '/<input|<textarea|<select/i', $html ) ) {
			$labelCount = substr_count( strtolower( $html ), '<label' );
			if ( $labelCount === 0 ) {
				$issues[] = 'Form inputs found but no labels detected';
			}
		}

		return [
			'valid'   => empty( $issues ),
			'issues'  => $issues,
			'count'   => count( $issues ),
		];
	}

	/**
	 * General render validation
	 *
	 * @param string $html HTML content.
	 * @return array With overall render validation.
	 */
	public static function validateRender( string $html ): array {
		$structure = self::validateHtmlStructure( $html );
		$title = self::validatePageTitle( $html );
		$meta = self::validateMetaTags( $html );
		$mainContent = self::validateMainContent( $html );
		$schema = self::validateSchemaMarkup( $html );
		$accessibility = self::validateAccessibility( $html );

		return [
			'valid'             => $structure['valid'] && ! empty( $title['title'] ) && $mainContent['valid'],
			'structure'         => $structure,
			'title'             => $title,
			'meta'              => $meta,
			'mainContent'       => $mainContent,
			'schema'            => $schema,
			'accessibility'     => $accessibility,
		];
	}
}
