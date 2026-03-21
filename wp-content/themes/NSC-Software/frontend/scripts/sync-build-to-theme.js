/**
 * Syncs frontend/build (root) to wp-content/themes/NSC-Software/frontend/build.
 * Excludes home.html (build-only; theme pages use components / static HTML templates).
 *
 * Run from frontend: npm run sync
 * Or: node scripts/sync-build-to-theme.js
 * Full build + sync: npm run build:sync
 */
const fs = require('fs');
const path = require('path');

const BUILD_ROOT_FILE_TO_SKIP = 'home.html';

const repoRoot = path.resolve(__dirname, '..', '..');
const src = path.join(repoRoot, 'frontend', 'build');
const dest = path.join(repoRoot, 'wp-content', 'themes', 'NSC-Software', 'frontend', 'build');

if (!fs.existsSync(src)) {
  console.error('Source build folder not found:', src);
  console.error('Run "npm run build" in the frontend folder first.');
  process.exit(1);
}

function copyRecursiveSync(srcDir, destDir, buildRoot) {
  const root = buildRoot || srcDir;
  if (!fs.existsSync(destDir)) {
    fs.mkdirSync(destDir, { recursive: true });
  }
  const entries = fs.readdirSync(srcDir, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(srcDir, entry.name);
    const destPath = path.join(destDir, entry.name);
    if (entry.isDirectory()) {
      copyRecursiveSync(srcPath, destPath, root);
    } else {
      if (srcDir === root && entry.name === BUILD_ROOT_FILE_TO_SKIP) continue;
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

// Clear destination then copy so removed files don't linger
if (fs.existsSync(dest)) {
  fs.rmSync(dest, { recursive: true });
}
fs.mkdirSync(dest, { recursive: true });
copyRecursiveSync(src, dest, src);

// Ensure home.html is not present (e.g. from an old sync)
const homeInDest = path.join(dest, BUILD_ROOT_FILE_TO_SKIP);
if (fs.existsSync(homeInDest)) {
  fs.unlinkSync(homeInDest);
}

console.log('Synced', src, '->', dest, '(excluded', BUILD_ROOT_FILE_TO_SKIP + ')');
