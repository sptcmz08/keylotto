/**
 * SET & SET50 Index Scraper — from set.or.th (Official)
 * ดึง SET Index, SET50, และ Change จากเว็บตลาดหลักทรัพย์แห่งประเทศไทย
 * ใช้ Puppeteer เพราะ set.or.th เป็น JS-rendered
 *
 * Usage: node scripts/set_index_puppeteer.js
 * Output: JSON { success: true, set: 1434.81, set_change: 4.40, set50: 952.43 }
 *
 * ข้อดีเทียบกับ Google Finance:
 *   - ข้อมูลตรงจากแหล่ง (SET = เจ้าของตลาด)
 *   - ค่าปิด 12:30 จะนิ่งตลอดช่วงพัก 12:30-14:00
 *   - ไม่มีปัญหาค่าเปลี่ยนระหว่าง real-time กับ session close
 */

import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

/**
 * Auto-discover Chrome executable path (reuse logic from manycai_draw.js)
 */
function findChrome() {
    // Method 1: Puppeteer cache directories
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
                        console.error(`[SET Scraper] Found Chrome in cache: ${line}`);
                        return line;
                    }
                }
            }
        } catch (e) { /* find command failed, try next */ }
    }

    // Method 2: Common system paths
    const systemPaths = [
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/snap/bin/chromium',
    ];
    for (const p of systemPaths) {
        if (fs.existsSync(p)) {
            console.error(`[SET Scraper] Found Chrome at: ${p}`);
            return p;
        }
    }

    // Method 3: which command
    try {
        const which = execSync('which google-chrome chromium chromium-browser 2>/dev/null').toString().trim();
        if (which) {
            const found = which.split('\n')[0];
            console.error(`[SET Scraper] Found Chrome via which: ${found}`);
            return found;
        }
    } catch (e) { /* which command failed */ }

    console.error('[SET Scraper] No Chrome found, letting Puppeteer auto-detect...');
    return null;
}

/**
 * Extract numeric value from text, handling commas and Thai formatting
 * e.g., "1,434.81" → 1434.81, "+4.40" → 4.40, "-2.30" → -2.30
 */
function parseNumber(text) {
    if (!text) return null;
    // Replace Thai minus sign, strip commas
    const cleaned = text.replace(/−/g, '-').replace(/,/g, '').trim();
    const match = cleaned.match(/([+-]?\d+\.?\d*)/);
    if (match) {
        const v = parseFloat(match[1]);
        if (!isNaN(v)) return v;
    }
    return null;
}

async function main() {
    let browser;
    try {
        const executablePath = findChrome();

        browser = await puppeteer.launch({
            headless: 'new',
            executablePath: executablePath || undefined,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--single-process',
            ]
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        await page.setViewport({ width: 1280, height: 800 });

        // ============ Step 1: Scrape SET Index ============
        console.error('[SET Scraper] Fetching SET Index from set.or.th...');
        await page.goto('https://www.set.or.th/th/market/index/set/overview', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        // Wait for data to render
        await new Promise(resolve => setTimeout(resolve, 3000));

        const setData = await page.evaluate(() => {
            // The SET overview page displays:
            // - Current index value (large number)
            // - Change value (e.g., +7.68)
            // - Change percent (e.g., +0.54%)

            // Strategy 1: Look for the main index value and change
            // set.or.th typically uses specific data attributes or class names
            const result = { value: null, change: null };

            // Try to find index value - usually the largest/most prominent number
            // Look for elements with specific patterns
            const allText = document.body.innerText;

            // The page title area usually shows: "SET 1,438.09 +7.68 (+0.54%)"
            // Look for a pattern like a 4-digit number with decimals
            const indexMatch = allText.match(/SET[\s\S]*?(\d{1,2},?\d{3}\.\d{2})/);
            if (indexMatch) {
                result.value = parseFloat(indexMatch[1].replace(/,/g, ''));
            }

            // Look for change value pattern: +X.XX or -X.XX near the index
            const changeMatch = allText.match(/SET[\s\S]*?\d{1,2},?\d{3}\.\d{2}\s*([+-−]?\d{1,3}\.\d{2})/);
            if (changeMatch) {
                result.change = parseFloat(changeMatch[1].replace(/−/g, '-').replace(/,/g, ''));
            }

            return result;
        });

        console.error(`[SET Scraper] SET raw: value=${setData.value}, change=${setData.change}`);

        // ============ Step 2: Scrape SET50 Index ============
        console.error('[SET Scraper] Fetching SET50 Index from set.or.th...');
        await page.goto('https://www.set.or.th/th/market/index/set50/overview', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        await new Promise(resolve => setTimeout(resolve, 3000));

        const set50Data = await page.evaluate(() => {
            const allText = document.body.innerText;

            // SET50 page shows: "SET50 XXX.XX +Y.YY (+Z.ZZ%)"
            const indexMatch = allText.match(/SET50[\s\S]*?(\d{3,4}\.\d{2})/);
            let value = null;
            if (indexMatch) {
                value = parseFloat(indexMatch[1].replace(/,/g, ''));
            }

            return { value };
        });

        console.error(`[SET Scraper] SET50 raw: value=${set50Data.value}`);

        // ============ Validate & Output ============
        if (!setData.value || setData.value < 100 || setData.value > 3000) {
            console.log(JSON.stringify({
                success: false,
                error: `Invalid SET value: ${setData.value}`,
                source: 'set.or.th'
            }));
            return;
        }

        if (!set50Data.value || set50Data.value < 50 || set50Data.value > 2000) {
            console.log(JSON.stringify({
                success: false,
                error: `Invalid SET50 value: ${set50Data.value}`,
                source: 'set.or.th'
            }));
            return;
        }

        const result = {
            success: true,
            set: setData.value,
            set_change: setData.change !== null ? setData.change : 0,
            set50: set50Data.value,
            source: 'set.or.th',
        };

        if (setData.change === null) {
            result.change_warning = 'Could not parse change, defaulting to 0';
        }

        console.log(JSON.stringify(result));

    } catch (error) {
        console.error(`[SET Scraper] Error: ${error.message}`);
        console.log(JSON.stringify({
            success: false,
            error: error.message,
            source: 'set.or.th'
        }));
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

main();
