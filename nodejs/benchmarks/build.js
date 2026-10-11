import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { gzipSync } from 'node:zlib';
import { performance } from 'node:perf_hooks';
import { pathToFileURL } from 'node:url';
import { version } from 'esbuild';
import { build } from '../build.js';

const root = fs.mkdtempSync(path.join(os.tmpdir(), 'coriander-build-benchmark-'));
const report = {
  description: 'Warm esbuild rebuilds including discovery and publishing; type checking, npm/process startup, Tailwind and browser execution excluded.',
  source_commit: process.env.GITHUB_SHA ?? null,
  environment: { node: process.version, esbuild: version, os: os.platform(), release: os.release(), arch: os.arch() },
  cases: [],
};
try {
  for (const count of [1, 20, 100]) {
    const directory = path.join(root, String(count));
    const put = (name, code) => {
      const file = path.join(directory, name);
      fs.mkdirSync(path.dirname(file), { recursive: true });
      fs.writeFileSync(file, code);
    };
    put('package.json', '{"type":"module"}');
    put('src/Scripts/menu.ts', 'export function menu(title: string) { return "menu:" + title; }');
    put('src/Views/_script.ts', 'globalThis.benchmarkGlobal = "global-behavior";');
    for (let page = 0; page < count; page++) {
      put('src/Views/page' + page + '/index.get.php', '<?php throw new RuntimeException("must not execute");');
      put('src/Views/page' + page + '/index.ts', 'import { menu } from "../../Scripts/menu"; globalThis.benchmarkPage = menu("page' + page + '");');
    }
    await build(directory, true);
    const samples = [];
    let files;
    for (let sample = 0; sample < 3; sample++) {
      const start = performance.now();
      files = await build(directory, true);
      samples.push(performance.now() - start);
      if (files.length < count + 1 || files.some(file => !file.contents.length)) {
        throw new Error('Benchmark bundle verification failed');
      }
    }
    samples.sort((a, b) => a - b);
    const global = files.find(file => path.basename(file.path) === '_script.js');
    const page = files.find(file => file.path === path.join(directory, 'public/assets/js/pages/page0/index.js'));
    for (let index = 0; index < count; index++) {
      if (!files.some(file => file.path === path.join(directory, 'public/assets/js/pages/page' + index + '/index.js'))) {
        throw new Error('Benchmark page entry missing');
      }
    }
    await import(pathToFileURL(global.path));
    await import(pathToFileURL(page.path));
    if (globalThis.benchmarkGlobal !== 'global-behavior' || globalThis.benchmarkPage !== 'menu:page0') {
      throw new Error('Benchmark module execution failed');
    }
    // Every page in this fixture imports the same helper, so all shared chunks belong to this load.
    const loaded = [global, page, ...files.filter(file => path.dirname(file.path).endsWith('_chunks'))];
    report.cases.push({
      pages: count, samples: 3, median_build_ms: samples[1], max_build_ms: samples[2],
      bundle_count: files.length, total_js_bytes: files.reduce((total, file) => total + file.contents.length, 0),
      total_gzip_bytes: files.reduce((total, file) => total + gzipSync(file.contents).length, 0),
      one_page_js_bytes: loaded.reduce((total, file) => total + file.contents.length, 0),
      one_page_gzip_bytes: loaded.reduce((total, file) => total + gzipSync(file.contents).length, 0),
    });
  }
  console.log(JSON.stringify(report, null, 2));
} finally {
  delete globalThis.benchmarkGlobal;
  delete globalThis.benchmarkPage;
  if (!root.startsWith(path.join(os.tmpdir(), 'coriander-build-benchmark-'))) throw new Error('Unexpected benchmark cleanup path');
  fs.rmSync(root, { recursive: true, force: true });
}
