import { chromium } from 'playwright';

const [, , url, path] = process.argv;

if (!url || !path) {
    console.error('Usage: node wayback-screenshot.js <url> <path>');
    process.exit(1);
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
    await page.screenshot({ path, fullPage: true });
} finally {
    await browser.close();
}
