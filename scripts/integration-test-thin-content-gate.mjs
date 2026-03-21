#!/usr/bin/env node

/**
 * E2E Integration Tests for ThinContentGate
 * 
 * Tests content quality assessment against live WordPress pages
 *
 * Usage: node integration-test-thin-content-gate.mjs <base_url>
 */

const baseUrl = process.argv[2] || 'http://127.0.0.1:8080';

// Test results tracking
let passed = 0;
let failed = 0;
const results = [];

/**
 * Add test result
 */
function addResult(name, success, details = '') {
	results.push({ name, success, details });
	if (success) {
		passed++;
		console.log(`✓ ${name}`);
	} else {
		failed++;
		console.log(`✗ ${name}`);
		if (details) {
			console.log(`  Details: ${details}`);
		}
	}
}

/**
 * Analyze page content quality
 */
function analyzePageQuality(html) {
	// Extract text without tags
	const text = html
		.replace(/<script[^>]*>.*?<\/script>/gis, '')
		.replace(/<style[^>]*>.*?<\/style>/gis, '')
		.replace(/<[^>]*>/g, '')
		.replace(/\s+/g, ' ')
		.trim();

	const wordCount = text.split(/\s+/).filter(w => w.length > 0).length;
	const headingCount = (html.match(/<h[1-6]/gi) || []).length;
	const paragraphCount = (html.match(/<p/gi) || []).length;
	const listItems = (html.match(/<li/gi) || []).length;

	return {
		wordCount,
		headingCount,
		paragraphCount,
		listItems,
		text,
	};
}

/**
 * Check if page has minimum quality
 */
function isMinimalQuality(analysis, pageType = 'general') {
	const minimums = {
		guide: { words: 800, headings: 2, paragraphs: 3 },
		ranking: { words: 600, headings: 1, paragraphs: 2 },
		local: { words: 200, headings: 1, paragraphs: 2 },
		general: { words: 200, headings: 1, paragraphs: 1 },
	};

	const min = minimums[pageType] || minimums.general;
	return (
		analysis.wordCount >= min.words &&
		analysis.headingCount >= min.headings &&
		analysis.paragraphCount >= min.paragraphs
	);
}

/**
 * Run E2E Tests
 */
