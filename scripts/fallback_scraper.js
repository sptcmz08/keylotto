/**
 * Fallback Lottery Scraper (Sanook & Raakaadee)
 * ใช้เมื่อ ManyCai ล้มเหลว
 * 
 * Usage: node scripts/fallback_scraper.js <source> <lotteryType>
 * Example: node scripts/fallback_scraper.js sanook hanoi
 *          node scripts/fallback_scraper.js raakaadee lao-vip
 */

import puppeteer from 'puppeteer';

// Source configurations
const SOURCES = {
    sanook: {
        hanoi: 'https://www.sanook.com/news/9837690/',
        lao: 'https://www.sanook.com/news/9680038/',
        thai: 'https://news.sanook.com/lotto/'
    },
    raakaadee: {
        'lao-vip': 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว-VIP/',
        'hanoi': 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอย/',
        'hanoi-vip': 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอย-VIP/',
        'lao': 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว/'
    }
};

// Scrape from Sanook
async function scrapeSanook(page, lotteryType) {
    const url = SOURCES.sanook[lotteryType];
    if (!url) throw new Error(`No Sanook URL for ${lotteryType}`);

    console.error(`[Sanook] Navigating to ${url}...`);
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(resolve => setTimeout(resolve, 2000));

    // Different parsing based on lottery type
    if (lotteryType === 'thai') {
        return await page.evaluate(() => {
            const results = [];

            // Look for lottery result numbers
            const prizeElement = document.querySelector('.prize-box, .lotto-result, [class*="prize"]');
            if (prizeElement) {
                const text = prizeElement.innerText;
                const match = text.match(/(\d{6})/);
                if (match) {
                    const firstPrize = match[1];
                    results.push({
                        first_prize: firstPrize,
                        two_top: firstPrize.slice(-2),
                        three_top: firstPrize.slice(-3),
                        draw_date: new Date().toISOString().split('T')[0],
                        date_thai: new Date().toLocaleDateString('th-TH')
                    });
                }
            }

            return results;
        });
    } else {
        // Hanoi/Lao - look for table or list
        return await page.evaluate(() => {
            const results = [];

            // Find tables or result sections
            const tables = document.querySelectorAll('table');
            for (const table of tables) {
                const rows = table.querySelectorAll('tr');
                for (const row of rows) {
                    const cells = row.querySelectorAll('td, th');
                    const text = row.innerText;

                    // Look for 3-6 digit numbers
                    const match = text.match(/(\d{3,6})/);
                    if (match) {
                        const num = match[1];
                        if (num.length >= 3 && num.length <= 6) {
                            results.push({
                                first_prize: num.padStart(6, '0'),
                                two_top: num.slice(-2),
                                three_top: num.slice(-3),
                                draw_date: new Date().toISOString().split('T')[0],
                                date_thai: new Date().toLocaleDateString('th-TH')
                            });
                            break;
                        }
                    }
                }
                if (results.length > 0) break;
            }

            return results;
        });
    }
}

// Scrape from Raakaadee (มีโครงสร้างชัดเจนกว่า)
async function scrapeRaakaadee(page, lotteryType) {
    const url = SOURCES.raakaadee[lotteryType];
    if (!url) throw new Error(`No Raakaadee URL for ${lotteryType}`);

    console.error(`[Raakaadee] Navigating to ${url}...`);
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(resolve => setTimeout(resolve, 3000));

    return await page.evaluate(() => {
        const results = [];

        // Raakaadee structure: look for result cards/boxes
        const resultCards = document.querySelectorAll('.result-card, .lottery-result, [class*="result"]');

        for (const card of resultCards) {
            const text = card.innerText;

            // Find 3-digit number (3ตัวบน)
            const threeMatch = text.match(/3\s*ตัว\s*บน\s*[:\s]*(\d{3})/i) || text.match(/(\d{3})/);
            // Find 2-digit numbers
            const twoTopMatch = text.match(/2\s*ตัว\s*บน\s*[:\s]*(\d{2})/i);
            const twoBotMatch = text.match(/2\s*ตัว\s*ล่าง\s*[:\s]*(\d{2})/i);

            if (threeMatch || twoTopMatch) {
                const threeTop = threeMatch ? threeMatch[1] : '';
                const twoTop = twoTopMatch ? twoTopMatch[1] : threeTop.slice(-2);
                const twoBottom = twoBotMatch ? twoBotMatch[1] : '';

                results.push({
                    first_prize: threeTop.padStart(6, '0'),
                    three_top: threeTop,
                    two_top: twoTop,
                    two_bottom: twoBottom,
                    draw_date: new Date().toISOString().split('T')[0],
                    date_thai: new Date().toLocaleDateString('th-TH')
                });
                break;
            }
        }

        // Alternative: look for table structure
        if (results.length === 0) {
            const tables = document.querySelectorAll('table');
            for (const table of tables) {
                const cells = table.querySelectorAll('td');
                for (const cell of cells) {
                    const text = cell.innerText.trim();
                    if (/^\d{3}$/.test(text)) {
                        results.push({
                            first_prize: text.padStart(6, '0'),
                            three_top: text,
                            two_top: text.slice(-2),
                            draw_date: new Date().toISOString().split('T')[0],
                            date_thai: new Date().toLocaleDateString('th-TH')
                        });
                        break;
                    }
                }
                if (results.length > 0) break;
            }
        }

        return results;
    });
}

// Main scraping function
async function scrapeFallback(source, lotteryType) {
    console.error(`[Fallback] Starting ${source} scraper for ${lotteryType}...`);

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
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1280, height: 800 });

        let results = [];

        if (source === 'sanook') {
            results = await scrapeSanook(page, lotteryType);
        } else if (source === 'raakaadee') {
            results = await scrapeRaakaadee(page, lotteryType);
        } else {
            throw new Error(`Unknown source: ${source}`);
        }

        console.error(`[Fallback] Found ${results.length} results from ${source}`);

        return {
            success: results.length > 0,
            source,
            lotteryType,
            scrapedAt: new Date().toISOString(),
            count: results.length,
            results
        };

    } catch (error) {
        console.error(`[Fallback] Error: ${error.message}`);
        return {
            success: false,
            source,
            lotteryType,
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
    const source = args[0] || 'sanook';
    const lotteryType = args[1] || 'hanoi';

    const validSources = Object.keys(SOURCES);
    if (!validSources.includes(source)) {
        console.error(`[Fallback] Unknown source: ${source}`);
        console.error(`[Fallback] Available: ${validSources.join(', ')}`);
        process.exit(1);
    }

    const availableTypes = Object.keys(SOURCES[source]);
    if (!availableTypes.includes(lotteryType)) {
        console.error(`[Fallback] Unknown lottery type for ${source}: ${lotteryType}`);
        console.error(`[Fallback] Available: ${availableTypes.join(', ')}`);
        process.exit(1);
    }

    const result = await scrapeFallback(source, lotteryType);
    console.log(JSON.stringify(result, null, 2));
}

main().catch(error => {
    console.error(`[Fallback] Fatal: ${error.message}`);
    process.exit(1);
});
