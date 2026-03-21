<?php
/**
 * Thin Content Risk Gate Service
 *
 * Detects and prevents publication of thin content pages
 * Ensures minimum content quality standards
 *
 * @package PoradnikPro
 */

declare( strict_types=1 );

namespace PoradnikPro;

/**
 * ThinContentGate: Content quality validation and risk assessment
 */
class ThinContentGate {

	/**
	 * Minimum word count by page type
	 */
	const MIN_WORDS_GUIDE = 1000;
	const MIN_WORDS_RANKING = 800;
	const MIN_WORDS_LOCAL = 300;
	const MIN_WORDS_QA = 200;

	/**
	 * Minimum sections/structure requirements
	 */
	const MIN_SECTIONS_GUIDE = 3;
	const MIN_SECTIONS_RANKING = 2;
	const MIN_HEADINGS = 2;

	/**
	 * Thin content risk thresholds
	 */
	const RISK_THRESHOLD_HIGH = 75;      // 75+ = High risk, block
	const RISK_THRESHOLD_MEDIUM = 50;    // 50-75 = Medium risk, warn
	const RISK_THRESHOLD_LOW = 25;       // 25-50 = Low risk, OK

	/**
	 * Assess content quality and risk
	 *
	 * @param string $html Page HTML content.
	 * @param string $page_type Type of page (guide, ranking, local, qa).
	 * @return array With risk_score, is_thin, reasons array.
	 */
	public static function assessContent( string $html, string $page_type ): array {
		$score = 0;
		$reasons = [];

		// Extract text content (remove tags)
		$text = self::extractTextContent( $html );
		$word_count = self::countWords( $text );
		$heading_count = substr_count( strtolower( $html ), '<h' );
		$paragraph_count = substr_count( strtolower( $html ), '<p' );
		$section_count = max( $heading_count, 1 );

		// 1. Word count check (40 points max)
		$min_words = self::getMinWordCount( $page_type );
		if ( $word_count < $min_words ) {
			$deficit = $min_words - $word_count;
			$score += min( 40, (int) ( 40 * ( $deficit / $min_words ) ) );
			$reasons[] = "Insufficient content: {$word_count} words (min: {$min_words})";
		}

		// 2. Heading structure check (30 points max)
		$min_headings = self::MIN_HEADINGS;
		if ( $heading_count < $min_headings ) {
			$score += ( 30 * ( 1 - ( $heading_count / $min_headings ) ) );
			$reasons[] = "Missing headings: {$heading_count} found (min: {$min_headings})";
		}

		// 3. Paragraphs check (15 points max)
		$min_paragraphs = 2;
		if ( $paragraph_count < $min_paragraphs ) {
			$score += 15;
			$reasons[] = "Minimal paragraph structure: {$paragraph_count} paragraphs found";
		}

		// 4. Keyword density check (10 points max)
		if ( $word_count > 0 ) {
			// Check for repeated keywords (generic content indicator)
			$keywords = self::extractTopKeywords( $text, 5 );
			$keyword_density = self::calculateKeywordDensity( $text, $keywords );
			if ( $keyword_density > 10 ) { // High repetition suggests thin content
				$score += 10;
				$reasons[] = "High keyword density ({$keyword_density}%) suggests thin/auto content";
			}
		}

		// 5. Uniqueness check (5 points)
		$unique_sentences = self::countUniqueSentences( $text );
		if ( $word_count > 0 && $unique_sentences < 3 ) {
			$score += 5;
			$reasons[] = 'Very few unique sentences suggests copied/thin content';
		}

		// 6. Type-specific requirements
		$type_issues = self::checkTypeSpecificRequirements( $html, $page_type );
		if ( ! empty( $type_issues['missing'] ) ) {
			$score += count( $type_issues['missing'] ) * 5;
			foreach ( $type_issues['missing'] as $issue ) {
				$reasons[] = "Missing: {$issue}";
			}
		}

		return [
			'risk_score'      => (int) min( 100, $score ),
			'is_thin'         => (int) min( 100, $score ) >= self::RISK_THRESHOLD_HIGH,
			'is_medium_risk'  => (int) min( 100, $score ) >= self::RISK_THRESHOLD_MEDIUM,
			'word_count'      => $word_count,
			'heading_count'   => $heading_count,
			'paragraph_count' => $paragraph_count,
			'section_count'   => $section_count,
			'reasons'         => $reasons,
			'page_type'       => $page_type,
		];
	}

