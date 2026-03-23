/**
 * Lao Samakki (ລາວສາມັກຄີ) Scraper
 * ดึงผลหวยลาวสามัคคีจาก laounion.com (Vue.js SPA)
 *
 * Usage: node scripts/laounion_draw.js
 *
 * Output: JSON to stdout
 * {
 *   "success": true,
 *   "results": {
 *     "slug": "lao-samakki",
 *     "lottery_name": "ลาวสามัคคี",
 *     "digit5": "40712",
 *     "digit4": "0712",
 *     "three_top": "712",
 *     "two_top": "12",
 *     "two_bottom": "30",
 *     "draw_date": "2026-02-12"
 *   }
 * }
 */

import puppeteer from 'puppeteer';

async function findChrome() {
    const fs = await import('fs');
    const path = await import('path');
    const { execSync } = await import('child_process');

    let executablePath = null;

    // Method 1: Search Puppeteer cache directories
    const cacheRoots = [
        path.resolve(process.cwd(), '.puppeteer-cache'),
        path.resolve(process.cwd(), 'node_modules/puppeteer/.local-chromium'),
        path.resolve(process.env.HOME || '/root', '.cache/puppeteer'),
        '/var/www/vhosts/after-spa.com/lotto.after-spa.com/.puppeteer-cache',
        '/root/.cache/puppeteer',
    ];

    for (const cacheRoot of cacheRoots) {
        if (!fs.existsSync(cacheRoot)) continue;
        try {
            const findCmd = `find "${cacheRoot}" -type f \\( -name "chrome" -o -name "chromium" \\) 2>/dev/null | head -5`;
            const found = execSync(findCmd, { timeout: 5000 }).toString().trim();
            if (found) {
                const lines = found.split('\n');
                for (const line of lines) {
                    if (line && fs.existsSync(line)) {
                        executablePath = line;
                        console.error(`[LaoSamakki] Found Chrome in cache: ${line}`);
                        break;
                    }
                }
            }
        } catch (e) { /* ignore */ }
        if (executablePath) break;
    }

    // Method 2: Common system paths
    if (!executablePath) {
        const systemPaths = [
            '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium', '/usr/bin/chromium-browser',
            '/snap/bin/chromium', '/usr/local/bin/chrome',
        ];
        for (const p of systemPaths) {
            if (fs.existsSync(p)) {
                executablePath = p;
                console.error(`[LaoSamakki] Found Chrome at: ${p}`);
                break;
            }
        }
    }

    // Method 3: which command
    if (!executablePath) {
        try {
            executablePath = execSync('which google-chrome || which chromium || which chromium-browser', { timeout: 3000 }).toString().trim();
            if (executablePath) console.error(`[LaoSamakki] Found Chrome via which: ${executablePath}`);
        } catch (e) { /* not found */ }
    }

    return executablePath;
}

async function scrapeLaoSamakki() {
    console.error('[LaoSamakki] Starting scrape from laounion.com...');

    let browser;
    try {
        const executablePath = await findChrome();

        const launchOptions = {
            headless: 'new',
            args: [
                '--no-sandbox', '--disable-setuid-sandbox',
                '--disable-dev-shm-usage', '--disable-gpu',
                '--disable-web-security', '--single-process',
                '--no-zygote',
            ],
            timeout: 30000,
        };

        if (executablePath) {
            launchOptions.executablePath = executablePath;
        }

        browser = await puppeteer.launch(launchOptions);
        const page = await browser.newPage();

        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1280, height: 800 });

        console.error('[LaoSamakki] Loading laounion.com...');
        await page.goto('https://laounion.com/', {
            waitUntil: 'networkidle2',
            timeout: 30000,
        });

        // Wait for Vue.js to render content
        await new Promise(r => setTimeout(r, 5000));

        // Extract lottery results from the page
        const rawData = await page.evaluate(() => {
            const result = {
                text: document.body.innerText.substring(0, 10000),
                tables: [],
                cards: [],
            };

            // Look for tables
            const tables = document.querySelectorAll('table');
            for (const table of tables) {
                const rows = Array.from(table.querySelectorAll('tr')).map(r =>
                    Array.from(r.querySelectorAll('td, th')).map(c => c.textContent.trim())
                );
                result.tables.push(rows);
            }

            // Look for card-like elements with numbers
            const allElements = document.querySelectorAll('div, span, p, h1, h2, h3, h4, h5, h6, td');
            for (const el of allElements) {
                const text = (el.textContent || '').trim();
                // Look for 5-digit numbers (lottery result format)
                if (/^\d{5}$/.test(text)) {
                    result.cards.push({ type: 'digit5', value: text, parent: (el.parentElement?.textContent || '').substring(0, 200) });
                }
                // Look for formatted results like "x x x x x"
                if (/^\d\s+\d\s+\d\s+\d\s+\d$/.test(text)) {
                    result.cards.push({ type: 'digit5-spaced', value: text.replace(/\s+/g, ''), parent: (el.parentElement?.textContent || '').substring(0, 200) });
                }
            }

            return result;
        });

        console.error(`[LaoSamakki] Extracted page data: ${rawData.tables.length} tables, ${rawData.cards.length} cards`);

        // Parse the results
        const parsed = parseLaoSamakkiResults(rawData);

        await browser.close();

        if (parsed) {
            console.error(`[LaoSamakki] ✅ Result found: ${parsed.digit5}`);
            console.log(JSON.stringify({
                success: true,
                results: parsed,
                scraped_at: new Date().toISOString(),
            }));
        } else {
            console.error('[LaoSamakki] ⚠️ No results found — might not have drawn yet');
            console.log(JSON.stringify({
                success: false,
                error: 'No results found on page',
                results: null,
            }));
        }

    } catch (error) {
        if (browser) await browser.close().catch(() => { });
        console.error(`[LaoSamakki] Error: ${error.message}`);
        console.log(JSON.stringify({
            success: false,
            error: error.message,
            results: null,
        }));
        process.exit(1);
    }
}