async function runTests() {
	console.log('\nThinContentGate E2E Tests');
	console.log('========================\n');

	try {
		// Test 1: Homepage content quality
		console.log('Analyzing homepage content...');
		const homeResponse = await fetch(`${baseUrl}/`);
		if (homeResponse.ok) {
			const homeHtml = await homeResponse.text();
			const homeAnalysis = analyzePageQuality(homeHtml);

			addResult(
				'Homepage has minimum content',
				homeAnalysis.wordCount > 100,
				`${homeAnalysis.wordCount} words (min: 100)`
			);

			addResult(
				'Homepage has heading structure',
				homeAnalysis.headingCount > 0,
				`${homeAnalysis.headingCount} headings found`
			);

			addResult(
				'Homepage has paragraphs',
				homeAnalysis.paragraphCount > 0,
				`${homeAnalysis.paragraphCount} paragraphs found`
			);
		}

		// Test 2: Local archive quality
		console.log('Analyzing local archive...');
		const localResponse = await fetch(`${baseUrl}/uslugi/`);
		if (localResponse.ok) {
			const localHtml = await localResponse.text();
			const localAnalysis = analyzePageQuality(localHtml);

			addResult(
				'Local archive has content',
				localAnalysis.wordCount > 50,
				`${localAnalysis.wordCount} words`
			);

			addResult(
				'Local archive has structure',
				localAnalysis.listItems > 0 || localAnalysis.paragraphCount > 0,
				`Lists: ${localAnalysis.listItems}, Paragraphs: ${localAnalysis.paragraphCount}`
			);
		}

		// Test 3: No pages are obviously thin
		console.log('Checking for thin content...');
		const pages = [
			{ url: `${baseUrl}/`, label: 'Homepage' },
			{ url: `${baseUrl}/uslugi/`, label: 'Local archive' },
		];

		let thinPageCount = 0;
		for (const page of pages) {
			try {
				const response = await fetch(page.url);
				if (response.ok) {
					const html = await response.text();
					const analysis = analyzePageQuality(html);
					if (analysis.wordCount < 100) {
						thinPageCount++;
					}
				}
			} catch (e) {
				// Ignore network errors
			}
		}

		addResult(
			'No obviously thin pages detected',
			thinPageCount === 0,
			`${thinPageCount} thin page(s) found`
		);

		// Test 4: Content is varied (multiple headings/sections)
		console.log('Checking content structure variety...');
		const structureResponse = await fetch(`${baseUrl}/`);
		if (structureResponse.ok) {
			const html = await structureResponse.text();
			const analysis = analyzePageQuality(html);

			// Good content should have structure
			const hasGoodStructure = analysis.headingCount >= 2 || analysis.paragraphCount >= 3;
			addResult(
				'Homepage has varied content structure',
				hasGoodStructure,
				`Headings: ${analysis.headingCount}, Paragraphs: ${analysis.paragraphCount}`
			);
		}

		// Test 5: Pages meet minimum word count
		console.log('Checking minimum word counts...');
		const wordCountPages = [
			{ url: `${baseUrl}/`, label: 'Homepage', minWords: 150 },
			{ url: `${baseUrl}/uslugi/`, label: 'Local archive', minWords: 100 },
		];

		for (const page of wordCountPages) {
			try {
				const response = await fetch(page.url);
				if (response.ok) {
					const html = await response.text();
					const analysis = analyzePageQuality(html);
					addResult(
						`${page.label} meets word count (${page.minWords}+)`,
						analysis.wordCount >= page.minWords || analysis.listItems > 3,
						`${analysis.wordCount} words, ${analysis.listItems} items`
					);
				}
			} catch (e) {
				addResult(
					`${page.label} meets word count (${page.minWords}+)`,
					false,
					e.message
				);
			}
		}

		// Test 6: Pages have logical content organization
		console.log('Checking content organization...');
		const orgResponse = await fetch(`${baseUrl}/`);
		if (orgResponse.ok) {
			const html = await orgResponse.text();
			const analysis = analyzePageQuality(html);

			// Check for section markers
			const hasSections = /section|article|<h[1-6]|<div class="[^"]*content/i.test(html);
			addResult(
				'Homepage has organized sections',
				hasSections || analysis.headingCount > 0,
				'Structure detected'
			);
		}

		// Test 7: No copyright/placeholder text only pages
		console.log('Checking for placeholder content...');
		const placeholderPages = [
			{ url: `${baseUrl}/`, label: 'Homepage' },
			{ url: `${baseUrl}/uslugi/`, label: 'Local archive' },
		];

		let placeholderCount = 0;
		for (const page of placeholderPages) {
			try {
				const response = await fetch(page.url);
				if (response.ok) {
					const html = await response.text();
					const isPlaceholder = /lorem ipsum|demo|placeholder|test|todo|coming soon/i.test(html);
					if (isPlaceholder) {
						placeholderCount++;
					}
				}
			} catch (e) {
				// Ignore errors
			}
		}

		addResult(
			'No placeholder/demo content pages',
			placeholderCount === 0,
			`${placeholderCount} placeholder page(s) found`
		);

		// Test 8: Content diversity check
		console.log('Checking content diversity...');
		const diverseResponse = await fetch(`${baseUrl}/`);
		if (diverseResponse.ok) {
			const html = await diverseResponse.text();
			const analysis = analyzePageQuality(html);

			// More diverse content has more sentences
			const sentences = analysis.text.split(/[.!?]+/).filter(s => s.trim().length > 20);
			const hasGoodDiversity = sentences.length > 5;

			addResult(
				'Homepage has diverse content',
				hasGoodDiversity,
				`${sentences.length} distinct thoughts/sentences`
			);
		}

		// Test 9: CTA or engagement elements present
		console.log('Checking engagement elements...');
		const engageResponses = [
			{ url: `${baseUrl}/`, label: 'Homepage' },
			{ url: `${baseUrl}/uslugi/`, label: 'Local archive' },
		];

		for (const page of engageResponses) {
			try {
				const response = await fetch(page.url);
				if (response.ok) {
					const html = await response.text();
					const hasEngagement = /button|cta|contact|call|email|form|submit/i.test(html);
					addResult(
						`${page.label} has engagement/CTA elements`,
						hasEngagement,
						'Found'
					);
				}
			} catch (e) {
				// Ignore errors
			}
		}

		// Test 10: Pages load without errors
		console.log('Checking page reliability...');
		const reliabilityPages = [
			`${baseUrl}/`,
			`${baseUrl}/uslugi/`,
		];

		let successCount = 0;
		for (const url of reliabilityPages) {
			try {
				const response = await fetch(url);
				if (response.ok) {
					successCount++;
				}
			} catch (e) {
				// Network error
			}
		}

		addResult(
			'All tested pages load successfully',
			successCount === reliabilityPages.length,
			`${successCount}/${reliabilityPages.length} pages loaded`
		);

	} catch (error) {
		addResult('Test execution', false, error.message);
	}

	// Print summary
	console.log('\n========================');
	const total = passed + failed;
	console.log(`Results: ${passed}/${total} passed`);

	if (failed > 0) {
		console.log(`FAILED: ${failed} test(s) failed`);
		process.exit(1);
	} else {
		console.log('Overall: PASS');
		process.exit(0);
	}
}

// Run tests
runTests().catch(error => {
	console.error('Test execution error:', error);
	process.exit(1);
});
