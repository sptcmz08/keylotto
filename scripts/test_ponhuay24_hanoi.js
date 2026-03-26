#!/usr/bin/env node
/**
 * Test scraper: ดึงผลหวยฮานอยจาก ponhuay24.com/app/lottohanoi
 * ใช้ Puppeteer เพื่อ render Nuxt.js SPA
 * 
 * Usage: node scripts/test_ponhuay24_hanoi.js
 */

const puppeteer = require('puppeteer');

async function scrape() {
    console.error('[Ponhuay24-Hanoi] Starting Puppeteer...');
    
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    try {
        const page = await browser.newPage();
        
        // Set viewport and user agent
        await page.setViewport({ width: 1280, height: 900 });
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        console.error('[Ponhuay24-Hanoi] Loading page...');
        await page.goto('https://ponhuay24.com/app/lottohanoi', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        // Wait for SPA to render
        console.error('[Ponhuay24-Hanoi] Waiting for content...');
        await page.waitForTimeout(8000);
        
        // Try waiting for table content
        try {
            await page.waitForSelector('table, .trr, tr', { timeout: 10000 });
            console.error('[Ponhuay24-Hanoi] Table found!');
        } catch (e) {
            console.error('[Ponhuay24-Hanoi] No table found, checking raw text...');
        }
        
        // Get all text content
        const bodyText = await page.evaluate(() => document.body ? document.body.innerText : '');
        
        // Debug: show page text (first 3000 chars)
        console.error('\n=== PAGE TEXT (first 3000 chars) ===');
        console.error(bodyText.substring(0, 3000));
        console.error('=== END PAGE TEXT ===\n');
        
        // Also get HTML structure
        const tableHTML = await page.evaluate(() => {
            const tables = document.querySelectorAll('table');
            if (tables.length > 0) {
                return Array.from(tables).map(t => t.outerHTML.substring(0, 2000)).join('\n---\n');
            }
            // Try tr elements
            const rows = document.querySelectorAll('tr, .trr');
            if (rows.length > 0) {
                return `Found ${rows.length} rows:\n` + Array.from(rows).slice(0, 20).map(r => r.innerText).join('\n');
            }
            return 'No tables or rows found';
        });
        
        console.error('\n=== TABLE HTML ===');
        console.error(tableHTML.substring(0, 3000));
        console.error('=== END TABLE HTML ===\n');
        
        // Parse results
        const lines = bodyText.split('\n');
        const results = [];
        
        // Pattern: HH:MM:SS   Name    XXX    XX    คาดการ
        const pattern = /(\d{2}:\d{2}:\d{2})\s+(.+?)\s+(\d{3})\s+(\d{2})/;
        
        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed) continue;
            
            const match = trimmed.match(pattern);
            if (match) {
                const [, time, name, threeTop, twoBot] = match;
                if (threeTop === 'XXX' || twoBot === 'XX') continue;
                
                // Only show ฮานอยตรุษจีน
                if (name.includes('ตรุษจีน') || name.includes('ฮานอย')) {
                    results.push({ time, name: name.trim(), threeTop, twoBot });
                    console.error(`✅ Found: ${name.trim()} — ${threeTop}/${twoBot} (${time})`);
                }
            }
        }
        
        // Output JSON
        const output = {
            success: true,
            source: 'ponhuay24.com',
            page: '/app/lottohanoi',
            totalLines: lines.length,
            results: results,
            timestamp: new Date().toISOString()
        };
        
        console.log(JSON.stringify(output, null, 2));
        
    } catch (err) {
        console.error(`[Ponhuay24-Hanoi] Error: ${err.message}`);
        console.log(JSON.stringify({ success: false, error: err.message, results: [] }));
    } finally {
        await browser.close();
    }
}

scrape();
