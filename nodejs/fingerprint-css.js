import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { fileURLToPath, pathToFileURL } from 'node:url';

export function fingerprintCss(file) {
  const css = fs.readFileSync(file, 'utf8').replace(/^\/\* coriander-version:[a-f0-9]{64} \*\/\n/, '');
  const version = createHash('sha256').update(css).digest('hex');
  fs.writeFileSync(file, '/* coriander-version:' + version + ' */\n' + css);
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  try {
    fingerprintCss(process.argv[2] ?? fileURLToPath(new URL('../public/assets/css/output.css', import.meta.url)));
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
