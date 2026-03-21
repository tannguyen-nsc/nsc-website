/**
 * Copy repo-root frontend/ → wp-content/themes/NSC-Software/frontend/
 * Excludes node_modules.
 *
 * Windows: robocopy (handles long paths / odd names better than fs.cpSync).
 * Other: rsync -a (no --delete) or falls back to fs.cpSync.
 *
 * From frontend/: npm run sync:theme-dir
 */
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const os = require('os');

const repoRoot = path.resolve(__dirname, '..', '..');
const src = path.join(repoRoot, 'frontend');
const dest = path.join(repoRoot, 'wp-content', 'themes', 'NSC-Software', 'frontend');

if (!fs.existsSync(src)) {
  console.error('Source not found:', src);
  process.exit(1);
}

function robocopyOk(exitCode) {
  // Robocopy: 0 = nothing copied, 1–7 = success, ≥8 = failure
  return exitCode === undefined || exitCode < 8;
}

if (os.platform() === 'win32') {
  const cmd = `robocopy "${src}" "${dest}" /E /XD node_modules /NFL /NDL /NJH /NJS /NC /NS /R:1 /W:1`;
  let status = 0;
  try {
    execSync(cmd, { stdio: 'inherit', shell: true });
  } catch (e) {
    status = typeof e.status === 'number' ? e.status : 8;
  }
  if (!robocopyOk(status)) {
    console.error('robocopy failed with code', status);
    process.exit(1);
  }
  console.log('Synced', src, '->', dest, '(robocopy, excluded node_modules)');
  process.exit(0);
}

try {
  execSync(`rsync -a --exclude=node_modules "${src}/" "${dest}/"`, {
    stdio: 'inherit',
    shell: true,
  });
  console.log('Synced', src, '->', dest, '(rsync)');
} catch {
  fs.mkdirSync(dest, { recursive: true });
  const filter = (p) => {
    const rel = path.relative(src, p);
    if (!rel || rel === '.') return true;
    return !rel.split(path.sep).includes('node_modules');
  };
  fs.cpSync(src, dest, { recursive: true, force: true, filter });
  console.log('Synced', src, '->', dest, '(fs.cpSync fallback)');
}
