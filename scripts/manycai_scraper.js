/**
 * ManyCai Lottery Scraper v3 (Production)
 * ใช้ Puppeteer ดึงข้อมูลผลหวยจาก th.manycai.com
 * 
 * Usage: node scripts/manycai_scraper.js <lotteryCode> [count]
 * Example: node scripts/manycai_scraper.js YNHN 5
 */

import puppeteer from 'puppeteer';

// Lottery code mappings
const LOTTERY_MAPPINGS = {
    'YNHN': { name: 'หวยฮานอย', slug: 'hanoi' },
    'HNVIP': { name: 'หวยฮานอย VIP', slug: 'hanoi-vip' },
    'BFHN': { name: 'หวยฮานอยพิเศษ', slug: 'hanoi-special' },
    'ZCVIP': { name: 'หวยลาว VIP', slug: 'lao-vip' },
    'TLZC': { name: 'หวยลาวพัฒนา', slug: 'lao' },
    'TGFC': { name: 'หวยไทย', slug: 'thai' },
    'TLST': { name: 'หวยลาวสตาร์', slug: 'lao-star' }
};

async function scrapeManyCai(lotteryCode, count = 1) {
    const url = `https://th.manycai.com/Issue/history?lottername=${lotteryCode}`;

    console.error(`[ManyCai] Starting scrape for ${lotteryCode}...`);

    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage'
            ]
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        await page.setViewport({ width: 1280, height: 800 });

        console.error(`[ManyCai] Navigating to ${url}...`);
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Extract data from table
        const results = await page.evaluate((maxCount) => {
            const rows = document.querySelectorAll('table tr');
            const data = [];

            rows.forEach((row, index) => {
                if (index === 0) return; // Skip header
                if (data.length >= maxCount) return;

                const cells = row.querySelectorAll('td');
                if (cells.length >= 3) {
                    const firstPrize = cells[0]?.innerText?.trim();
                    const dateText = cells[1]?.innerText?.trim();
                    const datetime = cells[2]?.innerText?.trim();

                    // Parse date from "2026-01-27 19:30:00" format
                    const dateMatch = datetime?.match(/(\d{4}-\d{2}-\d{2})/);
                    const isoDate = dateMatch ? dateMatch[1] : null;

                    // Only add if we have valid data
                    if (firstPrize && /^\d{4,6}$/.test(firstPrize)) {
                        data.push({
                            first_prize: firstPrize,
                            draw_date: isoDate,
                            date_thai: dateText,
                            two_top: firstPrize.slice(-2),
                            three_top: firstPrize.slice(-3),
                            two_bottom: firstPrize.slice(0, 2)
                        });
                    }
                }
            });

            return data;
        }, count);

        console.error(`[ManyCai] Found ${results.length} results`);

        return {
            success: true,
            lotteryCode,
            lotteryName: LOTTERY_MAPPINGS[lotteryCode]?.name || lotteryCode,
            slug: LOTTERY_MAPPINGS[lotteryCode]?.slug || lotteryCode.toLowerCase(),
            scrapedAt: new Date().toISOString(),
            count: results.length,
            results: results
        };

    } catch (error) {
        console.error(`[ManyCai] Error: ${error.message}`);
        return {
            success: false,
            lotteryCode,
            error: error.message,
            scrapedAt: new Date().toISOString(),
            results: []
        };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Main execution
async function main() {
    const args = process.argv.slice(2);
    const lotteryCode = args[0] || 'YNHN';
    const count = parseInt(args[1]) || 10;

    if (!LOTTERY_MAPPINGS[lotteryCode]) {
        console.error(`[ManyCai] Unknown lottery code: ${lotteryCode}`);
        console.error(`[ManyCai] Available: ${Object.keys(LOTTERY_MAPPINGS).join(', ')}`);
        process.exit(1);
    }

    const result = await scrapeManyCai(lotteryCode, count);
    console.log(JSON.stringify(result, null, 2));
}

main().catch(error => {
    console.error(`[ManyCai] Fatal: ${error.message}`);
    process.exit(1);
});