	/**
	 * Extract text content from HTML
	 *
	 * @param string $html HTML content.
	 * @return string Plain text.
	 */
	private static function extractTextContent( string $html ): string {
		// Remove script and style tags
		$text = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );
		$text = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $text );

		// Remove HTML tags
		$text = wp_strip_all_tags( $text );

		// Clean up whitespace
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Count words in text
	 *
	 * @param string $text Text to count.
	 * @return int Word count.
	 */
	private static function countWords( string $text ): int {
		if ( empty( $text ) ) {
			return 0;
		}
		return str_word_count( $text );
	}

	/**
	 * Get minimum word count for page type
	 *
	 * @param string $page_type Page type slug.
	 * @return int Minimum word count.
	 */
	private static function getMinWordCount( string $page_type ): int {
		switch ( $page_type ) {
			case 'guide':
				return self::MIN_WORDS_GUIDE;
			case 'ranking':
				return self::MIN_WORDS_RANKING;
			case 'local':
				return self::MIN_WORDS_LOCAL;
			case 'qa':
				return self::MIN_WORDS_QA;
			default:
				return 500;
		}
	}

	/**
	 * Extract top keywords from text
	 *
	 * Simple keyword extraction (would use NLP in production)
	 *
	 * @param string $text Text content.
	 * @param int    $count Max keywords to extract.
	 * @return array Keywords.
	 */
	private static function extractTopKeywords( string $text, int $count = 5 ): array {
		// Simple word frequency analysis
		$words = str_word_count( strtolower( $text ), 1 );
		$word_freq = array_count_values( $words );

		// Remove stop words
		$stop_words = [ 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'is', 'are', 'be' ];
		foreach ( $stop_words as $stop ) {
			unset( $word_freq[ $stop ] );
		}

		arsort( $word_freq );
		return array_slice( array_keys( $word_freq ), 0, $count );
	}

	/**
	 * Calculate keyword density
	 *
	 * @param string $text Text content.
	 * @param array  $keywords Keywords to check.
	 * @return float Density percentage.
	 */
	private static function calculateKeywordDensity( string $text, array $keywords ): float {
		$word_count = self::countWords( $text );
		if ( $word_count === 0 ) {
			return 0;
		}

		$keyword_count = 0;
		$text_lower = strtolower( $text );

		foreach ( $keywords as $keyword ) {
			$keyword_count += substr_count( $text_lower, $keyword );
		}

		return ( $keyword_count / $word_count ) * 100;
	}

	/**
	 * Count unique sentences
	 *
	 * @param string $text Text content.
	 * @return int Unique sentence count.
	 */
	private static function countUniqueSentences( string $text ): int {
		// Split by sentence endings
		$sentences = preg_split( '/[.!?]+/', $text );
		$unique = array_unique( $sentences );

		// Filter empty
		$unique = array_filter( $unique, fn( $s ) => strlen( trim( $s ) ) > 10 );

		return count( $unique );
	}

	/**
	 * Check type-specific content requirements
	 *
	 * @param string $html HTML content.
	 * @param string $page_type Page type.
	 * @return array With 'missing' elements.
	 */
	private static function checkTypeSpecificRequirements( string $html, string $page_type ): array {
		$missing = [];

		switch ( $page_type ) {
			case 'guide':
				// Guides should have TOC, introduction, multiple sections
				if ( ! preg_match( '/<h2|<h3/i', $html ) ) {
					$missing[] = 'Guide structure (h2 subheadings)';
				}
				if ( substr_count( strtolower( $html ), '<p' ) < 5 ) {
					$missing[] = 'Detailed guide content (multiple paragraphs)';
				}
				break;

			case 'ranking':
				// Rankings should have numbered list or table
				if ( ! preg_match( '/<ol|<table|class=["\'][^"\']*ranking/i', $html ) ) {
					$missing[] = 'Ranking structure (ordered list or table)';
				}
				break;

			case 'local':
				// Local pages should have location/service info
				if ( ! preg_match( '/location|address|city|service/i', $html ) ) {
					$missing[] = 'Location/service information';
				}
				break;

			case 'qa':
				// QA should have question and answer clearly marked
				if ( ! preg_match( '/question|answer|response/i', $html ) ) {
					$missing[] = 'Clear question/answer structure';
				}
				break;
		}

		return [ 'missing' => $missing ];
	}

	/**
	 * Get risk level label
	 *
	 * @param int $score Risk score (0-100).
	 * @return string Risk level.
	 */
	public static function getRiskLevel( int $score ): string {
		if ( $score >= self::RISK_THRESHOLD_HIGH ) {
			return 'CRITICAL';
		} elseif ( $score >= self::RISK_THRESHOLD_MEDIUM ) {
			return 'WARNING';
		} elseif ( $score >= self::RISK_THRESHOLD_LOW ) {
			return 'CAUTION';
		} else {
			return 'OK';
		}
	}

	/**
	 * Should block publication (gate check)
	 *
	 * @param int $risk_score Risk score.
	 * @return bool True if should block.
	 */
	public static function shouldBlock( int $risk_score ): bool {
		return $risk_score >= self::RISK_THRESHOLD_HIGH;
	}

	/**
	 * Get gate recommendation
	 *
	 * @param array $assessment Assessment result from assessContent().
	 * @return array With 'can_publish', 'recommendation', 'actions'.
	 */
	public static function getRecommendation( array $assessment ): array {
		$score = $assessment['risk_score'];
		$level = self::getRiskLevel( $score );
		$can_publish = ! self::shouldBlock( $score );

		return [
			'can_publish'      => $can_publish,
			'risk_level'       => $level,
			'risk_score'       => $score,
			'recommendation'   => self::getRecommendationText( $level ),
			'actions'          => self::getRecommendedActions( $assessment ),
		];
	}

	/**
	 * Get recommendation text
	 *
	 * @param string $level Risk level.
	 * @return string Recommendation.
	 */
	private static function getRecommendationText( string $level ): string {
		switch ( $level ) {
			case 'CRITICAL':
				return 'Page blocked: Critical content quality issues prevent publication. Expand content and improve structure.';
			case 'WARNING':
				return 'Page flagged: Medium risk of SEO penalties. Consider expanding content and adding more structure.';
			case 'CAUTION':
				return 'Page caution: Minor content quality issues. Review and improve before prioritizing for promotion.';
			case 'OK':
				return 'Page passes quality gates. Ready for publication and SEO prioritization.';
			default:
				return 'Unknown risk level';
		}
	}

	/**
	 * Get recommended actions
	 *
	 * @param array $assessment Assessment result.
	 * @return array Recommended actions.
	 */
	private static function getRecommendedActions( array $assessment ): array {
		$actions = [];

		if ( $assessment['word_count'] < self::getMinWordCount( $assessment['page_type'] ) ) {
			$actions[] = 'Expand content to minimum word count';
		}

		if ( $assessment['heading_count'] < self::MIN_HEADINGS ) {
			$actions[] = 'Add more section headings for structure';
		}

		if ( $assessment['paragraph_count'] < 2 ) {
			$actions[] = 'Break content into multiple paragraphs';
		}

		if ( ! empty( $assessment['reasons'] ) ) {
			// Get first reason as action
			if ( count( $assessment['reasons'] ) > 0 ) {
				$first_reason = $assessment['reasons'][0];
				if ( strpos( $first_reason, 'density' ) !== false ) {
					$actions[] = 'Reduce keyword repetition and diversify language';
				} elseif ( strpos( $first_reason, 'sentences' ) !== false ) {
					$actions[] = 'Add original content instead of repeating same points';
				}
			}
		}

		return array_slice( $actions, 0, 3 );
	}

	/**
	 * Generate gate report
	 *
	 * @param array $assessment Assessment from assessContent().
	 * @return array Full report with all details.
	 */
	public static function generateReport( array $assessment ): array {
		$recommendation = self::getRecommendation( $assessment );

		return [
			'assessment'      => $assessment,
			'recommendation'  => $recommendation,
			'report'          => [
				'title'          => 'Content Quality Gate Report',
				'page_type'      => $assessment['page_type'],
				'risk_level'     => $recommendation['risk_level'],
				'risk_score'     => $assessment['risk_score'],
				'metrics'        => [
					'word_count'      => $assessment['word_count'],
					'heading_count'   => $assessment['heading_count'],
					'paragraph_count' => $assessment['paragraph_count'],
					'section_count'   => $assessment['section_count'],
				],
				'issues'         => $assessment['reasons'],
				'can_publish'     => $recommendation['can_publish'],
				'recommendation' => $recommendation['recommendation'],
				'actions'        => $recommendation['actions'],
			],
		];
	}
}

/**
 * Polyfill wp_strip_all_tags if not available
 */
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		if ( empty( $text ) ) {
			return '';
		}
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}
