import fetch from 'node-fetch';

async function testStructuredDataValidator() {
  const baseURL = process.env.WP_HOME || 'http://localhost:8888';
  const results = { passed: 0, failed: 0, tests: [] };

  const tests = [
    { name: 'GET /wp-json/peartree/v1/validate-schema with valid Article', url: `${baseURL}/wp-json/peartree/v1/validate-schema`, body: { schema_type: 'Article', html: '<script type="application/ld+json">{"@type":"Article","headline":"Test","author":{"@type":"Person","name":"Test"}}</script>' } },
    { name: 'Extract schemas from HTML', url: `${baseURL}/wp-json/peartree/v1/extract-schemas`, body: { html: '<script type="application/ld+json">{"@type":"LocalBusiness"}</script>' } },
    { name: 'Validate LocalBusiness schema', url: `${baseURL}/wp-json/peartree/v1/validate-schema`, body: { schema_type: 'LocalBusiness', html: '<script type="application/ld+json">{"@type":"LocalBusiness","name":"Test Business"}</script>' } },
    { name: 'Validate FAQPage schema', url: `${baseURL}/wp-json/peartree/v1/validate-schema`, body: { schema_type: 'FAQPage', html: '<script type="application/ld+json">{"@type":"FAQPage","mainEntity":[]}</script>' } },
    { name: 'Check ISO8601 date validation', url: `${baseURL}/wp-json/peartree/v1/validate-schema`, body: { schema_type: 'Article', html: '<script type="application/ld+json">{"@type":"Article","datePublished":"2025-03-21T10:00:00Z"}</script>' } },
    { name: 'Multiple schemas on page', url: `${baseURL}/wp-json/peartree/v1/extract-schemas`, body: { html: '<script type="application/ld+json">{"@type":"Article"}</script><script type="application/ld+json">{"@type":"LocalBusiness"}</script>' } },
    { name: 'Schema validation with missing required fields', url: `${baseURL}/wp-json/peartree/v1/validate-schema`, body: { schema_type: 'Article', html: '<script type="application/ld+json">{"@type":"Article"}</script>' } },
    { name: 'HTML with no schemas', url: `${baseURL}/wp-json/peartree/v1/extract-schemas`, body: { html: '<div>No schemas here</div>' } },
    { name: 'ItemList schema validation', url: `${baseURL}/wp-json/peartree/v1/validate-schema`, body: { schema_type: 'ItemList', html: '<script type="application/ld+json">{"@type":"ItemList","itemListElement":[]}</script>' } },
    { name: 'News schema validation', url: `${baseURL}/wp-json/peartree/v1/validate-schema`, body: { schema_type: 'News', html: '<script type="application/ld+json">{"@type":"NewsArticle","headline":"Test"}</script>' } }
  ];

  for (const test of tests) {
    try {
      const response = await fetch(test.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(test.body)
      });
      const passed = response.ok;
      results.tests.push({ name: test.name, passed, status: passed ? 'PASS' : 'FAIL' });
      if (passed) results.passed++; else results.failed++;
    } catch (e) {
      results.tests.push({ name: test.name, passed: false, status: 'ERROR', error: e.message });
      results.failed++;
    }
  }

  console.log(`E2E Tests: ${results.passed}/${results.passed + results.failed} PASS`);
  process.exit(results.failed > 0 ? 1 : 0);
}

testStructuredDataValidator();
