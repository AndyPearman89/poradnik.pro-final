<?php
/**
 * Internal Linking Controller Service
 *
 * Manages internal linking hierarchy (guides -> rankings -> local pages)
 * Prevents deep link chains and circular linking. Optimizes for SEO depth.
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

namespace PoradnikPro;

/**
 * InternalLinkingController: Link depth management and hierarchy optimization
 */
class InternalLinkingController {

	/**
	 * Max depth for guide -> ranking -> local chain
	 * Prevents thin content and link dilution
	 */
	const MAX_LINK_DEPTH = 3;

	/**
	 * Minimum authority links per page (to context pages)
	 */
	const MIN_CONTEXT_LINKS = 3;

	/**
	 * Maximum related links to surface per section
	 */
	const MAX_RELATED_LINKS = 5;

	/**
	 * Get internal links for current page
	 *
	 * Determines optimal links based on page type:
	 * - guide_type: Link to related guides (same category) and rankings
	 * - ranking_type: Link to guides (context) and local pages (conversion)
	 * - local_page: Link to related locations and guides (authority)
	 *
	 * @param string $page_type Type of page (guide_type, ranking_type, local_page).
	 * @param array  $context Context data (category, service, location, etc).
	 * @return array Array of link recommendations.
	 */
	public static function getInternalLinks(
		string $page_type,
		array $context = []
	): array {
		switch ( $page_type ) {
			case 'guide_type':
				return self::getGuideLinks( $context );
			case 'ranking_type':
				return self::getRankingLinks( $context );
			case 'local_page':
				return self::getLocalPageLinks( $context );
			default:
				return [];
		}
	}

	/**
	 * Get links for guide pages
	 *
	 * Guide linking strategy:
	 * - Related guides in same category (peer depth)
	 * - Relevant rankings (conversion depth)
	 * - Avoid deep local page chains
	 *
	 * @param array $context Page context (category, tags, taxonomy).
	 * @return array Link recommendations.
	 */
	private static function getGuideLinks( array $context ): array {
		$links = [
			'related_guides'   => [],
			'rankings'         => [],
			'local_pages'      => [],
			'depth'            => 1,
			'depth_strategy'   => 'peer + conversion',
		];

		// Related guides: same category, different topic
		if ( ! empty( $context['category'] ) ) {
			$links['related_guides'] = self::getRelatedGuidesByCategory(
				$context['category'],
				self::MAX_RELATED_LINKS
			);
		}

		// Rankings: topical relevance (prevent deep chains)
		if ( ! empty( $context['tags'] ) ) {
			$links['rankings'] = self::getRelatedRankingsByTags(
				$context['tags'],
				3
			);
		}

		// Local pages: only top 2 (avoid depth)
		if ( ! empty( $context['category'] ) ) {
			$links['local_pages'] = self::getLocalPagesByCategory(
				$context['category'],
				2
			);
		}

		return $links;
	}

	/**
	 * Get links for ranking pages
	 *
	 * Ranking linking strategy:
	 * - Guide pages (authority building and backlink target)
	 * - Local pages (conversion and lead routing)
	 * - Related rankings (topical cluster)
	 *
	 * @param array $context Page context (category, service, location).
	 * @return array Link recommendations.
	 */
	private static function getRankingLinks( array $context ): array {
		$links = [
			'guides'         => [],
			'local_pages'    => [],
			'related_rankings' => [],
			'depth'          => 2,
			'depth_strategy' => 'guide + conversion + cluster',
		];

		// Guides: topical authority (2-3 links)
		if ( ! empty( $context['category'] ) ) {
			$links['guides'] = self::getRelatedGuidesByCategory(
				$context['category'],
				3
			);
		}

		// Local pages: conversion focus (3-5 links to different locations)
		if ( ! empty( $context['service'] ) ) {
			$links['local_pages'] = self::getLocalPagesByService(
				$context['service'],
				5
			);
		}

		// Related rankings: topical cluster (2-3 rankings)
		if ( ! empty( $context['category'] ) ) {
			$links['related_rankings'] = self::getRelatedRankingsByCategory(
				$context['category'],
				3
			);
		}

		return $links;
	}

