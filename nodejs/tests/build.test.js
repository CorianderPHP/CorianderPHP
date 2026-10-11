import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { build, watch, checkTypes } from '../build.js';

function fixture(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'coriander-assets-'));
  t.after(() => {
    assert.ok(root.startsWith(path.join(os.tmpdir(), 'coriander-assets-')));
    fs.rmSync(root, { recursive: true, force: true });
  });
  const put = (name, content) => {
    const file = path.join(root, name);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content);
    if (name.endsWith('/index.ts')) fs.writeFileSync(file.slice(0, -3) + '.get.php', 'not executed by build');
  };
  return { root, put };
}

test('builds nested entries and preserves manual assets', async t => {
  const { root, put } = fixture(t);
  put('src/Views/admin/users/index.ts', 'import { value } from "../helper"; console.log(value);');
  put('src/Views/admin/helper.ts', 'export const value: string = "shared-value";');
  put('public/assets/js/manual.js', 'manual');
  const files = await build(root);
  assert.equal(files.length, 1);
  assert.match(fs.readFileSync(path.join(root, 'public/assets/js/pages/admin/users/index.js'), 'utf8'), /shared-value/);
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/manual.js'), 'utf8'), 'manual');
  await build(root, true);
  assert.ok(fs.statSync(files[0].path).size < files[0].contents.length);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/pages/admin/helper.js')), false);
});

test('failed compilation preserves all previous bundles', async t => {
  const { root, put } = fixture(t);
  put('src/Views/home/index.ts', 'console.log("before");');
  await build(root);
  const previous = fs.readFileSync(path.join(root, 'public/assets/js/pages/home/index.js'), 'utf8');
  const manifest = fs.readFileSync(path.join(root, 'public/assets/js/pages/.generated.json'), 'utf8');
  put('src/Views/home/index.ts', 'console.log("after");');
  put('src/Views/broken/index.ts', 'const = invalid;');
  await assert.rejects(build(root));
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/pages/home/index.js'), 'utf8'), previous);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/pages/broken/index.js')), false);
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/pages/.generated.json'), 'utf8'), manifest);
});

test('empty source works without an output directory', async t => {
  const { root, put } = fixture(t);
  put('nodejs/tsconfig.json', '{"include":["../src/Views","../src/Scripts"]}');
  await checkTypes(root);
  assert.deepEqual(await build(root), []);
});

test('generated scripts carry stable content versions across rapid builds and shared edits', async t => {
  const { root, put } = fixture(t);
  put('src/Scripts/value.ts', 'export const value = "one";');
  put('src/Views/_script.ts', 'import { value } from "../Scripts/value"; console.log(value);');
  put('src/Views/index.ts', 'import { value } from "../Scripts/value"; console.log(value);');
  const versions = async () => {
    const files = await build(root, true);
    const result = {};
    for (const file of files) {
      const source = Buffer.from(file.contents).toString();
      const newline = source.indexOf('\n');
      const version = source.slice(0, newline).match(/^\/\/ coriander-version:([a-f0-9]{64})$/)?.[1];
      assert.equal(version, createHash('sha256').update(source.slice(newline + 1)).digest('hex'));
      fs.utimesSync(file.path, 1700000000, 1700000000);
      result[path.relative(root, file.path)] = version;
    }
    return result;
  };
  const first = await versions();
  assert.deepEqual(await versions(), first);
  put('src/Scripts/value.ts', 'export const value = "two";');
  const changed = await versions();
  for (const entry of ['_script.js', 'index.js']) {
    const name = path.join('public/assets/js/pages', entry);
    assert.notEqual(changed[name], first[name]);
  }
});

test('discovers directory and dynamic page entries without executing PHP or bundling private helpers', async t => {
  const { root, put } = fixture(t);
  put('src/Views/_script.ts', 'console.log("root-shared");');
  put('src/Views/products/_script.ts', 'console.log("products-shared");');
  put('src/Views/products/[id].get.php', '<?php throw new RuntimeException("never execute");');
  put('src/Views/products/[id].ts', 'console.log("dynamic-page");');
  put('src/Views/teams/[teamId]/users/[UserID]/index.ts', 'console.log("nested-page");');
  put('src/Views/_draft/index.ts', 'console.log("private");');
  put('src/Views/_private.get.php', 'private');
  put('src/Views/_private.ts', 'console.log("private");');
  put('src/Views/unpaired.ts', 'console.log("helper");');
  put('src/Views/post.post.php', 'post');
  put('src/Views/post.ts', 'console.log("post");');
  const files = await build(root);
  assert.deepEqual(files.map(file => path.relative(path.join(root, 'public/assets/js/pages'), file.path).replaceAll('\\', '/')).sort(),
    ['_script.js', 'products/[id].js', 'products/_script.js', 'teams/[teamId]/users/[UserID]/index.js']);
});

