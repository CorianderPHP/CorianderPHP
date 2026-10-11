import esbuild from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';
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
  for (const file of files) outputPath(root, file.path);
  for (const file of files) {
    fs.mkdirSync(path.dirname(file.path), { recursive: true });
    fs.writeFileSync(file.path, file.contents);
  }
}

export function options(root, minify = false) {
  return {
    entryPoints: entries(root),
    outbase: path.join(root, 'src/Views'),
    outdir: path.join(root, 'public/assets/js/pages'),
    bundle: true,
    format: 'esm',
    platform: 'browser',
    target: 'es2020',
    minify,
    write: false,
  };
}

export async function build(root = projectRoot, minify = false) {
  const settings = options(root, minify);
  if (!settings.entryPoints.length) return [];
  const result = await esbuild.build(settings);
  publish(root, result.outputFiles);
  return result.outputFiles;
}

export async function watch(root = projectRoot) {
  const settings = options(root);
  const context = await esbuild.context({
    ...settings,
    plugins: [{
      name: 'publish-assets',
      setup(builder) {
        builder.onEnd(result => {
          if (!result.errors.length) publish(root, result.outputFiles);
        });
      },
    }],
  });
  await context.watch();
  return context;
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  try {
    if (process.argv.slice(2).some(arg => !['--watch', '--minify'].includes(arg))) throw new Error('Use --watch or --minify');
    if (process.argv.includes('--watch')) {
      const context = await watch();
      for (const signal of ['SIGINT', 'SIGTERM']) process.once(signal, async () => {
        await context.dispose();
        process.exit(0);
      });
    } else {
      await build(projectRoot, process.argv.includes('--minify'));
    }
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
