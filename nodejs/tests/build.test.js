import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
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
  };
  return { root, put };
}

test('builds nested entries and preserves manual assets', async t => {
  const { root, put } = fixture(t);
  put('nodejs/src/admin/users/index.ts', 'import { value } from "../helper"; console.log(value);');
  put('nodejs/src/admin/helper.ts', 'export const value: string = "shared-value";');
  put('public/assets/js/manual.js', 'manual');
  const files = await build(root);
  assert.equal(files.length, 1);
  assert.match(fs.readFileSync(path.join(root, 'public/assets/js/admin/users/index.js'), 'utf8'), /shared-value/);
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/manual.js'), 'utf8'), 'manual');
  await build(root, true);
  assert.ok(fs.statSync(files[0].path).size < files[0].contents.length);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/admin/helper.js')), false);
});

test('failed compilation preserves all previous bundles', async t => {
  const { root, put } = fixture(t);
  put('nodejs/src/home/index.ts', 'console.log("before");');
  await build(root);
  const previous = fs.readFileSync(path.join(root, 'public/assets/js/home/index.js'), 'utf8');
  put('nodejs/src/home/index.ts', 'console.log("after");');
  put('nodejs/src/broken/index.ts', 'const = invalid;');
  await assert.rejects(build(root));
  assert.equal(fs.readFileSync(path.join(root, 'public/assets/js/home/index.js'), 'utf8'), previous);
  assert.equal(fs.existsSync(path.join(root, 'public/assets/js/broken/index.js')), false);
});

test('empty source works without an output directory', async t => {
  const { root } = fixture(t);
  assert.deepEqual(await build(root), []);
});

test('generated output cannot follow a destination symlink', async t => {
  const { root, put } = fixture(t);
  put('nodejs/src/home/index.ts', 'console.log("generated");');
  put('outside/index.js', 'untouched');
  fs.mkdirSync(path.join(root, 'public/assets/js/home'), { recursive: true });
  try {
    fs.symlinkSync(path.join(root, 'outside/index.js'), path.join(root, 'public/assets/js/home/index.js'));
  } catch (error) {
    if (['EPERM', 'EACCES'].includes(error.code)) return t.skip('Symlink creation requires host privileges');
    throw error;
  }
  await assert.rejects(build(root), /symlink/);
  assert.equal(fs.readFileSync(path.join(root, 'outside/index.js'), 'utf8'), 'untouched');
});