test('generated output cannot follow a destination symlink', async t => {
  const { root, put } = fixture(t);
  put('src/Views/home/index.ts', 'console.log("generated");');
  put('outside/index.js', 'untouched');
  fs.mkdirSync(path.join(root, 'public/assets/js/pages/home'), { recursive: true });
  try {
    fs.symlinkSync(path.join(root, 'outside/index.js'), path.join(root, 'public/assets/js/pages/home/index.js'));
  } catch (error) {
    if (['EPERM', 'EACCES'].includes(error.code)) return t.skip('Symlink creation requires host privileges');
    throw error;
  }
  await assert.rejects(build(root), /symlink/);
  assert.equal(fs.readFileSync(path.join(root, 'outside/index.js'), 'utf8'), 'untouched');
});

test('multiple views import reusable values and types without separate helper bundles', async t => {
  const { root, put } = fixture(t);
  put('src/Scripts/types.ts', 'export interface PageState { title: string }');
  put('src/Scripts/menu.ts', 'export function menu(title: string) { return "menu:" + title; }');
  put('src/Scripts/http.ts', 'export function endpoint(id: number) { return "/users/" + id; }');
  const source = 'import type { PageState } from "../../Scripts/types"; import { menu } from "../../Scripts/menu"; import { endpoint } from "../../Scripts/http"; const page: PageState = { title: "shared" }; console.log(menu(page.title), endpoint(42));';
  for (const page of ['about', 'products']) put('src/Views/' + page + '/index.ts', source);
  const files = await build(root);
  const text = files.map(file => Buffer.from(file.contents).toString()).join('\n');
  assert.match(text, /menu:/);
  assert.match(text, /\/users\//);
  assert.doesNotMatch(text, /PageState|types\.ts/);
  for (const page of ['about', 'products']) assert.ok(files.some(file => file.path === path.join(root, 'public/assets/js/pages', page, 'index.js')));
  const compiler = fileURLToPath(new URL('../node_modules/typescript/bin/tsc', import.meta.url));
  const args = [compiler, '--noEmit', '--strict', '--skipLibCheck', path.join(root, 'src/Views/about/index.ts'), path.join(root, 'src/Views/products/index.ts')];
  assert.equal(spawnSync(process.execPath, args, { encoding: 'utf8' }).status, 0);
  put('src/Scripts/types.ts', 'export interface PageState { title: number }');
  const invalid = spawnSync(process.execPath, args, { encoding: 'utf8' });
  assert.notEqual(invalid.status, 0);
  assert.match(invalid.stdout, /not assignable/);
  assert.equal(fs.existsSync(path.join(root, 'src/Scripts/types.js')), false);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/pages/Scripts')), false);
});

test('directory and page entries share one module instance and clean obsolete chunks', async t => {
  const { root, put } = fixture(t);
  put('package.json', '{"type":"module"}');
  put('src/Scripts/state.ts', 'globalThis.moduleRuns = (globalThis.moduleRuns ?? 0) + 1; export const state = { value: 42 };');
  put('src/Views/_script.ts', 'import { state } from "../Scripts/state"; globalThis.rootState = state;');
  put('src/Views/shop/_script.ts', 'import { state } from "../../Scripts/state"; globalThis.directoryState = state;');
  put('src/Views/shop/index.ts', 'import { state } from "../../Scripts/state"; globalThis.pageState = state; export const loaded = import("../../Scripts/state");');
  put('public/assets/js/pages/_chunks/manual.js', 'manual');
  const files = await build(root);
  const chunk = files.find(file => path.dirname(file.path).endsWith('_chunks'));
  assert.ok(chunk);
  globalThis.moduleRuns = 0;
  try {
    await import(pathToFileURL(path.join(root, 'public/assets/js/pages/_script.js')));
    await import(pathToFileURL(path.join(root, 'public/assets/js/pages/shop/_script.js')));
    const page = await import(pathToFileURL(path.join(root, 'public/assets/js/pages/shop/index.js')));
    assert.equal(globalThis.moduleRuns, 1);
    assert.equal(globalThis.rootState, globalThis.directoryState);
    assert.equal(globalThis.rootState, globalThis.pageState);
    assert.equal(globalThis.pageState, (await page.loaded).state);
    assert.equal(globalThis.pageState.value, 42);
    put('src/Scripts/state.ts', 'export const state = { value: 99 };');
    await build(root, true);
    assert.equal(fs.existsSync(chunk.path), false);
    assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/pages/_chunks/manual.js'), 'utf8'), 'manual');
  } finally {
    for (const key of ['moduleRuns', 'rootState', 'directoryState', 'pageState']) delete globalThis[key];
  }
});

test('page entries without imports have independent TypeScript module scopes', async t => {
  const { root, put } = fixture(t);
  put('nodejs/tsconfig.json', fs.readFileSync(new URL('../tsconfig.json', import.meta.url), 'utf8'));
  put('src/Views/_script.ts', 'const value: string = "directory"; console.log(value);');
  put('src/Views/about/index.ts', 'const value: string = "page"; console.log(value);');
  await checkTypes(root);
  assert.equal((await build(root)).length, 2);
});

test('renamed and removed entries clean only recorded generated bundles', async t => {
  const { root, put } = fixture(t);
  put('src/Views/old/index.ts', 'console.log("old");');
  put('public/assets/js/pages/manual.js', 'manual');
  await build(root);
  fs.renameSync(path.join(root, 'src/Views/old'), path.join(root, 'src/Views/new'));
  await build(root);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/pages/old/index.js')), false);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/pages/new/index.js')), true);
  fs.unlinkSync(path.join(root, 'src/Views/new/index.get.php'));
  await build(root);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/pages/new/index.js')), false);
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/pages/manual.js'), 'utf8'), 'manual');
});

