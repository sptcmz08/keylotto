import fs from 'fs';
import fsp from 'fs/promises';
import os from 'os';
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
  top3: '3 \u0e15\u0e31\u0e27\u0e1a\u0e19',
  top2: '2 \u0e15\u0e31\u0e27\u0e1a\u0e19',
  bot2: '2 \u0e15\u0e31\u0e27\u0e25\u0e48\u0e32\u0e07',
  closeTitle: '\u0e1b\u0e34\u0e14\u0e23\u0e31\u0e1a',
  resultOut: '\u0e1c\u0e25\u0e2d\u0e2d\u0e01',
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
  const envCandidates = [
    process.env.PUPPETEER_EXECUTABLE_PATH,
    process.env.CHROME_BIN,
    process.env.GOOGLE_CHROME_BIN,
  ].filter(Boolean);
  const directCandidates = [
    path.join(cacheDir, 'chrome', 'linux-146.0.7680.153', 'chrome-linux64', 'chrome'),
    path.join(cacheDir, 'chrome', 'win64-146.0.7680.153', 'chrome-win64', 'chrome.exe'),
    path.join(cacheDir, 'chrome', 'mac-146.0.7680.153', 'chrome-mac-x64', 'Google Chrome for Testing.app', 'Contents', 'MacOS', 'Google Chrome for Testing'),
    path.join(cacheDir, 'chrome', 'mac_arm-146.0.7680.153', 'chrome-mac-arm64', 'Google Chrome for Testing.app', 'Contents', 'MacOS', 'Google Chrome for Testing'),
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
    '/opt/google/chrome/chrome',
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  ];

  for (const candidate of [...envCandidates, ...directCandidates]) {
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
  const tempRootDir = path.join(os.tmpdir(), 'keylotto-puppeteer');
  const configDir = path.join(tempRootDir, 'xdg-config');
  const profileRootDir = path.join(tempRootDir, 'profiles');

  await fsp.mkdir(cacheDir, { recursive: true });
  await fsp.mkdir(configDir, { recursive: true });
  await fsp.mkdir(profileRootDir, { recursive: true });

  process.env.PUPPETEER_CACHE_DIR = cacheDir;
  process.env.XDG_CACHE_HOME = baseCacheDir;
  process.env.XDG_CONFIG_HOME = configDir;
  if (!process.env.HOME) {
    process.env.HOME = rootDir;
  }

  const userDataDir = await fsp.mkdtemp(path.join(profileRootDir, 'session-'));
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

const imageMimeType = (filePath) => {
  const extension = path.extname(filePath).toLowerCase();
  if (extension === '.png') {
    return 'image/png';
  }
  if (extension === '.jpg' || extension === '.jpeg') {
    return 'image/jpeg';
  }
  if (extension === '.webp') {
    return 'image/webp';
  }
  return '';
};

const buildPosterBackground = (backgroundImagePath) => {
  if (!backgroundImagePath || !fs.existsSync(backgroundImagePath)) {
    return `
      background:
        linear-gradient(180deg, rgba(255,255,255,0.03), rgba(0,0,0,0.06)),
        transparent;
    `;
  }

  const mimeType = imageMimeType(backgroundImagePath);
  if (!mimeType) {
    return `
      background:
        linear-gradient(180deg, rgba(255,255,255,0.03), rgba(0,0,0,0.06)),
        transparent;
    `;
  }

  const encoded = fs.readFileSync(backgroundImagePath).toString('base64');
  return `
    background:
      linear-gradient(180deg, rgba(12, 0, 0, 0.16), rgba(0, 0, 0, 0.22)),
      linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.08)),
      url("data:${mimeType};base64,${encoded}") center/cover no-repeat;
  `;
};

const main = async () => {
  const raw = await fsp.readFile(inputPath, 'utf8');
  const data = JSON.parse(raw);

  await fsp.mkdir(path.dirname(outputPath), { recursive: true });
  const { userDataDir } = await ensureWritableBrowserDirs();
  const embeddedFontFace = buildEmbeddedFontFace();
  const posterBackground = buildPosterBackground(data.background_image_path);
  const mode = String(data.mode || 'result').trim().toLowerCase();
  const isBetClose = mode === 'bet_close';
  const resultTimeMarkup = String(data.result_time_display || '').trim() !== ''
    ? `
        <div class="time-pill">
          <span class="time-pill__label">${TEXT.resultOut}</span>
          <span class="time-pill__value">${escapeHtml(data.result_time_display)}</span>
          <span class="time-pill__suffix">น.</span>
        </div>`
    : '';
  const closeTitleMarkup = `
    <div class="close-hero">
      <div class="close-hero__title">${escapeHtml(data.close_title || TEXT.closeTitle)}</div>
      <div class="close-hero__lottery">${escapeHtml(data.lottery_name || '-')}</div>
      <div class="close-hero__divider"></div>
      ${resultTimeMarkup}
      <div class="close-hero__subtitle">${escapeHtml(data.close_subtitle || 'รอลุ้นผลเฮง ๆ ปัง ๆ ไปด้วยกัน')}</div>
    </div>`;
  const mainContentMarkup = isBetClose
    ? `
          <section class="hero hero--close">
            ${closeTitleMarkup}
          </section>`
    : `
          <section class="hero">
            <div class="lottery-name">${escapeHtml(data.lottery_name || '-')}</div>
            ${resultTimeMarkup}
          </section>

          <section class="results">
            ${renderResultChip(TEXT.top3, data.three_top, 'gold')}
            ${renderResultChip(TEXT.top2, data.two_top, 'green')}
            ${renderResultChip(TEXT.bot2, data.two_bot, 'orange')}
          </section>`;

  const html = `
  <!doctype html>
  <html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      ${embeddedFontFace}
      * { box-sizing: border-box; }
      html {
        width: 1920px;
        height: 1080px;
      }
      body {
        width: 1920px;
        height: 1080px;
        margin: 0;
        overflow: hidden;
        font-family: "LineThai", "Tahoma", sans-serif;
        text-rendering: geometricPrecision;
        -webkit-font-smoothing: antialiased;
        background:
          radial-gradient(circle at 20% 18%, rgba(255, 186, 72, 0.18), transparent 18%),
          radial-gradient(circle at 80% 74%, rgba(255, 122, 72, 0.16), transparent 20%),
          linear-gradient(180deg, #860707 0%, #ad0f0f 42%, #7d0606 100%);
        color: #fff4c8;
      }
      body::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
          radial-gradient(circle at center, rgba(255, 210, 100, 0.08) 0 3px, transparent 3px 100%),
          radial-gradient(circle at center, rgba(255, 210, 100, 0.06) 0 1.5px, transparent 1.5px 100%);
        background-size: 120px 120px, 42px 42px;
        background-position: 0 0, 21px 21px;
        opacity: 0.22;
        pointer-events: none;
      }
      body::after {
        content: "";
        position: absolute;
        inset: 0;
        background:
          linear-gradient(180deg, rgba(0,0,0,0.1), rgba(0,0,0,0.18)),
          radial-gradient(circle at 50% 18%, rgba(255, 218, 128, 0.12), transparent 24%);
        pointer-events: none;
      }
      .frame {
        width: 100%;
        height: 100%;
      }
      .poster {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
        ${posterBackground}
      }
      .poster::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
          linear-gradient(180deg, rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.2)),
          linear-gradient(90deg, rgba(255, 212, 96, 0.08), transparent 28%, transparent 72%, rgba(255, 212, 96, 0.08));
        pointer-events: none;
      }
      .poster::after {
        content: "";
        position: absolute;
        inset: 14px;
        border: 2px solid rgba(255, 216, 114, 0.34);
        box-shadow: inset 0 0 0 1px rgba(255, 245, 202, 0.06);
        pointer-events: none;
      }
      .content {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        padding: 18px 22px 24px;
      }
      .poster-top {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        min-height: 108px;
      }
      .title-meta__text {
        min-height: 84px;
        padding: 12px 30px;
        border-radius: 999px;
        border: 3px solid rgba(255, 142, 67, 0.72);
        background: rgba(62, 14, 3, 0.48);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 38px;
        font-weight: 900;
        color: #ffe6b0;
        letter-spacing: 0.3px;
        -webkit-text-stroke: 1.4px rgba(57, 20, 0, 0.7);
        paint-order: stroke fill;
        text-shadow: 0 4px 0 rgba(0,0,0,0.34);
        white-space: nowrap;
        box-shadow:
          inset 0 1px 0 rgba(255,255,255,0.08),
          0 12px 24px rgba(0,0,0,0.18);
      }
      .hero {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 22px;
        padding: 10px 64px 20px;
      }
      .hero--close {
        padding-top: 56px;
        padding-bottom: 84px;
      }
      .lottery-name {
        max-width: 1720px;
        font-size: clamp(180px, 11.8vw, 226px);
        line-height: 0.86;
        font-weight: 900;
        color: #ffd45e;
        text-align: center;
        letter-spacing: 0.2px;
        -webkit-text-stroke: 18px #1f0700;
        paint-order: stroke fill;
        text-wrap: balance;
        word-break: break-word;
        text-shadow:
          0 16px 0 #1f0700,
          0 28px 34px rgba(0,0,0,0.32),
          0 0 28px rgba(255, 226, 150, 0.22);
      }
      .time-pill {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        padding: 18px 34px;
        border-radius: 999px;
        border: 3px solid rgba(255, 214, 116, 0.5);
        background: rgba(48, 9, 2, 0.44);
        box-shadow:
          inset 0 1px 0 rgba(255,255,255,0.08),
          0 14px 28px rgba(0,0,0,0.18);
      }
      .time-pill__label,
      .time-pill__suffix {
        font-size: 38px;
        line-height: 1;
        font-weight: 900;
        color: #fff0c2;
        -webkit-text-stroke: 1.6px rgba(37, 10, 0, 0.56);
        paint-order: stroke fill;
        text-shadow: 0 4px 0 rgba(0,0,0,0.3);
      }
      .time-pill__value {
        font-size: 88px;
        line-height: 0.95;
        font-weight: 900;
        color: #5af186;
        letter-spacing: 0.5px;
        -webkit-text-stroke: 2.6px rgba(8, 58, 18, 0.76);
        paint-order: stroke fill;
        text-shadow:
          0 5px 0 rgba(0, 59, 23, 0.45),
          0 10px 18px rgba(0,0,0,0.18);
      }
      .title-meta {
        display: flex;
        justify-content: center;
      }
      .close-hero {
        width: min(1700px, 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 30px;
      }
      .close-hero__title {
        font-size: clamp(182px, 11.2vw, 228px);
        line-height: 0.9;
        font-weight: 900;
        color: #ffe169;
        text-align: center;
        -webkit-text-stroke: 18px #1f0700;
        paint-order: stroke fill;
        text-shadow:
          0 16px 0 #1f0700,
          0 28px 34px rgba(0,0,0,0.32),
          0 0 28px rgba(255, 226, 150, 0.22);
      }
      .close-hero__lottery {
        font-size: clamp(128px, 8.4vw, 176px);
        line-height: 0.98;
        font-weight: 900;
        color: #ffd45e;
        text-align: center;
        -webkit-text-stroke: 14px #1f0700;
        paint-order: stroke fill;
        text-shadow:
          0 12px 0 #1f0700,
          0 24px 28px rgba(0,0,0,0.28);
      }
      .close-hero__divider {
        width: min(760px, 74%);
        height: 8px;
        border-radius: 999px;
        background: linear-gradient(90deg, rgba(255, 212, 96, 0.0), rgba(255, 212, 96, 0.9), rgba(255, 212, 96, 0.0));
        box-shadow: 0 0 16px rgba(255, 212, 96, 0.24);
      }
      .close-hero .time-pill {
        margin-top: 6px;
        margin-bottom: 8px;
        transform: scale(1.08);
        transform-origin: center;
      }
      .close-hero__subtitle {
        font-size: 46px;
        line-height: 1.32;
        font-weight: 900;
        color: #fff4cf;
        text-align: center;
        -webkit-text-stroke: 1.8px rgba(38, 10, 0, 0.54);
        paint-order: stroke fill;
        text-shadow: 0 4px 0 rgba(0,0,0,0.3);
      }
      .results {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 22px;
        align-items: stretch;
      }
      .result-chip {
        min-height: 356px;
        padding: 28px 22px 30px;
        border-radius: 28px;
        border: 4px solid rgba(255, 177, 73, 0.54);
        box-shadow:
          inset 0 1px 0 rgba(255,255,255,0.08),
          inset 0 0 0 2px rgba(255, 247, 222, 0.06),
          0 18px 28px rgba(0,0,0,0.18);
        display: flex;
        flex-direction: column;
        justify-content: center;
      }
      .result-chip.gold {
        background: linear-gradient(180deg, rgba(58, 20, 0, 0.7) 0%, rgba(96, 29, 0, 0.84) 100%);
      }
      .result-chip.green {
        background: linear-gradient(180deg, rgba(0, 78, 32, 0.7) 0%, rgba(0, 111, 44, 0.84) 100%);
      }
      .result-chip.orange {
        background: linear-gradient(180deg, rgba(88, 40, 0, 0.7) 0%, rgba(120, 52, 0, 0.84) 100%);
      }
      .result-chip__label {
        font-size: 54px;
        color: #ffe5a6;
        font-weight: 900;
        letter-spacing: 0.28px;
        text-align: center;
        -webkit-text-stroke: 2px rgba(57, 20, 0, 0.6);
        paint-order: stroke fill;
        text-shadow: 0 4px 0 rgba(0,0,0,0.32);
      }
      .result-chip__value {
        margin-top: 22px;
        font-size: clamp(182px, 11vw, 238px);
        line-height: 0.88;
        font-weight: 900;
        color: #ffffff;
        text-align: center;
        letter-spacing: -3px;
        -webkit-text-stroke: 5px rgba(39, 10, 0, 0.44);
        paint-order: stroke fill;
        text-shadow:
          0 8px 0 rgba(24, 7, 0, 0.5),
          0 16px 22px rgba(0,0,0,0.2);
      }
    </style>
  </head>
  <body>
    <div class="frame">
      <main class="poster">
        <div class="content">
          <header class="poster-top">
            <div class="title-meta">
              <div class="title-meta__text">${TEXT.draw} ${escapeHtml(data.draw_date_display || data.draw_date || '-')}</div>
            </div>
          </header>

          ${mainContentMarkup}
        </div>
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
    await page.setViewport({ width: 1920, height: 1080, deviceScaleFactor: 2 });
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
    await fsp.rm(userDataDir, { recursive: true, force: true }).catch(() => {});
  }
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
