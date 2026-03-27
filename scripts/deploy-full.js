/**
 * Full NSC deploy: prepare build, then push frontend build, seeder assets, and full theme
 * to production and (optionally) dev paths on the remote server.
 *
 * Step 1 — Prepare: `npm run build:sync` in /frontend (build + sync to theme frontend/build).
 * Step 2 — Frontend: `frontend/build` → remote webroot (excludes index.html, test.html).
 * Step 3 — Seeders/tools: `policy-content/`, `tools/`, root `create-nsc-*.php`, `run-nsc-wpscan.php` → remote webroot.
 * Step 4 — Theme: `wp-content/themes/NSC-Software` → remote `.../wp-content/themes/NSC-Software`.
 * Step 5 — Dev: repeat steps 2–4 for `DEPLOY_REMOTE_DEV_ROOT` (default `/var/www/html/dev`).
 *
 * Usage (from repo):  node scripts/deploy-full.js
 * From frontend:      npm run deploy
 *
 * Env:
 *   DEPLOY_SSH_HOST          SSH host alias or user@host (default: nsc).
 *   DEPLOY_REMOTE_ROOT       Production webroot (default: /var/www/html).
 *   DEPLOY_REMOTE_DEV_ROOT   Dev webroot (default: /var/www/html/dev).
 *   DEPLOY_SKIP_DEV=1        Skip step 5 (dev deploy).
 *
 * Uses rsync when available; otherwise tar+ssh for dirs and scp for files (Windows-friendly).
 */

const path = require("path");
const fs = require("fs");
const { execSync } = require("child_process");

const repoRoot = path.resolve(__dirname, "..");
const frontendDir = path.join(repoRoot, "frontend");
const frontendBuild = path.join(frontendDir, "build");
const themeDir = path.join(
  repoRoot,
  "wp-content",
  "themes",
  "NSC-Software"
);

const sshHost = process.env.DEPLOY_SSH_HOST || "nsc";
const remoteRoot = process.env.DEPLOY_REMOTE_ROOT || "/var/www/html";
const remoteDevRoot = process.env.DEPLOY_REMOTE_DEV_ROOT || "/var/www/html/dev";
const skipDev = process.env.DEPLOY_SKIP_DEV === "1" || process.env.DEPLOY_SKIP_DEV === "true";

/** Root-level PHP seed scripts (HTTP token scripts + global options). */
const SEEDER_PHP_GLOB = /^create-nsc-.*\.php$/;

/** Root-level scripts not matching create-nsc-*.php (must still land in web root). */
const ROOT_EXTRA_PHP = ["run-nsc-wpscan.php"];

function hasRsync() {
  try {
    execSync("rsync --version", { stdio: "pipe", shell: true });
    return true;
  } catch {
    return false;
  }
}

const useRsync = hasRsync();

function tarChdir(dir) {
  return path.resolve(dir).replace(/\\/g, "/");
}

/**
 * @param {string} localDir
 * @param {string} remoteDestDir absolute path on server
 * @param {string[]} excludes file names only (tar --exclude)
 */
function deployDirWithTar(localDir, remoteDestDir, excludes) {
  if (!fs.existsSync(localDir)) {
    throw new Error(`Missing local dir: ${localDir}`);
  }
  const chdir = tarChdir(localDir);
  const excludeArgs = excludes.map((f) => `--exclude=${f}`).join(" ");
  const remoteCmd = `cd ${remoteDestDir} && tar -xf -`;
  const cmd = `tar -cf - ${excludeArgs} -C "${chdir}" . | ssh ${sshHost} "${remoteCmd}"`;
  execSync(cmd, { stdio: "inherit", shell: true });
}

function deployDirWithRsync(srcDir, remoteDest, excludes) {
  const excludeArgs = excludes.map((f) => `--exclude=${f}`).join(" ");
  const cmd = `rsync -avz --delete ${excludeArgs} "${srcDir}/" "${sshHost}:${remoteDest}/"`;
  execSync(cmd, { stdio: "inherit", shell: true });
}

/**
 * @param {string} localDir
 * @param {string} remoteDestDir
 * @param {string[]} excludes
 */
function deployDirectory(localDir, remoteDestDir, excludes) {
  execSync(`ssh ${sshHost} "mkdir -p ${remoteDestDir}"`, {
    stdio: "inherit",
    shell: true,
  });
  if (useRsync) {
    deployDirWithRsync(localDir, remoteDestDir, excludes);
  } else {
    deployDirWithTar(localDir, remoteDestDir, excludes);
  }
}

