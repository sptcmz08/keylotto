/**
 * Lao VIP Scraper (laos-vip.com)
 * ดึงผลหวยลาว VIP จากเว็บ laos-vip.com
 * ออกทุกวัน ~21:30 น.
 * 
 * Usage: node scripts/laos_vip_draw.js
 * Output: JSON { success, draw_date, results: { digit5, digit4, digit3, digit2_top, digit2_bottom } }
 */

import puppeteer from 'puppeteer';

async function scrapeLaoVip() {
    console.error('[LaoVIP] Starting scrape from laos-vip.com...');

    let browser;
    try {
        const fs = await import('fs');
        const { execSync } = await import('child_process');

        // Auto-discover Chrome
        let executablePath = null;
        const cacheRoots = [
            (await import('path')).resolve(process.cwd(), '.puppeteer-cache'),
            (await import('path')).resolve(process.env.HOME || '/root', '.cache/puppeteer'),
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
                            console.error(`[LaoVIP] Found Chrome: ${line}`);
                            break;
                        }
                    }
                }
            } catch (e) { /* ignore */ }
            if (executablePath) break;
        }

        if (!executablePath) {
            const systemPaths = [
                '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable',
                '/usr/bin/chromium', '/usr/bin/chromium-browser',
            ];
            for (const p of systemPaths) {
                if (fs.existsSync(p)) {
                    executablePath = p;
                    console.error(`[LaoVIP] Found Chrome at: ${p}`);
                    break;
                }
            }
        }

        browser = await puppeteer.launch({
            headless: 'new',
            executablePath: executablePath || undefined,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--single-process']
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        await page.setViewport({ width: 1280, height: 800 });

        console.error('[LaoVIP] Navigating to laos-vip.com...');
        await page.goto('https://laos-vip.com', { waitUntil: 'networkidle2', timeout: 30000 });

        // Wait for results to render
        await new Promise(resolve => setTimeout(resolve, 5000));

        // Extract result data from the page
        const result = await page.evaluate(() => {
            const body = document.body.innerText || '';
            const html = document.body.innerHTML || '';

            // Strategy 1: Look for structured result elements
            // Common patterns: digit5, digit4, digit3, digit2 labels with numbers
            const data = {
                raw_text: body.substring(0, 2000),
                raw_html: html.substring(0, 5000),
                digit5: '',
                digit4: '',
                digit3: '',
                digit2_top: '',
                digit2_bottom: '',
                draw_date: '',
            };

            // Try to find numbers in common patterns
            // Pattern: "5 ໂຕ" or "5 ตัว" followed by number
            const patterns = [
                // Lao language patterns
                { regex: /(?:ເລກ\s*5\s*(?:ໂຕ|ตัว|digits?))[:\s]*(\d{5})/i, field: 'digit5' },
                { regex: /(?:ເລກ\s*4\s*(?:ໂຕ|ตัว|digits?))[:\s]*(\d{4})/i, field: 'digit4' },
                { regex: /(?:ເລກ\s*3\s*(?:ໂຕ|ตัว|digits?))[:\s]*(\d{3})/i, field: 'digit3' },
                { regex: /(?:ເລກ\s*2\s*(?:ໂຕ|ตัว|digits?))[:\s]*(\d{2})/i, field: 'digit2_top' },
                // Thai language patterns
                { regex: /(?:เลข\s*5\s*(?:ตัว|โต))[:\s]*(\d{5})/i, field: 'digit5' },
                { regex: /(?:เลข\s*4\s*(?:ตัว|โต))[:\s]*(\d{4})/i, field: 'digit4' },
                { regex: /(?:เลข\s*3\s*(?:ตัว|โต))[:\s]*(\d{3})/i, field: 'digit3' },
                { regex: /(?:เลข\s*2\s*(?:ตัว|โต))[:\s]*(\d{2})/i, field: 'digit2_top' },
            ];

            for (const p of patterns) {
                const match = body.match(p.regex);
                if (match && !data[p.field]) {
                    data[p.field] = match[1];
                }
            }

            // Try to find date
            // Pattern: DD/MM/YYYY or YYYY-MM-DD
            const dateMatch = body.match(/(\d{4})-(\d{2})-(\d{2})/) ||
                body.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (dateMatch) {
                if (dateMatch[1].length === 4) {
                    data.draw_date = `${dateMatch[1]}-${dateMatch[2]}-${dateMatch[3]}`;
                } else {
                    data.draw_date = `${dateMatch[3]}-${dateMatch[2]}-${dateMatch[1]}`;
                }
            }

            // Strategy 2: Find all 5-digit numbers as potential first prize
            if (!data.digit5) {
                const fiveDigitMatches = body.match(/\b(\d{5})\b/g);
                if (fiveDigitMatches && fiveDigitMatches.length > 0) {
                    data.digit5 = fiveDigitMatches[0];
                }
            }

            // Strategy 3: Look in table cells or specific elements
            const tables = document.querySelectorAll('table');
            tables.forEach(table => {
                const cells = table.querySelectorAll('td, th');
                let nextIsValue = '';
                cells.forEach(cell => {
                    const text = cell.innerText.trim();
                    if (text.match(/5\s*(?:ໂຕ|ตัว)/i)) nextIsValue = 'digit5';
                    else if (text.match(/4\s*(?:ໂຕ|ตัว)/i)) nextIsValue = 'digit4';
                    else if (text.match(/3\s*(?:ໂຕ|ตัว)/i)) nextIsValue = 'digit3';
                    else if (text.match(/2\s*(?:ໂຕ|ตัว).*(?:ບົນ|บน)/i)) nextIsValue = 'digit2_top';
                    else if (text.match(/2\s*(?:ໂຕ|ตัว).*(?:ລຸ່ມ|ล่าง)/i)) nextIsValue = 'digit2_bottom';
                    else if (nextIsValue && /^\d+$/.test(text)) {
                        if (!data[nextIsValue]) data[nextIsValue] = text;
                        nextIsValue = '';
                    }
                });
            });

            // Strategy 4: Look in divs/spans with large numbers
            const allElements = document.querySelectorAll('div, span, p, h1, h2, h3, h4, td');
            const bigNumbers = [];
            allElements.forEach(el => {
                const text = el.innerText.trim();
                if (/^\d{5}$/.test(text)) bigNumbers.push({ text, el: el.tagName, class: el.className });
                if (/^\d{3}$/.test(text) && !data.digit3) {
                    // Check if parent/sibling mentions "3"
                    const parentText = el.parentElement?.innerText || '';
                    if (parentText.match(/3\s*(?:ໂຕ|ตัว)/i)) {
                        data.digit3 = text;
                    }
                }
            });

            if (!data.digit5 && bigNumbers.length > 0) {
                data.digit5 = bigNumbers[0].text;
            }

            // Derive missing values from digit5
            if (data.digit5 && data.digit5.length === 5) {
                if (!data.digit4) data.digit4 = data.digit5.slice(-4);
                if (!data.digit3) data.digit3 = data.digit5.slice(-3);
                if (!data.digit2_top) data.digit2_top = data.digit5.slice(-2);
                if (!data.digit2_bottom) data.digit2_bottom = data.digit5.slice(0, 2);
            }

            return data;
        });

        console.error(`[LaoVIP] Extracted: digit5=${result.digit5}, digit3=${result.digit3}, digit2_top=${result.digit2_top}, digit2_bottom=${result.digit2_bottom}, date=${result.draw_date}`);

        // Determine draw_date
        let drawDate = result.draw_date;
        if (!drawDate) {
            // Use today's date in Bangkok timezone
            const bangkokTime = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Bangkok' }));
            drawDate = bangkokTime.toISOString().split('T')[0];
        }

        // Validate
        if (!result.digit5 || result.digit5.length !== 5) {
            console.error(`[LaoVIP] No valid 5-digit result found. Raw text: ${result.raw_text.substring(0, 500)}`);
            return JSON.stringify({
                success: false,
                error: 'No valid 5-digit result found',
                draw_date: drawDate,
                debug: {
                    raw_text: result.raw_text.substring(0, 1000),
                }
            });
        }

        return JSON.stringify({
            success: true,
            source: 'laos-vip.com',
            draw_date: drawDate,
            results: {
                digit5: result.digit5,
                digit4: result.digit4 || result.digit5.slice(-4),
                digit3: result.digit3 || result.digit5.slice(-3),
                digit2_top: result.digit2_top || result.digit5.slice(-2),
                digit2_bottom: result.digit2_bottom || result.digit5.slice(0, 2),
            },
            scrapedAt: new Date().toISOString(),
        });

    } catch (error) {
        console.error(`[LaoVIP] Error: ${error.message}`);
        return JSON.stringify({
            success: false,
            error: error.message,
            scrapedAt: new Date().toISOString(),
        });
    } finally {
        if (browser) await browser.close();
    }
}

const output = await scrapeLaoVip();
console.log(output);
