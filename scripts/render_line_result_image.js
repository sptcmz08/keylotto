import fs from 'fs';
import fsp from 'fs/promises';
import path from 'path';
import puppeteer from 'puppeteer';
import { fileURLToPath } from 'url';

const [, , inputPath, outputPath] = process.argv;
const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(scriptDir, '..');

if (!inputPath || !outputPath) {
  console.error('Usage: node scripts/render_line_result_image.js <input.json> <output.png>');
  process.exit(1);
}

const TEXT = {
  draw: '\u0e07\u0e27\u0e14',
  result: '\u0e1c\u0e25\u0e2d\u0e2d\u0e01',
  top3: '3 \u0e15\u0e31\u0e27\u0e1a\u0e19',
  top2: '2 \u0e15\u0e31\u0e27\u0e1a\u0e19',
  bot2: '2 \u0e15\u0e31\u0e27\u0e25\u0e48\u0e32\u0e07',
};

const escapeHtml = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const renderResultChip = (label, value, tone) => `
  <div class="result-chip ${tone}">
    <div class="result-chip__label">${escapeHtml(label)}</div>
    <div class="result-chip__value">${escapeHtml(value || '-')}</div>
  </div>
`;

const findChromeExecutable = () => {
  const cacheDir = path.join(rootDir, '.cache', 'puppeteer');
  const directCandidates = [
    path.join(cacheDir, 'chrome', 'linux-146.0.7680.153', 'chrome-linux64', 'chrome'),
    path.join(cacheDir, 'chrome', 'win64-146.0.7680.153', 'chrome-win64', 'chrome.exe'),
    path.join(cacheDir, 'chrome', 'mac-146.0.7680.153', 'chrome-mac-x64', 'Google Chrome for Testing.app', 'Contents', 'MacOS', 'Google Chrome for Testing'),
    path.join(cacheDir, 'chrome', 'mac_arm-146.0.7680.153', 'chrome-mac-arm64', 'Google Chrome for Testing.app', 'Contents', 'MacOS', 'Google Chrome for Testing'),
  ];

  for (const candidate of directCandidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  const chromeRoot = path.join(cacheDir, 'chrome');
  if (!fs.existsSync(chromeRoot)) {
    return null;
  }

  const stack = [chromeRoot];
  const executableNames = new Set(['chrome', 'chrome.exe']);

  while (stack.length > 0) {
    const current = stack.pop();
    const entries = fs.readdirSync(current, { withFileTypes: true });

    for (const entry of entries) {
      const fullPath = path.join(current, entry.name);
      if (entry.isDirectory()) {
        stack.push(fullPath);
        continue;
      }

      if (executableNames.has(entry.name)) {
        return fullPath;
      }
    }
  }

  return null;
};

const ensureWritableBrowserDirs = async () => {
  const baseCacheDir = path.join(rootDir, '.cache');
  const cacheDir = path.join(baseCacheDir, 'puppeteer');
  const configDir = path.join(baseCacheDir, 'xdg-config');
  const userDataDir = path.join(baseCacheDir, 'puppeteer-profile');

  await fsp.mkdir(cacheDir, { recursive: true });
  await fsp.mkdir(configDir, { recursive: true });
  await fsp.mkdir(userDataDir, { recursive: true });

  process.env.PUPPETEER_CACHE_DIR = cacheDir;
  process.env.XDG_CACHE_HOME = baseCacheDir;
  process.env.XDG_CONFIG_HOME = configDir;
  if (!process.env.HOME) {
    process.env.HOME = rootDir;
  }

  return { userDataDir };
};

const findThaiFontPath = () => {
  const candidates = [
    path.join(rootDir, 'line', 'fonts', 'NotoSansThai-Regular.ttf'),
    path.join(rootDir, 'line', 'fonts', 'Prompt-Regular.ttf'),
    '/usr/share/fonts/truetype/noto/NotoSansThai-Regular.ttf',
    '/usr/share/fonts/opentype/noto/NotoSansThai-Regular.ttf',
    '/usr/share/fonts/truetype/tlwg/Garuda.ttf',
    '/usr/share/fonts/truetype/tlwg/Kinnari.ttf',
    '/usr/share/fonts/truetype/tlwg/Loma.ttf',
    '/usr/share/fonts/truetype/tlwg/Norasi.ttf',
    '/usr/share/fonts/truetype/tlwg/Purisa.ttf',
    '/usr/share/fonts/truetype/tlwg/Sawasdee.ttf',
    '/usr/share/fonts/truetype/tlwg/Umpush.ttf',
    '/usr/share/fonts/truetype/tlwg/Waree.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    'C:/Windows/Fonts/tahoma.ttf',
    'C:/Windows/Fonts/arial.ttf',
  ];

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  const scanDirs = [
    path.join(rootDir, 'line', 'fonts'),
    '/usr/share/fonts',
    '/usr/local/share/fonts',
    'C:/Windows/Fonts',
  ];

  const fontNamePattern = /(thai|sarabun|prompt|kanit|garuda|kinnari|loma|norasi|purisa|sawasdee|waree|umpush|tlwg|noto)/i;

  for (const scanDir of scanDirs) {
    if (!fs.existsSync(scanDir)) {
      continue;
    }

    const stack = [scanDir];
    while (stack.length > 0) {
      const current = stack.pop();
      const entries = fs.readdirSync(current, { withFileTypes: true });

      for (const entry of entries) {
        const fullPath = path.join(current, entry.name);
        if (entry.isDirectory()) {
          stack.push(fullPath);
          continue;
        }

        const lowerName = entry.name.toLowerCase();
        if ((lowerName.endsWith('.ttf') || lowerName.endsWith('.otf')) && fontNamePattern.test(entry.name)) {
          return fullPath;
        }
      }
    }
  }

  return null;
};

const buildEmbeddedFontFace = () => {
  const fontPath = findThaiFontPath();
  if (!fontPath) {
    return '';
  }

  const extension = path.extname(fontPath).toLowerCase();
  const mimeType = extension === '.otf' ? 'font/otf' : 'font/ttf';
  const format = extension === '.otf' ? 'opentype' : 'truetype';
  const fontData = fs.readFileSync(fontPath).toString('base64');

  return `
    @font-face {
      font-family: "LineThai";
      src: url("data:${mimeType};base64,${fontData}") format("${format}");
      font-weight: 400 900;
      font-style: normal;
      font-display: block;
    }
  `;
};

const main = async () => {
  const raw = await fsp.readFile(inputPath, 'utf8');
  const data = JSON.parse(raw);

  await fsp.mkdir(path.dirname(outputPath), { recursive: true });
  const { userDataDir } = await ensureWritableBrowserDirs();
  const embeddedFontFace = buildEmbeddedFontFace();

  const html = `
  <!doctype html>
  <html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      ${embeddedFontFace}
      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: "LineThai", "Tahoma", sans-serif;
        background:
          radial-gradient(circle at 25% 22%, rgba(255, 196, 76, 0.14), transparent 18%),
          radial-gradient(circle at 78% 68%, rgba(255, 150, 80, 0.12), transparent 20%),
          linear-gradient(180deg, #7c0606 0%, #980d0d 38%, #7c0606 100%);
        color: #fff4c8;
      }
      body::before {
        content: "";
        position: fixed;
        inset: 0;
        background:
          radial-gradient(circle at center, rgba(255, 208, 98, 0.08) 0 3px, transparent 3px 100%),
          radial-gradient(circle at center, rgba(255, 208, 98, 0.06) 0 1.5px, transparent 1.5px 100%);
        background-size: 120px 120px, 40px 40px;
        background-position: 0 0, 20px 20px;
        opacity: 0.22;
        pointer-events: none;
      }
      .frame {
        width: 1280px;
        height: 720px;
        padding: 28px 34px;
      }
      .poster {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: 28px;
        overflow: hidden;
        border: 3px solid rgba(255, 211, 109, 0.48);
        background:
          linear-gradient(180deg, rgba(255,255,255,0.03), rgba(0,0,0,0.06)),
          transparent;
        box-shadow:
          inset 0 0 0 3px rgba(255, 214, 116, 0.08),
          0 20px 60px rgba(0,0,0,0.28);
      }
      .top-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
      }
      .brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.18);
        border: 1px solid rgba(255, 217, 126, 0.28);
        color: #ffe6a4;
        font-size: 24px;
        font-weight: 700;
      }
      .brand-badge::before {
        content: "";
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: #ffcf57;
        box-shadow: 0 0 0 5px rgba(255, 207, 87, 0.14);
      }
      .draw-date {
        padding: 16px 24px;
        border-radius: 18px;
        background: rgba(0, 0, 0, 0.22);
        border: 1px solid rgba(255, 217, 126, 0.18);
        text-align: center;
      }
      .draw-date__label {
        font-size: 26px;
        color: #ffe2a0;
      }
      .draw-date__value {
        margin-top: 8px;
        font-size: 46px;
        font-weight: 800;
        color: #ffffff;
      }
      .title-block {
        margin-top: 34px;
      }
      .lottery-name {
        font-size: 108px;
        line-height: 1.02;
        font-weight: 900;
        color: #ffd45e;
        letter-spacing: 0.5px;
        -webkit-text-stroke: 8px #1f0700;
        paint-order: stroke fill;
        text-shadow:
          0 8px 0 #1f0700,
          0 16px 22px rgba(0,0,0,0.26),
          0 0 18px rgba(255, 226, 150, 0.15);
      }
      .divider {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-top: 20px;
      }
      .divider__line {
        flex: 1;
        height: 4px;
        border-radius: 999px;
        background: linear-gradient(90deg, transparent 0%, #f5c54d 22%, #f5c54d 78%, transparent 100%);
      }
      .divider__ornament {
        font-size: 42px;
        color: #ffd86a;
        text-shadow: 0 2px 0 rgba(0,0,0,0.35);
      }
      .result-head {
        margin-top: 22px;
        display: flex;
        align-items: baseline;
        gap: 18px;
        flex-wrap: wrap;
      }
      .result-head__label {
        font-size: 66px;
        font-weight: 900;
        color: #fff;
        -webkit-text-stroke: 6px #120400;
        paint-order: stroke fill;
        text-shadow: 0 6px 0 #120400;
      }
      .result-head__draw {
        font-size: 36px;
        font-weight: 700;
        color: #ffe6b0;
      }
      .results {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
        margin-top: 22px;
      }
      .result-chip {
        padding: 22px 24px 24px;
        border-radius: 24px;
        border: 2px solid rgba(255, 236, 185, 0.2);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
      }
      .result-chip.gold {
        background: linear-gradient(180deg, rgba(58, 20, 0, 0.66) 0%, rgba(96, 29, 0, 0.78) 100%);
      }
      .result-chip.green {
        background: linear-gradient(180deg, rgba(0, 78, 32, 0.66) 0%, rgba(0, 111, 44, 0.78) 100%);
      }
      .result-chip.orange {
        background: linear-gradient(180deg, rgba(88, 40, 0, 0.66) 0%, rgba(120, 52, 0, 0.78) 100%);
      }
      .result-chip__label {
        font-size: 30px;
        color: #ffe5a6;
        font-weight: 700;
      }
      .result-chip__value {
        margin-top: 10px;
        font-size: 98px;
        line-height: 1;
        font-weight: 900;
        color: #ffffff;
        text-shadow: 0 5px 0 rgba(0,0,0,0.34);
      }
    </style>
  </head>
  <body>
    <div class="frame">
      <main class="poster">
        <div class="top-row">
          <div class="brand-badge">${TEXT.result}</div>
          <div class="draw-date">
            <div class="draw-date__label">${TEXT.draw}</div>
            <div class="draw-date__value">${escapeHtml(data.draw_date_display || data.draw_date || '-')}</div>
          </div>
        </div>

        <section class="title-block">
          <div class="lottery-name">${escapeHtml(data.lottery_name || '-')}</div>
        </section>

        <div class="divider">
          <div class="divider__line"></div>
          <div class="divider__ornament">༻༺</div>
          <div class="divider__line"></div>
        </div>

        <div class="result-head">
          <div class="result-head__label">${TEXT.result}</div>
          <div class="result-head__draw">${TEXT.draw} ${escapeHtml(data.draw_date_display || data.draw_date || '-')}</div>
        </div>

        <section class="results">
          ${renderResultChip(TEXT.top3, data.three_top, 'gold')}
          ${renderResultChip(TEXT.top2, data.two_top, 'green')}
          ${renderResultChip(TEXT.bot2, data.two_bot, 'orange')}
        </section>
      </main>
    </div>
  </body>
  </html>`;

  const launchOptions = {
    headless: 'new',
    userDataDir,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-crash-reporter',
      '--disable-crashpad',
      '--disable-crashpad-for-testing',
      '--no-first-run',
      '--no-default-browser-check',
    ],
  };

  const executablePath = findChromeExecutable();
  if (executablePath) {
    launchOptions.executablePath = executablePath;
  }

  const browser = await puppeteer.launch(launchOptions);

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 720, deviceScaleFactor: 2 });
    await page.setContent(html, { waitUntil: 'networkidle0' });
    await page.evaluate(async () => {
      if (document.fonts?.ready) {
        await document.fonts.ready;
      }
    });
    await new Promise((resolve) => setTimeout(resolve, 600));
    await page.screenshot({
      path: outputPath,
      type: 'png',
    });
  } finally {
    await browser.close();
  }
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