test('invalid cleanup paths are rejected before publishing or deleting assets', async t => {
  const { root, put } = fixture(t);
  put('src/Views/index.ts', 'console.log("first");');
  await build(root);
  const bundle = path.join(root, 'public/assets/js/pages/index.js');
  const previous = fs.readFileSync(bundle, 'utf8');
  put('public/assets/js/outside.js', 'manual');
  put('public/assets/js/pages/.generated.json', '["../outside.js"]');
  put('src/Views/index.ts', 'console.log("changed");');
  await assert.rejects(build(root), /escapes/);
  assert.equal(fs.readFileSync(bundle, 'utf8'), previous);
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/outside.js'), 'utf8'), 'manual');
});


test('stale cleanup refuses symlinks before replacing any current bundle', async t => {
  const { root, put } = fixture(t);
  put('src/Views/index.ts', 'console.log("before");');
  put('src/Views/old/index.ts', 'console.log("old");');
  await build(root);
  const bundle = path.join(root, 'public/assets/js/pages/index.js');
  const previous = fs.readFileSync(bundle, 'utf8');
  const stale = path.join(root, 'public/assets/js/pages/old/index.js');
  fs.unlinkSync(stale);
  put('outside.js', 'manual');
  try { fs.symlinkSync(path.join(root, 'outside.js'), stale); }
  catch (error) {
    if (['EPERM', 'EACCES'].includes(error.code)) return t.skip('Symlink creation requires host privileges');
    throw error;
  }
  fs.unlinkSync(path.join(root, 'src/Views/old/index.ts'));
  put('src/Views/index.ts', 'console.log("after");');
  await assert.rejects(build(root), /symlink/);
  assert.equal(fs.readFileSync(bundle, 'utf8'), previous);
  assert.equal(fs.readFileSync(path.join(root, 'outside.js'), 'utf8'), 'manual');
});

async function until(check, message) {
  const deadline = Date.now() + 15000;
  while (!check()) {
    if (Date.now() > deadline) throw new Error('Timed out: ' + message);
    await new Promise(resolve => setTimeout(resolve, 50));
  }
}