	/**
	 * Get links for local pages
	 *
	 * Local page linking strategy:
	 * - Related locations (same service, different cities)
	 * - Authority guides (credibility)
	 * - Avoid linking to other local pages (prevent dilution)
	 *
	 * @param array $context Page context (service, location).
	 * @return array Link recommendations.
	 */
	private static function getLocalPageLinks( array $context ): array {
		$links = [
			'related_locations' => [],
			'guides'            => [],
			'depth'             => 3,
			'depth_strategy'    => 'location + authority',
		];

		// Related locations: same service, different cities (3-5 links)
		if ( ! empty( $context['service'] ) ) {
			$links['related_locations'] = self::getLocalPagesByService(
				$context['service'],
				5
			);
		}

		// Authority guides: build credibility (1-2 links to credible sources)
		if ( ! empty( $context['service'] ) ) {
			$links['guides'] = self::getGuidesByService(
				$context['service'],
				2
			);
		}

		return $links;
	}

	/**
	 * Get related guides by category
	 *
	 * @param string $category Category slug.
	 * @param int    $limit Max guides to return.
	 * @return array Guides with URL, title, excerpt.
	 */
	private static function getRelatedGuidesByCategory(
		string $category,
		int $limit = 5
	): array {
		// Placeholder: Would query WP_Query for guides in category
		return [
			[
				'url'    => home_url( "guide-1-{$category}/" ),
				'title'  => 'Guide 1: ' . ucwords( str_replace( '-', ' ', $category ) ),
				'type'   => 'guide_type',
				'depth'  => 1,
			],
			[
				'url'    => home_url( "guide-2-{$category}/" ),
				'title'  => 'Guide 2: ' . ucwords( str_replace( '-', ' ', $category ) ),
				'type'   => 'guide_type',
				'depth'  => 1,
			],
		];
	}

	/**
	 * Get related rankings by tags
	 *
	 * @param array $tags Tags array.
	 * @param int   $limit Max rankings to return.
	 * @return array Rankings with URL, title, excerpt.
	 */
	private static function getRelatedRankingsByTags(
		array $tags,
		int $limit = 3
	): array {
		// Placeholder: Would query WP_Query for rankings with matching tags
		$results = [];
		foreach ( array_slice( $tags, 0, $limit ) as $tag ) {
			$results[] = [
				'url'    => home_url( "ranking-{$tag}/" ),
				'title'  => 'Best ' . ucwords( str_replace( '-', ' ', $tag ) ),
				'type'   => 'ranking_type',
				'depth'  => 2,
			];
		}
		return $results;
	}

	/**
	 * Get related rankings by category
	 *
	 * @param string $category Category slug.
	 * @param int    $limit Max rankings to return.
	 * @return array Rankings with URL, title, excerpt.
	 */
	private static function getRelatedRankingsByCategory(
		string $category,
		int $limit = 3
	): array {
		// Placeholder: Would query WP_Query for rankings in category
		return [
			[
				'url'    => home_url( "best-{$category}-1/" ),
				'title'  => 'Best ' . ucwords( str_replace( '-', ' ', $category ) ),
				'type'   => 'ranking_type',
				'depth'  => 2,
			],
		];
	}

	/**
	 * Get local pages by category/service
	 *
	 * @param string $category Category or service slug.
	 * @param int    $limit Max pages to return.
	 * @return array Local pages with URL, title.
	 */
	private static function getLocalPagesByCategory(
		string $category,
		int $limit = 2
	): array {
		// Placeholder: Would query local pages matching service
		return [
			[
				'url'    => home_url( "uslugi/{$category}-warszawa/" ),
				'title'  => ucwords( str_replace( '-', ' ', $category ) ) . ' w Warszawie',
				'type'   => 'local_page',
				'depth'  => 3,
			],
			[
				'url'    => home_url( "uslugi/{$category}-krakow/" ),
				'title'  => ucwords( str_replace( '-', ' ', $category ) ) . ' w Krakowie',
				'type'   => 'local_page',
				'depth'  => 3,
			],
		];
	}

	/**
	 * Get local pages by service (different locations)
	 *
	 * @param string $service Service slug.
	 * @param int    $limit Max local pages to return.
	 * @return array Local pages with URL, title.
	 */
	private static function getLocalPagesByService(
		string $service,
		int $limit = 5
	): array {
		// Top locations for this service
		$locations = [ 'warszawa', 'krakow', 'wroclaw', 'poznan', 'gdansk' ];
		$results   = [];

		foreach ( array_slice( $locations, 0, $limit ) as $location ) {
			$results[] = [
				'url'    => LocalPageGenerator::generateLocalPageUrl( $service, $location ),
				'title'  => ucwords( str_replace( '-', ' ', $service ) ) . ' w ' . ucfirst( $location ),
				'type'   => 'local_page',
				'depth'  => 3,
			];
		}

		return $results;
	}

