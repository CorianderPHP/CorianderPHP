import esbuild from 'esbuild';
import fs from 'fs';
import path from 'path';

// Directories containing folders to bundle and the output
const srcDirectory = path.join('src');
const outputBase = path.join('../public/assets/js');

// Function to get all index.ts files in a directory recursively
function getAllIndexTsFiles(dir, fileList = []) {
  const files = fs.readdirSync(dir, { withFileTypes: true });
  files.forEach(file => {
    if (file.isDirectory()) {
      fileList = getAllIndexTsFiles(path.join(dir, file.name), fileList);
    } else if (file.name === 'index.ts') {
      fileList.push(path.join(dir, file.name));
    }
  });
  return fileList;
}

// Function to delete folder contents
function deleteFolderContents(folderPath) {
  if (fs.existsSync(folderPath)) {
    fs.readdirSync(folderPath).forEach(file => {
      const curPath = path.join(folderPath, file);
      if (fs.lstatSync(curPath).isDirectory()) {
        deleteFolderContents(curPath);
        fs.rmdirSync(curPath);
      } else {
        fs.unlinkSync(curPath);
      }
    });
  }
}

// Function to clean up any residual folders/files in the output directory
function cleanupOutputDirectory(sourceFiles, outputBase) {
  const expectedPaths = new Set(sourceFiles.map(file =>
    path.relative(srcDirectory, path.dirname(file)).replace(/\\/g, '/')
  ));

  function recurse(currentPath) {
    fs.readdirSync(currentPath, { withFileTypes: true }).forEach(dirent => {
      const subPath = path.join(currentPath, dirent.name);
      const relativeSubPath = path.relative(outputBase, subPath).replace(/\\/g, '/');

      if (dirent.isDirectory()) {
        recurse(subPath);
        if (!expectedPaths.has(relativeSubPath)) {
          deleteFolderContents(subPath);
          fs.rmdirSync(subPath);
        }
      } else {
        if (!expectedPaths.has(relativeSubPath)) {
          fs.unlinkSync(subPath);
        }
      }
    });
  }

  recurse(outputBase);
}

// Build a bundle for each found index.ts file
const indexTsFiles = getAllIndexTsFiles(srcDirectory);
cleanupOutputDirectory(indexTsFiles, outputBase); // Clean up before starting build
indexTsFiles.forEach(entryPoint => {
  const relativePath = path.relative(srcDirectory, path.dirname(entryPoint));
  const outputFolderPath = path.join(outputBase, relativePath);

  deleteFolderContents(outputFolderPath);

  watch(outputFolderPath, entryPoint);
});

async function watch(outputFolderPath, entryPoint) {
  // Ensure output directory exists
  fs.mkdirSync(outputFolderPath, { recursive: true });

  console.log(`Starting to watch ${entryPoint}...`);

  let ctx = await esbuild.context({
    entryPoints: [entryPoint],
    bundle: true,
    outfile: path.join(outputFolderPath, 'index.js'),
    minify: true, // set to true to minify the output
    sourcemap: false, // set to true if you need a source map
    platform: 'browser', // or 'node' depending on your target
    target: ['es2020'], // target environment
  });

  try {
    await ctx.watch();
    console.log(`Watching ${entryPoint} for changes...`);
  } catch (err) {
    console.error(`Error while watching ${entryPoint}:`, err.message);
  }
}
