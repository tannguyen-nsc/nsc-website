/**
 * Minimal deploy: frontend/build + theme frontend/build only (legacy quick push).
 * Full site deploy: npm run deploy (uses deploy-full.js).
 *
 * 1) Copies frontend/build (exclude index.html, test.html) to nsc:/var/www/html/
 * 2) Copies wp-content/themes/NSC-Software/frontend/build (exclude test.html)
 *    to nsc:/var/www/html/wp-content/themes/NSC-Software/frontend
 *
 * Uses rsync when available; otherwise tar + ssh (works on Windows with OpenSSH).
 *
 * Run from repo root: node scripts/deploy-minimal.js
 * From frontend: npm run deploy:minimal
 * Set DEPLOY_SSH_HOST to override host (default: nsc).
 */
const path = require("path");
const fs = require("fs");
const { execSync } = require("child_process");

const repoRoot = path.resolve(__dirname, "..");
const frontendBuild = path.join(repoRoot, "frontend", "build");
const themeBuild = path.join(
  repoRoot,
  "wp-content",
  "themes",
  "NSC-Software",
  "frontend",
  "build"
);
const sshHost = process.env.DEPLOY_SSH_HOST || "nsc";
const remoteWebroot = process.env.DEPLOY_REMOTE_ROOT || "/var/www/html";
const remoteThemeDir = `${remoteWebroot}/wp-content/themes/NSC-Software/frontend`;

if (!fs.existsSync(frontendBuild)) {
  console.error("Error: frontend build not found at", frontendBuild);
  console.error('Run "npm run build" in the frontend folder first.');
  process.exit(1);
}
if (!fs.existsSync(themeBuild)) {
  console.error("Error: theme frontend build not found at", themeBuild);
  console.error('Run "npm run build:sync" in the frontend folder first.');
  process.exit(1);
}

function hasRsync() {
  try {
    execSync("rsync --version", { stdio: "pipe", shell: true });
    return true;
  } catch {
    return false;
  }
}

function tarChdir(dir) {
  return path.resolve(dir).replace(/\\/g, "/");
}

function deployWithTar(localDir, remoteDestDir, excludes) {
  const chdir = tarChdir(localDir);
  const excludeArgs = excludes.map((f) => `--exclude=${f}`).join(" ");
  const remoteCmd = `cd ${remoteDestDir} && tar -xf -`;
  const cmd = `tar -cf - ${excludeArgs} -C "${chdir}" . | ssh ${sshHost} "${remoteCmd}"`;
  execSync(cmd, { stdio: "inherit", shell: true });
}

function deployWithRsync(srcDir, dest, excludes) {
  const excludeArgs = excludes.map((f) => `--exclude=${f}`).join(" ");
  const cmd = `rsync -avz --delete ${excludeArgs} "${srcDir}/" "${sshHost}:${dest}/"`;
  execSync(cmd, { stdio: "inherit", shell: true });
}

const useRsync = hasRsync();
if (useRsync) {
  console.log("Using rsync for deploy.\n");
} else {
  console.log("rsync not found; using tar + ssh (Windows-friendly).\n");
}

console.log("Deploying to", sshHost + ":" + remoteWebroot, "...\n");

console.log(
  "[1/2] Frontend build -> " + remoteWebroot + "/ (excluding index.html, test.html)"
);
if (useRsync) {
  deployWithRsync(frontendBuild, remoteWebroot, ["index.html", "test.html"]);
} else {
  deployWithTar(frontendBuild, remoteWebroot, ["index.html", "test.html"]);
}

console.log(
  "\n[2/2] NSC theme frontend build -> " + remoteThemeDir + "/ (excluding test.html)"
);
if (useRsync) {
  deployWithRsync(themeBuild, remoteThemeDir, ["test.html"]);
} else {
  execSync(`ssh ${sshHost} "mkdir -p ${remoteThemeDir}"`, {
    stdio: "inherit",
    shell: true,
  });
  deployWithTar(themeBuild, remoteThemeDir, ["test.html"]);
}

console.log("\nDeploy done.");
