import { spawn } from 'child_process';
import path from 'path';
import fs from 'node:fs';

const tailwindPackage = path.join('node_modules', '@tailwindcss', 'cli');
const tailwindBinary = path.join(tailwindPackage, JSON.parse(fs.readFileSync(path.join(tailwindPackage, 'package.json'), 'utf8')).bin.tailwindcss);

let shuttingDown = false;

const watchers = [
  ['watch-ts', process.execPath, ['build.js', '--watch']],
  [
    'watch-tw',
    process.execPath,
    [
      tailwindBinary,
      '-i',
      '../public/assets/css/input.css',
      '-o',
      '../public/assets/css/output.css',
      '--watch=always',
      '--minify',
    ],
  ],
];

const children = watchers.map(([label, command, args]) => {
  const child = spawn(command, args, {
    stdio: ['inherit', 'pipe', 'pipe'],
  });

  child.stdout.on('data', chunk => {
    process.stdout.write(`[${label}] ${chunk}`);
  });

  child.stderr.on('data', chunk => {
    process.stderr.write(`[${label}] ${chunk}`);
  });

  child.on('error', error => {
    console.error(`[${label}] failed to start: ${error.message}`);
    stopWatchers(1);
  });

  child.on('exit', code => {
    if (shuttingDown) {
      return;
    }

    console.error(`[${label}] exited with code ${code}. Stopping watchers.`);
    stopWatchers(code ?? 1);
  });

  return child;
});

function stopWatchers(exitCode = 0) {
  if (shuttingDown) {
    return;
  }

  shuttingDown = true;
  process.stdin.pause();
  for (const child of children) {
    if (!child.killed) {
      child.kill();
    }
  }

  process.exitCode = exitCode;
}

process.on('SIGINT', () => stopWatchers(0));
process.on('SIGTERM', () => stopWatchers(0));
process.stdin.once('end', () => stopWatchers(0));
process.stdin.resume();
