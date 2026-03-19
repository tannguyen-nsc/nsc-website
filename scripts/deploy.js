/**
 * Deploy frontend build and NSC theme frontend to remote via ssh nsc.
 *
 * 1) Copies frontend/build (exclude index.html, test.html) to nsc:/var/www/html/
 * 2) Copies wp-content/themes/NSC-Software/frontend/build (exclude test.html)
 *    to nsc:/var/www/html/wp-content/themes/NSC-Software/frontend
 *
 * Prerequisites: rsync in PATH, ssh host "nsc" configured (e.g. in ~/.ssh/config)
 * Run from repo root: node scripts/deploy.js
 * Or set DEPLOY_SSH_HOST to use a different host (default: nsc).
 */
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

const repoRoot = path.resolve(__dirname, '..');
const frontendBuild = path.join(repoRoot, 'frontend', 'build');
const themeBuild = path.join(repoRoot, 'wp-content', 'themes', 'NSC-Software', 'frontend', 'build');
const sshHost = process.env.DEPLOY_SSH_HOST || 'nsc';
const remoteWebroot = '/var/www/html';

if (!fs.existsSync(frontendBuild)) {
  console.error('Error: frontend build not found at', frontendBuild);
  console.error('Run "npm run build" in the frontend folder first.');
  process.exit(1);
}
if (!fs.existsSync(themeBuild)) {
  console.error('Error: theme frontend build not found at', themeBuild);
  console.error('Run "npm run build:sync" in the frontend folder first.');
  process.exit(1);
}

function rsync(srcDir, dest, excludes) {
  const excludeArgs = excludes.map((f) => `--exclude=${f}`).join(' ');
  const cmd = `rsync -avz --delete ${excludeArgs} "${srcDir}/" "${sshHost}:${dest}"`;
  execSync(cmd, { stdio: 'inherit', shell: true });
}

console.log('Deploying to', sshHost + ':' + remoteWebroot, '...\n');

console.log('[1/2] Frontend build -> ' + remoteWebroot + '/ (excluding index.html, test.html)');
rsync(frontendBuild, remoteWebroot, ['index.html', 'test.html']);

console.log('\n[2/2] NSC theme frontend build -> ' + remoteWebroot + '/wp-content/themes/NSC-Software/frontend/ (excluding test.html)');
rsync(themeBuild, remoteWebroot + '/wp-content/themes/NSC-Software/frontend', ['test.html']);

console.log('\nDeploy done.');
