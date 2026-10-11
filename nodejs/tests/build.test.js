import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { build } from '../build.js';

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
  put('src/Views/home/index.ts', 'console.log("after");');
  put('src/Views/broken/index.ts', 'const = invalid;');
  await assert.rejects(build(root));
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/pages/home/index.js'), 'utf8'), previous);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/pages/broken/index.js')), false);
});

test('empty source works without an output directory', async t => {
  const { root } = fixture(t);
  assert.deepEqual(await build(root), []);
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
  assert.equal(files.length, 2);
  for (const file of files) {
    const text = Buffer.from(file.contents).toString();
    assert.match(text, /menu:/);
    assert.match(text, /\/users\//);
    assert.doesNotMatch(text, /PageState|types\.ts/);
  }
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