	/**
	 * Get guides by service keyword
	 *
	 * @param string $service Service slug.
	 * @param int    $limit Max guides to return.
	 * @return array Guides with URL, title.
	 */
	private static function getGuidesByService(
		string $service,
		int $limit = 2
	): array {
		// Placeholder: Query guides mentioning the service
		return [
			[
				'url'    => home_url( "guides/how-to-{$service}/" ),
				'title'  => 'How to ' . ucwords( str_replace( '-', ' ', $service ) ),
				'type'   => 'guide_type',
				'depth'  => 1,
			],
		];
	}

	/**
	 * Validate link depth (prevent circular linking)
	 *
	 * @param string $source_url Source page URL.
	 * @param string $target_url Target page URL.
	 * @param int    $max_depth Max allowed depth.
	 * @return bool True if link is safe (depth < max).
	 */
	public static function validateLinkDepth(
		string $source_url,
		string $target_url,
		int $max_depth = self::MAX_LINK_DEPTH
	): bool {
		// Prevent circular linking
		if ( $source_url === $target_url ) {
			return false;
		}

		// In full system, would track link chain depth
		// For now, allow if different URLs
		return true;
	}

	/**
	 * Get link scoring for ranking pages
	 *
	 * Prioritize links by:
	 * - Relevance to page topic
	 * - Authority of target page
	 * - Conversion potential (local pages score higher)
	 *
	 * @param string $page_type Type of page linking from.
	 * @param array  $candidates Candidate links.
	 * @return array Sorted by score (descending).
	 */
	public static function scoreLinks(
		string $page_type,
		array $candidates
	): array {
		if ( 'ranking_type' !== $page_type ) {
			return $candidates;
		}

		// For ranking pages: local pages score highest (conversion)
		usort(
			$candidates,
			function ( $a, $b ) {
				$a_score = ( 'local_page' === $a['type'] ) ? 100 : 50;
				$b_score = ( 'local_page' === $b['type'] ) ? 100 : 50;
				return $b_score <=> $a_score;
			}
		);

		return $candidates;
	}

	/**
	 * Get anchor text recommendations
	 *
	 * For guides: topic keywords
	 * For rankings: product keywords + long-tail
	 * For local: location keywords + service
	 *
	 * @param string $page_type Type of page.
	 * @param array  $context Context with category, service, location, etc.
	 * @return array Recommended anchor texts.
	 */
	public static function getAnchorTextSuggestions(
		string $page_type,
		array $context = []
	): array {
		switch ( $page_type ) {
			case 'guide_type':
				return self::getGuideAnchorTexts( $context );

			case 'ranking_type':
				return self::getRankingAnchorTexts( $context );

			case 'local_page':
				return self::getLocalPageAnchorTexts( $context );

			default:
				return [];
		}
	}

	/**
	 * Get anchor text suggestions for guide pages
	 *
	 * @param array $context Context.
	 * @return array Anchor texts.
	 */
	private static function getGuideAnchorTexts( array $context ): array {
		$category = $context['category'] ?? 'topic';
		return [
			'read-more'  => 'Read More',
			'more-info'  => 'Read our full guide to ' . ucfirst( str_replace( '-', ' ', $category ) ),
			'learn-more' => 'Learn more',
		];
	}

	/**
	 * Get anchor text suggestions for ranking pages
	 *
	 * @param array $context Context.
	 * @return array Anchor texts.
	 */
	private static function getRankingAnchorTexts( array $context ): array {
		$service = $context['service'] ?? 'service';
		return [
			'top-choice'      => 'Top choice for ' . ucfirst( str_replace( '-', ' ', $service ) ),
			'compare'         => 'Compare ' . ucfirst( str_replace( '-', ' ', $service ) ),
			'best-of'         => 'Best ' . ucfirst( str_replace( '-', ' ', $service ) ),
			'local-providers' => 'Find local ' . ucfirst( str_replace( '-', ' ', $service ) ),
		];
	}

	/**
	 * Get anchor text suggestions for local pages
	 *
	 * @param array $context Context with service and location.
	 * @return array Anchor texts.
	 */
	private static function getLocalPageAnchorTexts( array $context ): array {
		$service  = $context['service'] ?? 'service';
		$location = $context['location'] ?? 'location';
		return [
			'location'      => ucfirst( str_replace( '-', ' ', $service ) ) . ' in ' . ucfirst( str_replace( '-', ' ', $location ) ),
			'specialists'   => 'Specialists in ' . ucfirst( str_replace( '-', ' ', $location ) ),
			'find-local'    => 'Find ' . ucfirst( str_replace( '-', ' ', $service ) ) . ' near you',
			'pricing-local' => ucfirst( str_replace( '-', ' ', $service ) ) . ' pricing in ' . ucfirst( str_replace( '-', ' ', $location ) ),
		];
	}
}