/**
 * @param {string} localFile
 * @param {string} remoteDestDir directory on server (trailing path)
 */
function deployFile(localFile, remoteDestDir) {
  if (!fs.existsSync(localFile)) {
    throw new Error(`Missing file: ${localFile}`);
  }
  const base = path.basename(localFile);
  const remote = `${remoteDestDir.replace(/\/$/, "")}/${base}`;
  execSync(`scp "${localFile}" ${sshHost}:${remote}`, {
    stdio: "inherit",
    shell: true,
  });
}

function stepPrepare() {
  console.log("\n========== Step 1 — Prepare (build + sync to theme) ==========\n");
  execSync("npm run build:sync", {
    cwd: frontendDir,
    stdio: "inherit",
    shell: true,
  });
  if (!fs.existsSync(frontendBuild)) {
    throw new Error("frontend/build missing after build:sync");
  }
  if (!fs.existsSync(themeDir)) {
    throw new Error("Theme directory missing: " + themeDir);
  }
  console.log("Prepare done.\n");
}

function listSeederPhpFiles() {
  const names = fs.readdirSync(repoRoot);
  return names.filter((n) => SEEDER_PHP_GLOB.test(n));
}

function stepFrontend(remoteBase) {
  const label = remoteBase === remoteRoot ? "production" : "dev";
  console.log(
    `\n--- Frontend build → ${remoteBase}/ (${label}, exclude index.html, test.html) ---\n`
  );
  deployDirectory(frontendBuild, remoteBase, ["index.html", "test.html"]);
}

function stepSeeders(remoteBase) {
  console.log(
    `\n--- Seeders / tools / policy-content → ${remoteBase}/ ---\n`
  );
  const policyDir = path.join(repoRoot, "policy-content");
  const toolsDir = path.join(repoRoot, "tools");
  if (fs.existsSync(policyDir)) {
    deployDirectory(
      policyDir,
      `${remoteBase}/policy-content`,
      []
    );
  } else {
    console.warn("Warning: policy-content/ not found, skip.");
  }
  if (fs.existsSync(toolsDir)) {
    deployDirectory(toolsDir, `${remoteBase}/tools`, []);
  } else {
    console.warn("Warning: tools/ not found, skip.");
  }
  const phpFiles = listSeederPhpFiles();
  if (phpFiles.length === 0) {
    console.warn("Warning: no create-nsc-*.php at repo root.");
  } else {
    for (const f of phpFiles) {
      const full = path.join(repoRoot, f);
      console.log(`  scp ${f} → ${remoteBase}/`);
      deployFile(full, remoteBase);
    }
  }
  for (const f of ROOT_EXTRA_PHP) {
    const full = path.join(repoRoot, f);
    if (!fs.existsSync(full)) {
      console.warn(`Warning: ${f} not found at repo root, skip.`);
      continue;
    }
    console.log(`  scp ${f} → ${remoteBase}/`);
    deployFile(full, remoteBase);
  }
}

function stepTheme(remoteBase) {
  const remoteTheme = `${remoteBase}/wp-content/themes/NSC-Software`;
  console.log(`\n--- Full NSC theme → ${remoteTheme}/ ---\n`);
  const excludes = [
    "node_modules",
    ".git",
    ".DS_Store",
    "Thumbs.db",
  ];
  deployDirectory(themeDir, remoteTheme, excludes);
}

function runBlockForRemote(remoteBase, name) {
  console.log(`\n>>>>>>>>>> ${name}: ${remoteBase} <<<<<<<<<<\n`);
  stepFrontend(remoteBase);
  stepSeeders(remoteBase);
  stepTheme(remoteBase);
}

function main() {
  console.log("NSC full deploy");
  console.log("SSH host:", sshHost);
  console.log("Production root:", remoteRoot);
  if (!skipDev) {
    console.log("Dev root:", remoteDevRoot);
  } else {
    console.log("Dev deploy: skipped (DEPLOY_SKIP_DEV=1)");
  }
  console.log("rsync:", useRsync ? "yes" : "no (tar/scp)");

  stepPrepare();

  runBlockForRemote(remoteRoot, "Step 2–4 Production");

  if (!skipDev) {
    execSync(`ssh ${sshHost} "mkdir -p ${remoteDevRoot}"`, {
      stdio: "inherit",
      shell: true,
    });
    runBlockForRemote(remoteDevRoot, "Step 5 Dev");
  }

  console.log("\n========== Full deploy finished ==========\n");
}

try {
  main();
} catch (e) {
  console.error(e);
  process.exit(1);
}
