import { spawn } from 'child_process';
import path from 'path';

const isWindows = process.platform === 'win32';
const tailwindBinary = path.join(
  'node_modules',
  '.bin',
  isWindows ? 'tailwindcss.cmd' : 'tailwindcss',
);

let shuttingDown = false;

const watchers = [
  ['watch-ts', process.execPath, ['build.watch.js'], false],
  [
    'watch-tw',
    tailwindBinary,
    [
      '-i',
      '../public/assets/css/input.css',
      '-o',
      '../public/assets/css/output.css',
      '--watch=always',
      '--minify',
    ],
    isWindows,
  ],
];

const children = watchers.map(([label, command, args, shell]) => {
  const child = spawn(command, args, {
    stdio: ['inherit', 'pipe', 'pipe'],
    shell,
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
    if (code === 0 || shuttingDown) {
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
  for (const child of children) {
    if (!child.killed) {
      child.kill();
    }
  }

  process.exitCode = exitCode;
}

process.on('SIGINT', () => stopWatchers(0));
process.on('SIGTERM', () => stopWatchers(0));
