import fetch from 'node-fetch';

async function testTemplateRenderValidator() {
  const baseURL = process.env.WP_HOME || 'http://localhost:8888';
  const results = { passed: 0, failed: 0, tests: [] };

  const tests = [
    { name: 'Validate single-question template render', url: `${baseURL}/wp-json/peartree/v1/render-template`, body: { template_type: 'single-question', context: { title: 'Test Q?', content: 'Answer content here', meta_description: 'This is about test' } } },
    { name: 'Validate archive-local template render', url: `${baseURL}/wp-json/peartree/v1/render-template`, body: { template_type: 'archive-local', context: { title: 'Services', items: [] } } },
    { name: 'Validate single-ranking template render', url: `${baseURL}/wp-json/peartree/v1/render-template`, body: { template_type: 'single-ranking', context: { title: 'Ranking', items: [], content: 'Ranking content' } } },
    { name: 'Check HTML structure validation', url: `${baseURL}/wp-json/peartree/v1/validate-render`, body: { html: '<html><head><title>Test</title></head><body><main>Content</main></body></html>' } },
    { name: 'Validate meta tags in render', url: `${baseURL}/wp-json/peartree/v1/validate-render`, body: { html: '<html><head><meta name="description" content="Test"></head><body>Content</body></html>' } },
    { name: 'Single-question with CTA validation', url: `${baseURL}/wp-json/peartree/v1/render-template`, body: { template_type: 'single-question', context: { title: 'Q', content: 'A', cta_text: 'Learn More' } } },
    { name: 'Archive-local with structured markup', url: `${baseURL}/wp-json/peartree/v1/render-template`, body: { template_type: 'archive-local', context: { title: 'Local Services', items: [{ name: 'Service1' }], schema: { '@type': 'ItemList' } } } },
    { name: 'Accessibility checks on render', url: `${baseURL}/wp-json/peartree/v1/validate-render`, body: { html: '<html><body><img src="test.jpg" alt="Test"></body></html>' } },
    { name: 'Mobile viewport meta tag', url: `${baseURL}/wp-json/peartree/v1/validate-render`, body: { html: '<html><head><meta name="viewport" content="width=device-width"></head><body>Content</body></html>' } },
    { name: 'Page title tag presence', url: `${baseURL}/wp-json/peartree/v1/validate-render`, body: { html: '<html><head><title>Page Title</title></head><body>Content</body></html>' } }
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

testTemplateRenderValidator();
