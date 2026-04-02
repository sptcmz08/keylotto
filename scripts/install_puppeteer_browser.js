import { spawn } from 'child_process';
import fs from 'fs';
import path from 'path';

const rootDir = process.cwd();
const cacheDir = path.join(rootDir, '.cache', 'puppeteer');

fs.mkdirSync(cacheDir, { recursive: true });

const env = {
  ...process.env,
  PUPPETEER_CACHE_DIR: cacheDir,
};

if (!env.HOME) {
  env.HOME = rootDir;
}

if (process.platform === 'win32' && !env.USERPROFILE) {
  env.USERPROFILE = rootDir;
}

const command = process.platform === 'win32' ? 'npx.cmd' : 'npx';
const child = spawn(command, ['puppeteer', 'browsers', 'install', 'chrome'], {
  stdio: 'inherit',
  env,
  cwd: rootDir,
});

child.on('exit', (code) => {
  process.exit(code ?? 1);
});

child.on('error', (error) => {
  console.error(error);
  process.exit(1);
});
