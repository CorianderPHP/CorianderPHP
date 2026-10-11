import esbuild from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const projectRoot = fileURLToPath(new URL('..', import.meta.url));

export function sourceFiles(directory) {
  if (!fs.existsSync(directory)) return [];
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const file = path.join(directory, entry.name);
    return entry.isDirectory() ? sourceFiles(file) : entry.isFile() ? [file] : [];
  }).sort();
}

export function entries(root) {
  const directory = path.join(root, 'src/Views');
  return sourceFiles(directory).filter(file => {
    if (!file.endsWith('.ts')) return false;
    const segments = path.relative(directory, file).split(path.sep);
    if (segments.slice(0, -1).some(segment => /^[_.]/.test(segment))) return false;
    if (path.basename(file) === '_script.ts') return true;
    if (/^[_.]/.test(path.basename(file))) return false;
    return fs.lstatSync(file.slice(0, -3) + '.get.php', { throwIfNoEntry: false })?.isFile() ?? false;
  });
}

function outputPath(root, file) {
  const directory = path.join(root, 'public/assets/js/pages');
  const relative = path.relative(directory, file);
  if ((relative === '..' || relative.startsWith('..' + path.sep)) || path.isAbsolute(relative)) throw new Error('Asset output escapes its directory');
  // Never follow an output symlink while publishing a generated bundle.
  let current = root;
  for (const segment of path.relative(root, file).split(path.sep)) {
    current = path.join(current, segment);
    if (fs.lstatSync(current, { throwIfNoEntry: false })?.isSymbolicLink()) throw new Error('Asset output must not follow symlinks: ' + current);
  }
  return file;
}

function publish(root, files) {
  const directory = path.join(root, 'public/assets/js/pages');
  const manifest = outputPath(root, path.join(directory, '.generated.json'));
  const previous = fs.existsSync(manifest) ? JSON.parse(fs.readFileSync(manifest, 'utf8')) : [];
  if (!Array.isArray(previous) || previous.some(name => typeof name !== 'string' || !name.endsWith('.js'))) {
    throw new Error('Invalid generated asset list');
  }
  const current = files.map(file => path.relative(directory, file.path).replaceAll('\\', '/'));
  const stale = previous.filter(name => !current.includes(name)).map(name => outputPath(root, path.resolve(directory, name)));
  for (const file of files) outputPath(root, file.path);
  if (!files.length && !previous.length) return;
  for (const file of files) {
    fs.mkdirSync(path.dirname(file.path), { recursive: true });
    fs.writeFileSync(file.path, file.contents);
  }
  // Only files recorded by a previous successful build belong to cleanup.
  for (const file of stale) if (fs.existsSync(file)) fs.unlinkSync(file);
  fs.mkdirSync(directory, { recursive: true });
  fs.writeFileSync(manifest, JSON.stringify(current.sort()) + '\n');
}

export function options(root, minify = false) {
  return {
    entryPoints: entries(root),
    outbase: path.join(root, 'src/Views'),
    outdir: path.join(root, 'public/assets/js/pages'),
    bundle: true,
    format: 'esm',
    splitting: true,
    chunkNames: '_chunks/[name]-[hash]',
    platform: 'browser',
    target: 'es2020',
    minify,
    write: false,
  };
}

export async function build(root = projectRoot, minify = false) {
  const settings = options(root, minify);
  const result = settings.entryPoints.length ? await esbuild.build(settings) : { outputFiles: [] };
  for (const file of result.outputFiles) {
    const version = createHash('sha256').update(file.contents).digest('hex');
    file.contents = Buffer.concat([Buffer.from('// coriander-version:' + version + '\n'), file.contents]);
  }
  publish(root, result.outputFiles);
  return result.outputFiles;
}

export async function checkTypes(root = projectRoot) {
  const config = path.join(root, 'nodejs/tsconfig.json');
  if (!fs.existsSync(config) || !['src/Views', 'src/Scripts'].some(directory => sourceFiles(path.join(root, directory)).some(file => file.endsWith('.ts')))) return;
  await new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [fileURLToPath(new URL('node_modules/typescript/bin/tsc', import.meta.url)), '--noEmit', '-p', config], { stdio: 'inherit' });
    child.once('error', reject);
    child.once('exit', code => code === 0 ? resolve() : reject(new Error('TypeScript check failed')));
  });
}

export async function watch(root = projectRoot) {
  const source = path.join(root, 'src');
  fs.mkdirSync(source, { recursive: true });
  let timer, running, dirty = false, stopped = false;
  const rebuild = async () => {
    dirty = true;
    if (running || stopped) return;
    running = (async () => {
      while (dirty && !stopped) {
        dirty = false;
        try {
          await checkTypes(root);
          await build(root);
          console.log('Page scripts rebuilt.');
        } catch (error) {
          console.error(error.message);
        }
      }
    })();
    try { await running; } finally { running = undefined; }
  };
  const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(rebuild, 100);
  };
  const watcher = fs.watch(source, { recursive: true }, (_, filename) => {
    if (!filename || /^(Views|Scripts)([\\/]|$)/.test(filename)) schedule();
  });
  const config = path.join(root, 'nodejs/tsconfig.json');
  const configWatcher = fs.existsSync(path.dirname(config)) ? fs.watch(path.dirname(config), (_, filename) => {
    if (!filename || filename === 'tsconfig.json') schedule();
  }) : null;
  await rebuild();
  return { async dispose() {
    stopped = true;
    clearTimeout(timer);
    watcher.close();
    configWatcher?.close();
    await running;
  }};
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  try {
    if (process.argv.slice(2).some(arg => !['--watch', '--minify', '--check'].includes(arg))) throw new Error('Use --watch, --minify or --check');
    if (process.argv.includes('--watch')) {
      const context = await watch();
      for (const signal of ['SIGINT', 'SIGTERM']) process.once(signal, async () => {
        await context.dispose();
        process.exit(0);
      });
    } else if (process.argv.includes('--check')) {
      await checkTypes();
    } else {
      await build(projectRoot, process.argv.includes('--minify'));
    }
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