/**
 * Parse lottery results from page data
 */
function parseLaoSamakkiResults(rawData) {
    const text = rawData.text;

    // Strategy 1: Find 5-digit numbers from cards
    if (rawData.cards.length > 0) {
        const digit5Card = rawData.cards.find(c => c.type === 'digit5' || c.type === 'digit5-spaced');
        if (digit5Card) {
            const digit5 = digit5Card.value;
            return buildResult(digit5, text, rawData);
        }
    }

    // Strategy 2: Parse from tables
    for (const table of rawData.tables) {
        for (const row of table) {
            for (const cell of row) {
                // Look for 5-digit number
                const match5 = cell.match(/(\d{5})/);
                if (match5) {
                    return buildResult(match5[1], text, rawData);
                }
            }
        }
    }

    // Strategy 3: Search in page text
    // Pattern: look for something like "ເລກ 5 ໂຕ" or "5 ตัว" followed by a 5-digit number
    const digit5Match = text.match(/(?:ເລກ\s*5\s*ໂຕ|5\s*ตัว|5\s*digit|ผลรางวัล)[:\s]*(\d{5})/i);
    if (digit5Match) {
        return buildResult(digit5Match[1], text, rawData);
    }

    // Strategy 4: Just find the first 5-digit number on the page
    const anyDigit5 = text.match(/\b(\d{5})\b/);
    if (anyDigit5) {
        return buildResult(anyDigit5[1], text, rawData);
    }

    return null;
}

/**
 * Build result object from a 5-digit number
 */
function buildResult(digit5, text, rawData) {
    const digit4 = digit5.slice(-4);
    const threeTop = digit5.slice(-3);
    const twoTop = digit5.slice(-2);

    // Find 2-digit special (2ตัวล่าง / ເລກ 2 ໂຕ พิเศษ)
    let twoBottom = '';

    // Look in tables for 2-digit bottom
    for (const table of rawData.tables) {
        for (const row of table) {
            const rowText = row.join(' ');
            if (rowText.includes('ພິເສດ') || rowText.includes('พิเศษ') || rowText.includes('special') || rowText.includes('ล่าง')) {
                const match2 = rowText.match(/(\d{2})\s*$/);
                if (match2) {
                    twoBottom = match2[1];
                    break;
                }
            }
        }
        if (twoBottom) break;
    }

    // Fallback: search in text
    if (!twoBottom) {
        const bottomMatch = text.match(/(?:ເລກ\s*2\s*ໂຕ\s*ພິເສດ|2\s*ตัว\s*(?:ล่าง|พิเศษ))[:\s]*(\d{2})/i);
        if (bottomMatch) {
            twoBottom = bottomMatch[1];
        }
    }

    // Extract draw date
    let drawDate = new Date().toISOString().split('T')[0];
    // Look for date patterns (DD/MM/YYYY or YYYY-MM-DD)
    const dateMatch = text.match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/);
    if (dateMatch) {
        const day = dateMatch[1].padStart(2, '0');
        const month = dateMatch[2].padStart(2, '0');
        let year = dateMatch[3];
        if (year.length === 2) year = '20' + year;
        if (parseInt(year) > 2500) year = String(parseInt(year) - 543);
        drawDate = `${year}-${month}-${day}`;
    }

    return {
        slug: 'lao-samakki',
        lottery_name: 'ลาวสามัคคี',
        digit5: digit5,
        digit4: digit4,
        first_prize: digit5,
        three_top: threeTop,
        two_top: twoTop,
        two_bottom: twoBottom,
        draw_date: drawDate,
    };
}

// Run
scrapeLaoSamakki();