test('watch discovers new entries, follows shared edits, preserves type-error output and removes stale files', async t => {
  const { root, put } = fixture(t);
  put('nodejs/tsconfig.json', JSON.stringify({ compilerOptions: { strict: true, noEmit: true, skipLibCheck: true }, include: ['../src/Views', '../src/Scripts'] }));
  const errors = [];
  const originalError = console.error;
  console.error = message => { errors.push(message); originalError(message); };
  const watcher = await watch(root);
  try {
    put('src/Scripts/helper.ts', 'export const value: string = "before";');
    put('src/Views/new/index.ts', 'import { value } from "../../Scripts/helper"; console.log(value);');
    const file = path.join(root, 'public/assets/js/pages/new/index.js');
    await until(() => fs.existsSync(file), 'new entry');
    assert.match(fs.readFileSync(file, 'utf8'), /before/);
    put('src/Scripts/helper.ts', 'export const value: string = 42;');
    await until(() => errors.includes('TypeScript check failed'), 'type error');
    assert.match(fs.readFileSync(file, 'utf8'), /before/);
    put('src/Scripts/helper.ts', 'export const value: string = "after";');
    await until(() => fs.readFileSync(file, 'utf8').includes('after'), 'shared edit recovery');
    fs.renameSync(path.join(root, 'src/Views/new'), path.join(root, 'src/Views/renamed'));
    const renamed = path.join(root, 'public/assets/js/pages/renamed/index.js');
    await until(() => fs.existsSync(renamed) && !fs.existsSync(file), 'renamed directory');
    fs.unlinkSync(path.join(root, 'src/Views/renamed/index.ts'));
    fs.unlinkSync(path.join(root, 'src/Scripts/helper.ts'));
    await until(() => !fs.existsSync(renamed), 'removed entry');
  } finally {
    await watcher.dispose();
    console.error = originalError;
  }
});

test('Tailwind scans views and shared scripts without build-tool fixtures', async t => {
  const { root, put } = fixture(t);
  const input = fs.readFileSync(new URL('../../public/assets/css/input.css', import.meta.url), 'utf8')
    .replace('@import "../../../nodejs/node_modules/tailwindcss"', '@import ' + JSON.stringify(fileURLToPath(new URL('../node_modules/tailwindcss', import.meta.url)).replaceAll('\\', '/')))
    .replace('@config "../../../nodejs/tailwind.config.js";', '');
  put('public/assets/css/input.css', input);
  put('src/Views/index.get.php', '<div class="p-[137px]"></div>');
  put('src/Scripts/menu.ts', 'const className = "m-[139px]";');
  put('nodejs/fixture.ts', 'const className = "p-[149px]";');
  const cli = fileURLToPath(new URL('../node_modules/@tailwindcss/cli/dist/index.mjs', import.meta.url));
  const result = spawnSync(process.execPath, [cli, '-i', path.join(root, 'public/assets/css/input.css'), '-o', path.join(root, 'output.css')], { cwd: root, encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  const css = fs.readFileSync(path.join(root, 'output.css'), 'utf8');
  assert.match(css, /padding: 137px/);
  assert.match(css, /margin: 139px/);
  assert.doesNotMatch(css, /padding: 149px/);
});

test('watch-all closes both Node children when its input closes', async t => {
  const { root, put } = fixture(t);
  put('watch.all.js', fs.readFileSync(new URL('../watch.all.js', import.meta.url), 'utf8'));
  put('package.json', '{"type":"module"}');
  const childSource = 'console.log("ready:" + process.pid); setInterval(() => {}, 1000);';
  put('build.js', childSource);
  put('node_modules/@tailwindcss/cli/package.json', '{"bin":{"tailwindcss":"dist/index.mjs"}}');
  put('node_modules/@tailwindcss/cli/dist/index.mjs', childSource);
  const child = spawn(process.execPath, [path.join(root, 'watch.all.js')], { cwd: root, stdio: ['pipe', 'pipe', 'pipe'] });
  let output = '';
  child.stdout.on('data', chunk => { output += chunk; });
  child.stderr.on('data', chunk => { output += chunk; });
  const exited = new Promise((resolve, reject) => { child.once('error', reject); child.once('exit', resolve); });
  let pids = [];
  try {
    await until(() => [...output.matchAll(/ready:(\d+)/g)].length === 2, 'watcher children');
    pids = [...output.matchAll(/ready:(\d+)/g)].map(match => Number(match[1]));
    child.stdin.end();
    assert.equal(await exited, 0, output);
    for (const pid of pids) assert.throws(() => process.kill(pid, 0), { code: 'ESRCH' });
  } finally {
    child.kill();
    for (const pid of pids) { try { process.kill(pid); } catch {} }
    await exited;
  }
});
